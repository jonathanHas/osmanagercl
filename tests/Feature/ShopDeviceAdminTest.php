<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\ShopDevice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The office view of trusted shop-floor devices and the switch log (cycle 27).
 */
class ShopDeviceAdminTest extends TestCase
{
    use RefreshDatabase;

    private const TOKEN = 'device-token-for-tests-0123456789abcdef';

    private function user(string $roleName): User
    {
        $role = Role::firstOrCreate(['name' => $roleName], ['display_name' => ucfirst($roleName)]);

        return User::factory()->create(['role_id' => $role->id]);
    }

    public function test_a_manager_sees_the_devices_and_the_log(): void
    {
        $manager = $this->user('manager');
        $device = ShopDevice::factory()->token(self::TOKEN)->create([
            'name' => 'Counter tablet',
            'registered_by' => $manager->id,
            'last_user_id' => $manager->id,
            'last_used_at' => now(),
        ]);
        \App\Models\ShopSwitchLog::create([
            'shop_device_id' => $device->id,
            'user_id' => $manager->id,
            'event' => 'trust',
            'ip' => '127.0.0.1',
        ]);

        $this->actingAs($manager)->get(route('shop-devices.index'))
            ->assertOk()
            ->assertSee('Counter tablet')
            ->assertSee('Active')
            ->assertSee('Recent switch activity')
            ->assertSee('Trusted')
            ->assertSee('127.0.0.1');
    }

    public function test_the_token_hash_is_never_rendered(): void
    {
        $manager = $this->user('manager');
        $device = ShopDevice::factory()->token(self::TOKEN)->create();

        $this->actingAs($manager)->get(route('shop-devices.index'))
            ->assertOk()
            ->assertDontSee($device->token_hash)
            ->assertDontSee(self::TOKEN);
    }

    public function test_an_employee_cannot_reach_the_page_or_revoke(): void
    {
        $employee = $this->user('employee');
        $device = ShopDevice::factory()->create();

        $this->actingAs($employee)->get(route('shop-devices.index'))->assertForbidden();
        $this->actingAs($employee)->post(route('shop-devices.revoke', $device))->assertForbidden();

        $this->assertTrue($device->fresh()->isActive());
    }

    public function test_revoking_stops_pin_sign_in_on_that_device(): void
    {
        $manager = $this->user('manager');
        $device = ShopDevice::factory()->token(self::TOKEN)->create(['name' => 'Counter tablet']);
        $cookies = [config('shop.device_cookie') => self::TOKEN];

        // Trusted to begin with: the grid renders rather than the "not set up" page.
        $this->withCookies($cookies)->get(route('shop.switch'))
            ->assertOk()
            ->assertDontSee("This device isn't set up for PIN sign-in", false);

        $this->actingAs($manager)
            ->from(route('shop-devices.index'))
            ->post(route('shop-devices.revoke', $device))
            ->assertRedirect(route('shop-devices.index'))
            ->assertSessionHas('success');

        $this->assertNotNull($device->fresh()->revoked_at);
        $this->assertFalse($device->fresh()->isActive());
        $this->assertDatabaseHas('shop_switch_logs', [
            'event' => 'revoke',
            'user_id' => $manager->id,
            'shop_device_id' => $device->id,
        ]);

        // And the device's next request is no longer trusted.
        $this->withCookies($cookies)->get(route('shop.switch'))
            ->assertOk()
            ->assertSee("This device isn't set up for PIN sign-in", false);
    }

    public function test_revoking_twice_is_a_no_op_with_a_notice(): void
    {
        $manager = $this->user('manager');
        $device = ShopDevice::factory()->revoked()->create(['name' => 'Old tablet']);
        $revokedAt = $device->revoked_at;

        $this->actingAs($manager)
            ->from(route('shop-devices.index'))
            ->post(route('shop-devices.revoke', $device))
            ->assertRedirect(route('shop-devices.index'))
            ->assertSessionHas('error');

        $this->assertEquals($revokedAt, $device->fresh()->revoked_at);
        $this->assertDatabaseMissing('shop_switch_logs', ['event' => 'revoke']);
    }

    public function test_the_page_has_empty_states(): void
    {
        $this->actingAs($this->user('manager'))->get(route('shop-devices.index'))
            ->assertOk()
            ->assertSee('No devices have been trusted yet.')
            ->assertSee('Nothing recorded yet.');
    }

    public function test_the_sidebar_links_managers_to_the_page(): void
    {
        $this->actingAs($this->user('manager'))->get(route('shop-devices.index'))
            ->assertOk()
            ->assertSee(route('shop-devices.index'));
    }
}
