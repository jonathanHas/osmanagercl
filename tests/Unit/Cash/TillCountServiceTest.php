<?php

namespace Tests\Unit\Cash;

use App\Models\CashReconciliation;
use App\Models\CustomerPayment;
use App\Models\Role;
use App\Models\Till;
use App\Models\User;
use App\Services\Cash\TillCountService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\BuildsPosTillFixture;
use Tests\TestCase;

/**
 * The till count's arithmetic and persistence, on an in-memory POS.
 */
class TillCountServiceTest extends TestCase
{
    use BuildsPosTillFixture;
    use RefreshDatabase;

    private TillCountService $service;

    private User $staff;

    private User $device;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpPos(withLegacy: true);
        $this->service = app(TillCountService::class);

        $role = Role::firstOrCreate(['name' => 'employee'], ['display_name' => 'Employee']);
        $this->staff = User::factory()->create(['role_id' => $role->id, 'name' => 'Katelyn Byrne']);
        $this->device = User::factory()->create(['role_id' => $role->id, 'name' => 'Counter Tablet']);
    }

    /** @return array<string, mixed> */
    private function countData(array $overrides = []): array
    {
        return array_merge([
            'cash_50' => 10, 'cash_20' => 5, 'cash_10' => 2, 'cash_5' => 1, // 625 notes
            'cash_2' => 10, 'cash_1' => 5, 'cash_50c' => 4, 'cash_20c' => 5, 'cash_10c' => 10, // 29 coins
            'note_float' => 150,
            'coin_float' => null,
            'card' => 900,
            'money_added' => 0,
            'payments' => [],
            'note' => '',
        ], $overrides);
    }

    public function test_pos_totals_fold_refunds_into_cash_and_card_and_keep_the_rest_as_information(): void
    {
        $this->closeTill('Till 2', 'M1', Carbon::parse('2026-10-14 18:47'), [
            ['cash', 300], ['cash', 100.50], ['cashrefund', -20],
            ['magcard', 800], ['magcardrefund', -10.12],
            ['paperin', 25], ['free', 12], ['debt', 40], ['debtpaid', -40], ['bank', 5],
        ]);

        $totals = $this->service->posTotals('M1');

        $this->assertSame(380.50, $totals['cash']);
        $this->assertSame(789.88, $totals['card']);
        $this->assertSame(25.0, $totals['voucher']);
        $this->assertSame(12.0, $totals['free']);
        $this->assertSame(40.0, $totals['debt']);
        $this->assertSame(-40.0, $totals['debtpaid']);
        $this->assertSame(['bank' => 5.0], $totals['other']);
    }

    public function test_saving_a_count_computes_totals_bag_and_variance_against_the_previous_counted_float(): void
    {
        $till = $this->till('Till 2');
        $this->closeTill('Till 2', 'M0', Carbon::parse('2026-10-13 18:39'), [['cash', 400]]);
        $this->closeTill('Till 2', 'M1', Carbon::parse('2026-10-14 18:47'), [['cash', 379.04], ['magcard', 926.01]]);

        // Yesterday: counted, €190 notes + €118 coins left in.
        $this->service->save($this->service->session('M0'), $till, $this->staff, $this->device, $this->countData([
            'cash_50' => 10, 'cash_20' => 0, 'cash_10' => 0, 'cash_5' => 0,
            'cash_2' => 50, 'cash_1' => 18, 'cash_50c' => 0, 'cash_20c' => 0, 'cash_10c' => 0,
            'note_float' => 190, 'coin_float' => null,
        ]));

        $today = $this->service->save($this->service->session('M1'), $till, $this->staff, $this->device, $this->countData([
            'cash_50' => 3, 'cash_20' => 11, 'cash_10' => 21, 'cash_5' => 9, // 150+220+210+45 = 625
            'cash_2' => 18, 'cash_1' => 7, 'cash_50c' => 23, 'cash_20c' => 7, 'cash_10c' => 45, // 36+7+11.5+1.4+4.5 = 60.40
            'note_float' => 150, 'coin_float' => null, 'card' => 922.08,
        ]));

        $this->assertSame(685.40, (float) $today->total_cash_counted);
        $this->assertSame(60.40, (float) $today->coin_float);          // all coins kept
        $this->assertSame(308.00, (float) $today->previous_float);     // 190 + 118 from yesterday
        $this->assertSame(475.00, $today->calculateAvailableToLodge()); // 685.40 − 150 − 60.40
        $this->assertSame(379.04, (float) $today->pos_cash_total);
        $this->assertSame(926.01, (float) $today->pos_card_total);
        $this->assertSame(-1.64, (float) $today->variance);            // 685.40 − 308 − 379.04
        $this->assertSame($this->staff->id, $today->counted_by);
        $this->assertSame($this->device->id, $today->created_by);
        $this->assertSame('app', $today->source);
        $this->assertNotNull($today->counted_at);
        $this->assertSame($till->id, $today->till_id);
    }

    public function test_supplier_payments_money_added_and_customer_cash_enter_the_variance(): void
    {
        $till = $this->till('OrgStore');
        $this->closeTill('OrgStore', 'M1', Carbon::parse('2026-10-14 17:54'), [['cash', 500]]);

        CustomerPayment::factory()->create([
            'method' => CustomerPayment::METHOD_CASH_TILL,
            'amount' => 40,
            'payment_date' => '2026-10-14',
            'till_id' => (string) $till->id,
            'till_name' => 'OrgStore',
        ]);

        $r = $this->service->save($this->service->session('M1'), $till, $this->staff, $this->device, $this->countData([
            'cash_50' => 12, 'cash_20' => 0, 'cash_10' => 0, 'cash_5' => 0, // 600
            'cash_2' => 0, 'cash_1' => 0, 'cash_50c' => 0, 'cash_20c' => 0, 'cash_10c' => 0,
            'note_float' => 100, 'previous_float' => 100, // nothing before this: typed
            'money_added' => 50,
            'payments' => [['supplier_id' => 'S2', 'amount' => 90]],
        ]));

        // 600 + 90 − 100 − 50 = 540 day's cash; expected 500 + 40 = 540 → balances.
        $this->assertSame(0.0, (float) $r->variance);
        $this->assertSame(100.0, (float) $r->previous_float);
        $this->assertCount(1, $r->payments);
        $this->assertSame('S2', $r->payments->first()->supplier_id);
    }

    public function test_resaving_keeps_payment_rows_by_id_and_edits_the_note_in_place(): void
    {
        $till = $this->till('OrgStore');
        $this->closeTill('OrgStore', 'M1', Carbon::parse('2026-10-14 17:54'), [['cash', 500]]);
        $session = $this->service->session('M1');

        $first = $this->service->save($session, $till, $this->staff, $this->device, $this->countData([
            'payments' => [['supplier_id' => 'S1', 'amount' => 30], ['supplier_id' => 'S2', 'amount' => 20]],
            'note' => 'Short €5',
        ]));
        $keep = $first->payments->firstWhere('supplier_id', 'S1');
        $keep->forceFill(['created_at' => '2026-10-14 18:00:00'])->saveQuietly();

        $second = $this->service->save($session, $till, $this->staff, $this->device, $this->countData([
            'payments' => [['id' => $keep->id, 'supplier_id' => 'S1', 'amount' => 35]],
            'note' => 'Short €5, found later',
        ]));

        $this->assertCount(1, $second->payments);
        $this->assertSame($keep->id, $second->payments->first()->id);
        $this->assertSame(35.0, (float) $second->payments->first()->amount);
        $this->assertSame('2026-10-14 18:00:00', $second->payments->first()->created_at->toDateTimeString());
        $this->assertSame(1, $second->notes()->count());
        $this->assertSame('Short €5, found later', $second->latestNote->message);
        $this->assertSame(1, CashReconciliation::count());

        $third = $this->service->save($session, $till, $this->staff, $this->device, $this->countData(['note' => '']));
        $this->assertSame(0, $third->notes()->count());
    }

    public function test_previous_float_falls_back_to_the_old_system_and_then_to_nothing(): void
    {
        $till = $this->till('Till 2');
        $this->closeTill('Till 2', 'M0', Carbon::parse('2026-10-13 18:39'));
        $this->closeTill('Till 2', 'M1', Carbon::parse('2026-10-14 18:47'));

        $this->assertNull($this->service->previousFloat($till, $this->service->session('M1')));

        DB::connection('pos')->table('money')->insert(['ID' => 'M0', 'noteFloat' => 190, 'coinFloat' => 118]);

        $previous = $this->service->previousFloat($till, $this->service->session('M1'));
        $this->assertSame('legacy', $previous['source']);
        $this->assertSame(308.0, $previous['amount']);
        $this->assertSame('2026-10-13', $previous['date']);
    }

    public function test_previous_float_is_the_closest_earlier_counted_session_of_the_same_till_only(): void
    {
        $tillA = $this->till('OrgStore');
        $tillB = $this->till('Till 2');
        $this->closeTill('OrgStore', 'A0', Carbon::parse('2026-10-13 17:54'));
        $this->closeTill('Till 2', 'B0', Carbon::parse('2026-10-13 18:39'));
        $this->closeTill('Till 2', 'B1', Carbon::parse('2026-10-14 12:00'));
        $this->closeTill('Till 2', 'B2', Carbon::parse('2026-10-14 18:47'));

        $this->service->save($this->service->session('A0'), $tillA, $this->staff, $this->device, $this->countData(['note_float' => 999]));
        $this->service->save($this->service->session('B0'), $tillB, $this->staff, $this->device, $this->countData(['note_float' => 100, 'coin_float' => 10]));
        $this->service->save($this->service->session('B1'), $tillB, $this->staff, $this->device, $this->countData(['note_float' => 200, 'coin_float' => 20]));

        $previous = $this->service->previousFloat($tillB, $this->service->session('B2'));

        $this->assertSame('count', $previous['source']);
        $this->assertSame(220.0, $previous['amount']); // the midday close, not the other till's 999
    }

    public function test_importing_the_old_systems_count_converts_totals_to_counts_and_dates_payments_to_the_close(): void
    {
        $till = $this->till('Till 2');
        $end = Carbon::parse('2026-10-14 18:47:52');
        $this->closeTill('Till 2', 'M1', $end, [['cash', 379.04], ['magcard', 926.01]]);

        $this->assertNull($this->service->importFromLegacy($this->service->session('M1'), $till), 'No money row: nothing is created.');
        $this->assertSame(0, CashReconciliation::count());

        DB::connection('pos')->table('money')->insert([
            'ID' => 'M1', 'cash50' => 150, 'cash20' => 220, 'cash10' => 210, 'cash5' => 45,
            'cash2' => 36, 'cash1' => 7, 'cash50c' => 11.5, 'cash20c' => 1.4, 'cash10c' => 4.5,
            'noteFloat' => 150, 'coinFloat' => 60.40, 'card' => 922.08, 'moneyAdded' => 0,
        ]);
        DB::connection('pos')->table('payeePayments')->insert(['ID' => 'P1', 'payeeID' => 'S1', 'amount' => 12.5, 'sequence' => 0, 'closedCashID' => 'M1']);
        DB::connection('pos')->table('dayNotes')->insert(['ID' => 'N1', 'message' => 'All fine', 'closedCashID' => 'M1']);

        $r = $this->service->importFromLegacy($this->service->session('M1'), $till);

        $this->assertSame(3, $r->cash_50);
        $this->assertSame(45, $r->cash_10c); // 4.50 / 0.10, not truncated to 44
        $this->assertSame(685.40, (float) $r->total_cash_counted);
        $this->assertSame('legacy', $r->source);
        $this->assertSame($end->toDateTimeString(), $r->counted_at->toDateTimeString());
        $this->assertNull($r->counted_by);
        $this->assertSame($end->toDateTimeString(), $r->payments->first()->created_at->toDateTimeString());
        $this->assertSame('All fine', $r->latestNote->message);
        // No previous float known → 0; variance = 685.40 + 12.5 − 0 − 379.04
        $this->assertSame(318.86, (float) $r->variance);
    }

    public function test_recalculate_rebases_a_stored_variance_on_the_right_previous_float(): void
    {
        $till = $this->till('Till 2');
        $this->closeTill('Till 2', 'M0', Carbon::parse('2026-10-13 18:39'), [['cash', 400]]);
        $this->closeTill('Till 2', 'M1', Carbon::parse('2026-10-14 18:47'), [['cash', 379.04]]);

        $this->service->save($this->service->session('M0'), $till, $this->staff, $this->device, $this->countData(['note_float' => 190, 'coin_float' => 118]));
        $today = $this->service->save($this->service->session('M1'), $till, $this->staff, $this->device, $this->countData([
            'cash_50' => 3, 'cash_20' => 11, 'cash_10' => 21, 'cash_5' => 9,
            'cash_2' => 18, 'cash_1' => 7, 'cash_50c' => 23, 'cash_20c' => 7, 'cash_10c' => 45,
            'note_float' => 150,
        ]));

        // Simulate the old page's damage: a frozen variance against no previous float.
        $today->forceFill(['variance' => 306.36, 'previous_float' => 0])->saveQuietly();

        $result = $this->service->recalculate($today->fresh());

        $this->assertSame(306.36, $result['before']);
        $this->assertSame(-1.64, $result['after']);
        $this->assertSame(308.0, $result['previous_after']);
        $this->assertSame(-1.64, (float) $today->fresh()->variance);
    }

    public function test_tills_are_registered_from_recent_closes_with_stable_ids(): void
    {
        $this->closeTill('OrgStore', 'A', Carbon::now()->subDay());
        $this->closeTill('Till 2', 'B', Carbon::now()->subDay());
        $this->closeTill('Cassiopeia', 'C', Carbon::now()->subYears(2));

        $options = $this->service->tillOptions();

        $this->assertSame(['OrgStore', 'Till 2'], $options->values()->all());
        $this->assertSame(Till::where('pos_host', 'OrgStore')->value('id'), $options->keys()->first());
    }

    public function test_statuses_say_which_tills_closed_today_and_whether_they_were_counted(): void
    {
        $date = Carbon::parse('2026-10-14');
        $tillB = $this->till('Till 2');
        $this->closeTill('OrgStore', 'A', Carbon::parse('2026-10-14 17:54'));
        $this->closeTill('Till 2', 'B', Carbon::parse('2026-10-14 18:47'));
        $this->openTill('Till 2', 'B-open', Carbon::parse('2026-10-14 18:47'));

        $this->service->save($this->service->session('B'), $tillB, $this->staff, $this->device, $this->countData());

        $statuses = collect($this->service->tillStatuses($date))->keyBy('name');

        $this->assertFalse($statuses['OrgStore']['counted']);
        $this->assertSame('17:54', $statuses['OrgStore']['closed_at']);
        $this->assertTrue($statuses['Till 2']['counted']);
        $this->assertSame('Katelyn Byrne', $statuses['Till 2']['counted_by']);

        $tomorrow = collect($this->service->tillStatuses($date->copy()->addDay()))->keyBy('name');
        $this->assertTrue($tomorrow['Till 2']['open']);
        $this->assertNull($tomorrow['Till 2']['session_id']);
    }
}
