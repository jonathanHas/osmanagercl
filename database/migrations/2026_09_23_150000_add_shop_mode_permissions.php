<?php

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Migrations\Migration;

/**
 * Shop mode cycle 2: the finer permissions the route gating needs.
 *
 * Until now most manager pages were only behind `auth`, so an employee could
 * reach orders, invoices, kitchen or the sales importer by typing a URL. These
 * permissions give those route groups something to gate on, and give the
 * shop-floor tasks (stock scanning, daily fruit & veg work) a permission of
 * their own rather than borrowing a broader one.
 */
return new class extends Migration
{
    /**
     * The permissions this migration owns. `down()` deletes exactly these.
     */
    private const PERMISSIONS = [
        [
            'name' => 'stocking.scan',
            'display_name' => 'Scan and adjust stock',
            'description' => 'Can scan products and adjust stock levels on the shop floor',
            'module' => 'Stock',
        ],
        [
            'name' => 'fruit_veg.operate',
            'display_name' => 'Fruit & veg daily tasks',
            'description' => 'Can run the daily fruit & veg tasks: availability, labels, waste and harvest',
            'module' => 'Category Management',
        ],
        [
            'name' => 'orders.manage',
            'display_name' => 'Manage supplier orders',
            'description' => 'Can generate, compare and approve supplier orders',
            'module' => 'Ordering',
        ],
        [
            'name' => 'invoices.manage',
            'display_name' => 'Manage supplier invoices and RTD',
            'description' => 'Can manage supplier invoices, attachments, RTD and supplier records',
            'module' => 'Accounting',
        ],
        [
            'name' => 'kitchen.manage',
            'display_name' => 'Manage kitchen recipes and orders',
            'description' => 'Can manage kitchen recipes, ingredient profiles and kitchen orders',
            'module' => 'Kitchen',
        ],
    ];

    private const EMPLOYEE_GRANTS = ['stocking.scan', 'fruit_veg.operate'];

    /**
     * Managers also gain the pre-existing products.create: they add products
     * while working the new delivery system, whose pages link to it.
     */
    private const MANAGER_EXTRA_GRANTS = ['products.create'];

    public function up(): void
    {
        foreach (self::PERMISSIONS as $permission) {
            Permission::firstOrCreate(
                ['name' => $permission['name']],
                [
                    'display_name' => $permission['display_name'],
                    'description' => $permission['description'],
                    'module' => $permission['module'],
                ]
            );
        }

        $names = array_column(self::PERMISSIONS, 'name');

        $adminRole = Role::where('name', 'admin')->first();
        $managerRole = Role::where('name', 'manager')->first();
        $employeeRole = Role::where('name', 'employee')->first();

        if ($adminRole) {
            $adminRole->permissions()->syncWithoutDetaching(
                Permission::whereIn('name', $names)->pluck('id')
            );
        }

        if ($managerRole) {
            $managerRole->permissions()->syncWithoutDetaching(
                Permission::whereIn('name', array_merge($names, self::MANAGER_EXTRA_GRANTS))->pluck('id')
            );
        }

        if ($employeeRole) {
            $employeeRole->permissions()->syncWithoutDetaching(
                Permission::whereIn('name', self::EMPLOYEE_GRANTS)->pluck('id')
            );
        }
    }

    public function down(): void
    {
        $names = array_column(self::PERMISSIONS, 'name');

        foreach (Role::all() as $role) {
            $role->permissions()->detach(
                Permission::whereIn('name', $names)->pluck('id')
            );
        }

        // products.create predates this migration, so only the grant is undone.
        if ($managerRole = Role::where('name', 'manager')->first()) {
            $managerRole->permissions()->detach(
                Permission::whereIn('name', self::MANAGER_EXTRA_GRANTS)->pluck('id')
            );
        }

        Permission::whereIn('name', $names)->delete();
    }
};
