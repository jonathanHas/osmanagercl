<?php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class UserManagementController extends Controller
{
    public function index()
    {
        $users = User::with('role')->paginate(15);

        return view('users.index', compact('users'));
    }

    public function create()
    {
        $roles = Role::orderBy('name')->get();
        $defaultRole = Role::where('name', 'employee')->first();

        return view('users.create', compact('roles', 'defaultRole'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'username' => 'nullable|string|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
            'role_id' => 'nullable|exists:roles,id',
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'username' => $request->username,
            'password' => Hash::make($request->password),
            'role_id' => $request->role_id,
        ]);

        $roleName = $user->role ? $user->role->display_name : 'No Role';

        return redirect()->route('users.index')
            ->with('success', "User '{$user->name}' created successfully with role: {$roleName}.");
    }

    public function edit(User $user)
    {
        $roles = Role::orderBy('name')->get();
        $user->load('role');

        return view('users.edit', compact('user', 'roles'));
    }

    public function update(Request $request, User $user)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email,'.$user->id,
            'username' => 'nullable|string|max:255|unique:users,username,'.$user->id,
            'password' => 'nullable|string|min:8|confirmed',
            'role_id' => 'nullable|exists:roles,id',
        ]);

        // Prevent users from changing their own role to a lower privilege
        if ($user->id === Auth::id() && $request->role_id != $user->role_id) {
            $currentRole = $user->role;
            $newRole = Role::find($request->role_id);

            if ($currentRole && $currentRole->name === 'admin' &&
                (! $newRole || $newRole->name !== 'admin')) {
                return redirect()->back()
                    ->withErrors(['role_id' => 'You cannot change your own admin role.']);
            }
        }

        // Prevent removal of the last admin role
        if ($user->role && $user->role->name === 'admin') {
            $newRole = Role::find($request->role_id);
            if (! $newRole || $newRole->name !== 'admin') {
                $adminCount = User::whereHas('role', function ($query) {
                    $query->where('name', 'admin');
                })->count();

                if ($adminCount <= 1) {
                    return redirect()->back()
                        ->withErrors(['role_id' => 'Cannot remove the last admin user. At least one admin must remain.']);
                }
            }
        }

        $oldRole = $user->role ? $user->role->display_name : 'No Role';

        $data = [
            'name' => $request->name,
            'email' => $request->email,
            'username' => $request->username,
            'role_id' => $request->role_id,
        ];

        if ($request->password) {
            $data['password'] = Hash::make($request->password);
        }

        $user->update($data);
        $user->load('role');

        $newRole = $user->role ? $user->role->display_name : 'No Role';
        $message = "User '{$user->name}' updated successfully.";

        if ($oldRole !== $newRole) {
            $message .= " Role changed from '{$oldRole}' to '{$newRole}'.";
        }

        return redirect()->route('users.index')->with('success', $message);
    }

    public function destroy(User $user)
    {
        // Prevent deleting the current user
        if ($user->id === Auth::id()) {
            return redirect()->route('users.index')->with('error', 'You cannot delete your own account.');
        }

        $user->delete();

        return redirect()->route('users.index')->with('success', 'User deleted successfully.');
    }
}
