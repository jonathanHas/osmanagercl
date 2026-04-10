<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CashBagVerification extends Model
{
    use HasUuids;

    protected $fillable = [
        'cash_reconciliation_id',
        'cash_50',
        'cash_20',
        'cash_10',
        'cash_5',
        'cash_2',
        'cash_1',
        'cash_50c',
        'cash_20c',
        'cash_10c',
        'counted_total',
        'expected_total',
        'variance',
        'cash_lodgement_id',
        'verified_by',
        'verified_at',
    ];

    protected $casts = [
        'cash_50' => 'integer',
        'cash_20' => 'integer',
        'cash_10' => 'integer',
        'cash_5' => 'integer',
        'cash_2' => 'integer',
        'cash_1' => 'integer',
        'cash_50c' => 'integer',
        'cash_20c' => 'integer',
        'cash_10c' => 'integer',
        'counted_total' => 'decimal:2',
        'expected_total' => 'decimal:2',
        'variance' => 'decimal:2',
        'verified_at' => 'datetime',
    ];

    public function calculateTotal(): float
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

    public function reconciliation(): BelongsTo
    {
        return $this->belongsTo(CashReconciliation::class, 'cash_reconciliation_id');
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function lodgement(): BelongsTo
    {
        return $this->belongsTo(CashLodgement::class, 'cash_lodgement_id');
    }

    public function getIsLodgedAttribute(): bool
    {
        return $this->cash_lodgement_id !== null;
    }
}
