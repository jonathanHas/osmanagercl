<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TillReviewSummary extends Model
{
    protected $table = 'till_review_summaries';

    protected $fillable = [
        'summary_date',
        'total_sales',
        'total_transactions',
        'cash_total',
        'card_total',
        'other_total',
        'free_total',
        'debt_total',
        'vat_breakdown',
        'drawer_opens',
        'no_sales',
        'voided_items_total',
        'voided_items_count',
        'hourly_breakdown',
        'terminal_breakdown',
        'cashier_breakdown',
    ];

    protected $casts = [
        'summary_date' => 'date',
        'total_sales' => 'decimal:2',
        'cash_total' => 'decimal:2',
        'card_total' => 'decimal:2',
        'other_total' => 'decimal:2',
        'free_total' => 'decimal:2',
        'debt_total' => 'decimal:2',
        'voided_items_total' => 'decimal:2',
        'vat_breakdown' => 'array',
        'hourly_breakdown' => 'array',
        'terminal_breakdown' => 'array',
        'cashier_breakdown' => 'array',
    ];
}