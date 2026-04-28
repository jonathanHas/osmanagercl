<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\SalesDailySummary;
use App\Models\Supplier;
use App\Services\SupplierService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SupplierCodeLookupController extends Controller
{
    public function index(SupplierService $supplierService)
    {
        $suppliers = Supplier::orderBy('Supplier')
            ->pluck('Supplier', 'SupplierID');

        return view('supplier-code-lookup.index', [
            'suppliers' => $suppliers,
            'supplierService' => $supplierService,
            'supplierLinks' => collect(),
            'products' => collect(),
            'salesData' => collect(),
            'weeklySalesData' => [],
            'notFoundCodes' => [],
            'inputCodes' => [],
            'supplierId' => null,
            'supplierCodes' => '',
        ]);
    }

    public function lookup(Request $request, SupplierService $supplierService)
    {
        $request->validate([
            'supplier_codes' => 'required|string',
            'supplier_id' => 'nullable|string',
        ]);

        $suppliers = Supplier::orderBy('Supplier')
            ->pluck('Supplier', 'SupplierID');

        $rawCodes = $request->input('supplier_codes');
        $supplierId = $request->input('supplier_id');

        // Parse codes. If the input contains quoted substrings (e.g. supplier
        // destock reports like: ❌ "43139B" Product code from imported file...),
        // extract those. Otherwise split on commas/whitespace/semicolons.
        if (preg_match_all('/"([^"]+)"/', $rawCodes, $matches) && ! empty($matches[1])) {
            $tokens = $matches[1];
        } else {
            $tokens = preg_split('/[\s,;]+/', $rawCodes);
        }

        $inputCodes = collect($tokens)
            ->map(fn ($code) => trim($code))
            ->filter(fn ($code) => $code !== '')
            ->unique()
            ->values()
            ->toArray();

        if (empty($inputCodes)) {
            return view('supplier-code-lookup.index', [
                'suppliers' => $suppliers,
                'supplierService' => $supplierService,
                'supplierLinks' => collect(),
                'products' => collect(),
                'salesData' => collect(),
                'weeklySalesData' => [],
                'notFoundCodes' => [],
                'inputCodes' => [],
                'supplierId' => $supplierId,
                'supplierCodes' => $rawCodes,
            ]);
        }

        // Query supplier_link for matching codes
        $query = DB::connection('pos')->table('supplier_link')
            ->whereIn('SupplierCode', $inputCodes);

        if ($supplierId) {
            $query->where('SupplierID', $supplierId);
        }

        $supplierLinks = $query->get();

        // Track which codes were not found
        $foundCodes = $supplierLinks->pluck('SupplierCode')->unique()->toArray();
        $notFoundCodes = array_values(array_diff($inputCodes, $foundCodes));

        // Load products with relationships
        $barcodes = $supplierLinks->pluck('Barcode')->unique()->toArray();
        $products = Product::whereIn('CODE', $barcodes)
            ->with(['supplierLink', 'supplier', 'stocking'])
            ->get()
            ->keyBy('CODE');

        // Fetch 3-month sales data (aggregates)
        $startDate = Carbon::now()->subMonths(3);
        $salesData = SalesDailySummary::select(
            'product_code',
            DB::raw('SUM(total_units) as total_units_sold'),
            DB::raw('SUM(total_revenue) as total_revenue'),
            DB::raw('COUNT(DISTINCT sale_date) as days_with_sales'),
            DB::raw('MAX(sale_date) as last_sale')
        )
            ->whereIn('product_code', $barcodes)
            ->where('sale_date', '>=', $startDate->format('Y-m-d'))
            ->groupBy('product_code')
            ->get()
            ->keyBy('product_code');

        // Fetch weekly sales data for charts (12 weeks = ~3 months)
        $weeksBack = 12;
        $endOfCurrentWeek = Carbon::now()->endOfWeek();
        $startRange = $endOfCurrentWeek->copy()->subWeeks($weeksBack - 1)->startOfWeek();

        // Build week buckets
        $weekBucketDefs = [];
        $cursor = $startRange->copy();
        for ($i = 0; $i < $weeksBack; $i++) {
            $weekStart = $cursor->copy()->startOfWeek();
            $weekBucketDefs[] = [
                'key' => $weekStart->format('Y-m-d'),
                'label' => $weekStart->format('d M'),
            ];
            $cursor->addWeek();
        }

        // Batch query weekly sales for all products
        $productIds = $products->pluck('ID')->toArray();
        $weeklyRaw = [];
        if (! empty($productIds)) {
            $weeklyRaw = SalesDailySummary::whereIn('product_id', $productIds)
                ->whereBetween('sale_date', [$startRange, $endOfCurrentWeek])
                ->selectRaw("product_id, DATE_FORMAT(DATE_SUB(sale_date, INTERVAL WEEKDAY(sale_date) DAY), '%Y-%m-%d') as week_start, SUM(total_units) as total_units")
                ->groupBy('product_id', 'week_start')
                ->get()
                ->groupBy('product_id');
        }

        // Build per-product weekly sales arrays
        $weeklySalesData = [];
        foreach ($products as $barcode => $product) {
            $productWeekly = $weeklyRaw[$product->ID] ?? collect();
            $weekMap = $productWeekly->pluck('total_units', 'week_start')->toArray();

            $weeks = [];
            foreach ($weekBucketDefs as $def) {
                $weeks[] = [
                    'label' => $def['label'],
                    'units' => round((float) ($weekMap[$def['key']] ?? 0), 2),
                ];
            }
            $weeklySalesData[$barcode] = $weeks;
        }

        return view('supplier-code-lookup.index', [
            'suppliers' => $suppliers,
            'supplierService' => $supplierService,
            'supplierLinks' => $supplierLinks,
            'products' => $products,
            'salesData' => $salesData,
            'weeklySalesData' => $weeklySalesData,
            'notFoundCodes' => $notFoundCodes,
            'inputCodes' => $inputCodes,
            'supplierId' => $supplierId,
            'supplierCodes' => $rawCodes,
        ]);
    }
}
