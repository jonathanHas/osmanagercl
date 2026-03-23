<?php

namespace App\Http\Controllers;

use App\Models\AccountingSupplier;
use App\Models\Category;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrganicTrustReportController extends Controller
{
    public function index(Request $request)
    {
        $startDate = $request->get('start_date', now()->startOfYear()->format('Y-m-d'));
        $endDate = $request->get('end_date', now()->format('Y-m-d'));

        $productTypes = $this->getOption('organic_product_types', AccountingSupplier::ORGANIC_PRODUCT_TYPES);
        sort($productTypes);
        $certBodies = $this->getOption('organic_certification_bodies', AccountingSupplier::ORGANIC_CERTIFICATION_BODIES);
        sort($certBodies);

        if (! $request->filled('start_date')) {
            return view('suppliers.organic-trust-report', compact('startDate', 'endDate', 'productTypes', 'certBodies'));
        }

        $startDateTime = Carbon::parse($startDate)->startOfDay();
        $endDateTime = Carbon::parse($endDate)->endOfDay();

        $suppliers = $this->getSupplierSpend($startDateTime, $endDateTime);

        $totalSpend = $suppliers->sum('period_total');
        $totalSales = $suppliers->sum('period_sales');
        $organicSuppliers = $suppliers->where('is_organic', true);
        $organicSpend = $organicSuppliers->sum('period_total');
        $organicSales = $organicSuppliers->sum('period_sales');
        $supplierCount = $suppliers->count();
        $organicCount = $organicSuppliers->count();

        $organicCategorySales = $this->getOrganicCategorySales($suppliers, $startDateTime, $endDateTime);

        return view('suppliers.organic-trust-report', compact(
            'startDate',
            'endDate',
            'suppliers',
            'totalSpend',
            'totalSales',
            'organicSpend',
            'organicSales',
            'supplierCount',
            'organicCount',
            'productTypes',
            'certBodies',
            'organicCategorySales'
        ));
    }

    public function toggleOrganic(AccountingSupplier $supplier)
    {
        $supplier->update(['is_organic' => ! $supplier->is_organic]);

        return response()->json([
            'success' => true,
            'is_organic' => $supplier->is_organic,
        ]);
    }

    public function updateOrganicFields(Request $request, AccountingSupplier $supplier)
    {
        $validated = $request->validate([
            'organic_product_type' => 'nullable|string|max:255',
            'organic_certification_body' => 'nullable|string|max:255',
        ]);

        $supplier->update($validated);

        return response()->json([
            'success' => true,
            'organic_product_type' => $supplier->organic_product_type,
            'organic_certification_body' => $supplier->organic_certification_body,
        ]);
    }

    public function exportBoughtIn(Request $request)
    {
        $startDate = $request->get('start_date', now()->startOfYear()->format('Y-m-d'));
        $endDate = $request->get('end_date', now()->format('Y-m-d'));

        $startDateTime = Carbon::parse($startDate)->startOfDay();
        $endDateTime = Carbon::parse($endDate)->endOfDay();

        $suppliers = $this->getSupplierSpend($startDateTime, $endDateTime)
            ->where('is_organic', true);

        $filename = 'Bought In Organic Products '.$startDate.' to '.$endDate.'.csv';

        $callback = function () use ($suppliers, $startDate, $endDate) {
            $file = fopen('php://output', 'w');

            fputcsv($file, ['Bought In Organic Products']);
            fputcsv($file, ['Date Range: '.$startDate.' to '.$endDate]);
            fputcsv($file, []);

            fputcsv($file, ['Supplier Name', 'Product Type', 'Certification Body', 'Total Amount (ex. VAT)']);

            $totalAmount = 0;

            foreach ($suppliers as $supplier) {
                fputcsv($file, [
                    $supplier->name,
                    $supplier->organic_product_type ?? '',
                    $supplier->organic_certification_body ?? '',
                    number_format($supplier->period_total, 2),
                ]);

                $totalAmount += $supplier->period_total;
            }

            fputcsv($file, []);
            fputcsv($file, ['Total', '', '', number_format($totalAmount, 2)]);

            fclose($file);
        };

        return response()->stream($callback, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    public function exportSales(Request $request)
    {
        $startDate = $request->get('start_date', now()->startOfYear()->format('Y-m-d'));
        $endDate = $request->get('end_date', now()->format('Y-m-d'));

        $startDateTime = Carbon::parse($startDate)->startOfDay();
        $endDateTime = Carbon::parse($endDate)->endOfDay();

        $suppliers = $this->getSupplierSpend($startDateTime, $endDateTime);
        $categorySales = $this->getOrganicCategorySales($suppliers, $startDateTime, $endDateTime);

        $filename = 'Sales of Organic Products '.$startDate.' to '.$endDate.'.csv';

        $callback = function () use ($categorySales, $startDate, $endDate) {
            $file = fopen('php://output', 'w');

            fputcsv($file, ['Sales of Organic Products']);
            fputcsv($file, ['Date Range: '.$startDate.' to '.$endDate]);
            fputcsv($file, []);

            fputcsv($file, ['Category', 'Units Sold', 'Sales Revenue (ex. VAT)']);

            $totalUnits = 0;
            $totalRevenue = 0;

            foreach ($categorySales as $catSale) {
                fputcsv($file, [
                    $catSale->category_name,
                    number_format($catSale->total_units, 0),
                    number_format($catSale->total_revenue, 2),
                ]);

                $totalUnits += $catSale->total_units;
                $totalRevenue += $catSale->total_revenue;
            }

            fputcsv($file, []);
            fputcsv($file, ['Total', number_format($totalUnits, 0), number_format($totalRevenue, 2)]);

            fclose($file);
        };

        return response()->stream($callback, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    public function updateOptions(Request $request)
    {
        $validated = $request->validate([
            'organic_product_types' => 'nullable|array',
            'organic_product_types.*' => 'string|max:255',
            'organic_certification_bodies' => 'nullable|array',
            'organic_certification_bodies.*' => 'string|max:255',
        ]);

        if ($request->has('organic_product_types')) {
            $this->setOption('organic_product_types', array_values(array_filter($validated['organic_product_types'] ?? [])));
        }

        if ($request->has('organic_certification_bodies')) {
            $this->setOption('organic_certification_bodies', array_values(array_filter($validated['organic_certification_bodies'] ?? [])));
        }

        return response()->json(['success' => true]);
    }

    private function getOption(string $key, array $default): array
    {
        $row = DB::table('app_settings')->where('key', $key)->first();

        if ($row && $row->value) {
            $decoded = json_decode($row->value, true);
            if (is_array($decoded) && count($decoded) > 0) {
                return $decoded;
            }
        }

        return $default;
    }

    private function setOption(string $key, array $value): void
    {
        DB::table('app_settings')->updateOrInsert(
            ['key' => $key],
            ['value' => json_encode($value), 'updated_at' => now()]
        );
    }

    /**
     * Query POS sales for given product IDs, excluding Kitchen/Coffee internal transfers.
     */
    private function getPOSSales(array $productIds, Carbon $startDateTime, Carbon $endDateTime): object
    {
        if (empty($productIds)) {
            return (object) ['revenue' => 0, 'units' => 0];
        }

        $placeholders = implode(',', array_fill(0, count($productIds), '?'));

        $result = DB::connection('pos')->select("
            SELECT
                COALESCE(SUM(tl.PRICE * ABS(tl.UNITS)), 0) as revenue,
                COALESCE(SUM(ABS(tl.UNITS)), 0) as units
            FROM TICKETLINES tl
            JOIN TICKETS t ON tl.TICKET = t.ID
            JOIN RECEIPTS r ON t.ID = r.ID
            LEFT JOIN CUSTOMERS c ON t.CUSTOMER = c.ID
            WHERE r.DATENEW >= ? AND r.DATENEW < ?
            AND tl.PRODUCT IN ({$placeholders})
            AND (c.NAME IS NULL OR c.NAME NOT IN ('Kitchen', 'Coffee'))
        ", array_merge(
            [$startDateTime->format('Y-m-d H:i:s'), $endDateTime->format('Y-m-d H:i:s')],
            $productIds
        ));

        return $result[0] ?? (object) ['revenue' => 0, 'units' => 0];
    }

    /**
     * Query POS sales grouped by category for given product IDs, excluding Kitchen/Coffee.
     */
    private function getPOSSalesByCategory(array $productIds, Carbon $startDateTime, Carbon $endDateTime)
    {
        if (empty($productIds)) {
            return collect();
        }

        $placeholders = implode(',', array_fill(0, count($productIds), '?'));

        return collect(DB::connection('pos')->select("
            SELECT
                p.CATEGORY as category_id,
                SUM(tl.PRICE * ABS(tl.UNITS)) as total_revenue,
                SUM(ABS(tl.UNITS)) as total_units
            FROM TICKETLINES tl
            JOIN TICKETS t ON tl.TICKET = t.ID
            JOIN RECEIPTS r ON t.ID = r.ID
            JOIN PRODUCTS p ON tl.PRODUCT = p.ID
            LEFT JOIN CUSTOMERS c ON t.CUSTOMER = c.ID
            WHERE r.DATENEW >= ? AND r.DATENEW < ?
            AND tl.PRODUCT IN ({$placeholders})
            AND (c.NAME IS NULL OR c.NAME NOT IN ('Kitchen', 'Coffee'))
            GROUP BY p.CATEGORY
        ", array_merge(
            [$startDateTime->format('Y-m-d H:i:s'), $endDateTime->format('Y-m-d H:i:s')],
            $productIds
        )));
    }

    private function getOrganicCategorySales($suppliers, Carbon $startDateTime, Carbon $endDateTime)
    {
        // Get product IDs for all organic, POS-linked suppliers
        $organicPosIds = $suppliers
            ->where('is_organic', true)
            ->where('is_pos_linked', true)
            ->pluck('external_pos_id')
            ->filter()
            ->toArray();

        if (empty($organicPosIds)) {
            return collect();
        }

        $productIds = DB::connection('pos')
            ->table('supplier_link')
            ->join('PRODUCTS', 'supplier_link.Barcode', '=', 'PRODUCTS.CODE')
            ->whereIn('supplier_link.SupplierID', $organicPosIds)
            ->pluck('PRODUCTS.ID')
            ->toArray();

        if (empty($productIds)) {
            return collect();
        }

        // Group sales by category (excluding Kitchen/Coffee)
        $categorySales = $this->getPOSSalesByCategory($productIds, $startDateTime, $endDateTime);

        if ($categorySales->isEmpty()) {
            return collect();
        }

        // Resolve category names from POS database
        $categoryNames = Category::whereIn('ID', $categorySales->pluck('category_id'))
            ->pluck('NAME', 'ID');

        return $categorySales->map(function ($row) use ($categoryNames) {
            $row->category_name = $categoryNames[$row->category_id] ?? 'Unknown ('.$row->category_id.')';

            return $row;
        })->sortByDesc('total_revenue')->values();
    }

    private function getSupplierSpend(Carbon $startDateTime, Carbon $endDateTime)
    {
        return AccountingSupplier::where('supplier_type', 'product')
            ->where('is_active', true)
            ->get()
            ->map(function ($supplier) use ($startDateTime, $endDateTime) {
                $invoiceStats = $supplier->invoices()
                    ->where('payment_status', '!=', 'cancelled')
                    ->whereBetween('invoice_date', [$startDateTime, $endDateTime])
                    ->selectRaw('COALESCE(SUM(total_amount - vat_amount), 0) as total, COUNT(*) as count')
                    ->first();

                $supplier->period_total = (float) $invoiceStats->total;
                $supplier->period_invoice_count = (int) $invoiceStats->count;

                // Sales revenue from POS (excluding Kitchen/Coffee internal transfers)
                $supplier->period_sales = 0.0;
                if ($supplier->is_pos_linked && $supplier->external_pos_id) {
                    $productIds = DB::connection('pos')
                        ->table('supplier_link')
                        ->join('PRODUCTS', 'supplier_link.Barcode', '=', 'PRODUCTS.CODE')
                        ->where('supplier_link.SupplierID', $supplier->external_pos_id)
                        ->pluck('PRODUCTS.ID')
                        ->toArray();

                    if (! empty($productIds)) {
                        $sales = $this->getPOSSales($productIds, $startDateTime, $endDateTime);
                        $supplier->period_sales = (float) $sales->revenue;
                    }
                }

                return $supplier;
            })
            ->filter(fn ($supplier) => $supplier->period_total > 0 || $supplier->period_sales > 0)
            ->sortBy('name')
            ->values();
    }
}
