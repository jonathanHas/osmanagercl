<?php

namespace App\Http\Controllers\Management;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class FinancialDashboardController extends Controller
{
    public function index(Request $request)
    {
        $date = $request->get('date', Carbon::today()->format('Y-m-d'));
        $carbonDate = Carbon::parse($date);
        
        // Get daily metrics
        $todayMetrics = $this->getDailyMetrics($carbonDate);
        $yesterdayMetrics = $this->getDailyMetrics($carbonDate->copy()->subDay());
        $weekMetrics = $this->getWeekMetrics($carbonDate);
        $monthMetrics = $this->getMonthMetrics($carbonDate);
        
        // Get cash position
        $cashPosition = $this->getCashPosition($carbonDate);
        
        // Get outstanding items
        $outstandingInvoices = $this->getOutstandingInvoices();
        $pendingReconciliations = $this->getPendingReconciliations();
        
        // Get trends
        $salesTrend = $this->getSalesTrend();
        $cashFlowTrend = $this->getCashFlowTrend();
        
        // Get alerts
        $alerts = $this->getFinancialAlerts($carbonDate);
        
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
            'alerts'
        ));
    }
    
    private function getDailyMetrics($date)
    {
        // Get POS sales for the day by joining RECEIPTS with PAYMENTS
        $sales = DB::connection('pos')
            ->table('RECEIPTS as r')
            ->join('PAYMENTS as p', 'r.ID', '=', 'p.RECEIPT')
            ->whereDate('r.DATENEW', $date)
            ->selectRaw('
                COUNT(DISTINCT r.ID) as transaction_count,
                SUM(CASE WHEN p.TOTAL >= 0 THEN p.TOTAL ELSE 0 END) as total_sales,
                SUM(CASE WHEN p.TOTAL < 0 THEN ABS(p.TOTAL) ELSE 0 END) as total_refunds,
                SUM(CASE WHEN p.PAYMENT = "cash" THEN p.TOTAL ELSE 0 END) as cash_sales,
                SUM(CASE WHEN p.PAYMENT = "cashrefund" THEN p.TOTAL ELSE 0 END) as cash_refunds,
                SUM(CASE WHEN p.PAYMENT = "magcard" THEN p.TOTAL ELSE 0 END) as card_sales,
                SUM(CASE WHEN p.PAYMENT = "magcardrefund" THEN p.TOTAL ELSE 0 END) as card_refunds,
                SUM(CASE WHEN p.PAYMENT = "debt" THEN p.TOTAL ELSE 0 END) as debt_sales,
                SUM(CASE WHEN p.PAYMENT = "free" THEN p.TOTAL ELSE 0 END) as free_sales,
                AVG(CASE WHEN p.TOTAL > 0 THEN p.TOTAL ELSE NULL END) as avg_transaction
            ')
            ->first();
        
        // Get cash reconciliation data if exists
        $reconciliation = DB::table('cash_reconciliations')
            ->where('date', $date->format('Y-m-d'))
            ->first();
        
        // Get supplier payments for the day
        $supplierPayments = DB::table('cash_reconciliation_payments')
            ->whereDate('created_at', $date)
            ->sum('amount');
        
        // Calculate net amounts (sales minus refunds for each type)
        $netCashSales = ($sales->cash_sales ?? 0) - abs($sales->cash_refunds ?? 0);
        $netCardSales = ($sales->card_sales ?? 0) - abs($sales->card_refunds ?? 0);
        
        // Calculate key metrics
        $netCash = $netCashSales - $supplierPayments;
        $variance = 0;
        if ($reconciliation) {
            $variance = $reconciliation->variance ?? 0;
        }
        
        return [
            'sales' => $sales->total_sales ?? 0,
            'refunds' => $sales->total_refunds ?? 0,
            'net_sales' => ($sales->total_sales ?? 0) - ($sales->total_refunds ?? 0),
            'transactions' => $sales->transaction_count ?? 0,
            'avg_transaction' => $sales->avg_transaction ?? 0,
            'cash_sales' => $netCashSales,
            'card_sales' => $netCardSales,
            'debt_sales' => $sales->debt_sales ?? 0,
            'free_sales' => $sales->free_sales ?? 0,
            'supplier_payments' => $supplierPayments,
            'net_cash' => $netCash,
            'variance' => $variance,
            'reconciled' => !is_null($reconciliation),
        ];
    }
    
    private function getWeekMetrics($date)
    {
        $startOfWeek = $date->copy()->startOfWeek();
        $endOfWeek = $date->copy()->endOfWeek();
        
        $sales = DB::connection('pos')
            ->table('RECEIPTS as r')
            ->join('PAYMENTS as p', 'r.ID', '=', 'p.RECEIPT')
            ->whereBetween('r.DATENEW', [$startOfWeek, $endOfWeek])
            ->selectRaw('
                SUM(CASE WHEN p.TOTAL >= 0 THEN p.TOTAL ELSE 0 END) - 
                SUM(CASE WHEN p.TOTAL < 0 THEN ABS(p.TOTAL) ELSE 0 END) as net_sales,
                COUNT(DISTINCT DATE(r.DATENEW)) as days_traded
            ')
            ->first();
        
        $supplierPayments = DB::table('cash_reconciliation_payments')
            ->whereBetween('created_at', [$startOfWeek, $endOfWeek])
            ->sum('amount');
        
        return [
            'net_sales' => $sales->net_sales ?? 0,
            'days_traded' => $sales->days_traded ?? 0,
            'daily_average' => $sales->days_traded > 0 ? ($sales->net_sales / $sales->days_traded) : 0,
            'supplier_payments' => $supplierPayments,
        ];
    }
    
    private function getMonthMetrics($date)
    {
        $startOfMonth = $date->copy()->startOfMonth();
        $endOfMonth = $date->copy()->endOfMonth();
        
        $sales = DB::connection('pos')
            ->table('RECEIPTS as r')
            ->join('PAYMENTS as p', 'r.ID', '=', 'p.RECEIPT')
            ->whereBetween('r.DATENEW', [$startOfMonth, $endOfMonth])
            ->selectRaw('
                SUM(CASE WHEN p.TOTAL >= 0 THEN p.TOTAL ELSE 0 END) - 
                SUM(CASE WHEN p.TOTAL < 0 THEN ABS(p.TOTAL) ELSE 0 END) as net_sales
            ')
            ->first();
        
        // Get last month for comparison
        $lastMonthSales = DB::connection('pos')
            ->table('RECEIPTS as r')
            ->join('PAYMENTS as p', 'r.ID', '=', 'p.RECEIPT')
            ->whereBetween('r.DATENEW', [
                $startOfMonth->copy()->subMonth(),
                $endOfMonth->copy()->subMonth()
            ])
            ->selectRaw('
                SUM(CASE WHEN p.TOTAL >= 0 THEN p.TOTAL ELSE 0 END) - 
                SUM(CASE WHEN p.TOTAL < 0 THEN ABS(p.TOTAL) ELSE 0 END) as net_sales
            ')
            ->first();
        
        $growth = 0;
        if ($lastMonthSales->net_sales > 0) {
            $growth = (($sales->net_sales - $lastMonthSales->net_sales) / $lastMonthSales->net_sales) * 100;
        }
        
        return [
            'net_sales' => $sales->net_sales ?? 0,
            'last_month_sales' => $lastMonthSales->net_sales ?? 0,
            'growth' => $growth,
        ];
    }
    
    private function getCashPosition($date)
    {
        // Get latest reconciliation
        $latest = DB::table('cash_reconciliations')
            ->where('date', '<=', $date->format('Y-m-d'))
            ->orderBy('date', 'desc')
            ->first();
        
        if (!$latest) {
            return [
                'current_float' => 0,
                'last_counted' => null,
                'expected_today' => 0,
                'days_since_count' => 0,
            ];
        }
        
        // Calculate total float from the last reconciliation
        $lastFloat = ($latest->note_float ?? 0) + ($latest->coin_float ?? 0);
        
        // Calculate expected cash for today
        $salesSinceCount = DB::connection('pos')
            ->table('RECEIPTS as r')
            ->join('PAYMENTS as p', 'r.ID', '=', 'p.RECEIPT')
            ->where('r.DATENEW', '>', $latest->date)
            ->where('r.DATENEW', '<=', $date)
            ->whereIn('p.PAYMENT', ['cash', 'cashrefund'])
            ->sum('p.TOTAL');
        
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
        // This would come from invoices table when fully implemented
        // For now, return mock data
        return [
            'count' => 0,
            'total_amount' => 0,
            'oldest_days' => 0,
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
            if (!in_array($checkDate, $reconciled)) {
                $missing[] = $checkDate;
            }
        }
        
        return count($missing);
    }
    
    private function getSalesTrend()
    {
        // Get last 7 days of sales
        $trend = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = Carbon::now()->subDays($i);
            $sales = DB::connection('pos')
                ->table('RECEIPTS as r')
                ->join('PAYMENTS as p', 'r.ID', '=', 'p.RECEIPT')
                ->whereDate('r.DATENEW', $date)
                ->selectRaw('
                    SUM(CASE WHEN p.TOTAL >= 0 THEN p.TOTAL ELSE 0 END) - 
                    SUM(CASE WHEN p.TOTAL < 0 THEN ABS(p.TOTAL) ELSE 0 END) as net_sales
                ')
                ->first();
            
            $trend[] = [
                'date' => $date->format('M j'),
                'sales' => $sales->net_sales ?? 0,
            ];
        }
        
        return $trend;
    }
    
    private function getCashFlowTrend()
    {
        // Get last 7 days of cash flow
        $trend = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = Carbon::now()->subDays($i);
            
            $cashIn = DB::connection('pos')
                ->table('RECEIPTS as r')
                ->join('PAYMENTS as p', 'r.ID', '=', 'p.RECEIPT')
                ->whereDate('r.DATENEW', $date)
                ->whereIn('p.PAYMENT', ['cash', 'cashrefund'])
                ->sum('p.TOTAL');
            
            $cashOut = DB::table('cash_reconciliation_payments')
                ->whereDate('created_at', $date)
                ->sum('amount');
            
            $trend[] = [
                'date' => $date->format('M j'),
                'in' => $cashIn ?? 0,
                'out' => $cashOut ?? 0,
                'net' => ($cashIn ?? 0) - ($cashOut ?? 0),
            ];
        }
        
        return $trend;
    }
    
    private function getFinancialAlerts($date)
    {
        $alerts = [];
        
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
                    'message' => 'Large cash variance detected: €' . number_format(abs($lastReconciliation->variance), 2),
                    'action' => route('cash-reconciliation.index'),
                ];
            }
        }
        
        // Check for low sales
        $todayMetrics = $this->getDailyMetrics($date);
        if ($todayMetrics['net_sales'] < 500 && $date->isWeekday()) {
            $alerts[] = [
                'type' => 'info',
                'message' => 'Sales below €500 threshold',
                'action' => route('till-review.index') . '?date=' . $date->format('Y-m-d'),
            ];
        }
        
        return $alerts;
    }
}