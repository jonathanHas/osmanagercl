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

    /**
     * A redirect after an action has to say what happened. Before cycle 9 the Shop
     * layer rendered no flash at all, so "Delivery completed" and "already
     * completed" went nowhere.
     */
    public function test_flash_success_is_shown_as_a_toast(): void
    {
        $this->actingAs($this->userWith('employee', ['stocking.scan']))
            ->withSession(['success' => 'Delivery completed. 18 units added.'])
            ->get('/shop')
            ->assertOk()
            ->assertSee('Delivery completed. 18 units added.')
            ->assertSee('shop-toast--ok', false);
    }

    public function test_flash_error_is_shown_as_a_bad_toast(): void
    {
        $this->actingAs($this->userWith('employee', ['stocking.scan']))
            ->withSession(['error' => 'This delivery is already completed.'])
            ->get('/shop')
            ->assertOk()
            ->assertSee('This delivery is already completed.')
            ->assertSee('shop-toast--bad', false)
            ->assertDontSee('shop-toast--ok', false);
    }

    /**
     * Home has no client-side toast region of its own, so the absence of the
     * markup is assertable here in a way it would not be on the scan screens.
     */
    public function test_no_toast_region_without_a_flash(): void
    {
        $this->actingAs($this->userWith('employee', ['stocking.scan']))
            ->get('/shop')
            ->assertOk()
            ->assertDontSee('shop-toasts', false);
    }

    public function test_employee_sees_only_the_tiles_they_may_use(): void
    {
        $user = $this->userWith('employee', ['stocking.scan', 'deliveries.process', 'customer-requests.manage']);

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
     * Employees receive deliveries through the legacy scan sessions; the newer
     * /deliveries system is manager-only from cycle 2 on, so the tile must not
     * send them somewhere they would get a 403. From cycle 8 the tile points at
     * the Shop mode list, which is built on those same legacy sessions.
     *
     * The badge is not asserted here: it counts open sessions on the POS
     * connection, which this test does not create, so its absence would prove
     * nothing. ShopDeliveryTest covers the badge with the table seeded.
     */
    public function test_deliveries_tile_links_to_the_shop_delivery_list(): void
    {
        $user = $this->userWith('employee', ['deliveries.process']);

        $response = $this->actingAs($user)->get('/shop');

        $response->assertOk()
            ->assertSee('href="'.route('shop.deliveries').'"', false)
            ->assertDontSee(route('deliveries.index'), false);
    }

    public function test_guest_is_sent_to_login(): void
    {
        $this->get('/shop')->assertRedirect('/login');
    }
}
