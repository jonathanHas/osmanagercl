<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create Roles
        $adminRole = Role::create([
            'name' => 'admin',
            'display_name' => 'Administrator',
            'description' => 'Full system access with all permissions',
        ]);

        $managerRole = Role::create([
            'name' => 'manager',
            'display_name' => 'Manager',
            'description' => 'Access to sales data, reports, and management functions',
        ]);

        $employeeRole = Role::create([
            'name' => 'employee',
            'display_name' => 'Employee',
            'description' => 'Basic operational access for daily tasks',
        ]);

        // Create Permissions

        // Product Management Permissions
        $permissions = [
            // Product Management
            [
                'name' => 'products.view',
                'display_name' => 'View Products',
                'description' => 'Can view product listings and details',
                'module' => 'Product Management',
            ],
            [
                'name' => 'products.create',
                'display_name' => 'Create Products',
                'description' => 'Can create new products',
                'module' => 'Product Management',
            ],
            [
                'name' => 'products.edit',
                'display_name' => 'Edit Products',
                'description' => 'Can edit product details',
                'module' => 'Product Management',
            ],
            [
                'name' => 'products.delete',
                'display_name' => 'Delete Products',
                'description' => 'Can delete products',
                'module' => 'Product Management',
            ],
            [
                'name' => 'products.manage_pricing',
                'display_name' => 'Manage Product Pricing',
                'description' => 'Can update product prices and costs',
                'module' => 'Product Management',
            ],
            [
                'name' => 'products.manage_barcodes',
                'display_name' => 'Manage Product Barcodes',
                'description' => 'Can edit product barcodes',
                'module' => 'Product Management',
            ],

            // Sales & Analytics
            [
                'name' => 'sales.view_reports',
                'display_name' => 'View Sales Reports',
                'description' => 'Can view sales reports and summaries',
                'module' => 'Sales & Analytics',
            ],
            [
                'name' => 'sales.view_analytics',
                'display_name' => 'View Sales Analytics',
                'description' => 'Can view analytics dashboards',
                'module' => 'Sales & Analytics',
            ],
            [
                'name' => 'sales.export_data',
                'display_name' => 'Export Sales Data',
                'description' => 'Can export sales data',
                'module' => 'Sales & Analytics',
            ],
            [
                'name' => 'sales.import_data',
                'display_name' => 'Import Sales Data',
                'description' => 'Can import sales data',
                'module' => 'Sales & Analytics',
            ],

            // Delivery Management
            [
                'name' => 'deliveries.view',
                'display_name' => 'View Deliveries',
                'description' => 'Can view delivery listings',
                'module' => 'Delivery Management',
            ],
            [
                'name' => 'deliveries.create',
                'display_name' => 'Create Deliveries',
                'description' => 'Can create new deliveries',
                'module' => 'Delivery Management',
            ],
            [
                'name' => 'deliveries.process',
                'display_name' => 'Process Deliveries',
                'description' => 'Can process and verify deliveries',
                'module' => 'Delivery Management',
            ],
            [
                'name' => 'deliveries.manage',
                'display_name' => 'Manage Deliveries',
                'description' => 'Can edit and delete deliveries',
                'module' => 'Delivery Management',
            ],

            // Label Management
            [
                'name' => 'labels.view',
                'display_name' => 'View Labels',
                'description' => 'Can view label queue',
                'module' => 'Label Management',
            ],
            [
                'name' => 'labels.print',
                'display_name' => 'Print Labels',
                'description' => 'Can print labels',
                'module' => 'Label Management',
            ],
            [
                'name' => 'labels.manage',
                'display_name' => 'Manage Labels',
                'description' => 'Can manage label queue',
                'module' => 'Label Management',
            ],

            // Category Management
            [
                'name' => 'categories.view',
                'display_name' => 'View Categories',
                'description' => 'Can view product categories',
                'module' => 'Category Management',
            ],
            [
                'name' => 'categories.manage',
                'display_name' => 'Manage Categories',
                'description' => 'Can edit category settings',
                'module' => 'Category Management',
            ],
            [
                'name' => 'fruit_veg.manage',
                'display_name' => 'Manage Fruit & Veg',
                'description' => 'Can manage fruit and vegetable module',
                'module' => 'Category Management',
            ],
            [
                'name' => 'coffee.manage',
                'display_name' => 'Manage Coffee',
                'description' => 'Can manage coffee module',
                'module' => 'Category Management',
            ],

            // User Management
            [
                'name' => 'users.view',
                'display_name' => 'View Users',
                'description' => 'Can view user list',
                'module' => 'User Management',
            ],
            [
                'name' => 'users.create',
                'display_name' => 'Create Users',
                'description' => 'Can create new users',
                'module' => 'User Management',
            ],
            [
                'name' => 'users.edit',
                'display_name' => 'Edit Users',
                'description' => 'Can edit user details',
                'module' => 'User Management',
            ],
            [
                'name' => 'users.delete',
                'display_name' => 'Delete Users',
                'description' => 'Can delete users',
                'module' => 'User Management',
            ],
            [
                'name' => 'users.manage_roles',
                'display_name' => 'Manage User Roles',
                'description' => 'Can assign and change user roles',
                'module' => 'User Management',
            ],

            // System Settings
            [
                'name' => 'settings.view',
                'display_name' => 'View Settings',
                'description' => 'Can view system settings',
                'module' => 'System Settings',
            ],
            [
                'name' => 'settings.manage',
                'display_name' => 'Manage Settings',
                'description' => 'Can modify system settings',
                'module' => 'System Settings',
            ],
            [
                'name' => 'system.backup',
                'display_name' => 'Create Backups',
                'description' => 'Can create system backups',
                'module' => 'System Settings',
            ],
            [
                'name' => 'system.maintenance',
                'display_name' => 'Maintenance Mode',
                'description' => 'Can enable/disable maintenance mode',
                'module' => 'System Settings',
            ],
        ];

        // Create all permissions
        foreach ($permissions as $permissionData) {
            Permission::create($permissionData);
        }

        // Assign permissions to roles

        // Employee Role Permissions
        $employeePermissions = [
            'products.view',
            'deliveries.view',
            'deliveries.process',
            'labels.view',
            'labels.print',
            'categories.view',
            'fruit_veg.manage',
            'coffee.manage',
        ];

        foreach ($employeePermissions as $permission) {
            $employeeRole->givePermissionTo($permission);
        }

        // Manager Role Permissions (includes all employee permissions plus more)
        $managerPermissions = array_merge($employeePermissions, [
            'products.edit',
            'products.manage_pricing',
            'sales.view_reports',
            'sales.view_analytics',
            'sales.export_data',
            'deliveries.create',
            'deliveries.manage',
            'labels.manage',
            'categories.manage',
            'users.view',
        ]);

        foreach ($managerPermissions as $permission) {
            $managerRole->givePermissionTo($permission);
        }

        // Admin gets all permissions automatically through the HasPermissions trait
        // but we can still assign them explicitly if needed
        $allPermissions = Permission::all();
        foreach ($allPermissions as $permission) {
            $adminRole->givePermissionTo($permission);
        }

        $this->command->info('Roles and permissions created successfully!');
    }
}
