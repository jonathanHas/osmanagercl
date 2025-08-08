<?php

namespace App\Http\Controllers;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;

class RoleTestController extends Controller
{
    /**
     * Display role and permission test page.
     */
    public function index(Request $request)
    {
        $user = $request->user();
        $roles = Role::withCount('users')->get();
        $permissions = Permission::getGroupedByModule();
        $users = User::with('role')->get();

        return view('roles.test', compact('user', 'roles', 'permissions', 'users'));
    }

    /**
     * Admin only test.
     */
    public function adminOnly()
    {
        return view('roles.admin-only');
    }

    /**
     * Manager only test.
     */
    public function managerOnly()
    {
        return view('roles.manager-only');
    }

    /**
     * Permission test.
     */
    public function salesReports()
    {
        return view('roles.sales-reports');
    }
}
