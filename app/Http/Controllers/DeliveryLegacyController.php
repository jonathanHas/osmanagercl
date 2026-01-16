<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DeliveryLegacyController extends Controller
{
    /**
     * Display the delivery/supplier selection page.
     */
    public function index()
    {
        // Get list of suppliers
        $suppliers = DB::connection('pos')
            ->table('suppliers')
            ->orderBy('Supplier')
            ->get();

        // Get distinct delivery scan sessions with supplier info
        // deliveriesScan.supID links to suppliers.SupplierID
        $scanSessions = DB::connection('pos')
            ->table('deliveriesScan')
            ->select(
                'deliveriesScan.ID',
                'deliveriesScan.supID',
                'deliveriesScan.dateUpload',
                'deliveriesScan.status',
                'suppliers.Supplier'
            )
            ->leftJoin('suppliers', 'deliveriesScan.supID', '=', 'suppliers.SupplierID')
            ->orderByDesc('dateUpload')
            ->limit(100)
            ->get();

        // Get count of items per scan session
        $scanItemCounts = DB::connection('pos')
            ->table('deliveriesScanItems')
            ->select('delID', DB::raw('COUNT(*) as item_count'), DB::raw('SUM(quantity) as total_qty'))
            ->groupBy('delID')
            ->pluck('item_count', 'delID')
            ->toArray();

        return view('delivery-legacy.index', compact('suppliers', 'scanSessions', 'scanItemCounts'));
    }

    /**
     * Display the invoice/delivery match view.
     */
    public function match(Request $request)
    {
        $deliveryId = $request->query('delID');
        $supplierId = $request->query('supplierID');

        if (! $deliveryId || ! $supplierId) {
            return redirect()->route('delivery-legacy.index')
                ->with('error', 'Please select a delivery and supplier.');
        }

        // Query 1: Main matched items (invoice + scan data)
        $matchedItems = $this->getMatchedItems($deliveryId, $supplierId);

        // Query 2: Scanned but NOT on invoice
        $scannedNotOnInvoice = $this->getScannedNotOnInvoice($deliveryId, $supplierId);

        // Query 3: On invoice but NOT scanned
        $onInvoiceNotScanned = $this->getOnInvoiceNotScanned($deliveryId, $supplierId);

        // Get supplier info
        $supplier = DB::connection('pos')
            ->table('suppliers')
            ->where('SupplierID', $supplierId)
            ->first();

        // Check if this is UDEA supplier (needs 15% delivery charge adjustment)
        $udeaIds = config('suppliers.external_links.udea.supplier_ids', [5, 44, 85]);
        $isUdea = in_array((int) $supplierId, $udeaIds) || in_array($supplierId, array_map('strval', $udeaIds));

        return view('delivery-legacy.match', compact(
            'matchedItems',
            'scannedNotOnInvoice',
            'onInvoiceNotScanned',
            'supplier',
            'deliveryId',
            'supplierId',
            'isUdea'
        ));
    }

    /**
     * Get matched items between invoice and scans.
     * Replicates the main query from the legacy PHP page.
     * Note: delivery table doesn't have SupplierID - filtering via supplier_link
     */
    private function getMatchedItems(string $deliveryId, string $supplierId): array
    {
        $sql = 'SELECT
                    prodName,
                    supCode,
                    supplier_link.Barcode,
                    rrPrice,
                    MIN(delivery.cost) as cost,
                    PRICEBUY,
                    PRICESELL,
                    RATE,
                    SUM(myOrder) as myOrder,
                    STOCKCURRENT.UNITS,
                    (PRICESELL - MIN(delivery.cost)) / NULLIF(PRICESELL, 0) AS margin,
                    supplier_link.CaseUnits,
                    delivery.caseUnits as invoiceCaseUnits,
                    b.barcode as scannedBarcode,
                    b.scanned
                FROM delivery
                LEFT JOIN supplier_link ON delivery.supCode = supplier_link.SupplierCode
                    AND supplier_link.SupplierID = ?
                LEFT JOIN PRODUCTS ON supplier_link.Barcode = PRODUCTS.CODE
                LEFT JOIN TAXES ON PRODUCTS.TAXCAT = TAXES.ID
                LEFT JOIN STOCKCURRENT ON PRODUCTS.ID = STOCKCURRENT.PRODUCT
                LEFT JOIN (
                    SELECT deliveriesScanItems.barcode, SUM(quantity) as scanned
                    FROM deliveriesScanItems
                    WHERE delID = ?
                    GROUP BY barcode
                ) b ON b.barcode = supplier_link.Barcode
                WHERE supplier_link.SupplierID = ?
                GROUP BY prodName, supCode, supplier_link.Barcode, rrPrice, PRICEBUY, PRICESELL,
                         RATE, delivery.caseUnits, supplier_link.CaseUnits, b.barcode, b.scanned, UNITS
                ORDER BY scanned DESC, margin ASC';

        $results = DB::connection('pos')->select($sql, [$supplierId, $deliveryId, $supplierId]);

        return $results;
    }

    /**
     * Get items that were scanned but NOT on the invoice.
     * These are barcodes scanned that don't match any supplier_link record for this supplier.
     * Also includes the supplier code from supplier_link if one exists for this supplier.
     */
    private function getScannedNotOnInvoice(string $deliveryId, string $supplierId): array
    {
        $sql = 'SELECT
                    deliveriesScanItems.barcode as Barcode,
                    SUM(deliveriesScanItems.quantity) as scanned,
                    PRODUCTS.NAME,
                    PRODUCTS.PRICESELL,
                    TAXES.RATE,
                    sl.SupplierCode
                FROM deliveriesScanItems
                LEFT JOIN PRODUCTS ON PRODUCTS.CODE = deliveriesScanItems.barcode
                LEFT JOIN TAXES ON PRODUCTS.TAXCAT = TAXES.ID
                LEFT JOIN supplier_link sl ON sl.Barcode = deliveriesScanItems.barcode
                    AND sl.SupplierID = ?
                WHERE deliveriesScanItems.delID = ?
                AND deliveriesScanItems.barcode NOT IN (
                    SELECT COALESCE(supplier_link.Barcode, \'\')
                    FROM delivery
                    LEFT JOIN supplier_link ON delivery.supCode = supplier_link.SupplierCode
                        AND supplier_link.SupplierID = ?
                    WHERE supplier_link.SupplierID = ?
                    AND supplier_link.Barcode IS NOT NULL
                    GROUP BY supplier_link.Barcode
                )
                GROUP BY deliveriesScanItems.barcode, PRODUCTS.NAME, PRODUCTS.PRICESELL, TAXES.RATE, sl.SupplierCode
                ORDER BY PRODUCTS.NAME';

        $results = DB::connection('pos')->select($sql, [$supplierId, $deliveryId, $supplierId, $supplierId]);

        return $results;
    }

    /**
     * Get items that are on the invoice but NOT scanned.
     * Uses supplier_link to map delivery supCode to barcodes,
     * then checks if those barcodes were scanned.
     */
    private function getOnInvoiceNotScanned(string $deliveryId, string $supplierId): array
    {
        // Get all supplier codes from the delivery that have a supplier_link for this supplier
        // but whose corresponding barcode was not scanned
        $sql = 'SELECT
                    delivery.supCode,
                    delivery.prodName,
                    SUM(delivery.myOrder) as myOrder,
                    MIN(delivery.cost) as cost,
                    delivery.caseUnits
                FROM delivery
                INNER JOIN supplier_link ON delivery.supCode = supplier_link.SupplierCode
                    AND supplier_link.SupplierID = ?
                WHERE supplier_link.Barcode NOT IN (
                    SELECT barcode
                    FROM deliveriesScanItems
                    WHERE delID = ?
                )
                GROUP BY delivery.supCode, delivery.prodName, delivery.caseUnits
                ORDER BY delivery.prodName ASC';

        $results = DB::connection('pos')->select($sql, [$supplierId, $deliveryId]);

        return $results;
    }
}
