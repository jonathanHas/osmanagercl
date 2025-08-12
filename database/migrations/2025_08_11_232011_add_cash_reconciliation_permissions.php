<?php

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Create Financial Management permissions
        $permissions = [
            [
                'name' => 'cash_reconciliation.view',
                'display_name' => 'View Cash Reconciliation',
                'description' => 'Can view cash reconciliation reports',
                'module' => 'Financial Management',
            ],
            [
                'name' => 'cash_reconciliation.create',
                'display_name' => 'Create Cash Reconciliation',
                'description' => 'Can create and edit cash reconciliations',
                'module' => 'Financial Management',
            ],
            [
                'name' => 'cash_reconciliation.export',
                'display_name' => 'Export Cash Reconciliation',
                'description' => 'Can export cash reconciliation data',
                'module' => 'Financial Management',
            ],
            [
                'name' => 'till_review.view',
                'display_name' => 'View Till Receipts',
                'description' => 'Can view till receipts and transactions',
                'module' => 'Financial Management',
            ],
            [
                'name' => 'till_review.export',
                'display_name' => 'Export Till Data',
                'description' => 'Can export till transaction data',
                'module' => 'Financial Management',
            ],
        ];

        foreach ($permissions as $permissionData) {
            Permission::firstOrCreate(
                ['name' => $permissionData['name']],
                [
                    'display_name' => $permissionData['display_name'],
                    'description' => $permissionData['description'],
                    'module' => $permissionData['module'],
                    'guard_name' => 'web',
                ]
            );
        }

        // Assign permissions to roles
        $adminRole = Role::where('name', 'admin')->first();
        $managerRole = Role::where('name', 'manager')->first();
        $employeeRole = Role::where('name', 'employee')->first();

        // Admin gets all financial permissions
        if ($adminRole) {
            $adminRole->permissions()->syncWithoutDetaching(
                Permission::whereIn('name', [
                    'cash_reconciliation.view',
                    'cash_reconciliation.create',
                    'cash_reconciliation.export',
                    'till_review.view',
                    'till_review.export',
                ])->pluck('id')
            );
        }

        // Manager gets all financial permissions
        if ($managerRole) {
            $managerRole->permissions()->syncWithoutDetaching(
                Permission::whereIn('name', [
                    'cash_reconciliation.view',
                    'cash_reconciliation.create',
                    'cash_reconciliation.export',
                    'till_review.view',
                    'till_review.export',
                ])->pluck('id')
            );
        }

        // Employee can only view till receipts
        if ($employeeRole) {
            $employeeRole->permissions()->syncWithoutDetaching(
                Permission::whereIn('name', [
                    'till_review.view',
                ])->pluck('id')
            );
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove permissions from roles
        $permissions = [
            'cash_reconciliation.view',
            'cash_reconciliation.create',
            'cash_reconciliation.export',
            'till_review.view',
            'till_review.export',
        ];

        foreach (Role::all() as $role) {
            $role->permissions()->detach(
                Permission::whereIn('name', $permissions)->pluck('id')
            );
        }

        // Delete permissions
        Permission::whereIn('name', $permissions)->delete();
    }
};
