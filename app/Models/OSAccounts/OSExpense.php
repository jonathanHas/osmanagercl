<?php

namespace App\Models\OSAccounts;

use Illuminate\Database\Eloquent\Model;

/**
 * OSAccounts EXPENSES table model
 * Represents suppliers/vendors from the legacy OSAccounts system
 */
class OSExpense extends Model
{
    protected $connection = 'osaccounts';

    protected $table = 'EXPENSES';

    protected $primaryKey = 'ID';

    protected $keyType = 'string';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = [
        'ID',
        'Name',
        'Supplier_Type_ID',
    ];

    protected $casts = [
        'Supplier_Type_ID' => 'integer',
    ];

    /**
     * Get the supplier type for this expense/supplier
     */
    public function supplierType()
    {
        return $this->belongsTo(OSSupplierType::class, 'Supplier_Type_ID', 'Supplier_Type_ID');
    }

    /**
     * Get all invoices for this supplier
     */
    public function invoices()
    {
        return $this->hasMany(OSInvoice::class, 'SupplierID', 'ID');
    }

    /**
     * Get invoice count for this supplier
     */
    public function getInvoiceCountAttribute()
    {
        return $this->invoices()->count();
    }

    /**
     * Get total invoice amount for this supplier
     */
    public function getTotalInvoiceAmountAttribute()
    {
        return $this->invoices()
            ->with('details')
            ->get()
            ->sum(function ($invoice) {
                return $invoice->details->sum('Amount');
            });
    }

    /**
     * Scope to get suppliers with invoices
     */
    public function scopeWithInvoices($query)
    {
        return $query->whereHas('invoices');
    }

    /**
     * Scope to get suppliers by type
     */
    public function scopeByType($query, $typeId)
    {
        return $query->where('Supplier_Type_ID', $typeId);
    }
}
