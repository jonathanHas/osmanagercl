<?php

namespace App\Http\Controllers;

use App\Models\DestockAudit;
use App\Models\SalesDailySummary;
use App\Models\User;
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

    public function suggestions(Request $request)
    {
        $days = (int) $request->input('days', 30);
        $days = max(7, min($days, 365));
        $startDate = Carbon::now()->subDays($days);
        $minUnits = (float) $request->input('min_units', 1);

        // Get all currently stocked barcodes from POS
        $stockedBarcodes = DB::connection('pos')
            ->table('stocking')
            ->pluck('Barcode')
            ->toArray();

        // Find destocked products with sales in the period
        $suggestions = SalesDailySummary::select(
                'product_code',
                'product_name',
                DB::raw('SUM(total_units) as total_units_sold'),
                DB::raw('SUM(total_revenue) as total_revenue'),
                DB::raw('COUNT(DISTINCT sale_date) as days_with_sales'),
                DB::raw('MIN(sale_date) as first_sale'),
                DB::raw('MAX(sale_date) as last_sale')
            )
            ->where('sale_date', '>=', $startDate->format('Y-m-d'))
            ->whereNotIn('product_code', $stockedBarcodes)
            ->groupBy('product_code', 'product_name')
            ->having('total_units_sold', '>=', $minUnits)
            ->orderByDesc('total_units_sold')
            ->paginate(30);

        return view('destock-review.suggestions', compact('suggestions', 'days', 'minUnits'));
    }
}
