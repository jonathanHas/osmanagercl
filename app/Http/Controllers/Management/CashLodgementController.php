<?php

namespace App\Http\Controllers\Management;

use App\Http\Controllers\Controller;
use App\Models\CashBagVerification;
use App\Models\CashLodgement;
use App\Models\CashReconciliation;
use App\Models\POS\ClosedCash;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CashLodgementController extends Controller
{
    /**
     * The denomination fields on a bag count. Shared by verifyBag() and updateBag() so the two
     * cannot drift apart — a field added to one but not the other would silently save as zero.
     */
    private const DENOMINATIONS = [
        'cash_50', 'cash_20', 'cash_10', 'cash_5', 'cash_2', 'cash_1', 'cash_50c', 'cash_20c', 'cash_10c',
    ];

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
            ->with(['matches.cashReconciliation', 'closedCash', 'bagVerifications.reconciliation']);

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

        // Re-sort current page by till closed date (cross-database, can't sort in query)
        $lodgements->setCollection(
            $lodgements->getCollection()->sortByDesc(
                fn ($l) => $l->closedCash?->DATEEND ?? $l->lodgement_date
            )->values()
        );

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

        // Build till name → till_id lookup for linking to cash-reconciliation page
        $reconciliationRepo = app(\App\Repositories\CashReconciliationRepository::class);
        $tillNameToId = $reconciliationRepo->getAvailableTills()->flip();

        // Auto-create CashReconciliation records for POS till closes that don't have one yet
        // This imports legacy denomination data so they appear in Pending Bags
        $reconciledMoneyIds = CashReconciliation::pluck('closed_cash_id')->toArray();
        $lodgedMoneyIds = CashLodgement::pluck('money_id')->toArray();
        $excludedMoneyIds = array_unique(array_merge($reconciledMoneyIds, $lodgedMoneyIds));
        $unreconciledClosedCash = ClosedCash::whereNotIn('MONEY', $excludedMoneyIds)
            ->whereDate('DATEEND', '>=', now()->subDays(60))
            ->orderBy('DATEEND', 'desc')
            ->get();

        // Create reconciliation records for unreconciled days (imports legacy money data)
        foreach ($unreconciledClosedCash as $closedCash) {
            try {
                $tillId = $tillNameToId[$closedCash->HOST] ?? 1;
                $reconciliationRepo->getOrCreateReconciliation(
                    $closedCash->DATEEND->startOfDay(),
                    $tillId,
                    $closedCash->HOST
                );
            } catch (\Exception $e) {
                // Skip if creation fails (e.g., missing POS data)
                continue;
            }
        }

        // Pending bags: reconciliations with cash available but no bag verification
        $pendingBags = CashReconciliation::whereDoesntHave('bagVerification')
            ->with(['payments'])
            ->orderBy('date', 'desc')
            ->get()
            ->filter(fn ($r) => $r->calculateAvailableToLodge() > 0)
            ->take(40)
            ->values();

        // Remaining unreconciled days: those where auto-creation didn't produce a lodgeable amount
        // (e.g., no legacy money data, denominations still zero)
        $allReconciledMoneyIds = CashReconciliation::pluck('closed_cash_id')->toArray();
        $allExcludedMoneyIds = array_unique(array_merge($allReconciledMoneyIds, $lodgedMoneyIds));
        $unreconciledDays = ClosedCash::whereNotIn('MONEY', $allExcludedMoneyIds)
            ->whereDate('DATEEND', '>=', now()->subDays(60))
            ->orderBy('DATEEND', 'desc')
            ->get();

        // Verified bags: bag verifications not yet included in a lodgement
        $verifiedBags = CashBagVerification::whereNull('cash_lodgement_id')
            ->with(['reconciliation', 'verifier', 'lastEditor'])
            ->orderBy('verified_at', 'desc')
            ->get();

        $finishLodgementSummary = null;
        if (session('prompt_finish_lodgement') && $verifiedBags->count() > 0) {
            $finishLodgementSummary = [
                'count' => $verifiedBags->count(),
                'total' => $verifiedBags->sum('counted_total'),
                'max_variance' => (float) ($verifiedBags->max(fn ($v) => abs($v->variance)) ?? 0),
                'all_ids' => $verifiedBags->pluck('id')->map(fn ($id) => (string) $id)->values(),
            ];
        }

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
            'unreconciledDays',
            'tillNameToId',
            'verifiedBags',
            'finishLodgementSummary'
        ));
    }

    /**
     * Show details for a specific lodgement
     */
    public function show(CashLodgement $lodgement)
    {
        $lodgement->load([
            'matches.cashReconciliation.payments',
            'bagVerifications.reconciliation.payments',
            'bagVerifications.reconciliation.notes',
            'bagVerifications.verifier',
            'bankTransaction',
            'creator',
            'updater',
        ]);

        // The verified bags are the source of truth for which trading days this
        // lodgement covers - a lodgement can span several days, but money_id only
        // ever holds the first bag's till close.
        $bagVerifications = $lodgement->bagVerifications
            ->sortBy(fn ($verification) => $verification->reconciliation?->date)
            ->values();

        // Get related reconciliations for context, falling back to the single
        // money_id lookup for legacy imports which have no bag verifications.
        $relatedReconciliations = $bagVerifications->isNotEmpty()
            ? $bagVerifications->map(fn ($verification) => $verification->reconciliation)->filter()->values()
            : CashReconciliation::where('closed_cash_id', $lodgement->money_id)
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
            'bagVerifications',
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
            ...$this->denominationRules(),
        ]);

        // Treat empty/null denomination fields as 0
        foreach (self::DENOMINATIONS as $field) {
            $validated[$field] = $validated[$field] ?? 0;
        }

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

        $flashData = [
            'success' => sprintf(
                'Bag verified: €%.2f counted (expected €%.2f, variance €%.2f)',
                $countedTotal,
                $expectedTotal,
                $countedTotal - $expectedTotal
            ),
        ];

        $remainingPending = CashReconciliation::whereDoesntHave('bagVerification')
            ->with('payments')
            ->get()
            ->filter(fn ($r) => $r->calculateAvailableToLodge() > 0)
            ->count();

        $verifiedUnlinked = CashBagVerification::whereNull('cash_lodgement_id')->count();

        if ($remainingPending === 0 && $verifiedUnlinked > 0) {
            $flashData['prompt_finish_lodgement'] = true;
        }

        return back()->with($flashData);
    }

    /**
     * Correct the count on a bag that has been verified but not yet lodged.
     *
     * There is no "unverified" state to fall back to — the existence of the CashBagVerification row
     * IS the verified state, and cash_reconciliation_id is unique, so the fix has to be an in-place
     * update rather than a delete-and-recount.
     *
     * expected_total is deliberately re-derived rather than carried over. verifyBag() snapshots it
     * at verify time, but the underlying reconciliation stays editable afterwards
     * (CashReconciliationRepository::saveReconciliation() overwrites the denominations with no
     * verification check), so keeping the old snapshot would leave the stored variance measuring the
     * new count against a figure that no longer exists. The edit form shows the live expected figure
     * for the same reason.
     */
    public function updateBag(Request $request)
    {
        $validated = $request->validate([
            'verification_id' => 'required|uuid|exists:cash_bag_verifications,id',
            ...$this->denominationRules(),
        ]);

        foreach (self::DENOMINATIONS as $field) {
            $validated[$field] = $validated[$field] ?? 0;
        }

        $verification = CashBagVerification::with('reconciliation')->findOrFail($validated['verification_id']);

        // Guard: a lodged bag is locked, because the lodgement's cash_amount and any bank match were
        // derived from this count. Re-checked here rather than trusting the page, which only hides the
        // button at render time and can be stale by the time it is submitted.
        if ($verification->is_lodged) {
            return back()->with('error', 'This bag is already part of a lodgement and can no longer be edited.');
        }

        if (! $verification->reconciliation) {
            return back()->with('error', 'This bag has no reconciliation attached, so it cannot be recounted.');
        }

        $previousTotal = (float) $verification->counted_total;

        $verification->fill($validated);
        $countedTotal = $verification->calculateTotal();
        $expectedTotal = $verification->reconciliation->calculateAvailableToLodge();

        $verification->fill([
            'counted_total' => $countedTotal,
            'expected_total' => $expectedTotal,
            'variance' => $countedTotal - $expectedTotal,
            'last_edited_by' => auth()->id(),
            'last_edited_at' => now(),
        ]);

        $verification->save();

        return back()->with('success', sprintf(
            'Bag count corrected: €%.2f → €%.2f (expected €%.2f, variance €%.2f)',
            $previousTotal,
            $countedTotal,
            $expectedTotal,
            $countedTotal - $expectedTotal
        ));
    }

    /**
     * Validation rules for the nine denomination fields, shared by verifyBag() and updateBag().
     */
    private function denominationRules(): array
    {
        return array_fill_keys(self::DENOMINATIONS, 'nullable|integer|min:0');
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
