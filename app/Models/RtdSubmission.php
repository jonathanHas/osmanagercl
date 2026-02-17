<?php

namespace App\Models;

use App\Services\SalesAccountingImportService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

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
     * Aggregate net sales by VAT rate from VAT returns within this submission's period.
     * Primary source: VatReturn.sales_vat_data (persisted VAT3 figures).
     * Fallback: sales_accounting_daily table for returns without sales_vat_data.
     */
    private function aggregateSalesFromVatReturns(): array
    {
        // Ensure sales_accounting_daily is populated for the submission period
        app(SalesAccountingImportService::class)->ensureDataExists($this->period_start, $this->period_end);

        $sales = ['0' => 0, '9' => 0, '13.5' => 0, '23' => 0];
        $usedFallback = false;

        // Map VAT rate to snapshot key — use string keys to avoid PHP float truncation
        $rateToKey = [
            '0' => '0', '0.0' => '0', '0.0000' => '0',
            '0.09' => '9', '0.0900' => '9',
            '0.135' => '13.5', '0.1350' => '13.5',
            '0.23' => '23', '0.2300' => '23',
        ];

        // Find all VAT returns whose period falls within the RTD submission period
        $vatReturns = VatReturn::where('period_start', '>=', $this->period_start)
            ->where('period_end', '<=', $this->period_end)
            ->get();

        foreach ($vatReturns as $vatReturn) {
            $salesVatData = $vatReturn->sales_vat_data;

            if ($salesVatData && ! empty($salesVatData['by_rate'])) {
                // Primary: use persisted VAT3 sales data
                foreach ($salesVatData['by_rate'] as $rateData) {
                    // Handle both keyed objects and array items
                    $vatRate = is_object($rateData)
                        ? $rateData->vat_rate
                        : ($rateData['vat_rate'] ?? null);
                    $totalNet = is_object($rateData)
                        ? $rateData->total_net
                        : ($rateData['total_net'] ?? 0);

                    if ($vatRate === null) {
                        continue;
                    }

                    // Map decimal rate to snapshot key (string lookup avoids float truncation)
                    $key = $rateToKey[(string) $vatRate] ?? null;

                    if ($key !== null) {
                        $sales[$key] += (float) $totalNet;
                    }
                }
            } else {
                // Fallback: query sales_accounting_daily for this period
                $usedFallback = true;
                $periodSales = DB::table('sales_accounting_daily')
                    ->select(
                        'vat_rate',
                        DB::raw('SUM(net_amount) as total_net')
                    )
                    ->whereBetween('sale_date', [
                        $vatReturn->period_start->format('Y-m-d'),
                        $vatReturn->period_end->format('Y-m-d'),
                    ])
                    ->groupBy('vat_rate')
                    ->get();

                foreach ($periodSales as $row) {
                    $key = $rateToKey[(string) $row->vat_rate] ?? null;

                    if ($key !== null) {
                        $sales[$key] += (float) $row->total_net;
                    }
                }

                // Deduct paperin (gift voucher redemption) gross from 0% to prevent double-counting
                $paperinGross = (float) DB::table('sales_accounting_daily')
                    ->where('payment_type', 'paperin')
                    ->whereBetween('sale_date', [
                        $vatReturn->period_start->format('Y-m-d'),
                        $vatReturn->period_end->format('Y-m-d'),
                    ])
                    ->sum('gross_amount');

                if ($paperinGross > 0) {
                    $sales['0'] -= $paperinGross;
                }
            }
        }

        // Fill coverage gaps: date ranges within the RTD period not covered by any VAT return
        if ($vatReturns->isNotEmpty()) {
            $coveredRanges = $vatReturns->map(fn ($vr) => [
                'start' => $vr->period_start,
                'end' => $vr->period_end,
            ])->sortBy('start')->values();

            $gaps = $this->findDateGaps($this->period_start, $this->period_end, $coveredRanges);

            foreach ($gaps as $gap) {
                $usedFallback = true;
                $gapSales = DB::table('sales_accounting_daily')
                    ->select('vat_rate', DB::raw('SUM(net_amount) as total_net'))
                    ->whereBetween('sale_date', [$gap['start'], $gap['end']])
                    ->groupBy('vat_rate')
                    ->get();

                foreach ($gapSales as $row) {
                    $key = $rateToKey[(string) $row->vat_rate] ?? null;
                    if ($key !== null) {
                        $sales[$key] += (float) $row->total_net;
                    }
                }

                $paperinGross = (float) DB::table('sales_accounting_daily')
                    ->where('payment_type', 'paperin')
                    ->whereBetween('sale_date', [$gap['start'], $gap['end']])
                    ->sum('gross_amount');

                if ($paperinGross > 0) {
                    $sales['0'] -= $paperinGross;
                }
            }
        }

        // If no VAT returns found, fall back to sales_accounting_daily directly
        if ($vatReturns->isEmpty()) {
            $directSales = DB::table('sales_accounting_daily')
                ->select('vat_rate', DB::raw('SUM(net_amount) as total_net'))
                ->whereBetween('sale_date', [
                    $this->period_start->format('Y-m-d'),
                    $this->period_end->format('Y-m-d'),
                ])
                ->groupBy('vat_rate')
                ->get();

            if ($directSales->isNotEmpty()) {
                foreach ($directSales as $row) {
                    $key = $rateToKey[(string) $row->vat_rate] ?? null;
                    if ($key !== null) {
                        $sales[$key] += (float) $row->total_net;
                    }
                }

                // Deduct paperin (gift voucher redemption) gross from 0% to prevent double-counting
                $paperinGross = (float) DB::table('sales_accounting_daily')
                    ->where('payment_type', 'paperin')
                    ->whereBetween('sale_date', [
                        $this->period_start->format('Y-m-d'),
                        $this->period_end->format('Y-m-d'),
                    ])
                    ->sum('gross_amount');

                if ($paperinGross > 0) {
                    $sales['0'] -= $paperinGross;
                }

                $usedFallback = true;
            }
        }

        // Round all values
        foreach ($sales as $key => $value) {
            $sales[$key] = round($value, 2);
        }

        $salesTotal = round(array_sum($sales), 2);
        $source = $vatReturns->isEmpty()
            ? ($usedFallback ? 'sales_accounting_daily' : 'none')
            : ($usedFallback ? 'mixed' : 'vat_returns');

        return [
            'sales' => $sales,
            'sales_total' => $salesTotal,
            'sales_source' => $source,
            'vat_returns_count' => $vatReturns->count(),
        ];
    }

    /**
     * Find date gaps within [periodStart, periodEnd] not covered by any of the sorted covered ranges.
     * Returns array of ['start' => 'Y-m-d', 'end' => 'Y-m-d'] for each gap.
     */
    private function findDateGaps(Carbon $periodStart, Carbon $periodEnd, $coveredRanges): array
    {
        $gaps = [];
        $cursor = $periodStart->copy();

        foreach ($coveredRanges as $range) {
            if ($cursor->lt($range['start'])) {
                $gaps[] = [
                    'start' => $cursor->format('Y-m-d'),
                    'end' => $range['start']->copy()->subDay()->format('Y-m-d'),
                ];
            }
            if ($range['end']->gte($cursor)) {
                $cursor = $range['end']->copy()->addDay();
            }
        }

        if ($cursor->lte($periodEnd)) {
            $gaps[] = [
                'start' => $cursor->format('Y-m-d'),
                'end' => $periodEnd->format('Y-m-d'),
            ];
        }

        return $gaps;
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

        // Aggregate sales data from VAT returns for ROS Section 1
        $salesData = $this->aggregateSalesFromVatReturns();

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
            'sales' => $salesData['sales'],
            'sales_total' => $salesData['sales_total'],
            'sales_source' => $salesData['sales_source'],
            'vat_returns_count' => $salesData['vat_returns_count'],
        ];
        $this->save();
    }
}
