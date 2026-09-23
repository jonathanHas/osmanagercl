<?php

namespace Tests\Feature\Shop;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Shop mode Home: permission-filtered tiles, badges and the user menu.
 */
class ShopHomeTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  array<int, string>  $permissions
     */
    private function userWith(string $roleName, array $permissions): User
    {
        $role = Role::firstOrCreate(['name' => $roleName], ['display_name' => ucfirst($roleName)]);

        foreach ($permissions as $name) {
            $permission = Permission::firstOrCreate(
                ['name' => $name],
                ['display_name' => $name, 'module' => 'Shop']
            );
            $role->givePermissionTo($permission);
        }

        return User::factory()->create(['role_id' => $role->id, 'name' => 'Maya Jensen']);
    }

    public function test_employee_sees_only_the_tiles_they_may_use(): void
    {
        $user = $this->userWith('employee', ['products.view', 'deliveries.process', 'customer-requests.manage']);

        $response = $this->actingAs($user)->get('/shop');

        $response->assertOk()
            ->assertSee('Stock scan')
            ->assertSee('Receive delivery')
            ->assertSee('Customer requests')
            ->assertDontSee('Coffee orders')
            ->assertDontSee('Print labels');
    }

    public function test_barista_sees_only_coffee_orders_and_no_office_switch(): void
    {
        $user = $this->userWith('barista', ['kds.access']);

        $response = $this->actingAs($user)->get('/shop');

        $response->assertOk()
            ->assertSee('Coffee orders')
            ->assertDontSee('Stock scan')
            ->assertDontSee('Office');
    }

    public function test_manager_sees_the_office_switch(): void
    {
        $user = $this->userWith('manager', ['products.view']);

        $this->actingAs($user)->get('/shop')
            ->assertOk()
            ->assertSee('Office');
    }

    public function test_user_with_no_permissions_sees_the_empty_state(): void
    {
        $user = $this->userWith('employee', []);

        $this->actingAs($user)->get('/shop')
            ->assertOk()
            ->assertSee('Nothing to do here yet');
    }

    public function test_page_is_marked_as_the_shop_shell(): void
    {
        $user = $this->userWith('employee', ['products.view']);

        $this->actingAs($user)->get('/shop')
            ->assertOk()
            ->assertSee('data-shell="shop"', false);
    }

    /**
     * Employees receive deliveries through the legacy flow; the newer
     * /deliveries system is manager-only from cycle 2 on, so the tile must not
     * send them somewhere they would get a 403.
     */
    public function test_deliveries_tile_links_to_the_legacy_delivery_screen(): void
    {
        $user = $this->userWith('employee', ['deliveries.process']);

        $response = $this->actingAs($user)->get('/shop');

        $response->assertOk()
            ->assertSee('href="'.route('delivery-legacy.index').'"', false)
            ->assertDontSee(route('deliveries.index'), false)
            ->assertDontSee('shop-tile__badge', false);
    }

    public function test_guest_is_sent_to_login(): void
    {
        $this->get('/shop')->assertRedirect('/login');
    }
}
