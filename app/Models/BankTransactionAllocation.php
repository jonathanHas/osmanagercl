<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BankTransactionAllocation extends Model
{
    use HasFactory, HasUuids;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'bank_transaction_id',
        'invoice_id',
        'allocated_amount',
        'allocation_type',
        'notes',
        'created_by',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'allocated_amount' => 'decimal:2',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Allocation types
     */
    const TYPE_STANDARD = 'standard';

    const TYPE_PARTIAL = 'partial';

    const TYPE_OVERPAYMENT = 'overpayment';

    const TYPE_DISCOUNT = 'discount';

    const TYPE_FEE = 'fee';

    const TYPE_MIGRATED = 'migrated';

    /**
     * Get the bank transaction for this allocation.
     */
    public function bankTransaction()
    {
        return $this->belongsTo(BankTransaction::class);
    }

    /**
     * Get the invoice for this allocation.
     */
    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }

    /**
     * Get the user who created this allocation.
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Scope to get allocations for a specific transaction
     */
    public function scopeForTransaction($query, $transactionId)
    {
        return $query->where('bank_transaction_id', $transactionId);
    }

    /**
     * Scope to get allocations for a specific invoice
     */
    public function scopeForInvoice($query, $invoiceId)
    {
        return $query->where('invoice_id', $invoiceId);
    }

    /**
     * Check if this is a partial allocation
     */
    public function isPartial()
    {
        return $this->allocation_type === self::TYPE_PARTIAL;
    }

    /**
     * Check if this is an overpayment
     */
    public function isOverpayment()
    {
        return $this->allocation_type === self::TYPE_OVERPAYMENT;
    }

    /**
     * Get formatted allocation type
     */
    public function getFormattedTypeAttribute()
    {
        $types = [
            self::TYPE_STANDARD => 'Standard Payment',
            self::TYPE_PARTIAL => 'Partial Payment',
            self::TYPE_OVERPAYMENT => 'Overpayment',
            self::TYPE_DISCOUNT => 'Early Payment Discount',
            self::TYPE_FEE => 'Bank Fee Adjustment',
            self::TYPE_MIGRATED => 'Migrated from Legacy',
        ];

        return $types[$this->allocation_type] ?? ucfirst($this->allocation_type);
    }
}
