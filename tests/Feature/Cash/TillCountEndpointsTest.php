<?php

namespace Tests\Feature\Cash;

use App\Models\CashReconciliation;
use App\Models\Permission;
use App\Models\Role;
use App\Models\ShopDevice;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\Concerns\BuildsPosTillFixture;
use Tests\TestCase;

/**
 * The till count's shared endpoints: identify (PIN or password), forget, store.
 */
class TillCountEndpointsTest extends TestCase
{
    use BuildsPosTillFixture;
    use RefreshDatabase;

    private const TOKEN = 'device-token-for-tests-0123456789abcdef';

    private User $tablet;

    private User $katelyn;

    private User $manager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpPos();

        $employee = Role::firstOrCreate(['name' => 'employee'], ['display_name' => 'Employee']);
        $managerRole = Role::firstOrCreate(['name' => 'manager'], ['display_name' => 'Manager']);
        foreach (['cash_reconciliation.view', 'cash_reconciliation.create'] as $name) {
            $permission = Permission::firstOrCreate(['name' => $name], ['display_name' => $name, 'module' => 'Cash']);
            $employee->permissions()->syncWithoutDetaching($permission->id);
            $managerRole->permissions()->syncWithoutDetaching($permission->id);
        }

        $this->tablet = User::factory()->create(['role_id' => $employee->id, 'name' => 'Counter Tablet']);
        $this->katelyn = User::factory()->withPin('2580')->create(['role_id' => $employee->id, 'name' => 'Katelyn Byrne']);
        $this->manager = User::factory()->create(['role_id' => $managerRole->id, 'name' => 'Tess Manager', 'password' => 'secret-pass']);

        $this->closeTill('Till 2', 'M1', Carbon::parse('2026-10-14 18:47'), [['cash', 379.04], ['magcard', 926.01]]);
    }

    /** @return array<string, string> */
    /**
     * The device cookie, for withCookies(). JSON requests only carry cookies when
     * withCredentials() is also called.
     */
    private function trusted(): array
    {
        ShopDevice::factory()->token(self::TOKEN)->create();

        return [config('shop.device_cookie') => self::TOKEN];
    }

    /** @return array<string, mixed> */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'session_id' => 'M1',
            'till_id' => $this->till('Till 2')->id,
            'cash_50' => 3, 'cash_20' => 11, 'cash_10' => 21, 'cash_5' => 9,
            'cash_2' => 18, 'cash_1' => 7, 'cash_50c' => 23, 'cash_20c' => 7, 'cash_10c' => 45,
            'note_float' => 150,
            'coin_float' => null,
            'card' => 922.08,
            'money_added' => 0,
            'previous_float' => 308,
            'payments' => [],
            'note' => '',
        ], $overrides);
    }

    public function test_store_refuses_until_someone_has_identified(): void
    {
        $response = $this->actingAs($this->tablet)->postJson(route('till-count.store'), $this->payload());

        $response->assertForbidden()->assertJson(['ok' => false, 'identify' => true]);
        $this->assertSame(0, CashReconciliation::count());
    }

    public function test_an_employee_identifies_with_a_pin_on_a_trusted_device_and_the_count_is_saved_against_them(): void
    {
        $identify = $this->actingAs($this->tablet)->withCookies($this->trusted())->withCredentials()
            ->postJson(route('till-count.identify'), ['user_id' => $this->katelyn->id, 'pin' => '2580']);
        $identify->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('user.first_name', 'Katelyn');

        $this->assertDatabaseHas('shop_switch_logs', ['event' => 'identify', 'user_id' => $this->katelyn->id]);

        $response = $this->actingAs($this->tablet)->postJson(route('till-count.store'), $this->payload());

        $response->assertOk()->assertJsonPath('ok', true)->assertJsonPath('counted_by', 'Katelyn Byrne');
        $this->assertEqualsWithDelta(-1.64, $response->json('variance'), 0.001);
        $this->assertEqualsWithDelta(475.00, $response->json('bag'), 0.001);

        $row = CashReconciliation::sole();
        $this->assertSame($this->katelyn->id, $row->counted_by);
        $this->assertSame($this->tablet->id, $row->created_by);

        // The identity is spent: a second save needs a fresh one.
        $this->actingAs($this->tablet)->postJson(route('till-count.store'), $this->payload())->assertForbidden();
        $this->assertSame($this->tablet->id, $this->tablet->fresh()->id, 'The signed-in user is untouched.');
    }

    public function test_a_pin_is_refused_off_a_trusted_device_and_a_wrong_pin_is_rate_limited(): void
    {
        $this->actingAs($this->tablet)
            ->postJson(route('till-count.identify'), ['user_id' => $this->katelyn->id, 'pin' => '2580'])
            ->assertStatus(422)
            ->assertJsonPath('ok', false);

        RateLimiter::clear('pin:'.$this->katelyn->id);
        $cookies = $this->trusted();
        for ($i = 0; $i < 5; $i++) {
            $this->actingAs($this->tablet)->withCookies($cookies)->withCredentials()
                ->postJson(route('till-count.identify'), ['user_id' => $this->katelyn->id, 'pin' => '0000'])
                ->assertStatus(422);
        }

        $this->actingAs($this->tablet)->withCookies($cookies)->withCredentials()
            ->postJson(route('till-count.identify'), ['user_id' => $this->katelyn->id, 'pin' => '2580'])
            ->assertStatus(429)
            ->assertJsonPath('blocked', true);

        $this->assertDatabaseHas('shop_switch_logs', ['event' => 'identify_blocked', 'user_id' => $this->katelyn->id]);
        RateLimiter::clear('pin:'.$this->katelyn->id);
    }

    public function test_a_manager_identifies_with_a_password_anywhere(): void
    {
        $this->actingAs($this->tablet)
            ->postJson(route('till-count.identify'), ['user_id' => $this->manager->id, 'password' => 'wrong'])
            ->assertStatus(422)
            ->assertJsonPath('error', 'Wrong password, try again.');

        $this->actingAs($this->tablet)
            ->postJson(route('till-count.identify'), ['user_id' => $this->manager->id, 'password' => 'secret-pass'])
            ->assertOk()
            ->assertJsonPath('user.name', 'Tess Manager');

        $this->actingAs($this->tablet)->postJson(route('till-count.store'), $this->payload())
            ->assertOk()
            ->assertJsonPath('counted_by', 'Tess Manager');
    }

    public function test_forget_drops_the_identity(): void
    {
        $this->actingAs($this->tablet)
            ->postJson(route('till-count.identify'), ['user_id' => $this->manager->id, 'password' => 'secret-pass'])
            ->assertOk();

        $this->actingAs($this->tablet)->postJson(route('till-count.forget'))->assertOk();
        $this->actingAs($this->tablet)->postJson(route('till-count.store'), $this->payload())->assertForbidden();
    }

    public function test_someone_without_the_permission_cannot_be_the_counter(): void
    {
        $barista = User::factory()->create(['role_id' => Role::firstOrCreate(['name' => 'barista'], ['display_name' => 'Barista'])->id, 'password' => 'latte-art']);

        $this->actingAs($this->tablet)
            ->postJson(route('till-count.identify'), ['user_id' => $barista->id, 'password' => 'latte-art'])
            ->assertStatus(422)
            ->assertJsonPath('error', 'That person may not count the till.');

        $this->actingAs($barista)->postJson(route('till-count.identify'), ['user_id' => $this->manager->id, 'password' => 'secret-pass'])
            ->assertForbidden();
    }

    public function test_store_validates_and_refuses_an_open_or_foreign_session(): void
    {
        $this->actingAs($this->tablet)
            ->postJson(route('till-count.identify'), ['user_id' => $this->manager->id, 'password' => 'secret-pass'])
            ->assertOk();

        $this->actingAs($this->tablet)->postJson(route('till-count.store'), $this->payload(['cash_50' => -1]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['cash_50']);

        $this->openTill('Till 2', 'OPEN', Carbon::parse('2026-10-14 18:47'));
        $this->actingAs($this->tablet)->postJson(route('till-count.store'), $this->payload(['session_id' => 'OPEN']))
            ->assertStatus(422)
            ->assertJsonPath('error', 'The till is still open. Close it first.');

        $this->closeTill('OrgStore', 'A1', Carbon::parse('2026-10-14 17:54'));
        $this->actingAs($this->tablet)->postJson(route('till-count.store'), $this->payload(['session_id' => 'A1']))
            ->assertStatus(422)
            ->assertJsonPath('error', 'That till close no longer exists.');
    }

    public function test_a_large_variance_without_a_note_is_saved_but_flagged(): void
    {
        $this->actingAs($this->tablet)
            ->postJson(route('till-count.identify'), ['user_id' => $this->manager->id, 'password' => 'secret-pass'])
            ->assertOk();

        $response = $this->actingAs($this->tablet)->postJson(route('till-count.store'), $this->payload(['cash_50' => 10]));

        $response->assertOk()->assertJsonPath('note_missing', true);
        $this->assertEqualsWithDelta(348.36, $response->json('variance'), 0.001);
    }
}
