# User Roles & Permissions System

## Overview

The User Roles & Permissions system provides comprehensive role-based access control (RBAC) for OSManager CL. This system enables fine-grained control over user access to different features and data, ensuring that employees only have access to the functionality they need for their role.

> **🤖 Claude Code Agent Available**: A specialized agent for this system is available at `.claude/agents/user_roles_agent.md`. This agent has comprehensive knowledge of the entire implementation and can assist with extending, maintaining, and troubleshooting the roles system.

## Architecture

### Database Schema

#### Tables
- **`roles`** - Stores role definitions
  - `id` - Primary key
  - `name` - Unique role identifier (admin, manager, employee)
  - `display_name` - Human-readable name
  - `description` - Role description
  - `timestamps`

- **`permissions`** - Stores individual permissions
  - `id` - Primary key
  - `name` - Unique permission identifier (e.g., 'products.edit')
  - `display_name` - Human-readable name
  - `description` - Permission description
  - `module` - Module grouping for organization
  - `timestamps`

- **`role_permissions`** - Pivot table linking roles to permissions
  - `role_id` - Foreign key to roles
  - `permission_id` - Foreign key to permissions
  - `timestamps`
  - Primary key on (role_id, permission_id)

- **`users`** - Extended with role support
  - Added `role_id` - Foreign key to roles (nullable)

### Models

#### Role Model (`app/Models/Role.php`)
- Manages role definitions and relationships
- Methods:
  - `permissions()` - BelongsToMany relationship
  - `users()` - HasMany relationship
  - `hasPermission(string $permission)` - Check single permission
  - `hasAnyPermission(array $permissions)` - Check any permission
  - `hasAllPermissions(array $permissions)` - Check all permissions
  - `givePermissionTo($permission)` - Attach permission
  - `revokePermissionTo($permission)` - Detach permission
  - `syncPermissions(array $permissions)` - Sync permissions

#### Permission Model (`app/Models/Permission.php`)
- Manages permission definitions
- Methods:
  - `roles()` - BelongsToMany relationship
  - `getGroupedByModule()` - Get permissions grouped by module
  - `getByModule(string $module)` - Get permissions for specific module

#### HasPermissions Trait (`app/Traits/HasPermissions.php`)
- Applied to User model
- Provides permission checking methods:
  - `hasPermission(string $permission)` - Check single permission
  - `hasAnyPermission(array $permissions)` - Check any permission
  - `hasAllPermissions(array $permissions)` - Check all permissions
  - `hasRole(string $roleName)` - Check user role
  - `hasAnyRole(array $roleNames)` - Check any role
  - `getPermissions()` - Get all user permissions
  - `can($abilities, $arguments = [])` - Override Laravel's can method
  - `assignRole($role)` - Assign role to user
  - `removeRole()` - Remove user's role
  - `isAdmin()`, `isManager()`, `isEmployee()` - Role checking helpers

### Middleware

#### RoleMiddleware (`app/Http/Middleware/RoleMiddleware.php`)
Protects routes by role requirement.

Usage:
```php
Route::get('/admin', [Controller::class, 'method'])
    ->middleware('role:admin');

Route::get('/management', [Controller::class, 'method'])
    ->middleware('role:manager,admin');
```

#### PermissionMiddleware (`app/Http/Middleware/PermissionMiddleware.php`)
Protects routes by permission requirement.

Usage:
```php
Route::get('/sales', [Controller::class, 'method'])
    ->middleware('permission:sales.view_reports');

Route::get('/products/edit', [Controller::class, 'method'])
    ->middleware('permission:products.edit,products.manage');
```

## Default Roles

### Administrator (`admin`)
- **Description**: Full system access with all permissions
- **Special Behavior**: Automatically has all permissions (hardcoded in HasPermissions trait)
- **Use Case**: System administrators, store owners

### Manager (`manager`)
- **Description**: Access to sales data, reports, and management functions
- **Key Permissions**:
  - All Employee permissions
  - Product editing and pricing management
  - Sales reports and analytics access
  - Delivery creation and management
  - Category management
  - User viewing
- **Use Case**: Store managers, supervisors

### Employee (`employee`)
- **Description**: Basic operational access for daily tasks
- **Key Permissions**:
  - View products
  - View and process deliveries
  - View and print labels
  - View categories
  - Manage Fruit & Veg module
  - Manage Coffee module
- **Use Case**: Shop floor staff, cashiers

## Permission Modules

### Product Management
- `products.view` - View product listings and details
- `products.create` - Create new products
- `products.edit` - Edit product details
- `products.delete` - Delete products
- `products.manage_pricing` - Update prices and costs
- `products.manage_barcodes` - Edit product barcodes

### Sales & Analytics
- `sales.view_reports` - View sales reports
- `sales.view_analytics` - View analytics dashboards
- `sales.export_data` - Export sales data
- `sales.import_data` - Import sales data

### Delivery Management
- `deliveries.view` - View delivery listings
- `deliveries.create` - Create new deliveries
- `deliveries.process` - Process and verify deliveries
- `deliveries.manage` - Edit and delete deliveries

### Label Management
- `labels.view` - View label queue
- `labels.print` - Print labels
- `labels.manage` - Manage label queue

### Category Management
- `categories.view` - View product categories
- `categories.manage` - Edit category settings
- `fruit_veg.manage` - Manage Fruit & Veg module
- `coffee.manage` - Manage Coffee module

### User Management
- `users.view` - View user list
- `users.create` - Create new users
- `users.edit` - Edit user details
- `users.delete` - Delete users
- `users.manage_roles` - Assign and change user roles

### System Settings
- `settings.view` - View system settings
- `settings.manage` - Modify system settings
- `system.backup` - Create system backups
- `system.maintenance` - Enable/disable maintenance mode

## Usage Examples

### Protecting Routes

#### By Role
```php
// Single role
Route::get('/admin-dashboard', [AdminController::class, 'dashboard'])
    ->middleware('role:admin');

// Multiple roles (OR condition)
Route::get('/reports', [ReportsController::class, 'index'])
    ->middleware('role:manager,admin');
```

#### By Permission
```php
// Single permission
Route::post('/products', [ProductController::class, 'store'])
    ->middleware('permission:products.create');

// Multiple permissions (OR condition)
Route::get('/sales', [SalesController::class, 'index'])
    ->middleware('permission:sales.view_reports,sales.view_analytics');
```

### Checking Permissions in Controllers

```php
class ProductController extends Controller
{
    public function edit(Request $request, $id)
    {
        // Method 1: Using hasPermission
        if (!$request->user()->hasPermission('products.edit')) {
            abort(403, 'Unauthorized');
        }

        // Method 2: Using can (works with Laravel's authorization)
        if (!$request->user()->can('products.edit')) {
            abort(403);
        }

        // Method 3: Using Gate (after defining gate)
        $this->authorize('edit-products');

        // Continue with edit logic...
    }
}
```

### Checking Permissions in Blade Views

```blade
{{-- Check single permission --}}
@if(auth()->user()->can('products.edit'))
    <button>Edit Product</button>
@endif

{{-- Check role --}}
@if(auth()->user()->hasRole('admin'))
    <a href="/admin">Admin Panel</a>
@endif

{{-- Check any permission --}}
@if(auth()->user()->hasAnyPermission(['sales.view_reports', 'sales.view_analytics']))
    <a href="/sales">Sales Dashboard</a>
@endif

{{-- Using Blade directives (if gates are defined) --}}
@can('edit-products')
    <button>Edit</button>
@endcan

@cannot('delete-products')
    <p>You cannot delete this product</p>
@endcannot
```

### Assigning Roles to Users

```php
// In a controller or command
$user = User::find($userId);

// Assign role by name
$user->assignRole('manager');

// Assign role by model
$role = Role::where('name', 'employee')->first();
$user->assignRole($role);

// Remove role
$user->removeRole();

// Check role
if ($user->isManager()) {
    // Manager-specific logic
}
```

## Database Seeding

### Running the Seeder
```bash
# Run roles and permissions seeder
php artisan db:seed --class=RolesAndPermissionsSeeder

# Update admin user with admin role
php artisan db:seed --class=AdminUserSeeder
```

### Creating Custom Seeders
```php
// Example: Assign roles to existing users
$managers = User::whereIn('email', ['manager1@example.com', 'manager2@example.com'])->get();
foreach ($managers as $manager) {
    $manager->assignRole('manager');
}

$employees = User::whereNotIn('email', ['admin@osmanager.local', 'manager1@example.com'])->get();
foreach ($employees as $employee) {
    $employee->assignRole('employee');
}
```

## Testing

### Test Page
A comprehensive test page is available at `/roles-test` (when authenticated) that displays:
- Current user's role and permissions
- Test links to protected routes
- System roles overview
- All system users and their roles

### Test Routes
- `/roles-test/admin-only` - Requires admin role
- `/roles-test/manager-only` - Requires manager or admin role
- `/roles-test/sales-reports` - Requires sales.view_reports permission

## Migration Guide

### For Existing Applications

1. **Run Migrations**
   ```bash
   php artisan migrate
   ```

2. **Seed Roles and Permissions**
   ```bash
   php artisan db:seed --class=RolesAndPermissionsSeeder
   ```

3. **Assign Admin Role to Existing Admin**
   ```bash
   php artisan db:seed --class=AdminUserSeeder
   ```

4. **Assign Roles to Existing Users**
   ```php
   // In tinker or a migration
   User::where('is_admin', true)->each(function ($user) {
       $user->assignRole('admin');
   });
   ```

5. **Update Routes**
   Add middleware to protect sensitive routes:
   ```php
   Route::middleware(['auth', 'role:admin'])->group(function () {
       // Admin routes
   });
   ```

6. **Update Views**
   Add permission checks to UI elements:
   ```blade
   @if(auth()->user()->can('products.edit'))
       <!-- Show edit button -->
   @endif
   ```

## Best Practices

### 1. Permission Naming Convention
- Use dot notation: `module.action`
- Examples: `products.edit`, `sales.view_reports`
- Keep names consistent and descriptive

### 2. Role Hierarchy
- Design roles hierarchically (Employee < Manager < Admin)
- Managers should have all Employee permissions plus additional ones
- Use the permission assignment pattern in seeder for clarity

### 3. Middleware Usage
- Use role middleware for broad access control
- Use permission middleware for specific feature access
- Combine with Laravel's `auth` middleware

### 4. UI/UX Considerations
- Hide UI elements users can't access
- Provide clear error messages for unauthorized access
- Show user's role in profile or navigation

### 5. Security
- Always check permissions on both frontend and backend
- Use database transactions when modifying roles/permissions
- Log permission changes for audit trails
- Regularly review and audit user permissions

## Troubleshooting

### Common Issues

#### 1. "Declaration must be compatible" Error
**Problem**: The `can()` method in HasPermissions trait conflicts with Laravel's.
**Solution**: Ensure the method signature matches Laravel's:
```php
public function can($abilities, $arguments = [])
```

#### 2. Middleware Not Working
**Problem**: Routes not being protected.
**Solution**: Ensure middleware is registered in `bootstrap/app.php`:
```php
->withMiddleware(function (Middleware $middleware): void {
    $middleware->alias([
        'role' => \App\Http\Middleware\RoleMiddleware::class,
        'permission' => \App\Http\Middleware\PermissionMiddleware::class,
    ]);
})
```

#### 3. Admin Not Getting All Permissions
**Problem**: Admin role not automatically having all permissions.
**Solution**: Check the HasPermissions trait has the admin check:
```php
if ($this->hasRole('admin')) {
    return true;
}
```

## Future Enhancements

### Planned Features
- [ ] Permission caching for performance
- [ ] Dynamic permission creation UI
- [ ] Role templates for quick setup
- [ ] Permission delegation (temporary permissions)
- [ ] Activity logging for permission usage
- [ ] Two-factor authentication for admin roles
- [ ] API token permissions
- [ ] Department-based permissions
- [ ] Time-based permissions (shift-based access)

### Potential Improvements
- Implement permission inheritance
- Add permission groups for easier management
- Create permission presets for common store types
- Add bulk user role assignment UI
- Implement role-based dashboards

## Recent Updates

### Profile Role Display (2025-08-08)
- Added comprehensive role information section to user profile page
- Shows role badge with color coding and icons
- Displays role description and permission count
- Includes access summary tailored to each role type
- Shows key permissions the user has
- Provides guidance for requesting additional access
- Special handling for users without assigned roles

### User Management Role Integration (2025-08-08)
- Role selection dropdown in user create/edit forms
- Automatic role assignment with Employee as default
- Security warnings for role changes
- Prevention of self-demotion for admins
- Protection against removing the last admin user
- Role column with badges in user list
- Permission-based UI controls for actions

## Related Documentation

- [Authentication System](../authentication.md)
- [User Management](./user-management.md)
- [API Authentication](../api/authentication.md)
- [Security Best Practices](../security.md)
- **[User Roles Agent](../../.claude/agents/user_roles_agent.md)** - Specialized Claude Code agent for this system