<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Models\Voucher;
use App\Models\VoucherTillRedemption;
use App\Models\VoucherTransaction;
use App\Services\VoucherPosProductService;
use App\Services\VoucherSyncHeartbeat;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Tests\Concerns\CreatesVoucherPosTables;
use Tests\TestCase;

/**
 * The live voucher activity screen for managers (vouchers cycle 2).
 */
class VoucherActivityTest extends TestCase
{
    use CreatesVoucherPosTables, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-09-29 12:00:00');
        Config::set('vouchers.sync.on_lookup', false);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /**
     * @param  array<int, string>  $permissions
     */
    private function userWith(string $roleName, array $permissions, string $name = 'Maya Jensen'): User
    {
        $role = Role::firstOrCreate(['name' => $roleName], ['display_name' => ucfirst($roleName)]);

        foreach ($permissions as $permissionName) {
            $role->givePermissionTo(Permission::firstOrCreate(
                ['name' => $permissionName],
                ['display_name' => $permissionName, 'module' => 'Vouchers']
            ));
        }

        return User::factory()->create(['role_id' => $role->id, 'name' => $name]);
    }

    private function manager(): User
    {
        return $this->userWith('manager', ['vouchers.redeem', 'vouchers.manage'], 'Tom Byrne');
    }

    private function employee(): User
    {
        return $this->userWith('employee', ['vouchers.redeem']);
    }

    private function voucher(string $code = 'GV7KQFM2RA9T', float $balance = 50, string $status = Voucher::STATUS_ACTIVE, ?string $posProductId = 'product'): Voucher
    {
        return Voucher::create([
            'code' => $code,
            'pos_product_id' => $posProductId === 'product' ? 'pos-'.$code : $posProductId,
            'initial_value' => $balance,
            'current_balance' => $balance,
            'status' => $status,
        ]);
    }

    private function tx(Voucher $voucher, string $type, float $amount, float $after, ?Carbon $at = null, array $extra = []): VoucherTransaction
    {
        $t = $voucher->transactions()->create($extra + [
            'type' => $type,
            'amount' => $amount,
            'balance_after' => $after,
        ]);
        $t->forceFill(['created_at' => $at ?? now(), 'updated_at' => $at ?? now()])->save();

        return $t;
    }

    private function redemption(array $attributes, ?Carbon $at = null): VoucherTillRedemption
    {
        static $n = 0;
        $n++;

        $r = VoucherTillRedemption::create($attributes + [
            'pos_ticket_id' => 'ticket-'.$n,
            'pos_product_id' => 'product-'.$n,
            'voucher_code' => 'GV7KQFM2RA9T',
            'ticket_number' => 430000 + $n,
            'sold_at' => ($at ?? now())->copy()->subSeconds(30),
            'voucher_tender' => 0,
            'status' => VoucherTillRedemption::STATUS_APPLIED,
        ]);
        $r->forceFill(['created_at' => $at ?? now(), 'updated_at' => $at ?? now()])->save();

        return $r;
    }

    /**
     * A till deduct and its redemption, as the sync writes them.
     */
    private function tillDeduct(Voucher $voucher, float $amount, float $after, int $ticket, string $status = VoucherTillRedemption::STATUS_APPLIED, float $shortfall = 0, ?Carbon $at = null): VoucherTransaction
    {
        $t = $this->tx($voucher, VoucherTransaction::TYPE_DEDUCT, $amount, $after, $at, [
            'source' => VoucherTransaction::SOURCE_TILL,
            'note' => 'Till #'.$ticket,
        ]);
        $this->redemption([
            'voucher_id' => $voucher->id,
            'voucher_code' => $voucher->code,
            'ticket_number' => $ticket,
            'voucher_tender' => $amount + $shortfall,
            'amount_deducted' => $amount,
            'shortfall' => $shortfall,
            'status' => $status,
            'voucher_transaction_id' => $t->id,
        ], $at);

        return $t;
    }

    private function feed(array $query = [])
    {
        return $this->actingAs($this->manager())->getJson(route('vouchers.activity.feed', $query));
    }

    // --- Access ---

    public function test_employees_are_forbidden_and_guests_sent_to_login(): void
    {
        $employee = $this->employee();

        $this->actingAs($employee)->get(route('vouchers.activity'))->assertForbidden();
        $this->actingAs($employee)->getJson(route('vouchers.activity.feed'))->assertForbidden();

        auth()->logout();
        $this->get(route('vouchers.activity'))->assertRedirect('/login');
    }

    public function test_managers_see_the_page(): void
    {
        $this->actingAs($this->manager())->get('/vouchers/activity')
            ->assertOk()
            ->assertViewIs('vouchers.activity')
            ->assertSee('Voucher activity')
            ->assertSee('data-feed-url="'.route('vouchers.activity.feed').'"', false)
            ->assertSee('id="voucher-activity-initial"', false);
    }

    public function test_the_activity_url_is_not_swallowed_by_the_transactions_route(): void
    {
        $response = $this->actingAs($this->manager())->get('/vouchers/activity');

        $response->assertOk();
        $this->assertSame('vouchers.activity', request()->route()->getName());
    }

    // --- Feed events ---

    public function test_the_feed_shape_and_every_kind_of_event(): void
    {
        $v = $this->voucher();
        $this->tx($v, VoucherTransaction::TYPE_ISSUE, 50, 50, now()->subMinutes(10), ['user_id' => $this->manager()->id]);
        $this->tx($v, VoucherTransaction::TYPE_DEDUCT, 5, 45, now()->subMinutes(9), ['user_id' => $this->employee()->id]);
        $this->tillDeduct($v, 20, 25, 430025, at: now()->subMinutes(8));
        $this->tillDeduct($v, 25, 0, 430026, VoucherTillRedemption::STATUS_PARTIAL, 5, now()->subMinutes(7));
        $this->tx($v, VoucherTransaction::TYPE_DEACTIVATE, 0, 0, now()->subMinutes(6));
        $this->redemption(['voucher_id' => $v->id, 'ticket_number' => 430027, 'status' => VoucherTillRedemption::STATUS_NO_TENDER], now()->subMinutes(5));

        $response = $this->feed()->assertOk()->assertJsonStructure(['server_now', 'health', 'totals', 'events']);
        $events = $response->json('events');

        $this->assertSame(
            ['exception', 'deactivate', 'redeem_till', 'redeem_till', 'redeem_manual', 'issue'],
            array_column($events, 'kind')
        );

        [$noTender, $deactivate, $partial, $applied, $manual, $issue] = $events;

        $this->assertSame('No voucher tender', $noTender['label']);
        $this->assertSame('no_tender', $noTender['status']);
        $this->assertSame('Till #430027', $noTender['who']);
        $this->assertEquals(0, $noTender['amount']);
        $this->assertNull($noTender['balance_after']);
        $this->assertFalse($noTender['reviewed']);

        $this->assertSame('Deactivated', $deactivate['label']);
        $this->assertEquals(0, $deactivate['amount']);
        $this->assertSame('Office', $deactivate['who']);

        $this->assertSame('Redeemed at till', $partial['label']);
        $this->assertSame('partial', $partial['status']);
        $this->assertEquals(5, $partial['shortfall']);
        $this->assertEquals(-25, $partial['amount']);
        $this->assertSame('Till #430026', $partial['who']);
        $this->assertFalse($partial['reviewed']);

        $this->assertSame('applied', $applied['status']);
        $this->assertEquals(-20, $applied['amount']);
        $this->assertEquals(25, $applied['balance_after']);
        $this->assertNull($applied['shortfall']);
        $this->assertNotNull($applied['sold_at']);

        $this->assertSame('Redeemed', $manual['label']);
        $this->assertEquals(-5, $manual['amount']);
        $this->assertSame('Maya Jensen', $manual['who']);
        $this->assertNull($manual['status']);

        $this->assertSame('Issued', $issue['label']);
        $this->assertEquals(50, $issue['amount']);
        $this->assertSame('Tom Byrne', $issue['who']);
        $this->assertSame(route('vouchers.transactions', $v), $issue['voucher_url']);

        // Applied and partial redemptions appear once, as their transaction.
        $this->assertCount(6, $events);
        $this->assertCount(6, array_unique(array_column($events, 'key')));
    }

    public function test_the_period_filter(): void
    {
        $v = $this->voucher();
        $this->tx($v, VoucherTransaction::TYPE_ISSUE, 50, 50, now()->subDay());
        $this->redemption(['status' => VoucherTillRedemption::STATUS_UNKNOWN], now()->subDay());
        $this->tx($v, VoucherTransaction::TYPE_DEDUCT, 1, 49, now()->subHour());

        $this->assertCount(1, $this->feed(['days' => 1])->json('events'));
        $this->assertCount(3, $this->feed(['days' => 30])->json('events'));
        $this->assertCount(3, $this->feed()->json('events')); // default 7 days

        $this->feed(['days' => 5])->assertStatus(422);
    }

    public function test_the_code_search_covers_both_kinds_of_row(): void
    {
        $a = $this->voucher('GVAAAAAAAAAA');
        $b = $this->voucher('GVBBBBBBBBBB');
        $this->tx($a, VoucherTransaction::TYPE_ISSUE, 10, 10);
        $this->tx($b, VoucherTransaction::TYPE_ISSUE, 10, 10);
        $this->redemption(['voucher_code' => 'GVAAAAAAAAAA', 'status' => VoucherTillRedemption::STATUS_NO_TENDER]);
        $this->redemption(['voucher_code' => 'GVBBBBBBBBBB', 'status' => VoucherTillRedemption::STATUS_NO_TENDER]);

        $events = $this->feed(['q' => 'AAAA'])->json('events');

        $this->assertCount(2, $events);
        $this->assertSame(['GVAAAAAAAAAA'], array_values(array_unique(array_column($events, 'code'))));
    }

    public function test_the_limit_is_respected(): void
    {
        Config::set('vouchers.activity.limit', 3);
        $v = $this->voucher();
        foreach (range(1, 5) as $i) {
            $this->tx($v, VoucherTransaction::TYPE_DEDUCT, 1, 50 - $i, now()->subMinutes(10 - $i));
        }

        $events = $this->feed()->json('events');

        $this->assertCount(3, $events);
        $this->assertEquals(45, $events[0]['balance_after']); // newest first
    }

    public function test_a_deleted_vouchers_rows_still_show_without_a_link(): void
    {
        $v = $this->voucher();
        $this->tx($v, VoucherTransaction::TYPE_ISSUE, 10, 10);
        $v->delete();

        $events = $this->feed()->json('events');

        $this->assertCount(1, $events);
        $this->assertSame('GV7KQFM2RA9T', $events[0]['code']);
        $this->assertNull($events[0]['voucher_url']);
    }

    // --- Totals ---

    public function test_totals(): void
    {
        $a = $this->voucher('GVAAAAAAAAAA', 30);
        $this->voucher('GVBBBBBBBBBB', 12.5);
        $this->voucher('GVCCCCCCCCCC', 99, Voucher::STATUS_DEACTIVATED);
        $this->voucher('GVDDDDDDDDDD', 0, Voucher::STATUS_EXHAUSTED);

        $this->tx($a, VoucherTransaction::TYPE_ISSUE, 50, 50, now()->subHours(3));
        $this->tx($a, VoucherTransaction::TYPE_ISSUE, 20, 20, now()->subDay()); // yesterday: not counted
        $this->tillDeduct($a, 12.25, 37.75, 1, at: now()->subHours(2));
        $this->tx($a, VoucherTransaction::TYPE_DEDUCT, 7.75, 30, now()->subHour());
        $this->tx($a, VoucherTransaction::TYPE_DEDUCT, 3, 27, now()->subDay());
        $this->redemption(['status' => VoucherTillRedemption::STATUS_NO_TENDER]);
        $this->redemption(['status' => VoucherTillRedemption::STATUS_REFUND, 'reviewed_at' => now()]);

        $totals = $this->feed()->json('totals');

        $this->assertEquals(20, $totals['redeemed_today']);
        $this->assertEquals(12.25, $totals['redeemed_today_till']);
        $this->assertEquals(7.75, $totals['redeemed_today_manual']);
        $this->assertEquals(50, $totals['issued_today']);
        $this->assertEquals(42.5, $totals['outstanding_balance']);
        $this->assertSame(2, $totals['active_vouchers']);
        $this->assertSame(1, $totals['unreviewed_exceptions']);
    }

    // --- Health ---

    public function test_health_is_error_when_the_last_check_failed(): void
    {
        app(VoucherSyncHeartbeat::class)->record(VoucherSyncHeartbeat::SOURCE_SCHEDULE, false, null, 'SQLSTATE[HY000] [2002] Connection refused');

        $health = $this->feed()->json('health');

        $this->assertSame('error', $health['state']);
        $this->assertSame(['The till database could not be read: SQLSTATE[HY000] [2002] Connection refused'], $health['messages']);
    }

    public function test_health_warns_when_the_scheduler_is_stale_or_never_ran(): void
    {
        $health = $this->feed()->json('health');
        $this->assertSame('warn', $health['state']);
        $this->assertSame(['The scheduler has never run the till check. This page checks the till itself while it is open.'], $health['messages']);

        app(VoucherSyncHeartbeat::class)->record(VoucherSyncHeartbeat::SOURCE_SCHEDULE, true, ['errors' => 0]);
        Carbon::setTestNow(now()->addMinutes(10));

        $health = $this->feed()->json('health');
        $this->assertSame('warn', $health['state']);
        $this->assertSame(['The scheduler has not run the till check since 29 Sep 12:00. This page checks the till itself while it is open.'], $health['messages']);
    }

    public function test_health_warns_about_last_run_errors(): void
    {
        app(VoucherSyncHeartbeat::class)->record(VoucherSyncHeartbeat::SOURCE_SCHEDULE, true, ['errors' => 2]);

        $health = $this->feed()->json('health');

        $this->assertSame('warn', $health['state']);
        $this->assertSame(['2 till ticket(s) could not be applied on the last check. See the application log.'], $health['messages']);
    }

    public function test_health_counts_vouchers_without_a_till_product(): void
    {
        app(VoucherSyncHeartbeat::class)->record(VoucherSyncHeartbeat::SOURCE_SCHEDULE, true, ['errors' => 0]);
        $this->voucher('GVAAAAAAAAAA', 10, Voucher::STATUS_ACTIVE, null);
        $this->voucher('GVBBBBBBBBBB', 0, Voucher::STATUS_INACTIVE, null);
        $this->voucher('GVCCCCCCCCCC', 0, Voucher::STATUS_EXHAUSTED, null);
        $this->voucher('GVDDDDDDDDDD', 5, Voucher::STATUS_DEACTIVATED, null);

        $health = $this->feed()->json('health');

        $this->assertSame('warn', $health['state']);
        $this->assertSame(2, $health['vouchers_without_till_product']);
        $this->assertSame(['2 voucher(s) have no till product and cannot be scanned at the till. Run vouchers:sync-pos-products.'], $health['messages']);
    }

    public function test_health_is_ok_when_the_scheduler_is_fresh_and_every_voucher_has_a_product(): void
    {
        $this->voucher();
        app(VoucherSyncHeartbeat::class)->record(VoucherSyncHeartbeat::SOURCE_SCHEDULE, true, ['errors' => 0]);

        $health = $this->feed()->json('health');

        $this->assertSame('ok', $health['state']);
        $this->assertSame([], $health['messages']);
        $this->assertSame('schedule', $health['last_check_source']);
    }

    // --- The feed runs the till check ---

    public function test_a_feed_request_applies_a_pending_till_sale(): void
    {
        Config::set('vouchers.sync.on_lookup', true);
        $this->createVoucherPosTables();
        $goods = $this->posGoods();
        $voucher = Voucher::create(['code' => 'GV7KQFM2RA9T', 'initial_value' => 50, 'current_balance' => 50, 'status' => Voucher::STATUS_ACTIVE]);
        app(VoucherPosProductService::class)->sync($voucher);
        $voucher->refresh();
        $this->posSale(430025, [
            ['product' => $goods, 'price' => 40, 'tax' => '001'],
            ['product' => $voucher->pos_product_id],
        ], [['payment' => 'paperin', 'total' => 30]], now()->subMinute());

        $response = $this->feed()->assertOk();

        $this->assertSame('redeem_till', $response->json('events.0.kind'));
        $this->assertSame('Till #430025', $response->json('events.0.who'));
        $this->assertSame('activity', $response->json('health.last_check_source'));
        $this->assertSame('20.00', $voucher->fresh()->current_balance);
    }

    public function test_the_feed_still_answers_when_the_till_database_is_missing(): void
    {
        Config::set('vouchers.sync.on_lookup', true);
        // No POS tables: the till check fails inside syncIfDue().

        $response = $this->feed()->assertOk();

        $this->assertSame('error', $response->json('health.state'));
        $this->assertFalse($response->json('health.last_check_ok'));
    }

    // --- Navigation ---

    public function test_the_activity_links_show_to_managers_only(): void
    {
        $this->actingAs($this->manager())->get(route('vouchers.index'))
            ->assertOk()
            ->assertSee('href="'.route('vouchers.activity').'"', false)
            ->assertSee('Voucher activity');

        $this->actingAs($this->employee())->get(route('vouchers.index'))
            ->assertOk()
            ->assertDontSee('href="'.route('vouchers.activity').'"', false);
    }

    public function test_the_exceptions_page_links_to_activity(): void
    {
        $this->actingAs($this->manager())->get(route('vouchers.exceptions'))
            ->assertOk()
            ->assertSee('href="'.route('vouchers.activity').'"', false);
    }
}
