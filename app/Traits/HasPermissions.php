<?php

namespace App\Traits;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Support\Collection;

trait HasPermissions
{
    /**
     * Check if the user has a specific permission.
     */
    public function hasPermission(string $permission): bool
    {
        // Super admin check (if user has admin role)
        if ($this->hasRole('admin')) {
            return true;
        }

        // Check if user's role has the permission
        return $this->role && $this->role->hasPermission($permission);
    }

    /**
     * Check if the user has any of the given permissions.
     */
    public function hasAnyPermission(array $permissions): bool
    {
        // Super admin check
        if ($this->hasRole('admin')) {
            return true;
        }

        return $this->role && $this->role->hasAnyPermission($permissions);
    }

    /**
     * Check if the user has all of the given permissions.
     */
    public function hasAllPermissions(array $permissions): bool
    {
        // Super admin check
        if ($this->hasRole('admin')) {
            return true;
        }

        return $this->role && $this->role->hasAllPermissions($permissions);
    }

    /**
     * Check if the user has a specific role.
     */
    public function hasRole(string $roleName): bool
    {
        return $this->role && $this->role->name === $roleName;
    }

    /**
     * Check if the user has any of the given roles.
     */
    public function hasAnyRole(array $roleNames): bool
    {
        return $this->role && in_array($this->role->name, $roleNames);
    }

    /**
     * Get all permissions for the user through their role.
     */
    public function getPermissions(): Collection
    {
        if (! $this->role) {
            return collect();
        }

        // Admin gets all permissions
        if ($this->hasRole('admin')) {
            return Permission::all();
        }

        return $this->role->permissions;
    }

    /**
     * Override Laravel's can method to check our permissions.
     */
    public function can($abilities, $arguments = [])
    {
        // If it's a string permission, check our custom permissions
        if (is_string($abilities) && empty($arguments)) {
            // Check if it's one of our custom permissions
            if ($this->hasPermission($abilities)) {
                return true;
            }
        }

        // Fall back to Laravel's default authorization
        return parent::can($abilities, $arguments);
    }

    /**
     * Assign a role to the user.
     */
    public function assignRole($role): void
    {
        if (is_string($role)) {
            $role = Role::where('name', $role)->firstOrFail();
        }

        $this->role()->associate($role);
        $this->save();
    }

    /**
     * Remove the user's role.
     */
    public function removeRole(): void
    {
        $this->role()->dissociate();
        $this->save();
    }

    /**
     * Check if the user is an admin.
     */
    public function isAdmin(): bool
    {
        return $this->hasRole('admin');
    }

    /**
     * Check if the user is a manager.
     */
    public function isManager(): bool
    {
        return $this->hasRole('manager');
    }

    /**
     * Check if the user is an employee.
     */
    public function isEmployee(): bool
    {
        return $this->hasRole('employee');
    }

    /**
     * Check if the user is a barista.
     */
    public function isBarista(): bool
    {
        return $this->hasRole('barista');
    }
}
