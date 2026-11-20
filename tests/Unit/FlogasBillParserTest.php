<?php

namespace Tests\Unit;

use App\Services\Electricity\ElectricityBillValidator;
use App\Services\Electricity\FlogasBillParser;
use PHPUnit\Framework\TestCase;

/**
 * Fixtures are pdftotext output of real bills: "<name>.full.txt" is the whole
 * page, "<name>.left.txt" the left 370 points (see FlogasBillParser).
 */
class FlogasBillParserTest extends TestCase
{
    private function parse(string $name): array
    {
        $dir = __DIR__.'/../Fixtures/electricity/';

        return (new FlogasBillParser)->parse(
            file_get_contents($dir.$name.'.full.txt'),
            file_get_contents($dir.$name.'.left.txt')
        );
    }

    private function assertPassesChecks(array $bill): void
    {
        $validator = new ElectricityBillValidator;
        $failed = array_filter($validator->validate($bill), fn ($c) => ! $c['passed']);
        $this->assertSame([], array_values(array_map(fn ($c) => $c['label'].': '.$c['detail'], $failed)));
    }

    public function test_new_layout_with_reversed_estimate(): void
    {
        $bill = $this->parse('flogas_new_layout_reversal');

        $this->assertSame('Flogas', $bill['provider']);
        $this->assertSame('10012131474', $bill['mprn']);
        $this->assertSame('400114994', $bill['account_number']);
        $this->assertSame('INV1001281492', $bill['bill_number']);
        $this->assertSame('invoice', $bill['document_type']);
        $this->assertSame('2026-11-13', $bill['issue_date']);
        $this->assertSame(['2026-07-17', '2026-08-18'], [$bill['period_start'], $bill['period_end']]);
        $this->assertSame(15, $bill['mic_kva']);
        $this->assertSame([934.19, 84.08, -95.10, 923.17],
            [$bill['net_total'], $bill['vat_total'], $bill['export_credit_total'], $bill['gross_total']]);

        // Four meter rows, two of them taking back last month's estimate: 5660 + 22 - 1388 - 1388
        $this->assertCount(4, $bill['readings']);
        $this->assertSame([-1388.0, -1388.0, 5660.0, 22.0], array_column($bill['readings'], 'kwh'));
        $this->assertSame([true, true, false, false], array_column($bill['readings'], 'is_reversal'));
        $this->assertEqualsWithDelta(2906, array_sum(array_column($bill['readings'], 'kwh')), 0.001);
        $this->assertSame('2026-07-16', $bill['readings'][0]['period_end']);
        $this->assertSame(['actual', 'estimate'], [$bill['readings'][0]['previous_type'], $bill['readings'][0]['present_type']]);
        $this->assertSame(['actual', 'actual'], [$bill['readings'][2]['previous_type'], $bill['readings'][2]['present_type']]);

        $this->assertSame(
            ['energy', 'electricity_tax', 'market', 'pso_levy', 'standing', 'export_credit'],
            array_column($bill['charges'], 'category')
        );
        $energy = $bill['charges'][0];
        $this->assertSame([2906.0, 'kwh', 0.2549, 740.74, 9.0, '24h'],
            [$energy['quantity'], $energy['unit'], $energy['rate'], $energy['net_amount'], $energy['vat_rate'], $energy['band']]);
        $this->assertSame(['2026-06-17', '2026-08-18'], [$energy['period_start'], $energy['period_end']]);

        $pso = $bill['charges'][3];
        $this->assertSame(['2026-07-31', '2026-07-31', 'month'], [$pso['period_start'], $pso['period_end'], $pso['unit']]);

        // "17/06 - 18/08" has no year: it comes from the bill
        $export = $bill['charges'][5];
        $this->assertSame([-514.08, 0.185, -95.10, 0.0], [$export['quantity'], $export['rate'], $export['net_amount'], $export['vat_rate']]);
        $this->assertSame(['2026-06-17', '2026-08-18'], [$export['period_start'], $export['period_end']]);

        $this->assertPassesChecks($bill);
    }

    public function test_old_layout(): void
    {
        $bill = $this->parse('flogas_old_layout');

        $this->assertSame('5352450', $bill['bill_number']);
        $this->assertSame('50148307', $bill['account_number']);
        $this->assertSame('2025-09-23', $bill['issue_date']);
        $this->assertSame(['2025-08-14', '2025-09-15'], [$bill['period_start'], $bill['period_end']]);
        $this->assertSame('DG5 24HR NBRCB', $bill['tariff']);
        $this->assertSame([1128.41, 101.55, 1229.96], [$bill['net_total'], $bill['vat_total'], $bill['gross_total']]);

        $this->assertCount(2, $bill['readings']);
        // No "To" column in this layout: rows end with the bill
        $this->assertSame('2025-09-15', $bill['readings'][0]['period_end']);
        $this->assertSame('estimate', $bill['readings'][0]['present_type']);

        $this->assertEqualsWithDelta(3636, (new ElectricityBillValidator)->billedKwh($bill['charges']), 0.001);
        $tax = array_values(array_filter($bill['charges'], fn ($c) => $c['category'] === 'electricity_tax'))[0];
        $this->assertSame([3.636, 'mwh', 3.64], [$tax['quantity'], $tax['unit'], $tax['net_amount']]);

        $this->assertPassesChecks($bill);
    }

    public function test_old_layout_with_a_rate_change_inside_the_period(): void
    {
        $bill = $this->parse('flogas_old_split_rates');

        // 2024 bills print the MPRN and the period without their labels
        $this->assertSame('10012131474', $bill['mprn']);
        $this->assertSame(['2024-07-16', '2024-08-13'], [$bill['period_start'], $bill['period_end']]);

        // The line's name is on the row above its figures; dates have no year
        $energy = array_values(array_filter($bill['charges'], fn ($c) => $c['category'] === 'energy' && $c['quantity'] > 0));
        $this->assertCount(2, $energy);
        $this->assertSame('Unit Rate 24Hr (1st 131 kWh per 24Hr)', $energy[0]['description']);
        $this->assertSame([1647.0, 0.2829, '2024-07-16', '2024-07-31'],
            [$energy[0]['quantity'], $energy[0]['rate'], $energy[0]['period_start'], $energy[0]['period_end']]);
        $this->assertSame([1338.0, 0.2309, '2024-08-01', '2024-08-13'],
            [$energy[1]['quantity'], $energy[1]['rate'], $energy[1]['period_start'], $energy[1]['period_end']]);

        // A plain negative is the estimate going backwards, not a reversal
        $this->assertSame([-1286.0, false], [$bill['readings'][0]['kwh'], $bill['readings'][0]['is_reversal']]);

        $this->assertPassesChecks($bill);
    }

    public function test_credit_note(): void
    {
        $bill = $this->parse('flogas_credit_note');

        $this->assertSame('CN1000006159', $bill['bill_number']);
        $this->assertSame('credit_note', $bill['document_type']);
        $this->assertSame([-60.37, -5.43, -65.80], [$bill['net_total'], $bill['vat_total'], $bill['gross_total']]);
        $this->assertEqualsWithDelta(-204, array_sum(array_column($bill['readings'], 'kwh')), 0.001);

        // Names wrap over three lines in this layout
        $this->assertSame('Unit Rate 24Hr (1st 131 kWh per 24Hr)', $bill['charges'][0]['description']);
        $this->assertSame('kwh', $bill['charges'][0]['unit']);
        $tax = array_values(array_filter($bill['charges'], fn ($c) => $c['category'] === 'electricity_tax'))[0];
        $this->assertSame('mwh', $tax['unit']);

        $this->assertPassesChecks($bill);
    }

    public function test_bill_with_a_one_off_charge_at_another_vat_rate(): void
    {
        $bill = $this->parse('flogas_mixed_vat');

        $fee = array_values(array_filter($bill['charges'], fn ($c) => $c['category'] === 'other'))[0];
        $this->assertSame(['Contract Exit Fee', 1219.51, 23.0], [$fee['description'], $fee['net_amount'], $fee['vat_rate']]);
        $this->assertSame([1264.56, 284.54, -1.51, 1547.59],
            [$bill['net_total'], $bill['vat_total'], $bill['export_credit_total'], $bill['gross_total']]);

        $this->assertPassesChecks($bill);
    }

    public function test_lump_credit_that_cancels_a_bill(): void
    {
        $bill = $this->parse('flogas_credit_adjustment');

        $this->assertSame('credit_note', $bill['document_type']);
        $this->assertSame('2025-06-13', $bill['issue_date']);
        $this->assertCount(1, $bill['charges']);
        $this->assertSame(['adjustment', null, -1413.95], [
            $bill['charges'][0]['category'], $bill['charges'][0]['quantity'], $bill['charges'][0]['net_amount'],
        ]);
        $this->assertSame([], $bill['readings']);

        $this->assertPassesChecks($bill);
    }

    public function test_old_layout_export_credit_statement(): void
    {
        $bill = $this->parse('flogas_export_statement');

        // Printed as the net bill; in the neutral shape an export credit is outside the net total
        $this->assertSame([0.0, -63.40, -63.40], [$bill['net_total'], $bill['export_credit_total'], $bill['gross_total']]);
        $export = $bill['charges'][0];
        $this->assertSame(['export_credit', -342.72, 0.185, 'kwh', null, '2025-07-21'], [
            $export['category'], $export['quantity'], $export['rate'], $export['unit'], $export['period_start'], $export['period_end'],
        ]);

        $this->assertPassesChecks($bill);
    }

    public function test_rows_printed_with_the_same_dates_take_them_from_the_other_meter(): void
    {
        $bill = $this->parse('flogas_misprinted_row_dates');

        $rows = [];
        foreach ($bill['readings'] as $r) {
            $rows[$r['meter_serial']][] = [$r['previous_reading'], $r['present_reading'], $r['period_start'], $r['period_end']];
        }
        sort($rows['Z00000302']);
        sort($rows['Z00027782']);

        // Z00027782 is printed properly; Z00000302 has 24/10 - 15/12 on both of its rows
        $this->assertSame([
            [44148.0, 44567.0, '2025-10-24', '2025-12-15'],
            [44567.0, 45167.0, '2025-12-16', '2026-02-17'],
        ], $rows['Z00027782']);
        $this->assertSame([
            [249076.0, 255162.0, '2025-10-24', '2025-12-15'],
            [255162.0, 262251.0, '2025-12-16', '2026-02-17'],
        ], $rows['Z00000302']);
        $this->assertStringContainsString('Z00000302', $bill['warnings'][0]);

        $this->assertPassesChecks($bill);
    }

    public function test_it_only_claims_flogas_electricity_bills(): void
    {
        $parser = new FlogasBillParser;

        $this->assertTrue($parser->supports("www.flogas.ie\nMPRN: 10012131474"));
        $this->assertFalse($parser->supports('Flogas LPG delivery docket'));
        $this->assertFalse($parser->supports('Electric Ireland MPRN: 10012131474'));
    }
}
