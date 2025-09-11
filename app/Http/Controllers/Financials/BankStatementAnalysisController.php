<?php

namespace App\Http\Controllers\Financials;

use App\Http\Controllers\Controller;
use App\Services\BankStatementAnalysisService;
use Carbon\Carbon;
use Illuminate\Http\Request;

class BankStatementAnalysisController extends Controller
{
    protected BankStatementAnalysisService $analysisService;

    public function __construct(BankStatementAnalysisService $analysisService)
    {
        $this->analysisService = $analysisService;
    }

    public function index(Request $request)
    {
        // Default to last 30 days
        $startDate = $request->has('start_date')
            ? Carbon::parse($request->start_date)
            : Carbon::now()->subDays(30);

        $endDate = $request->has('end_date')
            ? Carbon::parse($request->end_date)
            : Carbon::now();

        // Get analysis data
        $analysisData = $this->analysisService->analyzePeriod($startDate, $endDate);

        // Get summary statistics
        $summary = $this->analysisService->getSummaryStatistics($startDate, $endDate);

        // Get unmatched items for quick action
        $unmatchedPOS = $this->analysisService->getUnmatchedPOSDays($startDate, $endDate);
        $unmatchedBank = $this->analysisService->getUnmatchedBankTransactions($startDate, $endDate);
        $unmatchedBankByCategory = $this->analysisService->getUnmatchedBankTransactionsByCategory($startDate, $endDate);

        // Get pattern insights
        $patterns = $this->analysisService->getPatternInsights();

        // Get cash reconciliation data
        $cashReconciliation = $this->analysisService->getCashReconciliationSummary($startDate, $endDate);
        $cashMatchSuggestions = $this->analysisService->suggestCashMatches($startDate, $endDate);

        return view('financials.bank-statements.analysis', compact(
            'analysisData',
            'summary',
            'unmatchedPOS',
            'unmatchedBank',
            'unmatchedBankByCategory',
            'patterns',
            'cashReconciliation',
            'cashMatchSuggestions',
            'startDate',
            'endDate'
        ));
    }

    public function matchTransaction(Request $request)
    {
        $request->validate([
            'pos_date' => 'required|date',
            'bank_transaction_id' => 'required|uuid',
            'match_type' => 'required|in:exact,partial,combined,split',
            'matched_amount' => 'required|numeric|min:0',
            'notes' => 'nullable|string|max:500',
        ]);

        try {
            $match = $this->analysisService->createMatch(
                $request->pos_date,
                $request->bank_transaction_id,
                $request->match_type,
                $request->matched_amount,
                $request->notes
            );

            return response()->json([
                'success' => true,
                'message' => 'Successfully matched transaction',
                'match' => $match,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to match transaction: '.$e->getMessage(),
            ], 500);
        }
    }

    public function unmatchTransaction(Request $request)
    {
        $request->validate([
            'match_id' => 'required|exists:pos_bank_matches,id',
        ]);

        try {
            $this->analysisService->removeMatch($request->match_id);

            return response()->json([
                'success' => true,
                'message' => 'Successfully unmatched transaction',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to unmatch transaction: '.$e->getMessage(),
            ], 500);
        }
    }

    public function refreshPOSData(Request $request)
    {
        $request->validate([
            'date' => 'required|date',
        ]);

        try {
            $summary = $this->analysisService->refreshPOSSummary($request->date);

            return response()->json([
                'success' => true,
                'message' => 'POS data refreshed successfully',
                'summary' => $summary,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to refresh POS data: '.$e->getMessage(),
            ], 500);
        }
    }

    public function export(Request $request)
    {
        $startDate = $request->has('start_date')
            ? Carbon::parse($request->start_date)
            : Carbon::now()->subDays(30);

        $endDate = $request->has('end_date')
            ? Carbon::parse($request->end_date)
            : Carbon::now();

        // Get all the data that's displayed on the analysis page
        $analysisData = $this->analysisService->analyzePeriod($startDate, $endDate);
        $summary = $this->analysisService->getSummaryStatistics($startDate, $endDate);
        $unmatchedPOS = $this->analysisService->getUnmatchedPOSDays($startDate, $endDate);
        $unmatchedBank = $this->analysisService->getUnmatchedBankTransactions($startDate, $endDate);
        $unmatchedBankByCategory = $this->analysisService->getUnmatchedBankTransactionsByCategory($startDate, $endDate);
        $patterns = $this->analysisService->getPatternInsights();
        $cashReconciliation = $this->analysisService->getCashReconciliationSummary($startDate, $endDate);
        $cashMatchSuggestions = $this->analysisService->suggestCashMatches($startDate, $endDate);

        $filename = 'bank_statement_analysis_comprehensive_'.$startDate->format('Y-m-d').'_to_'.$endDate->format('Y-m-d').'.csv';

        return response()->streamDownload(function () use ($startDate, $endDate, $summary, $analysisData, $unmatchedBankByCategory, $cashReconciliation, $cashMatchSuggestions, $patterns) {
            $handle = fopen('php://output', 'w');

            // Header with export info
            fputcsv($handle, ['Bank Statement Analysis - Comprehensive Export']);
            fputcsv($handle, ['Period:', $startDate->format('Y-m-d'), 'to', $endDate->format('Y-m-d')]);
            fputcsv($handle, ['Generated:', now()->format('Y-m-d H:i:s')]);
            fputcsv($handle, []); // Empty row

            // ===== SUMMARY STATISTICS =====
            fputcsv($handle, ['=== SUMMARY STATISTICS ===']);
            fputcsv($handle, ['Metric', 'Amount', 'Notes']);
            fputcsv($handle, ['POS Total', '€'.number_format($summary['pos_total'], 2), 'Cash + Card sales']);
            fputcsv($handle, ['POS Cash', '€'.number_format($summary['pos_cash'], 2), 'Cash sales only']);
            fputcsv($handle, ['POS Card', '€'.number_format($summary['pos_card'], 2), 'Card sales only']);
            fputcsv($handle, ['Bank Total', '€'.number_format($summary['bank_total'], 2), 'All bank lodgements']);
            fputcsv($handle, ['Variance', '€'.number_format($summary['variance'], 2), 'Bank - POS difference']);
            fputcsv($handle, ['Variance %', round(($summary['variance'] / max($summary['pos_total'], 1)) * 100, 2).'%', 'Percentage difference']);
            fputcsv($handle, ['Unmatched Days', $summary['unmatched_days'], 'Days requiring attention']);
            fputcsv($handle, []); // Empty row

            // ===== CASH RECONCILIATION =====
            if (isset($cashReconciliation)) {
                fputcsv($handle, ['=== CASH RECONCILIATION SUMMARY ===']);
                fputcsv($handle, ['Metric', 'Amount', 'Count', 'Notes']);
                fputcsv($handle, ['POS Cash Sales', '€'.number_format($cashReconciliation['pos_cash_total'], 2), '', 'Total cash from POS']);
                fputcsv($handle, ['Cash Lodgements', '€'.number_format($cashReconciliation['cash_lodgements_total'], 2), $cashReconciliation['lodgement_count'], 'Total bank cash deposits']);
                fputcsv($handle, ['Unmatched Lodgements', '€'.number_format($cashReconciliation['unmatched_cash_lodgements'], 2), $cashReconciliation['unmatched_lodgement_count'], 'Deposits not yet matched']);
                fputcsv($handle, ['Accumulated Unmatched', '€'.number_format($cashReconciliation['accumulated_unmatched_cash'], 2), '', 'Running cash balance']);
                fputcsv($handle, ['Cash Variance', '€'.number_format($cashReconciliation['variance'], 2), '', 'Lodgements - POS cash']);
                fputcsv($handle, ['Match Rate', $cashReconciliation['match_rate'].'%', '', 'Percentage matched']);
                fputcsv($handle, []); // Empty row
            }

            // ===== CREDIT CATEGORIES BREAKDOWN =====
            if (count($unmatchedBankByCategory) > 0) {
                fputcsv($handle, ['=== UNMATCHED CREDIT CATEGORIES ===']);
                fputcsv($handle, ['Category', 'Count', 'Total Amount', 'Display Name']);
                foreach ($unmatchedBankByCategory as $categoryKey => $categoryData) {
                    fputcsv($handle, [
                        $categoryKey,
                        $categoryData['count'],
                        '€'.number_format($categoryData['total_amount'], 2),
                        $categoryData['display_name'],
                    ]);
                }
                fputcsv($handle, []); // Empty row
            }

            // ===== CASH MATCH SUGGESTIONS =====
            if (isset($cashMatchSuggestions) && count($cashMatchSuggestions) > 0) {
                fputcsv($handle, ['=== SMART CASH MATCHING SUGGESTIONS ===']);
                fputcsv($handle, ['Lodgement Date', 'Amount', 'Suggested POS Date', 'Accumulated Cash', 'Variance €', 'Variance %', 'Days Delay', 'Confidence %', 'Description']);
                foreach ($cashMatchSuggestions as $suggestion) {
                    fputcsv($handle, [
                        $suggestion['lodgement_date'],
                        '€'.number_format($suggestion['lodgement_amount'], 2),
                        $suggestion['suggested_pos_date'],
                        '€'.number_format($suggestion['accumulated_cash'], 2),
                        '€'.number_format($suggestion['variance'], 2),
                        $suggestion['variance_percent'].'%',
                        $suggestion['days_delay'],
                        $suggestion['confidence'].'%',
                        $suggestion['description'],
                    ]);
                }
                fputcsv($handle, []); // Empty row
            }

            // ===== UNMATCHED CASH LODGEMENTS DETAIL =====
            if (isset($cashReconciliation) && count($cashReconciliation['unmatched_lodgements']) > 0) {
                fputcsv($handle, ['=== UNMATCHED CASH LODGEMENTS DETAIL ===']);
                fputcsv($handle, ['Date', 'Amount', 'Description', 'Days Since Period End']);
                foreach ($cashReconciliation['unmatched_lodgements'] as $lodgement) {
                    $lodgementDate = \Carbon\Carbon::parse($lodgement->transaction_date);
                    $daysSinceEnd = $endDate->diffInDays($lodgementDate, false);
                    fputcsv($handle, [
                        $lodgementDate->format('Y-m-d'),
                        '€'.number_format($lodgement->credit_amount, 2),
                        $lodgement->description,
                        ($daysSinceEnd >= 0 ? '+' : '').$daysSinceEnd.' days',
                    ]);
                }
                fputcsv($handle, []); // Empty row
            }

            // ===== PATTERN INSIGHTS =====
            if (count($patterns) > 0) {
                fputcsv($handle, ['=== PATTERN INSIGHTS ===']);
                fputcsv($handle, ['Insights']);
                foreach ($patterns as $pattern) {
                    fputcsv($handle, [$pattern]);
                }
                fputcsv($handle, []); // Empty row
            }

            // ===== DAILY COMPARISON =====
            fputcsv($handle, ['=== DAILY COMPARISON ===']);
            fputcsv($handle, [
                'Date',
                'Day',
                'POS Cash',
                'POS Card',
                'POS Total',
                'Bank Lodged',
                'Bank Category',
                'Match Type',
                'Confidence',
                'Variance €',
                'Variance %',
                'Status',
                'Suggestions',
            ]);

            foreach ($analysisData as $day) {
                $bankCategory = '';
                $matchType = '';
                $confidence = '';

                if (isset($day['bank']['category_info'])) {
                    $bankCategory = $day['bank']['category_info']['display'] ?? '';
                }
                if (isset($day['bank']['match_type'])) {
                    $matchType = $day['bank']['match_type'];
                }
                if (isset($day['bank']['confidence'])) {
                    $confidence = $day['bank']['confidence'].'%';
                }

                fputcsv($handle, [
                    $day['date'],
                    $day['day_of_week'],
                    '€'.number_format($day['pos']['net_cash'], 2),
                    '€'.number_format($day['pos']['net_card'], 2),
                    '€'.number_format($day['pos']['total'], 2),
                    '€'.number_format($day['bank']['total_lodged'] ?? 0, 2),
                    $bankCategory,
                    $matchType,
                    $confidence,
                    '€'.number_format($day['variance']['amount'], 2),
                    round($day['variance']['percentage'], 2).'%',
                    $day['status'],
                    implode('; ', $day['suggestions']),
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

    public function suggestMatches(Request $request)
    {
        $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date',
        ]);

        $suggestions = $this->analysisService->suggestMatches(
            Carbon::parse($request->start_date),
            Carbon::parse($request->end_date)
        );

        return response()->json([
            'success' => true,
            'suggestions' => $suggestions,
        ]);
    }
}
