# Quick Start Guide

This guide provides all the commands and procedures you need to get started with OSManager CL development.

**Quick Navigation:**
- [Initial Setup](#initial-setup)
- [Development Commands](#development-commands)
- [Database Operations](#database-operations)
- [Frontend Development](#frontend-development)
- [Authentication](#authentication)
- [Configuration](#configuration)

---

## Initial Setup

### Prerequisites
- PHP 8.2+
- Composer
- Node.js and npm
- SQLite (or MySQL/PostgreSQL)
- Git

### First Time Setup

```bash
# Clone the repository (if needed)
git clone <repository-url>
cd osmanagercl

# Install PHP dependencies
composer install

# Install Node.js dependencies
npm install

# Copy environment file
cp .env.example .env

# Generate application key
php artisan key:generate

# Create database file (SQLite)
touch database/database.sqlite

# Run migrations
php artisan migrate

# Seed database with test admin account (optional)
php artisan db:seed --class=AdminUserSeeder

# Start development server
composer run dev
```

---

## Development Commands

### Starting Development

#### All-in-One Development Server
```bash
# Start everything (server, queue, logs, and Vite)
composer run dev
```

This single command starts:
- PHP development server (`php artisan serve`)
- Queue worker (`php artisan queue:listen`)
- Log viewer (`php artisan pail`)
- Vite development server (`npm run dev`)

#### Individual Services

If you need to run services separately:

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

# Format specific files
./vendor/bin/pint app/Http/Controllers/

# Check without fixing
./vendor/bin/pint --test
```

### Cache Management

```bash
# Clear all caches at once
php artisan optimize:clear

# Clear individual caches
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear
```

---

## Database Operations

### Common Database Commands

```bash
# Create new migration
php artisan make:migration create_example_table

# Run migrations
php artisan migrate

# Rollback migrations
php artisan migrate:rollback

# Rollback and re-run all migrations
php artisan migrate:fresh

# Rollback and re-run with seeding
php artisan migrate:fresh --seed

# Seed database
php artisan db:seed

# Seed specific seeder
php artisan db:seed --class=AdminUserSeeder

# Access database directly
php artisan tinker
```

### Database Configuration

The application uses **SQLite by default**. The database file is located at `database/database.sqlite`.

#### Primary Database
- SQLite by default (`database/database.sqlite`)
- Can be configured for MySQL/PostgreSQL in `.env`
- See `.env.example` for configuration

#### POS Database (uniCenta)
- Secondary connection for read-only POS data
- Configure POS_DB_* variables in `.env`
- See [POS Integration Documentation](../features/pos-integration.md) for details

---

## Frontend Development

### Technology Stack
- **Templating**: Blade templates
- **CSS Framework**: Tailwind CSS with forms plugin
- **JavaScript**: Alpine.js for interactive components
- **Build Tool**: Vite for asset building and hot reloading
- **Components**: Reusable Blade components

### Asset Files
- `resources/css/app.css` - Main CSS file
- `resources/js/app.js` - Main JavaScript file with Alpine.js
- `resources/js/bootstrap.js` - Bootstrap configuration with Axios

### Development Workflow

```bash
# Start Vite development server with hot reloading
npm run dev

# Build assets for production
npm run build

# Watch for changes (alternative to dev server)
npm run watch
```

### Admin Layout

Admin pages use `<x-admin-layout>` component which provides:
- Mobile-responsive sidebar navigation
- Dark theme with consistent visual hierarchy
- Alpine.js powered interactive components
- Tailwind CSS utility-first styling

See frontend documentation for component details.

---

## Authentication

### Authentication Flow

Laravel Breeze provides:
- User registration with email verification
- Login/logout functionality (supports username OR email)
- Password reset flow
- Profile management (edit profile, change password, delete account)
- Email verification middleware

All authentication routes are defined in `routes/auth.php` and controllers are in `app/Http/Controllers/Auth/`.

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

**Credentials:**
- **Username:** `admin`
- **Email:** `admin@osmanager.local`
- **Password:** `admin123`

⚠️ **Important**: Change these credentials in production!

---

## Configuration

### Environment Configuration

Key environment variables to configure:

```bash
# Application
APP_NAME="OSManager CL"
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost

# Database (Primary)
DB_CONNECTION=sqlite
DB_DATABASE=/absolute/path/to/database.sqlite

# POS Database (uniCenta)
POS_DB_CONNECTION=mysql
POS_DB_HOST=127.0.0.1
POS_DB_PORT=3307
POS_DB_DATABASE=unicentaopos
POS_DB_USERNAME=root
POS_DB_PASSWORD=

# Mail (for development)
MAIL_MAILER=log
```

### Configuration Files

- **Database**: `.env` configuration
- **Vite**: `vite.config.js` handles asset compilation
- **Tailwind CSS**: `tailwind.config.js` with forms plugin
- **PHPUnit**: `phpunit.xml` with SQLite in-memory testing database

---

## Architecture Overview

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
- `app/Services/` - Service layer for business logic
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

---

## Debugging and Troubleshooting

### Common Issues

For comprehensive troubleshooting, see:
- [Known Issues](./known-issues.md) - Previously resolved issues and solutions
- [Troubleshooting Guide](./troubleshooting.md) - Detailed debugging procedures

### Quick Debug Commands

```bash
# Check routes
php artisan route:list

# Debug models in tinker
php artisan tinker

# Check queue jobs
php artisan queue:failed

# View logs
php artisan pail

# Clear all caches
php artisan optimize:clear
```

---

## Next Steps

After setup, explore:
- **[Features Index](../FEATURES_INDEX.md)** - Complete list of features
- **[AI Assistant Guide](./ai-assistant-guide.md)** - Guidelines for AI assistants
- **[Contributing Guidelines](../../CONTRIBUTING.md)** - Development standards
- **[Performance Guide](./performance-optimization-guide.md)** - Optimization strategies

---

**Need Help?**
- Check the [Documentation Index](../README.md) for complete documentation
- Review [Troubleshooting Guide](./troubleshooting.md) for common issues
- See [Contributing Guidelines](../../CONTRIBUTING.md) for development standards
