<?php

namespace Tests\Feature\Shop;

use App\Models\Role;
use App\Models\ShopDevice;
use App\Models\ShopSwitchLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

/**
 * Shop mode cycle 26: trusting a shared device, PIN sign-in, Lock and Locked.
 *
 * The device cookie is the gate — without it there is no people grid and no
 * PIN page — so almost every test here goes through trusted().
 */
class ShopPinSwitchTest extends TestCase
{
    use RefreshDatabase;

    private const TOKEN = 'device-token-for-tests-0123456789abcdef';

    private function user(string $roleName, array $attributes = []): User
    {
        $role = Role::firstOrCreate(['name' => $roleName], ['display_name' => ucfirst($roleName)]);

        return User::factory()->create(['role_id' => $role->id] + $attributes);
    }

    private function employeeWithPin(string $name = 'Tom Byrne', string $pin = '2580'): User
    {
        $role = Role::firstOrCreate(['name' => 'employee'], ['display_name' => 'Employee']);

        return User::factory()->withPin($pin)->create(['role_id' => $role->id, 'name' => $name]);
    }

    /**
     * Create a trusted device and return the cookie jar for withCookies().
     *
     * @return array<string, string>
     */
    private function trusted(): array
    {
        ShopDevice::factory()->token(self::TOKEN)->create();

        return [config('shop.device_cookie') => self::TOKEN];
    }

    // ---------------------------------------------------------------- trust

    public function test_a_manager_can_trust_a_device_and_gets_the_cookie(): void
    {
        $manager = $this->user('manager');

        $response = $this->actingAs($manager)
            ->post(route('shop.devices.trust.store'), ['name' => 'Counter tablet']);

        $response->assertRedirect(route('shop.home'));
        $response->assertSessionHas('success');
        $response->assertCookie(config('shop.device_cookie'));

        $device = ShopDevice::sole();
        $this->assertSame('Counter tablet', $device->name);
        $this->assertSame($manager->id, $device->registered_by);
        $this->assertNotSame(
            $response->getCookie(config('shop.device_cookie'), false)->getValue(),
            $device->token_hash,
            'The raw token must never be what is stored.'
        );
        $this->assertDatabaseHas('shop_switch_logs', ['event' => 'trust', 'user_id' => $manager->id]);
    }

    public function test_an_employee_cannot_trust_a_device(): void
    {
        $employee = $this->user('employee');

        $this->actingAs($employee)->get(route('shop.devices.trust'))->assertForbidden();
        $this->actingAs($employee)->post(route('shop.devices.trust.store'), ['name' => 'Phone'])->assertForbidden();
        $this->assertSame(0, ShopDevice::count());
    }

    public function test_the_menu_offers_switch_user_only_on_a_trusted_device(): void
    {
        $employee = $this->user('employee');

        $this->actingAs($employee)->get(route('shop.home'))
            ->assertDontSee(route('shop.switch'));

        $this->actingAs($employee)->withCookies($this->trusted())->get(route('shop.home'))
            ->assertSee(route('shop.switch'));
    }

    public function test_the_menu_offers_trust_this_device_to_a_manager_on_an_untrusted_device(): void
    {
        $this->actingAs($this->user('manager'))->get(route('shop.home'))
            ->assertSee(route('shop.devices.trust'));
    }

    // --------------------------------------------------------------- switch

    public function test_an_untrusted_device_gets_the_not_set_up_page(): void
    {
        $this->employeeWithPin();

        $this->get(route('shop.switch'))
            ->assertOk()
            ->assertSee("This device isn't set up for PIN sign-in", false)
            ->assertDontSee('Who is working?');
    }

    public function test_the_grid_lists_only_shop_floor_staff_who_have_a_pin(): void
    {
        $withPin = $this->employeeWithPin('Tom Byrne');
        $noPin = $this->user('employee', ['name' => 'Ana Silva']);
        // A manager with a hash somehow set still never appears: canUsePin is
        // the rule, and the query filters on the role.
        $managerRole = Role::firstOrCreate(['name' => 'manager'], ['display_name' => 'Manager']);
        User::factory()->withPin()->create(['role_id' => $managerRole->id, 'name' => 'Maya Jensen']);

        $response = $this->withCookies($this->trusted())->get(route('shop.switch'));

        $response->assertOk()
            ->assertSee('Who is working?')
            ->assertSee('Tom')
            ->assertDontSee('Ana')
            ->assertDontSee('Maya');
        $this->assertStringContainsString(route('shop.switch.pin', $withPin), $response->getContent());
        $this->assertStringNotContainsString(route('shop.switch.pin', $noPin), $response->getContent());
    }

    public function test_the_signed_in_person_is_marked_on_the_grid(): void
    {
        $tom = $this->employeeWithPin('Tom Byrne');

        $this->actingAs($tom)->withCookies($this->trusted())->get(route('shop.switch'))
            ->assertOk()
            ->assertSee('Signed in');
    }

    public function test_nobody_is_marked_on_the_grid_for_a_guest(): void
    {
        $this->employeeWithPin('Tom Byrne');

        $this->withCookies($this->trusted())->get(route('shop.switch'))
            ->assertOk()
            ->assertSee('Tom')
            ->assertDontSee('Signed in');
    }

    // ------------------------------------------------------------------ pin

    public function test_the_pin_page_renders_a_dot_per_digit(): void
    {
        $tom = $this->employeeWithPin('Tom Byrne', '739104');

        $response = $this->withCookies($this->trusted())->get(route('shop.switch.pin', $tom));

        $response->assertOk()
            ->assertSee('Hi Tom')
            ->assertSee('data-length="6"', false);
        // The container is shop-pin__dots, so count the dot class attribute.
        $this->assertSame(6, substr_count($response->getContent(), 'class="shop-pin__dot"'));
    }

    public function test_the_pin_page_is_404_without_a_trusted_device(): void
    {
        $tom = $this->employeeWithPin();

        $this->get(route('shop.switch.pin', $tom))->assertNotFound();
    }

    public function test_the_pin_page_is_404_for_a_manager(): void
    {
        $managerRole = Role::firstOrCreate(['name' => 'manager'], ['display_name' => 'Manager']);
        $manager = User::factory()->withPin()->create(['role_id' => $managerRole->id]);

        $this->withCookies($this->trusted())->get(route('shop.switch.pin', $manager))->assertNotFound();
    }

    public function test_the_pin_page_is_404_for_an_employee_with_no_pin(): void
    {
        $employee = $this->user('employee');

        $this->withCookies($this->trusted())->get(route('shop.switch.pin', $employee))->assertNotFound();
    }

    // --------------------------------------------------------- authenticate

    public function test_the_right_pin_signs_that_person_in(): void
    {
        $tom = $this->employeeWithPin('Tom Byrne', '2580');
        $cookies = $this->trusted();

        $response = $this->withCookies($cookies)
            ->post(route('shop.switch.authenticate', $tom), ['pin' => '2580']);

        $response->assertRedirect(route('shop.home'));
        $this->assertAuthenticatedAs($tom);
        $this->assertSame('pin', session('auth_via'));
        $this->assertDatabaseHas('shop_switch_logs', ['event' => 'switch', 'user_id' => $tom->id]);
        $this->assertNotNull(ShopDevice::sole()->last_used_at);
        $this->assertSame($tom->id, ShopDevice::sole()->last_user_id);
    }

    public function test_switching_replaces_the_previous_session(): void
    {
        $maya = $this->employeeWithPin('Maya Jensen', '2580');
        $tom = $this->employeeWithPin('Tom Byrne', '4907');

        $this->actingAs($maya)
            ->withCookies($this->trusted())
            ->post(route('shop.switch.authenticate', $tom), ['pin' => '4907'])
            ->assertRedirect(route('shop.home'));

        $this->assertAuthenticatedAs($tom);
    }

    public function test_a_wrong_pin_is_refused_and_logged(): void
    {
        $tom = $this->employeeWithPin('Tom Byrne', '2580');

        $response = $this->withCookies($this->trusted())
            ->from(route('shop.switch.pin', $tom))
            ->post(route('shop.switch.authenticate', $tom), ['pin' => '1357']);

        $response->assertRedirect(route('shop.switch.pin', $tom));
        $response->assertSessionHas('pinError', 'Wrong PIN, try again.');
        $this->assertGuest();
        $this->assertDatabaseHas('shop_switch_logs', ['event' => 'switch_failed', 'user_id' => $tom->id]);
    }

    public function test_too_many_wrong_pins_locks_that_person_out(): void
    {
        $tom = $this->employeeWithPin('Tom Byrne', '2580');
        $cookies = $this->trusted();
        $pinUrl = route('shop.switch.pin', $tom);

        for ($i = 0; $i < config('shop.pin_attempts'); $i++) {
            $this->withCookies($cookies)->from($pinUrl)
                ->post(route('shop.switch.authenticate', $tom), ['pin' => '1357'])
                ->assertSessionHas('pinError', 'Wrong PIN, try again.');
        }

        $blocked = $this->withCookies($cookies)->from($pinUrl)
            ->post(route('shop.switch.authenticate', $tom), ['pin' => '1357']);
        $blocked->assertRedirect($pinUrl);
        $blocked->assertSessionHas('pinError', 'Too many tries — wait 15 minutes.');

        // And the correct PIN is refused while the window is open.
        $this->withCookies($cookies)->from($pinUrl)
            ->post(route('shop.switch.authenticate', $tom), ['pin' => '2580'])
            ->assertSessionHas('pinError', 'Too many tries — wait 15 minutes.');
        $this->assertGuest();

        RateLimiter::clear('pin:'.$tom->id);
        $this->withCookies($cookies)
            ->post(route('shop.switch.authenticate', $tom), ['pin' => '2580'])
            ->assertRedirect(route('shop.home'));
        $this->assertAuthenticatedAs($tom);
    }

    public function test_a_correct_pin_clears_the_attempt_counter(): void
    {
        $tom = $this->employeeWithPin('Tom Byrne', '2580');
        $cookies = $this->trusted();

        $this->withCookies($cookies)->post(route('shop.switch.authenticate', $tom), ['pin' => '1357']);
        $this->withCookies($cookies)->post(route('shop.switch.authenticate', $tom), ['pin' => '2580'])
            ->assertRedirect(route('shop.home'));

        $this->assertSame(0, RateLimiter::attempts('pin:'.$tom->id));
    }

    public function test_authenticate_is_404_without_a_trusted_device(): void
    {
        $tom = $this->employeeWithPin('Tom Byrne', '2580');

        $this->post(route('shop.switch.authenticate', $tom), ['pin' => '2580'])->assertNotFound();
        $this->assertGuest();
    }

    // ------------------------------------------------------------- lock

    public function test_lock_signs_out_and_shows_the_clock(): void
    {
        $tom = $this->employeeWithPin('Tom Byrne');

        $this->actingAs($tom)->withCookies($this->trusted())->get(route('shop.lock'))
            ->assertRedirect(route('shop.locked'));

        $this->assertGuest();
        $this->assertDatabaseHas('shop_switch_logs', ['event' => 'lock', 'user_id' => $tom->id]);
    }

    public function test_lock_for_a_guest_just_goes_to_the_locked_screen(): void
    {
        $this->get(route('shop.lock'))->assertRedirect(route('shop.locked'));
        $this->assertSame(0, ShopSwitchLog::count());
    }

    public function test_the_locked_screen_renders_for_a_guest_with_no_topbar(): void
    {
        $response = $this->get(route('shop.locked'));

        $response->assertOk()
            ->assertSee('data-shell="shop"', false)
            ->assertSee('Tap to unlock')
            ->assertSee('Locked after 5 minutes without activity')
            // The lock mark reuses shop-topbar__mark, so it is the header itself
            // that must be absent.
            ->assertDontSee('<header class="shop-topbar">', false);
    }

    public function test_the_locked_screen_sends_a_signed_in_user_home(): void
    {
        $this->actingAs($this->user('employee'))->get(route('shop.locked'))
            ->assertRedirect(route('shop.home'));
    }

    // -------------------------------------------------------- idle lock

    public function test_the_idle_lock_attributes_appear_on_a_trusted_device(): void
    {
        $this->actingAs($this->user('employee'))->withCookies($this->trusted())->get(route('shop.home'))
            ->assertSee('data-idle-lock-seconds="300"', false)
            ->assertSee('data-idle-lock-url="'.route('shop.lock').'"', false);
    }

    /**
     * A staff member's own phone is theirs to leave open; only the shared
     * tablet locks itself. (Its own test because withCookies() is sticky for
     * the rest of the test method.)
     */
    public function test_the_idle_lock_is_absent_on_an_untrusted_device(): void
    {
        $this->actingAs($this->user('employee'))->get(route('shop.home'))
            ->assertDontSee('data-idle-lock-seconds', false);
    }

    public function test_the_idle_lock_is_absent_for_a_guest(): void
    {
        $this->withCookies($this->trusted())->get(route('shop.switch'))
            ->assertDontSee('data-idle-lock-seconds', false);
    }

    public function test_the_idle_lock_is_off_on_the_switch_and_locked_screens(): void
    {
        $tom = $this->employeeWithPin();
        $cookies = $this->trusted();

        $this->actingAs($tom)->withCookies($cookies)->get(route('shop.switch'))
            ->assertDontSee('data-idle-lock-seconds', false);

        $this->withCookies($cookies)->get(route('shop.locked'))
            ->assertDontSee('data-idle-lock-seconds', false);
    }

    public function test_a_revoked_device_is_no_longer_trusted(): void
    {
        ShopDevice::factory()->token(self::TOKEN)->revoked()->create();
        $this->employeeWithPin();

        $this->withCookies([config('shop.device_cookie') => self::TOKEN])
            ->get(route('shop.switch'))
            ->assertSee("This device isn't set up for PIN sign-in", false);
    }
}
