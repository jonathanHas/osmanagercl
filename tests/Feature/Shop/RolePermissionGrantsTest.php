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

    /** Cycle 20: the four the live database never got. */
    private const REQUESTS_MIGRATION = 'database/migrations/2026_09_26_120000_add_customer_requests_and_voucher_permissions.php';

    private const REQUESTS_PERMISSIONS = [
        'customer-invoices.manage',
        'customer-requests.manage',
        'vouchers.redeem',
        'vouchers.manage',
    ];

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

    // --- cycle 20: customer requests, vouchers, customer invoices ----------

    /**
     * An existing install as production actually is: roles, some unrelated
     * permissions, and none of the four — because the seeder was never run there.
     *
     * The four have to be removed explicitly: this migration lives in
     * `database/migrations/`, so RefreshDatabase has already applied it to every
     * test database. Without this the tests below would be asserting the state the
     * harness set up rather than the state `up()` produces.
     */
    private function existingDatabase(): void
    {
        $ids = Permission::whereIn('name', self::REQUESTS_PERMISSIONS)->pluck('id');
        DB::table('role_permissions')->whereIn('permission_id', $ids)->delete();
        Permission::whereIn('id', $ids)->delete();

        foreach (['admin', 'manager', 'employee', 'barista'] as $name) {
            Role::create(['name' => $name, 'display_name' => ucfirst($name)]);
        }

        // Something the live database holds that the migration must not disturb.
        // firstOrCreate, not create: `till_review.export` is created by the cash
        // reconciliation migration, so RefreshDatabase has already made it — which
        // is exactly why it is a good stand-in for the live database's extras.
        $unrelated = Permission::firstOrCreate(
            ['name' => 'till_review.export'],
            ['display_name' => 'Export till review', 'module' => 'Reports']
        );

        $this->role('employee')->permissions()->syncWithoutDetaching([$unrelated->id]);
    }

    public function test_requests_migration_creates_the_four_permissions_and_grants_them_by_role(): void
    {
        $this->existingDatabase();

        foreach (self::REQUESTS_PERMISSIONS as $name) {
            $this->assertDatabaseMissing('permissions', ['name' => $name]);
        }

        (require base_path(self::REQUESTS_MIGRATION))->up();

        $employee = $this->role('employee');
        $this->assertTrue($employee->hasPermission('customer-requests.manage'));
        $this->assertTrue($employee->hasPermission('vouchers.redeem'));
        // Issuing vouchers and customer invoicing stay with managers.
        $this->assertFalse($employee->hasPermission('vouchers.manage'));
        $this->assertFalse($employee->hasPermission('customer-invoices.manage'));

        $manager = $this->role('manager');
        foreach (self::REQUESTS_PERMISSIONS as $name) {
            $this->assertTrue($manager->hasPermission($name), "Manager must hold {$name}.");
        }

        $admin = $this->role('admin');
        foreach (self::REQUESTS_PERMISSIONS as $name) {
            $this->assertTrue($admin->hasPermission($name), "Admin must hold {$name}.");
        }

        $this->assertSame([], $this->role('barista')->permissions->pluck('name')->all());
    }

    public function test_requests_migration_is_additive_and_idempotent(): void
    {
        $this->existingDatabase();

        $migration = require base_path(self::REQUESTS_MIGRATION);
        $migration->up();

        $permissions = Permission::count();
        $pivots = DB::table('role_permissions')->count();

        $migration->up();

        $this->assertSame($permissions, Permission::count(), 'up() must not duplicate permissions.');
        $this->assertSame($pivots, DB::table('role_permissions')->count(), 'up() must not duplicate grants.');

        // The permission the live database already had is untouched by either run.
        $this->assertTrue($this->role('employee')->fresh()->hasPermission('till_review.export'));
    }

    public function test_requests_migration_down_removes_only_the_four(): void
    {
        $this->existingDatabase();

        $migration = require base_path(self::REQUESTS_MIGRATION);
        $migration->up();
        $migration->down();

        foreach (self::REQUESTS_PERMISSIONS as $name) {
            $this->assertDatabaseMissing('permissions', ['name' => $name]);
            $this->assertFalse($this->role('employee')->fresh()->hasPermission($name));
            $this->assertFalse($this->role('manager')->fresh()->hasPermission($name));
        }

        // Everything that predates the migration survives.
        $this->assertDatabaseHas('permissions', ['name' => 'till_review.export']);
        $this->assertTrue($this->role('employee')->fresh()->hasPermission('till_review.export'));
    }

    public function test_the_migration_grants_what_the_seeder_grants(): void
    {
        // The two sources of truth must not drift: whatever a fresh install gets
        // from the seeder for these four, an existing database gets from the
        // migration. This is the assertion that would have caught the original
        // problem, had the seeder-only route ever been trusted.
        $this->seed(RolesAndPermissionsSeeder::class);

        $fromSeeder = [];
        foreach (['employee', 'manager', 'admin'] as $name) {
            $fromSeeder[$name] = $this->role($name)->permissions->pluck('name')
                ->intersect(self::REQUESTS_PERMISSIONS)->sort()->values()->all();
        }

        // Put the database into the state production was in: the seeder's roles,
        // but none of these four permissions. (Not refreshDatabase() — calling that
        // inside a test rolls back the transaction RefreshDatabase is holding.)
        $ids = Permission::whereIn('name', self::REQUESTS_PERMISSIONS)->pluck('id');
        DB::table('role_permissions')->whereIn('permission_id', $ids)->delete();
        Permission::whereIn('id', $ids)->delete();

        foreach (['employee', 'manager', 'admin'] as $name) {
            $this->assertSame([], $this->role($name)->fresh()->permissions->pluck('name')
                ->intersect(self::REQUESTS_PERMISSIONS)->values()->all());
        }

        (require base_path(self::REQUESTS_MIGRATION))->up();

        foreach (['employee', 'manager', 'admin'] as $name) {
            $fromMigration = $this->role($name)->fresh()->permissions->pluck('name')
                ->intersect(self::REQUESTS_PERMISSIONS)->sort()->values()->all();

            $this->assertSame($fromSeeder[$name], $fromMigration, "{$name} grants differ between seeder and migration.");
        }

        $this->assertNotSame([], $fromSeeder['employee'], 'The comparison must not be vacuous.');
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
