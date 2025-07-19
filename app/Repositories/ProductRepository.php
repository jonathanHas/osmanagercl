<?php

namespace App\Repositories;

use App\Models\Product;
use App\Models\TaxCategory;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

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

        // Apply filters in order of most restrictive first for better performance
        if ($categoryId) {
            $query->where('CATEGORY', $categoryId);
        }

        if ($activeOnly === true) {
            $query->active();
        }

        // Handle stock filters - use inCurrentStock if both are selected as it's more restrictive
        if ($inStockOnly === true) {
            $query->inCurrentStock();
        } elseif ($stockedOnly === true) {
            $query->stocked();
        }

        if ($search) {
            $query->search($search);
        }

        // When using JOINs, we need to specify the table for ORDER BY
        $orderByColumn = ($stockedOnly || $inStockOnly) ? 'PRODUCTS.NAME' : 'NAME';
        
        return $query->with(['stockCurrent', 'taxCategory', 'tax'])
            ->orderBy($orderByColumn)
            ->paginate($perPage);
    }

    /**
     * Get product statistics.
     */
    public function getStatistics(): array
    {
        // Using raw queries for statistics is much faster
        $stockedCount = DB::connection('pos')
            ->table('PRODUCTS')
            ->join('stocking', 'PRODUCTS.CODE', '=', 'stocking.Barcode')
            ->distinct()
            ->count('PRODUCTS.ID');

        $inStockCount = DB::connection('pos')
            ->table('PRODUCTS')
            ->join('STOCKCURRENT', 'PRODUCTS.ID', '=', 'STOCKCURRENT.PRODUCT')
            ->where('STOCKCURRENT.UNITS', '>', 0)
            ->count();

        $activeCount = Product::where('ISSERVICE', 0)->count();

        // Calculate out of stock more efficiently
        $outOfStockCount = DB::connection('pos')
            ->table('PRODUCTS')
            ->where('ISSERVICE', 0)
            ->leftJoin('STOCKCURRENT', 'PRODUCTS.ID', '=', 'STOCKCURRENT.PRODUCT')
            ->where(function ($query) {
                $query->whereNull('STOCKCURRENT.PRODUCT')
                    ->orWhere('STOCKCURRENT.UNITS', '<=', 0);
            })
            ->count();

        return [
            'total_products' => Product::count(),
            'active_products' => $activeCount,
            'service_products' => Product::where('ISSERVICE', 1)->count(),
            'stocked_products' => $stockedCount,
            'in_stock' => $inStockCount,
            'out_of_stock' => $outOfStockCount,
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
