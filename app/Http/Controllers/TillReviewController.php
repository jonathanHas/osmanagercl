<?php

namespace App\Http\Controllers;

use App\Models\TillReviewAudit;
use App\Repositories\TillTransactionRepository;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Response;

class TillReviewController extends Controller
{
    protected TillTransactionRepository $repository;

    public function __construct(TillTransactionRepository $repository)
    {
        $this->repository = $repository;
    }

    /**
     * Display the till review dashboard
     */
    public function index(Request $request)
    {
        // Get date from request, URL parameter, or default to today
        $date = $request->input('date') ?? $request->get('date') ?? now()->format('Y-m-d');
        
        try {
            $selectedDate = Carbon::parse($date);
        } catch (\Exception $e) {
            // If date parsing fails, default to today
            $selectedDate = now();
        }
        
        // Log audit
        $this->logAudit($selectedDate, $request->all());
        
        // Get transactions first to ensure cache is populated
        $transactions = $this->repository->getTransactionsForDate($selectedDate);
        
        // Get summary for the date
        $summary = $this->repository->getDailySummary($selectedDate);
        
        // Debug logging
        \Log::info('Till Review Index Debug', [
            'date' => $selectedDate->format('Y-m-d'),
            'transactions_count' => $transactions->count(),
            'summary_total_sales' => $summary->total_sales ?? 'null',
            'summary_total_transactions' => $summary->total_transactions ?? 'null',
            'summary_exists' => $summary ? 'yes' : 'no',
        ]);
        
        // Get available filters
        $terminals = $this->repository->getTerminals();
        $cashiers = $this->repository->getCashiers();
        
        return view('till-review.index', compact(
            'selectedDate',
            'summary',
            'terminals',
            'cashiers'
        ));
    }

    /**
     * Get summary data via AJAX
     */
    public function getSummary(Request $request)
    {
        $request->validate([
            'date' => 'required|date',
        ]);
        
        $date = Carbon::parse($request->input('date'));
        
        // Ensure transactions are cached first
        $transactions = $this->repository->getTransactionsForDate($date);
        
        // Get summary
        $summary = $this->repository->getDailySummary($date);
        
        return response()->json([
            'date' => $date->format('Y-m-d'),
            'summary' => [
                'total_sales' => $summary->total_sales ?? 0,
                'total_transactions' => $summary->total_transactions ?? 0,
                'cash_total' => $summary->cash_total ?? 0,
                'card_total' => $summary->card_total ?? 0,
                'other_total' => $summary->other_total ?? 0,
                'free_total' => $summary->free_total ?? 0,
                'debt_total' => $summary->debt_total ?? 0,
                'drawer_opens' => $summary->drawer_opens ?? 0,
                'voided_items_count' => $summary->voided_items_count ?? 0,
            ]
        ]);
    }

    /**
     * Get transactions via AJAX
     */
    public function getTransactions(Request $request)
    {
        $request->validate([
            'date' => 'required|date',
            'type' => 'nullable|in:receipt,drawer,removed,card',
            'terminal' => 'nullable|string',
            'cashier' => 'nullable|string',
            'time_from' => 'nullable|date_format:H:i',
            'time_to' => 'nullable|date_format:H:i',
            'search' => 'nullable|string|max:100',
            'min_amount' => 'nullable|numeric|min:0',
            'max_amount' => 'nullable|numeric|min:0',
            'payment_type' => 'nullable|in:cash,magcard,free,debt',
        ]);
        
        $date = Carbon::parse($request->input('date'));
        $filters = $request->only([
            'type', 'terminal', 'cashier', 'time_from', 
            'time_to', 'search', 'min_amount', 'max_amount', 'payment_type'
        ]);
        
        // Get transactions
        $transactions = $this->repository->getTransactionsForDate($date, $filters);
        
        // Format for display
        $formatted = $transactions->map(function ($item) {
            $data = is_array($item) ? $item : $item->transaction_data;
            
            return [
                'time' => Carbon::parse($data['transaction_time'])->format('H:i:s'),
                'type' => $data['transaction_type'],
                'type_display' => $this->getTypeDisplay($data['transaction_type']),
                'type_color' => $this->getTypeColor($data['transaction_type']),
                'description' => $this->getTransactionDescription($data),
                'amount' => $data['amount'] ?? 0,
                'details' => $data,
            ];
        });
        
        return response()->json([
            'transactions' => $formatted,
            'count' => $formatted->count(),
        ]);
    }

    /**
     * Refresh cache for a date
     */
    public function refreshCache(Request $request)
    {
        $request->validate([
            'date' => 'required|date',
        ]);
        
        $date = Carbon::parse($request->input('date'));
        
        // Clear existing cache
        $this->repository->clearCache($date);
        
        // Re-fetch and cache
        $transactions = $this->repository->getTransactionsForDate($date);
        
        return response()->json([
            'success' => true,
            'message' => 'Cache refreshed successfully',
            'transaction_count' => $transactions->count(),
        ]);
    }

    /**
     * Export transactions
     */
    public function export(Request $request)
    {
        $request->validate([
            'date' => 'required|date',
            'format' => 'required|in:csv,pdf',
        ]);
        
        $date = Carbon::parse($request->input('date'));
        $format = $request->input('format');
        
        $transactions = $this->repository->getTransactionsForDate($date);
        $summary = $this->repository->getDailySummary($date);
        
        if ($format === 'csv') {
            return $this->exportCSV($transactions, $summary, $date);
        }
        
        // PDF export would require additional package like dompdf
        return response()->json(['error' => 'PDF export not yet implemented'], 501);
    }

    /**
     * Export as CSV
     */
    private function exportCSV($transactions, $summary, Carbon $date)
    {
        $filename = 'till-review-' . $date->format('Y-m-d') . '.csv';
        
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];
        
        $callback = function () use ($transactions, $summary, $date) {
            $file = fopen('php://output', 'w');
            
            // Header
            fputcsv($file, ['Till Review Report - ' . $date->format('Y-m-d')]);
            fputcsv($file, []);
            
            // Summary
            fputcsv($file, ['Summary']);
            fputcsv($file, ['Total Sales', number_format($summary->total_sales, 2)]);
            fputcsv($file, ['Total Transactions', $summary->total_transactions]);
            fputcsv($file, ['Cash Total', number_format($summary->cash_total, 2)]);
            fputcsv($file, ['Card Total', number_format($summary->card_total, 2)]);
            fputcsv($file, ['Drawer Opens', $summary->drawer_opens]);
            fputcsv($file, ['Voided Items', $summary->voided_items_count]);
            fputcsv($file, []);
            
            // Transactions header
            fputcsv($file, ['Time', 'Type', 'Description', 'Amount', 'Terminal', 'Cashier']);
            
            // Transactions data
            foreach ($transactions as $transaction) {
                $data = is_array($transaction) ? $transaction : $transaction->transaction_data;
                
                fputcsv($file, [
                    Carbon::parse($data['transaction_time'])->format('H:i:s'),
                    $data['transaction_type'],
                    $this->getTransactionDescription($data),
                    number_format($data['amount'] ?? 0, 2),
                    $data['terminal'] ?? '',
                    $data['cashier'] ?? '',
                ]);
            }
            
            fclose($file);
        };
        
        return Response::stream($callback, 200, $headers);
    }

    /**
     * Get transaction type display name
     */
    private function getTypeDisplay(string $type): string
    {
        return match ($type) {
            'receipt' => 'Receipt',
            'drawer' => 'Drawer',
            'removed' => 'Void',
            'card' => 'Card',
            default => ucfirst($type),
        };
    }

    /**
     * Get transaction type color class
     */
    private function getTypeColor(string $type): string
    {
        return match ($type) {
            'receipt' => 'text-green-600',
            'drawer' => 'text-blue-600',
            'removed' => 'text-red-600',
            'card' => 'text-purple-600',
            default => 'text-gray-600',
        };
    }

    /**
     * Get transaction description
     */
    private function getTransactionDescription(array $data): string
    {
        switch ($data['transaction_type']) {
            case 'receipt':
                $items = $data['line_count'] ?? 0;
                $payment = $data['payment_type'] ?? 'unknown';
                $customer = $data['customer'] ?? null;
                $desc = "Receipt #{$data['ticket_id']} - {$items} items ({$payment})";
                if ($customer) {
                    $desc .= " - Customer: {$customer}";
                }
                return $desc;
                
            case 'drawer':
                return "Drawer: {$data['action']}";
                
            case 'removed':
                return "Voided: {$data['product_name']} x {$data['units']}";
                
            case 'card':
                return "Card Transaction";
                
            default:
                return "Transaction";
        }
    }

    /**
     * Log audit trail
     */
    private function logAudit(Carbon $date, array $filters)
    {
        TillReviewAudit::create([
            'user_id' => Auth::id(),
            'viewed_date' => $date->format('Y-m-d'),
            'filters_used' => $filters,
            'action' => 'view',
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
    }
}