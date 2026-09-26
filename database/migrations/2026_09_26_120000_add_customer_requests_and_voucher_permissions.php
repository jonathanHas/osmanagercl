<?php

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Migrations\Migration;

/**
 * Customer requests, vouchers and customer invoices: the permissions the live
 * database never got (2026-09-26).
 *
 * Both features shipped with docs saying "run the roles seeder on deploy", which
 * never happened, so on production the `permissions` table simply had no
 * `customer-requests.manage` or `vouchers.*` rows — and the Home tiles behind them
 * were invisible to everyone. The seeder cannot be used to repair that: the live
 * database also holds permissions the seeder does not list (`cash_reconciliation.*`,
 * `till_review.export`), and running it is not the additive operation it looks like.
 *
 * So the grants ship as a migration, which `migrate --force` applies on deploy.
 * Everything here is additive: `firstOrCreate` and `syncWithoutDetaching`, no role
 * loses anything, and running it again changes nothing.
 */
return new class extends Migration
{
    /**
     * The permissions this migration owns, verbatim from RolesAndPermissionsSeeder.
     * `down()` deletes exactly these.
     */
    private const PERMISSIONS = [
        [
            'name' => 'customer-invoices.manage',
            'display_name' => 'Manage Customer Invoices',
            'description' => 'Create, issue, view and download customer invoices',
            'module' => 'Customer Invoicing',
        ],
        [
            'name' => 'customer-requests.manage',
            'display_name' => 'Manage Customer Requests',
            'description' => 'Take, edit and progress customer pre-orders and sourcing requests',
            'module' => 'Customer Requests',
        ],
        [
            'name' => 'vouchers.redeem',
            'display_name' => 'Redeem Vouchers',
            'description' => 'Scan a voucher and deduct from its balance at the till',
            'module' => 'Voucher Management',
        ],
        [
            'name' => 'vouchers.manage',
            'display_name' => 'Manage Vouchers',
            'description' => 'Generate, activate and view gift vouchers',
            'module' => 'Voucher Management',
        ],
    ];

    /** Shop-floor staff take requests and redeem vouchers at the till. */
    private const EMPLOYEE_GRANTS = [
        'customer-requests.manage',
        'vouchers.redeem',
    ];

    /** Managers also issue vouchers and run customer invoicing. */
    private const MANAGER_EXTRA_GRANTS = [
        'vouchers.manage',
        'customer-invoices.manage',
    ];

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

        // Admins bypass permission checks, so this is for completeness — the
        // seeder grants them everything for the same reason.
        if ($adminRole = Role::where('name', 'admin')->first()) {
            $adminRole->permissions()->syncWithoutDetaching(
                Permission::whereIn('name', $names)->pluck('id')
            );
        }

        if ($managerRole = Role::where('name', 'manager')->first()) {
            $managerRole->permissions()->syncWithoutDetaching(
                Permission::whereIn('name', array_merge(self::EMPLOYEE_GRANTS, self::MANAGER_EXTRA_GRANTS))->pluck('id')
            );
        }

        if ($employeeRole = Role::where('name', 'employee')->first()) {
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

        Permission::whereIn('name', $names)->delete();
    }
};
