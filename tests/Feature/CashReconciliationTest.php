<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CashReconciliationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Run seeders
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
    }

    public function test_cash_reconciliation_page_requires_authentication()
    {
        $response = $this->get('/cash-reconciliation');
        $response->assertRedirect('/login');
    }

    public function test_admin_can_access_cash_reconciliation_page()
    {
        $admin = User::factory()->create();
        $adminRole = Role::where('name', 'admin')->first();
        $admin->roles()->attach($adminRole);

        $response = $this->actingAs($admin)->get('/cash-reconciliation');

        // Will show error about no closed cash, but page loads
        $response->assertStatus(200);
        $response->assertSee('Cash Reconciliation');
    }

    public function test_manager_can_access_cash_reconciliation_page()
    {
        $manager = User::factory()->create();
        $managerRole = Role::where('name', 'manager')->first();
        $manager->roles()->attach($managerRole);

        $response = $this->actingAs($manager)->get('/cash-reconciliation');

        // Will show error about no closed cash, but page loads
        $response->assertStatus(200);
        $response->assertSee('Cash Reconciliation');
    }

    public function test_employee_cannot_access_cash_reconciliation_page()
    {
        $employee = User::factory()->create();
        $employeeRole = Role::where('name', 'employee')->first();
        $employee->roles()->attach($employeeRole);

        $response = $this->actingAs($employee)->get('/cash-reconciliation');

        // Should be forbidden or redirected
        $response->assertStatus(403);
    }

    public function test_cash_reconciliation_models_exist()
    {
        $this->assertTrue(class_exists(\App\Models\CashReconciliation::class));
        $this->assertTrue(class_exists(\App\Models\CashReconciliationPayment::class));
        $this->assertTrue(class_exists(\App\Models\CashReconciliationNote::class));
    }

    public function test_cash_reconciliation_repository_exists()
    {
        $this->assertTrue(class_exists(\App\Repositories\CashReconciliationRepository::class));
    }

    public function test_cash_reconciliation_controller_exists()
    {
        $this->assertTrue(class_exists(\App\Http\Controllers\Management\CashReconciliationController::class));
    }

    public function test_database_tables_exist()
    {
        $this->assertTrue(\Schema::hasTable('cash_reconciliations'));
        $this->assertTrue(\Schema::hasTable('cash_reconciliation_payments'));
        $this->assertTrue(\Schema::hasTable('cash_reconciliation_notes'));
    }
}
