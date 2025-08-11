<?php

namespace App\Repositories;

use App\Models\POS\ClosedCash;
use App\Models\POS\DrawerOpened;
use App\Models\POS\LineRemoved;
use App\Models\POS\Payment;
use App\Models\POS\Receipt;
use App\Models\POS\Ticket;
use App\Models\TillReviewCache;
use App\Models\TillReviewSummary;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TillTransactionRepository
{
    /**
     * Get transactions for a specific date
     */
    public function getTransactionsForDate(Carbon $date, array $filters = []): Collection
    {
        $dateStr = $date->format('Y-m-d');
        
        // Try to get from cache first
        $cached = $this->getCachedTransactions($dateStr, $filters);
        if ($cached && $cached->isNotEmpty()) {
            return $cached;
        }
        
        // If not cached, fetch from POS and cache it
        $transactions = $this->fetchFromPOS($date);
        $this->cacheTransactions($transactions, $dateStr);
        
        return $this->applyFilters($transactions, $filters);
    }

    /**
     * Get cached transactions
     */
    private function getCachedTransactions(string $date, array $filters): ?Collection
    {
        $query = TillReviewCache::where('transaction_date', $date);
        
        if (!empty($filters['type'])) {
            $query->where('transaction_type', $filters['type']);
        }
        
        if (!empty($filters['terminal'])) {
            $query->where('terminal', $filters['terminal']);
        }
        
        if (!empty($filters['cashier'])) {
            $query->where('cashier', $filters['cashier']);
        }
        
        if (!empty($filters['time_from'])) {
            $query->where('transaction_time', '>=', $date . ' ' . $filters['time_from']);
        }
        
        if (!empty($filters['time_to'])) {
            $query->where('transaction_time', '<=', $date . ' ' . $filters['time_to']);
        }
        
        // Filter by payment type using JSON search
        if (!empty($filters['payment_type'])) {
            $query->whereJsonContains('transaction_data->payment_type', $filters['payment_type']);
        }
        
        return $query->orderBy('transaction_time')->get();
    }

    /**
     * Fetch transactions from POS database
     */
    private function fetchFromPOS(Carbon $date): Collection
    {
        $transactions = collect();
        $dateStr = $date->format('Y-m-d');
        
        // Fetch receipts with all related data
        $receipts = $this->fetchReceipts($dateStr);
        foreach ($receipts as $receipt) {
            $transactions->push($this->formatReceipt($receipt));
        }
        
        // Fetch line removed events
        $removedLines = $this->fetchRemovedLines($dateStr);
        foreach ($removedLines as $removed) {
            $transactions->push($this->formatRemovedLine($removed));
        }
        
        // Fetch drawer opened events
        $drawerEvents = $this->fetchDrawerEvents($dateStr);
        foreach ($drawerEvents as $drawer) {
            $transactions->push($this->formatDrawerEvent($drawer));
        }
        
        // Sort by time
        return $transactions->sortBy('transaction_time')->values();
    }

    /**
     * Fetch receipts from POS
     */
    private function fetchReceipts(string $date)
    {
        return Receipt::with([
            'ticket.ticketLines.product',
            'ticket.ticketLines.tax',
            'ticket.person',
            'ticket.customer',
            'payments',
            'closedCash'
        ])
        ->whereDate('DATENEW', $date)
        ->get();
    }

    /**
     * Format receipt for storage/display
     */
    private function formatReceipt($receipt): array
    {
        $ticket = $receipt->ticket;
        $payment = $receipt->payments->first();
        
        $lines = [];
        if ($ticket && $ticket->ticketLines) {
            foreach ($ticket->ticketLines as $line) {
                $tax = $line->tax ? $line->tax->RATE : 0;
                $lineTotal = ($line->PRICE * $line->UNITS) * (1 + $tax);
                
                $lines[] = [
                    'line' => $line->LINE,
                    'product' => $line->product ? $line->product->NAME : 'Unknown',
                    'product_code' => $line->product ? $line->product->CODE : '',
                    'units' => $line->UNITS,
                    'price' => $line->PRICE,
                    'tax' => $tax,
                    'total' => round($lineTotal, 2),
                ];
            }
        }
        
        return [
            'transaction_time' => $receipt->DATENEW,
            'transaction_type' => 'receipt',
            'receipt_id' => $receipt->ID,
            'ticket_id' => $ticket ? $ticket->TICKETID : null,
            'terminal' => $receipt->closedCash ? $receipt->closedCash->HOST : null,
            'cashier' => $ticket && $ticket->person ? $ticket->person->NAME : null,
            'customer' => $ticket && $ticket->customer ? $ticket->customer->NAME : null,
            'payment_type' => $payment ? $payment->PAYMENT : null,
            'amount' => $payment ? $payment->TOTAL : 0,
            'lines' => $lines,
            'line_count' => count($lines),
        ];
    }

    /**
     * Fetch removed lines from POS
     */
    private function fetchRemovedLines(string $date)
    {
        return LineRemoved::whereDate('REMOVEDDATE', $date)->get();
    }

    /**
     * Format removed line for storage/display
     */
    private function formatRemovedLine($removed): array
    {
        return [
            'transaction_time' => $removed->REMOVEDDATE,
            'transaction_type' => 'removed',
            'product_name' => $removed->PRODUCTNAME,
            'product_id' => $removed->PRODUCTID,
            'units' => $removed->UNITS,
            'ticket_id' => $removed->TICKETID,
            'terminal' => null,
            'cashier' => null,
            'amount' => 0,
        ];
    }

    /**
     * Fetch drawer events from POS
     */
    private function fetchDrawerEvents(string $date)
    {
        return DrawerOpened::whereDate('OPENDATE', $date)->get();
    }

    /**
     * Format drawer event for storage/display
     */
    private function formatDrawerEvent($drawer): array
    {
        return [
            'transaction_time' => $drawer->OPENDATE,
            'transaction_type' => 'drawer',
            'action' => $drawer->NAME,
            'ticket_id' => $drawer->TICKETID,
            'terminal' => null,
            'cashier' => null,
            'amount' => 0,
        ];
    }

    /**
     * Cache transactions in Laravel database
     */
    private function cacheTransactions(Collection $transactions, string $date): void
    {
        // Clear existing cache for this date
        TillReviewCache::where('transaction_date', $date)->delete();
        
        foreach ($transactions as $transaction) {
            TillReviewCache::create([
                'transaction_date' => $date,
                'transaction_time' => $transaction['transaction_time'],
                'transaction_type' => $transaction['transaction_type'],
                'receipt_id' => $transaction['receipt_id'] ?? null,
                'ticket_id' => $transaction['ticket_id'] ?? null,
                'transaction_data' => $transaction,
                'terminal' => $transaction['terminal'] ?? null,
                'cashier' => $transaction['cashier'] ?? null,
                'amount' => $transaction['amount'] ?? 0,
                'cached_at' => now(),
            ]);
        }
    }

    /**
     * Apply filters to transactions
     */
    private function applyFilters(Collection $transactions, array $filters): Collection
    {
        if (!empty($filters['search'])) {
            $search = strtolower($filters['search']);
            $transactions = $transactions->filter(function ($item) use ($search) {
                $data = is_array($item) ? $item : $item->transaction_data;
                $searchable = json_encode($data);
                return str_contains(strtolower($searchable), $search);
            });
        }
        
        if (!empty($filters['min_amount'])) {
            $transactions = $transactions->filter(function ($item) use ($filters) {
                $amount = is_array($item) ? ($item['amount'] ?? 0) : $item->amount;
                return $amount >= $filters['min_amount'];
            });
        }
        
        if (!empty($filters['max_amount'])) {
            $transactions = $transactions->filter(function ($item) use ($filters) {
                $amount = is_array($item) ? ($item['amount'] ?? 0) : $item->amount;
                return $amount <= $filters['max_amount'];
            });
        }
        
        if (!empty($filters['payment_type'])) {
            $transactions = $transactions->filter(function ($item) use ($filters) {
                $data = is_array($item) ? $item : $item->transaction_data;
                $paymentType = $data['payment_type'] ?? null;
                return $paymentType === $filters['payment_type'];
            });
        }
        
        return $transactions;
    }

    /**
     * Get or generate daily summary
     */
    public function getDailySummary(Carbon $date): TillReviewSummary
    {
        $dateStr = $date->format('Y-m-d');
        
        // Check if summary exists and looks valid
        $summary = TillReviewSummary::where('summary_date', $dateStr)->first();
        
        // Get actual transaction count to validate summary
        $actualTransactionCount = TillReviewCache::where('transaction_date', $dateStr)
            ->where('transaction_type', 'receipt')
            ->count();
            
        // If no cached transactions, get from POS to check
        if ($actualTransactionCount === 0) {
            $posTransactionCount = Receipt::whereDate('DATENEW', $dateStr)->count();
            if ($posTransactionCount > 0) {
                // We have transactions in POS but not cached, force regeneration
                if ($summary) {
                    $summary->delete();
                }
                return $this->generateDailySummary($date);
            }
        }
        
        // If summary exists and transaction counts match, return it
        if ($summary && $summary->total_transactions === $actualTransactionCount) {
            return $summary;
        }
        
        // Delete invalid summary and regenerate
        if ($summary) {
            $summary->delete();
        }
        
        // Generate fresh summary
        return $this->generateDailySummary($date);
    }

    /**
     * Generate daily summary from transactions
     */
    private function generateDailySummary(Carbon $date): TillReviewSummary
    {
        $dateStr = $date->format('Y-m-d');
        $transactions = $this->getTransactionsForDate($date);
        
        $summary = [
            'summary_date' => $dateStr,
            'total_sales' => 0,
            'total_transactions' => 0,
            'cash_total' => 0,
            'card_total' => 0,
            'other_total' => 0,
            'free_total' => 0,
            'debt_total' => 0,
            'drawer_opens' => 0,
            'no_sales' => 0,
            'voided_items_total' => 0,
            'voided_items_count' => 0,
            'vat_breakdown' => [],
            'hourly_breakdown' => [],
            'terminal_breakdown' => [],
            'cashier_breakdown' => [],
        ];
        
        foreach ($transactions as $transaction) {
            $data = is_array($transaction) ? $transaction : $transaction->transaction_data;
            
            switch ($data['transaction_type']) {
                case 'receipt':
                    $summary['total_transactions']++;
                    $amount = floatval($data['amount'] ?? 0);
                    $summary['total_sales'] += $amount;
                    
                    if (($data['payment_type'] ?? '') == 'cash') {
                        $summary['cash_total'] += $amount;
                    } elseif (($data['payment_type'] ?? '') == 'magcard') {
                        $summary['card_total'] += $amount;
                    } elseif (($data['payment_type'] ?? '') == 'free') {
                        $summary['free_total'] += $amount;
                    } elseif (($data['payment_type'] ?? '') == 'debt') {
                        $summary['debt_total'] += $amount;
                    } else {
                        $summary['other_total'] += $amount;
                    }
                    
                    // Track by hour
                    $hour = Carbon::parse($data['transaction_time'])->setTimezone(config('app.timezone'))->format('H');
                    if (!isset($summary['hourly_breakdown'][$hour])) {
                        $summary['hourly_breakdown'][$hour] = ['count' => 0, 'total' => 0];
                    }
                    $summary['hourly_breakdown'][$hour]['count']++;
                    $summary['hourly_breakdown'][$hour]['total'] += $amount;
                    
                    // Track by terminal
                    if (!empty($data['terminal'])) {
                        if (!isset($summary['terminal_breakdown'][$data['terminal']])) {
                            $summary['terminal_breakdown'][$data['terminal']] = ['count' => 0, 'total' => 0];
                        }
                        $summary['terminal_breakdown'][$data['terminal']]['count']++;
                        $summary['terminal_breakdown'][$data['terminal']]['total'] += $amount;
                    }
                    
                    // Track by cashier
                    if (!empty($data['cashier'])) {
                        if (!isset($summary['cashier_breakdown'][$data['cashier']])) {
                            $summary['cashier_breakdown'][$data['cashier']] = ['count' => 0, 'total' => 0];
                        }
                        $summary['cashier_breakdown'][$data['cashier']]['count']++;
                        $summary['cashier_breakdown'][$data['cashier']]['total'] += $amount;
                    }
                    break;
                    
                case 'drawer':
                    $summary['drawer_opens']++;
                    if (($data['action'] ?? '') == 'No Sale') {
                        $summary['no_sales']++;
                    }
                    break;
                    
                case 'removed':
                    $summary['voided_items_count']++;
                    break;
            }
        }
        
        return TillReviewSummary::create($summary);
    }

    /**
     * Clear cache for a specific date
     */
    public function clearCache(Carbon $date): void
    {
        $dateStr = $date->format('Y-m-d');
        TillReviewCache::where('transaction_date', $dateStr)->delete();
        TillReviewSummary::where('summary_date', $dateStr)->delete();
    }

    /**
     * Get available terminals
     */
    public function getTerminals(): Collection
    {
        // Try cache first
        $terminals = TillReviewCache::distinct('terminal')
            ->whereNotNull('terminal')
            ->pluck('terminal');
            
        // If cache is empty, get from POS database
        if ($terminals->isEmpty()) {
            $terminals = ClosedCash::distinct('HOST')
                ->whereNotNull('HOST')
                ->pluck('HOST');
        }
        
        return $terminals;
    }

    /**
     * Get available cashiers
     */
    public function getCashiers(): Collection
    {
        // Try cache first
        $cashiers = TillReviewCache::distinct('cashier')
            ->whereNotNull('cashier')
            ->pluck('cashier');
            
        // If cache is empty, get from POS database
        if ($cashiers->isEmpty()) {
            $cashiers = DB::connection('pos')
                ->table('PEOPLE')
                ->distinct()
                ->whereNotNull('NAME')
                ->pluck('NAME');
        }
        
        return $cashiers;
    }
}