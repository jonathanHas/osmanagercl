<?php

namespace App\Services;

use App\Models\AccountingSupplier;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class OrderManagerService
{
    /**
     * Run stock check for all managed suppliers.
     * Returns products grouped by supplier where stock is below threshold.
     */
    public function runStockCheck(): array
    {
        $managedSuppliers = AccountingSupplier::orderManaged()
            ->orderBy('name')
            ->get();

        $results = [];

        foreach ($managedSuppliers as $supplier) {
            $lowStockProducts = $this->getLowStockProductsForSupplier(
                $supplier->external_pos_id,
                $supplier->order_manager_threshold
            );

            $results[] = [
                'supplier' => $supplier,
                'threshold' => $supplier->order_manager_threshold,
                'products' => $lowStockProducts,
                'low_stock_count' => $lowStockProducts->count(),
                'out_of_stock_count' => $lowStockProducts->where('current_stock', '<=', 0)->count(),
            ];
        }

        return $results;
    }

    /**
     * Get products for a specific POS supplier ID with stock below threshold.
     */
    public function getLowStockProductsForSupplier(string $posSupplierID, int $threshold): Collection
    {
        return DB::connection('pos')
            ->table('supplier_link')
            ->join('stocking', 'supplier_link.Barcode', '=', 'stocking.Barcode')
            ->join('PRODUCTS', 'supplier_link.Barcode', '=', 'PRODUCTS.CODE')
            ->leftJoin('STOCKCURRENT', 'PRODUCTS.ID', '=', 'STOCKCURRENT.PRODUCT')
            ->where('supplier_link.SupplierID', $posSupplierID)
            ->where('supplier_link.stocked', true)
            ->where(function ($q) use ($threshold) {
                $q->whereNull('STOCKCURRENT.UNITS')
                    ->orWhere('STOCKCURRENT.UNITS', '<=', $threshold);
            })
            ->select([
                'PRODUCTS.ID as product_id',
                'PRODUCTS.NAME as product_name',
                'PRODUCTS.CODE as barcode',
                DB::raw('COALESCE(STOCKCURRENT.UNITS, 0) as current_stock'),
                'supplier_link.SupplierCode as supplier_code',
                'supplier_link.CaseUnits as case_units',
            ])
            ->orderBy('STOCKCURRENT.UNITS')
            ->orderBy('PRODUCTS.NAME')
            ->get();
    }

    /**
     * Get summary statistics for the dashboard.
     */
    public function getStats(): array
    {
        $managedSuppliers = AccountingSupplier::orderManaged()->get();
        $managedCount = $managedSuppliers->count();

        $totalLowStockProducts = 0;
        $totalOutOfStock = 0;

        foreach ($managedSuppliers as $supplier) {
            $lowStockProducts = $this->getLowStockProductsForSupplier(
                $supplier->external_pos_id,
                $supplier->order_manager_threshold
            );
            $totalLowStockProducts += $lowStockProducts->count();
            $totalOutOfStock += $lowStockProducts->where('current_stock', '<=', 0)->count();
        }

        return [
            'managed_suppliers_count' => $managedCount,
            'low_stock_products_count' => $totalLowStockProducts,
            'out_of_stock_count' => $totalOutOfStock,
        ];
    }

    /**
     * Get all POS-linked suppliers (candidates for management).
     */
    public function getPosLinkedSuppliers(): Collection
    {
        return AccountingSupplier::posLinked()
            ->activeOnly()
            ->orderBy('name')
            ->get();
    }

    /**
     * Get count of low-stock products for a single supplier.
     */
    public function getLowStockCountForSupplier(AccountingSupplier $supplier): int
    {
        if (! $supplier->is_pos_linked || ! $supplier->external_pos_id) {
            return 0;
        }

        return $this->getLowStockProductsForSupplier(
            $supplier->external_pos_id,
            $supplier->order_manager_threshold
        )->count();
    }

    /**
     * Get all stocked products for a supplier with current stock levels.
     */
    public function getAllProductsForSupplier(string $posSupplierID, int $threshold): Collection
    {
        return DB::connection('pos')
            ->table('supplier_link')
            ->join('stocking', 'supplier_link.Barcode', '=', 'stocking.Barcode')
            ->join('PRODUCTS', 'supplier_link.Barcode', '=', 'PRODUCTS.CODE')
            ->leftJoin('STOCKCURRENT', 'PRODUCTS.ID', '=', 'STOCKCURRENT.PRODUCT')
            ->where('supplier_link.SupplierID', $posSupplierID)
            ->where('supplier_link.stocked', true)
            ->select([
                'PRODUCTS.ID as product_id',
                'PRODUCTS.NAME as product_name',
                'PRODUCTS.CODE as barcode',
                DB::raw('COALESCE(STOCKCURRENT.UNITS, 0) as current_stock'),
                'supplier_link.SupplierCode as supplier_code',
                'supplier_link.CaseUnits as case_units',
                DB::raw("CASE
                    WHEN COALESCE(STOCKCURRENT.UNITS, 0) <= 0 THEN 'out_of_stock'
                    WHEN COALESCE(STOCKCURRENT.UNITS, 0) <= {$threshold} THEN 'low_stock'
                    ELSE 'ok'
                END as stock_status"),
            ])
            ->orderBy('STOCKCURRENT.UNITS')
            ->orderBy('PRODUCTS.NAME')
            ->get();
    }
}
