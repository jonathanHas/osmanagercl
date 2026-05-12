<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Customer extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'email',
        'phone',
        'address_line1',
        'address_line2',
        'city',
        'postcode',
        'country',
        'vat_number',
        'default_discount_percent',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'default_discount_percent' => 'decimal:2',
    ];

    public function invoices(): HasMany
    {
        return $this->hasMany(CustomerInvoice::class);
    }

    /**
     * All non-void payments. Voided payments are excluded from balance maths.
     */
    public function payments(): HasMany
    {
        return $this->hasMany(CustomerPayment::class)->whereNull('voided_at');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Sum of non-void invoice totals.
     */
    public function getTotalInvoicedAttribute(): float
    {
        return round((float) $this->invoices()
            ->where('status', '!=', CustomerInvoice::STATUS_VOID)
            ->sum('total'), 2);
    }

    /**
     * Sum of non-void payment amounts.
     */
    public function getTotalPaidAttribute(): float
    {
        return round((float) $this->payments()->sum('amount'), 2);
    }

    /**
     * Customer balance: positive = customer owes us; negative = on-account credit.
     */
    public function getBalanceAttribute(): float
    {
        return round($this->total_invoiced - $this->total_paid, 2);
    }

    public function getFullAddressAttribute(): string
    {
        return collect([
            $this->address_line1,
            $this->address_line2,
            $this->city,
            $this->postcode,
            $this->country,
        ])->filter()->implode(', ');
    }
}
