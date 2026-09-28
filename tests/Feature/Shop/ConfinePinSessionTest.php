<?php

namespace Tests\Feature\Shop;

use App\Models\Permission;
use App\Models\Role;
use App\Models\ShopDevice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * A PIN is a weaker credential than a password, so a PIN session buys the Shop
 * and nothing else (cycle 26). Everything off the allow-list asks for the
 * password, and confirming it turns the session into an ordinary one.
 */
class ConfinePinSessionTest extends TestCase
{
    use RefreshDatabase;

    private const TOKEN = 'device-token-for-tests-0123456789abcdef';

    /**
     * @param  array<int, string>  $permissions
     */
    private function pinUser(array $permissions = []): User
    {
        $role = Role::firstOrCreate(['name' => 'employee'], ['display_name' => 'Employee']);

        foreach ($permissions as $name) {
            $role->givePermissionTo(Permission::firstOrCreate(
                ['name' => $name],
                ['display_name' => $name, 'module' => 'Shop']
            ));
        }

        $user = User::factory()->withPin()->create(['role_id' => $role->id, 'name' => 'Tom Byrne']);

        ShopDevice::factory()->token(self::TOKEN)->create();
        $this->withCookies([config('shop.device_cookie') => self::TOKEN]);

        // Sign in the way the shop floor does, so auth_via is set for real
        // rather than poked into the session.
        $this->post(route('shop.switch.authenticate', $user), ['pin' => '2580'])
            ->assertRedirect(route('shop.home'));

        return $user;
    }

    public function test_a_pin_session_is_sent_to_confirm_password_for_the_office(): void
    {
        $this->pinUser();

        $this->get(route('dashboard'))->assertRedirect(route('password.confirm'));
    }

    public function test_a_pin_session_reaches_the_shop_and_the_endpoints_it_calls(): void
    {
        $this->pinUser(['stocking.scan', 'products.view', 'customer-requests.manage']);

        $this->get(route('shop.home'))->assertOk();
        $this->get(route('shop.stock-scan'))->assertOk();
        $this->get(route('customer-requests.index'))->assertOk();
        $this->get(route('auth.check'))->assertOk()->assertJson(['authenticated' => true]);
    }

    public function test_an_off_list_json_request_is_refused_rather_than_redirected(): void
    {
        $this->pinUser(['settings.view']);

        $this->getJson(route('settings.index'))
            ->assertForbidden()
            ->assertJson(['message' => 'Confirm your password to use the office.']);
    }

    /**
     * Cycle 27. The product search API hands Shop screens
     * route('products.image', …) as a picture URL whenever the POS holds a
     * blob, so leaving it off the list cost PIN users their thumbnails on Find
     * product and the request typeahead. A 200 would need a real blob; what
     * matters here is that it is not the confinement redirect or 403.
     */
    public function test_a_pin_session_may_load_a_product_picture(): void
    {
        $this->pinUser(['products.view']);

        $response = $this->get(route('products.image', 1));

        $this->assertNotSame(403, $response->status());
        $this->assertNotSame(route('password.confirm'), $response->headers->get('Location'));
    }

    public function test_an_unnamed_route_is_not_allowed(): void
    {
        $this->pinUser();

        \Illuminate\Support\Facades\Route::middleware('web')->get('/pin-test-unnamed', fn () => 'ok');

        $this->get('/pin-test-unnamed')->assertRedirect(route('password.confirm'));
    }

    public function test_confirming_the_password_ends_the_confinement(): void
    {
        $user = $this->pinUser();
        $user->forceFill(['password' => 'a-real-password'])->save();

        $this->get(route('dashboard'))->assertRedirect(route('password.confirm'));
        $this->get(route('password.confirm'))->assertOk();

        $this->post(route('password.confirm.store'), ['password' => 'a-real-password'])
            ->assertRedirect(route('dashboard'));

        $this->assertNull(session('auth_via'));
        // The dashboard itself needs the POS database, so what is checked here
        // is that the confinement redirect is gone, not that it renders.
        $this->assertNotSame(
            route('password.confirm'),
            $this->get(route('dashboard'))->headers->get('Location'),
        );
    }

    public function test_an_ordinary_password_session_is_never_confined(): void
    {
        $role = Role::firstOrCreate(['name' => 'manager'], ['display_name' => 'Manager']);
        $manager = User::factory()->create(['role_id' => $role->id]);

        $this->actingAs($manager)->get(route('profile.edit'))->assertOk();
        $this->assertNull(session('auth_via'));
    }

    /**
     * Allow-list drift is the failure mode this cycle is most likely to hit: a
     * later Shop screen calls a new office endpoint and it 403s on the shop
     * floor. Every route a Shop view names must be covered.
     */
    public function test_every_route_a_shop_view_names_is_on_the_allow_list(): void
    {
        $roots = [
            base_path('resources/views/shop'),
            base_path('resources/views/components/shop'),
            base_path('resources/views/layouts/shop.blade.php'),
            base_path('resources/js/shop'),
        ];

        $names = [];

        foreach ($roots as $root) {
            $files = is_dir($root)
                ? new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root))
                : [new \SplFileInfo($root)];

            foreach ($files as $file) {
                if (! $file->isFile()) {
                    continue;
                }

                preg_match_all('/route\(\s*[\'"]([a-z0-9._-]+)[\'"]/i', file_get_contents($file->getPathname()), $m);
                foreach ($m[1] as $name) {
                    $names[$name] = str_replace(base_path().'/', '', $file->getPathname());
                }
            }
        }

        $this->assertNotEmpty($names, 'No route() calls found; this check would pass vacuously.');

        $allowed = config('shop.pin_session_routes');

        foreach ($names as $name => $file) {
            $this->assertTrue(
                Str::is($allowed, $name),
                "{$file} calls route('{$name}'), which a PIN session cannot reach. Add it to config/shop.php 'pin_session_routes'."
            );
        }
    }
}
