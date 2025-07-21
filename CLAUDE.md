# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

This is a Laravel 12 application using PHP 8.2+ with Laravel Breeze for authentication. The project uses:
- **Frontend**: Blade templates with Tailwind CSS and Alpine.js
- **Build System**: Vite for asset compilation
- **Database**: SQLite (default) with Eloquent ORM
- **Authentication**: Laravel Breeze with email verification
- **Testing**: PHPUnit with Feature and Unit test suites

## Documentation

For detailed documentation on specific areas of the codebase, see the `docs/` folder:

- **[POS Integration](./docs/pos-integration.md)** - uniCenta POS database integration, models, and relationships
- **[Documentation Index](./docs/README.md)** - Complete list of available documentation

## Planning and Next Steps

- **[next.md](./next.md)** - VAT rate integration planning and requirements gathering

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
  - Primary database (SQLite/MySQL) for application data
  - Secondary POS connection for uniCenta product data (read-only)
- **Product Management**: ProductRepository provides clean interface to POS products
- **Frontend**: Server-side rendered Blade templates with Tailwind CSS styling
- **Asset Pipeline**: Vite handles CSS and JavaScript compilation with hot reloading

### Development Best Practices
- **Always Use Eloquent Models**: Check for existing models before accessing database tables directly
  - Use `exists:App\Models\ModelName,column` in validation rules instead of `exists:table_name,column`
  - Models handle database connections, table names, and configurations automatically
  - Example: Use `exists:App\Models\Supplier,SupplierID` not `exists:SUPPLIERS,SupplierID`
- **Follow Laravel Conventions**: Leverage Eloquent relationships and model configurations
- **Database Access**: Prefer model-based queries over raw database calls for consistency

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
- Configuration in `config/database.php`

### POS Database (uniCenta)
A secondary `pos` connection provides read-only access to uniCenta POS data:
- Configuration in `config/database.php`
- Environment variables:
  - `POS_DB_HOST` - Database host (default: 127.0.0.1)
  - `POS_DB_PORT` - Database port (default: 3306)
  - `POS_DB_DATABASE` - Database name (default: unicenta)
  - `POS_DB_USERNAME` - Database username
  - `POS_DB_PASSWORD` - Database password

#### Product Model
The `Product` model (`app/Models/Product.php`) connects to the POS database:
- Uses `PRODUCTS` table from uniCenta
- Handles bit fields as booleans
- Provides scopes for common queries (active, inStock, search)
- No timestamps (uniCenta doesn't use Laravel timestamps)

#### ProductRepository
The `ProductRepository` (`app/Repositories/ProductRepository.php`) provides:
- `getAllProducts()` - Paginated product list
- `findById()` - Find product by ID
- `searchByName()` - Search by product name
- `searchByCode()` - Search by code or reference
- `getActiveProducts()` - Non-service products only
- `getByCategory()` - Products by category
- `getStatistics()` - Product counts and statistics
- `getLowStockProducts()` - Products low in stock

Usage example:
```php
$repository = new ProductRepository();
$products = $repository->searchProducts('coffee', activeOnly: true);
$stats = $repository->getStatistics();
```

## Troubleshooting

### Storage Permission Issues
If you encounter "Permission denied" errors for `storage/framework/views/`:

```bash
# Clear compiled views and caches
php artisan view:clear
php artisan config:clear
php artisan route:clear

# Fix storage permissions
chmod -R 775 storage/
chmod -R 775 bootstrap/cache/
```

This typically happens when Laravel runs under different users (web server vs CLI).

## Supplier External Integration

The application includes external supplier integration for product images and website links:

### Features
- **External Product Images**: Shows product images from supplier CDNs (currently Ekoplaza)
- **Supplier Website Links**: Direct links to search products on supplier websites
- **Product List Integration**: When "Show suppliers" is checked, shows thumbnails and links
- **Product Detail Integration**: Dedicated supplier information section with larger images
- **Delivery Integration**: Product images throughout delivery verification workflow with hover previews

### Configuration
- Config file: `config/suppliers.php`
- Service: `app/Services/SupplierService.php`
- Component: `resources/views/components/supplier-external-info.blade.php`
- Currently configured for Udea (supplier IDs: 5, 44, 85)

### Implementation Details
- **Live Price Comparison**: Real-time pricing data from Udea with automatic cost calculations
- **VAT-Inclusive Pricing**: Input selling prices including VAT with automatic net price calculation
- **High Precision Storage**: Prices stored with 4 decimal precision to preserve VAT calculations
- **Transport Cost Analysis**: 15% transport cost calculations for accurate margin analysis
- **Customer Price Extraction**: Retrieves retail prices from supplier product detail pages
- **Enhanced Barcode Extraction**: Multiple regex patterns support various HTML formats including table structures
- **Responsive Layout**: Mobile-optimized pricing interface with collapsible sections
- **Hover Previews**: Large image overlays on hover (192x192px) with product information
- **New Product Image Support**: Images work immediately for new products once barcodes are retrieved
- Images use lazy loading with `loading="lazy"` attribute
- Graceful error handling for missing images
- URL sanitization for security

### Barcode Extraction Patterns
The `UdeaScrapingService` supports multiple barcode extraction patterns for maximum compatibility:

1. **HTML Table Format with Class**: `<td class="wt-semi">EAN</td><td>8711521021925</td>`
2. **Simple Table Format**: `<td>EAN</td><td>8711521021925</td>`
3. **Colon Separated Format**: `EAN: 8711521021925`
4. **Fallback EAN-13 Pattern**: Validates 13-digit codes starting with '87'

### New Product Image Integration
For products without existing Product models (new deliveries):
- `SupplierService::getExternalImageUrlByBarcode()` generates image URLs using supplier ID + barcode
- Delivery views automatically show images once barcodes are retrieved
- Same hover preview functionality as existing products
- Barcode displayed in image overlay for identification

### Adding New Suppliers
To add a new supplier integration, update `config/suppliers.php`:
```php
'new_supplier' => [
    'supplier_ids' => [/* supplier IDs */],
    'image_url' => 'https://example.com/images/{CODE}.jpg',
    'website_search' => 'https://example.com/search?q={SUPPLIER_CODE}',
    'display_name' => 'Supplier Name',
    'enabled' => true,
],
```

## Admin UI Layout

The application now includes a dedicated admin layout with sidebar navigation for better administrative functionality:

### Admin Layout (`resources/views/layouts/admin.blade.php`)
- **Sidebar Navigation**: Fixed sidebar on desktop, mobile-responsive slide-out menu
- **Dark Theme**: Gray-900 sidebar with better visual hierarchy
- **Icons**: SVG icons for all navigation items (Dashboard, Products, Users, Settings)
- **User Profile**: Bottom section shows user avatar, name, and email with dropdown menu
- **Alpine.js Integration**: Sidebar toggle functionality using Alpine.js `x-data`

### Layout Usage
Admin pages use the `<x-admin-layout>` component instead of `<x-app-layout>`:
```blade
<x-admin-layout>
    <x-slot name="header">
        <h2>Page Title</h2>
    </x-slot>
    
    <!-- Page content -->
</x-admin-layout>
```

### Enhanced Dashboard Design
The dashboard (`resources/views/dashboard.blade.php`) features:
- **Welcome Section**: Personalized greeting with user's name
- **Metric Cards**: 4-column grid with icons and contextual information
  - Total Products (with link to view all)
  - Active Products (with percentage of total)
  - In Stock (with out of stock count)
  - Service Products (non-physical items)
- **Quick Actions Grid**: 2x2 grid of action cards for common tasks
  - View Products
  - Active Items
  - Reports (placeholder)
  - Settings (placeholder)

### Tailwind Configuration
Extended Tailwind config (`tailwind.config.js`) with:
- **Admin Color Palette**: Purple-based color scheme (`admin-50` through `admin-900`)
- **Animations**: `slide-in` and `fade-in` animations for smooth transitions
- **Custom Shadows**: `admin` and `admin-lg` for consistent elevation

### Navigation Structure
The admin sidebar includes:
- Dashboard (home icon)
- Products (package icon)
- Deliveries (delivery box icon) - Complete delivery verification system
- Users (users icon) - placeholder for future implementation
- Settings (cog icon) - placeholder for future implementation

Each navigation item shows active state with gray-800 background when on that route.

## Delivery Verification System

The application includes a comprehensive delivery verification system for processing supplier deliveries:

### Features
- **CSV Import**: Upload delivery dockets from suppliers (Udea format supported)
- **Real-time Scanning**: Mobile-optimized barcode scanning interface with Alpine.js
- **Progress Tracking**: Live delivery completion monitoring with visual indicators
- **Discrepancy Management**: Automatic detection and reporting of quantity differences
- **Stock Updates**: Final stock level updates upon delivery completion
- **Visual Product Identification**: Supplier images with hover previews throughout workflow
- **Export Capability**: JSON export of discrepancies for supplier reconciliation

### Workflow
1. **Import**: Upload CSV delivery file → automatic product matching → barcode retrieval for new products
2. **Scan**: Real-time barcode verification → quantity tracking → status updates → manual adjustments
3. **Review**: Discrepancy analysis → export reports → complete delivery and update stock

### Access
- **Sidebar Navigation**: Click "Deliveries" in admin sidebar
- **Main Features**: Import deliveries, scan products, track progress, manage discrepancies
- **Documentation**: See `docs/delivery-verification-system.md` for complete technical guide

### Database Tables
- `deliveries` - Main delivery tracking with supplier and status information
- `delivery_items` - Individual product items with quantities and matching status  
- `delivery_scans` - Complete scan history with matched/unmatched tracking

### Dependencies
- `league/csv` package for CSV processing (auto-installed)
- Existing SupplierService for product images and integration
- UdeaScrapingService for automatic barcode retrieval
- SupplierLink model for product-supplier code matching

## Product Pricing System

The application features an advanced pricing management system with supplier integration and precise VAT calculations.

### Key Features
- **Consolidated Pricing Interface**: All pricing information grouped in a single, organized section
- **VAT-Inclusive Input**: Enter selling prices including VAT for easier price management
- **Live Supplier Comparison**: Real-time price comparison with Udea supplier pricing
- **High Precision Calculations**: 4-decimal precision storage to preserve VAT calculations exactly
- **Quick Action Buttons**: One-click price updates based on supplier data or margin strategies

### Pricing Components
- **Product Pricing Section** (`resources/views/components/product-pricing-section.blade.php`): Main pricing interface
- **Supplier Info Card** (`resources/views/components/supplier-info-card.blade.php`): Clean supplier information display
- **UdeaScrapingService** (`app/Services/UdeaScrapingService.php`): Live price data retrieval

### VAT Calculation Precision
The system uses 4-decimal precision for net prices to ensure VAT-inclusive prices are preserved exactly:
- **Input**: €7.30 (23% VAT inclusive)
- **Stored**: €5.9350 (net price with 4 decimals)
- **Display**: €7.30 (calculated from precise net price)

### Price Update Methods
1. **Manual Cost Update**: Direct cost price entry
2. **VAT-Inclusive Selling Price**: Enter final selling price including VAT
3. **Supplier-Based Updates**: Match Udea cost, customer price, or calculated optimal pricing
4. **Quick Actions**: Competitive pricing (+10%), optimal margin (35%), customer price matching

### Configuration
- **Model Precision**: `PRICESELL` field uses `decimal:4` casting for high precision
- **Validation**: Accepts up to 4 decimal places in price inputs
- **Display**: Shows 2 decimal places for user interface while maintaining precision internally