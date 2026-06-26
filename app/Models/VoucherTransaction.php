<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VoucherTransaction extends Model
{
    /**
     * Transaction type constants.
     */
    const TYPE_ISSUE = 'issue';     // initial balance loaded on activation

    const TYPE_DEDUCT = 'deduct';   // amount redeemed at the till

    const TYPE_DEACTIVATE = 'deactivate'; // admin disabled the voucher

    const TYPE_ACTIVATE = 'activate';     // admin re-enabled a disabled voucher

    protected $fillable = [
        'voucher_id',
        'type',
        'amount',
        'balance_after',
        'note',
        'user_id',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'balance_after' => 'decimal:2',
    ];

    public function voucher(): BelongsTo
    {
        return $this->belongsTo(Voucher::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
