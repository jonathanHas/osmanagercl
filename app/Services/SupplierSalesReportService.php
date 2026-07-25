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

        // Step 2: today's per-product sales for those barcodes.
        $todayRows = SalesDailySummary::whereIn('product_code', $barcodes)
            ->whereDate('sale_date', $date)
            ->get();

        if ($todayRows->isEmpty()) {
            return null;
        }

        // Step 3: running context (this month / avg monthly / 12-month / trend),
        // keyed by product_id, reusing the order-system bulk query.
        $productIds = $todayRows->pluck('product_id')->unique()->values()->all();
        $stats = $this->salesRepository->getBulkProductSalesStatistics($productIds);

        // This-week units, straight from the summary table (cheap aggregate).
        $weekStart = $date->copy()->startOfWeek();
        $weekUnits = SalesDailySummary::whereIn('product_id', $productIds)
            ->whereBetween('sale_date', [$weekStart, $date])
            ->selectRaw('product_id, SUM(total_units) as units')
            ->groupBy('product_id')
            ->pluck('units', 'product_id');

        // Step 4: assemble line items (one per product sold today).
        $items = $todayRows
            ->sortByDesc(fn ($row) => (float) $row->total_revenue)
            ->map(function ($row) use ($stats, $weekUnits) {
                $context = $stats[$row->product_id] ?? [];

                return [
                    'barcode' => $row->product_code,
                    'name' => $row->product_name,
                    'units' => (float) $row->total_units,
                    'revenue' => (float) $row->total_revenue,
                    'transactions' => (int) $row->transaction_count,
                    'week_units' => (float) ($weekUnits[$row->product_id] ?? 0),
                    'month_units' => (float) ($context['this_month_sales'] ?? 0),
                    'avg_monthly_units' => (float) ($context['avg_monthly_sales'] ?? 0),
                    'total_12m_units' => (float) ($context['total_sales_12m'] ?? 0),
                    'trend' => $context['trend'] ?? 'stable',
                ];
            })
            ->values()
            ->all();

        $totals = [
            'units' => array_sum(array_column($items, 'units')),
            'revenue' => array_sum(array_column($items, 'revenue')),
            'transactions' => array_sum(array_column($items, 'transactions')),
            'lines' => count($items),
        ];

        return [
            'supplier' => $supplier,
            'date' => $date->copy(),
            'items' => $items,
            'totals' => $totals,
            'show_values' => (bool) $supplier->include_sales_values,
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
