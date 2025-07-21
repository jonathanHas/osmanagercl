<?php

namespace App\Repositories;

use App\Models\Product;
use App\Models\TaxCategory;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Facades\DB;

class ProductRepository
{
    /**
     * Get all products with pagination.
     */
    public function getAllProducts(int $perPage = 20, bool $withSuppliers = false): LengthAwarePaginator
    {
        $query = Product::query();

        if ($withSuppliers) {
            $query->with(['stockCurrent', 'taxCategory', 'tax', 'supplierLink', 'supplier']);
        } else {
            $query->with(['stockCurrent', 'taxCategory', 'tax']);
        }

        return $query->orderBy('NAME')->paginate($perPage);
    }

    /**
     * Find a product by ID.
     */
    public function findById(string $id): ?Product
    {
        return Product::with(['stockCurrent', 'taxCategory', 'tax', 'supplierLink', 'supplier'])
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
        ?string $supplierId = null,
        int $perPage = 20,
        bool $withSuppliers = false
    ): LengthAwarePaginator {
        // For any supplier filtering, use an optimized strategy that pre-filters by supplier first
        if ($supplierId) {
            return $this->searchProductsWithSupplierOptimized(
                $search, $activeOnly, $stockedOnly, $inStockOnly,
                $categoryId, $supplierId, $perPage, $withSuppliers
            );
        }

        $query = Product::query();

        // Apply basic filters first
        if ($categoryId) {
            $query->where('CATEGORY', $categoryId);
        }

        if ($activeOnly === true) {
            $query->active();
        }

        // Optimize stock filtering based on context
        if ($inStockOnly === true) {
            // For in-stock filtering, use JOIN for better performance when no supplier filter
            $query->join('STOCKCURRENT', 'PRODUCTS.ID', '=', 'STOCKCURRENT.PRODUCT')
                ->where('STOCKCURRENT.UNITS', '>', 0);
        } elseif ($stockedOnly === true) {
            // For stocked filtering, use JOIN for better performance when no supplier filter
            $query->join('stocking', 'PRODUCTS.CODE', '=', 'stocking.Barcode');
        }

        // Filter by supplier using EXISTS to avoid conflicts with stock JOINs
        if ($supplierId) {
            $query->whereExists(function ($q) use ($supplierId) {
                $q->select(DB::raw(1))
                    ->from('supplier_link')
                    ->whereRaw('supplier_link.Barcode = PRODUCTS.CODE')
                    ->where('supplier_link.SupplierID', $supplierId);
            });
        }

        if ($search) {
            $query->search($search);
        }

        // Load relationships based on requirements
        if ($withSuppliers) {
            $query->with(['stockCurrent', 'taxCategory', 'tax', 'supplierLink', 'supplier']);
        } else {
            $query->with(['stockCurrent', 'taxCategory', 'tax']);
        }

        return $query->orderBy('NAME')->paginate($perPage);
    }

    /**
     * Optimized search for products with supplier filtering.
     * This method pre-filters by supplier first to reduce the dataset size,
     * which is especially important for suppliers with large product catalogs.
     */
    private function searchProductsWithSupplierOptimized(
        ?string $search,
        ?bool $activeOnly,
        ?bool $stockedOnly,
        ?bool $inStockOnly,
        ?string $categoryId,
        string $supplierId,
        int $perPage,
        bool $withSuppliers
    ): LengthAwarePaginator {
        // Start with supplier products directly to reduce dataset size
        $supplierProducts = DB::connection('pos')
            ->table('supplier_link')
            ->where('SupplierID', $supplierId)
            ->pluck('Barcode');

        $query = Product::query()->whereIn('CODE', $supplierProducts);

        // Apply basic filters
        if ($categoryId) {
            $query->where('CATEGORY', $categoryId);
        }

        if ($activeOnly === true) {
            $query->active();
        }

        // Apply stock filters more efficiently on the pre-filtered set
        if ($inStockOnly === true) {
            $query->whereExists(function ($q) {
                $q->select(DB::raw(1))
                    ->from('STOCKCURRENT')
                    ->whereRaw('STOCKCURRENT.PRODUCT = PRODUCTS.ID')
                    ->where('STOCKCURRENT.UNITS', '>', 0);
            });
        } elseif ($stockedOnly === true) {
            // Since we already have supplier products, filter stocking records by these barcodes first
            $stockedBarcodes = DB::connection('pos')
                ->table('stocking')
                ->whereIn('Barcode', $supplierProducts)
                ->pluck('Barcode');

            $query->whereIn('CODE', $stockedBarcodes);
        }

        if ($search) {
            $query->search($search);
        }

        // Load relationships based on requirements
        if ($withSuppliers) {
            $query->with(['stockCurrent', 'taxCategory', 'tax', 'supplierLink', 'supplier']);
        } else {
            $query->with(['stockCurrent', 'taxCategory', 'tax']);
        }

        return $query->orderBy('NAME')->paginate($perPage);
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
        return Product::whereExists(function ($q) use ($threshold) {
            $q->select(DB::raw(1))
                ->from('STOCKCURRENT')
                ->whereRaw('STOCKCURRENT.PRODUCT = PRODUCTS.ID')
                ->where('STOCKCURRENT.UNITS', '>', 0)
                ->where('STOCKCURRENT.UNITS', '<=', $threshold);
        })
            ->where('ISSERVICE', 0)
            ->with(['stockCurrent'])
            ->limit($limit)
            ->get()
            ->sortBy(function ($product) {
                return $product->getCurrentStock();
            })
            ->values();
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

    /**
     * Get all suppliers that have products for dropdown filter.
     */
    public function getAllSuppliersWithProducts(
        ?bool $stockedOnly = null,
        ?bool $inStockOnly = null,
        ?bool $activeOnly = null
    ): SupportCollection {
        // If no filters are applied, use the simple query
        if (! $stockedOnly && ! $inStockOnly && ! $activeOnly) {
            return DB::connection('pos')
                ->table('suppliers')
                ->join('supplier_link', 'suppliers.SupplierID', '=', 'supplier_link.SupplierID')
                ->join('PRODUCTS', 'supplier_link.Barcode', '=', 'PRODUCTS.CODE')
                ->select('suppliers.SupplierID', 'suppliers.Supplier')
                ->distinct()
                ->orderBy('suppliers.Supplier')
                ->get();
        }

        // For filtered queries, use EXISTS subqueries to avoid complex JOINs
        $query = DB::connection('pos')
            ->table('suppliers')
            ->whereExists(function ($q) use ($stockedOnly, $inStockOnly, $activeOnly) {
                $subQuery = $q->select(DB::raw(1))
                    ->from('supplier_link')
                    ->join('PRODUCTS', 'supplier_link.Barcode', '=', 'PRODUCTS.CODE')
                    ->whereRaw('supplier_link.SupplierID = suppliers.SupplierID');

                if ($activeOnly === true) {
                    $subQuery->where('PRODUCTS.ISSERVICE', 0);
                }

                if ($stockedOnly === true) {
                    $subQuery->whereExists(function ($stQuery) {
                        $stQuery->select(DB::raw(1))
                            ->from('stocking')
                            ->whereRaw('stocking.Barcode = supplier_link.Barcode');
                    });
                }

                if ($inStockOnly === true) {
                    $subQuery->whereExists(function ($stQuery) {
                        $stQuery->select(DB::raw(1))
                            ->from('STOCKCURRENT')
                            ->whereRaw('STOCKCURRENT.PRODUCT = PRODUCTS.ID')
                            ->where('STOCKCURRENT.UNITS', '>', 0);
                    });
                }
            });

        return $query->select('suppliers.SupplierID', 'suppliers.Supplier')
            ->orderBy('suppliers.Supplier')
            ->get();
    }
}
