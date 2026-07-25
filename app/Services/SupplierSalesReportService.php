<?php

namespace App\Services;

use App\Models\AccountingSupplier;
use App\Models\SalesDailySummary;
use App\Repositories\SalesRepository;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class SupplierSalesReportService
{
    public function __construct(protected SalesRepository $salesRepository) {}

    /**
     * Build a same-day sales report for a supplier's products.
     *
     * Returns null when the supplier has no POS link or no products sold on the
     * given date (the caller should skip these rather than send an empty email).
     *
     * @return array{supplier: AccountingSupplier, date: Carbon, items: array<int, array<string, mixed>>, totals: array<string, float>}|null
     */
    public function buildReport(AccountingSupplier $supplier, Carbon $date): ?array
    {
        if (! $supplier->is_pos_linked || ! $supplier->external_pos_id) {
            return null;
        }

        // Step 1: resolve the supplier's product barcodes from the POS link table.
        // Barcode == PRODUCTS.CODE == sales_daily_summary.product_code.
        $barcodes = DB::connection('pos')
            ->table('supplier_link')
            ->where('SupplierID', $supplier->external_pos_id)
            ->pluck('Barcode')
            ->filter()
            ->unique()
            ->values()
            ->all();

        if (empty($barcodes)) {
            return null;
        }

        // Step 2: the report date's per-product sales. This is the gate — we
        // still skip suppliers who sold nothing that day (see return null).
        $todayRows = SalesDailySummary::whereIn('product_code', $barcodes)
            ->whereDate('sale_date', $date)
            ->get();

        if ($todayRows->isEmpty()) {
            return null;
        }

        // Step 3: the product set is every product this supplier sold *this
        // calendar month* (up to the report date), so the email shows the
        // month's range — not only the day's sellers. Aggregate month units +
        // identity per product from the summary table.
        $monthStart = $date->copy()->startOfMonth();
        $monthProducts = SalesDailySummary::whereIn('product_code', $barcodes)
            ->whereBetween('sale_date', [$monthStart, $date])
            ->selectRaw('product_id, MAX(product_code) as product_code, MAX(product_name) as product_name, SUM(total_units) as month_units')
            ->groupBy('product_id')
            ->get();

        // Running context (avg monthly / 12-month / trend) for the whole set.
        $productIds = $monthProducts->pluck('product_id')->unique()->values()->all();
        $stats = $this->salesRepository->getBulkProductSalesStatistics($productIds);

        // This-week units, straight from the summary table (cheap aggregate).
        $weekStart = $date->copy()->startOfWeek();
        $weekUnits = SalesDailySummary::whereIn('product_id', $productIds)
            ->whereBetween('sale_date', [$weekStart, $date])
            ->selectRaw('product_id, SUM(total_units) as units')
            ->groupBy('product_id')
            ->pluck('units', 'product_id');

        // The report date's rows keyed by product, for quick lookup (a product
        // in the month set may have had no sale on the report date → 0 for the day).
        $todayByProduct = $todayRows->keyBy('product_id');

        // Step 4: one line item per product sold this month; the day's figures
        // are 0 where the product didn't sell on the report date.
        $items = $monthProducts
            ->map(function ($row) use ($stats, $weekUnits, $todayByProduct) {
                $context = $stats[$row->product_id] ?? [];
                $today = $todayByProduct->get($row->product_id);

                return [
                    'barcode' => $row->product_code,
                    'name' => $row->product_name,
                    'units' => $today ? (float) $today->total_units : 0.0,
                    'revenue' => $today ? (float) $today->total_revenue : 0.0,
                    'transactions' => $today ? (int) $today->transaction_count : 0,
                    'week_units' => (float) ($weekUnits[$row->product_id] ?? 0),
                    'month_units' => (float) $row->month_units,
                    'avg_monthly_units' => (float) ($context['avg_monthly_sales'] ?? 0),
                    'total_12m_units' => (float) ($context['total_sales_12m'] ?? 0),
                    'trend' => $context['trend'] ?? 'stable',
                ];
            })
            // The day's sellers first (by day revenue, then day units), then the
            // rest of the month's products by month volume.
            ->sortByDesc(fn ($item) => [$item['revenue'], $item['units'], $item['month_units']])
            ->values()
            ->all();

        // Totals reflect the report DATE only — the extra month rows contribute
        // 0 to the day's units/revenue; "lines" = products that sold that day.
        $totals = [
            'units' => array_sum(array_column($items, 'units')),
            'revenue' => array_sum(array_column($items, 'revenue')),
            'transactions' => array_sum(array_column($items, 'transactions')),
            'lines' => $todayRows->pluck('product_id')->unique()->count(),
        ];

        return [
            'supplier' => $supplier,
            'date' => $date->copy(),
            'items' => $items,
            'totals' => $totals,
            'show_values' => (bool) $supplier->include_sales_values,
            'attach_csv' => (bool) $supplier->attach_sales_csv,
        ];
    }

    /**
     * Build the CSV body for a report: one row per product sold on the report date.
     * Shared by the email attachment and the preview-page download so both match.
     *
     * @param  array{items: array<int, array<string, mixed>>}  $report
     */
    public function toCsv(array $report): string
    {
        $showValues = $report['show_values'] ?? true;

        $handle = fopen('php://temp', 'r+');

        $header = ['Barcode', 'Product', 'Units Sold'];
        if ($showValues) {
            $header[] = 'Revenue';
        }
        $header = array_merge($header, [
            'Transactions',
            'This Week Units',
            'This Month Units',
            'Avg Monthly Units',
            '12-Month Units',
            'Trend',
        ]);
        fputcsv($handle, $header);

        foreach ($report['items'] as $item) {
            $row = [$item['barcode'], $item['name'], $this->num($item['units'])];
            if ($showValues) {
                $row[] = number_format($item['revenue'], 2, '.', '');
            }
            $row = array_merge($row, [
                $item['transactions'],
                $this->num($item['week_units']),
                $this->num($item['month_units']),
                $this->num($item['avg_monthly_units']),
                $this->num($item['total_12m_units']),
                $item['trend'],
            ]);
            fputcsv($handle, $row);
        }

        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return $csv;
    }

    /**
     * Format a unit count without trailing decimals when whole.
     */
    protected function num(float $value): string
    {
        return rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.');
    }
}
