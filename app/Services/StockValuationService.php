<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Product;
use App\Models\StockValuationCategory;
use App\Models\StockValuationItem;
use App\Models\StockValuationSnapshot;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class StockValuationService
{
    /**
     * Calculate live valuation from current POS data (no snapshot).
     */
    public function calculateLiveValuation(): array
    {
        // Get all products with stock and their categories
        $products = Product::with(['stockCurrent', 'category'])
            ->whereNotNull('CATEGORY')
            ->get();

        $categories = [];
        $totalValue = 0;

        foreach ($products as $product) {
            $categoryId = $product->CATEGORY;
            $categoryName = $product->category?->NAME ?? 'Uncategorized';
            $cost = (float) ($product->PRICEBUY ?? 0);
            $stock = (float) ($product->stockCurrent?->UNITS ?? 0);
            $value = $cost * $stock;

            if (! isset($categories[$categoryId])) {
                $categories[$categoryId] = [
                    'category_id' => $categoryId,
                    'category_name' => $categoryName,
                    'product_count' => 0,
                    'total_value' => 0,
                    'products' => [],
                ];
            }

            $categories[$categoryId]['product_count']++;
            $categories[$categoryId]['total_value'] += $value;
            $categories[$categoryId]['products'][] = [
                'product_id' => $product->ID,
                'product_code' => $product->CODE,
                'product_name' => $product->NAME,
                'unit_cost' => $cost,
                'stock_units' => $stock,
                'line_value' => $value,
            ];

            $totalValue += $value;
        }

        // Sort by category name
        uasort($categories, fn ($a, $b) => strcmp($a['category_name'], $b['category_name']));

        return [
            'valuation_date' => now(),
            'categories' => array_values($categories),
            'total_value' => $totalValue,
            'category_count' => count($categories),
            'product_count' => $products->count(),
        ];
    }

    /**
     * Get live products for a specific category.
     */
    public function getLiveCategoryProducts(string $categoryId): array
    {
        $products = Product::with(['stockCurrent', 'category'])
            ->where('CATEGORY', $categoryId)
            ->get();

        $category = Category::find($categoryId);
        $items = [];
        $totalValue = 0;

        foreach ($products as $product) {
            $cost = (float) ($product->PRICEBUY ?? 0);
            $stock = (float) ($product->stockCurrent?->UNITS ?? 0);
            $value = $cost * $stock;

            $items[] = [
                'product_id' => $product->ID,
                'product_code' => $product->CODE,
                'product_name' => $product->NAME,
                'unit_cost' => $cost,
                'stock_units' => $stock,
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
            'total_value' => $totalValue,
            'products' => $items,
        ];
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
                'notes' => $notes,
                'created_by' => $userId,
            ]);

            // Create category and item records
            foreach ($liveData['categories'] as $categoryData) {
                $categoryRecord = StockValuationCategory::create([
                    'snapshot_id' => $snapshot->id,
                    'category_id' => $categoryData['category_id'],
                    'category_name' => $categoryData['category_name'],
                    'product_count' => $categoryData['product_count'],
                    'calculated_value' => $categoryData['total_value'],
                ]);

                // Create item records
                foreach ($categoryData['products'] as $productData) {
                    StockValuationItem::create([
                        'snapshot_id' => $snapshot->id,
                        'category_record_id' => $categoryRecord->id,
                        'product_id' => $productData['product_id'],
                        'product_code' => $productData['product_code'],
                        'product_name' => $productData['product_name'],
                        'unit_cost' => $productData['unit_cost'],
                        'stock_units' => $productData['stock_units'],
                        'line_value' => $productData['line_value'],
                    ]);
                }
            }

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

            // Recreate category and item records
            foreach ($liveData['categories'] as $categoryData) {
                $categoryRecord = StockValuationCategory::create([
                    'snapshot_id' => $snapshot->id,
                    'category_id' => $categoryData['category_id'],
                    'category_name' => $categoryData['category_name'],
                    'product_count' => $categoryData['product_count'],
                    'calculated_value' => $categoryData['total_value'],
                ]);

                foreach ($categoryData['products'] as $productData) {
                    StockValuationItem::create([
                        'snapshot_id' => $snapshot->id,
                        'category_record_id' => $categoryRecord->id,
                        'product_id' => $productData['product_id'],
                        'product_code' => $productData['product_code'],
                        'product_name' => $productData['product_name'],
                        'unit_cost' => $productData['unit_cost'],
                        'stock_units' => $productData['stock_units'],
                        'line_value' => $productData['line_value'],
                    ]);
                }
            }

            // Update totals
            $snapshot->update(['calculated_total' => $liveData['total_value']]);
            $snapshot->calculateTotals();

            return $snapshot->fresh(['categories', 'items']);
        });
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
        $lines = [];

        // Header
        $lines[] = 'Stock Valuation Report: '.$snapshot->name;
        $lines[] = 'Date: '.$snapshot->valuation_date->format('d/m/Y');
        $lines[] = 'Status: '.ucfirst($snapshot->status);
        $lines[] = '';

        // Category summary
        $lines[] = 'CATEGORY SUMMARY';
        $lines[] = 'Category,Products,Calculated Value,Override Value,Final Value';

        foreach ($snapshot->categories()->orderBy('category_name')->get() as $category) {
            $lines[] = sprintf(
                '"%s",%d,%.2f,%s,%.2f',
                $category->category_name,
                $category->product_count,
                $category->calculated_value,
                $category->override_value !== null ? number_format($category->override_value, 2) : '',
                $category->final_value
            );
        }

        $lines[] = '';
        $lines[] = sprintf('TOTAL,,%s,%s,%.2f',
            number_format($snapshot->calculated_total, 2),
            $snapshot->adjusted_total !== null ? number_format($snapshot->adjusted_total, 2) : '',
            $snapshot->final_total
        );

        $lines[] = '';
        $lines[] = '';

        // Product details
        $lines[] = 'PRODUCT DETAILS';
        $lines[] = 'Category,Product Code,Product Name,Unit Cost,Stock Units,Line Value';

        foreach ($snapshot->categories()->orderBy('category_name')->get() as $category) {
            foreach ($category->items()->orderBy('product_name')->get() as $item) {
                $lines[] = sprintf(
                    '"%s","%s","%s",%.4f,%.2f,%.2f',
                    $category->category_name,
                    $item->product_code,
                    $item->product_name,
                    $item->unit_cost,
                    $item->stock_units,
                    $item->line_value
                );
            }
        }

        return implode("\n", $lines);
    }
}
