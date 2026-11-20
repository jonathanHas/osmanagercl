<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

/**
 * One electricity bill or credit note in the provider-neutral shape.
 * See docs/features/electricity.md.
 */
class ElectricityBill extends Model
{
    public const STATUS_NEEDS_REVIEW = 'needs_review';

    public const STATUS_CONFIRMED = 'confirmed';

    protected $fillable = [
        'electricity_supply_point_id', 'invoice_id', 'provider', 'account_number', 'bill_number',
        'document_type', 'issue_date', 'due_date', 'period_start', 'period_end', 'tariff',
        'net_total', 'vat_total', 'export_credit_total', 'gross_total', 'import_kwh', 'export_kwh',
        'status', 'source', 'checks', 'warnings', 'original_filename', 'file_path', 'file_hash',
        'confirmed_by', 'confirmed_at',
    ];

    protected $casts = [
        'issue_date' => 'date',
        'due_date' => 'date',
        'period_start' => 'date',
        'period_end' => 'date',
        'net_total' => 'float',
        'vat_total' => 'float',
        'export_credit_total' => 'float',
        'gross_total' => 'float',
        'import_kwh' => 'float',
        'export_kwh' => 'float',
        'checks' => 'array',
        'warnings' => 'array',
        'confirmed_at' => 'datetime',
    ];

    public function supplyPoint(): BelongsTo
    {
        return $this->belongsTo(ElectricitySupplyPoint::class, 'electricity_supply_point_id');
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function charges(): HasMany
    {
        return $this->hasMany(ElectricityBillCharge::class);
    }

    public function readings(): HasMany
    {
        return $this->hasMany(ElectricityMeterReading::class);
    }

    public function confirmedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }

    public function scopeConfirmed(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_CONFIRMED);
    }

    public function isConfirmed(): bool
    {
        return $this->status === self::STATUS_CONFIRMED;
    }

    public function isCreditNote(): bool
    {
        return $this->document_type === 'credit_note';
    }

    /** Checks that did not pass, "fail" level first. */
    public function failedChecks(): array
    {
        $failed = array_values(array_filter($this->checks ?? [], fn ($c) => ! $c['passed']));
        usort($failed, fn ($a, $b) => strcmp($a['level'], $b['level']));

        return $failed;
    }

    public function hasBlockingFailure(): bool
    {
        foreach ($this->checks ?? [] as $check) {
            if ($check['level'] === 'fail' && ! $check['passed']) {
                return true;
            }
        }

        return false;
    }

    /** Absolute path of the PDF: the bill's own upload, else the accounts invoice's attachment. */
    public function pdfPath(): ?string
    {
        if ($this->file_path && Storage::disk('private')->exists($this->file_path)) {
            return Storage::disk('private')->path($this->file_path);
        }

        $attachment = $this->invoice?->attachments
            ->filter(fn ($a) => $a->isPdf() && $a->exists())
            ->sortByDesc('is_primary')
            ->first();

        return $attachment?->full_storage_path;
    }
}
