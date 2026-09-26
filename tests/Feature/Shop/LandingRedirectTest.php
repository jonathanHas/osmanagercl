<?php

namespace Tests\Feature\Shop;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Where a user lands after signing in: shop floor or office.
 */
class LandingRedirectTest extends TestCase
{
    use RefreshDatabase;

    private function userWithRole(?string $role): User
    {
        if ($role === null) {
            return User::factory()->create();
        }

        $model = Role::firstOrCreate(['name' => $role], ['display_name' => ucfirst($role)]);

        return User::factory()->create(['role_id' => $model->id]);
    }

    private function login(User $user)
    {
        return $this->post('/login', [
            'login' => $user->email,
            'password' => 'password',
        ]);
    }

    public function test_employee_lands_on_shop(): void
    {
        $this->login($this->userWithRole('employee'))->assertRedirect('/shop');
    }

    /**
     * The KDS is the whole of a barista's job and Shop Home has no tile for them
     * since cycle 12, so login goes straight there.
     */
    public function test_barista_lands_on_the_kds(): void
    {
        $this->login($this->userWithRole('barista'))->assertRedirect(route('kds.index', absolute: false));
    }

    public function test_manager_lands_on_dashboard(): void
    {
        $this->login($this->userWithRole('manager'))->assertRedirect('/dashboard');
    }

    public function test_user_without_a_role_lands_on_dashboard(): void
    {
        $this->login($this->userWithRole(null))->assertRedirect('/dashboard');
    }

    /**
     * `withCookie` (not `withUnencryptedCookie`) is the helper here: ui_mode is
     * not in EncryptCookies' except list, so an unencrypted one is discarded by
     * the middleware. A manager can only be sent to /shop by the cookie branch,
     * so the redirect is the proof that request()->cookie('ui_mode') read it.
     */
    public function test_shop_cookie_overrides_the_role_default(): void
    {
        $manager = $this->userWithRole('manager');

        $this->withCookie('ui_mode', 'shop')
            ->post('/login', ['login' => $manager->email, 'password' => 'password'])
            ->assertRedirect('/shop');
    }

    public function test_office_cookie_overrides_the_role_default(): void
    {
        $employee = $this->userWithRole('employee');

        $this->withCookie('ui_mode', 'office')
            ->post('/login', ['login' => $employee->email, 'password' => 'password'])
            ->assertRedirect('/dashboard');
    }

    public function test_redirect_query_still_wins_over_the_landing_url(): void
    {
        $employee = $this->userWithRole('employee');

        $this->get('/login?redirect=/customer-requests')->assertOk();

        $this->login($employee)->assertRedirect('/customer-requests');
    }
}
