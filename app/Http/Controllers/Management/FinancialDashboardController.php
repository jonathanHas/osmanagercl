<?php

namespace App\Http\Controllers\Management;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Repositories\OptimizedSalesRepository;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FinancialDashboardController extends Controller
{
    public function __construct(
        private OptimizedSalesRepository $optimizedSalesRepository
    ) {}

    public function index(Request $request)
    {
        $date = $request->get('date', Carbon::today()->format('Y-m-d'));
        $carbonDate = Carbon::parse($date);

        // Get daily metrics - using pre-aggregated data (2 queries vs 2 cross-DB queries)
        $todayMetrics = $this->getDailyMetrics($carbonDate);
        $yesterdayMetrics = $this->getDailyMetrics($carbonDate->copy()->subDay());

        // Get week and month metrics - using pre-aggregated data (2 queries vs 4 cross-DB queries)
        $weekMetrics = $this->optimizedSalesRepository->getWeekFinancialMetrics($carbonDate);
        $monthMetrics = $this->optimizedSalesRepository->getMonthFinancialMetrics($carbonDate);

        // Get cash position
        $cashPosition = $this->getCashPosition($carbonDate);

        // Get outstanding items
        $outstandingInvoices = $this->getOutstandingInvoices();
        $pendingReconciliations = $this->getPendingReconciliations();

        // Get trends - using pre-aggregated data (2 queries vs 14 cross-DB queries!)
        // Trend is relative to the selected date, not today
        $salesTrend = $this->optimizedSalesRepository->getSalesTrendOptimized(7, $carbonDate);
        $cashFlowTrend = $this->optimizedSalesRepository->getCashFlowTrendOptimized(7, $carbonDate);

        // Get alerts (including overdue invoices)
        $alerts = $this->getFinancialAlerts($carbonDate, $todayMetrics, $outstandingInvoices);

        // Last updated timestamp
        $lastUpdated = now();

        return view('management.financial.dashboard', compact(
            'date',
            'todayMetrics',
            'yesterdayMetrics',
            'weekMetrics',
            'monthMetrics',
            'cashPosition',
            'outstandingInvoices',
            'pendingReconciliations',
            'salesTrend',
            'cashFlowTrend',
            'alerts',
            'lastUpdated'
        ));
    }

    private function getDailyMetrics($date)
    {
        // Use pre-aggregated data from pos_daily_summaries (local SQLite - instant!)
        $metrics = $this->optimizedSalesRepository->getDailyFinancialMetrics($date);

        // Get cash reconciliation data if exists
        $reconciliation = DB::table('cash_reconciliations')
            ->where('date', $date->format('Y-m-d'))
            ->first();

        // Get supplier payments for the day
        $supplierPayments = DB::table('cash_reconciliation_payments')
            ->whereDate('created_at', $date)
            ->sum('amount');

        // Calculate key metrics
        $netCash = $metrics['cash_sales'] - $supplierPayments;
        $variance = 0;
        if ($reconciliation) {
            $variance = $reconciliation->variance ?? 0;
        }

        return [
            'sales' => $metrics['sales'],
            'refunds' => $metrics['refunds'],
            'net_sales' => $metrics['net_sales'],
            'transactions' => $metrics['transactions'],
            'avg_transaction' => $metrics['avg_transaction'],
            'cash_sales' => $metrics['cash_sales'],
            'card_sales' => $metrics['card_sales'],
            'debt_sales' => $metrics['debt_sales'],
            'free_sales' => $metrics['free_sales'],
            'supplier_payments' => $supplierPayments,
            'net_cash' => $netCash,
            'variance' => $variance,
            'reconciled' => ! is_null($reconciliation),
        ];
    }

    private function getCashPosition($date)
    {
        // Get latest reconciliation
        $latest = DB::table('cash_reconciliations')
            ->where('date', '<=', $date->format('Y-m-d'))
            ->orderBy('date', 'desc')
            ->first();

        if (! $latest) {
            return [
                'current_float' => 0,
                'last_counted' => null,
                'expected_today' => 0,
                'days_since_count' => 0,
            ];
        }

        // Calculate total float from the last reconciliation
        $lastFloat = ($latest->note_float ?? 0) + ($latest->coin_float ?? 0);

        // Calculate expected cash using pre-aggregated data (local SQLite - instant!)
        $salesSinceCount = DB::table('pos_daily_summaries')
            ->where('sale_date', '>', $latest->date)
            ->where('sale_date', '<=', $date->format('Y-m-d'))
            ->selectRaw('SUM(cash_sales - cash_refunds) as net_cash')
            ->value('net_cash') ?? 0;

        $paymentsSinceCount = DB::table('cash_reconciliation_payments')
            ->where('created_at', '>', $latest->date)
            ->where('created_at', '<=', $date)
            ->sum('amount');

        $expectedToday = $lastFloat + $salesSinceCount - $paymentsSinceCount;

        return [
            'current_float' => $lastFloat,
            'last_counted' => $latest->date,
            'expected_today' => $expectedToday,
            'days_since_count' => Carbon::parse($latest->date)->diffInDays($date),
        ];
    }

    private function getOutstandingInvoices()
    {
        // Get unpaid invoices (pending, overdue, or partial payment)
        $unpaidStatuses = ['pending', 'overdue', 'partial'];

        $count = Invoice::whereIn('payment_status', $unpaidStatuses)->count();
        $totalAmount = Invoice::whereIn('payment_status', $unpaidStatuses)->sum('total_amount');
        $overdueCount = Invoice::where('payment_status', 'overdue')->count();

        // Get the oldest unpaid invoice to calculate days outstanding
        $oldestUnpaid = Invoice::whereIn('payment_status', $unpaidStatuses)
            ->whereNotNull('due_date')
            ->orderBy('due_date', 'asc')
            ->first();

        $oldestDays = $oldestUnpaid
            ? Carbon::parse($oldestUnpaid->due_date)->diffInDays(now(), false)
            : 0;

        return [
            'count' => $count,
            'total_amount' => $totalAmount,
            'overdue_count' => $overdueCount,
            'oldest_days' => max(0, $oldestDays), // Only show positive days overdue
        ];
    }

    private function getPendingReconciliations()
    {
        // Get days without reconciliation in last 7 days
        $lastWeek = Carbon::now()->subDays(7);

        $reconciled = DB::table('cash_reconciliations')
            ->where('date', '>=', $lastWeek->format('Y-m-d'))
            ->pluck('date')
            ->toArray();

        $missing = [];
        for ($i = 0; $i < 7; $i++) {
            $checkDate = Carbon::now()->subDays($i)->format('Y-m-d');
            if (! in_array($checkDate, $reconciled)) {
                $missing[] = $checkDate;
            }
        }

        return count($missing);
    }

    private function getFinancialAlerts($date, array $todayMetrics, array $outstandingInvoices)
    {
        $alerts = [];

        // Check for overdue invoices
        if ($outstandingInvoices['overdue_count'] > 0) {
            $alerts[] = [
                'type' => 'danger',
                'message' => $outstandingInvoices['overdue_count'].' overdue invoice(s) - €'.number_format($outstandingInvoices['total_amount'], 2).' outstanding',
                'action' => route('invoices.index', ['payment_status' => 'overdue']),
            ];
        }

        // Check for unreconciled days
        $lastReconciliation = DB::table('cash_reconciliations')
            ->orderBy('date', 'desc')
            ->first();

        if ($lastReconciliation) {
            $daysSince = Carbon::parse($lastReconciliation->date)->diffInDays($date);
            if ($daysSince > 1) {
                $alerts[] = [
                    'type' => 'warning',
                    'message' => "Cash not reconciled for {$daysSince} days",
                    'action' => route('cash-reconciliation.index'),
                ];
            }

            // Check for large variance
            if (abs($lastReconciliation->variance) > 50) {
                $alerts[] = [
                    'type' => 'danger',
                    'message' => 'Large cash variance detected: €'.number_format(abs($lastReconciliation->variance), 2),
                    'action' => route('cash-reconciliation.index'),
                ];
            }
        }

        // Check for low sales - using pre-fetched metrics (no extra query!)
        if ($todayMetrics['net_sales'] < 500 && $date->isWeekday()) {
            $alerts[] = [
                'type' => 'info',
                'message' => 'Sales below €500 threshold',
                'action' => route('till-review.index').'?date='.$date->format('Y-m-d'),
            ];
        }

        return $alerts;
    }
}
