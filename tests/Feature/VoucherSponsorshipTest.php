<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Models\Voucher;
use App\Models\VoucherTransaction;
use App\Services\VoucherAdminService;
use App\Services\VoucherSponsorshipService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CreatesVoucherPosTables;
use Tests\TestCase;

/**
 * Sponsorship activation: an admin activates an unsold voucher without a sale
 * (charity / fundraiser) and the accounts can see it as a sponsorship.
 */
class VoucherSponsorshipTest extends TestCase
{
    use CreatesVoucherPosTables, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('vouchers.sync.on_lookup', false);
        $this->createVoucherPosTables();
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

    private function admin(): User
    {
        return $this->userWith('admin', ['vouchers.redeem', 'vouchers.manage'], 'Ada Admin');
    }

    private function forSaleVoucher(float $value = 20, string $code = 'GV45678901CD'): Voucher
    {
        return Voucher::create([
            'code' => $code,
            'face_value' => $value,
            'current_balance' => 0,
            'status' => Voucher::STATUS_INACTIVE,
        ]);
    }

    public function test_admin_activates_an_unsold_voucher_as_a_sponsorship(): void
    {
        $voucher = $this->forSaleVoucher(20);
        $admin = $this->admin();

        $this->actingAs($admin)->postJson(route('vouchers.sponsor', $voucher), [
            'amount' => '20.00',
            'beneficiary' => "St Mary's School raffle",
            'note' => 'Asked by the principal',
        ])->assertOk()->assertJson(['success' => true, 'status' => 'active', 'current_balance' => 20.0]);

        $voucher->refresh();
        $this->assertSame(Voucher::STATUS_ACTIVE, $voucher->status);
        $this->assertEquals(20, $voucher->initial_value);
        $this->assertEquals(20, $voucher->current_balance);
        $this->assertSame("St Mary's School raffle", $voucher->sponsored_to);
        $this->assertNotNull($voucher->sponsored_at);
        $this->assertTrue($voucher->isSponsored());

        $tx = $voucher->transactions()->first();
        $this->assertSame(VoucherTransaction::TYPE_ISSUE, $tx->type);
        $this->assertSame(VoucherTransaction::SOURCE_MANUAL, $tx->source);
        $this->assertEquals(20, $tx->amount);
        $this->assertSame("Sponsorship: St Mary's School raffle · Asked by the principal", $tx->note);
        $this->assertSame($admin->id, $tx->user_id);
        $this->assertTrue($tx->isSponsorship());

        // The till product no longer charges the face value: it redeems like any active voucher.
        $product = DB::connection('pos')->table('PRODUCTS')->where('CODE', $voucher->code)->first();
        $this->assertEquals(0, $product->PRICESELL);
        $this->assertStringContainsString('bal €20.00', $product->NAME);
    }

    public function test_amount_need_not_match_the_face_value_and_old_vouchers_have_none(): void
    {
        $voucher = Voucher::create(['code' => 'GV2345678901', 'current_balance' => 0, 'status' => Voucher::STATUS_INACTIVE]);

        $this->actingAs($this->admin())->postJson(route('vouchers.sponsor', $voucher), [
            'amount' => '35.50', 'beneficiary' => 'Tidy Towns',
        ])->assertOk();

        $this->assertEquals(35.5, $voucher->refresh()->current_balance);
        $this->assertSame('Sponsorship: Tidy Towns', $voucher->transactions()->first()->note);
    }

    public function test_a_beneficiary_and_a_positive_amount_are_required(): void
    {
        $voucher = $this->forSaleVoucher(20);

        $this->actingAs($this->admin())->postJson(route('vouchers.sponsor', $voucher), ['amount' => '20.00'])
            ->assertStatus(422)->assertJsonValidationErrors(['beneficiary']);

        $this->actingAs($this->admin())->postJson(route('vouchers.sponsor', $voucher), ['amount' => '0', 'beneficiary' => 'Tidy Towns'])
            ->assertStatus(422)->assertJsonValidationErrors(['amount']);

        $this->assertSame(Voucher::STATUS_INACTIVE, $voucher->refresh()->status);
        $this->assertDatabaseCount('voucher_transactions', 0);
    }

    public function test_only_an_unsold_voucher_can_be_sponsored(): void
    {
        $active = Voucher::create(['code' => 'GVACTIVE0001', 'initial_value' => 10, 'current_balance' => 10, 'status' => Voucher::STATUS_ACTIVE]);

        $this->actingAs($this->admin())->postJson(route('vouchers.sponsor', $active), ['amount' => '10', 'beneficiary' => 'Tidy Towns'])
            ->assertStatus(422)->assertJson(['success' => false]);

        $this->assertNull($active->refresh()->sponsored_at);
        $this->assertDatabaseCount('voucher_transactions', 0);

        $deleted = $this->forSaleVoucher(20, 'GVDELETED001');
        $deleted->delete();

        $this->actingAs($this->admin())->postJson(route('vouchers.sponsor', $deleted->id), ['amount' => '20', 'beneficiary' => 'Tidy Towns'])
            ->assertNotFound();
    }

    public function test_managers_are_forbidden_and_guests_sent_to_login(): void
    {
        $voucher = $this->forSaleVoucher(20);
        $manager = $this->userWith('manager', ['vouchers.redeem', 'vouchers.manage'], 'Tom Byrne');

        $this->actingAs($manager)->postJson(route('vouchers.sponsor', $voucher), ['amount' => '20', 'beneficiary' => 'Tidy Towns'])
            ->assertForbidden();

        auth()->logout();
        $this->post(route('vouchers.sponsor', $voucher), ['amount' => '20', 'beneficiary' => 'Tidy Towns'])
            ->assertRedirect('/login');

        $this->assertSame(Voucher::STATUS_INACTIVE, $voucher->refresh()->status);
    }

    public function test_list_shows_the_sponsor_button_the_badge_and_the_filter(): void
    {
        $unsold = $this->forSaleVoucher(20, 'GVUNSOLD0001');
        $sponsored = $this->forSaleVoucher(20, 'GVSPONSOR001');
        $sold = Voucher::create(['code' => 'GVSOLD000001', 'initial_value' => 10, 'current_balance' => 10, 'status' => Voucher::STATUS_ACTIVE]);
        $admin = $this->admin();

        app(VoucherSponsorshipService::class)->sponsor($sponsored, 20, 'Tidy Towns', null, $admin);

        $page = $this->actingAs($admin)->get(route('vouchers.list'))->assertOk();
        $page->assertSee(route('vouchers.sponsor', ['voucher' => '__ID__']), false);
        $page->assertSee('openSponsor('.$unsold->id.',', false);
        $page->assertDontSee('openSponsor('.$sold->id.',', false);
        $page->assertSee('Sponsorship: Tidy Towns');
        $page->assertSee('Sponsored (no sale)');

        $filtered = $this->actingAs($admin)->get(route('vouchers.list', ['status' => 'sponsored']))->assertOk();
        $filtered->assertSee('GVSPONSOR001')->assertDontSee('GVUNSOLD0001')->assertDontSee('GVSOLD000001');

        // Managers see neither the button nor the modal.
        $manager = $this->userWith('manager', ['vouchers.redeem', 'vouchers.manage'], 'Tom Byrne');
        $this->actingAs($manager)->get(route('vouchers.list'))->assertOk()
            ->assertDontSee('openSponsor(', false)
            ->assertSee('Sponsorship: Tidy Towns');
    }

    public function test_history_and_activity_label_the_issue_as_sponsored(): void
    {
        $voucher = $this->forSaleVoucher(20);
        $admin = $this->admin();
        app(VoucherSponsorshipService::class)->sponsor($voucher, 20, 'Tidy Towns', null, $admin);

        $this->actingAs($admin)->get(route('vouchers.transactions', $voucher))->assertOk()
            ->assertSee('Given to Tidy Towns')
            ->assertSee('Sponsored');

        $this->actingAs($admin)->postJson(route('vouchers.lookup'), ['code' => $voucher->code])->assertOk()
            ->assertJsonPath('history.0.label', 'Sponsored')
            ->assertJsonPath('history.0.amount', 20);

        $feed = $this->actingAs($admin)->getJson(route('vouchers.activity.feed', ['days' => 1]))->assertOk()->json();
        $labels = collect($feed['events'] ?? $feed['items'] ?? $feed)->pluck('label')->all();
        $this->assertContains('Sponsored', $labels);
    }

    public function test_issued_between_lists_the_periods_sponsorships_for_the_accounts(): void
    {
        $admin = $this->admin();
        $service = app(VoucherSponsorshipService::class);

        Carbon::setTestNow('2026-10-05 10:00:00');
        $inRange = $this->forSaleVoucher(20, 'GVINRANGE001');
        $service->sponsor($inRange, 20, 'Tidy Towns', null, $admin);

        Carbon::setTestNow('2026-09-30 23:59:00');
        $before = $this->forSaleVoucher(10, 'GVBEFORE0001');
        $service->sponsor($before, 10, 'Old club', null, $admin);

        Carbon::setTestNow('2026-10-31 23:30:00');
        $lastDay = $this->forSaleVoucher(15, 'GVLASTDAY001');
        $service->sponsor($lastDay, 15, 'Late club', null, $admin);

        $gone = $this->forSaleVoucher(50, 'GVDELETED001');
        $service->sponsor($gone, 50, 'Deleted club', null, $admin);
        $gone->delete();
        Carbon::setTestNow();

        // A sold voucher never appears, whatever its dates.
        Voucher::create(['code' => 'GVSOLD000001', 'initial_value' => 10, 'current_balance' => 10, 'status' => Voucher::STATUS_ACTIVE]);

        $codes = $service->issuedBetween(Carbon::parse('2026-10-01'), Carbon::parse('2026-10-31'))->pluck('code')->all();

        $this->assertSame(['GVINRANGE001', 'GVLASTDAY001'], $codes);
    }

    public function test_make_for_sale_clears_the_sponsorship(): void
    {
        $voucher = $this->forSaleVoucher(20);
        $admin = $this->admin();
        app(VoucherSponsorshipService::class)->sponsor($voucher, 20, 'Tidy Towns', null, $admin);

        $result = app(VoucherAdminService::class)->makeForSale([$voucher->id], 'given back', $admin);

        $this->assertSame(1, $result['done']);
        $voucher->refresh();
        $this->assertSame(Voucher::STATUS_INACTIVE, $voucher->status);
        $this->assertNull($voucher->sponsored_to);
        $this->assertNull($voucher->sponsored_at);
        $this->assertFalse($voucher->isSponsored());
    }
}
