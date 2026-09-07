<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CustomerPayment extends Model
{
    use HasFactory;

    public const METHOD_CARD_TILL = 'card_till';

    public const METHOD_CASH_TILL = 'cash_till';

    public const METHOD_ONLINE = 'online';

    public const METHODS = [
        self::METHOD_CARD_TILL => 'Card (Till)',
        self::METHOD_CASH_TILL => 'Cash (Till)',
        self::METHOD_ONLINE => 'Online (Bank)',
    ];

    protected $fillable = [
        'customer_id',
        'payment_date',
        'amount',
        'method',
        'till_id',
        'till_name',
        'reference',
        'notes',
        'created_by',
        'voided_at',
        'voided_by',
        'last_matched_at',
        'last_matched_by',
    ];

    protected $casts = [
        'payment_date' => 'date',
        'amount' => 'decimal:2',
        'voided_at' => 'datetime',
        'last_matched_at' => 'datetime',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * Allocations against live invoices only — rows pointing at a voided invoice
     * are excluded from the payment's allocated total, so voiding an invoice
     * returns its money to on-account credit instead of swallowing it.
     *
     * The exact mirror of CustomerInvoice::allocations(), which excludes rows
     * belonging to voided payments. Use allAllocations() when you need every row
     * the payment owns regardless of the invoice's state.
     */
    public function allocations(): HasMany
    {
        return $this->hasMany(CustomerPaymentAllocation::class)
            ->whereHas('invoice', fn ($q) => $q->where('status', '!=', CustomerInvoice::STATUS_VOID));
    }

    /**
     * Every allocation row this payment owns, void invoices included. For
     * display (so nothing is silently hidden) and for replacing the set wholesale.
     */
    public function allAllocations(): HasMany
    {
        return $this->hasMany(CustomerPaymentAllocation::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function voider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'voided_by');
    }

    public function lastMatcher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'last_matched_by');
    }

    public function isVoid(): bool
    {
        return $this->voided_at !== null;
    }

    public function methodLabel(): string
    {
        return self::METHODS[$this->method] ?? $this->method;
    }

    public function isTillPayment(): bool
    {
        return in_array($this->method, [self::METHOD_CARD_TILL, self::METHOD_CASH_TILL], true);
    }

    /** Allocated to live invoices. Excludes rows against voided invoices. */
    public function getTotalAllocatedAttribute(): float
    {
        return round((float) $this->allocations->sum('amount'), 2);
    }

    /**
     * Allocated to invoices that have since been voided — money that has come
     * back to on-account credit. Shown on the payment page so the allocation
     * list still explains its own total.
     */
    public function getVoidedAllocatedAttribute(): float
    {
        return round((float) $this->allAllocations->sum('amount') - $this->total_allocated, 2);
    }

    public function getUnallocatedAmountAttribute(): float
    {
        return round((float) $this->amount - $this->getTotalAllocatedAttribute(), 2);
    }

    public function isFullyAllocated(): bool
    {
        return abs($this->unallocated_amount) < 0.01;
    }

    public function scopeNotVoid(Builder $query): Builder
    {
        return $query->whereNull('voided_at');
    }

    /**
     * Payments still carrying at least $min of unapplied credit — money received
     * but not yet matched to any invoice.
     *
     * Filtered in SQL so the payments index can page over the result rather than
     * loading every payment and its allocations to sift them in PHP.
     */
    public function scopeWithUnallocatedOver(Builder $query, float $min = 0.005): Builder
    {
        $allocated = CustomerPaymentAllocation::query()
            ->selectRaw('COALESCE(SUM(amount), 0)')
            ->whereColumn('customer_payment_allocations.customer_payment_id', 'customer_payments.id')
            // Mirrors the allocations() relation — a voided invoice's row is not
            // allocated money, so this scope must agree with unallocated_amount.
            ->whereHas('invoice', fn ($q) => $q->where('status', '!=', CustomerInvoice::STATUS_VOID));

        // The threshold is inlined rather than bound: SQLite binds a PHP float as
        // text, and text compares greater than every number, so a bound `> ?`
        // silently matched nothing. %F is locale-independent and $min is ours.
        return $query->whereRaw(
            sprintf('(customer_payments.amount - (%s)) > %F', $allocated->toSql(), $min),
            $allocated->getBindings()
        );
    }

    /**
     * Payments with at least one allocation but still holding credit back.
     */
    public function scopePartiallyAllocated(Builder $query): Builder
    {
        return $query->withUnallocatedOver()->has('allocations');
    }
}
