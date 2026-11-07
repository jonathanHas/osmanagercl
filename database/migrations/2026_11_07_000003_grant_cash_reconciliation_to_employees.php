<?php

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Migrations\Migration;

/**
 * Any member of staff may count the till (till count rewrite, 2026-11-07).
 *
 * `cash_reconciliation.view` and `.create` exist from the 2025-08-11 migration and
 * were granted to admins and managers only. The end-of-day count is done by whoever
 * closes the till, so employees get both. `.export` stays with managers. Additive:
 * `firstOrCreate` and `syncWithoutDetaching`, safe to run twice.
 */
return new class extends Migration
{
    private const PERMISSIONS = [
        [
            'name' => 'cash_reconciliation.view',
            'display_name' => 'View Cash Reconciliation',
            'description' => 'View the end-of-day till count',
            'module' => 'Cash Management',
        ],
        [
            'name' => 'cash_reconciliation.create',
            'display_name' => 'Create Cash Reconciliation',
            'description' => 'Count the till at the end of the day',
            'module' => 'Cash Management',
        ],
    ];

    public function up(): void
    {
        foreach (self::PERMISSIONS as $permission) {
            Permission::firstOrCreate(['name' => $permission['name']], $permission);
        }

        $ids = Permission::whereIn('name', array_column(self::PERMISSIONS, 'name'))->pluck('id');

        foreach (['admin', 'manager', 'employee'] as $name) {
            if ($role = Role::where('name', $name)->first()) {
                $role->permissions()->syncWithoutDetaching($ids);
            }
        }
    }

    public function down(): void
    {
        if ($employee = Role::where('name', 'employee')->first()) {
            $employee->permissions()->detach(
                Permission::whereIn('name', array_column(self::PERMISSIONS, 'name'))->pluck('id')
            );
        }
    }
};
