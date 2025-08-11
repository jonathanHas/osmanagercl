<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AccountingSupplier extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'address',
        'phone',
        'email',
        'website',
        'vat_number',
        'default_vat_code',
        'default_expense_category',
        'payment_terms_days',
        'external_id',
        'integration_type',
        'is_active',
        // Enhanced fields from migration
        'external_pos_id',
        'is_pos_linked',
        'external_osaccounts_id',
        'is_osaccounts_linked',
        'osaccounts_last_sync',
        'supplier_type',
        'contact_person',
        'phone_secondary',
        'fax',
        'bank_account',
        'sort_code',
        'preferred_payment_method',
        'company_registration',
        'tax_reference',
        'delivery_instructions',
        'total_spent',
        'invoice_count',
        'last_invoice_date',
        'last_payment_date',
        'average_invoice_value',
        'days_since_last_order',
        'status',
        'notes',
        'tags',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'payment_terms_days' => 'integer',
        // Enhanced field casts
        'is_pos_linked' => 'boolean',
        'total_spent' => 'decimal:2',
        'invoice_count' => 'integer',
        'average_invoice_value' => 'decimal:2',
        'days_since_last_order' => 'integer',
        'last_invoice_date' => 'date',
        'last_payment_date' => 'date',
        'tags' => 'json',
        'created_by' => 'integer',
        'updated_by' => 'integer',
    ];

    /**
     * Get all invoices for this supplier.
     */
    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class, 'supplier_id');
    }

    /**
     * Get unpaid invoices for this supplier.
     */
    public function unpaidInvoices(): HasMany
    {
        return $this->hasMany(Invoice::class, 'supplier_id')
            ->whereIn('payment_status', ['pending', 'overdue', 'partial']);
    }

    /**
     * Calculate total owed to this supplier.
     */
    public function getTotalOwedAttribute(): float
    {
        return $this->unpaidInvoices()->sum('total_amount');
    }

    /**
     * Calculate total spent with this supplier.
     */
    public function getTotalSpentAttribute(): float
    {
        return $this->invoices()
            ->where('payment_status', '!=', 'cancelled')
            ->sum('total_amount');
    }

    /**
     * Scope for active suppliers.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Get supplier statistics.
     */
    public function getStatistics(): array
    {
        $invoices = $this->invoices()
            ->where('payment_status', '!=', 'cancelled')
            ->get();

        return [
            'total_invoices' => $invoices->count(),
            'total_spent' => $invoices->sum('total_amount'),
            'total_owed' => $this->total_owed,
            'average_invoice' => $invoices->avg('total_amount'),
            'last_invoice_date' => $invoices->max('invoice_date'),
        ];
    }

    /**
     * Get the POS supplier if linked.
     */
    public function posSupplier()
    {
        if (!$this->is_pos_linked || !$this->external_pos_id) {
            return null;
        }

        return $this->belongsTo(\App\Models\Supplier::class, 'external_pos_id', 'SupplierID');
    }

    /**
     * Get the user who created this supplier.
     */
    public function creator()
    {
        return $this->belongsTo(\App\Models\User::class, 'created_by');
    }

    /**
     * Get the user who last updated this supplier.
     */
    public function updater()
    {
        return $this->belongsTo(\App\Models\User::class, 'updated_by');
    }

    /**
     * Scope for POS-linked suppliers.
     */
    public function scopePosLinked($query)
    {
        return $query->where('is_pos_linked', true);
    }

    /**
     * Scope by supplier type.
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('supplier_type', $type);
    }

    /**
     * Scope for suppliers with status.
     */
    public function scopeWithStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope for active suppliers only (commonly used in dropdowns).
     */
    public function scopeActiveOnly($query)
    {
        return $query->where('status', 'active')->where('is_active', true);
    }

    /**
     * Check if supplier is overdue for contact.
     */
    public function getIsOverdueForContactAttribute(): bool
    {
        if (!$this->last_invoice_date) {
            return false;
        }

        $daysSinceLastInvoice = now()->diffInDays($this->last_invoice_date);
        
        // Consider overdue if no invoice in 90+ days and previously active
        return $daysSinceLastInvoice > 90 && $this->invoice_count > 0;
    }

    /**
     * Get supplier's performance rating based on spend and reliability.
     */
    public function getPerformanceRatingAttribute(): string
    {
        $rating = 'unknown';
        
        if ($this->total_spent >= 10000) {
            $rating = 'premium';
        } elseif ($this->total_spent >= 1000) {
            $rating = 'regular';
        } elseif ($this->invoice_count > 0) {
            $rating = 'occasional';
        } else {
            $rating = 'inactive';
        }

        return $rating;
    }

    /**
     * Update spend analytics from invoices.
     */
    public function refreshSpendAnalytics(): void
    {
        $stats = $this->invoices()
            ->where('payment_status', '!=', 'cancelled')
            ->selectRaw('
                COUNT(*) as invoice_count,
                SUM(total_amount) as total_spent,
                AVG(total_amount) as average_invoice_value,
                MAX(invoice_date) as last_invoice_date
            ')
            ->first();

        $this->update([
            'invoice_count' => $stats->invoice_count ?? 0,
            'total_spent' => $stats->total_spent ?? 0,
            'average_invoice_value' => $stats->average_invoice_value ?? 0,
            'last_invoice_date' => $stats->last_invoice_date,
            'days_since_last_order' => $stats->last_invoice_date 
                ? now()->diffInDays($stats->last_invoice_date) 
                : null,
        ]);
    }

    /**
     * Get contact display name (contact person or supplier name).
     */
    public function getContactDisplayNameAttribute(): string
    {
        return $this->contact_person ?: $this->name;
    }

    /**
     * Get full contact information formatted.
     */
    public function getFormattedContactInfoAttribute(): array
    {
        $contact = [];
        
        if ($this->contact_person) {
            $contact['person'] = $this->contact_person;
        }
        
        if ($this->phone) {
            $contact['phone'] = $this->phone;
        }
        
        if ($this->phone_secondary) {
            $contact['phone_secondary'] = $this->phone_secondary;
        }
        
        if ($this->email) {
            $contact['email'] = $this->email;
        }
        
        if ($this->fax) {
            $contact['fax'] = $this->fax;
        }

        return $contact;
    }
}