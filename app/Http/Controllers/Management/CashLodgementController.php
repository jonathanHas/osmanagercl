<?php

namespace App\Http\Controllers\Management;

use App\Http\Controllers\Controller;
use App\Models\CashBagVerification;
use App\Models\CashLodgement;
use App\Models\CashReconciliation;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CashLodgementController extends Controller
{
    /**
     * Display the cash lodgements management page
     */
    public function index(Request $request)
    {
        // Default to last 30 days
        $startDate = $request->has('start_date')
            ? Carbon::parse($request->start_date)
            : Carbon::now()->subDays(30);

        $endDate = $request->has('end_date')
            ? Carbon::parse($request->end_date)
            : Carbon::now();

        // Get cash lodgements with pagination
        $query = CashLodgement::whereBetween('lodgement_date', [$startDate, $endDate])
            ->with(['matches.cashReconciliation']);

        // Filter by till if specified
        if ($request->filled('till')) {
            $query->where('till_name', $request->till);
        }

        // Filter by match status
        if ($request->filled('status')) {
            if ($request->status === 'matched') {
                $query->where('is_matched', true);
            } elseif ($request->status === 'unmatched') {
                $query->where('is_matched', false);
            }
        }

        // Filter by type
        if ($request->filled('type')) {
            $query->where('lodgement_type', $request->type);
        }

        $lodgements = $query->orderBy('lodgement_date', 'desc')->paginate(50);

        // Get summary statistics
        $totalLodgements = CashLodgement::whereBetween('lodgement_date', [$startDate, $endDate])->count();
        $totalAmount = CashLodgement::whereBetween('lodgement_date', [$startDate, $endDate])->sum('total_amount');
        $totalCash = CashLodgement::whereBetween('lodgement_date', [$startDate, $endDate])->sum('cash_amount');
        $totalCheque = CashLodgement::whereBetween('lodgement_date', [$startDate, $endDate])->sum('cheque_amount');

        $matchedCount = CashLodgement::whereBetween('lodgement_date', [$startDate, $endDate])
            ->where('is_matched', true)->count();
        $unmatchedCount = $totalLodgements - $matchedCount;
        $matchRate = $totalLodgements > 0 ? round(($matchedCount / $totalLodgements) * 100, 1) : 0;

        // Get unique tills for filter dropdown
        $tills = CashLodgement::whereBetween('lodgement_date', [$startDate, $endDate])
            ->distinct()
            ->pluck('till_name')
            ->filter()
            ->sort()
            ->values();

        // Get lodgements by till for chart data
        $lodgementsByTill = CashLodgement::whereBetween('lodgement_date', [$startDate, $endDate])
            ->selectRaw('till_name, COUNT(*) as count, SUM(total_amount) as total')
            ->groupBy('till_name')
            ->orderBy('total', 'desc')
            ->get();

        // Pending bags: reconciliations with cash available but no bag verification
        $pendingBags = CashReconciliation::whereDoesntHave('bagVerification')
            ->with(['payments'])
            ->orderBy('date', 'desc')
            ->take(30)
            ->get()
            ->filter(fn ($r) => $r->calculateAvailableToLodge() > 0)
            ->values();

        // Verified bags: bag verifications not yet included in a lodgement
        $verifiedBags = CashBagVerification::whereNull('cash_lodgement_id')
            ->with(['reconciliation', 'verifier'])
            ->orderBy('verified_at', 'desc')
            ->get();

        return view('management.cash-lodgements.index', compact(
            'lodgements',
            'startDate',
            'endDate',
            'totalLodgements',
            'totalAmount',
            'totalCash',
            'totalCheque',
            'matchedCount',
            'unmatchedCount',
            'matchRate',
            'tills',
            'lodgementsByTill',
            'pendingBags',
            'verifiedBags'
        ));
    }

    /**
     * Show details for a specific lodgement
     */
    public function show(CashLodgement $lodgement)
    {
        $lodgement->load([
            'matches.cashReconciliation.payments',
            'bankTransaction',
            'creator',
            'updater',
        ]);

        // Get related reconciliations for context
        $relatedReconciliations = CashReconciliation::where('closed_cash_id', $lodgement->money_id)
            ->with(['payments', 'notes'])
            ->get();

        // Get potential matches if unmatched
        $potentialMatches = [];
        if (! $lodgement->is_matched) {
            // Look for reconciliations within reasonable date range
            $searchStart = $lodgement->lodgement_date->copy()->subDays(7);
            $searchEnd = $lodgement->lodgement_date->copy()->addDays(3);

            $potentialMatches = CashReconciliation::whereBetween('date', [$searchStart, $searchEnd])
                ->with(['legacyCashLodgement'])
                ->get()
                ->filter(function ($reconciliation) use ($lodgement) {
                    $availableToLodge = $reconciliation->calculateAvailableToLodge();
                    $variance = abs($availableToLodge - $lodgement->cash_amount);

                    return $variance <= ($lodgement->cash_amount * 0.2); // Within 20%
                })
                ->map(function ($reconciliation) use ($lodgement) {
                    $availableToLodge = $reconciliation->calculateAvailableToLodge();
                    $variance = abs($availableToLodge - $lodgement->cash_amount);
                    $variancePercent = $availableToLodge > 0 ? ($variance / $availableToLodge) * 100 : 100;

                    return [
                        'reconciliation' => $reconciliation,
                        'variance' => $variance,
                        'variance_percent' => round($variancePercent, 1),
                        'confidence' => max(50, 100 - $variancePercent),
                        'available_to_lodge' => $availableToLodge,
                    ];
                })
                ->sortBy('variance')
                ->take(5);
        }

        return view('management.cash-lodgements.show', compact(
            'lodgement',
            'relatedReconciliations',
            'potentialMatches'
        ));
    }

    /**
     * Export lodgements to CSV
     */
    public function export(Request $request)
    {
        $startDate = $request->has('start_date')
            ? Carbon::parse($request->start_date)
            : Carbon::now()->subDays(30);

        $endDate = $request->has('end_date')
            ? Carbon::parse($request->end_date)
            : Carbon::now();

        $query = CashLodgement::whereBetween('lodgement_date', [$startDate, $endDate])
            ->with(['matches.cashReconciliation']);

        // Apply same filters as index
        if ($request->filled('till')) {
            $query->where('till_name', $request->till);
        }

        if ($request->filled('status')) {
            if ($request->status === 'matched') {
                $query->where('is_matched', true);
            } elseif ($request->status === 'unmatched') {
                $query->where('is_matched', false);
            }
        }

        if ($request->filled('type')) {
            $query->where('lodgement_type', $request->type);
        }

        $lodgements = $query->orderBy('lodgement_date', 'desc')->get();

        $filename = 'cash_lodgements_'.$startDate->format('Y-m-d').'_to_'.$endDate->format('Y-m-d').'.csv';

        return response()->streamDownload(function () use ($lodgements, $startDate, $endDate) {
            $handle = fopen('php://output', 'w');

            // Header
            fputcsv($handle, ['Cash Lodgements Export']);
            fputcsv($handle, ['Period:', $startDate->format('Y-m-d'), 'to', $endDate->format('Y-m-d')]);
            fputcsv($handle, ['Generated:', now()->format('Y-m-d H:i:s')]);
            fputcsv($handle, []); // Empty row

            // Column headers
            fputcsv($handle, [
                'Lodgement Date',
                'Till Name',
                'Cash Amount',
                'Cheque Amount',
                'Total Amount',
                'Type',
                'Match Status',
                'Matched Amount',
                'Remaining Amount',
                'Legacy Import',
                'Original Date',
                'Money ID',
                'Created At',
            ]);

            // Data rows
            foreach ($lodgements as $lodgement) {
                fputcsv($handle, [
                    $lodgement->lodgement_date->format('Y-m-d'),
                    $lodgement->till_name,
                    number_format($lodgement->cash_amount, 2),
                    number_format($lodgement->cheque_amount, 2),
                    number_format($lodgement->total_amount, 2),
                    ucfirst(str_replace('_', ' ', $lodgement->lodgement_type)),
                    $lodgement->is_matched ? 'Matched' : 'Unmatched',
                    number_format($lodgement->total_matched_amount, 2),
                    number_format($lodgement->remaining_amount, 2),
                    $lodgement->imported_from_legacy ? 'Yes' : 'No',
                    $lodgement->original_lodge_date?->format('Y-m-d H:i:s') ?? '',
                    $lodgement->money_id,
                    $lodgement->created_at->format('Y-m-d H:i:s'),
                ]);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Content-Disposition' => 'attachment; filename='.$filename,
            'Expires' => '0',
            'Pragma' => 'public',
        ]);
    }

    /**
     * Verify a cash bag (denomination count against expected)
     */
    public function verifyBag(Request $request)
    {
        $validated = $request->validate([
            'cash_reconciliation_id' => 'required|uuid|exists:cash_reconciliations,id',
            'cash_50' => 'nullable|integer|min:0',
            'cash_20' => 'nullable|integer|min:0',
            'cash_10' => 'nullable|integer|min:0',
            'cash_5' => 'nullable|integer|min:0',
            'cash_2' => 'nullable|integer|min:0',
            'cash_1' => 'nullable|integer|min:0',
            'cash_50c' => 'nullable|integer|min:0',
            'cash_20c' => 'nullable|integer|min:0',
            'cash_10c' => 'nullable|integer|min:0',
        ]);

        $reconciliation = CashReconciliation::with('payments')->findOrFail($validated['cash_reconciliation_id']);

        // Check no existing verification
        if ($reconciliation->bagVerification) {
            return back()->with('error', 'This bag has already been verified.');
        }

        $verification = new CashBagVerification($validated);
        $countedTotal = $verification->calculateTotal();
        $expectedTotal = $reconciliation->calculateAvailableToLodge();

        $verification->fill([
            'counted_total' => $countedTotal,
            'expected_total' => $expectedTotal,
            'variance' => $countedTotal - $expectedTotal,
            'verified_by' => auth()->id(),
            'verified_at' => now(),
        ]);

        $verification->save();

        return back()->with('success', sprintf(
            'Bag verified: €%.2f counted (expected €%.2f, variance €%.2f)',
            $countedTotal,
            $expectedTotal,
            $countedTotal - $expectedTotal
        ));
    }

    /**
     * Create a lodgement from verified bags
     */
    public function createLodgement(Request $request)
    {
        $validated = $request->validate([
            'verification_ids' => 'required|array|min:1',
            'verification_ids.*' => 'uuid|exists:cash_bag_verifications,id',
        ]);

        $verifications = CashBagVerification::with('reconciliation')
            ->whereIn('id', $validated['verification_ids'])
            ->whereNull('cash_lodgement_id')
            ->get();

        if ($verifications->isEmpty()) {
            return back()->with('error', 'No valid unlinked verifications selected.');
        }

        DB::transaction(function () use ($verifications) {
            $totalCash = $verifications->sum('counted_total');

            $lodgement = CashLodgement::create([
                'money_id' => $verifications->first()->reconciliation->closed_cash_id,
                'lodgement_date' => now()->toDateString(),
                'cash_amount' => $totalCash,
                'cheque_amount' => 0,
                'till_name' => $verifications->first()->reconciliation->till_name,
                'till_id' => $verifications->first()->reconciliation->till_id,
                'imported_from_legacy' => false,
                'created_by' => auth()->id(),
            ]);

            // Link verifications to lodgement
            CashBagVerification::whereIn('id', $verifications->pluck('id'))
                ->update(['cash_lodgement_id' => $lodgement->id]);
        });

        return back()->with('success', sprintf(
            'Lodgement created: €%.2f from %d bag(s)',
            $verifications->sum('counted_total'),
            $verifications->count()
        ));
    }
}
