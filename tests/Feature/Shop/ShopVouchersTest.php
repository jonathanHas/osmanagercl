<?php

namespace Tests\Feature\Shop;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Models\Voucher;
use App\Models\VoucherTillRedemption;
use App\Models\VoucherTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CreatesVoucherPosTables;
use Tests\TestCase;

/**
 * Shop mode vouchers: the screen, who may open it, and the office endpoints it
 * leans on — which had no coverage at all before this cycle.
 *
 * Only `lookup()` changes in this cycle, and only by adding keys; `deduct()` is
 * covered here for the first time because the Shop screen is the first thing to
 * depend on its refusal messages.
 */
class ShopVouchersTest extends TestCase
{
    use CreatesVoucherPosTables, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // lookup() would otherwise try the till sync against the test POS connection.
        Config::set('vouchers.sync.on_lookup', false);
    }

    /**
     * @param  array<int, string>  $permissions
     */
    private function userWith(string $roleName, array $permissions, string $name = 'Maya Jensen'): User
    {
        $role = Role::firstOrCreate(['name' => $roleName], ['display_name' => ucfirst($roleName)]);

        foreach ($permissions as $permissionName) {
            $permission = Permission::firstOrCreate(
                ['name' => $permissionName],
                ['display_name' => $permissionName, 'module' => 'Vouchers']
            );
            $role->givePermissionTo($permission);
        }

        return User::factory()->create(['role_id' => $role->id, 'name' => $name]);
    }

    /**
     * There is no VoucherFactory, so build one the way the controller would.
     */
    private function activeVoucher(float $balance = 42.50, string $code = 'GV23456789AB'): Voucher
    {
        return Voucher::create([
            'code' => $code,
            'initial_value' => $balance,
            'current_balance' => $balance,
            'status' => Voucher::STATUS_ACTIVE,
        ]);
    }

    public function test_employee_can_open_the_vouchers_screen(): void
    {
        $user = $this->userWith('employee', ['vouchers.redeem']);

        $response = $this->actingAs($user)->get('/shop/vouchers');

        $response->assertOk()
            ->assertSee('data-shell="shop"', false)
            ->assertSee('shop-scan__input', false)
            ->assertSee('data-lookup-url="'.route('vouchers.lookup').'"', false)
            ->assertSee('data-deduct-url="'.route('vouchers.deduct').'"', false)
            ->assertSee('data-activate-url=""', false)
            ->assertSee('Use full balance', false)
            ->assertDontSee(route('vouchers.activate'), false)
            ->assertDontSee('Activate', false);
    }

    public function test_manager_gets_the_activate_url(): void
    {
        $user = $this->userWith('manager', ['vouchers.redeem', 'vouchers.manage']);

        $this->actingAs($user)->get('/shop/vouchers')
            ->assertOk()
            ->assertSee('data-activate-url="'.route('vouchers.activate').'"', false)
            ->assertSee('Activate', false);
    }

    public function test_barista_is_forbidden(): void
    {
        $user = $this->userWith('barista', ['coffee.kds']);

        $this->actingAs($user)->get('/shop/vouchers')->assertForbidden();
    }

    public function test_guest_is_sent_to_login(): void
    {
        $this->get('/shop/vouchers')->assertRedirect('/login');
    }

    public function test_home_tile_links_to_the_shop_screen(): void
    {
        $user = $this->userWith('employee', ['vouchers.redeem']);

        $this->actingAs($user)->get('/shop')
            ->assertOk()
            ->assertSee('href="'.route('shop.vouchers').'"', false)
            ->assertDontSee('href="'.route('vouchers.index').'"', false);
    }

    public function test_lookup_includes_history_and_issued_at(): void
    {
        $office = $this->userWith('manager', ['vouchers.manage'], 'Tom Byrne');
        $employee = $this->userWith('employee', ['vouchers.redeem']);

        $voucher = $this->activeVoucher(42.50);

        $issue = $voucher->transactions()->create([
            'type' => VoucherTransaction::TYPE_ISSUE,
            'amount' => 50,
            'balance_after' => 50,
            'user_id' => $office->id,
        ]);
        $issue->forceFill(['created_at' => now()->subDays(3)])->save();

        $deduct = $voucher->transactions()->create([
            'type' => VoucherTransaction::TYPE_DEDUCT,
            'amount' => 7.50,
            'balance_after' => 42.50,
            'user_id' => $employee->id,
        ]);
        $deduct->forceFill(['created_at' => now()->subDay()])->save();

        // Redeemed at the till (cycle 28): no user, the ticket number instead.
        $till = $voucher->transactions()->create([
            'type' => VoucherTransaction::TYPE_DEDUCT,
            'source' => VoucherTransaction::SOURCE_TILL,
            'amount' => 10,
            'balance_after' => 32.50,
            'note' => 'Till #430025',
            'user_id' => null,
        ]);
        VoucherTillRedemption::create([
            'pos_ticket_id' => 'ticket-1',
            'pos_product_id' => 'product-1',
            'voucher_code' => $voucher->code,
            'ticket_number' => 430025,
            'sold_at' => now(),
            'voucher_tender' => 10,
            'amount_deducted' => 10,
            'status' => VoucherTillRedemption::STATUS_APPLIED,
            'voucher_id' => $voucher->id,
            'voucher_transaction_id' => $till->id,
        ]);

        $response = $this->actingAs($employee)
            ->postJson(route('vouchers.lookup'), ['code' => $voucher->code]);

        $response->assertOk()
            // The keys the office till screen already reads, unchanged.
            ->assertJson([
                'found' => true,
                'code' => $voucher->code,
                'status' => 'active',
                'initial_value' => 42.5,
                'current_balance' => 42.5,
            ]);

        $this->assertNotNull($response->json('issued_at'));

        $this->assertSame('Redeemed at till', $response->json('history.0.label'));
        $this->assertSame('Till #430025', $response->json('history.0.user'));
        $this->assertSame('till', $response->json('history.0.source'));
        $this->assertSame(430025, $response->json('history.0.ticket_number'));

        $this->assertSame('Redeemed', $response->json('history.1.label'));
        $this->assertSame(-7.5, $response->json('history.1.amount'));
        $this->assertSame('Maya Jensen', $response->json('history.1.user'));
        $this->assertSame('manual', $response->json('history.1.source'));
        $this->assertNull($response->json('history.1.ticket_number'));

        $this->assertSame('Issued', $response->json('history.2.label'));
        // json_encode writes a whole float as `50`, so it decodes as an int here.
        // Harmless in the browser, where Number(50) and 50.0 are the same value,
        // but assertSame would be asserting the JSON artefact rather than the amount.
        $this->assertEquals(50.0, $response->json('history.2.amount'));
        $this->assertSame('Tom Byrne', $response->json('history.2.user'));
    }

    public function test_lookup_reports_an_unknown_code(): void
    {
        $user = $this->userWith('employee', ['vouchers.redeem']);

        $this->actingAs($user)->postJson(route('vouchers.lookup'), ['code' => 'GV99999999ZZ'])
            ->assertOk()
            ->assertExactJson(['found' => false]);
    }

    public function test_history_names_the_office_when_no_user_is_recorded(): void
    {
        $user = $this->userWith('employee', ['vouchers.redeem']);
        $voucher = $this->activeVoucher(10);

        $voucher->transactions()->create([
            'type' => VoucherTransaction::TYPE_ISSUE,
            'amount' => 10,
            'balance_after' => 10,
            'user_id' => null,
        ]);

        $this->actingAs($user)->postJson(route('vouchers.lookup'), ['code' => $voucher->code])
            ->assertOk()
            ->assertJsonPath('history.0.user', 'Office');
    }

    public function test_deduct_and_refusals_through_the_existing_endpoint(): void
    {
        $user = $this->userWith('employee', ['vouchers.redeem']);
        $voucher = $this->activeVoucher(42.50);

        $this->actingAs($user)->postJson(route('vouchers.deduct'), [
            'code' => $voucher->code, 'amount' => '7.50',
        ])->assertOk()->assertJson(['success' => true, 'status' => 'active', 'new_balance' => 35]);

        // Over the remaining balance: refused, and the client is told the truth.
        $this->actingAs($user)->postJson(route('vouchers.deduct'), [
            'code' => $voucher->code, 'amount' => '100.00',
        ])->assertStatus(422)->assertJson([
            'success' => false,
            'message' => 'Amount exceeds the remaining balance of €35.00.',
            'current_balance' => 35,
        ]);

        $voucher->update(['status' => Voucher::STATUS_DEACTIVATED]);

        $this->actingAs($user)->postJson(route('vouchers.deduct'), [
            'code' => $voucher->code, 'amount' => '1.00',
        ])->assertStatus(422)->assertJson([
            'success' => false,
            'message' => 'Voucher has been deactivated.',
        ]);
    }

    public function test_employee_may_not_activate(): void
    {
        $user = $this->userWith('employee', ['vouchers.redeem']);

        $this->actingAs($user)->postJson(route('vouchers.activate'), [
            'code' => 'GV34567890BC', 'starting_balance' => '50.00',
        ])->assertForbidden();

        $this->assertDatabaseMissing('vouchers', ['code' => 'GV34567890BC']);
    }

    /**
     * Cycle 31. Cycle 30 made a camera read *pause* the decoder rather than
     * stop it, and the page's `shop-scan-saved` is what resumes it. Vouchers
     * never dispatched that event, so after a camera scan its frame stayed
     * frozen on "Got it" until the user closed the camera.
     *
     * Source assertion in the cycle-29 style: the camera cannot run on dev.
     */
    public function test_the_vouchers_page_resumes_the_camera_after_a_lookup(): void
    {
        $js = file_get_contents(resource_path('js/shop/vouchers.js'));

        $this->assertStringContainsString("new CustomEvent('shop-scan-saved')", $js);

        // After the lookup's announceDone(), not before it.
        $doneAt = strpos($js, 'this.announceDone();');
        $savedAt = strpos($js, "new CustomEvent('shop-scan-saved')");
        $this->assertNotFalse($doneAt);
        $this->assertNotFalse($savedAt);
        $this->assertGreaterThan($doneAt, $savedAt);
    }

    // --- Vouchers cycle 3: vouchers generated with a value are sold at the till ---

    private function forSaleVoucher(float $value = 20, string $code = 'GV45678901CD'): Voucher
    {
        return Voucher::create([
            'code' => $code,
            'face_value' => $value,
            'current_balance' => 0,
            'status' => Voucher::STATUS_INACTIVE,
        ]);
    }

    public function test_lookup_of_a_for_sale_voucher_reports_its_value(): void
    {
        $user = $this->userWith('employee', ['vouchers.redeem']);
        $voucher = $this->forSaleVoucher(20);

        $this->actingAs($user)->postJson(route('vouchers.lookup'), ['code' => $voucher->code])
            ->assertOk()
            ->assertJson([
                'found' => true,
                'status' => 'inactive',
                'face_value' => 20,
                'for_sale' => true,
            ]);

        // An active voucher is not for sale, and a valueless one has no face value.
        $active = $this->activeVoucher(10, 'GV56789012DE');
        $this->actingAs($user)->postJson(route('vouchers.lookup'), ['code' => $active->code])
            ->assertJsonPath('for_sale', false)
            ->assertJsonPath('face_value', null);
    }

    public function test_the_shop_page_has_both_the_for_sale_and_the_ask_a_manager_wording(): void
    {
        $this->actingAs($this->userWith('employee', ['vouchers.redeem']))->get('/shop/vouchers')
            ->assertOk()
            ->assertSee('Sell it at the till: scan the label as an item.', false)
            ->assertSee('Voucher not active. Please ask a manager.', false);

        $this->actingAs($this->userWith('manager', ['vouchers.redeem', 'vouchers.manage']))->get('/shop/vouchers')
            ->assertOk()
            ->assertSee('Normally sold at the till; to activate by hand, confirm the value.', false)
            ->assertSee('Not yet active. Enter the starting balance and activate.', false);
    }

    public function test_employees_still_cannot_activate_a_for_sale_voucher(): void
    {
        $voucher = $this->forSaleVoucher(20);

        $this->actingAs($this->userWith('employee', ['vouchers.redeem']))
            ->postJson(route('vouchers.activate'), ['code' => $voucher->code, 'starting_balance' => '20.00'])
            ->assertForbidden();

        $this->assertSame(Voucher::STATUS_INACTIVE, $voucher->fresh()->status);
    }

    public function test_a_manager_activating_a_for_sale_voucher_by_hand_drops_its_till_price(): void
    {
        $this->createVoucherPosTables();
        $voucher = $this->forSaleVoucher(20);
        app(\App\Services\VoucherPosProductService::class)->sync($voucher);
        $this->assertEquals(20, DB::connection('pos')->table('PRODUCTS')->where('CODE', $voucher->code)->value('PRICESELL'));

        $this->actingAs($this->userWith('manager', ['vouchers.redeem', 'vouchers.manage']))
            ->postJson(route('vouchers.activate'), ['code' => $voucher->code, 'starting_balance' => '20.00'])
            ->assertOk()
            ->assertJson(['success' => true]);

        $product = DB::connection('pos')->table('PRODUCTS')->where('CODE', $voucher->code)->first();
        $this->assertEquals(0, $product->PRICESELL);
        $this->assertSame('Gift Voucher GV45678901CD [bal €20.00]', $product->NAME);
    }

    // --- Vouchers cycle 4: deleted vouchers ---

    public function test_a_deleted_code_is_reported_deleted_and_cannot_be_activated(): void
    {
        $voucher = $this->forSaleVoucher(20);
        $voucher->delete();

        $this->actingAs($this->userWith('employee', ['vouchers.redeem']))
            ->postJson(route('vouchers.lookup'), ['code' => $voucher->code])
            ->assertOk()
            ->assertExactJson(['found' => false, 'deleted' => true]);

        $this->actingAs($this->userWith('manager', ['vouchers.redeem', 'vouchers.manage']))
            ->postJson(route('vouchers.activate'), ['code' => $voucher->code, 'starting_balance' => '20.00'])
            ->assertStatus(422)
            ->assertJson(['success' => false, 'message' => 'This voucher was deleted. It cannot be activated.']);

        $this->assertSame(1, Voucher::withTrashed()->where('code', $voucher->code)->count());
    }

    public function test_the_shop_page_has_the_deleted_wording(): void
    {
        $this->actingAs($this->userWith('employee', ['vouchers.redeem']))->get('/shop/vouchers')
            ->assertOk()
            ->assertSee('This voucher was deleted. It cannot be used.', false);
    }
}
