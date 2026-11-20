<?php

namespace Tests\Feature;

use App\Models\ElectricityBill;
use App\Models\ElectricityMeter;
use App\Models\ElectricitySupplyPoint;
use App\Models\Role;
use App\Models\User;
use App\Services\Electricity\BillTextExtractor;
use App\Services\Electricity\ElectricityBillAiReader;
use App\Services\Electricity\ElectricityBillImportService;
use App\Services\Electricity\ElectricityUsageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ElectricityTest extends TestCase
{
    use RefreshDatabase;

    private const MPRN = '10012131474';

    private function userWithRole(string $role): User
    {
        return User::factory()->create([
            'role_id' => Role::firstOrCreate(['name' => $role], ['display_name' => ucfirst($role)])->id,
        ]);
    }

    private function importer(): ElectricityBillImportService
    {
        return app(ElectricityBillImportService::class);
    }

    /** A bill for one meter: one reading row and one energy line at 25c, plus €1 a day standing. */
    private function billData(string $number, string $issued, string $from, string $to, float $previous, float $present,
        string $previousType = 'actual', string $presentType = 'actual', array $extraReadings = []): array
    {
        $readings = array_merge([[
            'meter_serial' => 'M1', 'band' => '24h', 'period_start' => $from, 'period_end' => $to,
            'previous_reading' => $previous, 'previous_type' => $previousType,
            'present_reading' => $present, 'present_type' => $presentType,
            'multiplier' => 1.0, 'kwh' => $present - $previous, 'is_reversal' => false,
        ]], $extraReadings);

        $kwh = array_sum(array_column($readings, 'kwh'));
        $days = (int) \Carbon\Carbon::parse($from)->diffInDays(\Carbon\Carbon::parse($to)) + 1;
        $net = round($kwh * 0.25 + $days, 2);

        return [
            'provider' => 'Testco', 'mprn' => self::MPRN, 'account_number' => '1', 'bill_number' => $number,
            'document_type' => 'invoice', 'issue_date' => $issued, 'period_start' => $from, 'period_end' => $to,
            'net_total' => $net, 'vat_total' => round($net * 0.09, 2), 'export_credit_total' => 0.0,
            'gross_total' => round($net + round($net * 0.09, 2), 2),
            'charges' => [
                ['category' => 'energy', 'band' => '24h', 'description' => 'Units', 'quantity' => $kwh, 'unit' => 'kwh',
                    'rate' => 0.25, 'net_amount' => round($kwh * 0.25, 2), 'vat_rate' => 9.0],
                ['category' => 'standing', 'description' => 'Standing', 'quantity' => (float) $days, 'unit' => 'day',
                    'rate' => 1.0, 'period_start' => $from, 'period_end' => $to, 'net_amount' => (float) $days, 'vat_rate' => 9.0],
            ],
            'readings' => $readings,
        ];
    }

    private function fakeExtractor(string $fixture): void
    {
        $dir = base_path('tests/Fixtures/electricity/');
        $this->app->instance(BillTextExtractor::class, new class($dir.$fixture) extends BillTextExtractor
        {
            public function __construct(private string $base) {}

            public function text(string $pdfPath, ?int $cropWidth = null): string
            {
                return file_get_contents($this->base.($cropWidth ? '.left.txt' : '.full.txt'));
            }
        });
    }

    private function tempFile(string $content): string
    {
        $path = tempnam(sys_get_temp_dir(), 'bill');
        file_put_contents($path, $content);

        return $path;
    }

    // ── Access ────────────────────────────────────────────────────────────

    public function test_managers_and_admins_can_open_the_section(): void
    {
        foreach (['admin', 'manager'] as $role) {
            $user = $this->userWithRole($role);
            $this->actingAs($user)->get(route('management.electricity.index'))->assertOk()->assertSee('No confirmed electricity bills yet');
            $this->actingAs($user)->get(route('management.electricity.bills.index'))->assertOk()->assertSee('Upload bills');
        }
    }

    public function test_other_staff_and_guests_cannot(): void
    {
        $bill = $this->importer()->store($this->billData('B1', '2026-02-03', '2026-01-01', '2026-01-31', 0, 310), 'parser');

        $this->get(route('management.electricity.index'))->assertRedirect(route('login'));

        $employee = $this->userWithRole('employee');
        $this->actingAs($employee)->get(route('management.electricity.index'))->assertForbidden();
        $this->actingAs($employee)->get(route('management.electricity.bills.show', $bill))->assertForbidden();
        $this->actingAs($employee)->post(route('management.electricity.bills.confirm', $bill))->assertForbidden();
        $this->actingAs($employee)->delete(route('management.electricity.bills.destroy', $bill))->assertForbidden();
    }

    // ── Import ────────────────────────────────────────────────────────────

    public function test_a_parsed_bill_that_passes_every_check_confirms_itself(): void
    {
        $this->fakeExtractor('flogas_new_layout_reversal');

        $outcome = $this->importer()->importFromPdf($this->tempFile('pdf-one'), null, 'bill.pdf');

        $this->assertSame('imported', $outcome['result']);
        $bill = $outcome['bill'];
        $this->assertSame(ElectricityBill::STATUS_CONFIRMED, $bill->status);
        $this->assertNull($bill->confirmed_by);
        $this->assertSame('parser', $bill->source);
        $this->assertSame('INV1001281492', $bill->bill_number);
        $this->assertEqualsWithDelta(2906, $bill->import_kwh, 0.001);
        $this->assertEqualsWithDelta(514.08, $bill->export_kwh, 0.001);
        $this->assertCount(6, $bill->charges);
        $this->assertCount(4, $bill->readings);

        $point = ElectricitySupplyPoint::where('mprn', self::MPRN)->firstOrFail();
        $this->assertSame(15, $point->mic_kva);
        $this->assertEqualsCanonicalizing(['Z00000302', 'Z00027782'], $point->meters->pluck('serial')->all());
    }

    public function test_the_same_file_or_bill_number_is_not_loaded_twice(): void
    {
        $this->fakeExtractor('flogas_new_layout_reversal');

        $this->importer()->importFromPdf($this->tempFile('pdf-one'));
        $this->assertSame('duplicate', $this->importer()->importFromPdf($this->tempFile('pdf-one'))['result']);
        // Another copy of the same bill (different bytes, e.g. re-downloaded)
        $this->assertSame('duplicate', $this->importer()->importFromPdf($this->tempFile('pdf-two'))['result']);

        $this->assertSame(1, ElectricityBill::count());
    }

    public function test_a_bill_whose_sums_do_not_add_up_waits_for_review(): void
    {
        $data = $this->billData('B1', '2026-02-03', '2026-01-01', '2026-01-31', 0, 310);
        $data['net_total'] += 5;

        $bill = $this->importer()->store($data, 'parser');

        $this->assertSame(ElectricityBill::STATUS_NEEDS_REVIEW, $bill->status);
        $this->assertTrue($bill->hasBlockingFailure());
    }

    public function test_an_ai_reading_always_waits_for_review(): void
    {
        $bill = $this->importer()->store($this->billData('B1', '2026-02-03', '2026-01-01', '2026-01-31', 0, 310), 'ai');

        $this->assertSame(ElectricityBill::STATUS_NEEDS_REVIEW, $bill->status);
        $this->assertFalse($bill->hasBlockingFailure());
    }

    public function test_a_pdf_that_is_not_an_electricity_bill_is_left_alone(): void
    {
        $this->app->instance(BillTextExtractor::class, new class extends BillTextExtractor
        {
            public function text(string $pdfPath, ?int $cropWidth = null): string
            {
                return 'Flogas LPG bulk gas delivery 1,200 litres';
            }
        });

        $outcome = $this->importer()->importFromPdf($this->tempFile('gas'));

        $this->assertSame('unsupported', $outcome['result']);
        $this->assertSame(0, ElectricityBill::count());
    }

    public function test_ai_reply_is_forced_into_the_neutral_shape(): void
    {
        $reader = app(ElectricityBillAiReader::class);

        $data = $reader->normalise($reader->decode("```json\n".json_encode([
            'provider' => 'Energia', 'mprn' => 10012131474, 'bill_number' => 'E-1', 'document_type' => 'Invoice',
            'issue_date' => '2027-01-05', 'period_start' => '05/12/2026', 'net_total' => '100.50',
            'charges' => [['category' => 'Energy', 'band' => 'Night', 'quantity' => '10', 'unit' => 'kWh', 'rate' => '0.2', 'net_amount' => '2.00']],
            'readings' => [['meter_serial' => 77, 'previous_type' => 'Actual', 'present_type' => 'Estimate', 'kwh' => '10']],
            'made_up_key' => 'ignored',
        ])."\n```"));

        $this->assertSame('10012131474', $data['mprn']);
        $this->assertSame('invoice', $data['document_type']);
        $this->assertNull($data['period_start']); // not ISO: dropped, and the checks will ask for it
        $this->assertSame(100.5, $data['net_total']);
        $this->assertSame(['energy', 'night', 10.0, 'kwh'], [
            $data['charges'][0]['category'], $data['charges'][0]['band'], $data['charges'][0]['quantity'], $data['charges'][0]['unit'],
        ]);
        $this->assertSame(['77', 'actual', 'estimate'], [
            $data['readings'][0]['meter_serial'], $data['readings'][0]['previous_type'], $data['readings'][0]['present_type'],
        ]);
        $this->assertArrayNotHasKey('made_up_key', $data);
    }

    // ── Usage and cost ────────────────────────────────────────────────────

    private function monthly(): array
    {
        return app(ElectricityUsageService::class)->monthly(ElectricitySupplyPoint::where('mprn', self::MPRN)->firstOrFail());
    }

    public function test_usage_is_spread_evenly_between_actual_reads(): void
    {
        // Read on 31 Dec (via the row's previous reading) and on 28 Feb: 590 kWh over 59 days
        $this->importer()->store($this->billData('B1', '2026-03-03', '2026-01-01', '2026-02-28', 1000, 1590), 'parser');

        $months = $this->monthly()['months'];

        $this->assertSame(['2026-01', '2026-02'], array_keys($months));
        $this->assertEqualsWithDelta(310, $months['2026-01']['kwh'], 0.1);
        $this->assertEqualsWithDelta(280, $months['2026-02']['kwh'], 0.1);
        $this->assertFalse($months['2026-01']['estimated']);
        // 25c energy + €1 a day
        $this->assertEqualsWithDelta(310 * 0.25 + 31, $months['2026-01']['net'], 0.02);
        $this->assertEqualsWithDelta(280 * 0.25 + 28, $months['2026-02']['net'], 0.02);
        $this->assertEqualsWithDelta(35.0, $months['2026-01']['cents_per_kwh'], 0.05);
    }

    public function test_an_estimate_that_is_reversed_gives_the_same_months_as_the_actual_read_alone(): void
    {
        // January billed on an estimate that was far too high (900 instead of 310)...
        $this->importer()->store(
            $this->billData('B1', '2026-02-03', '2026-01-01', '2026-01-31', 1000, 1900, 'actual', 'estimate'), 'parser');
        // ...then the February bill takes it back and bills the actual read for both months.
        $this->importer()->store($this->billData('B2', '2026-03-03', '2026-01-01', '2026-02-28', 1000, 1590, 'actual', 'actual', [[
            'meter_serial' => 'M1', 'band' => '24h', 'period_start' => '2026-01-01', 'period_end' => '2026-01-31',
            'previous_reading' => 1000.0, 'previous_type' => 'actual', 'present_reading' => 1900.0, 'present_type' => 'estimate',
            'multiplier' => 1.0, 'kwh' => -900.0, 'is_reversal' => true,
        ]]), 'parser');
        $this->assertSame(2, ElectricityBill::confirmed()->count());

        $months = $this->monthly()['months'];

        $this->assertEqualsWithDelta(310, $months['2026-01']['kwh'], 0.1);
        $this->assertEqualsWithDelta(280, $months['2026-02']['kwh'], 0.1);
        $this->assertFalse($months['2026-01']['estimated']);

        // Energy cost follows the meter rows, so the correction lands in January, not thinned over both months.
        $this->assertEqualsWithDelta(310 * 0.25, $months['2026-01']['categories']['energy'], 0.02);
        $this->assertEqualsWithDelta(280 * 0.25, $months['2026-02']['categories']['energy'], 0.02);
    }

    public function test_a_trailing_estimate_counts_until_an_actual_read_arrives(): void
    {
        $this->importer()->store($this->billData('B1', '2026-02-03', '2026-01-01', '2026-01-31', 1000, 1310), 'parser');
        $this->importer()->store(
            $this->billData('B2', '2026-03-03', '2026-02-01', '2026-02-28', 1310, 1800, 'actual', 'estimate'), 'parser');

        $months = $this->monthly()['months'];

        $this->assertEqualsWithDelta(490, $months['2026-02']['kwh'], 0.1);
        $this->assertTrue($months['2026-02']['estimated']);
        $this->assertFalse($months['2026-01']['estimated']);
    }

    public function test_bills_waiting_for_review_are_not_counted(): void
    {
        $this->importer()->store($this->billData('B1', '2026-02-03', '2026-01-01', '2026-01-31', 1000, 1310), 'ai');

        $this->assertSame([], $this->monthly()['months']);
    }

    public function test_cost_is_split_between_meters_by_their_share_of_the_kwh(): void
    {
        $data = $this->billData('B1', '2026-02-03', '2026-01-01', '2026-01-31', 1000, 1300, 'actual', 'actual', [[
            'meter_serial' => 'M2', 'band' => '24h', 'period_start' => '2026-01-01', 'period_end' => '2026-01-31',
            'previous_reading' => 50.0, 'previous_type' => 'actual', 'present_reading' => 150.0, 'present_type' => 'actual',
            'multiplier' => 1.0, 'kwh' => 100.0, 'is_reversal' => false,
        ]]);
        $this->importer()->store($data, 'parser');

        $result = $this->monthly();
        $month = $result['months']['2026-01'];
        $ids = ElectricityMeter::pluck('id', 'serial');

        $this->assertEqualsWithDelta(0.75, $month['meters'][$ids['M1']]['share'], 0.0001);
        $this->assertEqualsWithDelta(0.25, $month['meters'][$ids['M2']]['share'], 0.0001);
        $this->assertEqualsWithDelta($month['net'], $month['meters'][$ids['M1']]['net'] + $month['meters'][$ids['M2']]['net'], 0.02);
        $this->assertEqualsWithDelta($month['net'] * 0.25, $month['meters'][$ids['M2']]['net'], 0.01);

        $csv = $this->actingAs($this->userWithRole('manager'))
            ->get(route('management.electricity.meter-split', ['point' => ElectricitySupplyPoint::first()->id]))
            ->assertOk()
            ->streamedContent();
        $this->assertStringContainsString('2026-01,M2,100.0,25.00', $csv);
    }

    public function test_a_credit_that_cancels_a_bill_not_on_file_is_left_out_and_listed(): void
    {
        $this->importer()->store($this->billData('B1', '2026-02-03', '2026-01-01', '2026-01-31', 1000, 1310), 'parser');
        $credit = [
            'provider' => 'Testco', 'mprn' => self::MPRN, 'bill_number' => 'OLD-9', 'document_type' => 'credit_note',
            'issue_date' => '2026-02-10', 'period_start' => '2026-02-10', 'period_end' => '2026-02-10',
            'net_total' => -500.0, 'vat_total' => -45.0, 'export_credit_total' => 0.0, 'gross_total' => -545.0,
            'charges' => [['category' => 'adjustment', 'description' => 'Credit adjustment.', 'net_amount' => -500.0, 'vat_rate' => 9.0]],
            'readings' => [],
        ];
        $this->assertTrue($this->importer()->store($credit, 'parser')->isConfirmed());

        $result = $this->monthly();

        $this->assertSame(['2026-01'], array_keys($result['months']));
        $this->assertSame('OLD-9', $result['excluded_credits'][0]['bill_number']);
    }

    public function test_a_credit_under_the_number_of_a_bill_on_file_cancels_it_in_its_own_months(): void
    {
        $bill = $this->billData('B1', '2026-02-03', '2026-01-01', '2026-01-31', 1000, 1310);
        $this->importer()->store($bill, 'parser');
        $this->importer()->store([
            'provider' => 'Testco', 'mprn' => self::MPRN, 'bill_number' => 'B1', 'document_type' => 'credit_note',
            'issue_date' => '2026-06-13', 'period_start' => '2026-06-13', 'period_end' => '2026-06-13',
            'net_total' => -$bill['net_total'], 'vat_total' => -$bill['vat_total'], 'export_credit_total' => 0.0,
            'gross_total' => -$bill['gross_total'],
            'charges' => [['category' => 'adjustment', 'description' => 'Credit adjustment.', 'net_amount' => -$bill['net_total'], 'vat_rate' => 9.0]],
            'readings' => [],
        ], 'parser');

        $result = $this->monthly();

        $this->assertEqualsWithDelta(0, $result['months']['2026-01']['net'], 0.02);
        $this->assertArrayNotHasKey('2026-06', $result['months']);
        $this->assertSame([], $result['excluded_credits']);
    }

    // ── Review ────────────────────────────────────────────────────────────

    public function test_a_manager_corrects_and_confirms_a_bill(): void
    {
        $manager = $this->userWithRole('manager');
        $data = $this->billData('B1', '2026-02-03', '2026-01-01', '2026-01-31', 1000, 1310);
        $wrong = $data;
        $wrong['charges'][0]['net_amount'] = 70.0; // misread
        $bill = $this->importer()->store($wrong, 'parser');
        $this->assertFalse($bill->isConfirmed());

        $this->actingAs($manager)->get(route('management.electricity.bills.show', $bill))
            ->assertOk()->assertSee('Waiting for review')->assertSee('Confirm anyway');

        // Refused while the sums fail, unless overridden
        $this->actingAs($manager)->post(route('management.electricity.bills.confirm', $bill))->assertSessionHas('error');
        $this->assertFalse($bill->fresh()->isConfirmed());

        $this->actingAs($manager)->put(route('management.electricity.bills.update', $bill), $data)
            ->assertRedirect(route('management.electricity.bills.show', $bill));
        $bill->refresh();
        $this->assertSame('manual', $bill->source);
        $this->assertFalse($bill->isConfirmed());
        $this->assertFalse($bill->hasBlockingFailure());
        $this->assertEqualsWithDelta(77.5, $bill->charges->firstWhere('category', 'energy')->net_amount, 0.001);

        $this->actingAs($manager)->post(route('management.electricity.bills.confirm', $bill))
            ->assertRedirect(route('management.electricity.bills.index'));
        $bill->refresh();
        $this->assertTrue($bill->isConfirmed());
        $this->assertSame($manager->id, $bill->confirmed_by);

        $this->actingAs($manager)->get(route('management.electricity.index'))->assertOk()->assertSee('Electricity used each month');
    }

    public function test_the_bills_list_links_straight_to_the_pdf(): void
    {
        \Illuminate\Support\Facades\Storage::fake('private');
        $manager = $this->userWithRole('manager');
        $bill = $this->importer()->store(
            $this->billData('B1', '2026-02-03', '2026-01-01', '2026-01-31', 1000, 1310), 'parser', null,
            ['file_path' => 'electricity/2026/b1.pdf']
        );
        $without = $this->importer()->store($this->billData('B2', '2026-03-03', '2026-02-01', '2026-02-28', 1310, 1600), 'parser');
        \Illuminate\Support\Facades\Storage::disk('private')->put('electricity/2026/b1.pdf', '%PDF-1.4 test');

        $this->actingAs($manager)->get(route('management.electricity.bills.index'))
            ->assertOk()
            ->assertSee(route('management.electricity.bills.pdf', $bill))
            ->assertDontSee(route('management.electricity.bills.pdf', $without))
            ->assertSee('not on disk');

        $this->actingAs($manager)->get(route('management.electricity.bills.pdf', $bill))
            ->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->actingAs($manager)->get(route('management.electricity.bills.pdf', $without))->assertNotFound();
        $this->actingAs($this->userWithRole('employee'))->get(route('management.electricity.bills.pdf', $bill))->assertForbidden();
    }

    public function test_a_manager_can_name_a_meter_and_remove_a_bill(): void
    {
        $manager = $this->userWithRole('manager');
        $bill = $this->importer()->store($this->billData('B1', '2026-02-03', '2026-01-01', '2026-01-31', 1000, 1310), 'parser');
        $meter = ElectricityMeter::firstOrFail();

        $this->actingAs($manager)->patch(route('management.electricity.meters.update', $meter), ['name' => 'Shop'])->assertRedirect();
        $this->assertSame('Shop', $meter->fresh()->label);
        $this->actingAs($manager)->get(route('management.electricity.index'))->assertSee('Shop kWh');

        $this->actingAs($manager)->delete(route('management.electricity.bills.destroy', $bill))
            ->assertRedirect(route('management.electricity.bills.index'));
        $this->assertSame(0, ElectricityBill::count());
    }
}
