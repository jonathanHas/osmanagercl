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
- MySQL or MariaDB (application database), plus a MySQL 5.7 instance for uniCenta POS
- Git

> Setting up a machine from scratch? Follow the
> [Fresh Install Guide](./fresh-install-guide.md) instead — it covers the full three-database
> layout, the POS container, and loading real data.

### Required PHP Extensions

These extensions are needed for full functionality (XLS/XLSX parsing via PhpSpreadsheet, image handling, etc.):

```bash
# Adjust php version number to match your installation (check with: php -v)
sudo apt install php-gd php-zip php-xml php-mbstring
```

> **Note:** `dom`, `simplexml`, `xmlreader`, `xmlwriter` are bundled with `php-xml`. `iconv`, `ctype`, `fileinfo` are bundled with base PHP. Verify all required extensions are loaded with: `php -m | grep -iE 'gd|zip|xml|mbstring|dom|fileinfo'`

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

# Create the MySQL/MariaDB database and set DB_* in .env, then:
php artisan migrate

# Seed roles first — AdminUserSeeder needs the admin role to exist
php artisan db:seed --class=RolesAndPermissionsSeeder
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

The application uses **three databases across two engines**. `.env.example` still ships SQLite
defaults — do not rely on them.

#### Primary Database
- MySQL/MariaDB on port **3306** (`osmanagercl` in dev, `osmanager` in production)
- `config/database.php` sets the `default` connection fallback to `mysql`
- `database/database.sqlite` is an unused 0-byte placeholder

#### POS Database (uniCenta)
- `unicenta2016` on port **3307** — in dev, a pinned `mysql:5.7.33` Docker container
- Not read-only: F&V price sync writes `PRICESELL` back to the POS
- Configure `POS_DB_*` in `.env`
- See [POS Integration Documentation](../features/pos-integration.md) for details

#### OSAccounts Database
- `OSAccounts` on port **3307**, same container as the POS — legacy supplier invoices
- Configure `INV_DB_*` in `.env`

> ⚠️ Passing `-P 3307` to `mysql`/`mysqldump` without `-h 127.0.0.1` silently connects to the
> local socket (MariaDB) instead. See the
> [Fresh Install Guide](./fresh-install-guide.md#5-pos--osaccounts-database-container).

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

# Database (Primary) — MariaDB/MySQL on 3306
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=osmanagercl
DB_USERNAME=osmanager
DB_PASSWORD=

# POS Database (uniCenta) — MySQL 5.7 container on 3307
POS_DB_CONNECTION=mysql
POS_DB_HOST=127.0.0.1
POS_DB_PORT=3307
POS_DB_DATABASE=unicenta2016
POS_DB_USERNAME=unicenta_user
POS_DB_PASSWORD=

# OSAccounts Database — same container, 3307
INV_DB_HOST=127.0.0.1
INV_DB_PORT=3307
INV_DB_DATABASE=OSAccounts
INV_DB_USERNAME=osaccounts_user
INV_DB_PASSWORD=

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
  - Primary database (MySQL/MariaDB) for application data and configuration
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
