<?php

namespace App\Models\OSAccounts;

use Illuminate\Database\Eloquent\Model;

/**
 * OSAccounts INVOICES_UNPAID table model
 * Represents unpaid invoices in the legacy OSAccounts system
 */
class OSInvoiceUnpaid extends Model
{
    protected $connection = 'osaccounts';
    protected $table = 'INVOICES_UNPAID';
    protected $primaryKey = 'ID';
    protected $keyType = 'string';
    public $incrementing = false;
    public $timestamps = false;

    protected $fillable = [
        'ID',
        'InvoiceID',
    ];

    /**
     * Get the invoice this unpaid record refers to
     */
    public function invoice()
    {
        return $this->belongsTo(OSInvoice::class, 'InvoiceID', 'ID');
    }
}