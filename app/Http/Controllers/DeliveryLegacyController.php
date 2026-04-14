<?php

namespace App\Http\Controllers;

use App\Models\Delivery;
use App\Models\DeliveryScanItem;
use App\Services\SupplierService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DeliveryLegacyController extends Controller
{
    private SupplierService $supplierService;

    public function __construct(SupplierService $supplierService)
    {
        $this->supplierService = $supplierService;
    }

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

        // Get synced delivery with documents (if any)
        // Using database table instead of cache to survive cache clears during deployment
        $syncedDelivery = null;
        $syncedDeliveryId = DB::table('app_settings')
            ->where('key', 'legacy_synced_delivery_id')
            ->value('value');
        if ($syncedDeliveryId) {
            $syncedDelivery = Delivery::with(['documents', 'supplier'])->find($syncedDeliveryId);
        }

        return view('delivery-legacy.index', compact('suppliers', 'scanSessions', 'scanItemCounts', 'syncedDelivery'));
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

        // Calculate financial summaries for dashboard
        $financials = $this->calculateFinancials($matchedItems, $scannedNotOnInvoice, $onInvoiceNotScanned, $isUdea);

        // Calculate stock preview for update confirmation
        $stockPreview = $this->calculateStockPreview($matchedItems, $scannedNotOnInvoice);

        // Check if delivery is completed
        $scanSession = DB::connection('pos')->table('deliveriesScan')
            ->where('ID', $deliveryId)
            ->first();
        $isCompleted = $scanSession && $scanSession->status == 1;

        // Get synced delivery with documents (if any)
        // Using database table instead of cache to survive cache clears during deployment
        $syncedDelivery = null;
        $syncedDeliveryId = DB::table('app_settings')
            ->where('key', 'legacy_synced_delivery_id')
            ->value('value');
        if ($syncedDeliveryId) {
            $syncedDelivery = Delivery::with(['documents', 'supplier'])->find($syncedDeliveryId);
        }

        // Determine which supplier codes need image resolution (only for {SUPPLIER_CODE} suppliers like Independent)
        $unresolvedCodes = collect();
        if ($this->supplierService->hasExternalIntegration((int) $supplierId)
            && $this->supplierService->usesSupplierCodeImages((int) $supplierId)) {
            $allCodes = collect($matchedItems)->pluck('supCode')
                ->merge(collect($scannedNotOnInvoice)->pluck('SupplierCode'))
                ->merge(collect($onInvoiceNotScanned)->pluck('supCode'))
                ->filter()
                ->unique();

            $cachedCodes = \App\Models\SupplierImageCache::where('supplier_id', (int) $supplierId)
                ->whereIn('supplier_code', $allCodes)
                ->pluck('supplier_code');

            $unresolvedCodes = $allCodes->diff($cachedCodes)->values();
        }

        return view('delivery-legacy.match', compact(
            'matchedItems',
            'scannedNotOnInvoice',
            'onInvoiceNotScanned',
            'supplier',
            'deliveryId',
            'supplierId',
            'isUdea',
            'financials',
            'stockPreview',
            'isCompleted',
            'syncedDelivery',
            'unresolvedCodes'
        ))->with('supplierService', $this->supplierService);
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
                    b.scanned,
                    PRODUCTS.ID as productID,
                    CATEGORIES.NAME as categoryName,
                    MIN(delivery.orderNumber) as orderNumber
                FROM delivery
                LEFT JOIN supplier_link ON delivery.supCode = supplier_link.SupplierCode
                    AND supplier_link.SupplierID = ?
                LEFT JOIN PRODUCTS ON supplier_link.Barcode = PRODUCTS.CODE
                LEFT JOIN CATEGORIES ON PRODUCTS.CATEGORY = CATEGORIES.ID
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
                         RATE, delivery.caseUnits, supplier_link.CaseUnits, b.barcode, b.scanned, UNITS, PRODUCTS.ID, CATEGORIES.NAME
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
                    sl.SupplierCode,
                    sl.CaseUnits,
                    PRODUCTS.ID as productID,
                    STOCKCURRENT.UNITS,
                    CATEGORIES.NAME as categoryName
                FROM deliveriesScanItems
                LEFT JOIN PRODUCTS ON PRODUCTS.CODE = deliveriesScanItems.barcode
                LEFT JOIN CATEGORIES ON PRODUCTS.CATEGORY = CATEGORIES.ID
                LEFT JOIN TAXES ON PRODUCTS.TAXCAT = TAXES.ID
                LEFT JOIN STOCKCURRENT ON PRODUCTS.ID = STOCKCURRENT.PRODUCT
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
                GROUP BY deliveriesScanItems.barcode, PRODUCTS.NAME, PRODUCTS.PRICESELL, TAXES.RATE, sl.SupplierCode, sl.CaseUnits, PRODUCTS.ID, STOCKCURRENT.UNITS, CATEGORIES.NAME
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
                    delivery.caseUnits,
                    MIN(delivery.orderNumber) as orderNumber
                FROM delivery
                INNER JOIN supplier_link ON delivery.supCode = supplier_link.SupplierCode
                    AND supplier_link.SupplierID = ?
                WHERE supplier_link.Barcode NOT IN (
                    SELECT barcode
                    FROM deliveriesScanItems
                    WHERE delID = ?
                )
                GROUP BY delivery.supCode, delivery.prodName, delivery.caseUnits
                HAVING SUM(delivery.myOrder) > 0
                ORDER BY delivery.prodName ASC';

        $results = DB::connection('pos')->select($sql, [$supplierId, $deliveryId]);

        return $results;
    }

    /**
     * Calculate financial summaries for the dashboard.
     */
    private function calculateFinancials(array $matchedItems, array $scannedNotOnInvoice, array $onInvoiceNotScanned, bool $isUdea): array
    {
        $invoiceTotal = 0;
        $scannedTotal = 0;
        $verifiedCount = 0;
        $mismatchCount = 0;
        $oosCount = 0;
        $marginAlerts = 0;
        $missingValue = 0;
        $extraValue = 0;

        foreach ($matchedItems as $item) {
            $cost = $item->cost ?? 0;
            $caseUnits = $item->invoiceCaseUnits ?? 1;
            $myOrder = $item->myOrder ?? 0;
            $unitsDelivered = (fmod($myOrder, 1) == 0.0) ? $caseUnits * $myOrder : round($caseUnits * $myOrder);

            // Track OOS items (supplier didn't deliver any)
            if ($myOrder == 0) {
                $oosCount++;
            }

            $invoiceTotal += $cost * $unitsDelivered;

            if ($item->scanned !== null) {
                $scannedTotal += $cost * $item->scanned;
                if (floatval($item->scanned) == $unitsDelivered) {
                    $verifiedCount++;
                } else {
                    $mismatchCount++;
                }
            }

            // Margin alert: negative profit or margin below 15%
            $profit = $isUdea ? (($item->PRICESELL ?? 0) - ($cost * 1.15)) : (($item->PRICESELL ?? 0) - $cost);
            if ($profit < 0 || (($item->PRICESELL ?? 0) > 0 && ($profit / $item->PRICESELL) < 0.15)) {
                $marginAlerts++;
            }
        }

        // Missing items value (on invoice but not scanned)
        foreach ($onInvoiceNotScanned as $item) {
            $caseUnits = $item->caseUnits ?? 1;
            $missingValue += ($item->cost ?? 0) * $caseUnits * ($item->myOrder ?? 0);
        }

        // Extra items value (scanned but not on invoice - estimate using sell price)
        foreach ($scannedNotOnInvoice as $item) {
            $extraValue += ($item->PRICESELL ?? 0) * ($item->scanned ?? 0);
        }

        return [
            'invoiceTotal' => $invoiceTotal,
            'scannedTotal' => $scannedTotal,
            'discrepancy' => abs($invoiceTotal - $scannedTotal),
            'missingValue' => $missingValue,
            'extraValue' => $extraValue,
            'verifiedCount' => $verifiedCount,
            'mismatchCount' => $mismatchCount,
            'oosCount' => $oosCount,
            'marginAlerts' => $marginAlerts,
            'totalItems' => count($matchedItems),
            'pendingCount' => count($matchedItems) - $verifiedCount - $mismatchCount - $oosCount,
        ];
    }

    /**
     * Calculate stock preview data for the update confirmation.
     */
    private function calculateStockPreview(array $matchedItems, array $extraItems): array
    {
        $productsToUpdate = 0;
        $totalUnitsToAdd = 0;
        $productIds = [];

        foreach (array_merge($matchedItems, $extraItems) as $item) {
            if ($item->scanned !== null && $item->scanned > 0 && $item->productID) {
                $productsToUpdate++;
                $totalUnitsToAdd += $item->scanned;
                $productIds[] = $item->productID;
            }
        }

        // Get current stock total for these products
        $currentStockTotal = 0;
        if (! empty($productIds)) {
            $currentStockTotal = DB::connection('pos')->table('STOCKCURRENT')
                ->whereIn('PRODUCT', $productIds)
                ->sum('UNITS') ?? 0;
        }

        return [
            'productsToUpdate' => $productsToUpdate,
            'totalUnitsToAdd' => $totalUnitsToAdd,
            'currentStockTotal' => $currentStockTotal,
            'expectedStockTotal' => $currentStockTotal + $totalUnitsToAdd,
        ];
    }

    /**
     * Update the scanned quantity for a specific barcode in a delivery scan session.
     */
    public function updateScannedQuantity(Request $request)
    {
        $validated = $request->validate([
            'delID' => 'required|string',
            'barcode' => 'required|string',
            'quantity' => 'required|numeric|min:0',
            'supplierID' => 'required|string',
        ]);

        $delID = $validated['delID'];
        $barcode = $validated['barcode'];
        $quantity = $validated['quantity'];
        $supplierID = $validated['supplierID'];

        // Delete existing scan records for this barcode+delID and insert consolidated record
        DeliveryScanItem::where('delID', $delID)
            ->where('barcode', $barcode)
            ->delete();

        if ($quantity > 0) {
            DeliveryScanItem::create([
                'delID' => $delID,
                'barcode' => $barcode,
                'quantity' => $quantity,
            ]);
        }

        // Recalculate financials
        $matchedItems = $this->getMatchedItems($delID, $supplierID);
        $scannedNotOnInvoice = $this->getScannedNotOnInvoice($delID, $supplierID);
        $onInvoiceNotScanned = $this->getOnInvoiceNotScanned($delID, $supplierID);

        $udeaIds = config('suppliers.external_links.udea.supplier_ids', [5, 44, 85]);
        $isUdea = in_array((int) $supplierID, $udeaIds) || in_array($supplierID, array_map('strval', $udeaIds));

        $financials = $this->calculateFinancials($matchedItems, $scannedNotOnInvoice, $onInvoiceNotScanned, $isUdea);

        return response()->json([
            'success' => true,
            'quantity' => $quantity,
            'financials' => $financials,
        ]);
    }

    /**
     * Increment the scanned quantity for a barcode (used by camera scanner).
     * Unlike updateScannedQuantity which replaces, this adds to the existing total.
     */
    public function incrementScanQuantity(Request $request)
    {
        $validated = $request->validate([
            'delID' => 'required|string',
            'barcode' => 'required|string',
            'quantity' => 'nullable|numeric|min:0',
            'supplierID' => 'required|string',
        ]);

        $delID = $validated['delID'];
        $scannedBarcode = $validated['barcode'];
        $increment = $validated['quantity'] ?? 1;
        $supplierID = $validated['supplierID'];

        // Resolve barcode: check if this is an outer/case barcode
        $scanType = 'unit';
        $caseUnitsPerScan = 1;
        $resolvedBarcode = $scannedBarcode;

        // First try as a regular unit barcode
        $product = DB::connection('pos')->selectOne(
            'SELECT
                PRODUCTS.NAME as name,
                supplier_link.Barcode,
                supplier_link.SupplierCode as supplierCode,
                PRODUCTS.PRICESELL,
                CATEGORIES.NAME as categoryName,
                STOCKCURRENT.UNITS as currentStock
            FROM PRODUCTS
            LEFT JOIN supplier_link ON supplier_link.Barcode = PRODUCTS.CODE
                AND supplier_link.SupplierID = ?
            LEFT JOIN CATEGORIES ON PRODUCTS.CATEGORY = CATEGORIES.ID
            LEFT JOIN STOCKCURRENT ON PRODUCTS.ID = STOCKCURRENT.PRODUCT
            WHERE PRODUCTS.CODE = ?',
            [$supplierID, $scannedBarcode]
        );

        // If not found by unit barcode, try as an outer/case barcode
        if (! $product) {
            $outerMatch = DB::connection('pos')->selectOne(
                'SELECT
                    supplier_link.Barcode as unitBarcode,
                    supplier_link.CaseUnits,
                    supplier_link.SupplierCode as supplierCode,
                    PRODUCTS.NAME as name,
                    PRODUCTS.PRICESELL,
                    CATEGORIES.NAME as categoryName,
                    STOCKCURRENT.UNITS as currentStock
                FROM supplier_link
                JOIN PRODUCTS ON supplier_link.Barcode = PRODUCTS.CODE
                LEFT JOIN CATEGORIES ON PRODUCTS.CATEGORY = CATEGORIES.ID
                LEFT JOIN STOCKCURRENT ON PRODUCTS.ID = STOCKCURRENT.PRODUCT
                WHERE supplier_link.OuterCode = ? AND supplier_link.SupplierID = ?',
                [$scannedBarcode, $supplierID]
            );

            if ($outerMatch) {
                $product = $outerMatch;
                $scanType = 'case';
                $caseUnitsPerScan = max(1, (int) ($outerMatch->CaseUnits ?? 1));
                $resolvedBarcode = $outerMatch->unitBarcode;
            }
        }

        // Multiply increment by case units for outer barcode scans
        $effectiveIncrement = $increment * $caseUnitsPerScan;

        // Get current total for the resolved barcode in this session
        $currentTotal = DeliveryScanItem::where('delID', $delID)
            ->where('barcode', $resolvedBarcode)
            ->sum('quantity');

        $newQuantity = $currentTotal + $effectiveIncrement;

        // Only write to DB if actually incrementing (quantity > 0)
        if ($effectiveIncrement > 0) {
            DeliveryScanItem::where('delID', $delID)
                ->where('barcode', $resolvedBarcode)
                ->delete();

            DeliveryScanItem::create([
                'delID' => $delID,
                'barcode' => $resolvedBarcode,
                'quantity' => $newQuantity,
            ]);
        }

        // Check expected quantity from invoice
        $expectedQty = null;
        $matchStatus = 'unknown';

        if ($product) {
            $invoiceRow = DB::connection('pos')->selectOne(
                'SELECT SUM(delivery.myOrder) as myOrder, MIN(delivery.cost) as cost, delivery.caseUnits
                FROM delivery
                INNER JOIN supplier_link ON delivery.supCode = supplier_link.SupplierCode
                    AND supplier_link.SupplierID = ?
                WHERE supplier_link.Barcode = ?
                GROUP BY delivery.supCode, delivery.caseUnits',
                [$supplierID, $resolvedBarcode]
            );

            if ($invoiceRow) {
                $caseUnits = $invoiceRow->caseUnits ?? 1;
                $myOrder = $invoiceRow->myOrder ?? 0;
                $expectedQty = (fmod($myOrder, 1) == 0.0) ? $caseUnits * $myOrder : round($caseUnits * $myOrder);

                if ($newQuantity == $expectedQty) {
                    $matchStatus = 'verified';
                } elseif ($newQuantity < $expectedQty) {
                    $matchStatus = 'partial';
                } else {
                    $matchStatus = 'over';
                }
            } else {
                $matchStatus = 'extra';
            }
        }

        return response()->json([
            'success' => true,
            'product' => $product ? [
                'name' => $product->name,
                'barcode' => $resolvedBarcode,
                'supplierCode' => $product->supplierCode,
                'categoryName' => $product->categoryName,
                'currentStock' => $product->currentStock,
            ] : null,
            'expectedQty' => $expectedQty,
            'newQuantity' => $newQuantity,
            'matchStatus' => $matchStatus,
            'scanType' => $scanType,
            'caseUnits' => $caseUnitsPerScan,
        ]);
    }

    /**
     * Update the case units for a supplier link record.
     */
    public function updateCaseUnits(Request $request)
    {
        $validated = $request->validate([
            'barcode' => 'required|string',
            'supplierID' => 'required|string',
            'caseUnits' => 'required|numeric|min:1',
        ]);

        DB::connection('pos')->table('supplier_link')
            ->where('Barcode', $validated['barcode'])
            ->where('SupplierID', $validated['supplierID'])
            ->update(['CaseUnits' => $validated['caseUnits']]);

        return response()->json([
            'success' => true,
            'caseUnits' => $validated['caseUnits'],
        ]);
    }

    /**
     * Save an outer barcode to a supplier link record.
     */
    public function saveOuterBarcode(Request $request)
    {
        $validated = $request->validate([
            'unitBarcode' => 'required|string',
            'supplierID' => 'required|string',
            'outerCode' => 'required|string',
        ]);

        // Parse GS1-128 outer code to extract clean GTIN-14
        $outerCode = $this->parseGS1OuterCode($validated['outerCode']);

        // Verify the unit barcode exists for this supplier
        $link = DB::connection('pos')->table('supplier_link')
            ->where('Barcode', $validated['unitBarcode'])
            ->where('SupplierID', $validated['supplierID'])
            ->first();

        if (! $link) {
            return response()->json(['success' => false, 'message' => 'Product not found for this supplier.'], 404);
        }

        // Check the outer code isn't already assigned to another product for this supplier
        $existing = DB::connection('pos')->table('supplier_link')
            ->where('OuterCode', $outerCode)
            ->where('SupplierID', $validated['supplierID'])
            ->where('Barcode', '!=', $validated['unitBarcode'])
            ->first();

        if ($existing) {
            $productName = DB::connection('pos')->table('PRODUCTS')
                ->where('CODE', $existing->Barcode)
                ->value('NAME');

            return response()->json([
                'success' => false,
                'message' => "This outer barcode is already assigned to: {$productName} ({$existing->Barcode})",
            ], 409);
        }

        DB::connection('pos')->table('supplier_link')
            ->where('Barcode', $validated['unitBarcode'])
            ->where('SupplierID', $validated['supplierID'])
            ->update(['OuterCode' => $outerCode]);

        // Get the product name for confirmation
        $productName = DB::connection('pos')->table('PRODUCTS')
            ->where('CODE', $validated['unitBarcode'])
            ->value('NAME');

        return response()->json([
            'success' => true,
            'productName' => $productName,
            'unitBarcode' => $validated['unitBarcode'],
        ]);
    }

    /**
     * Parse a GS1-128 barcode string to extract the GTIN-14.
     * Returns the GTIN-14 if found, otherwise the original string cleaned up.
     */
    private function parseGS1OuterCode(string $raw): string
    {
        $text = trim($raw);

        // Strip GS1 symbology identifier prefixes
        foreach ([']C1', ']d2', ']e0'] as $prefix) {
            if (str_starts_with($text, $prefix)) {
                $text = substr($text, strlen($prefix));
                break;
            }
        }

        // Replace group separator characters
        $text = str_replace("\x1D", '|', $text);

        // Extract GTIN-14 from AI (01) — always 14 digits
        if (preg_match('/(?:^|\|)01(\d{14})/', $text, $matches)) {
            return $matches[1];
        }

        return $text;
    }

    /**
     * Complete the delivery by updating stock levels and marking as completed.
     */
    public function completeDelivery(Request $request)
    {
        $validated = $request->validate([
            'delID' => 'required|string',
            'supplierID' => 'required|string',
        ]);

        $delID = $validated['delID'];
        $supplierID = $validated['supplierID'];

        // Get matched items with scanned quantities
        $matchedItems = $this->getMatchedItems($delID, $supplierID);
        // Get extra items (scanned but NOT on invoice)
        $extraItems = $this->getScannedNotOnInvoice($delID, $supplierID);

        // Track update results
        $updateResults = [
            'productsUpdated' => 0,
            'productsSkipped' => 0,
            'unitsAdded' => 0,
        ];

        DB::connection('pos')->transaction(function () use ($matchedItems, $extraItems, $delID, &$updateResults) {
            // Update stock for matched items (on invoice AND scanned)
            foreach ($matchedItems as $item) {
                if ($item->scanned !== null && $item->scanned > 0 && $item->productID) {
                    $affected = DB::connection('pos')->table('STOCKCURRENT')
                        ->where('PRODUCT', $item->productID)
                        ->increment('UNITS', $item->scanned);

                    if ($affected > 0) {
                        $updateResults['productsUpdated']++;
                        $updateResults['unitsAdded'] += $item->scanned;
                    } else {
                        $updateResults['productsSkipped']++;
                    }
                }
            }

            // Update stock for extra items (scanned but NOT on invoice)
            foreach ($extraItems as $item) {
                if ($item->scanned !== null && $item->scanned > 0 && $item->productID) {
                    $affected = DB::connection('pos')->table('STOCKCURRENT')
                        ->where('PRODUCT', $item->productID)
                        ->increment('UNITS', $item->scanned);

                    if ($affected > 0) {
                        $updateResults['productsUpdated']++;
                        $updateResults['unitsAdded'] += $item->scanned;
                    } else {
                        $updateResults['productsSkipped']++;
                    }
                }
            }

            // Mark delivery scan session as completed
            DB::connection('pos')->table('deliveriesScan')
                ->where('ID', $delID)
                ->update(['status' => 1]);
        });

        return redirect()
            ->route('delivery-legacy.match', ['delID' => $delID, 'supplierID' => $supplierID])
            ->with('success', 'Stock updated successfully. Delivery marked as complete.')
            ->with('updateResults', $updateResults);
    }

    /**
     * Merge two scan sessions into one.
     */
    public function mergeSessions(Request $request)
    {
        $validated = $request->validate([
            'sourceSessionId' => 'required|string',
            'targetSessionId' => 'required|string|different:sourceSessionId',
        ]);

        $sourceId = $validated['sourceSessionId'];
        $targetId = $validated['targetSessionId'];

        // Verify both sessions exist and are pending
        $source = DB::connection('pos')->table('deliveriesScan')->where('ID', $sourceId)->first();
        $target = DB::connection('pos')->table('deliveriesScan')->where('ID', $targetId)->first();

        if (! $source || ! $target) {
            return redirect()->route('delivery-legacy.index')->with('error', 'One or both sessions not found.');
        }

        if ($source->status != 0 || $target->status != 0) {
            return redirect()->route('delivery-legacy.index')->with('error', 'Only pending sessions can be merged.');
        }

        DB::connection('pos')->transaction(function () use ($sourceId, $targetId) {
            // Get all items from source session
            $sourceItems = DB::connection('pos')->table('deliveriesScanItems')
                ->where('delID', $sourceId)
                ->get();

            foreach ($sourceItems as $item) {
                // Check if target already has this barcode
                $existing = DB::connection('pos')->table('deliveriesScanItems')
                    ->where('delID', $targetId)
                    ->where('barcode', $item->barcode)
                    ->first();

                if ($existing) {
                    // Sum quantities
                    DB::connection('pos')->table('deliveriesScanItems')
                        ->where('delID', $targetId)
                        ->where('barcode', $item->barcode)
                        ->update(['quantity' => $existing->quantity + $item->quantity]);
                } else {
                    // Move item to target session
                    DB::connection('pos')->table('deliveriesScanItems')
                        ->where('ID', $item->ID)
                        ->update(['delID' => $targetId]);
                }
            }

            // Delete any remaining source items (those that were summed, not moved)
            DB::connection('pos')->table('deliveriesScanItems')
                ->where('delID', $sourceId)
                ->delete();

            // Delete the source session
            DB::connection('pos')->table('deliveriesScan')
                ->where('ID', $sourceId)
                ->delete();
        });

        return redirect()->route('delivery-legacy.index')
            ->with('success', 'Sessions merged successfully. Source session has been removed.');
    }

    /**
     * Change the supplier on a scan session.
     */
    public function changeSupplier(Request $request)
    {
        $validated = $request->validate([
            'sessionId' => 'required|string',
            'newSupplierID' => 'required|string',
        ]);

        $session = DB::connection('pos')->table('deliveriesScan')
            ->where('ID', $validated['sessionId'])
            ->first();

        if (! $session) {
            return redirect()->route('delivery-legacy.index')->with('error', 'Session not found.');
        }

        if ($session->status != 0) {
            return redirect()->route('delivery-legacy.index')->with('error', 'Only pending sessions can have their supplier changed.');
        }

        DB::connection('pos')->table('deliveriesScan')
            ->where('ID', $validated['sessionId'])
            ->update(['supID' => $validated['newSupplierID']]);

        // Get supplier name for message
        $supplier = DB::connection('pos')->table('suppliers')
            ->where('SupplierID', $validated['newSupplierID'])
            ->first();

        return redirect()->route('delivery-legacy.index')
            ->with('success', 'Supplier changed to '.($supplier->Supplier ?? 'Unknown').'.');
    }

    /**
     * Create a new delivery scan session.
     */
    public function createSession(Request $request)
    {
        $validated = $request->validate([
            'supplierID' => 'required|string',
        ]);

        $supplierId = $validated['supplierID'];

        // Generate a UUID for the session ID
        $sessionId = (string) \Illuminate\Support\Str::uuid();

        // Create the scan session
        DB::connection('pos')->table('deliveriesScan')->insert([
            'ID' => $sessionId,
            'supID' => $supplierId,
            'dateUpload' => now(),
            'status' => 0,
        ]);

        // Get supplier name for message
        $supplier = DB::connection('pos')
            ->table('suppliers')
            ->where('SupplierID', $supplierId)
            ->first();

        return redirect()
            ->route('delivery-legacy.match', ['delID' => $sessionId, 'supplierID' => $supplierId])
            ->with('success', 'New scan session created for '.($supplier->Supplier ?? 'supplier').'.');
    }

    /**
     * Resolve and cache images for a batch of supplier codes.
     */
    public function resolveImagesBatch(Request $request): \Illuminate\Http\JsonResponse
    {
        $request->validate([
            'supplier_codes' => 'required|array|max:10',
            'supplier_codes.*' => 'string|max:50',
            'supplier_id' => 'required|integer',
        ]);

        $supplierId = (int) $request->supplier_id;
        $results = [];

        foreach ($request->supplier_codes as $code) {
            $imageUrl = $this->supplierService->resolveAndCacheImageUrl($code, $supplierId);
            $results[$code] = ['image_url' => $imageUrl];
        }

        return response()->json(['results' => $results]);
    }
}
