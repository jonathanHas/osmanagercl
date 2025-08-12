<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

class VatReturn extends Model
{
    use HasFactory;

    protected $fillable = [
        'return_period',
        'period_start',
        'period_end',
        'status',
        'total_net',
        'total_vat',
        'total_gross',
        'zero_net',
        'zero_vat',
        'second_reduced_net',
        'second_reduced_vat',
        'reduced_net',
        'reduced_vat',
        'standard_net',
        'standard_vat',
        'notes',
        'submitted_date',
        'reference_number',
        'is_historical',
        'created_by',
        'finalized_by',
    ];

    protected $casts = [
        'period_start' => 'date',
        'period_end' => 'date',
        'submitted_date' => 'date',
        'total_net' => 'decimal:2',
        'total_vat' => 'decimal:2',
        'total_gross' => 'decimal:2',
        'zero_net' => 'decimal:2',
        'zero_vat' => 'decimal:2',
        'second_reduced_net' => 'decimal:2',
        'second_reduced_vat' => 'decimal:2',
        'reduced_net' => 'decimal:2',
        'reduced_vat' => 'decimal:2',
        'standard_net' => 'decimal:2',
        'standard_vat' => 'decimal:2',
        'is_historical' => 'boolean',
    ];

    /**
     * Get the invoices for this VAT return.
     */
    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    /**
     * Get the user who created this VAT return.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the user who finalized this VAT return.
     */
    public function finalizer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'finalized_by');
    }

    /**
     * Calculate and update totals from assigned invoices.
     */
    public function calculateTotals(): void
    {
        $totals = $this->invoices()
            ->select(
                DB::raw('SUM(subtotal) as total_net'),
                DB::raw('SUM(vat_amount) as total_vat'),
                DB::raw('SUM(total_amount) as total_gross'),
                DB::raw('SUM(zero_net) as zero_net'),
                DB::raw('SUM(zero_vat) as zero_vat'),
                DB::raw('SUM(second_reduced_net) as second_reduced_net'),
                DB::raw('SUM(second_reduced_vat) as second_reduced_vat'),
                DB::raw('SUM(reduced_net) as reduced_net'),
                DB::raw('SUM(reduced_vat) as reduced_vat'),
                DB::raw('SUM(standard_net) as standard_net'),
                DB::raw('SUM(standard_vat) as standard_vat')
            )
            ->first();

        $this->update([
            'total_net' => $totals->total_net ?? 0,
            'total_vat' => $totals->total_vat ?? 0,
            'total_gross' => $totals->total_gross ?? 0,
            'zero_net' => $totals->zero_net ?? 0,
            'zero_vat' => $totals->zero_vat ?? 0,
            'second_reduced_net' => $totals->second_reduced_net ?? 0,
            'second_reduced_vat' => $totals->second_reduced_vat ?? 0,
            'reduced_net' => $totals->reduced_net ?? 0,
            'reduced_vat' => $totals->reduced_vat ?? 0,
            'standard_net' => $totals->standard_net ?? 0,
            'standard_vat' => $totals->standard_vat ?? 0,
        ]);
    }

    /**
     * Get VAT breakdown array for display.
     */
    public function getVatBreakdown(): array
    {
        return [
            '0%' => [
                'net' => $this->zero_net,
                'vat' => $this->zero_vat,
                'rate' => 0.00,
            ],
            '9%' => [
                'net' => $this->second_reduced_net,
                'vat' => $this->second_reduced_vat,
                'rate' => 0.09,
            ],
            '13.5%' => [
                'net' => $this->reduced_net,
                'vat' => $this->reduced_vat,
                'rate' => 0.135,
            ],
            '23%' => [
                'net' => $this->standard_net,
                'vat' => $this->standard_vat,
                'rate' => 0.23,
            ],
        ];
    }

    /**
     * Check if VAT return can be modified.
     */
    public function canBeModified(): bool
    {
        return $this->status === 'draft';
    }

    /**
     * Finalize the VAT return.
     */
    public function finalize(int $userId = null): void
    {
        if (!$this->canBeModified()) {
            throw new \Exception('VAT return cannot be modified once finalized.');
        }

        $this->calculateTotals();
        $this->update([
            'status' => 'finalized',
            'finalized_by' => $userId ?? auth()->id(),
        ]);
    }

    /**
     * Scope for draft returns.
     */
    public function scopeDraft($query)
    {
        return $query->where('status', 'draft');
    }

    /**
     * Scope for finalized returns.
     */
    public function scopeFinalized($query)
    {
        return $query->whereIn('status', ['finalized', 'submitted', 'paid']);
    }

    /**
     * Generate a unique return period identifier.
     */
    public static function generateReturnPeriod(\Carbon\Carbon $date): string
    {
        // Format: YYYY-MM for monthly returns
        // Can be changed to YYYY-Q[1-4] for quarterly returns
        return $date->format('Y-m');
    }
}