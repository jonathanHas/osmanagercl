<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RtdSubmission extends Model
{
    protected $fillable = [
        'period_start',
        'period_end',
        'status',
        'submitted_date',
        'reference_number',
        'notes',
        'totals_snapshot',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'period_start' => 'date',
        'period_end' => 'date',
        'submitted_date' => 'date',
        'totals_snapshot' => 'array',
    ];

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class, 'rtd_submission_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    public function isSubmitted(): bool
    {
        return $this->status === 'submitted';
    }

    public function scopeDraft($query)
    {
        return $query->where('status', 'draft');
    }

    public function scopeSubmitted($query)
    {
        return $query->where('status', 'submitted');
    }

    /**
     * Calculate and store a totals snapshot from linked invoices.
     * Uses the same T1/T2 separation logic as RtdController::yearReport().
     * Also tracks EU/non-EU acquisition subtotals by VAT rate.
     */
    public function calculateTotalsSnapshot(): void
    {
        $invoices = $this->invoices()
            ->with('supplier')
            ->where('rtd_status', 'frozen')
            ->whereNotNull('rtd_snapshot')
            ->get();

        $goods = ['0' => 0, '9' => 0, '13.5' => 0, '23' => 0];
        $service = ['0' => 0, '9' => 0, '13.5' => 0, '23' => 0];
        $excluded = ['freight' => 0, 'deposits' => 0, 'drs' => 0, 'vat' => 0, 'service_overhead' => 0];
        $goodsTotal = 0;
        $serviceTotal = 0;

        // EU/Non-EU acquisition tracking (subset of T1/T2 — not additional)
        $euAcquisitions = ['0' => 0, '9' => 0, '13.5' => 0, '23' => 0];
        $nonEuAcquisitions = ['0' => 0, '9' => 0, '13.5' => 0, '23' => 0];
        $postponedAccounting = 0;
        $euAcquisitionsTotal = 0;
        $nonEuAcquisitionsTotal = 0;

        foreach ($invoices as $invoice) {
            $snapshot = $invoice->rtd_snapshot;
            if (! $snapshot) {
                continue;
            }

            $isService = ($snapshot['breakdown']['stats']['is_service'] ?? false)
                || ($invoice->supplier && $invoice->supplier->rtd_classification === 'service_overhead');

            // Determine supplier origin for EU/non-EU tracking
            $vatTreatment = $invoice->supplier->vat_treatment ?? 'irish_vat';
            $isEu = in_array($vatTreatment, ['eu_goods_zero_rated', 'eu_reverse_charge_services']);
            $isPostponed = $vatTreatment === 'postponed_import';
            $isNonEu = $isPostponed || $vatTreatment === 'outside_scope_or_exempt';

            if ($isService) {
                $serviceBreakdown = $snapshot['breakdown']['service_overhead'] ?? $snapshot['breakdown']['goods_for_resale'] ?? [];
                foreach ($service as $rate => $value) {
                    $amount = (float) ($serviceBreakdown[$rate] ?? 0);
                    $service[$rate] += $amount;
                    if ($isEu) {
                        $euAcquisitions[$rate] += $amount;
                    } elseif ($isNonEu) {
                        $nonEuAcquisitions[$rate] += $amount;
                    }
                }
                $invoiceRtdTotal = array_sum(array_map('floatval', $serviceBreakdown));
                $serviceTotal += $invoiceRtdTotal;
            } else {
                $gfr = $snapshot['breakdown']['goods_for_resale'] ?? [];
                foreach ($goods as $rate => $value) {
                    $amount = (float) ($gfr[$rate] ?? 0);
                    $goods[$rate] += $amount;
                    if ($isEu) {
                        $euAcquisitions[$rate] += $amount;
                    } elseif ($isNonEu) {
                        $nonEuAcquisitions[$rate] += $amount;
                    }
                }
                $invoiceRtdTotal = array_sum(array_map('floatval', $gfr));
                $goodsTotal += $invoiceRtdTotal;
            }

            if ($isPostponed) {
                $postponedAccounting += $invoiceRtdTotal;
            }

            $exc = $snapshot['breakdown']['excluded'] ?? [];
            $excluded['freight'] += (float) ($exc['freight'] ?? 0);
            $excluded['deposits'] += (float) ($exc['deposits'] ?? 0);
            $excluded['drs'] += (float) ($exc['drs'] ?? 0);
            $excluded['vat'] += (float) ($exc['vat'] ?? 0);
            $excluded['service_overhead'] += (float) ($exc['service_overhead'] ?? 0);
        }

        $euAcquisitionsTotal = round(array_sum($euAcquisitions), 2);
        $nonEuAcquisitionsTotal = round(array_sum($nonEuAcquisitions), 2);

        $this->totals_snapshot = [
            'goods' => $goods,
            'service' => $service,
            'excluded' => $excluded,
            'goods_total' => round($goodsTotal, 2),
            'service_total' => round($serviceTotal, 2),
            'invoice_count' => $invoices->count(),
            'eu_acquisitions' => $euAcquisitions,
            'eu_acquisitions_total' => $euAcquisitionsTotal,
            'non_eu_acquisitions' => $nonEuAcquisitions,
            'non_eu_acquisitions_total' => $nonEuAcquisitionsTotal,
            'postponed_accounting' => round($postponedAccounting, 2),
        ];
        $this->save();
    }
}
