<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsPosTillFixture;
use Tests\TestCase;

/**
 * The office cash reconciliation page: who may open it and export from it.
 * The count itself is covered by tests/Unit/Cash and tests/Feature/Cash.
 */
class CashReconciliationTest extends TestCase
{
    use BuildsPosTillFixture;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpPos();
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
    }

    private function userWithRole(string $role): User
    {
        return User::factory()->create(['role_id' => Role::where('name', $role)->firstOrFail()->id]);
    }

    public function test_the_page_requires_authentication(): void
    {
        $this->get('/cash-reconciliation')->assertRedirect('/login');
    }

    public function test_admins_managers_and_employees_can_open_the_page(): void
    {
        foreach (['admin', 'manager', 'employee'] as $role) {
            $this->actingAs($this->userWithRole($role))
                ->get('/cash-reconciliation')
                ->assertOk()
                ->assertSee('Till count');
        }
    }

    public function test_a_barista_cannot_open_the_page(): void
    {
        $this->actingAs($this->userWithRole('barista'))->get('/cash-reconciliation')->assertForbidden();
    }

    public function test_only_managers_export_and_only_managers_sync(): void
    {
        $employee = $this->userWithRole('employee');
        $manager = $this->userWithRole('manager');

        $this->actingAs($employee)
            ->get(route('cash-reconciliation.export', ['start_date' => '2026-10-01', 'end_date' => '2026-10-31']))
            ->assertForbidden();

        $this->actingAs($manager)
            ->get(route('cash-reconciliation.export', ['start_date' => '2026-10-01', 'end_date' => '2026-10-31']))
            ->assertOk()
            ->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

        $this->actingAs($employee)
            ->post(route('cash-reconciliation.sync'), ['date' => '2026-10-14', 'till_id' => 1])
            ->assertForbidden();
    }

    public function test_the_old_store_and_ajax_routes_are_gone(): void
    {
        $this->assertFalse(\Illuminate\Support\Facades\Route::has('cash-reconciliation.store'));
        $this->assertFalse(\Illuminate\Support\Facades\Route::has('cash-reconciliation.previous-float'));
        $this->assertFalse(\Illuminate\Support\Facades\Route::has('cash-reconciliation.get-reconciliation'));
    }
}
