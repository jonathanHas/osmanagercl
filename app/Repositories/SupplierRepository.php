<?php

namespace App\Repositories;

use App\Models\Product;
use App\Models\Supplier;
use App\Models\SupplierLink;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class SupplierRepository
{
    /**
     * Get products with their supplier information.
     */
    public function getProductsWithSuppliers(int $perPage = 25): LengthAwarePaginator
    {
        return Product::with(['supplierLink', 'supplier'])
            ->select(['ID', 'CODE', 'NAME', 'PRICESELL'])
            ->paginate($perPage);
    }

    /**
     * Get all products for a specific supplier.
     */
    public function getSupplierProducts(string $supplierId): Collection
    {
        return Product::whereHas('supplierLink', function ($query) use ($supplierId) {
            $query->where('SupplierID', $supplierId);
        })->with('supplierLink')->get();
    }

    /**
     * Update the cost for a product from a supplier.
     */
    public function updateSupplierCost(string $productCode, float $newCost): int
    {
        return SupplierLink::where('Barcode', $productCode)
            ->update(['Cost' => $newCost]);
    }

    /**
     * Get products with low margins (below threshold).
     */
    public function getLowMarginProducts(float $marginThreshold = 20.0): Collection
    {
        return Product::with(['supplierLink'])
            ->whereHas('supplierLink', function ($query) {
                $query->where('Cost', '>', 0);
            })
            ->get()
            ->filter(function ($product) use ($marginThreshold) {
                $cost = $product->supplierLink->Cost;
                $price = $product->PRICESELL;
                $margin = (($price - $cost) / $price) * 100;

                return $margin < $marginThreshold;
            });
    }

    /**
     * Get supplier statistics.
     */
    public function getSupplierStatistics(string $supplierId): array
    {
        $products = $this->getSupplierProducts($supplierId);

        $totalProducts = $products->count();
        $totalValue = 0;
        $averageMargin = 0;
        $margins = [];

        foreach ($products as $product) {
            if ($product->supplierLink && $product->supplierLink->Cost > 0) {
                $cost = $product->supplierLink->Cost;
                $price = $product->PRICESELL;
                $totalValue += $cost * ($product->STOCKUNITS ?? 0);
                $margins[] = (($price - $cost) / $price) * 100;
            }
        }

        if (count($margins) > 0) {
            $averageMargin = array_sum($margins) / count($margins);
        }

        return [
            'total_products' => $totalProducts,
            'total_stock_value' => $totalValue,
            'average_margin' => $averageMargin,
            'products_with_cost' => count($margins),
        ];
    }

    /**
     * Find products without supplier links.
     */
    public function getProductsWithoutSuppliers(int $perPage = 25): LengthAwarePaginator
    {
        return Product::doesntHave('supplierLink')
            ->select(['ID', 'CODE', 'NAME', 'PRICESELL'])
            ->paginate($perPage);
    }

    /**
     * Create or update a supplier link for a product.
     */
    public function upsertSupplierLink(array $data): SupplierLink
    {
        return SupplierLink::updateOrCreate(
            ['Barcode' => $data['Barcode']],
            $data
        );
    }
}
