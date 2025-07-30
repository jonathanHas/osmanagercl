# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## 🚀 Quick Start for AI Assistants

1. **First Time?** Read this file completely for project context
2. **Working on a Feature?** Check [Documentation Guide](./docs/DOCUMENTATION_GUIDE.md) for where to find/update docs
3. **Need Details?** Look in `docs/features/` for specific feature documentation
4. **Making Changes?** Follow [Contributing Guidelines](./CONTRIBUTING.md)
5. **ALWAYS End Every Response**: MUST run `mpg123 /home/jon/Music/notification.mp3` as the final action of EVERY response - whether completing tasks, asking questions, presenting plans, or any other interaction

⚠️ **Important**: This file contains HIGH-LEVEL CONTEXT ONLY. Detailed documentation belongs in the `docs/` folder.

## Project Overview

This is a Laravel 12 application using PHP 8.2+ with Laravel Breeze for authentication. The project uses:
- **Frontend**: Blade templates with Tailwind CSS and Alpine.js
- **Build System**: Vite for asset compilation
- **Database**: SQLite (default) with Eloquent ORM
- **Authentication**: Laravel Breeze with email verification
- **Testing**: PHPUnit with Feature and Unit test suites

## Documentation

📚 **IMPORTANT: See [Documentation Usage Guide](./docs/DOCUMENTATION_GUIDE.md) for how to use and update documentation properly.**

Comprehensive documentation is organized in the `docs/` folder:

- **[Documentation Index](./docs/README.md)** - Complete documentation overview
- **[Architecture](./docs/architecture/overview.md)** - System design and patterns
- **[Features](./docs/features/)** - Detailed feature documentation
- **[Development](./docs/development/)** - Setup and development guides
- **[API Reference](./docs/api/)** - API documentation

## Planning

- **[Planning Documents](./planning/README.md)** - Future features and improvements
- **[Contributing](./CONTRIBUTING.md)** - Development guidelines and standards
- **[Changelog](./CHANGELOG.md)** - Version history and releases

## Development Commands

### Starting Development
```bash
# Install PHP dependencies
composer install

# Install Node.js dependencies
npm install

# Copy environment file (if needed)
cp .env.example .env

# Generate application key
php artisan key:generate

# Create database file and run migrations
touch database/database.sqlite
php artisan migrate

# Start development server (includes server, queue, logs, and Vite)
composer run dev
```

### Individual Services
```bash
# PHP development server
php artisan serve

# Vite development server for assets
npm run dev

# Queue worker
php artisan queue:listen --tries=1

# Log viewer
php artisan pail --timeout=0
```

### Building for Production
```bash
# Build frontend assets
npm run build

# Optimize Laravel for production
php artisan optimize
```

### Testing
```bash
# Run all tests
composer run test
# OR
php artisan test

# Run specific test suite
php artisan test --testsuite=Feature
php artisan test --testsuite=Unit

# Run specific test file
php artisan test tests/Feature/ExampleTest.php
```

### Code Quality
```bash
# Laravel Pint (code formatter)
./vendor/bin/pint

# Clear caches
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear
```

## Architecture

### Directory Structure
- `app/Http/Controllers/` - HTTP controllers including Auth controllers from Breeze
  - `ProductController.php` - Handles product listing and detail views
- `app/Models/` - Eloquent models
  - `User.php` - User authentication model
  - `Product.php` - POS product model (read-only, connects to 'pos' database)
  - `VegDetails.php` - POS veg details model (connects to 'pos' database, uses vegDetails table)
  - `VegClass.php` - POS class model (connects to 'pos' database, uses class table)
  - `Country.php` - Countries model (main database)
  - `VegUnit.php` - Units model (main database)
- `app/Repositories/` - Repository pattern for data access
  - `ProductRepository.php` - Handles product data queries and statistics
- `app/View/Components/` - Blade components (AppLayout, GuestLayout)
- `resources/views/` - Blade templates with auth views and dashboard
  - `products/` - Product listing and detail views
- `resources/js/` - JavaScript files (Alpine.js setup)
- `resources/css/` - CSS files (Tailwind CSS)
- `routes/` - Route definitions (web.php, auth.php)
- `database/migrations/` - Database migrations
- `tests/` - PHPUnit tests (Feature and Unit)

### Key Components
- **Authentication**: Laravel Breeze provides login, registration, password reset, and email verification
- **User Management**: Profile editing and account deletion functionality
- **Dual Database Support**: 
  - Primary database (SQLite/MySQL) for application data and configuration
  - Secondary POS connection for uniCenta product data (read-only)
  - Cross-database relationships for seamless data integration
- **Product Management**: ProductRepository provides clean interface to POS products
- **Veg Details Integration**: VegDetails model connects directly to POS database for real-time class, country, and unit data
- **Frontend**: Server-side rendered Blade templates with Tailwind CSS styling
- **Asset Pipeline**: Vite handles CSS and JavaScript compilation with hot reloading

### Development Best Practices

### Key Principles
- **Always Use Eloquent Models**: Check for existing models before accessing database tables directly
  - Use `exists:App\Models\ModelName,column` in validation rules instead of `exists:table_name,column`
  - Models handle database connections, table names, and configurations automatically
  - Example: Use `exists:App\Models\Supplier,SupplierID` not `exists:SUPPLIERS,SupplierID`
- **Follow Laravel Conventions**: Leverage Eloquent relationships and model configurations
- **Database Access**: Prefer model-based queries over raw database calls for consistency
- **Service Layer**: Extract complex business logic into service classes
- **Repository Pattern**: Use repositories for data access when appropriate
- **Testing**: Write tests for new features and bug fixes

For detailed coding standards, see [Contributing Guidelines](./CONTRIBUTING.md).

### Configuration
- Database configured for SQLite in `.env`
- Vite configuration in `vite.config.js` handles asset compilation
- Tailwind CSS configured in `tailwind.config.js` with forms plugin
- PHPUnit configured in `phpunit.xml` with SQLite in-memory testing database

## Database

The application uses SQLite by default. The database file is located at `database/database.sqlite`.

### Common Database Operations
```bash
# Create new migration
php artisan make:migration create_example_table

# Run migrations
php artisan migrate

# Rollback migrations
php artisan migrate:rollback

# Seed database
php artisan db:seed

# Access database directly
php artisan tinker
```

## Frontend Development

The frontend uses Blade templates with Tailwind CSS and Alpine.js:
- Tailwind CSS for styling with forms plugin
- Alpine.js for interactive components
- Vite for asset building and hot reloading
- Blade components for reusable UI elements

### Asset Files
- `resources/css/app.css` - Main CSS file
- `resources/js/app.js` - Main JavaScript file with Alpine.js
- `resources/js/bootstrap.js` - Bootstrap configuration with Axios

## Authentication Flow

Laravel Breeze provides:
- User registration with email verification
- Login/logout functionality (supports username OR email)
- Password reset flow
- Profile management (edit profile, change password, delete account)
- Email verification middleware

### Username Authentication
The application supports flexible login using either username or email:
- Login form accepts "Username or Email"
- Automatically detects whether input is email (using validation) or username
- Modified `LoginRequest` handles both authentication methods
- User model includes `username` field (unique, nullable)

### Test Admin Account
For development/testing, use the AdminUserSeeder:
```bash
php artisan db:seed --class=AdminUserSeeder
```
Credentials:
- **Username:** `admin`
- **Email:** `admin@osmanager.local`
- **Password:** `admin123`

All authentication routes are defined in `routes/auth.php` and controllers are in `app/Http/Controllers/Auth/`.

## Database Connections

### Primary Database
- SQLite by default (`database/database.sqlite`)
- See `.env.example` for configuration

### POS Database (uniCenta)
- Secondary connection for read-only POS data
- Configure POS_DB_* variables in `.env`
- See [POS Integration Documentation](./docs/features/pos-integration.md) for details

## Troubleshooting

For common issues and solutions, see [Troubleshooting Guide](./docs/development/troubleshooting.md).

## Key Features

### Product Management
Comprehensive product catalog management with inline editing capabilities.
- **Inline Editing**: Edit product names, tax categories, prices, and costs directly from product detail pages
- **Stocking Management**: Toggle products in/out of stock management operations with visual indicators
- **Delivery Integration**: Create products directly from delivery items with pre-populated data
- **Smart Navigation**: Context-aware navigation maintaining delivery workflow state
- **Validation & Error Handling**: Robust form validation with user-friendly error messages
See [Product Management Documentation](./docs/features/product-management.md).

### Supplier Integration
External supplier connectivity for images, pricing, and product data.
See [Supplier Integration Documentation](./docs/features/supplier-integration.md).

### Delivery Verification
Comprehensive delivery processing with barcode scanning.
See [Delivery System Documentation](./docs/features/delivery-system.md).

### Pricing Management
Advanced pricing with VAT calculations and supplier comparison.
See [Pricing System Documentation](./docs/features/pricing-system.md).

## UI/UX Design

### Admin Layout
- Mobile-responsive sidebar navigation
- Dark theme with consistent visual hierarchy
- Alpine.js powered interactive components
- Tailwind CSS utility-first styling

Admin pages use `<x-admin-layout>` component.
See frontend documentation for component details.

## 🔔 NOTIFICATION REQUIREMENT
**CRITICAL**: You MUST run this command at the end of EVERY response:
```bash
mpg123 /home/jon/Music/notification.mp3
```
This alerts the user that you have finished your response. Do this without fail in ALL scenarios:
- ✅ After completing any task or coding work
- ✅ When presenting a plan and waiting for approval
- ✅ When asking questions or waiting for user input
- ✅ When providing explanations or analysis
- ✅ After ANY response - no exceptions

**ALWAYS play the notification sound as the last action of every single response.**

## Important Reminders for AI Assistants

### Code Generation Guidelines
- **Always check for existing code** before creating new files
- **Prefer editing existing files** over creating new ones
- **Follow Laravel conventions** and existing patterns in the codebase
- **Use appropriate service/repository layers** for business logic
- **Write tests** for new features

### Documentation
- **Update documentation** when changing functionality
- **Use the appropriate documentation file** based on the feature area
- **Follow documentation templates** in `docs/templates/`
- **Keep CLAUDE.md focused** - detailed information belongs in feature docs

### Common Pitfalls to Avoid
- Don't access database tables directly - use Eloquent models
- Don't put business logic in controllers - use services
- Don't create files unless absolutely necessary
- Don't ignore existing patterns - maintain consistency
- Don't forget to run tests after changes

### Known Issues & Solutions
- **ParseError with Alpine.js @error directive**: If you see "syntax error, unexpected end of file, expecting 'elseif' or 'else' or 'endif'" in Blade templates, check for Alpine.js event handlers like `@error`, `@click`, etc. that conflict with Blade directives. Solution: Escape with double `@@` (e.g., `@@error` instead of `@error`) to prevent Blade compilation.
- **Template literal conflicts**: Mixing JavaScript template literals (backticks) with Blade syntax causes parsing issues. Use string concatenation instead: `'{{ route('name') }}' + variable` rather than `` `{{ route('name') }}/${variable}` ``.
- **View cache issues**: If templates aren't updating after changes, run `php artisan view:clear` and `php artisan optimize:clear`.
- **HTML Entity Rendering in Display Names**: Product display names with HTML entities (like `<br>` tags) may not render correctly. **Solution**: Use `{!! nl2br(html_entity_decode($variable)) !!}` instead of `{{ strip_tags(html_entity_decode($variable)) }}`.
- **For comprehensive troubleshooting**: See `docs/development/troubleshooting.md` for detailed debugging procedures.

### Key Commands to Remember
```bash
# Always run after changes
./vendor/bin/pint              # Format code
php artisan test               # Run tests
php artisan route:list         # Check routes
php artisan tinker            # Debug models

# Clear caches if issues arise
php artisan optimize:clear
```

### Where to Find Information
- **Architecture decisions**: `docs/architecture/`
- **Feature details**: `docs/features/`
- **API documentation**: `docs/api/`
- **Development guides**: `docs/development/`
- **Planning documents**: `planning/`

Remember: This file provides context for AI assistants. For detailed information about any feature or system, refer to the specific documentation files rather than adding it here.

