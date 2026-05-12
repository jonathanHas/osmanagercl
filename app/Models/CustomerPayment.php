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
    ];

    protected $casts = [
        'payment_date' => 'date',
        'amount' => 'decimal:2',
        'voided_at' => 'datetime',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function allocations(): HasMany
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

    public function getTotalAllocatedAttribute(): float
    {
        return round((float) $this->allocations->sum('amount'), 2);
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
}
