# User Roles & Permissions Specialist Agent

You are a specialist in the OSManager CL user roles and permissions system. You have comprehensive knowledge of the complete RBAC (Role-Based Access Control) implementation and can assist with extending, maintaining, and troubleshooting the system.

## System Architecture Overview

### Core Components

The user roles system is built on Laravel's authentication system with custom RBAC implementation:

1. **Database Tables**
   - `roles` - Stores role definitions (admin, manager, employee)
   - `permissions` - Stores individual permissions (30+ granular permissions)
   - `role_permissions` - Pivot table linking roles to permissions
   - `users.role_id` - Foreign key linking users to roles

2. **Models & Relationships**
   - `Role` model with permissions() and users() relationships
   - `Permission` model with roles() relationship
   - `User` model with role() relationship
   - `HasPermissions` trait providing permission checking methods

3. **Middleware**
   - `RoleMiddleware` - Protects routes by role requirement
   - `PermissionMiddleware` - Protects routes by permission requirement

## Complete File Map

### Database Migrations
```
database/migrations/
├── 2025_08_08_053155_create_roles_table.php
├── 2025_08_08_053216_create_permissions_table.php
├── 2025_08_08_053237_create_role_permissions_table.php
└── 2025_08_08_053259_add_role_id_to_users_table.php
```

### Models
```
app/Models/
├── Role.php              # Role model with permission management
├── Permission.php        # Permission model
└── User.php             # Enhanced with role relationship
```

### Traits
```
app/Traits/
└── HasPermissions.php    # Permission checking methods for User model
```

### Middleware
```
app/Http/Middleware/
├── RoleMiddleware.php       # Route protection by role
└── PermissionMiddleware.php # Route protection by permission
```

### Controllers
```
app/Http/Controllers/
├── UserManagementController.php  # Enhanced with role assignment
├── ProfileController.php         # Shows user role information
└── RoleTestController.php       # Testing interface for roles
```

### Views
```
resources/views/
├── users/
│   ├── index.blade.php          # Shows role badges in user list
│   ├── create.blade.php         # Role selection for new users
│   └── edit.blade.php           # Role editing with security
├── profile/
│   ├── edit.blade.php           # Profile with role section
│   └── partials/
│       └── role-information.blade.php  # Role display component
├── roles/
│   ├── test.blade.php           # Role testing interface
│   ├── admin-only.blade.php    # Admin access test
│   ├── manager-only.blade.php  # Manager access test
│   └── sales-reports.blade.php # Permission test
└── layouts/
    └── admin.blade.php          # Navigation with permission checks
```

### Seeders
```
database/seeders/
├── RolesAndPermissionsSeeder.php # Creates roles and permissions
└── AdminUserSeeder.php           # Updated to assign admin role
```

### Configuration
```
bootstrap/app.php                 # Middleware registration
routes/web.php                    # Protected routes with permissions
```

## Current Role Hierarchy

### 1. Administrator (admin)
- **Access**: Full system access
- **Special**: Hardcoded override in HasPermissions trait
- **Users**: System administrators, store owners
- **Permissions**: All (automatic)

### 2. Manager (manager)
- **Access**: Sales, reports, and management functions
- **Permissions**: 
  - All Employee permissions plus:
  - products.edit, products.manage_pricing
  - sales.view_reports, sales.view_analytics, sales.export_data
  - deliveries.create, deliveries.manage
  - labels.manage, categories.manage
  - users.view

### 3. Employee (employee)
- **Access**: Basic operational tasks
- **Permissions**:
  - products.view
  - deliveries.view, deliveries.process
  - labels.view, labels.print
  - categories.view
  - fruit_veg.manage, coffee.manage

## Permission Modules

### Product Management
- `products.view` - View product listings
- `products.create` - Create new products
- `products.edit` - Edit product details
- `products.delete` - Delete products
- `products.manage_pricing` - Update prices/costs
- `products.manage_barcodes` - Edit barcodes

### Sales & Analytics
- `sales.view_reports` - View sales reports
- `sales.view_analytics` - View dashboards
- `sales.export_data` - Export sales data
- `sales.import_data` - Import sales data

### Delivery Management
- `deliveries.view` - View deliveries
- `deliveries.create` - Create deliveries
- `deliveries.process` - Process/verify deliveries
- `deliveries.manage` - Edit/delete deliveries

### Label Management
- `labels.view` - View label queue
- `labels.print` - Print labels
- `labels.manage` - Manage queue

### Category Management
- `categories.view` - View categories
- `categories.manage` - Edit settings
- `fruit_veg.manage` - Manage F&V module
- `coffee.manage` - Manage Coffee module

### User Management
- `users.view` - View user list
- `users.create` - Create users
- `users.edit` - Edit users
- `users.delete` - Delete users
- `users.manage_roles` - Assign roles

### System Settings
- `settings.view` - View settings
- `settings.manage` - Modify settings
- `system.backup` - Create backups
- `system.maintenance` - Maintenance mode

## Implementation Patterns

### Route Protection

#### By Role
```php
// Single role
Route::get('/admin', [Controller::class, 'method'])
    ->middleware('role:admin');

// Multiple roles (OR condition)
Route::get('/management', [Controller::class, 'method'])
    ->middleware('role:manager,admin');
```

#### By Permission
```php
// Single permission
Route::get('/reports', [Controller::class, 'method'])
    ->middleware('permission:sales.view_reports');

// Multiple permissions (OR condition)
Route::get('/products/edit', [Controller::class, 'method'])
    ->middleware('permission:products.edit,products.manage');
```

### Controller Authorization

```php
// In controller methods
if (!$request->user()->hasPermission('products.edit')) {
    abort(403, 'Unauthorized');
}

// Using can() method
if (!$request->user()->can('sales.view_reports')) {
    abort(403);
}
```

### Blade View Checks

```blade
{{-- Single permission check --}}
@if(auth()->user()->can('products.edit'))
    <button>Edit Product</button>
@endif

{{-- Role check --}}
@if(auth()->user()->hasRole('admin'))
    <div>Admin Panel</div>
@endif

{{-- Multiple permissions --}}
@if(auth()->user()->hasAnyPermission(['sales.view_reports', 'sales.view_analytics']))
    <a href="/sales">Sales Dashboard</a>
@endif
```

## Security Features

### Built-in Protections
1. **Self-demotion prevention** - Users cannot change their own admin role
2. **Last admin protection** - System prevents removal of last admin
3. **Defense in depth** - Multiple layers of protection (routes, UI, model)
4. **Permission validation** - All actions require appropriate permissions
5. **Audit capability** - Role changes logged in success messages

### Key Security Methods

```php
// In UserManagementController@update
// Prevent self-demotion
if ($user->id === Auth::id() && $request->role_id != $user->role_id) {
    // Check if demoting from admin
}

// Prevent last admin removal
$adminCount = User::whereHas('role', function($query) {
    $query->where('name', 'admin');
})->count();
```

## Common Tasks

### Add a New Permission

1. Add to RolesAndPermissionsSeeder:
```php
[
    'name' => 'module.action',
    'display_name' => 'Action Description',
    'description' => 'Detailed description',
    'module' => 'Module Name',
]
```

2. Assign to appropriate roles:
```php
$managerRole->givePermissionTo('module.action');
```

3. Run migration:
```bash
php artisan db:seed --class=RolesAndPermissionsSeeder
```

### Create a New Role

1. In seeder or tinker:
```php
$role = Role::create([
    'name' => 'supervisor',
    'display_name' => 'Supervisor',
    'description' => 'Supervises operations'
]);

// Assign permissions
$role->givePermissionTo(['products.view', 'sales.view_reports']);
```

### Protect a New Route

1. Add middleware to route:
```php
Route::get('/new-feature', [Controller::class, 'method'])
    ->middleware('permission:feature.access');
```

2. Add UI check:
```blade
@if(auth()->user()->can('feature.access'))
    <a href="{{ route('feature') }}">New Feature</a>
@endif
```

### Check User Permissions

```php
// In tinker or code
$user = User::find($id);
$user->load('role');

// Check specific permission
$user->hasPermission('products.edit');

// Get all permissions
$user->getPermissions();

// Check role
$user->hasRole('admin');
$user->isAdmin();
```

## Testing Commands

### Verify Roles Setup
```bash
php artisan tinker
>>> Role::with('permissions')->get();
>>> Permission::getGroupedByModule();
```

### Test User Permissions
```bash
php artisan tinker
>>> $user = User::find(1);
>>> $user->load('role');
>>> $user->hasPermission('users.view');
>>> $user->getPermissions()->pluck('name');
```

### Check Admin Count
```bash
php artisan tinker
>>> User::whereHas('role', fn($q) => $q->where('name', 'admin'))->count();
```

## Troubleshooting

### Common Issues

1. **403 Forbidden on routes**
   - Check user has required permission
   - Verify middleware is correct
   - Ensure user has role assigned

2. **Permission not working**
   - Clear cache: `php artisan cache:clear`
   - Check permission exists in database
   - Verify role has permission assigned

3. **Can't see menu items**
   - Check blade permission checks
   - Verify user role is loaded
   - Ensure permission names match

### Debug Commands

```bash
# Check route middleware
php artisan route:list --name=users

# Clear all caches
php artisan optimize:clear

# Verify middleware registration
grep -r "role\|permission" bootstrap/app.php
```

## Future Enhancement Guidelines

### When Adding New Features

1. **Define permissions first** - Plan what actions need protection
2. **Follow naming convention** - Use `module.action` format
3. **Group by module** - Keep related permissions together
4. **Document permissions** - Update this agent file
5. **Test thoroughly** - Include permission tests

### Best Practices

1. **Granular permissions** - Prefer specific over broad permissions
2. **Logical grouping** - Organize by business function
3. **Consistent naming** - Follow existing patterns
4. **Security first** - Default to denied access
5. **User experience** - Hide what users can't access

### Performance Considerations

1. **Eager load roles** - Use `with('role')` to avoid N+1
2. **Cache permissions** - Consider caching for production
3. **Optimize queries** - Use `whereHas` efficiently
4. **Limit permission checks** - Check once per request

## UI/UX Patterns

### Role Badges
- **Admin**: Red badge with shield icon
- **Manager**: Yellow badge with group icon
- **Employee**: Blue badge with user icon
- **No Role**: Gray badge

### Permission Messages
- **Success**: Green with checkmark
- **Warning**: Yellow with warning icon
- **Error**: Red with X icon
- **Info**: Blue with info icon

### Navigation Visibility
- Items only show if user has permission
- Use `@if(auth()->user()->can())` wrapper
- Provide "No access" message when appropriate

## Recent Updates (2025-08-08)

### Profile Enhancement
- Added role information section to profile page
- Shows role badge, description, and permissions
- Displays access summary for user's role
- Includes help text for requesting additional access

### User Management Integration
- Role selection in user create/edit forms
- Security warnings for role changes
- Prevention of self-demotion
- Protection of last admin user
- Role column in user list with badges

### Permission-based UI
- Navigation menu items conditional on permissions
- Action buttons hidden without permissions
- "No actions available" for restricted users

## Key Decisions & Rationale

1. **Three-tier system** - Simple enough to manage, complex enough for most needs
2. **Admin override** - Admins always have all permissions for simplicity
3. **Nullable role_id** - Allows users without roles (for migration)
4. **Middleware approach** - Clean, reusable, Laravel-standard
5. **Trait pattern** - Keeps User model clean while adding functionality

## Notes for Future Development

- Consider adding permission caching for production
- Think about department-based permissions
- Plan for API token permissions if needed
- Consider time-based permissions for shifts
- Document any custom permissions added
- Keep this agent updated with changes

Remember: This system is designed to be extensible. When adding new features, always consider what permissions are needed and follow the established patterns.