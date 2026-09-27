<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Setting and clearing a Shop PIN from the office staff forms (cycle 26).
 */
class UserPinManagementTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $role = Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Admin']);

        return User::factory()->create(['role_id' => $role->id]);
    }

    private function role(string $name): Role
    {
        return Role::firstOrCreate(['name' => $name], ['display_name' => ucfirst($name)]);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(User $user, array $overrides = []): array
    {
        return array_merge([
            'name' => $user->name,
            'email' => $user->email,
            'username' => $user->username,
            'role_id' => $user->role_id,
        ], $overrides);
    }

    public function test_an_admin_can_set_a_pin_for_an_employee(): void
    {
        $employee = User::factory()->create(['role_id' => $this->role('employee')->id]);

        $this->actingAs($this->admin())
            ->patch(route('users.update', $employee), $this->payload($employee, [
                'pin' => '2580',
                'pin_confirmation' => '2580',
            ]))
            ->assertRedirect(route('users.index'));

        $employee->refresh();
        $this->assertTrue($employee->hasPin());
        $this->assertTrue($employee->checkPin('2580'));
        $this->assertSame(4, $employee->pin_length);
        $this->assertNotNull($employee->pin_set_at);
        $this->assertNotSame('2580', $employee->pin_hash);
    }

    public function test_a_pin_can_be_set_when_creating_an_employee(): void
    {
        $this->actingAs($this->admin())
            ->post(route('users.store'), [
                'name' => 'Tom Byrne',
                'email' => 'tom@example.test',
                'password' => 'password123',
                'password_confirmation' => 'password123',
                'role_id' => $this->role('employee')->id,
                'pin' => '739104',
                'pin_confirmation' => '739104',
            ])
            ->assertRedirect(route('users.index'));

        $tom = User::where('email', 'tom@example.test')->sole();
        $this->assertTrue($tom->checkPin('739104'));
        $this->assertSame(6, $tom->pin_length);
    }

    public function test_a_trivial_pin_is_rejected(): void
    {
        $employee = User::factory()->create(['role_id' => $this->role('employee')->id]);

        $this->actingAs($this->admin())
            ->patch(route('users.update', $employee), $this->payload($employee, [
                'pin' => '1234',
                'pin_confirmation' => '1234',
            ]))
            ->assertSessionHasErrors('pin');

        $this->assertFalse($employee->refresh()->hasPin());
    }

    public function test_a_mismatched_confirmation_is_rejected(): void
    {
        $employee = User::factory()->create(['role_id' => $this->role('employee')->id]);

        $this->actingAs($this->admin())
            ->patch(route('users.update', $employee), $this->payload($employee, [
                'pin' => '2580',
                'pin_confirmation' => '2581',
            ]))
            ->assertSessionHasErrors('pin');

        $this->assertFalse($employee->refresh()->hasPin());
    }

    public function test_a_pin_for_a_manager_is_refused(): void
    {
        $manager = User::factory()->create(['role_id' => $this->role('manager')->id]);

        $this->actingAs($this->admin())
            ->patch(route('users.update', $manager), $this->payload($manager, [
                'pin' => '2580',
                'pin_confirmation' => '2580',
            ]))
            ->assertSessionHasErrors('pin');

        $this->assertFalse($manager->refresh()->hasPin());
    }

    public function test_clearing_a_pin_works(): void
    {
        $employee = User::factory()->withPin()->create(['role_id' => $this->role('employee')->id]);

        $this->actingAs($this->admin())
            ->patch(route('users.update', $employee), $this->payload($employee, ['clear_pin' => '1']))
            ->assertRedirect(route('users.index'));

        $employee->refresh();
        $this->assertFalse($employee->hasPin());
        $this->assertNull($employee->pin_length);
        $this->assertNull($employee->pin_set_at);
    }

    public function test_an_edit_that_touches_nothing_keeps_the_pin(): void
    {
        $employee = User::factory()->withPin()->create(['role_id' => $this->role('employee')->id]);

        $this->actingAs($this->admin())
            ->patch(route('users.update', $employee), $this->payload($employee, ['name' => 'Tom B']))
            ->assertRedirect(route('users.index'));

        $this->assertTrue($employee->refresh()->checkPin('2580'));
    }

    public function test_moving_an_employee_off_the_shop_floor_takes_their_pin(): void
    {
        $employee = User::factory()->withPin()->create(['role_id' => $this->role('employee')->id]);

        $this->actingAs($this->admin())
            ->patch(route('users.update', $employee), $this->payload($employee, [
                'role_id' => $this->role('manager')->id,
            ]))
            ->assertRedirect(route('users.index'));

        $this->assertFalse($employee->refresh()->hasPin());
    }

    public function test_the_edit_form_shows_the_pin_block_and_the_role_ids_it_applies_to(): void
    {
        $employeeRole = $this->role('employee');
        $employee = User::factory()->withPin()->create(['role_id' => $employeeRole->id]);

        $this->actingAs($this->admin())->get(route('users.edit', $employee))
            ->assertOk()
            ->assertSee('Shop PIN')
            ->assertSee('Clear this PIN')
            ->assertSee('pinRoles', false)
            ->assertSee('['.$employeeRole->id.']', false);
    }

    public function test_the_index_badges_a_user_who_has_a_pin(): void
    {
        User::factory()->withPin()->create(['role_id' => $this->role('employee')->id, 'name' => 'Tom Byrne']);

        $this->actingAs($this->admin())->get(route('users.index'))
            ->assertOk()
            ->assertSee('PIN set');
    }

    public function test_the_pin_hash_is_never_serialised(): void
    {
        $employee = User::factory()->withPin()->create(['role_id' => $this->role('employee')->id]);

        $this->assertArrayNotHasKey('pin_hash', $employee->toArray());
    }
}
