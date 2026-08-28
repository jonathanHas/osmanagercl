<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * Pre-aggregated daily stock transfers to internal departments (Kitchen, Coffee).
 *
 * These are internal movements, not sales to customers, so they are excluded from
 * VAT returns and from headline revenue on the sales review. Populated alongside
 * sales_accounting_daily by SalesAccountingImportService.
 */
class StockTransferDaily extends Model
{
    protected $table = 'stock_transfer_daily';

    protected $fillable = [
        'transfer_date', 'department', 'vat_rate',
        'net_amount', 'vat_amount', 'gross_amount', 'transaction_count',
    ];

    protected $casts = [
        'transfer_date' => 'date',
        'vat_rate' => 'decimal:4',
        'net_amount' => 'decimal:2',
        'vat_amount' => 'decimal:2',
        'gross_amount' => 'decimal:2',
    ];

    public function scopeForDateRange($query, Carbon $startDate, Carbon $endDate)
    {
        return $query->whereBetween('transfer_date', [
            $startDate->format('Y-m-d'),
            $endDate->format('Y-m-d'),
        ]);
    }
}
