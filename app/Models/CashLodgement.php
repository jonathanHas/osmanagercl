<?php

namespace App\Models;

use App\Models\POS\ClosedCash;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CashLodgement extends Model
{
    use HasUuids;

    protected $fillable = [
        'money_id',
        'lodgement_date',
        'cash_amount',
        'cheque_amount',
        'total_amount',
        'till_name',
        'till_id',
        'lodgement_type',
        'notes',
        'imported_from_legacy',
        'original_lodge_date',
        'is_matched',
        'bank_transaction_id',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'lodgement_date' => 'date',
        'cash_amount' => 'decimal:2',
        'cheque_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'imported_from_legacy' => 'boolean',
        'is_matched' => 'boolean',
        'original_lodge_date' => 'datetime',
    ];

    /**
     * Boot method to auto-calculate total amount
     */
    protected static function boot()
    {
        parent::boot();

        static::saving(function ($lodgement) {
            $lodgement->total_amount = $lodgement->cash_amount + $lodgement->cheque_amount;

            // Auto-set lodgement type
            if ($lodgement->cash_amount > 0 && $lodgement->cheque_amount > 0) {
                $lodgement->lodgement_type = 'mixed';
            } elseif ($lodgement->cheque_amount > 0) {
                $lodgement->lodgement_type = 'cheque_only';
            } else {
                $lodgement->lodgement_type = 'cash_only';
            }
        });
    }

    /**
     * Get the closed cash record from POS
     */
    public function closedCash(): BelongsTo
    {
        return $this->belongsTo(ClosedCash::class, 'money_id', 'MONEY');
    }

    /**
     * Get the matches for this lodgement
     */
    public function matches(): HasMany
    {
        return $this->hasMany(CashLodgementMatch::class)->orderBy('pos_date');
    }

    /**
     * Get the bank transaction if matched
     */
    public function bankTransaction(): BelongsTo
    {
        return $this->belongsTo(BankTransaction::class, 'bank_transaction_id');
    }

    /**
     * Get the user who created this lodgement
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the user who last updated this lodgement
     */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Scope for unmatched lodgements
     */
    public function scopeUnmatched($query)
    {
        return $query->where('is_matched', false);
    }

    /**
     * Scope for matched lodgements
     */
    public function scopeMatched($query)
    {
        return $query->where('is_matched', true);
    }

    /**
     * Scope for cash lodgements only
     */
    public function scopeCashOnly($query)
    {
        return $query->where('lodgement_type', 'cash_only');
    }

    /**
     * Scope for cheque lodgements only
     */
    public function scopeChequeOnly($query)
    {
        return $query->where('lodgement_type', 'cheque_only');
    }

    /**
     * Scope for lodgements from legacy system
     */
    public function scopeFromLegacy($query)
    {
        return $query->where('imported_from_legacy', true);
    }

    /**
     * Get the total matched amount
     */
    public function getTotalMatchedAmountAttribute(): float
    {
        return $this->matches->sum('matched_amount');
    }

    /**
     * Get the remaining unmatched amount
     */
    public function getRemainingAmountAttribute(): float
    {
        return $this->total_amount - $this->total_matched_amount;
    }

    /**
     * Check if fully matched
     */
    public function getIsFullyMatchedAttribute(): bool
    {
        return abs($this->remaining_amount) < 0.01;
    }
}
