<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CashLodgementMatch extends Model
{
    use HasUuids;

    protected $fillable = [
        'cash_lodgement_id',
        'cash_reconciliation_id',
        'pos_date',
        'matched_amount',
        'match_type',
        'confidence_score',
        'match_notes',
        'cash_taken',
        'float_retained',
        'supplier_payments',
        'available_to_lodge',
        'matched_by',
        'matched_at',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'pos_date' => 'date',
        'matched_amount' => 'decimal:2',
        'cash_taken' => 'decimal:2',
        'float_retained' => 'decimal:2',
        'supplier_payments' => 'decimal:2',
        'available_to_lodge' => 'decimal:2',
        'matched_at' => 'datetime',
    ];

    /**
     * Boot method to auto-calculate available to lodge
     */
    protected static function boot()
    {
        parent::boot();

        static::saving(function ($match) {
            // Calculate available to lodge if not set
            if ($match->cash_taken > 0 && $match->available_to_lodge == 0) {
                $match->available_to_lodge = $match->cash_taken - $match->float_retained - $match->supplier_payments;
            }
        });
    }

    /**
     * Get the cash lodgement
     */
    public function cashLodgement(): BelongsTo
    {
        return $this->belongsTo(CashLodgement::class);
    }

    /**
     * Get the cash reconciliation
     */
    public function cashReconciliation(): BelongsTo
    {
        return $this->belongsTo(CashReconciliation::class);
    }

    /**
     * Get the user who created the match
     */
    public function matcher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'matched_by');
    }

    /**
     * Get the user who created this record
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the user who last updated this record
     */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Scope for manual matches
     */
    public function scopeManual($query)
    {
        return $query->where('match_type', 'manual');
    }

    /**
     * Scope for automatic matches
     */
    public function scopeAutomatic($query)
    {
        return $query->whereIn('match_type', ['exact', 'partial', 'accumulated']);
    }

    /**
     * Scope for high confidence matches
     */
    public function scopeHighConfidence($query)
    {
        return $query->where('confidence_score', '>=', 80);
    }

    /**
     * Get variance between cash taken and available to lodge
     */
    public function getVarianceAttribute(): float
    {
        return $this->cash_taken - $this->available_to_lodge;
    }

    /**
     * Get variance percentage
     */
    public function getVariancePercentageAttribute(): float
    {
        if ($this->cash_taken == 0) {
            return 0;
        }

        return (abs($this->variance) / $this->cash_taken) * 100;
    }

    /**
     * Check if this is a perfect match (no variance)
     */
    public function getIsPerfectMatchAttribute(): bool
    {
        return abs($this->matched_amount - $this->available_to_lodge) < 0.01;
    }

    /**
     * Get match quality description
     */
    public function getMatchQualityAttribute(): string
    {
        if ($this->confidence_score >= 90) {
            return 'Excellent';
        } elseif ($this->confidence_score >= 75) {
            return 'Good';
        } elseif ($this->confidence_score >= 60) {
            return 'Fair';
        } else {
            return 'Poor';
        }
    }

    /**
     * Get float percentage of cash taken
     */
    public function getFloatPercentageAttribute(): float
    {
        if ($this->cash_taken == 0) {
            return 0;
        }

        return ($this->float_retained / $this->cash_taken) * 100;
    }
}
