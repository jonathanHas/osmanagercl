<?php

namespace App\Services;

use App\Models\Category;
use App\Models\CostPriceAdjustment;
use App\Models\Product;
use App\Models\StockValuationCategory;
use App\Models\StockValuationItem;
use App\Models\StockValuationSnapshot;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class StockValuationService
{
    /**
     * Maximum number of diagnostic rows surfaced in the UI/CSV.
     *
     * Counts and totals are always calculated across every row; only the
     * itemised listing is capped.
     */
    protected const DIAGNOSTIC_LIMIT = 50;

    /**
     * Rows per batch when writing snapshot items.
     */
    protected const INSERT_CHUNK = 500;

    /**
     * Bounds for a cost price divisor.
     *
     * MAX_DIVISOR also keeps the recorded value inside the audit column's
     * decimal(10,4); MIN_COST stops a cost being divided into nothing.
     */
    protected const MAX_DIVISOR = 10000;

    protected const MIN_COST = 0.0001;

    /**
     * Calculate live valuation from current POS data (no snapshot).
     *
     * Stock is valued at cost (PRODUCTS.PRICEBUY) x units on hand. Products
     * whose net stock is zero or negative are excluded from the valuation --
     * negative stock is not a holding, it is an untracked line that has been
     * sold without ever being booked in. Those lines are reported separately
     * so the headline figure is never read without its caveat.
     */
    public function calculateLiveValuation(): array
    {
        $rows = $this->fetchPositiveStockRows();

        $categories = [];
        $totalValue = 0.0;
        $productCount = 0;

        foreach ($rows as $row) {
            $categoryId = $row->CATEGORY;
            // Round at the line so category subtotals and the grand total are
            // exact sums of what is printed -- an accounts document has to tie
            // to its own detail lines to the cent.
            $value = round((float) $row->LINE_VALUE, 2);

            if (! isset($categories[$categoryId])) {
                $categories[$categoryId] = [
                    'category_id' => $categoryId,
                    'category_name' => $row->CATEGORY_NAME ?? 'Uncategorized',
                    'product_count' => 0,
                    'total_value' => 0.0,
                    'products' => [],
                ];
            }

            $categories[$categoryId]['product_count']++;
            $categories[$categoryId]['total_value'] += $value;
            $categories[$categoryId]['products'][] = [
                'product_id' => $row->ID,
                'product_code' => $row->CODE,
                'product_name' => $row->NAME,
                'unit_cost' => (float) $row->PRICEBUY,
                'stock_units' => (float) $row->UNITS,
                'line_value' => $value,
            ];

            $totalValue += $value;
            $productCount++;
        }

        // Sort by category name
        uasort($categories, fn ($a, $b) => strcmp($a['category_name'], $b['category_name']));

        // Clear binary-float drift from accumulating decimal amounts
        foreach ($categories as &$category) {
            $category['total_value'] = round($category['total_value'], 2);
        }
        unset($category);

        return [
            'valuation_date' => now(),
            'categories' => array_values($categories),
            'total_value' => round($totalValue, 2),
            'category_count' => count($categories),
            'product_count' => $productCount,
            'negative_stock' => $this->getNegativeStock(),
            'cost_anomalies' => $this->getCostAnomalies(),
        ];
    }

    /**
     * Get live products for a specific category.
     *
     * Applies the same positive-stock flooring as calculateLiveValuation() so
     * the drill-down reconciles with the category summary row.
     */
    public function getLiveCategoryProducts(string $categoryId): array
    {
        $rows = $this->fetchPositiveStockRows($categoryId);

        $category = Category::find($categoryId);
        $items = [];
        $totalValue = 0.0;

        foreach ($rows as $row) {
            $value = round((float) $row->LINE_VALUE, 2);

            $items[] = [
                'product_id' => $row->ID,
                'product_code' => $row->CODE,
                'product_name' => $row->NAME,
                'unit_cost' => (float) $row->PRICEBUY,
                'stock_units' => (float) $row->UNITS,
                'line_value' => $value,
            ];

            $totalValue += $value;
        }

        // Sort by product name
        usort($items, fn ($a, $b) => strcmp($a['product_name'], $b['product_name']));

        return [
            'category_id' => $categoryId,
            'category_name' => $category?->NAME ?? 'Unknown',
            'product_count' => count($items),
            'total_value' => round($totalValue, 2),
            'products' => $items,
        ];
    }

    /**
     * Fetch products holding net-positive stock, valued at cost.
     *
     * Units are summed per product first so that multi-location or
     * multi-attribute rows collapse before the sign test is applied.
     */
    protected function fetchPositiveStockRows(?string $categoryId = null): array
    {
        $bindings = [];
        $categoryFilter = '';

        if ($categoryId !== null) {
            $categoryFilter = 'WHERE p.CATEGORY = ?';
            $bindings[] = $categoryId;
        }

        return DB::connection('pos')->select(
            "SELECT p.ID,
                    p.CODE,
                    p.NAME,
                    p.CATEGORY,
                    c.NAME AS CATEGORY_NAME,
                    p.PRICEBUY,
                    SUM(sc.UNITS) AS UNITS,
                    SUM(sc.UNITS) * p.PRICEBUY AS LINE_VALUE
             FROM STOCKCURRENT sc
             JOIN PRODUCTS p ON p.ID = sc.PRODUCT
             LEFT JOIN CATEGORIES c ON c.ID = p.CATEGORY
             {$categoryFilter}
             GROUP BY p.ID, p.CODE, p.NAME, p.CATEGORY, c.NAME, p.PRICEBUY
             HAVING SUM(sc.UNITS) > 0",
            $bindings
        );
    }

    /**
     * Lines carrying negative stock, excluded from the valuation.
     *
     * These are typically made-to-order or loose lines that are sold at the
     * till but never booked in, so stock drifts permanently negative.
     */
    protected function getNegativeStock(): array
    {
        $rows = DB::connection('pos')->select(
            'SELECT p.CODE,
                    p.NAME,
                    c.NAME AS CATEGORY_NAME,
                    p.PRICEBUY,
                    SUM(sc.UNITS) AS UNITS,
                    SUM(sc.UNITS) * p.PRICEBUY AS LINE_VALUE
             FROM STOCKCURRENT sc
             JOIN PRODUCTS p ON p.ID = sc.PRODUCT
             LEFT JOIN CATEGORIES c ON c.ID = p.CATEGORY
             GROUP BY p.ID, p.CODE, p.NAME, c.NAME, p.PRICEBUY
             HAVING SUM(sc.UNITS) < 0
             ORDER BY LINE_VALUE ASC'
        );

        $notionalValue = 0.0;
        foreach ($rows as $row) {
            $notionalValue += (float) $row->LINE_VALUE;
        }

        return [
            'line_count' => count($rows),
            'notional_value' => $notionalValue,
            'items' => array_map(fn ($row) => [
                'product_code' => $row->CODE,
                'product_name' => $row->NAME,
                'category_name' => $row->CATEGORY_NAME ?? 'Uncategorized',
                'unit_cost' => (float) $row->PRICEBUY,
                'stock_units' => (float) $row->UNITS,
                'line_value' => (float) $row->LINE_VALUE,
            ], array_slice($rows, 0, self::DIAGNOSTIC_LIMIT)),
            'truncated' => count($rows) > self::DIAGNOSTIC_LIMIT,
        ];
    }

    /**
     * Stocked products whose cost price exceeds their sell price.
     *
     * Almost always a data entry error -- a case cost entered against a unit
     * price -- which inflates the valuation until corrected.
     */
    protected function getCostAnomalies(): array
    {
        $rows = DB::connection('pos')->select(
            'SELECT p.ID,
                    p.CODE,
                    p.NAME,
                    c.NAME AS CATEGORY_NAME,
                    p.PRICEBUY,
                    p.PRICESELL,
                    sl.CaseUnits,
                    SUM(sc.UNITS) AS UNITS,
                    SUM(sc.UNITS) * p.PRICEBUY AS LINE_VALUE
             FROM STOCKCURRENT sc
             JOIN PRODUCTS p ON p.ID = sc.PRODUCT
             LEFT JOIN CATEGORIES c ON c.ID = p.CATEGORY
             LEFT JOIN supplier_link sl ON sl.Barcode = p.CODE
             WHERE p.PRICEBUY > p.PRICESELL
             GROUP BY p.ID, p.CODE, p.NAME, c.NAME, p.PRICEBUY, p.PRICESELL, sl.CaseUnits
             HAVING SUM(sc.UNITS) > 0
             ORDER BY LINE_VALUE DESC'
        );

        $value = 0.0;
        foreach ($rows as $row) {
            $value += (float) $row->LINE_VALUE;
        }

        return [
            'line_count' => count($rows),
            'value' => $value,
            'items' => array_map(function ($row) {
                $suggestion = $this->suggestDivisor($row->NAME, $row->CaseUnits ?? null);

                return [
                    'product_id' => $row->ID,
                    'product_code' => $row->CODE,
                    'product_name' => $row->NAME,
                    'category_name' => $row->CATEGORY_NAME ?? 'Uncategorized',
                    'unit_cost' => (float) $row->PRICEBUY,
                    'sell_price' => (float) $row->PRICESELL,
                    'stock_units' => (float) $row->UNITS,
                    'line_value' => (float) $row->LINE_VALUE,
                    'suggested_divisor' => $suggestion['divisor'],
                    'suggestion_source' => $suggestion['source'],
                ];
            }, array_slice($rows, 0, self::DIAGNOSTIC_LIMIT)),
            'truncated' => count($rows) > self::DIAGNOSTIC_LIMIT,
        ];
    }

    /**
     * Suggest a divisor to convert a case/bulk cost into a per-unit cost.
     *
     * Two sources, in order of trust: the supplier link's case units, then the
     * pack size in the product name ("Bulk 20L", "30Bags"). Only ever a hint --
     * neither source is reliable enough to apply without a human confirming it.
     *
     * @return array{divisor: float|null, source: string|null}
     */
    public function suggestDivisor(?string $productName, $caseUnits = null): array
    {
        $caseUnits = (float) $caseUnits;

        if ($caseUnits > 1) {
            return ['divisor' => $caseUnits, 'source' => 'case units'];
        }

        if ($productName === null) {
            return ['divisor' => null, 'source' => null];
        }

        // Pack size in the name: "20L", "1.5 Litre", "500ml", "30Bags", "x12".
        $patterns = [
            '/(\d+(?:\.\d+)?)\s*(?:l|ltr|litre|liter)s?\b/i' => 'name',
            '/(\d+(?:\.\d+)?)\s*(?:kg|kilo)s?\b/i' => 'name',
            '/(\d+)\s*(?:bags|sachets|pcs|pieces|pack|packs|rolls|cups)\b/i' => 'name',
            '/\bx\s*(\d+)\b/i' => 'name',
        ];

        foreach ($patterns as $pattern => $source) {
            if (preg_match($pattern, $productName, $matches)) {
                $divisor = (float) $matches[1];

                if ($divisor > 1) {
                    return ['divisor' => $divisor, 'source' => $source];
                }
            }
        }

        return ['divisor' => null, 'source' => null];
    }

    /**
     * Divide a product's cost price down to a per-unit figure.
     *
     * Writes PRODUCTS.PRICEBUY in the POS database and records both sides of
     * the change locally, since the POS keeps no history of its own.
     *
     * @return array{product_name: string, old_cost: float, new_cost: float}
     */
    public function adjustCostPrice(string $productId, float $divisor, int $userId): array
    {
        if ($divisor <= 0) {
            throw new \InvalidArgumentException('Divisor must be greater than zero.');
        }

        if ($divisor > self::MAX_DIVISOR) {
            throw new \InvalidArgumentException('Divisor must be '.self::MAX_DIVISOR.' or less.');
        }

        $product = Product::find($productId);

        if (! $product) {
            throw new \RuntimeException('Product not found.');
        }

        $oldCost = (float) $product->PRICEBUY;
        $newCost = round($oldCost / $divisor, 4);

        if ($newCost < self::MIN_COST) {
            throw new \InvalidArgumentException('That divisor would reduce the cost price to effectively zero.');
        }

        // The audit lives on the Laravel connection and the price on the POS
        // connection, so one transaction cannot cover both. Writing the audit
        // first inside a transaction means a failed POS write rolls the audit
        // back, and a failed audit leaves the POS untouched -- a price can
        // never change without a record of what it was.
        return DB::transaction(function () use ($product, $oldCost, $newCost, $divisor, $userId) {
            CostPriceAdjustment::create([
                'product_id' => $product->ID,
                'product_code' => $product->CODE,
                'product_name' => $product->NAME,
                'old_cost' => $oldCost,
                'new_cost' => $newCost,
                'sell_price' => $product->PRICESELL,
                'divisor' => $divisor,
                'source' => 'valuation_anomaly',
                'user_id' => $userId,
            ]);

            $product->PRICEBUY = $newCost;
            $product->save();

            return [
                'product_name' => $product->NAME,
                'old_cost' => $oldCost,
                'new_cost' => $newCost,
            ];
        });
    }

    /**
     * Create a new snapshot from current POS data.
     */
    public function createSnapshot(string $name, Carbon $date, int $userId, ?string $notes = null): StockValuationSnapshot
    {
        return DB::transaction(function () use ($name, $date, $userId, $notes) {
            // Get live valuation
            $liveData = $this->calculateLiveValuation();

            // Create snapshot record
            $snapshot = StockValuationSnapshot::create([
                'name' => $name,
                'valuation_date' => $date,
                'status' => 'draft',
                'calculated_total' => $liveData['total_value'],
                'diagnostics' => $this->summariseDiagnostics($liveData),
                'notes' => $notes,
                'created_by' => $userId,
            ]);

            $this->writeSnapshotLines($snapshot, $liveData);

            return $snapshot->fresh(['categories', 'items']);
        });
    }

    /**
     * Refresh a snapshot from current POS data (draft only).
     */
    public function refreshSnapshot(StockValuationSnapshot $snapshot): StockValuationSnapshot
    {
        if (! $snapshot->canBeModified()) {
            throw new \Exception('Cannot refresh a finalized snapshot.');
        }

        return DB::transaction(function () use ($snapshot) {
            // Delete existing categories and items (cascade delete handles items)
            $snapshot->categories()->delete();

            // Get fresh live valuation
            $liveData = $this->calculateLiveValuation();

            $this->writeSnapshotLines($snapshot, $liveData);

            // Update totals
            $snapshot->update([
                'calculated_total' => $liveData['total_value'],
                'diagnostics' => $this->summariseDiagnostics($liveData),
            ]);
            $snapshot->calculateTotals();

            return $snapshot->fresh(['categories', 'items']);
        });
    }

    /**
     * Write category and item rows for a snapshot.
     *
     * Items are inserted in batches rather than one model at a time -- a full
     * valuation runs to a few thousand lines.
     */
    protected function writeSnapshotLines(StockValuationSnapshot $snapshot, array $liveData): void
    {
        $timestamp = now();

        foreach ($liveData['categories'] as $categoryData) {
            $categoryRecord = StockValuationCategory::create([
                'snapshot_id' => $snapshot->id,
                'category_id' => $categoryData['category_id'],
                'category_name' => $categoryData['category_name'],
                'product_count' => $categoryData['product_count'],
                'calculated_value' => $categoryData['total_value'],
            ]);

            $items = array_map(fn ($productData) => [
                'snapshot_id' => $snapshot->id,
                'category_record_id' => $categoryRecord->id,
                'product_id' => $productData['product_id'],
                'product_code' => $productData['product_code'],
                'product_name' => $productData['product_name'],
                'unit_cost' => $productData['unit_cost'],
                'stock_units' => $productData['stock_units'],
                'line_value' => $productData['line_value'],
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ], $categoryData['products']);

            foreach (array_chunk($items, self::INSERT_CHUNK) as $chunk) {
                StockValuationItem::insert($chunk);
            }
        }
    }

    /**
     * Condense the diagnostic blocks for storage against a snapshot.
     *
     * A finalized valuation needs to record what was excluded at the time it
     * was taken, not what happens to be excluded when it is next viewed.
     */
    protected function summariseDiagnostics(array $liveData): array
    {
        return [
            'negative_stock' => [
                'line_count' => $liveData['negative_stock']['line_count'],
                'notional_value' => round($liveData['negative_stock']['notional_value'], 2),
                'items' => $liveData['negative_stock']['items'],
            ],
            'cost_anomalies' => [
                'line_count' => $liveData['cost_anomalies']['line_count'],
                'value' => round($liveData['cost_anomalies']['value'], 2),
                'items' => $liveData['cost_anomalies']['items'],
            ],
        ];
    }

    /**
     * Set a category override value.
     */
    public function setCategoryOverride(StockValuationCategory $category, float $value, ?string $reason = null): void
    {
        $category->setOverride($value, $reason);
    }

    /**
     * Clear a category override value.
     */
    public function clearCategoryOverride(StockValuationCategory $category): void
    {
        $category->clearOverride();
    }

    /**
     * Finalize a snapshot.
     */
    public function finalizeSnapshot(StockValuationSnapshot $snapshot, int $userId): void
    {
        $snapshot->finalize($userId);
    }

    /**
     * Export snapshot to CSV format.
     */
    public function exportToCsv(StockValuationSnapshot $snapshot): string
    {
        $handle = fopen('php://temp', 'r+');

        $write = function (array $fields = []) use ($handle) {
            fputcsv($handle, $fields);
        };

        // Header
        $write(['Stock Valuation Report: '.$snapshot->name]);
        $write(['Date: '.$snapshot->valuation_date->format('d/m/Y')]);
        $write(['Status: '.ucfirst($snapshot->status)]);
        $write(['Basis: Stock at cost (PRICEBUY) x units on hand, ex-VAT. Excludes lines at zero or negative stock.']);
        $write();

        // Category summary
        $write(['CATEGORY SUMMARY']);
        $write(['Category', 'Products', 'Calculated Value', 'Override Value', 'Final Value']);

        foreach ($snapshot->categories()->orderBy('category_name')->get() as $category) {
            $write([
                $category->category_name,
                $category->product_count,
                number_format((float) $category->calculated_value, 2, '.', ''),
                $category->override_value !== null ? number_format((float) $category->override_value, 2, '.', '') : '',
                number_format((float) $category->final_value, 2, '.', ''),
            ]);
        }

        $write();
        $write([
            'TOTAL',
            '',
            number_format((float) $snapshot->calculated_total, 2, '.', ''),
            $snapshot->adjusted_total !== null ? number_format((float) $snapshot->adjusted_total, 2, '.', '') : '',
            number_format((float) $snapshot->final_total, 2, '.', ''),
        ]);

        $write();
        $write();

        // Product details
        $write(['PRODUCT DETAILS']);
        $write(['Category', 'Product Code', 'Product Name', 'Unit Cost', 'Stock Units', 'Line Value']);

        foreach ($snapshot->categories()->orderBy('category_name')->get() as $category) {
            foreach ($category->items()->orderBy('product_name')->get() as $item) {
                $write([
                    $category->category_name,
                    $item->product_code,
                    $item->product_name,
                    number_format((float) $item->unit_cost, 4, '.', ''),
                    number_format((float) $item->stock_units, 2, '.', ''),
                    number_format((float) $item->line_value, 2, '.', ''),
                ]);
            }
        }

        $this->writeDiagnosticBlocks($write, $snapshot->diagnostics ?? []);

        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return rtrim($csv, "\n");
    }

    /**
     * Append the excluded-lines and anomaly blocks to a CSV export.
     */
    protected function writeDiagnosticBlocks(callable $write, array $diagnostics): void
    {
        $negative = $diagnostics['negative_stock'] ?? null;

        if ($negative && ($negative['line_count'] ?? 0) > 0) {
            $write();
            $write();
            $write(['EXCLUDED - NEGATIVE STOCK']);
            $write(['These lines are sold at the till but never booked in, so stock runs permanently']);
            $write(['negative. They are excluded from the valuation above.']);
            $write([
                'Lines excluded',
                $negative['line_count'],
                'Notional value',
                number_format((float) $negative['notional_value'], 2, '.', ''),
            ]);
            $write();
            $write(['Category', 'Product Code', 'Product Name', 'Unit Cost', 'Stock Units', 'Notional Value']);

            foreach ($negative['items'] ?? [] as $item) {
                $write([
                    $item['category_name'],
                    $item['product_code'],
                    $item['product_name'],
                    number_format((float) $item['unit_cost'], 4, '.', ''),
                    number_format((float) $item['stock_units'], 2, '.', ''),
                    number_format((float) $item['line_value'], 2, '.', ''),
                ]);
            }

            if (count($negative['items'] ?? []) < ($negative['line_count'] ?? 0)) {
                $write(['... showing worst '.count($negative['items'] ?? []).' of '.$negative['line_count'].' lines']);
            }
        }

        $anomalies = $diagnostics['cost_anomalies'] ?? null;

        if ($anomalies && ($anomalies['line_count'] ?? 0) > 0) {
            $write();
            $write();
            $write(['COST PRICE ANOMALIES']);
            $write(['Stocked products where cost price exceeds sell price. Usually a case cost entered']);
            $write(['against a unit price. These lines ARE included in the valuation above.']);
            $write([
                'Lines affected',
                $anomalies['line_count'],
                'Value included',
                number_format((float) $anomalies['value'], 2, '.', ''),
            ]);
            $write();
            $write(['Category', 'Product Code', 'Product Name', 'Unit Cost', 'Sell Price', 'Stock Units', 'Line Value']);

            foreach ($anomalies['items'] ?? [] as $item) {
                $write([
                    $item['category_name'],
                    $item['product_code'],
                    $item['product_name'],
                    number_format((float) $item['unit_cost'], 4, '.', ''),
                    number_format((float) $item['sell_price'], 4, '.', ''),
                    number_format((float) $item['stock_units'], 2, '.', ''),
                    number_format((float) $item['line_value'], 2, '.', ''),
                ]);
            }

            if (count($anomalies['items'] ?? []) < ($anomalies['line_count'] ?? 0)) {
                $write(['... showing largest '.count($anomalies['items'] ?? []).' of '.$anomalies['line_count'].' lines']);
            }
        }
    }
}
