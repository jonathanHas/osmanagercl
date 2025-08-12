<?php

namespace App\Http\Controllers\Management;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\VatReturn;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class VatDashboardController extends Controller
{
    /**
     * Define the standard VAT periods (bi-monthly)
     */
    private const VAT_PERIODS = [
        ['start_month' => 1, 'end_month' => 2, 'label' => 'Jan-Feb'],
        ['start_month' => 3, 'end_month' => 4, 'label' => 'Mar-Apr'],
        ['start_month' => 5, 'end_month' => 6, 'label' => 'May-Jun'],
        ['start_month' => 7, 'end_month' => 8, 'label' => 'Jul-Aug'],
        ['start_month' => 9, 'end_month' => 10, 'label' => 'Sep-Oct'],
        ['start_month' => 11, 'end_month' => 12, 'label' => 'Nov-Dec'],
    ];

    /**
     * Display the VAT dashboard
     */
    public function index()
    {
        // Get outstanding periods
        $outstandingPeriods = $this->getOutstandingPeriods();
        
        // Get recent submissions (last 6)
        $recentSubmissions = VatReturn::with('creator')
            ->orderBy('period_end', 'desc')
            ->orderBy('created_at', 'desc')
            ->limit(6)
            ->get();
        
        // Get unsubmitted invoices summary
        $unsubmittedSummary = $this->getUnsubmittedInvoicesSummary();
        
        // Get current period info
        $currentPeriodInfo = $this->getCurrentPeriodInfo();
        
        // Calculate next deadline
        $nextDeadline = $this->getNextDeadline();
        
        // Get yearly statistics
        $yearlyStats = $this->getYearlyStatistics();
        
        return view('management.vat-dashboard.index', compact(
            'outstandingPeriods',
            'recentSubmissions',
            'unsubmittedSummary',
            'currentPeriodInfo',
            'nextDeadline',
            'yearlyStats'
        ));
    }
    
    /**
     * Get list of outstanding VAT periods that need submission
     */
    private function getOutstandingPeriods(): array
    {
        $outstanding = [];
        $today = Carbon::now();
        
        // Start checking from 2024 (or whenever your VAT returns began)
        $startYear = 2024;
        $currentYear = $today->year;
        
        for ($year = $startYear; $year <= $currentYear; $year++) {
            foreach (self::VAT_PERIODS as $period) {
                // Create the period end date
                $periodEnd = Carbon::create($year, $period['end_month'])->endOfMonth();
                
                // Skip future periods
                if ($periodEnd->isFuture()) {
                    continue;
                }
                
                // Skip if we're still in the grace period (e.g., 15 days after period end)
                $graceEnd = $periodEnd->copy()->addDays(15);
                if ($today->lessThan($graceEnd)) {
                    continue;
                }
                
                // Check if a VAT return exists for this period
                $periodStart = Carbon::create($year, $period['start_month'])->startOfMonth();
                
                $exists = VatReturn::where('period_start', '>=', $periodStart)
                    ->where('period_end', '<=', $periodEnd)
                    ->exists();
                
                if (!$exists) {
                    // Check if there are unassigned invoices for this period
                    $unassignedCount = Invoice::whereNull('vat_return_id')
                        ->whereBetween('invoice_date', [$periodStart, $periodEnd])
                        ->count();
                    
                    if ($unassignedCount > 0) {
                        $outstanding[] = [
                            'year' => $year,
                            'period' => $period,
                            'label' => $period['label'] . ' ' . $year,
                            'start_date' => $periodStart,
                            'end_date' => $periodEnd,
                            'invoice_count' => $unassignedCount,
                            'days_overdue' => $graceEnd->diffInDays($today),
                        ];
                    }
                }
            }
        }
        
        return $outstanding;
    }
    
    /**
     * Get summary of unsubmitted invoices
     */
    private function getUnsubmittedInvoicesSummary(): array
    {
        $unassignedInvoices = Invoice::whereNull('vat_return_id');
        
        // Calculate totals
        $totals = $unassignedInvoices->select(
            DB::raw('COUNT(*) as count'),
            DB::raw('SUM(total_amount) as total_amount'),
            DB::raw('SUM(vat_amount) as total_vat'),
            DB::raw('MIN(invoice_date) as earliest_date'),
            DB::raw('MAX(invoice_date) as latest_date')
        )->first();
        
        // Group by month for breakdown
        $monthlyBreakdown = Invoice::whereNull('vat_return_id')
            ->select(
                DB::raw('YEAR(invoice_date) as year'),
                DB::raw('MONTH(invoice_date) as month'),
                DB::raw('COUNT(*) as count'),
                DB::raw('SUM(vat_amount) as vat_total')
            )
            ->groupBy('year', 'month')
            ->orderBy('year', 'desc')
            ->orderBy('month', 'desc')
            ->limit(12)
            ->get();
        
        return [
            'total_count' => $totals->count ?? 0,
            'total_amount' => $totals->total_amount ?? 0,
            'total_vat' => $totals->total_vat ?? 0,
            'earliest_date' => $totals->earliest_date ? Carbon::parse($totals->earliest_date) : null,
            'latest_date' => $totals->latest_date ? Carbon::parse($totals->latest_date) : null,
            'monthly_breakdown' => $monthlyBreakdown,
        ];
    }
    
    /**
     * Get information about the current VAT period
     */
    private function getCurrentPeriodInfo(): array
    {
        $today = Carbon::now();
        
        // Find which period we're currently in
        foreach (self::VAT_PERIODS as $period) {
            $periodStart = Carbon::create($today->year, $period['start_month'])->startOfMonth();
            $periodEnd = Carbon::create($today->year, $period['end_month'])->endOfMonth();
            
            if ($today->between($periodStart, $periodEnd)) {
                // Check if return exists for current period
                $returnExists = VatReturn::where('period_start', '>=', $periodStart)
                    ->where('period_end', '<=', $periodEnd)
                    ->exists();
                
                // Count invoices in current period
                $invoiceCount = Invoice::whereNull('vat_return_id')
                    ->whereBetween('invoice_date', [$periodStart, $today])
                    ->count();
                
                return [
                    'label' => $period['label'] . ' ' . $today->year,
                    'start_date' => $periodStart,
                    'end_date' => $periodEnd,
                    'days_remaining' => $today->diffInDays($periodEnd),
                    'return_exists' => $returnExists,
                    'invoice_count' => $invoiceCount,
                ];
            }
        }
        
        return [];
    }
    
    /**
     * Calculate the next VAT deadline
     */
    private function getNextDeadline(): ?Carbon
    {
        $today = Carbon::now();
        
        // Find the current or next period end
        foreach (self::VAT_PERIODS as $period) {
            $periodEnd = Carbon::create($today->year, $period['end_month'])->endOfMonth();
            
            // Add grace period (e.g., 15 days for submission)
            $deadline = $periodEnd->copy()->addDays(15);
            
            if ($deadline->isFuture()) {
                return $deadline;
            }
        }
        
        // If we've passed all deadlines this year, return first deadline of next year
        $nextYear = $today->year + 1;
        $firstPeriod = self::VAT_PERIODS[0];
        return Carbon::create($nextYear, $firstPeriod['end_month'])->endOfMonth()->addDays(15);
    }
    
    /**
     * Get yearly VAT statistics
     */
    private function getYearlyStatistics(): array
    {
        $currentYear = Carbon::now()->year;
        $lastYear = $currentYear - 1;
        
        $stats = [];
        
        foreach ([$lastYear, $currentYear] as $year) {
            $yearStats = VatReturn::whereYear('period_end', $year)
                ->select(
                    DB::raw('COUNT(*) as return_count'),
                    DB::raw('SUM(total_net) as total_net'),
                    DB::raw('SUM(total_vat) as total_vat'),
                    DB::raw('SUM(total_gross) as total_gross')
                )
                ->first();
            
            $stats[$year] = [
                'return_count' => $yearStats->return_count ?? 0,
                'total_net' => $yearStats->total_net ?? 0,
                'total_vat' => $yearStats->total_vat ?? 0,
                'total_gross' => $yearStats->total_gross ?? 0,
            ];
        }
        
        return $stats;
    }
    
    /**
     * Show all VAT returns history
     */
    public function history(Request $request)
    {
        $query = VatReturn::with(['creator', 'finalizer']);
        
        // Apply year filter if provided
        if ($request->filled('year')) {
            $query->whereYear('period_end', $request->year);
        }
        
        // Apply status filter if provided
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        
        $vatReturns = $query->orderBy('period_end', 'desc')
            ->orderBy('created_at', 'desc')
            ->paginate(20);
        
        // Get available years for filter
        $availableYears = VatReturn::selectRaw('YEAR(period_end) as year')
            ->distinct()
            ->orderBy('year', 'desc')
            ->pluck('year');
        
        return view('management.vat-dashboard.history', compact('vatReturns', 'availableYears'));
    }
}