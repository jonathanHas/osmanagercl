<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CashReconciliationPayment extends Model
{
    use HasUuids;

    protected $fillable = [
        'cash_reconciliation_id',
        'supplier_id',
        'payee_name',
        'amount',
        'sequence',
        'description',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'sequence' => 'integer',
    ];

    /**
     * Get the reconciliation this payment belongs to
     */
    public function reconciliation(): BelongsTo
    {
        return $this->belongsTo(CashReconciliation::class, 'cash_reconciliation_id');
    }

    /**
     * Get the supplier from POS database
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'supplier_id', 'SupplierID');
    }

    /**
     * Get display name for the payee
     */
    public function getPayeeDisplayNameAttribute(): string
    {
        if ($this->supplier) {
            return $this->supplier->Supplier;
        }

        return $this->payee_name ?? 'Unknown';
    }
}
