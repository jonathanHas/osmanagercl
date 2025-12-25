<?php

namespace App\Http\Controllers\Management;

use App\Http\Controllers\Controller;
use App\Repositories\OptimizedSalesRepository;
use Carbon\Carbon;
use Illuminate\Http\Request;

class SalesReviewController extends Controller
{
    public function __construct(
        private OptimizedSalesRepository $salesRepository
    ) {}

    public function index(Request $request)
    {
        // Date handling (default: last 7 days ending today)
        $endDate = Carbon::parse($request->get('end_date', now()->format('Y-m-d')));
        $startDate = Carbon::parse($request->get('start_date', $endDate->copy()->subDays(6)->format('Y-m-d')));

        // Calculate period length in days
        $periodDays = $startDate->diffInDays($endDate) + 1;

        // Current period data
        $salesTrend = $this->salesRepository->getAllDailySales($startDate, $endDate);
        $stats = $this->salesRepository->getAllSalesStats($startDate, $endDate);

        // Year-over-year comparison
        $lastYearStart = $startDate->copy()->subYear();
        $lastYearEnd = $endDate->copy()->subYear();
        $lastYearTrend = $this->salesRepository->getAllDailySales($lastYearStart, $lastYearEnd);
        $lastYearStats = $this->salesRepository->getAllSalesStats($lastYearStart, $lastYearEnd);

        // Calculate year-over-year changes
        $yoyChanges = $this->calculateYoyChanges($stats, $lastYearStats);

        // Top sellers
        $topByVolume = $this->salesRepository->getTopAllProducts($startDate, $endDate, 10)
            ->sortByDesc('total_units')
            ->values();
        $topByRevenue = $this->salesRepository->getTopAllProducts($startDate, $endDate, 10);

        // Last updated timestamp
        $lastUpdated = now();

        return view('management.sales-review.index', compact(
            'startDate',
            'endDate',
            'periodDays',
            'salesTrend',
            'stats',
            'lastYearTrend',
            'lastYearStats',
            'yoyChanges',
            'topByVolume',
            'topByRevenue',
            'lastUpdated'
        ));
    }

    private function calculateYoyChanges(array $currentStats, array $lastYearStats): array
    {
        $changes = [];

        // Revenue change
        $currentRevenue = $currentStats['total_revenue'] ?? 0;
        $lastYearRevenue = $lastYearStats['total_revenue'] ?? 0;
        $changes['revenue'] = $lastYearRevenue > 0
            ? (($currentRevenue - $lastYearRevenue) / $lastYearRevenue) * 100
            : 0;

        // Units change
        $currentUnits = $currentStats['total_units'] ?? 0;
        $lastYearUnits = $lastYearStats['total_units'] ?? 0;
        $changes['units'] = $lastYearUnits > 0
            ? (($currentUnits - $lastYearUnits) / $lastYearUnits) * 100
            : 0;

        // Transactions change
        $currentTransactions = $currentStats['total_transactions'] ?? 0;
        $lastYearTransactions = $lastYearStats['total_transactions'] ?? 0;
        $changes['transactions'] = $lastYearTransactions > 0
            ? (($currentTransactions - $lastYearTransactions) / $lastYearTransactions) * 100
            : 0;

        // Avg transaction value change
        $currentAvg = $currentTransactions > 0 ? $currentRevenue / $currentTransactions : 0;
        $lastYearAvg = $lastYearTransactions > 0 ? $lastYearRevenue / $lastYearTransactions : 0;
        $changes['avg_transaction'] = $lastYearAvg > 0
            ? (($currentAvg - $lastYearAvg) / $lastYearAvg) * 100
            : 0;

        return $changes;
    }
}
