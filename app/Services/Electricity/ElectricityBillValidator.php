<?php

namespace App\Services\Electricity;

use Carbon\Carbon;

/**
 * Arithmetic and continuity checks on a neutral bill array, whichever reader
 * produced it (provider parser, AI, or a manager's edits).
 *
 * "fail" checks block automatic confirmation; "warn" checks are shown for
 * information (re-bills and credit notes legitimately break continuity).
 */
class ElectricityBillValidator
{
    public const CATEGORIES = [
        'energy', 'standing', 'pso_levy', 'electricity_tax', 'market', 'network',
        'export_credit', 'adjustment', 'other',
    ];

    public const BANDS = ['24h', 'day', 'night', 'peak'];

    public const READ_TYPES = ['actual', 'customer', 'estimate'];

    /**
     * @param  array  $bill  neutral bill array
     * @param  array{last_readings?: array<string, float>, previous_period_end?: ?string, invoice_total?: ?float}  $context
     * @return array<int, array{key: string, label: string, level: string, passed: bool, detail: string}>
     */
    public function validate(array $bill, array $context = []): array
    {
        $checks = [];
        $add = function (string $key, string $label, string $level, bool $passed, string $detail = '') use (&$checks) {
            $checks[] = compact('key', 'label', 'level', 'passed', 'detail');
        };

        $charges = $bill['charges'] ?? [];
        $readings = $bill['readings'] ?? [];

        // Required facts
        $missing = [];
        foreach (['mprn' => 'MPRN', 'bill_number' => 'bill number', 'issue_date' => 'date of issue',
            'net_total' => 'net total', 'vat_total' => 'VAT', 'gross_total' => 'bill total'] as $field => $name) {
            if (($bill[$field] ?? null) === null || $bill[$field] === '') {
                $missing[] = $name;
            }
        }
        if (! $charges) {
            $missing[] = 'charge lines';
        }
        $add('required', 'Key details found', 'fail', ! $missing, $missing ? 'Missing: '.implode(', ', $missing) : '');

        // 1. Meter kWh add up to the billed energy units
        $billedKwh = $this->billedKwh($charges);
        if ($readings) {
            $metered = round(array_sum(array_column($readings, 'kwh')), 3);
            $add('meter_units', 'Meter readings add up to the units billed', 'fail',
                abs($metered - $billedKwh) <= 1.0,
                sprintf('Meters %s kWh, billed %s kWh', $this->n($metered), $this->n($billedKwh)));
        } elseif (abs($billedKwh) > 0) {
            $add('meter_units', 'Meter readings add up to the units billed', 'fail', false,
                sprintf('%s kWh billed but no meter readings found', $this->n($billedKwh)));
        }

        // 2. Units × rate = amount on each line
        $wrong = [];
        foreach ($charges as $c) {
            if (($c['quantity'] ?? null) === null || ($c['rate'] ?? null) === null) {
                continue;
            }
            $expected = $c['quantity'] * $c['rate'];
            // Bills print rounded quantities, so allow for it on high rates.
            $tolerance = max(0.02, 0.05 * abs($c['rate']));
            if (abs($expected - $c['net_amount']) > $tolerance) {
                $wrong[] = sprintf('%s: %s × %s = %.2f, bill says %.2f',
                    $c['description'], $this->n($c['quantity']), $this->n($c['rate']), $expected, $c['net_amount']);
            }
        }
        $add('line_maths', 'Units × rate matches each line amount', 'fail', ! $wrong, implode('; ', $wrong));

        // 3. Lines add up to the net total (export credit is outside the net total)
        if (($bill['net_total'] ?? null) !== null) {
            $sum = round(array_sum(array_map(
                fn ($c) => $c['category'] === 'export_credit' ? 0 : $c['net_amount'], $charges
            )), 2);
            $add('lines_net', 'Charge lines add up to the net total', 'fail',
                abs($sum - $bill['net_total']) <= 0.05,
                sprintf('Lines %.2f, net total %.2f', $sum, $bill['net_total']));
        }

        // 4. Net + VAT + export credit = bill total
        if (isset($bill['net_total'], $bill['vat_total'], $bill['gross_total'])) {
            $sum = round($bill['net_total'] + $bill['vat_total'] + ($bill['export_credit_total'] ?? 0), 2);
            $add('totals', 'Net + VAT + export credit equals the bill total', 'fail',
                abs($sum - $bill['gross_total']) <= 0.02,
                sprintf('%.2f against %.2f', $sum, $bill['gross_total']));
        }

        // Vocabulary (matters for AI output and manual edits)
        $bad = [];
        foreach ($charges as $c) {
            if (! in_array($c['category'] ?? null, self::CATEGORIES, true)) {
                $bad[] = 'category "'.($c['category'] ?? '').'"';
            }
        }
        foreach ($readings as $r) {
            foreach (['previous_type', 'present_type'] as $t) {
                if (! in_array($r[$t] ?? null, self::READ_TYPES, true)) {
                    $bad[] = 'read type "'.($r[$t] ?? '').'"';
                }
            }
            if (empty($r['period_start']) || empty($r['period_end'])) {
                $bad[] = 'reading dates for meter '.($r['meter_serial'] ?? '?');
            }
        }
        $add('vocabulary', 'Categories, read types and reading dates are valid', 'fail', ! $bad,
            implode(', ', array_unique($bad)));

        // 5. Readings carry on from the last ones on file
        if (! empty($context['last_readings']) && $readings) {
            $breaks = [];
            $firstBySerial = [];
            foreach ($readings as $r) {
                if (! $r['is_reversal']) {
                    $serial = $r['meter_serial'];
                    if (! isset($firstBySerial[$serial]) || $r['period_start'] < $firstBySerial[$serial]['period_start']) {
                        $firstBySerial[$serial] = $r;
                    }
                }
            }
            // An estimate this bill takes back is not a break in the run.
            $reversed = [];
            foreach ($readings as $r) {
                if ($r['is_reversal']) {
                    $reversed[$r['meter_serial']][] = (float) $r['present_reading'];
                }
            }
            foreach ($firstBySerial as $serial => $r) {
                $last = $context['last_readings'][$serial] ?? null;
                if ($last !== null && in_array((float) $last, $reversed[$serial] ?? [], true)) {
                    continue;
                }
                if ($last !== null && abs($last - $r['previous_reading']) > 0.5) {
                    $breaks[] = sprintf('%s starts at %s, last bill ended at %s',
                        $serial, $this->n($r['previous_reading']), $this->n($last));
                }
            }
            $add('reading_continuity', 'Readings follow on from the previous bill', 'warn', ! $breaks, implode('; ', $breaks));
        }

        // 6. Period follows the previous bill
        if (! empty($context['previous_period_end']) && ! empty($bill['period_start'])) {
            $expected = Carbon::parse($context['previous_period_end'])->addDay()->toDateString();
            $add('period_continuity', 'Billing period follows the previous bill', 'warn',
                $expected === $bill['period_start'],
                $expected === $bill['period_start'] ? '' : sprintf(
                    'Starts %s; previous bill ended %s',
                    Carbon::parse($bill['period_start'])->format('d/m/Y'),
                    Carbon::parse($context['previous_period_end'])->format('d/m/Y')
                ));
        }

        // 7. Agrees with the accounts invoice
        if (($context['invoice_total'] ?? null) !== null && isset($bill['gross_total'])) {
            $add('invoice_total', 'Total matches the invoice in the accounts', 'warn',
                abs($context['invoice_total'] - $bill['gross_total']) <= 0.02,
                sprintf('Accounts %.2f, bill %.2f', $context['invoice_total'], $bill['gross_total']));
        }

        return $checks;
    }

    /** True when no "fail" check failed. */
    public function passes(array $checks): bool
    {
        foreach ($checks as $check) {
            if ($check['level'] === 'fail' && ! $check['passed']) {
                return false;
            }
        }

        return true;
    }

    /** kWh on the energy lines (MWh quantities converted). */
    public function billedKwh(array $charges): float
    {
        $kwh = 0.0;
        foreach ($charges as $c) {
            if (($c['category'] ?? null) !== 'energy' || ($c['quantity'] ?? null) === null) {
                continue;
            }
            $kwh += ($c['unit'] ?? null) === 'mwh' ? $c['quantity'] * 1000 : $c['quantity'];
        }

        return round($kwh, 3);
    }

    private function n(float $value): string
    {
        return rtrim(rtrim(number_format($value, 3, '.', ','), '0'), '.');
    }
}
