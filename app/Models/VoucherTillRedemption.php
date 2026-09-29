<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One voucher line seen on a uniCenta ticket, and what the till sync did with it.
 *
 * Anything other than `applied` is an exception for a manager to review.
 */
class VoucherTillRedemption extends Model
{
    const STATUS_APPLIED = 'applied';     // tender deducted in full

    const STATUS_PARTIAL = 'partial';     // tender exceeded the balance; deducted to zero

    const STATUS_NO_TENDER = 'no_tender'; // voucher scanned but not paid with the Voucher tender

    const STATUS_INACTIVE = 'inactive';   // voucher not active (or no balance); nothing deducted

    const STATUS_UNKNOWN = 'unknown';     // product in the voucher category with no voucher row

    const STATUS_REFUND = 'refund';       // refund ticket; recorded, not reversed

    const STATUS_ACTIVATED = 'activated'; // the till sale activated the voucher (not an exception)

    const STATUS_SALE_FLAGGED = 'sale_flagged'; // a voucher sale that activated nothing

    const EXCEPTION_STATUSES = [
        self::STATUS_PARTIAL,
        self::STATUS_NO_TENDER,
        self::STATUS_INACTIVE,
        self::STATUS_UNKNOWN,
        self::STATUS_REFUND,
        self::STATUS_SALE_FLAGGED,
    ];

    protected $fillable = [
        'pos_ticket_id',
        'pos_product_id',
        'voucher_code',
        'ticket_number',
        'ticket_type',
        'sold_at',
        'voucher_tender',
        'ticket_total',
        'sale_amount',
        'amount_deducted',
        'shortfall',
        'status',
        'voucher_id',
        'voucher_transaction_id',
        'note',
        'reviewed_at',
        'reviewed_by',
    ];

    protected $casts = [
        'sold_at' => 'datetime',
        'reviewed_at' => 'datetime',
        'voucher_tender' => 'decimal:2',
        'ticket_total' => 'decimal:2',
        'sale_amount' => 'decimal:2',
        'amount_deducted' => 'decimal:2',
        'shortfall' => 'decimal:2',
    ];

    public function voucher(): BelongsTo
    {
        return $this->belongsTo(Voucher::class);
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(VoucherTransaction::class, 'voucher_transaction_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function scopeExceptions(Builder $query): Builder
    {
        return $query->whereIn('status', self::EXCEPTION_STATUSES);
    }

    public function scopeUnreviewed(Builder $query): Builder
    {
        return $query->whereNull('reviewed_at');
    }
}
