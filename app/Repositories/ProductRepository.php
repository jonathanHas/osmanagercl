<?php

namespace App\Repositories;

use App\Models\Product;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class ProductRepository
{
    /**
     * Get all products with pagination.
     *
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function getAllProducts(int $perPage = 20): LengthAwarePaginator
    {
        return Product::orderBy('NAME')
            ->paginate($perPage);
    }

    /**
     * Find a product by ID.
     *
     * @param string $id
     * @return Product|null
     */
    public function findById(string $id): ?Product
    {
        return Product::find($id);
    }

    /**
     * Search products by name.
     *
     * @param string $name
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function searchByName(string $name, int $perPage = 20): LengthAwarePaginator
    {
        return Product::where('NAME', 'like', '%' . $name . '%')
            ->orderBy('NAME')
            ->paginate($perPage);
    }

    /**
     * Search products by code or reference.
     *
     * @param string $code
     * @return Collection
     */
    public function searchByCode(string $code): Collection
    {
        return Product::where('CODE', 'like', '%' . $code . '%')
            ->orWhere('REFERENCE', 'like', '%' . $code . '%')
            ->orderBy('NAME')
            ->get();
    }

    /**
     * Get active (non-service) products.
     *
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function getActiveProducts(int $perPage = 20): LengthAwarePaginator
    {
        return Product::active()
            ->orderBy('NAME')
            ->paginate($perPage);
    }

    /**
     * Get products by category ID.
     *
     * @param string $categoryId
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function getByCategory(string $categoryId, int $perPage = 20): LengthAwarePaginator
    {
        return Product::where('CATEGORY', $categoryId)
            ->orderBy('NAME')
            ->paginate($perPage);
    }

    /**
     * Search products with multiple criteria.
     *
     * @param string|null $search
     * @param bool|null $activeOnly
     * @param string|null $categoryId
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function searchProducts(
        ?string $search = null,
        ?bool $activeOnly = null,
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

        if ($categoryId) {
            $query->where('CATEGORY', $categoryId);
        }

        return $query->orderBy('NAME')->paginate($perPage);
    }

    /**
     * Get product statistics.
     *
     * @return array
     */
    public function getStatistics(): array
    {
        return [
            'total_products' => Product::count(),
            'active_products' => Product::active()->count(),
            'service_products' => Product::where('ISSERVICE', 1)->count(),
            'in_stock' => Product::inStock()->count(),
            'out_of_stock' => Product::where('STOCKUNITS', '<=', 0)->count(),
        ];
    }

    /**
     * Get products that are low in stock.
     *
     * @param float $threshold
     * @param int $limit
     * @return Collection
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
     *
     * @param int $limit
     * @return Collection
     */
    public function getRecentProducts(int $limit = 10): Collection
    {
        return Product::orderBy('ID', 'desc')
            ->limit($limit)
            ->get();
    }
}