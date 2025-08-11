<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceVatLine extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'invoice_id',
        'vat_category',
        'net_amount',
        'vat_rate',
        'vat_amount',
        'gross_amount',
        'line_number',
        'created_by',
        'updated_by',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'net_amount' => 'decimal:2',
        'vat_rate' => 'decimal:4',
        'vat_amount' => 'decimal:2',
        'gross_amount' => 'decimal:2',
    ];

    /**
     * VAT category labels for display.
     *
     * @var array<string, string>
     */
    public static $vatCategoryLabels = [
        'STANDARD' => 'Standard Rate (23%)',
        'REDUCED' => 'Reduced Rate (13.5%)',
        'SECOND_REDUCED' => 'Second Reduced Rate (9%)',
        'ZERO' => 'Zero Rate (0%)',
    ];

    /**
     * Default VAT rates for each category.
     *
     * @var array<string, float>
     */
    public static $defaultVatRates = [
        'STANDARD' => 0.23,
        'REDUCED' => 0.135,
        'SECOND_REDUCED' => 0.09,
        'ZERO' => 0.00,
    ];

    /**
     * Boot the model.
     */
    protected static function boot()
    {
        parent::boot();

        // Auto-calculate amounts when creating/updating
        static::creating(function ($vatLine) {
            $vatLine->calculateAmounts();
        });

        static::updating(function ($vatLine) {
            if ($vatLine->isDirty(['net_amount', 'vat_rate'])) {
                $vatLine->calculateAmounts();
            }
        });
    }

    /**
     * Get the invoice that owns the VAT line.
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /**
     * Get the user who created this VAT line.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the user who last updated this VAT line.
     */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Calculate VAT and gross amounts from net amount and VAT rate.
     */
    public function calculateAmounts(): void
    {
        $this->vat_amount = round($this->net_amount * $this->vat_rate, 2);
        $this->gross_amount = round($this->net_amount + $this->vat_amount, 2);
    }

    /**
     * Set VAT rate from category if not explicitly set.
     */
    public function setVatCategoryAttribute(string $value): void
    {
        $this->attributes['vat_category'] = $value;
        
        // Set default VAT rate if not already set
        if (!isset($this->attributes['vat_rate']) || $this->attributes['vat_rate'] == 0) {
            $this->attributes['vat_rate'] = self::$defaultVatRates[$value] ?? 0;
        }
    }

    /**
     * Get the VAT category label.
     */
    public function getVatCategoryLabelAttribute(): string
    {
        return self::$vatCategoryLabels[$this->vat_category] ?? $this->vat_category;
    }

    /**
     * Get formatted net amount.
     */
    public function getFormattedNetAmountAttribute(): string
    {
        return '€' . number_format($this->net_amount, 2);
    }

    /**
     * Get formatted VAT amount.
     */
    public function getFormattedVatAmountAttribute(): string
    {
        return '€' . number_format($this->vat_amount, 2);
    }

    /**
     * Get formatted gross amount.
     */
    public function getFormattedGrossAmountAttribute(): string
    {
        return '€' . number_format($this->gross_amount, 2);
    }

    /**
     * Get formatted VAT rate as percentage.
     */
    public function getFormattedVatRateAttribute(): string
    {
        return number_format($this->vat_rate * 100, 1) . '%';
    }

    /**
     * Scope to get VAT lines for a specific category.
     */
    public function scopeForCategory($query, string $category)
    {
        return $query->where('vat_category', $category);
    }

    /**
     * Scope to get VAT lines ordered by line number.
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('line_number')->orderBy('id');
    }

    /**
     * Get the default VAT rate for a category.
     */
    public static function getDefaultVatRate(string $category): float
    {
        return self::$defaultVatRates[$category] ?? 0;
    }

    /**
     * Get all available VAT categories with labels.
     */
    public static function getVatCategories(): array
    {
        return self::$vatCategoryLabels;
    }

    /**
     * Create a VAT line from net amount and category.
     */
    public static function createFromNetAmount(int $invoiceId, float $netAmount, string $vatCategory, int $lineNumber = 1): self
    {
        return self::create([
            'invoice_id' => $invoiceId,
            'net_amount' => $netAmount,
            'vat_category' => $vatCategory,
            'vat_rate' => self::getDefaultVatRate($vatCategory),
            'line_number' => $lineNumber,
            'created_by' => auth()->id(),
        ]);
    }
}