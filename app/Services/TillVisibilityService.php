<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductsCat;
use App\Models\ProductActivityLog;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class TillVisibilityService
{
    /**
     * Category mappings for different product types
     */
    const CATEGORY_MAPPINGS = [
        'fruit_veg' => ['SUB1', 'SUB2', 'SUB3'], // Fruits, Vegetables, Veg Barcoded
        'coffee' => ['COFFEE', 'HOT_DRINKS'],     // To be defined based on actual categories
        'lunch' => ['SANDWICHES', 'SALADS'],      // To be defined based on actual categories
        'cakes' => ['CAKES', 'PASTRIES'],         // To be defined based on actual categories
    ];

    /**
     * Check if a product is visible on till.
     */
    public function isVisibleOnTill(string $productId): bool
    {
        return ProductsCat::isProductVisible($productId);
    }

    /**
     * Set product visibility on till.
     */
    public function setVisibility(string $productId, bool $visible, string $categoryType = null): bool
    {
        $wasVisible = $this->isVisibleOnTill($productId);
        
        if ($visible) {
            if (!$wasVisible) {
                ProductsCat::addProduct($productId);
                
                // Log the activity
                $this->logActivity($productId, ProductActivityLog::TYPE_ADDED_TO_TILL, $categoryType);
            }
            return true;
        } else {
            if ($wasVisible) {
                $result = ProductsCat::removeProduct($productId);
                
                // Log the activity
                $this->logActivity($productId, ProductActivityLog::TYPE_REMOVED_FROM_TILL, $categoryType);
                
                return $result;
            }
            return true;
        }
    }

    /**
     * Toggle product visibility on till.
     */
    public function toggleVisibility(string $productId): bool
    {
        return ProductsCat::toggleProduct($productId);
    }

    /**
     * Bulk update visibility for multiple products.
     */
    public function bulkSetVisibility(array $productIds, bool $visible): void
    {
        ProductsCat::bulkUpdateVisibility($productIds, $visible);
    }

    /**
     * Get all products for a category type with their till visibility status.
     */
    public function getProductsWithVisibility(string $categoryType, array $filters = []): EloquentCollection
    {
        $categoryIds = self::CATEGORY_MAPPINGS[$categoryType] ?? [];
        
        if (empty($categoryIds)) {
            return collect();
        }

        $query = Product::whereIn('CATEGORY', $categoryIds)
            ->with(['category', 'vegDetails.country']);

        // Apply search filter
        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('NAME', 'LIKE', "%{$search}%")
                  ->orWhere('CODE', 'LIKE', "%{$search}%")
                  ->orWhere('DISPLAY', 'LIKE', "%{$search}%");
            });
        }

        // Apply specific category filter
        if (!empty($filters['category']) && $filters['category'] !== 'all') {
            $specificCategory = $this->mapFilterToCategory($categoryType, $filters['category']);
            if ($specificCategory) {
                $query->where('CATEGORY', $specificCategory);
            }
        }

        // Get products
        $products = $query->orderBy('NAME')->get();

        // Get visible product IDs
        $visibleProductIds = ProductsCat::whereIn('PRODUCT', $products->pluck('ID'))
            ->pluck('PRODUCT')
            ->toArray();

        // Add visibility status to each product
        $products->each(function ($product) use ($visibleProductIds) {
            $product->is_visible_on_till = in_array($product->ID, $visibleProductIds);
        });

        // Apply visibility filter
        if (!empty($filters['visibility']) && $filters['visibility'] !== 'all') {
            $products = $products->filter(function ($product) use ($filters) {
                return $filters['visibility'] === 'visible' 
                    ? $product->is_visible_on_till 
                    : !$product->is_visible_on_till;
            });
        }

        return $products;
    }

    /**
     * Get visible products for a category type.
     */
    public function getVisibleProducts(string $categoryType): EloquentCollection
    {
        $categoryIds = self::CATEGORY_MAPPINGS[$categoryType] ?? [];
        
        if (empty($categoryIds)) {
            return collect();
        }

        return ProductsCat::getVisibleProductsForCategories($categoryIds)->get();
    }

    /**
     * Get count of visible products for a category type.
     */
    public function getVisibleCount(string $categoryType): int
    {
        $categoryIds = self::CATEGORY_MAPPINGS[$categoryType] ?? [];
        
        if (empty($categoryIds)) {
            return 0;
        }

        return ProductsCat::whereHas('product', function ($query) use ($categoryIds) {
            $query->whereIn('CATEGORY', $categoryIds);
        })->count();
    }

    /**
     * Get count of visible products for specific category.
     */
    public function getVisibleCountForCategory(string $categoryId): int
    {
        return ProductsCat::whereHas('product', function ($query) use ($categoryId) {
            $query->where('CATEGORY', $categoryId);
        })->count();
    }

    /**
     * Get featured visible products for display.
     */
    public function getFeaturedVisibleProducts(string $categoryType, int $limit = 12): EloquentCollection
    {
        $categoryIds = self::CATEGORY_MAPPINGS[$categoryType] ?? [];
        
        if (empty($categoryIds)) {
            return collect();
        }

        return ProductsCat::getVisibleProductsForCategories($categoryIds)
            ->limit($limit)
            ->get()
            ->map(function ($productsCat) {
                $product = $productsCat->product;
                $product->is_visible_on_till = true;
                return $product;
            });
    }

    /**
     * Map filter value to specific category ID.
     */
    private function mapFilterToCategory(string $categoryType, string $filter): ?string
    {
        $mappings = [
            'fruit_veg' => [
                'fruit' => 'SUB1',
                'vegetables' => 'SUB2',
                'veg_barcoded' => 'SUB3',
            ],
            // Add mappings for other category types as needed
        ];

        return $mappings[$categoryType][$filter] ?? null;
    }

    /**
     * Migrate from old availability system to PRODUCTS_CAT.
     * This is a one-time migration method.
     */
    public function migrateFromVegAvailability(): array
    {
        $migrated = 0;
        $errors = 0;

        // Get all available products from old system
        $availableProducts = DB::table('veg_availability')
            ->where('is_available', true)
            ->pluck('product_code');

        // Get product IDs from codes
        $products = Product::whereIn('CODE', $availableProducts)
            ->pluck('ID', 'CODE');

        // Start transaction
        DB::beginTransaction();

        try {
            foreach ($products as $code => $id) {
                if (!ProductsCat::isProductVisible($id)) {
                    ProductsCat::addProduct($id);
                    $migrated++;
                }
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            $errors++;
            throw $e;
        }

        return [
            'migrated' => $migrated,
            'errors' => $errors,
            'total' => count($products),
        ];
    }

    /**
     * Get category statistics for a category type.
     */
    public function getCategoryStats(string $categoryType): array
    {
        $stats = [];
        
        if ($categoryType === 'fruit_veg') {
            // Fruit stats
            $totalFruits = Product::where('CATEGORY', 'SUB1')->count();
            $visibleFruits = $this->getVisibleCountForCategory('SUB1');
            
            // Vegetable stats (SUB2 and SUB3)
            $totalVegetables = Product::whereIn('CATEGORY', ['SUB2', 'SUB3'])->count();
            $visibleVegetables = ProductsCat::whereHas('product', function ($query) {
                $query->whereIn('CATEGORY', ['SUB2', 'SUB3']);
            })->count();
            
            $stats = [
                'total_fruits' => $totalFruits,
                'visible_fruits' => $visibleFruits,
                'total_vegetables' => $totalVegetables,
                'visible_vegetables' => $visibleVegetables,
            ];
        }
        // Add stats for other category types as needed
        
        return $stats;
    }

    /**
     * Log product activity.
     */
    protected function logActivity(string $productId, string $activityType, string $categoryType = null, array $oldValue = null, array $newValue = null): void
    {
        // Get product details
        $product = Product::find($productId);
        if (!$product) {
            return;
        }

        ProductActivityLog::create([
            'product_id' => $productId,
            'product_code' => $product->CODE,
            'activity_type' => $activityType,
            'category' => $categoryType,
            'old_value' => $oldValue,
            'new_value' => $newValue,
            'user_id' => Auth::id(),
        ]);
    }

    /**
     * Get recently added products for a category type.
     */
    public function getRecentlyAddedProducts(string $categoryType, int $days = 7, int $limit = 10): Collection
    {
        $logs = ProductActivityLog::recentlyAdded($categoryType, $days)
            ->with('product.category')
            ->limit($limit)
            ->get();

        // Get unique products with their visibility status
        $products = collect();
        $addedProductIds = [];

        foreach ($logs as $log) {
            if (!in_array($log->product_id, $addedProductIds) && $log->product) {
                $product = $log->product;
                $product->is_visible_on_till = $this->isVisibleOnTill($log->product_id);
                $product->added_at = $log->created_at;
                
                // Get current price from price history or product
                $lastPriceRecord = DB::table('veg_price_history')
                    ->where('product_code', $product->CODE)
                    ->orderBy('changed_at', 'desc')
                    ->first();
                
                $product->current_price = $lastPriceRecord ? $lastPriceRecord->new_price : $product->getGrossPrice();
                
                $products->push($product);
                $addedProductIds[] = $log->product_id;
            }
        }

        return $products;
    }

    /**
     * Get product activity history.
     */
    public function getProductActivityHistory(string $productId, int $limit = 10): Collection
    {
        return ProductActivityLog::where('product_id', $productId)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }
}