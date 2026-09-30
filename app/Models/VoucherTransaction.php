<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class VoucherTransaction extends Model
{
    /**
     * Transaction type constants.
     */
    const TYPE_ISSUE = 'issue';     // initial balance loaded on activation

    const TYPE_DEDUCT = 'deduct';   // amount redeemed at the till

    const TYPE_DEACTIVATE = 'deactivate'; // admin disabled the voucher

    const TYPE_ACTIVATE = 'activate';     // admin re-enabled a disabled voucher

    const TYPE_DELETE = 'delete';         // admin soft-deleted the voucher (admin tools)

    const TYPE_RESTORE = 'restore';       // admin restored a deleted voucher (admin tools)

    const TYPE_FOR_SALE = 'for_sale';     // a hand-activated voucher returned to unsold (admin tools)

    /**
     * Where a transaction came from.
     */
    const SOURCE_MANUAL = 'manual'; // a person on the office or shop screen

    const SOURCE_TILL = 'till';     // the uniCenta Voucher tender, via vouchers:sync-till

    protected $fillable = [
        'voucher_id',
        'type',
        'source',
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

    public function tillRedemption(): HasOne
    {
        return $this->hasOne(VoucherTillRedemption::class, 'voucher_transaction_id');
    }
}
