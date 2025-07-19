<?php

namespace App\Repositories;

use App\Models\Product;
use App\Models\TaxCategory;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class ProductRepository
{
    /**
     * Get all products with pagination.
     */
    public function getAllProducts(int $perPage = 20): LengthAwarePaginator
    {
        return Product::with(['stockCurrent', 'taxCategory', 'tax'])
            ->orderBy('NAME')
            ->paginate($perPage);
    }

    /**
     * Find a product by ID.
     */
    public function findById(string $id): ?Product
    {
        return Product::with(['stockCurrent', 'taxCategory', 'tax'])
            ->find($id);
    }

    /**
     * Search products by name.
     */
    public function searchByName(string $name, int $perPage = 20): LengthAwarePaginator
    {
        return Product::where('NAME', 'like', '%'.$name.'%')
            ->orderBy('NAME')
            ->paginate($perPage);
    }

    /**
     * Search products by code or reference.
     */
    public function searchByCode(string $code): Collection
    {
        return Product::where('CODE', 'like', '%'.$code.'%')
            ->orWhere('REFERENCE', 'like', '%'.$code.'%')
            ->orderBy('NAME')
            ->get();
    }

    /**
     * Get active (non-service) products.
     */
    public function getActiveProducts(int $perPage = 20): LengthAwarePaginator
    {
        return Product::with(['stockCurrent', 'taxCategory', 'tax'])
            ->active()
            ->orderBy('NAME')
            ->paginate($perPage);
    }

    /**
     * Get products that are stocked and have current stock.
     */
    public function getAvailableProducts(int $perPage = 20): LengthAwarePaginator
    {
        return Product::with(['stockCurrent', 'taxCategory', 'tax'])
            ->active()
            ->stocked()
            ->inCurrentStock()
            ->orderBy('NAME')
            ->paginate($perPage);
    }

    /**
     * Get products by category ID.
     */
    public function getByCategory(string $categoryId, int $perPage = 20): LengthAwarePaginator
    {
        return Product::where('CATEGORY', $categoryId)
            ->orderBy('NAME')
            ->paginate($perPage);
    }

    /**
     * Search products with multiple criteria.
     */
    public function searchProducts(
        ?string $search = null,
        ?bool $activeOnly = null,
        ?bool $stockedOnly = null,
        ?bool $inStockOnly = null,
        ?string $categoryId = null,
        int $perPage = 20
    ): LengthAwarePaginator {
        $query = Product::query();

        if ($search) {
            $query->search($search);
        }

        if ($activeOnly === true) {
            $query->active();
        }

        if ($stockedOnly === true) {
            $query->stocked();
        }

        if ($inStockOnly === true) {
            $query->inCurrentStock();
        }

        if ($categoryId) {
            $query->where('CATEGORY', $categoryId);
        }

        return $query->with(['stockCurrent', 'taxCategory', 'tax'])->orderBy('NAME')->paginate($perPage);
    }

    /**
     * Get product statistics.
     */
    public function getStatistics(): array
    {
        return [
            'total_products' => Product::count(),
            'active_products' => Product::active()->count(),
            'service_products' => Product::where('ISSERVICE', 1)->count(),
            'stocked_products' => Product::stocked()->count(),
            'in_stock' => Product::inCurrentStock()->count(),
            'out_of_stock' => Product::active()->where(function ($query) {
                $query->whereDoesntHave('stockCurrent')
                    ->orWhereHas('stockCurrent', function ($q) {
                        $q->where('UNITS', '<=', 0);
                    });
            })->count(),
        ];
    }

    /**
     * Get products that are low in stock.
     */
    public function getLowStockProducts(float $threshold = 10, int $limit = 10): Collection
    {
        return Product::where('STOCKUNITS', '>', 0)
            ->where('STOCKUNITS', '<=', $threshold)
            ->where('ISSERVICE', 0)
            ->orderBy('STOCKUNITS')
            ->limit($limit)
            ->get();
    }

    /**
     * Get recently added products.
     */
    public function getRecentProducts(int $limit = 10): Collection
    {
        return Product::orderBy('ID', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Get all tax categories for dropdown lists.
     */
    public function getAllTaxCategories(): Collection
    {
        return TaxCategory::with('primaryTax')
            ->orderBy('NAME')
            ->get();
    }
}
