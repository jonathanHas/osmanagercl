<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Staff setting their own Shop PIN on the profile page (cycle 27).
 *
 * The current password is required throughout: a PIN is the weaker credential
 * and must be minted by the stronger one.
 */
class ProfilePinTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $roleName, array $attributes = []): User
    {
        $role = Role::firstOrCreate(['name' => $roleName], ['display_name' => ucfirst($roleName)]);

        return User::factory()->create(['role_id' => $role->id, 'password' => 'password'] + $attributes);
    }

    public function test_an_employee_can_set_their_own_pin(): void
    {
        $employee = $this->user('employee');

        $this->actingAs($employee)
            ->from(route('profile.edit'))
            ->put(route('profile.pin.update'), [
                'current_password' => 'password',
                'pin' => '2580',
                'pin_confirmation' => '2580',
            ])
            ->assertRedirect(route('profile.edit'))
            ->assertSessionHas('status', 'pin-updated');

        $employee->refresh();
        $this->assertTrue($employee->checkPin('2580'));
        $this->assertSame(4, $employee->pin_length);
        $this->assertNotNull($employee->pin_set_at);
    }

    public function test_a_wrong_current_password_changes_nothing(): void
    {
        $employee = $this->user('employee');

        $this->actingAs($employee)
            ->from(route('profile.edit'))
            ->put(route('profile.pin.update'), [
                'current_password' => 'not-my-password',
                'pin' => '2580',
                'pin_confirmation' => '2580',
            ])
            ->assertSessionHasErrorsIn('updatePin', ['current_password']);

        $this->assertFalse($employee->refresh()->hasPin());
    }

    public function test_a_trivial_pin_is_rejected(): void
    {
        $employee = $this->user('employee');

        $this->actingAs($employee)
            ->from(route('profile.edit'))
            ->put(route('profile.pin.update'), [
                'current_password' => 'password',
                'pin' => '1234',
                'pin_confirmation' => '1234',
            ])
            ->assertSessionHasErrorsIn('updatePin', ['pin']);

        $this->assertFalse($employee->refresh()->hasPin());
    }

    public function test_a_mismatched_confirmation_is_rejected(): void
    {
        $employee = $this->user('employee');

        $this->actingAs($employee)
            ->from(route('profile.edit'))
            ->put(route('profile.pin.update'), [
                'current_password' => 'password',
                'pin' => '2580',
                'pin_confirmation' => '2581',
            ])
            ->assertSessionHasErrorsIn('updatePin', ['pin']);

        $this->assertFalse($employee->refresh()->hasPin());
    }

    public function test_an_empty_submission_says_so_rather_than_silently_doing_nothing(): void
    {
        $employee = $this->user('employee');

        $this->actingAs($employee)
            ->from(route('profile.edit'))
            ->put(route('profile.pin.update'), ['current_password' => 'password'])
            ->assertSessionHasErrorsIn('updatePin', ['pin']);

        $this->assertFalse($employee->refresh()->hasPin());
    }

    public function test_an_employee_can_remove_their_pin(): void
    {
        $role = Role::firstOrCreate(['name' => 'employee'], ['display_name' => 'Employee']);
        $employee = User::factory()->withPin()->create(['role_id' => $role->id, 'password' => 'password']);

        $this->actingAs($employee)
            ->from(route('profile.edit'))
            ->put(route('profile.pin.update'), [
                'current_password' => 'password',
                'clear_pin' => '1',
            ])
            ->assertSessionHas('status', 'pin-cleared');

        $employee->refresh();
        $this->assertFalse($employee->hasPin());
        $this->assertNull($employee->pin_length);
        $this->assertNull($employee->pin_set_at);
    }

    public function test_clearing_wins_over_setting(): void
    {
        $role = Role::firstOrCreate(['name' => 'employee'], ['display_name' => 'Employee']);
        $employee = User::factory()->withPin()->create(['role_id' => $role->id, 'password' => 'password']);

        $this->actingAs($employee)
            ->from(route('profile.edit'))
            ->put(route('profile.pin.update'), [
                'current_password' => 'password',
                'pin' => '4907',
                'pin_confirmation' => '4907',
                'clear_pin' => '1',
            ])
            ->assertSessionHas('status', 'pin-cleared');

        $this->assertFalse($employee->refresh()->hasPin());
    }

    public function test_a_manager_is_refused_by_the_route(): void
    {
        $manager = $this->user('manager');

        $this->actingAs($manager)
            ->from(route('profile.edit'))
            ->put(route('profile.pin.update'), [
                'current_password' => 'password',
                'pin' => '2580',
                'pin_confirmation' => '2580',
            ])
            ->assertSessionHasErrorsIn('updatePin', ['pin']);

        $this->assertFalse($manager->refresh()->hasPin());
    }

    public function test_the_section_shows_for_an_employee_and_not_for_a_manager(): void
    {
        $this->actingAs($this->user('employee'))->get(route('profile.edit'))
            ->assertOk()
            ->assertSee('Shop PIN')
            ->assertSee('No PIN set.');

        $this->actingAs($this->user('manager'))->get(route('profile.edit'))
            ->assertOk()
            ->assertDontSee('Shop PIN');
    }

    public function test_the_section_shows_the_remove_box_only_when_a_pin_is_set(): void
    {
        $role = Role::firstOrCreate(['name' => 'employee'], ['display_name' => 'Employee']);

        $this->actingAs(User::factory()->create(['role_id' => $role->id]))->get(route('profile.edit'))
            ->assertOk()
            ->assertDontSee('Remove my PIN');

        $this->actingAs(User::factory()->withPin()->create(['role_id' => $role->id]))->get(route('profile.edit'))
            ->assertOk()
            ->assertSee('Remove my PIN');
    }
}
