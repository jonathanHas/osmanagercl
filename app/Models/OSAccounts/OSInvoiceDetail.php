<?php

namespace App\Models\OSAccounts;

use Illuminate\Database\Eloquent\Model;
use App\Models\OSAccounts\OSInvoice;

/**
 * OSAccounts INVOICE_DETAIL table model
 * Represents invoice line items with VAT breakdown from the legacy OSAccounts system
 */
class OSInvoiceDetail extends Model
{
    protected $connection = 'osaccounts';
    protected $table = 'INVOICE_DETAIL';
    protected $primaryKey = 'ID';
    protected $keyType = 'string';
    public $incrementing = false;
    public $timestamps = false;

    protected $fillable = [
        'ID',
        'InvoiceID',
        'Amount',
        'VatID',
    ];

    protected $casts = [
        'Amount' => 'decimal:2',
    ];

    /**
     * Get the invoice for this detail
     */
    public function invoice()
    {
        return $this->belongsTo(OSInvoice::class, 'InvoiceID', 'ID');
    }

    /**
     * Map OSAccounts VAT ID to Irish VAT rates and calculate net/VAT amounts
     * OSAccounts amounts are EXCLUSIVE of VAT (ex-VAT), so we need to add VAT
     */
    public function getLaravelVatMapping()
    {
        $netAmount = (float) $this->Amount; // OSAccounts stores ex-VAT amounts
        
        switch ($this->VatID) {
            case '000': // Zero rate (0%)
                return [
                    'net_field' => 'zero_net',
                    'vat_field' => 'zero_vat',
                    'rate' => 0.00,
                    'net_amount' => $netAmount,
                    'vat_amount' => 0.00,
                ];
                
            case '001': // Reduced rate (13.5%) - less common, hospitality/food
                $vatAmount = round($netAmount * 0.135, 2);
                return [
                    'net_field' => 'reduced_net',
                    'vat_field' => 'reduced_vat',
                    'rate' => 0.135,
                    'net_amount' => $netAmount,
                    'vat_amount' => $vatAmount,
                ];
                
            case '002': // Standard rate (23%) - most common business expenses
                $vatAmount = round($netAmount * 0.23, 2);
                return [
                    'net_field' => 'standard_net',
                    'vat_field' => 'standard_vat',
                    'rate' => 0.23,
                    'net_amount' => $netAmount,
                    'vat_amount' => $vatAmount,
                ];
                
            case '003': // Second reduced rate (9%) - rare, specific sectors
                $vatAmount = round($netAmount * 0.09, 2);
                return [
                    'net_field' => 'second_reduced_net',
                    'vat_field' => 'second_reduced_vat',
                    'rate' => 0.09,
                    'net_amount' => $netAmount,
                    'vat_amount' => $vatAmount,
                ];
                
            default:
                // Unknown VAT ID - log and treat as zero rate
                \Log::warning('Unknown VAT ID in OSAccounts invoice detail', [
                    'invoice_detail_id' => $this->ID,
                    'invoice_id' => $this->InvoiceID,
                    'vat_id' => $this->VatID,
                    'amount' => $netAmount,
                ]);
                
                return [
                    'net_field' => 'zero_net',
                    'vat_field' => 'zero_vat',
                    'rate' => 0.00,
                    'net_amount' => $netAmount,
                    'vat_amount' => 0.00,
                ];
        }
    }

    /**
     * Get the net amount for this detail line
     */
    public function getNetAmountAttribute()
    {
        $mapping = $this->getLaravelVatMapping();
        return $mapping['net_amount'];
    }

    /**
     * Get the VAT amount for this detail line
     */
    public function getVatAmountAttribute()
    {
        $mapping = $this->getLaravelVatMapping();
        return $mapping['vat_amount'];
    }

    /**
     * Get the VAT rate for this detail line
     */
    public function getVatRateAttribute()
    {
        $mapping = $this->getLaravelVatMapping();
        return $mapping['rate'];
    }

    /**
     * Scope to filter by VAT ID
     */
    public function scopeByVatId($query, $vatId)
    {
        return $query->where('VatID', $vatId);
    }

    /**
     * Scope to get zero rate details
     */
    public function scopeZeroRate($query)
    {
        return $query->where('VatID', '000');
    }

    /**
     * Scope to get standard rate details
     */
    public function scopeStandardRate($query)
    {
        return $query->where('VatID', '001');
    }

    /**
     * Scope to get reduced rate details
     */
    public function scopeReducedRate($query)
    {
        return $query->where('VatID', '002');
    }

    /**
     * Scope to get second reduced rate details
     */
    public function scopeSecondReducedRate($query)
    {
        return $query->where('VatID', '003');
    }
}