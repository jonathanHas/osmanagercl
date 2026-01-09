<?php

namespace App\Models;

use App\Models\POS\Payment;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CardTransaction extends Model
{
    use HasUuids;

    protected $fillable = [
        'upload_batch_id',
        'source_filename',
        'transaction_datetime',
        'terminal_id',
        'terminal_name',
        'transaction_type',
        'transaction_reference',
        'transaction_status',
        'payment_status',
        'card_masked',
        'amount',
        'currency',
        'settlement_datetime',
        'settlement_amount',
        'fee',
        'processor',
        'card_type',
        'auth_code',
        'reconciliation_status',
        'pos_payment_id',
        'confidence_score',
        'variance_amount',
        'notes',
    ];

    protected $casts = [
        'transaction_datetime' => 'datetime',
        'settlement_datetime' => 'datetime',
        'amount' => 'decimal:2',
        'settlement_amount' => 'decimal:2',
        'fee' => 'decimal:2',
        'variance_amount' => 'decimal:2',
        'confidence_score' => 'integer',
    ];

    public function posPayment(): BelongsTo
    {
        return $this->belongsTo(Payment::class, 'pos_payment_id', 'ID');
    }

    public function scopePending($query)
    {
        return $query->where('reconciliation_status', 'pending');
    }

    public function scopeMatched($query)
    {
        return $query->where('reconciliation_status', 'matched');
    }

    public function scopeMismatches($query)
    {
        return $query->where('reconciliation_status', 'mismatch');
    }

    public function scopeDeclined($query)
    {
        return $query->where('reconciliation_status', 'declined');
    }

    public function scopeOrphans($query)
    {
        return $query->where('reconciliation_status', 'orphan');
    }

    public function scopeForBatch($query, string $batchId)
    {
        return $query->where('upload_batch_id', $batchId);
    }

    public function isApproved(): bool
    {
        return strtolower($this->transaction_status) === 'approved';
    }

    public function isDeclined(): bool
    {
        return strtolower($this->transaction_status) !== 'approved';
    }

    public function hasDiscrepancy(): bool
    {
        return in_array($this->reconciliation_status, ['mismatch', 'declined', 'orphan']);
    }
}
