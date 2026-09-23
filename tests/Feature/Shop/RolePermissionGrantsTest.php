<?php

namespace Tests\Feature\Shop;

use App\Models\Permission;
use App\Models\Role;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The grants the route gating depends on, from both sources of truth: the
 * seeder (fresh installs, tests) and the migration (existing databases).
 */
class RolePermissionGrantsTest extends TestCase
{
    use RefreshDatabase;

    private const MIGRATION = 'database/migrations/2026_09_23_150000_add_shop_mode_permissions.php';

    private const NEW_PERMISSIONS = [
        'stocking.scan',
        'fruit_veg.operate',
        'orders.manage',
        'invoices.manage',
        'kitchen.manage',
    ];

    private function role(string $name): Role
    {
        return Role::where('name', $name)->firstOrFail();
    }

    public function test_seeder_grants_the_shop_floor_permissions_to_employees(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $employee = $this->role('employee');

        $this->assertTrue($employee->hasPermission('stocking.scan'));
        $this->assertTrue($employee->hasPermission('fruit_veg.operate'));

        foreach (['products.create', 'orders.manage', 'invoices.manage', 'kitchen.manage'] as $withheld) {
            $this->assertFalse(
                $employee->hasPermission($withheld),
                "Employee must not hold {$withheld}."
            );
        }
    }

    public function test_seeder_grants_managers_the_office_permissions(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $manager = $this->role('manager');

        foreach ([...self::NEW_PERMISSIONS, 'products.create'] as $granted) {
            $this->assertTrue($manager->hasPermission($granted), "Manager must hold {$granted}.");
        }
    }

    public function test_seeder_leaves_the_barista_with_kds_only(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $barista = $this->role('barista');

        $this->assertTrue($barista->hasPermission('kds.access'));
        $this->assertSame(['kds.access'], $barista->permissions->pluck('name')->all());
    }

    public function test_migration_grants_the_same_permissions_on_an_existing_database(): void
    {
        // An existing install: roles and the pre-existing permission, no seeder run.
        foreach (['admin', 'manager', 'employee', 'barista'] as $name) {
            Role::create(['name' => $name, 'display_name' => ucfirst($name)]);
        }
        Permission::create([
            'name' => 'products.create',
            'display_name' => 'Create Products',
            'module' => 'Product Management',
        ]);

        $migration = require base_path(self::MIGRATION);
        $migration->up();

        $employee = $this->role('employee');
        $this->assertTrue($employee->hasPermission('stocking.scan'));
        $this->assertTrue($employee->hasPermission('fruit_veg.operate'));
        $this->assertFalse($employee->hasPermission('orders.manage'));
        $this->assertFalse($employee->hasPermission('products.create'));

        $manager = $this->role('manager');
        foreach ([...self::NEW_PERMISSIONS, 'products.create'] as $granted) {
            $this->assertTrue($manager->hasPermission($granted), "Manager must hold {$granted}.");
        }

        $this->assertSame([], $this->role('barista')->permissions->pluck('name')->all());
    }

    public function test_migration_is_idempotent(): void
    {
        foreach (['admin', 'manager', 'employee'] as $name) {
            Role::create(['name' => $name, 'display_name' => ucfirst($name)]);
        }
        Permission::create([
            'name' => 'products.create',
            'display_name' => 'Create Products',
            'module' => 'Product Management',
        ]);

        $migration = require base_path(self::MIGRATION);
        $migration->up();

        $permissionsAfterFirst = Permission::count();
        $pivotAfterFirst = DB::table('role_permissions')->count();

        $migration->up();

        $this->assertSame($permissionsAfterFirst, Permission::count(), 'up() must not duplicate permissions.');
        $this->assertSame($pivotAfterFirst, DB::table('role_permissions')->count(), 'up() must not duplicate grants.');
    }

    public function test_migration_down_removes_only_what_it_created(): void
    {
        foreach (['admin', 'manager', 'employee'] as $name) {
            Role::create(['name' => $name, 'display_name' => ucfirst($name)]);
        }
        Permission::create([
            'name' => 'products.create',
            'display_name' => 'Create Products',
            'module' => 'Product Management',
        ]);

        $migration = require base_path(self::MIGRATION);
        $migration->up();
        $migration->down();

        foreach (self::NEW_PERMISSIONS as $name) {
            $this->assertDatabaseMissing('permissions', ['name' => $name]);
        }

        // products.create predates the migration, so only the grant is undone.
        $this->assertDatabaseHas('permissions', ['name' => 'products.create']);
        $this->assertFalse($this->role('manager')->fresh()->hasPermission('products.create'));
    }
}
