<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TillReviewCache extends Model
{
    protected $table = 'till_review_cache';

    protected $fillable = [
        'transaction_date',
        'transaction_time',
        'transaction_type',
        'transaction_data',
        'receipt_id',
        'ticket_id',
        'terminal',
        'cashier',
        'amount',
        'cached_at',
    ];

    protected $casts = [
        'transaction_date' => 'date',
        'transaction_time' => 'datetime',
        'transaction_data' => 'array',
        'amount' => 'decimal:2',
        'cached_at' => 'datetime',
    ];
}