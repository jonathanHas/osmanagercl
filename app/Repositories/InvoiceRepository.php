<?php

namespace App\Repositories;

use App\Models\Invoice;
use App\Models\InvoiceUploadFile;
use Illuminate\Database\Eloquent\Builder;

class InvoiceRepository
{
    /**
     * Get filtered statistics in a single query (replaces 14+ queries).
     *
     * Uses selectRaw with CASE expressions to calculate all stats at once.
     */
    public function getFilteredStatistics(Builder $query): array
    {
        $result = (clone $query)
            ->selectRaw('
                COUNT(*) as total_count,
                COALESCE(SUM(total_amount), 0) as total_amount,
                COALESCE(SUM(subtotal), 0) as total_subtotal,
                COALESCE(SUM(vat_amount), 0) as total_vat,
                COALESCE(SUM(standard_net), 0) as standard_net,
                COALESCE(SUM(standard_vat), 0) as standard_vat,
                COALESCE(SUM(reduced_net), 0) as reduced_net,
                COALESCE(SUM(reduced_vat), 0) as reduced_vat,
                COALESCE(SUM(second_reduced_net), 0) as second_reduced_net,
                COALESCE(SUM(second_reduced_vat), 0) as second_reduced_vat,
                COALESCE(SUM(zero_net), 0) as zero_net,
                SUM(CASE WHEN payment_status = "paid" THEN 1 ELSE 0 END) as paid_count,
                SUM(CASE WHEN payment_status IN ("pending", "overdue", "partial") THEN 1 ELSE 0 END) as unpaid_count,
                COALESCE(SUM(CASE WHEN payment_status IN ("pending", "overdue", "partial") THEN total_amount ELSE 0 END), 0) as unpaid_total
            ')
            ->first();

        return [
            'total_count' => (int) $result->total_count,
            'total_amount' => (float) $result->total_amount,
            'total_subtotal' => (float) $result->total_subtotal,
            'total_vat' => (float) $result->total_vat,
            'standard_net' => (float) $result->standard_net,
            'standard_vat' => (float) $result->standard_vat,
            'reduced_net' => (float) $result->reduced_net,
            'reduced_vat' => (float) $result->reduced_vat,
            'second_reduced_net' => (float) $result->second_reduced_net,
            'second_reduced_vat' => (float) $result->second_reduced_vat,
            'zero_net' => (float) $result->zero_net,
            'paid_count' => (int) $result->paid_count,
            'unpaid_count' => (int) $result->unpaid_count,
            'unpaid_total' => (float) $result->unpaid_total,
        ];
    }

    /**
     * Get overall unpaid/overdue statistics in a single query (replaces 4 queries).
     */
    public function getOverallStatistics(): array
    {
        $result = Invoice::query()
            ->selectRaw('
                COALESCE(SUM(CASE WHEN payment_status IN ("pending", "overdue", "partial") THEN total_amount ELSE 0 END), 0) as total_unpaid,
                COALESCE(SUM(CASE WHEN payment_status IN ("pending", "overdue", "partial") AND due_date < ? THEN total_amount ELSE 0 END), 0) as total_overdue,
                SUM(CASE WHEN payment_status IN ("pending", "overdue", "partial") THEN 1 ELSE 0 END) as count_unpaid,
                SUM(CASE WHEN payment_status IN ("pending", "overdue", "partial") AND due_date < ? THEN 1 ELSE 0 END) as count_overdue
            ', [now()->toDateString(), now()->toDateString()])
            ->first();

        return [
            'total_unpaid' => (float) $result->total_unpaid,
            'total_overdue' => (float) $result->total_overdue,
            'count_unpaid' => (int) $result->count_unpaid,
            'count_overdue' => (int) $result->count_overdue,
        ];
    }

    /**
     * Get monthly totals for This Month and Last Month cards (replaces 2 Blade queries).
     */
    public function getMonthlyTotals(): array
    {
        $now = now();
        $lastMonth = $now->copy()->subMonth();

        $result = Invoice::query()
            ->selectRaw('
                COALESCE(SUM(CASE WHEN MONTH(invoice_date) = ? AND YEAR(invoice_date) = ? THEN total_amount ELSE 0 END), 0) as this_month,
                COALESCE(SUM(CASE WHEN MONTH(invoice_date) = ? AND YEAR(invoice_date) = ? THEN total_amount ELSE 0 END), 0) as last_month
            ', [
                $now->month,
                $now->year,
                $lastMonth->month,
                $lastMonth->year,
            ])
            ->first();

        return [
            'this_month' => (float) $result->this_month,
            'last_month' => (float) $result->last_month,
        ];
    }

    /**
     * Get Amazon pending count (replaces Blade inline query).
     */
    public function getAmazonPendingCount(): int
    {
        return InvoiceUploadFile::where('status', 'amazon_pending')
            ->orWhere(function ($query) {
                $query->where('supplier_detected', 'Amazon')
                    ->whereIn('status', ['review', 'parsed']);
            })
            ->count();
    }
}
