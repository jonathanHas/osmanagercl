<?php

namespace Tests\Feature\Shop;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Models\Voucher;
use App\Models\VoucherTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
    use RefreshDatabase;

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

        $voucher->transactions()->create([
            'type' => VoucherTransaction::TYPE_DEDUCT,
            'amount' => 7.50,
            'balance_after' => 42.50,
            'user_id' => $employee->id,
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

        $this->assertSame('Redeemed', $response->json('history.0.label'));
        $this->assertSame(-7.5, $response->json('history.0.amount'));
        $this->assertSame('Maya Jensen', $response->json('history.0.user'));

        $this->assertSame('Issued', $response->json('history.1.label'));
        // json_encode writes a whole float as `50`, so it decodes as an int here.
        // Harmless in the browser, where Number(50) and 50.0 are the same value,
        // but assertSame would be asserting the JSON artefact rather than the amount.
        $this->assertEquals(50.0, $response->json('history.1.amount'));
        $this->assertSame('Tom Byrne', $response->json('history.1.user'));
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
}
