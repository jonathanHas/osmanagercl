<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CashReconciliationNote extends Model
{
    use HasUuids;

    protected $fillable = [
        'cash_reconciliation_id',
        'message',
        'created_by',
    ];

    /**
     * Get the reconciliation this note belongs to
     */
    public function reconciliation(): BelongsTo
    {
        return $this->belongsTo(CashReconciliation::class, 'cash_reconciliation_id');
    }

    /**
     * Get the user who created this note
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}