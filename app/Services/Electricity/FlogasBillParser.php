<?php

namespace App\Services\Electricity;

use App\Services\Electricity\Contracts\ElectricityBillParser;
use Carbon\Carbon;

/**
 * Flogas electricity bills, both layouts: the "fge_" one used to September
 * 2025 and the "_flri" one since. Header facts come from the full page text;
 * the reading and charge tables come from the left 370 points of each page,
 * because the right-hand panel (payment options, contact details) otherwise
 * lands in the middle of the table lines.
 *
 * See docs/features/electricity.md for the layouts and their quirks.
 */
class FlogasBillParser implements ElectricityBillParser
{
    private const NUM = '-?[\d,]*\d(?:\.\d+)?';

    private const DATE = '\d{2}\/\d{2}\/\d{2,4}';

    public function provider(): string
    {
        return 'Flogas';
    }

    public function supports(string $fullText): bool
    {
        return stripos($fullText, 'flogas') !== false
            && preg_match('/MPRN/i', $fullText) === 1;
    }

    public function cropWidth(): ?int
    {
        return 370;
    }

    public function parse(string $fullText, ?string $croppedText = null): array
    {
        $full = $this->normalise($fullText);
        $left = $this->normalise($croppedText ?? $fullText);
        $warnings = [];

        $billNumber = $this->match('/Invoice Number:?\s+(\S+)/i', $full);
        $issueDate = $this->date($this->match('/Date of issue:?\s+('.self::DATE.'|\d{4}-\d{2}-\d{2})/i', $full));
        $net = $this->money($this->match('/Net Bill for this period\s+(?:€\s*)?('.self::NUM.')/i', $full));
        $vat = $this->money($this->match('/Total VAT\s+(?:€\s*)?('.self::NUM.')/i', $full));
        $gross = $this->money($this->match('/Current Bill\s+(?:€\s*)?('.self::NUM.')/', $full));
        $exportSummary = $this->money($this->match('/Export Credit\s+(?:€\s*)?('.self::NUM.')/', $full));

        [$periodStart, $periodEnd] = $this->headerPeriod($full);

        $readings = $this->repairChainedRows($this->readings($left, $periodEnd), $warnings);
        $charges = $this->charges($left, $issueDate, $periodEnd);

        // The header period is unusable on re-bills ("13/06/25 - 13/06/25") and
        // absent on the oldest bills: fall back to what the lines cover.
        if (! $periodStart || ! $periodEnd || $periodStart >= $periodEnd) {
            [$lineStart, $lineEnd] = $this->coveredPeriod($charges, $readings);
            if ($lineStart && $lineEnd && $lineStart < $lineEnd) {
                [$periodStart, $periodEnd] = [$lineStart, $lineEnd];
            }
        }

        // A reading row without its own end date ends with the bill.
        foreach ($readings as &$reading) {
            $reading['period_end'] ??= $periodEnd;
        }
        unset($reading);

        $exportLines = round(array_sum(array_map(
            fn ($c) => $c['category'] === 'export_credit' ? $c['net_amount'] : 0,
            $charges
        )), 2);

        // An old-layout export credit statement shows the credit as its net bill.
        // In the neutral shape an export credit is never part of the net total.
        $isExportStatement = $charges && $exportSummary === null && $net !== null
            && count(array_filter($charges, fn ($c) => $c['category'] !== 'export_credit')) === 0
            && abs($exportLines - $net) < 0.01;
        if ($isExportStatement) {
            $exportSummary = $exportLines;
            $net = 0.0;
        }

        if ($net === null) {
            $warnings[] = 'Net bill amount not found';
        }

        return [
            'provider' => $this->provider(),
            // The 2024 bills print the MPRN without its label (the label is part of a picture).
            'mprn' => $this->match('/MPRN:?\s+(\d{11})\b/', $full) ?? $this->match('/(?<!\d)(10\d{9})(?!\d)/', $full),
            'account_number' => $this->match('/Account Number:?\s+(\d+)/i', $full),
            'bill_number' => $billNumber,
            'document_type' => ($billNumber && str_starts_with(strtoupper($billNumber), 'CN')) || ($net !== null && $net < 0) || $isExportStatement
                ? 'credit_note'
                : 'invoice',
            'issue_date' => $issueDate,
            'due_date' => $this->date($this->match('/Current Bill due date:?\s+('.self::DATE.')/i', $full)),
            'period_start' => $periodStart,
            'period_end' => $periodEnd,
            'tariff' => $this->match('/\b(DG\d+ [A-Za-z0-9]+(?: [A-Za-z]+)*)(?=\s{2,}|\s*$)/m', $full),
            'dg_group' => $this->match('/\b(DG\d+)\b/', $full),
            'mic_kva' => ($mic = $this->match('/\bMIC:?\s+(\d+)\b/', $full)) !== null ? (int) $mic : null,
            'net_total' => $net,
            'vat_total' => $vat,
            'export_credit_total' => $exportSummary ?? $exportLines,
            'gross_total' => $gross,
            'charges' => $charges,
            'readings' => $readings,
            'warnings' => $warnings,
        ];
    }

    /**
     * Meter reading rows, e.g.
     *   24hr  Z00027782  17/06/2026  16/07/2026  45752 A  47140 FE  1  - 1388   (new layout, a reversal)
     *   24hr  Z00027782  16/07/24    44230 E     42944 E  1  -1286 kWh          (old layout)
     *
     * A detached minus ("- 1388", "- -1440") marks the reversal of a row billed
     * earlier; a plain negative is the meter estimate going backwards.
     */
    private function readings(string $left, ?string $periodEnd): array
    {
        $re = '/^\s*(?<band>[A-Za-z0-9]+(?: [A-Za-z]+)?)\s+(?<meter>[A-Z0-9]{6,})\s+(?<from>'.self::DATE.')\s+'
            .'(?:(?<to>'.self::DATE.')\s+)?'
            .'(?<prev>\d+)\s*(?<pt>[A-Z]{1,2})\s+(?<pres>\d+)\s*(?<ct>[A-Z]{1,2})\s+'
            .'(?<mult>\d+(?:\.\d+)?)\s+(?<rev>-\s+)?(?<kwh>'.self::NUM.')(?:\s*kWh)?\s*$/u';

        $rows = [];
        foreach ($this->lines($left) as $line) {
            if (! preg_match($re, $line, $m)) {
                continue;
            }

            $isReversal = ($m['rev'] ?? '') !== '';
            $kwh = $this->number($m['kwh']);

            $rows[] = [
                'meter_serial' => $m['meter'],
                'band' => $this->band($m['band']),
                'period_start' => $this->date($m['from']),
                'period_end' => ($m['to'] ?? '') !== '' ? $this->date($m['to']) : null,
                'previous_reading' => (float) $m['prev'],
                'previous_type' => $this->readType($m['pt']),
                'present_reading' => (float) $m['pres'],
                'present_type' => $this->readType($m['ct']),
                'multiplier' => (float) $m['mult'],
                'kwh' => $isReversal ? -$kwh : $kwh,
                'is_reversal' => $isReversal,
            ];
        }

        return $rows;
    }

    /**
     * A bill covering two reads of a meter prints two rows that follow on from
     * each other (the second starts at the first one's present reading). Flogas
     * leaves the first row's end date out, and sometimes prints the same dates
     * on both rows of one meter while the other meter's rows are dated properly.
     * Fill the missing end dates, and take misprinted dates from the meter on
     * the same bill whose rows are in order.
     */
    private function repairChainedRows(array $rows, array &$warnings): array
    {
        $byMeter = [];
        foreach ($rows as $i => $row) {
            if (! $row['is_reversal']) {
                $byMeter[$row['meter_serial'].'|'.$row['band']][] = $i;
            }
        }

        $chains = [];
        foreach ($byMeter as $key => $indexes) {
            if (count($indexes) < 2) {
                continue;
            }
            $presents = array_map(fn ($i) => $rows[$i]['present_reading'], $indexes);
            $heads = array_values(array_filter($indexes, fn ($i) => ! in_array($rows[$i]['previous_reading'], $presents, true)));
            if (count($heads) !== 1) {
                continue;
            }
            $chain = [$heads[0]];
            while (count($chain) < count($indexes)) {
                $last = $rows[end($chain)]['present_reading'];
                $next = null;
                foreach ($indexes as $i) {
                    if (! in_array($i, $chain, true) && $rows[$i]['previous_reading'] === $last) {
                        $next = $i;
                        break;
                    }
                }
                if ($next === null) {
                    continue 2;
                }
                $chain[] = $next;
            }
            $chains[$key] = $chain;
        }

        $inOrder = [];
        foreach ($chains as $key => $chain) {
            $sequential = true;
            for ($n = 1; $n < count($chain); $n++) {
                $sequential = $sequential && $rows[$chain[$n]]['period_start'] > $rows[$chain[$n - 1]]['period_start'];
            }
            if (! $sequential) {
                continue;
            }
            for ($n = 0; $n < count($chain) - 1; $n++) {
                $nextStart = $rows[$chain[$n + 1]]['period_start'];
                $end = $rows[$chain[$n]]['period_end'];
                if ($end === null || $end >= $nextStart) {
                    $rows[$chain[$n]]['period_end'] = Carbon::parse($nextStart)->subDay()->toDateString();
                }
            }
            $inOrder[$key] = $chain;
        }

        foreach ($chains as $key => $chain) {
            if (isset($inOrder[$key])) {
                continue;
            }
            $meter = $rows[$chain[0]]['meter_serial'];
            $donor = null;
            foreach ($inOrder as $candidate) {
                if (count($candidate) === count($chain)) {
                    $donor = $candidate;
                    break;
                }
            }
            if (! $donor) {
                $warnings[] = "Meter {$meter}: the bill prints the same dates on consecutive reading rows; check the dates";

                continue;
            }
            foreach ($chain as $n => $i) {
                $rows[$i]['period_start'] = $rows[$donor[$n]]['period_start'];
                $rows[$i]['period_end'] = $rows[$donor[$n]]['period_end'];
            }
            $warnings[] = "Meter {$meter}: the bill prints the same dates on consecutive reading rows; dates taken from meter {$rows[$donor[0]]['meter_serial']} on the same bill";
        }

        return $rows;
    }

    /**
     * Charge lines between the "Bill Information" heading and "Current Bill".
     *
     * Old layout: a line's name may sit on the line(s) above its figures.
     * New layout: the name is on the figures line and the date range or the
     * rest of a wrapped name follows below. So loose lines belong to the next
     * figures line when that line has no name of its own, otherwise to the
     * previous charge.
     */
    private function charges(string $left, ?string $issueDate, ?string $periodEnd): array
    {
        $full = '/^(?<desc>.*?)\s*(?<![\d\/.,-])(?<qty>'.self::NUM.')\s+(?<rate>'.self::NUM.')\s+'
            .'(?<vat>[A-Z]|\d+(?:\.\d+)?%)\s+(?:(?<unit>€\/[A-Za-z]+)\s+)?(?<amount>-?[\d,]*\d\.\d{2})$/u';
        // A lump with no quantity: "Credit adjustment.   R   -1,413.95"
        $lump = '/^(?<desc>[A-Za-z].*?)\s{2,}(?<vat>[A-Z]|\d+(?:\.\d+)?%)\s+(?<amount>-?[\d,]*\d\.\d{2})$/u';

        $charges = [];
        $loose = [];
        $inTable = false;

        $attachLoose = function () use (&$charges, &$loose) {
            if ($loose && $charges) {
                $charges[array_key_last($charges)]['_text'] .= ' '.implode(' ', $loose);
            }
            $loose = [];
        };

        foreach ($this->lines($left) as $raw) {
            $line = trim($raw);

            if (! $inTable) {
                $inTable = preg_match('/^(Bill Information\b|Description\s+VAT\s+Amount)/i', $line) === 1;

                continue;
            }
            if ($line === '') {
                continue;
            }
            if (preg_match('/Current Bill|Total Due|Previous Outstanding/i', $line)) {
                break;
            }
            if (preg_match('/^(Net total|VAT\b)/i', $line) || preg_match('/^'.self::NUM.'$/', $line)) {
                $attachLoose();

                continue;
            }

            if (preg_match($full, $line, $m) || preg_match($lump, $line, $m)) {
                $named = preg_match('/[A-Za-z]{3,}/', $m['desc']) === 1;
                if ($named) {
                    $attachLoose();
                    $text = $m['desc'];
                } else {
                    $text = trim(implode(' ', $loose).' '.$m['desc']);
                    $loose = [];
                }

                $charges[] = [
                    '_text' => $text,
                    '_unit' => $m['unit'] ?? '',
                    'quantity' => isset($m['qty']) && $m['qty'] !== '' ? $this->number($m['qty']) : null,
                    'rate' => isset($m['rate']) && $m['rate'] !== '' ? $this->number($m['rate']) : null,
                    'vat_rate' => $this->vatRate($m['vat']),
                    'net_amount' => $this->number($m['amount']),
                ];

                continue;
            }

            $loose[] = $line;
        }
        $attachLoose();

        $reference = $periodEnd ?? $issueDate;

        return array_map(function (array $c) use ($reference, $issueDate) {
            $text = $c['_text'];
            [$start, $end] = $this->lineDates($text, $reference, $issueDate);

            $unit = $c['_unit'] !== '' ? $c['_unit'] : ($this->match('/(€\/[A-Za-z]+)/u', $text) ?? '');
            $unit = strtolower(str_replace('€/', '', $unit)) ?: null;

            $description = preg_replace('/\(€\/[A-Za-z]+\)/u', ' ', $text);
            $description = preg_replace('/\d{2}\/\d{2}(?:\/\d{2,4})?/', ' ', $description);
            $description = trim(preg_replace('/\s+/', ' ', str_replace(' - ', ' ', $description.' ')), " -\t");

            $category = $this->category($description);

            // Old layout prints an export credit as one sentence on its own statement:
            // "Export Credit-342.72kWh @ €.185 to 21-07-25"
            if ($category === 'export_credit' && $c['quantity'] === null
                && preg_match('/(-?[\d.,]+)\s*kWh\s*@\s*(?:€\s*)?([\d.]+)(?:\s*to\s*(\d{2})-(\d{2})-(\d{2,4}))?/iu', $text, $x)) {
                $c['quantity'] = $this->number($x[1]);
                $c['rate'] = (float) $x[2];
                $unit = 'kwh';
                $description = 'Export Credit';
                $start = null;
                $end = isset($x[5]) ? $this->date("{$x[3]}/{$x[4]}/{$x[5]}") : null;
            }

            return [
                'category' => $category,
                'band' => $category === 'energy' ? $this->band($description) : null,
                'description' => $description,
                'quantity' => $c['quantity'],
                'unit' => $unit,
                'rate' => $c['rate'],
                'period_start' => $start,
                'period_end' => $end,
                'net_amount' => $c['net_amount'],
                'vat_rate' => $c['vat_rate'],
            ];
        }, $charges);
    }

    /** First and last date printed with a charge line; day/month pairs take their year from the bill. */
    private function lineDates(string $text, ?string $reference, ?string $issueDate): array
    {
        if (! preg_match_all('/(\d{2})\/(\d{2})(?:\/(\d{2,4}))?(?![\d\/])/', $text, $found, PREG_SET_ORDER)) {
            return [null, null];
        }

        $dates = [];
        foreach ($found as $f) {
            if (($f[3] ?? '') !== '') {
                $dates[] = $this->date($f[0]);

                continue;
            }
            if (! $reference) {
                continue;
            }
            $year = (int) substr($reference, 0, 4);
            $date = sprintf('%04d-%02d-%02d', $year, (int) $f[2], (int) $f[1]);
            // A day/month later than the bill itself belongs to the year before.
            if ($date > ($issueDate ?? $reference)) {
                $date = sprintf('%04d-%02d-%02d', $year - 1, (int) $f[2], (int) $f[1]);
            }
            $dates[] = $date;
        }
        $dates = array_values(array_filter($dates));

        if (! $dates) {
            return [null, null];
        }

        return [min($dates), max($dates)];
    }

    private function headerPeriod(string $full): array
    {
        if (preg_match('/Billing Period[\s\S]{0,400}?('.self::DATE.')\s*-\s*('.self::DATE.')/i', $full, $m)) {
            return [$this->date($m[1]), $this->date($m[2])];
        }

        // 2024 bills: the range is printed with no label, above the bill summary.
        $top = strstr($full, 'Bill Summary', true) ?: '';
        if (preg_match('/('.self::DATE.')\s*-\s*('.self::DATE.')/', $top, $m)) {
            return [$this->date($m[1]), $this->date($m[2])];
        }

        return [null, null];
    }

    private function coveredPeriod(array $charges, array $readings): array
    {
        $starts = $ends = [];
        foreach ($charges as $c) {
            $starts[] = $c['period_start'];
            $ends[] = $c['period_end'];
        }
        foreach ($readings as $r) {
            if (! $r['is_reversal']) {
                $starts[] = $r['period_start'];
                $ends[] = $r['period_end'];
            }
        }
        $starts = array_filter($starts);
        $ends = array_filter($ends);

        return [$starts ? min($starts) : null, $ends ? max($ends) : null];
    }

    private function category(string $description): string
    {
        $d = strtolower($description);

        return match (true) {
            str_contains($d, 'export') => 'export_credit',
            str_contains($d, 'standing') => 'standing',
            str_contains($d, 'pso') => 'pso_levy',
            (bool) preg_match('/elec(tricity)?\s*tax/', $d) => 'electricity_tax',
            (bool) preg_match('/i-?sem|capacity|market/', $d) => 'market',
            (bool) preg_match('/\bmic\b|duos|tuos|network|power factor/', $d) => 'network',
            (bool) preg_match('/unit rate|\bunits\b/', $d) => 'energy',
            str_contains($d, 'adjustment') => 'adjustment',
            default => 'other',
        };
    }

    private function band(string $text): string
    {
        $t = strtolower($text);

        return match (true) {
            str_contains($t, 'night') => 'night',
            str_contains($t, 'peak') => 'peak',
            (bool) preg_match('/\bday\b/', $t) => 'day',
            default => '24h',
        };
    }

    private function readType(string $code): string
    {
        return match (strtoupper($code)) {
            'A', 'F' => 'actual',
            'C' => 'customer',
            default => 'estimate',
        };
    }

    private function vatRate(string $token): ?float
    {
        return match (true) {
            str_ends_with($token, '%') => (float) $token,
            $token === 'R' => 9.0,
            $token === 'Z' => 0.0,
            default => null,
        };
    }

    /** Arabic ornate brackets, long dashes and ligatures the newer layout prints. */
    private function normalise(string $text): string
    {
        return strtr($text, [
            '﴾' => '(', '﴿' => ')', '–' => '-', '—' => '-', "\u{00A0}" => ' ',
            'ﬁ' => 'fi', 'ﬂ' => 'fl', 'ﬀ' => 'ff', 'ﬃ' => 'ffi', "\f" => "\n",
        ]);
    }

    private function lines(string $text): array
    {
        return preg_split('/\R/u', $text) ?: [];
    }

    private function match(string $pattern, string $text): ?string
    {
        return preg_match($pattern, $text, $m) ? trim($m[1]) : null;
    }

    private function number(string $value): float
    {
        return (float) str_replace(',', '', $value);
    }

    private function money(?string $value): ?float
    {
        return $value === null ? null : round($this->number($value), 2);
    }

    private function date(?string $value): ?string
    {
        if ($value && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return $value;
        }
        if (! $value || ! preg_match('/^(\d{2})\/(\d{2})\/(\d{2,4})$/', $value, $m)) {
            return null;
        }
        $year = strlen($m[3]) === 2 ? 2000 + (int) $m[3] : (int) $m[3];

        return checkdate((int) $m[2], (int) $m[1], $year)
            ? Carbon::create($year, (int) $m[2], (int) $m[1])->toDateString()
            : null;
    }
}
