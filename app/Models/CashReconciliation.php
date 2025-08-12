<?php

namespace App\Models;

use App\Models\POS\ClosedCash;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class CashReconciliation extends Model
{
    use HasUuids;

    protected $fillable = [
        'closed_cash_id',
        'date',
        'till_name',
        'till_id',
        'cash_50',
        'cash_20',
        'cash_10',
        'cash_5',
        'cash_2',
        'cash_1',
        'cash_50c',
        'cash_20c',
        'cash_10c',
        'note_float',
        'coin_float',
        'card',
        'cash_back',
        'cheque',
        'debt',
        'debt_paid_cash',
        'debt_paid_cheque',
        'debt_paid_card',
        'free',
        'voucher_used',
        'money_added',
        'total_cash_counted',
        'pos_cash_total',
        'pos_card_total',
        'variance',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'date' => 'date',
        'cash_50' => 'integer',
        'cash_20' => 'integer',
        'cash_10' => 'integer',
        'cash_5' => 'integer',
        'cash_2' => 'integer',
        'cash_1' => 'integer',
        'cash_50c' => 'integer',
        'cash_20c' => 'integer',
        'cash_10c' => 'integer',
        'note_float' => 'decimal:2',
        'coin_float' => 'decimal:2',
        'card' => 'decimal:2',
        'cash_back' => 'decimal:2',
        'cheque' => 'decimal:2',
        'debt' => 'decimal:2',
        'debt_paid_cash' => 'decimal:2',
        'debt_paid_cheque' => 'decimal:2',
        'debt_paid_card' => 'decimal:2',
        'free' => 'decimal:2',
        'voucher_used' => 'decimal:2',
        'money_added' => 'decimal:2',
        'total_cash_counted' => 'decimal:2',
        'pos_cash_total' => 'decimal:2',
        'pos_card_total' => 'decimal:2',
        'variance' => 'decimal:2',
    ];

    /**
     * Calculate total cash from denominations
     */
    public function calculateTotalCash(): float
    {
        return ($this->cash_50 * 50) +
               ($this->cash_20 * 20) +
               ($this->cash_10 * 10) +
               ($this->cash_5 * 5) +
               ($this->cash_2 * 2) +
               ($this->cash_1 * 1) +
               ($this->cash_50c * 0.50) +
               ($this->cash_20c * 0.20) +
               ($this->cash_10c * 0.10);
    }

    /**
     * Calculate total coins
     */
    public function calculateTotalCoins(): float
    {
        return ($this->cash_2 * 2) +
               ($this->cash_1 * 1) +
               ($this->cash_50c * 0.50) +
               ($this->cash_20c * 0.20) +
               ($this->cash_10c * 0.10);
    }

    /**
     * Calculate total notes
     */
    public function calculateTotalNotes(): float
    {
        return ($this->cash_50 * 50) +
               ($this->cash_20 * 20) +
               ($this->cash_10 * 10) +
               ($this->cash_5 * 5);
    }

    /**
     * Get the closed cash record from POS
     */
    public function closedCash(): BelongsTo
    {
        return $this->belongsTo(ClosedCash::class, 'closed_cash_id', 'MONEY');
    }

    /**
     * Get payments for this reconciliation
     */
    public function payments(): HasMany
    {
        return $this->hasMany(CashReconciliationPayment::class)->orderBy('sequence');
    }

    /**
     * Get notes for this reconciliation
     */
    public function notes(): HasMany
    {
        return $this->hasMany(CashReconciliationNote::class);
    }

    /**
     * Get the latest note
     */
    public function latestNote(): HasOne
    {
        return $this->hasOne(CashReconciliationNote::class)->latestOfMany();
    }

    /**
     * Get the user who created this reconciliation
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the user who last updated this reconciliation
     */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Scope for filtering by date
     */
    public function scopeForDate($query, $date)
    {
        return $query->whereDate('date', $date);
    }

    /**
     * Scope for filtering by till
     */
    public function scopeForTill($query, $tillId)
    {
        return $query->where('till_id', $tillId);
    }
}
