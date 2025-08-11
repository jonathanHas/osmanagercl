<?php

namespace App\Models\OSAccounts;

use Illuminate\Database\Eloquent\Model;
use App\Models\OSAccounts\OSExpense;
use App\Models\OSAccounts\OSInvoiceDetail;
use App\Models\OSAccounts\OSInvoiceUnpaid;
use Carbon\Carbon;

/**
 * OSAccounts INVOICES table model
 * Represents invoice headers from the legacy OSAccounts system
 */
class OSInvoice extends Model
{
    protected $connection = 'osaccounts';
    protected $table = 'INVOICES';
    protected $primaryKey = 'ID';
    protected $keyType = 'string';
    public $incrementing = false;
    public $timestamps = false;

    protected $fillable = [
        'ID',
        'InvoiceNum',
        'SupplierID',
        'InvoiceDate',
        'DateUpload',
        'Assigned',
        'PaidDate',
        'InvoicePath',
        'Filename',
    ];

    protected $casts = [
        'InvoiceNum' => 'integer',
        'InvoiceDate' => 'date',
        'DateUpload' => 'datetime',
        'PaidDate' => 'date',
    ];

    /**
     * Get the supplier for this invoice
     */
    public function supplier()
    {
        return $this->belongsTo(OSExpense::class, 'SupplierID', 'ID');
    }

    /**
     * Get all invoice details (VAT breakdown)
     */
    public function details()
    {
        return $this->hasMany(OSInvoiceDetail::class, 'InvoiceID', 'ID');
    }

    /**
     * Get unpaid record if this invoice is still outstanding
     */
    public function unpaidRecord()
    {
        return $this->hasOne(OSInvoiceUnpaid::class, 'InvoiceID', 'ID');
    }

    /**
     * Get total invoice amount
     */
    public function getTotalAmountAttribute()
    {
        return $this->details->sum('Amount');
    }

    /**
     * Get payment status for Laravel system
     * Based on OSAccounts logic:
     * - If PaidDate exists: definitely paid (with known date)
     * - If in INVOICES_UNPAID table: still outstanding (pending/overdue)
     * - If NOT in INVOICES_UNPAID and no PaidDate: paid (date unknown)
     */
    public function getPaymentStatusAttribute()
    {
        // If has a specific paid date, it's definitely paid
        if ($this->PaidDate) {
            return 'paid';
        }
        
        // Check if this invoice is in the INVOICES_UNPAID table
        $isUnpaid = OSInvoiceUnpaid::where('InvoiceID', $this->ID)->exists();
        
        if ($isUnpaid) {
            // Invoice is in INVOICES_UNPAID - still outstanding
            // Determine if overdue based on invoice date
            if ($this->InvoiceDate && $this->InvoiceDate->addDays(30)->isPast()) {
                return 'overdue';
            }
            return 'pending';
        }
        
        // Not in INVOICES_UNPAID and no PaidDate = paid (date unknown)
        // This is the key logic the user clarified
        return 'paid';
    }

    /**
     * Get VAT breakdown for Laravel import
     */
    public function getVatBreakdownAttribute()
    {
        $breakdown = [
            'standard_net' => 0,
            'standard_vat' => 0,
            'reduced_net' => 0,
            'reduced_vat' => 0,
            'second_reduced_net' => 0,
            'second_reduced_vat' => 0,
            'zero_net' => 0,
            'zero_vat' => 0,
        ];

        foreach ($this->details as $detail) {
            $vatMapping = $detail->getLaravelVatMapping();
            if ($vatMapping) {
                $netField = $vatMapping['net_field'];
                $vatField = $vatMapping['vat_field'];
                
                $breakdown[$netField] += $vatMapping['net_amount'];
                $breakdown[$vatField] += $vatMapping['vat_amount'];
            }
        }

        return $breakdown;
    }

    /**
     * Get subtotal (total net amount)
     */
    public function getSubtotalAttribute()
    {
        $breakdown = $this->getVatBreakdownAttribute();
        return $breakdown['standard_net'] + $breakdown['reduced_net'] + 
               $breakdown['second_reduced_net'] + $breakdown['zero_net'];
    }

    /**
     * Get total VAT amount
     */
    public function getVatAmountAttribute()
    {
        $breakdown = $this->getVatBreakdownAttribute();
        return $breakdown['standard_vat'] + $breakdown['reduced_vat'] + 
               $breakdown['second_reduced_vat'] + $breakdown['zero_vat'];
    }

    /**
     * Scope to get paid invoices
     */
    public function scopePaid($query)
    {
        return $query->whereNotNull('PaidDate');
    }

    /**
     * Scope to get unpaid invoices
     */
    public function scopeUnpaid($query)
    {
        return $query->whereNull('PaidDate');
    }

    /**
     * Scope to get invoices by date range
     */
    public function scopeByDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('InvoiceDate', [$startDate, $endDate]);
    }

    /**
     * Scope to get invoices by supplier
     */
    public function scopeBySupplier($query, $supplierId)
    {
        return $query->where('SupplierID', $supplierId);
    }
}