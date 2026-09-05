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
        'payment_terms_days',
        'send_statements',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'default_discount_percent' => 'decimal:2',
        'payment_terms_days' => 'integer',
        'send_statements' => 'boolean',
        'statement_last_sent_at' => 'datetime',
    ];

    /**
     * Fallback payment terms when neither the customer nor the invoice says
     * otherwise. Used to derive an effective due date for aging.
     */
    public const DEFAULT_PAYMENT_TERMS_DAYS = 30;

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

    /**
     * Customers who should receive an emailed statement: opted in, and we have
     * somewhere to send it. Mirrors AccountingSupplier::scopeReceivesDailySalesEmail().
     */
    public function scopeReceivesStatements($query)
    {
        return $query->where('send_statements', true)
            ->whereNotNull('email')
            ->where('email', '!=', '');
    }

    /**
     * Restrict to customers whose balance exceeds $min (default: anyone owing).
     *
     * Uses correlated subqueries in WHERE rather than HAVING on a withSum alias:
     * MySQL rejects HAVING+GROUP BY here under ONLY_FULL_GROUP_BY, and SQLite
     * rejects HAVING without GROUP BY, so neither alias form is portable.
     * The subqueries are built by Eloquent, so the relationship rules (non-void
     * invoices, non-void payments) stay in one place.
     */
    public function scopeWithBalanceOver($query, float $min = 0.005)
    {
        $invoiced = CustomerInvoice::query()
            ->selectRaw('COALESCE(SUM(total), 0)')
            ->whereColumn('customer_invoices.customer_id', 'customers.id')
            ->where('status', '!=', CustomerInvoice::STATUS_VOID);

        $paid = CustomerPayment::query()
            ->selectRaw('COALESCE(SUM(amount), 0)')
            ->whereColumn('customer_payments.customer_id', 'customers.id')
            ->whereNull('voided_at');

        // The threshold is inlined rather than bound: SQLite binds a PHP float as
        // text, and text compares greater than every number, so a bound `> ?`
        // silently matched nothing. %F is locale-independent and $min is ours.
        return $query->whereRaw(
            sprintf('((%s) - (%s)) > %F', $invoiced->toSql(), $paid->toSql(), $min),
            array_merge($invoiced->getBindings(), $paid->getBindings())
        );
    }

    /**
     * Effective payment terms in days, falling back to the house default.
     */
    public function getTermsDaysAttribute(): int
    {
        return $this->payment_terms_days ?? self::DEFAULT_PAYMENT_TERMS_DAYS;
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
