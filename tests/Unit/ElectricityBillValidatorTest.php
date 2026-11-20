<?php

namespace Tests\Unit;

use App\Services\Electricity\ElectricityBillValidator;
use PHPUnit\Framework\TestCase;

class ElectricityBillValidatorTest extends TestCase
{
    private function bill(array $overrides = []): array
    {
        return array_replace([
            'mprn' => '10012131474', 'bill_number' => 'A1', 'issue_date' => '2026-03-10',
            'period_start' => '2026-02-01', 'period_end' => '2026-02-28',
            'net_total' => 128.0, 'vat_total' => 11.52, 'export_credit_total' => -10.0, 'gross_total' => 129.52,
            'charges' => [
                ['category' => 'energy', 'description' => 'Units', 'quantity' => 400.0, 'unit' => 'kwh', 'rate' => 0.25, 'net_amount' => 100.0],
                ['category' => 'standing', 'description' => 'Standing', 'quantity' => 28.0, 'unit' => 'day', 'rate' => 1.0, 'net_amount' => 28.0],
                ['category' => 'export_credit', 'description' => 'Export', 'quantity' => -50.0, 'unit' => 'kwh', 'rate' => 0.2, 'net_amount' => -10.0],
            ],
            'readings' => [[
                'meter_serial' => 'M1', 'band' => '24h', 'period_start' => '2026-02-01', 'period_end' => '2026-02-28',
                'previous_reading' => 1000.0, 'previous_type' => 'actual', 'present_reading' => 1400.0, 'present_type' => 'actual',
                'multiplier' => 1.0, 'kwh' => 400.0, 'is_reversal' => false,
            ]],
        ], $overrides);
    }

    private function failed(array $checks): array
    {
        return array_values(array_map(fn ($c) => $c['key'], array_filter($checks, fn ($c) => ! $c['passed'])));
    }

    public function test_a_consistent_bill_passes(): void
    {
        $validator = new ElectricityBillValidator;
        $checks = $validator->validate($this->bill());

        $this->assertSame([], $this->failed($checks));
        $this->assertTrue($validator->passes($checks));
    }

    public function test_each_sum_is_checked(): void
    {
        $validator = new ElectricityBillValidator;

        $bill = $this->bill();
        $bill['readings'][0]['kwh'] = 380.0;
        $this->assertSame(['meter_units'], $this->failed($validator->validate($bill)));

        $bill = $this->bill();
        $bill['charges'][0]['rate'] = 0.3;
        $this->assertSame(['line_maths'], $this->failed($validator->validate($bill)));

        $this->assertSame(['lines_net', 'totals'], $this->failed($validator->validate($this->bill(['net_total' => 120.0]))));
        $this->assertSame(['totals'], $this->failed($validator->validate($this->bill(['gross_total' => 139.52]))));
        $this->assertSame(['required'], $this->failed($validator->validate($this->bill(['mprn' => null]))));
    }

    public function test_unknown_words_fail(): void
    {
        $bill = $this->bill();
        $bill['charges'][1]['category'] = 'rent';
        $bill['readings'][0]['present_type'] = 'guess';

        $validator = new ElectricityBillValidator;
        $checks = $validator->validate($bill);

        $this->assertSame(['vocabulary'], $this->failed($checks));
        $this->assertFalse($validator->passes($checks));
    }

    public function test_continuity_breaks_are_notes_that_do_not_block(): void
    {
        $validator = new ElectricityBillValidator;
        $checks = $validator->validate($this->bill(), [
            'last_readings' => ['M1' => 990.0],
            'previous_period_end' => '2026-01-25',
            'invoice_total' => 150.0,
        ]);

        $this->assertSame(['reading_continuity', 'period_continuity', 'invoice_total'], $this->failed($checks));
        $this->assertTrue($validator->passes($checks));
    }

    public function test_an_estimate_the_bill_takes_back_is_not_a_break(): void
    {
        $bill = $this->bill();
        $bill['readings'][] = [
            'meter_serial' => 'M1', 'band' => '24h', 'period_start' => '2026-01-01', 'period_end' => '2026-01-31',
            'previous_reading' => 1000.0, 'previous_type' => 'actual', 'present_reading' => 1300.0, 'present_type' => 'estimate',
            'multiplier' => 1.0, 'kwh' => -300.0, 'is_reversal' => true,
        ];
        $bill['charges'][0]['quantity'] = 100.0;
        $bill['charges'][0]['net_amount'] = 25.0;
        $bill = array_replace($bill, ['net_total' => 53.0, 'vat_total' => 4.77, 'gross_total' => 47.77]);

        $checks = (new ElectricityBillValidator)->validate($bill, ['last_readings' => ['M1' => 1300.0]]);

        $this->assertSame([], $this->failed($checks));
    }

    public function test_mwh_energy_lines_count_as_kwh(): void
    {
        $this->assertSame(1500.0, (new ElectricityBillValidator)->billedKwh([
            ['category' => 'energy', 'quantity' => 1.5, 'unit' => 'mwh'],
            ['category' => 'market', 'quantity' => 999, 'unit' => 'kwh'],
        ]));
    }
}
