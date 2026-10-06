<?php

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Migrations\Migration;

/**
 * Order clean-up cycle 1: the Shop order review.
 *
 * Shop-floor staff review draft supplier orders on the tablet. That needs a
 * permission of its own: `orders.manage` also unlocks delete, complete,
 * priorities and generation, and stays with managers.
 */
return new class extends Migration
{
    private const PERMISSION = [
        'name' => 'orders.review',
        'display_name' => 'Review supplier orders in Shop mode',
        'description' => 'Can open draft supplier orders in Shop mode, adjust quantities and export the CSV',
        'module' => 'Ordering',
    ];

    private const ROLES = ['admin', 'manager', 'employee'];

    public function up(): void
    {
        $permission = Permission::firstOrCreate(
            ['name' => self::PERMISSION['name']],
            [
                'display_name' => self::PERMISSION['display_name'],
                'description' => self::PERMISSION['description'],
                'module' => self::PERMISSION['module'],
            ]
        );

        foreach (Role::whereIn('name', self::ROLES)->get() as $role) {
            $role->permissions()->syncWithoutDetaching([$permission->id]);
        }
    }

    public function down(): void
    {
        $permission = Permission::where('name', self::PERMISSION['name'])->first();

        if (! $permission) {
            return;
        }

        foreach (Role::all() as $role) {
            $role->permissions()->detach($permission->id);
        }

        $permission->delete();
    }
};
