<?php

namespace App\Http\Controllers\Management;

use App\Http\Controllers\Controller;
use App\Models\BankTransaction;
use App\Models\CashLodgement;
use App\Models\CashLodgementMatch;
use App\Models\CashReconciliation;
use App\Models\CashReconciliationNote;
use App\Models\CashReconciliationPayment;
use App\Models\POS\ClosedCash;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CashLodgementDiagnosticController extends Controller
{
    /**
     * Display diagnostic information for cash lodgements system
     */
    public function index(Request $request)
    {
        // Default to last 30 days for better performance
        $startDate = $request->has('start_date')
            ? Carbon::parse($request->start_date)
            : Carbon::now()->subDays(30);

        $endDate = $request->has('end_date')
            ? Carbon::parse($request->end_date)
            : Carbon::now();

        // 1. Cash Lodgements (Main Laravel table)
        $cashLodgements = CashLodgement::whereBetween('lodgement_date', [$startDate, $endDate])
            ->with(['creator', 'updater', 'bankTransaction', 'closedCash', 'matches.cashReconciliation'])
            ->orderBy('lodgement_date', 'desc')
            ->limit(20)
            ->get();

        $cashLodgementStats = [
            'total_count' => CashLodgement::count(),
            'date_range_count' => CashLodgement::whereBetween('lodgement_date', [$startDate, $endDate])->count(),
            'matched_count' => CashLodgement::whereBetween('lodgement_date', [$startDate, $endDate])->where('is_matched', true)->count(),
            'unmatched_count' => CashLodgement::whereBetween('lodgement_date', [$startDate, $endDate])->where('is_matched', false)->count(),
            'total_amount' => CashLodgement::whereBetween('lodgement_date', [$startDate, $endDate])->sum('total_amount'),
            'legacy_imports' => CashLodgement::where('imported_from_legacy', true)->count(),
        ];

        // 2. Cash Lodgement Matches (Junction table)
        $cashLodgementMatches = CashLodgementMatch::whereHas('cashLodgement', function ($query) use ($startDate, $endDate) {
            $query->whereBetween('lodgement_date', [$startDate, $endDate]);
        })
            ->with(['cashLodgement', 'cashReconciliation', 'matcher'])
            ->orderBy('created_at', 'desc')
            ->limit(20)
            ->get();

        $matchStats = [
            'total_matches' => CashLodgementMatch::count(),
            'date_range_matches' => CashLodgementMatch::whereHas('cashLodgement', function ($query) use ($startDate, $endDate) {
                $query->whereBetween('lodgement_date', [$startDate, $endDate]);
            })->count(),
            'manual_matches' => CashLodgementMatch::where('match_type', 'manual')->count(),
            'automatic_matches' => CashLodgementMatch::whereIn('match_type', ['exact', 'partial', 'accumulated'])->count(),
            'high_confidence' => CashLodgementMatch::where('confidence_score', '>=', 80)->count(),
        ];

        // 3. Cash Reconciliations (Till reconciliation data)
        $cashReconciliations = CashReconciliation::whereBetween('date', [$startDate, $endDate])
            ->with(['creator', 'updater', 'payments', 'notes'])
            ->orderBy('date', 'desc')
            ->limit(20)
            ->get();

        $reconciliationStats = [
            'total_count' => CashReconciliation::count(),
            'date_range_count' => CashReconciliation::whereBetween('date', [$startDate, $endDate])->count(),
            'with_payments' => CashReconciliation::has('payments')->count(),
            'with_notes' => CashReconciliation::has('notes')->count(),
            'unique_tills' => CashReconciliation::distinct('till_name')->count('till_name'),
        ];

        // 4. Cash Reconciliation Payments (Supplier payments)
        $reconciliationPayments = CashReconciliationPayment::whereHas('reconciliation', function ($query) use ($startDate, $endDate) {
            $query->whereBetween('date', [$startDate, $endDate]);
        })
            ->with(['reconciliation', 'supplier'])
            ->orderBy('created_at', 'desc')
            ->limit(20)
            ->get();

        $paymentStats = [
            'total_payments' => CashReconciliationPayment::count(),
            'date_range_payments' => CashReconciliationPayment::whereHas('reconciliation', function ($query) use ($startDate, $endDate) {
                $query->whereBetween('date', [$startDate, $endDate]);
            })->count(),
            'total_amount' => CashReconciliationPayment::sum('amount'),
            'unique_payees' => CashReconciliationPayment::distinct('payee_name')->count('payee_name'),
        ];

        // 5. Bank Transactions (Bank statement data)
        $bankTransactions = BankTransaction::whereBetween('transaction_date', [$startDate, $endDate])
            ->where(function ($query) {
                $query->where('description', 'like', '%lodgement%')
                    ->orWhere('description', 'like', '%cash%')
                    ->orWhere('credit_category', 'cash_lodgement');
            })
            ->with(['user', 'allocations'])
            ->orderBy('transaction_date', 'desc')
            ->limit(20)
            ->get();

        $bankStats = [
            'total_transactions' => BankTransaction::count(),
            'cash_related' => BankTransaction::where(function ($query) {
                $query->where('description', 'like', '%lodgement%')
                    ->orWhere('description', 'like', '%cash%')
                    ->orWhere('credit_category', 'cash_lodgement');
            })->count(),
            'pending_status' => BankTransaction::where('status', 'pending')->count(),
            'matched_status' => BankTransaction::where('status', 'matched')->count(),
        ];

        // 6. POS Closed Cash (From POS database)
        $posClosedCash = ClosedCash::whereDate('DATEEND', '>=', $startDate)
            ->whereDate('DATEEND', '<=', $endDate)
            ->orderBy('DATEEND', 'desc')
            ->limit(20)
            ->get();

        $posStats = [
            'total_pos_records' => ClosedCash::count(),
            'date_range_pos' => ClosedCash::whereDate('DATEEND', '>=', $startDate)
                ->whereDate('DATEEND', '<=', $endDate)->count(),
        ];

        // 7. Reconciliation Notes
        $reconciliationNotes = CashReconciliationNote::whereHas('reconciliation', function ($query) use ($startDate, $endDate) {
            $query->whereBetween('date', [$startDate, $endDate]);
        })
            ->with(['reconciliation', 'creator'])
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        // 8. Relationship Analysis
        $relationshipStats = [
            'lodgements_without_matches' => CashLodgement::doesntHave('matches')->count(),
            'reconciliations_without_lodgements' => CashReconciliation::doesntHave('cashLodgements')->count(),
            'orphaned_matches' => CashLodgementMatch::whereDoesntHave('cashLodgement')->count(),
            'pos_without_reconciliation' => $this->getOrphanedPosRecords(),
        ];

        // 9. Users involved in the system
        $systemUsers = User::whereIn('id', function ($query) {
            $query->select('created_by')->from('cash_lodgements')->whereNotNull('created_by')
                ->union(
                    DB::table('cash_reconciliations')->select('created_by')->whereNotNull('created_by')
                )
                ->union(
                    DB::table('cash_lodgement_matches')->select('matched_by')->whereNotNull('matched_by')
                );
        })->get();

        return view('management.cash-lodgements.diagnostic', compact(
            'startDate',
            'endDate',
            'cashLodgements',
            'cashLodgementStats',
            'cashLodgementMatches',
            'matchStats',
            'cashReconciliations',
            'reconciliationStats',
            'reconciliationPayments',
            'paymentStats',
            'bankTransactions',
            'bankStats',
            'posClosedCash',
            'posStats',
            'reconciliationNotes',
            'relationshipStats',
            'systemUsers'
        ));
    }

    /**
     * Get count of POS records without corresponding reconciliation
     */
    private function getOrphanedPosRecords(): int
    {
        try {
            $posMoneyIds = ClosedCash::pluck('MONEY')->toArray();
            $reconciliationMoneyIds = CashReconciliation::pluck('closed_cash_id')->toArray();

            return count(array_diff($posMoneyIds, $reconciliationMoneyIds));
        } catch (\Exception $e) {
            return 0;
        }
    }

    /**
     * Export diagnostic data as CSV
     */
    public function export(Request $request)
    {
        // Re-run the same queries but get all data (remove limits)
        $startDate = $request->has('start_date')
            ? Carbon::parse($request->start_date)
            : Carbon::now()->subDays(30);

        $endDate = $request->has('end_date')
            ? Carbon::parse($request->end_date)
            : Carbon::now();

        $filename = 'cash_lodgements_diagnostic_'.$startDate->format('Y-m-d').'_to_'.$endDate->format('Y-m-d').'.csv';

        return response()->streamDownload(function () use ($startDate, $endDate) {
            $handle = fopen('php://output', 'w');

            // Header
            fputcsv($handle, ['Cash Lodgements Diagnostic Export']);
            fputcsv($handle, ['Period:', $startDate->format('Y-m-d'), 'to', $endDate->format('Y-m-d')]);
            fputcsv($handle, ['Generated:', now()->format('Y-m-d H:i:s')]);
            fputcsv($handle, []);

            // Cash Lodgements
            fputcsv($handle, ['=== CASH LODGEMENTS ===']);
            fputcsv($handle, [
                'ID', 'Money ID', 'Lodgement Date', 'Cash Amount', 'Cheque Amount',
                'Total Amount', 'Till Name', 'Type', 'Matched', 'Legacy Import', 'Created At',
            ]);

            $lodgements = CashLodgement::whereBetween('lodgement_date', [$startDate, $endDate])->get();
            foreach ($lodgements as $lodgement) {
                fputcsv($handle, [
                    $lodgement->id,
                    $lodgement->money_id,
                    $lodgement->lodgement_date->format('Y-m-d'),
                    $lodgement->cash_amount,
                    $lodgement->cheque_amount,
                    $lodgement->total_amount,
                    $lodgement->till_name,
                    $lodgement->lodgement_type,
                    $lodgement->is_matched ? 'Yes' : 'No',
                    $lodgement->imported_from_legacy ? 'Yes' : 'No',
                    $lodgement->created_at->format('Y-m-d H:i:s'),
                ]);
            }

            fputcsv($handle, []);

            // Add other tables similarly...
            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Content-Disposition' => 'attachment; filename='.$filename,
            'Expires' => '0',
            'Pragma' => 'public',
        ]);
    }
}
