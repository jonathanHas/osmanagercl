<?php

namespace App\Services\Electricity;

use App\Models\ElectricityBill;
use App\Models\ElectricityMeterReading;
use App\Models\ElectricitySupplyPoint;
use Carbon\Carbon;
use Carbon\CarbonPeriod;

/**
 * Monthly kWh and cost for a supply point, from confirmed bills only.
 *
 * kWh come from the meter readings, not from the units each bill charged:
 * bills estimate, reverse and re-bill. Each meter's readings form a timeline;
 * actual and customer reads are fixed points, and the kWh between two fixed
 * points are spread evenly over the days between them. Estimated readings only
 * count at the two ends of the timeline, where there is nothing better.
 *
 * Costs come from the charge lines, each spread over its own date range, so
 * credit notes and re-bills land in the months they correct.
 */
class ElectricityUsageService
{
    private const RANK = ['estimate' => 1, 'customer' => 2, 'actual' => 3];

    /**
     * @return array{meters: array<int, string>, months: array<string, array>, uncovered: string[], excluded_credits: array}
     */
    public function monthly(ElectricitySupplyPoint $point): array
    {
        $meters = $point->meters()->orderBy('serial')->get()->mapWithKeys(fn ($m) => [$m->id => $m->label])->all();

        $usage = $this->usageByMonth($point);
        $excluded = [];
        $costs = $this->costsByMonth($point, $excluded);

        $keys = array_merge(array_keys($usage), array_keys($costs));
        if (! $keys) {
            return ['meters' => $meters, 'months' => [], 'uncovered' => [], 'excluded_credits' => $excluded];
        }

        $months = [];
        $uncovered = [];
        $cursor = Carbon::parse(min($keys).'-01');
        $last = Carbon::parse(max($keys).'-01');

        for (; $cursor->lte($last); $cursor->addMonthNoOverflow()) {
            $ym = $cursor->format('Y-m');
            $u = $usage[$ym] ?? ['meters' => [], 'estimated' => false];
            $c = $costs[$ym] ?? ['categories' => [], 'vat' => 0.0, 'export_kwh' => 0.0, 'billed_kwh' => 0.0, 'covered' => false];

            $kwh = array_sum($u['meters']);
            $export = $c['categories']['export_credit'] ?? 0.0;
            $net = array_sum($c['categories']) - $export;
            $other = $c['categories']['other'] ?? 0.0;

            $positive = array_map(fn ($v) => max($v, 0), $u['meters']);
            $positiveTotal = array_sum($positive);
            $split = [];
            foreach ($meters as $id => $label) {
                $share = $positiveTotal > 0 ? ($positive[$id] ?? 0) / $positiveTotal : 0.0;
                $split[$id] = [
                    'kwh' => round($u['meters'][$id] ?? 0, 1),
                    'share' => $share,
                    'net' => round($net * $share, 2),
                    'vat' => round($c['vat'] * $share, 2),
                ];
            }

            $months[$ym] = [
                'label' => $cursor->format('M Y'),
                'kwh' => round($kwh, 1),
                'estimated' => $u['estimated'],
                'meters' => $split,
                'categories' => array_map(fn ($v) => round($v, 2), $c['categories']),
                'net' => round($net, 2),
                'vat' => round($c['vat'], 2),
                'export_credit' => round($export, 2),
                'export_kwh' => round($c['export_kwh'], 1),
                'billed_kwh' => round($c['billed_kwh'], 1),
                'covered' => $c['covered'],
                // Everything a kWh cost except one-off items such as an exit fee.
                'cents_per_kwh' => $c['covered'] && $kwh > 0 ? round(($net - $other) / $kwh * 100, 2) : null,
            ];

            if (! $c['covered'] && $kwh > 0) {
                $uncovered[] = $cursor->format('M Y');
            }
        }

        return ['meters' => $meters, 'months' => $months, 'uncovered' => $uncovered, 'excluded_credits' => $excluded];
    }

    /**
     * Totals for the 12 months ending with $endMonth (Y-m) and the 12 before.
     *
     * @return array{current: array, previous: array}
     */
    public function yearSummary(array $months, string $endMonth): array
    {
        $end = Carbon::parse($endMonth.'-01');

        return [
            'current' => $this->totals($months, $end->copy()->subMonthsNoOverflow(11), $end),
            'previous' => $this->totals($months, $end->copy()->subMonthsNoOverflow(23), $end->copy()->subMonthsNoOverflow(12)),
        ];
    }

    public function totals(array $months, Carbon $from, Carbon $to): array
    {
        $t = ['kwh' => 0.0, 'net' => 0.0, 'vat' => 0.0, 'export_credit' => 0.0, 'export_kwh' => 0.0,
            'energy' => 0.0, 'billed_kwh' => 0.0, 'fixed' => 0.0, 'other' => 0.0, 'covered_kwh' => 0.0,
            'covered_net' => 0.0, 'months' => 0, 'days' => 0];

        foreach ($months as $ym => $m) {
            $date = Carbon::parse($ym.'-01');
            if ($date->lt($from) || $date->gt($to)) {
                continue;
            }
            $t['months']++;
            $t['kwh'] += $m['kwh'];
            $t['net'] += $m['net'];
            $t['vat'] += $m['vat'];
            $t['export_credit'] += $m['export_credit'];
            $t['export_kwh'] += $m['export_kwh'];
            $t['energy'] += $m['categories']['energy'] ?? 0;
            $t['billed_kwh'] += $m['billed_kwh'];
            $t['other'] += $m['categories']['other'] ?? 0;
            if ($m['covered']) {
                $t['fixed'] += ($m['categories']['standing'] ?? 0) + ($m['categories']['pso_levy'] ?? 0);
                $t['covered_kwh'] += $m['kwh'];
                $t['covered_net'] += $m['net'] - ($m['categories']['other'] ?? 0);
                $t['days'] += $date->daysInMonth;
            }
        }

        return $t + [
            'from' => $from->format('M Y'),
            'to' => $to->format('M Y'),
            // Only months a bill covers count towards the rates.
            'cents_per_kwh' => $t['covered_kwh'] > 0 ? round($t['covered_net'] / $t['covered_kwh'] * 100, 2) : null,
            'unit_rate_cents' => $t['billed_kwh'] > 0 ? round($t['energy'] / $t['billed_kwh'] * 100, 2) : null,
            'fixed_per_day' => $t['days'] > 0 ? round($t['fixed'] / $t['days'], 2) : null,
        ];
    }

    /**
     * kWh per month per meter from the reading timeline.
     *
     * @return array<string, array{meters: array<int, float>, estimated: bool}>
     */
    public function usageByMonth(ElectricitySupplyPoint $point): array
    {
        $rows = ElectricityMeterReading::query()
            ->whereHas('bill', fn ($q) => $q->confirmed()->where('electricity_supply_point_id', $point->id))
            ->with('bill:id,issue_date')
            ->get();

        $months = [];

        foreach ($rows->groupBy(fn ($r) => $r->electricity_meter_id.'|'.$r->band) as $register) {
            $meterId = $register->first()->electricity_meter_id;
            $multiplier = $register->last()->multiplier ?: 1.0;

            // Estimates a later bill took back.
            $withdrawn = [];
            foreach ($register->where('is_reversal', true) as $r) {
                $withdrawn[$r->period_end->toDateString().'|'.$r->present_reading] = true;
            }

            $points = [];
            $add = function (string $date, float $value, string $type, string $issued) use (&$points, $withdrawn) {
                if ($type === 'estimate' && isset($withdrawn[$date.'|'.$value])) {
                    return;
                }
                $current = $points[$date] ?? null;
                $better = ! $current
                    || self::RANK[$type] > self::RANK[$current['type']]
                    || (self::RANK[$type] === self::RANK[$current['type']] && $issued >= $current['issued']);
                if ($better) {
                    $points[$date] = ['date' => $date, 'value' => $value, 'type' => $type, 'issued' => $issued];
                }
            };

            foreach ($register->where('is_reversal', false) as $r) {
                $issued = $r->bill->issue_date?->toDateString() ?? '';
                // The previous reading was taken at the end of the day before the row starts.
                $add($r->period_start->copy()->subDay()->toDateString(), $r->previous_reading, $r->previous_type, $issued);
                $add($r->period_end->toDateString(), $r->present_reading, $r->present_type, $issued);
            }

            ksort($points);
            $points = array_values($points);
            $lastIndex = count($points) - 1;
            $anchors = array_values(array_filter(
                $points,
                fn ($p, $i) => $p['type'] !== 'estimate' || $i === 0 || $i === $lastIndex,
                ARRAY_FILTER_USE_BOTH
            ));

            for ($i = 1; $i < count($anchors); $i++) {
                [$a, $b] = [$anchors[$i - 1], $anchors[$i]];
                $from = Carbon::parse($a['date'])->addDay();
                $to = Carbon::parse($b['date']);
                $days = $from->diffInDays($to) + 1;
                if ($days < 1) {
                    continue;
                }
                $perDay = ($b['value'] - $a['value']) * $multiplier / $days;
                $estimated = $a['type'] === 'estimate' || $b['type'] === 'estimate';

                foreach ($this->daysPerMonth($from, $to) as $ym => $n) {
                    $months[$ym]['meters'][$meterId] = ($months[$ym]['meters'][$meterId] ?? 0) + $perDay * $n;
                    $months[$ym]['estimated'] = ($months[$ym]['estimated'] ?? false) || $estimated;
                }
            }
        }

        return $months;
    }

    /**
     * Net cost per month per category, VAT, exported and billed kWh.
     *
     * Three rules beyond "spread each line over its dates":
     * - A lump credit that cancels a bill takes that bill's period when the
     *   bill is on file; when it is not, the credit is left out (and listed in
     *   $excluded), because neither side of the cancellation should count.
     * - A per-kWh line covering the whole bill follows the bill's meter rows,
     *   so a bill that reverses an estimate puts the correction in the days
     *   the estimate covered rather than thinning it over the longer period.
     * - An export credit that only says "to <date>" starts the day after the
     *   previous one ended.
     *
     * @param  array  $excluded  filled with the credits left out
     * @return array<string, array{categories: array<string, float>, vat: float, export_kwh: float, billed_kwh: float, covered: bool}>
     */
    public function costsByMonth(ElectricitySupplyPoint $point, array &$excluded = []): array
    {
        $bills = ElectricityBill::confirmed()
            ->where('electricity_supply_point_id', $point->id)
            ->with(['charges', 'readings'])
            ->orderBy('issue_date')
            ->get();

        $originals = $bills->where('document_type', 'invoice')->keyBy(fn ($b) => $b->provider.'|'.$b->bill_number);
        $months = [];

        // Export credits that only say "to <date>" run from the day after the one before.
        $exportEnds = $bills->flatMap->charges
            ->where('category', 'export_credit')
            ->map(fn ($c) => $c->period_end?->toDateString())
            ->filter()->unique()->sort()->values();

        foreach ($bills as $bill) {
            // A credit issued under the number of the bill it cancels belongs to that bill's period.
            $original = $bill->isCreditNote() ? $originals->get($bill->provider.'|'.$bill->bill_number) : null;
            $fallback = $original ?? $bill;
            $fallbackStart = $fallback->period_start ?? $bill->issue_date;
            $fallbackEnd = $fallback->period_end ?? $bill->issue_date;

            $isCancellation = $bill->isCreditNote() && $bill->charges->isNotEmpty()
                && $bill->charges->every(fn ($c) => $c->category === 'adjustment' && ! $c->period_start);
            if ($isCancellation && ! $original) {
                $excluded[] = [
                    'id' => $bill->id,
                    'bill_number' => $bill->bill_number,
                    'issue_date' => $bill->issue_date?->format('d/m/Y'),
                    'net_total' => $bill->net_total,
                ];

                continue;
            }

            // kWh per month according to this bill's own meter rows
            $rowKwh = [];
            foreach ($bill->readings as $row) {
                $rowDays = $row->period_start->diffInDays($row->period_end) + 1;
                if ($rowDays < 1) {
                    continue;
                }
                foreach ($this->daysPerMonth($row->period_start, $row->period_end) as $ym => $n) {
                    $rowKwh[$ym] = ($rowKwh[$ym] ?? 0) + $row->kwh * $n / $rowDays;
                }
            }
            $rowTotal = array_sum($rowKwh);

            foreach ($bill->charges as $charge) {
                $chargeKwh = match ($charge->unit) {
                    'kwh' => $charge->quantity,
                    'mwh' => $charge->quantity * 1000,
                    default => null,
                };
                $followsMeters = $chargeKwh !== null && $charge->category !== 'export_credit'
                    && abs($rowTotal) >= 1 && abs($chargeKwh - $rowTotal) <= 1.0;

                if ($followsMeters) {
                    $shares = array_map(fn ($kwh) => $kwh / $rowTotal, $rowKwh);
                } else {
                    $shares = null;
                }

                $start = $charge->period_start ?? $fallbackStart;
                $end = $charge->period_end ?? $charge->period_start ?? $fallbackEnd;

                if ($charge->category === 'export_credit' && ! $charge->period_start && $charge->period_end) {
                    $before = $exportEnds->last(fn ($d) => $d < $charge->period_end->toDateString());
                    $start = $before && Carbon::parse($before)->diffInDays($charge->period_end) <= 100
                        ? Carbon::parse($before)->addDay()
                        : $charge->period_end;
                }
                if (! $start || ! $end) {
                    continue;
                }
                if ($end->lt($start)) {
                    $end = $start;
                }

                if ($shares === null) {
                    $days = $start->diffInDays($end) + 1;
                    $shares = array_map(fn ($n) => $n / $days, $this->daysPerMonth($start, $end));
                }

                foreach ($shares as $ym => $part) {
                    $months[$ym] ??= ['categories' => [], 'vat' => 0.0, 'export_kwh' => 0.0, 'billed_kwh' => 0.0, 'covered' => false];
                    $months[$ym]['categories'][$charge->category] = ($months[$ym]['categories'][$charge->category] ?? 0) + $charge->net_amount * $part;
                    $months[$ym]['vat'] += $charge->net_amount * ($charge->vat_rate ?? 0) / 100 * $part;

                    if ($charge->category === 'export_credit') {
                        $months[$ym]['export_kwh'] += abs($charge->quantity ?? 0) * $part;
                    }
                    if ($charge->category === 'energy') {
                        $months[$ym]['billed_kwh'] += ($charge->unit === 'mwh' ? 1000 : 1) * ($charge->quantity ?? 0) * $part;
                    }
                    if (in_array($charge->category, ['energy', 'standing'], true)) {
                        $months[$ym]['covered'] = true;
                    }
                }
            }
        }

        return $months;
    }

    /** @return array<string, int> days falling in each Y-m between two dates, inclusive */
    private function daysPerMonth(Carbon $from, Carbon $to): array
    {
        $out = [];
        foreach (CarbonPeriod::create($from->copy()->startOfMonth(), '1 month', $to->copy()->startOfMonth()) as $month) {
            $start = $month->copy()->startOfMonth()->max($from);
            $end = $month->copy()->endOfMonth()->startOfDay()->min($to);
            $out[$month->format('Y-m')] = (int) $start->copy()->startOfDay()->diffInDays($end->copy()->startOfDay()) + 1;
        }

        return $out;
    }
}
