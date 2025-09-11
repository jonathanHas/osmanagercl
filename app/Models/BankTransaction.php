<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BankTransaction extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'transaction_date',
        'description',
        'debit_amount',
        'credit_amount',
        'balance',
        'source_filename',
        'status',
        'reconciliation_type',
        'reconciliation_id',
        'reconciliation_model',
        'credit_category',
        'reconciled_at',
        'notes',
        'user_id',
    ];

    protected $casts = [
        'transaction_date' => 'date',
        'debit_amount' => 'decimal:2',
        'credit_amount' => 'decimal:2',
        'balance' => 'decimal:2',
        'reconciled_at' => 'datetime',
    ];

    /**
     * Get the user who reconciled this transaction
     */
    public function user()
    {
        return $this->belongsTo(\App\Models\User::class);
    }

    /**
     * Get the related model (invoice, expense, etc.) for this reconciliation
     */
    public function reconciliationModel()
    {
        return $this->morphTo('reconciliation', 'reconciliation_model', 'reconciliation_id');
    }

    /**
     * Get all allocations for this transaction
     */
    public function allocations()
    {
        return $this->hasMany(BankTransactionAllocation::class);
    }

    /**
     * Get all invoices linked through allocations
     */
    public function invoices()
    {
        return $this->belongsToMany(Invoice::class, 'bank_transaction_allocations')
            ->withPivot('allocated_amount', 'allocation_type', 'notes', 'created_by')
            ->withTimestamps();
    }

    /**
     * Get the total amount allocated across all invoices
     */
    public function getTotalAllocatedAttribute()
    {
        return $this->allocations->sum('allocated_amount');
    }

    /**
     * Get the remaining unallocated amount
     */
    public function getRemainingAmountAttribute()
    {
        $transactionAmount = $this->debit_amount ?: $this->credit_amount;

        return $transactionAmount - $this->total_allocated;
    }

    /**
     * Check if transaction is fully allocated
     */
    public function isFullyAllocated()
    {
        return abs($this->remaining_amount) < 0.01;
    }

    /**
     * Check if transaction is partially allocated
     */
    public function isPartiallyAllocated()
    {
        return $this->total_allocated > 0 && ! $this->isFullyAllocated();
    }

    /**
     * Check if transaction is over-allocated
     */
    public function isOverAllocated()
    {
        return $this->remaining_amount < -0.01;
    }

    /**
     * Scope to get pending transactions
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope to get matched transactions
     */
    public function scopeMatched($query)
    {
        return $query->where('status', 'matched');
    }

    /**
     * Scope to get ignored transactions
     */
    public function scopeIgnored($query)
    {
        return $query->where('status', 'ignored');
    }

    /**
     * Scope to get unreconciled transactions
     */
    public function scopeUnreconciled($query)
    {
        return $query->whereIn('status', ['pending', null]);
    }

    /**
     * Get the transaction amount (debit or credit)
     */
    public function getAmountAttribute()
    {
        return $this->debit_amount > 0 ? $this->debit_amount : $this->credit_amount;
    }

    /**
     * Get the transaction type (expense or income)
     */
    public function getTypeAttribute()
    {
        return $this->debit_amount > 0 ? 'expense' : 'income';
    }

    /**
     * Check if transaction is a credit (income)
     */
    public function isCreditTransaction()
    {
        return $this->credit_amount > 0;
    }

    /**
     * Get credit category options
     */
    public static function getCreditCategories()
    {
        return [
            'card_lodgement' => 'Card Lodgements',
            'cash_lodgement' => 'Cash Lodgements',
            'rent' => 'Rent',
            'other_credit' => 'Other Credits',
        ];
    }

    /**
     * Get credit category display name
     */
    public function getCreditCategoryDisplayAttribute()
    {
        $categories = self::getCreditCategories();

        return $categories[$this->credit_category] ?? 'Uncategorized';
    }

    /**
     * Scope to filter by credit category
     */
    public function scopeCreditCategory($query, $category)
    {
        return $query->where('credit_category', $category);
    }

    /**
     * Scope to get only credit transactions
     */
    public function scopeCreditsOnly($query)
    {
        return $query->where('credit_amount', '>', 0);
    }
}
