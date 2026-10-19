<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A till product that carries a customer deposit of one tier (barrel code).
 *
 * Suggested from supplier documents, confirmed (or rejected) by the owner.
 * Only confirmed rows on a tier with charge_customer get deposit properties
 * on the till (DepositPosService).
 */
class ProductDeposit extends Model
{
    public const STATUS_SUGGESTED = 'suggested';

    public const STATUS_CONFIRMED = 'confirmed';

    public const STATUS_REJECTED = 'rejected';

    public const STATUSES = [self::STATUS_SUGGESTED, self::STATUS_CONFIRMED, self::STATUS_REJECTED];

    public const SOURCE_INVOICE = 'invoice';

    public const SOURCE_MANUAL = 'manual';

    public const PRICE_KEPT = 'kept';

    public const PRICE_LOWERED = 'lowered';

    public const PRICE_SET = 'set';

    public const PRICE_REVIEWS = [self::PRICE_KEPT, self::PRICE_LOWERED, self::PRICE_SET];

    protected $fillable = [
        'product_id',
        'product_code',
        'barrel_code_id',
        'status',
        'source',
        'sightings_units',
        'sightings_count',
        'conflicting_units',
        'last_seen_on',
        'confirmed_by',
        'confirmed_at',
        'pos_synced_at',
        'pos_sync_error',
        'notes',
        'price_review',
        'price_review_from',
        'price_reviewed_at',
        'price_reviewed_by',
    ];

    protected $casts = [
        'sightings_units' => 'integer',
        'sightings_count' => 'integer',
        'conflicting_units' => 'integer',
        'last_seen_on' => 'date',
        'confirmed_at' => 'datetime',
        'pos_synced_at' => 'datetime',
        'price_review_from' => 'decimal:4',
        'price_reviewed_at' => 'datetime',
    ];

    public function barrelCode(): BelongsTo
    {
        return $this->belongsTo(BarrelCode::class);
    }

    /** The till product (POS connection). */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id', 'ID');
    }

    public function confirmedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }

    public function priceReviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'price_reviewed_by');
    }

    public function scopeSuggested(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_SUGGESTED);
    }

    public function scopeConfirmed(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_CONFIRMED);
    }

    /**
     * Record the owner's decision on this row (not saved).
     */
    public function decide(string $status, ?User $by): self
    {
        $this->status = $status;
        $this->confirmed_by = $status === self::STATUS_CONFIRMED ? $by?->id : null;
        $this->confirmed_at = $status === self::STATUS_CONFIRMED ? now() : null;

        return $this;
    }

    /**
     * Record the owner's price review on this row (not saved). A null
     * outcome clears the review; $from is the gross price before a lowering
     * or a custom price.
     */
    public function reviewPrice(?string $outcome, ?User $by, ?float $from = null): self
    {
        $this->price_review = $outcome;
        $this->price_review_from = $outcome === null ? null : $from;
        $this->price_reviewed_at = $outcome === null ? null : now();
        $this->price_reviewed_by = $outcome === null ? null : $by?->id;

        return $this;
    }

    public function isPriceReviewed(): bool
    {
        return $this->price_review !== null;
    }

    public function isManual(): bool
    {
        return $this->source === self::SOURCE_MANUAL;
    }
}
