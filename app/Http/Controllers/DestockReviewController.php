<?php

namespace App\Http\Controllers;

use App\Models\DestockAudit;
use App\Models\Product;
use App\Models\SalesDailySummary;
use App\Models\Supplier;
use App\Models\User;
use App\Services\SupplierService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DestockReviewController extends Controller
{
    public function index(Request $request)
    {
        $query = DestockAudit::with('user')
            ->orderByDesc('created_at');

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        if ($request->filled('action') && in_array($request->action, ['destock', 'restock'])) {
            $query->where('action', $request->action);
        }

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        if ($request->filled('barcode')) {
            $query->where('barcode', 'like', '%' . $request->barcode . '%');
        }

        $audits = $query->paginate(30);
        $users = User::orderBy('name')->pluck('name', 'id');

        return view('destock-review.index', compact('audits', 'users'));
    }

    public function suggestions(Request $request, SupplierService $supplierService)
    {
        $days = (int) $request->input('days', 30);
        $days = max(7, min($days, 365));
        $startDate = Carbon::now()->subDays($days);
        $minUnits = (float) $request->input('min_units', 1);
        $excludeFv = $request->input('exclude_fv', '1') === '1';
        $sortBy = $request->input('sort', 'units');
        $supplierId = $request->input('supplier_id');
        $search = $request->input('search');

        // Get all currently stocked barcodes from POS
        $stockedBarcodes = DB::connection('pos')
            ->table('stocking')
            ->pluck('Barcode')
            ->toArray();

        // Build query
        $query = SalesDailySummary::select(
                'product_code',
                'product_name',
                'category_id',
                DB::raw('SUM(total_units) as total_units_sold'),
                DB::raw('SUM(total_revenue) as total_revenue'),
                DB::raw('COUNT(DISTINCT sale_date) as days_with_sales'),
                DB::raw('MIN(sale_date) as first_sale'),
                DB::raw('MAX(sale_date) as last_sale')
            )
            ->where('sale_date', '>=', $startDate->format('Y-m-d'))
            ->whereNotIn('product_code', $stockedBarcodes);

        // Exclude F&V categories
        if ($excludeFv) {
            $query->whereNotIn('category_id', ['SUB1', 'SUB2', 'SUB3']);
        }

        // Search by product name or barcode
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('product_name', 'like', '%' . $search . '%')
                  ->orWhere('product_code', 'like', '%' . $search . '%');
            });
        }

        // Filter by supplier (cross-database, so pre-filter barcodes)
        if ($supplierId) {
            $supplierBarcodes = DB::connection('pos')
                ->table('supplier_link')
                ->where('SupplierID', $supplierId)
                ->pluck('Barcode')
                ->toArray();
            $query->whereIn('product_code', $supplierBarcodes);
        }

        $query->groupBy('product_code', 'product_name', 'category_id')
            ->having('total_units_sold', '>=', $minUnits);

        // Sort
        match ($sortBy) {
            'revenue' => $query->orderByDesc('total_revenue'),
            'days' => $query->orderByDesc('days_with_sales'),
            'last_sale' => $query->orderByDesc('last_sale'),
            default => $query->orderByDesc('total_units_sold'),
        };

        $suggestions = $query->paginate(30);

        // Load Product models with supplier relationships for the results
        $barcodes = $suggestions->pluck('product_code')->toArray();
        $products = Product::whereIn('CODE', $barcodes)
            ->with(['supplier', 'supplierLink'])
            ->get()
            ->keyBy('CODE');

        // Get suppliers for filter dropdown
        $suppliers = Supplier::orderBy('Supplier')
            ->get()
            ->pluck('Supplier', 'SupplierID');

        return view('destock-review.suggestions', compact(
            'suggestions', 'days', 'minUnits', 'excludeFv', 'sortBy',
            'supplierId', 'products', 'suppliers', 'supplierService'
        ));
    }
}
