# Fruit & Vegetables Development Setup

## Overview

This guide covers the development setup and configuration required for the Fruit & Vegetables management system. The F&V system uses a dual database architecture and requires specific migrations and configurations.

## Database Setup

### Required Migrations

The F&V system requires three Laravel database migrations:

#### 1. Availability Tracking
```bash
php artisan make:migration create_veg_availability_table
```

Migration content:
```php
Schema::create('veg_availability', function (Blueprint $table) {
    $table->id();
    $table->string('product_code')->unique();
    $table->boolean('is_available')->default(false);
    $table->decimal('current_price', 10, 4)->nullable();
    $table->timestamps();
    
    // Indexes for performance
    $table->index(['is_available', 'product_code']);
    $table->index('current_price');
});
```

#### 2. Price History Audit Trail
```bash
php artisan make:migration create_veg_price_history_table
```

Migration content:
```php
Schema::create('veg_price_history', function (Blueprint $table) {
    $table->id();
    $table->string('product_code');
    $table->decimal('old_price', 10, 4);
    $table->decimal('new_price', 10, 4);
    $table->unsignedBigInteger('changed_by');
    $table->timestamp('changed_at');
    $table->timestamps();
    
    // Foreign key constraint
    $table->foreign('changed_by')->references('id')->on('users');
    
    // Indexes for queries
    $table->index(['product_code', 'changed_at']);
    $table->index('changed_by');
});
```

#### 3. Print Queue Management
```bash
php artisan make:migration create_veg_print_queue_table
```

Migration content:
```php
Schema::create('veg_print_queue', function (Blueprint $table) {
    $table->id();
    $table->string('product_code')->unique();
    $table->string('reason')->nullable(); // price_change, marked_available, etc.
    $table->timestamp('added_at');
    $table->timestamps();
    
    // Index for queue retrieval
    $table->index(['added_at', 'product_code']);
});
```

### Running Migrations
```bash
# Run all pending migrations
php artisan migrate

# Check migration status
php artisan migrate:status

# Rollback if needed
php artisan migrate:rollback --step=3
```

## Model Setup

### Required Models

#### 1. VegPrintQueue Model
```bash
php artisan make:model VegPrintQueue
```

Model should include static helper methods for queue management.

#### 2. VegDetails Model (POS Database)
```bash
php artisan make:model VegDetails
```

This model connects to the POS database and should include:
- Country relationship
- Unit and class name accessors
- Proper connection configuration

#### 3. Country Model (POS Database)
```bash
php artisan make:model Country
```

For country of origin management.

### Model Configurations

#### Product Model Enhancements
Add these relationships and methods to the existing Product model:

```php
// Relationship to VegDetails
public function vegDetails()
{
    return $this->hasOne(VegDetails::class, 'product', 'CODE');
}

// Image handling
public function getImageThumbnailAttribute(): ?string
{
    if (!$this->IMAGE) return null;
    return 'data:image/jpeg;base64,'.base64_encode($this->IMAGE);
}

public function hasImage(): bool
{
    return !empty($this->IMAGE);
}
```

## Route Setup

Add F&V routes to `routes/web.php`:

```php
// Fruit & Veg routes
Route::prefix('fruit-veg')->name('fruit-veg.')->middleware('auth')->group(function () {
    Route::get('/', [FruitVegController::class, 'index'])->name('index');
    Route::get('/availability', [FruitVegController::class, 'availability'])->name('availability');
    Route::post('/availability/toggle', [FruitVegController::class, 'toggleAvailability'])->name('availability.toggle');
    Route::post('/availability/bulk', [FruitVegController::class, 'bulkAvailability'])->name('availability.bulk');
    Route::get('/prices', [FruitVegController::class, 'prices'])->name('prices');
    Route::post('/prices/update', [FruitVegController::class, 'updatePrice'])->name('prices.update');
    Route::get('/labels', [FruitVegController::class, 'labels'])->name('labels');
    Route::get('/labels/preview', [FruitVegController::class, 'previewLabels'])->name('labels.preview');
    Route::post('/labels/printed', [FruitVegController::class, 'markLabelsPrinted'])->name('labels.printed');
    Route::post('/display/update', [FruitVegController::class, 'updateDisplay'])->name('display.update');
    Route::post('/country/update', [FruitVegController::class, 'updateCountry'])->name('country.update');
    Route::get('/countries', [FruitVegController::class, 'getCountries'])->name('countries');
    Route::get('/search', [FruitVegController::class, 'searchProducts'])->name('search');
    Route::get('/product-image/{code}', [FruitVegController::class, 'productImage'])->name('product-image');
});
```

## Controller Setup

### Generate Controller
```bash
php artisan make:controller FruitVegController
```

### Required Methods
The controller should include these essential methods:
- `index()` - Dashboard with statistics
- `availability()` - Availability management with pagination
- `searchProducts()` - AJAX search endpoint
- `toggleAvailability()` - Single product toggle
- `bulkAvailability()` - Bulk operations
- `updatePrice()` - Price management
- `updateDisplay()` - Display name editing
- `updateCountry()` - Country of origin
- `productImage()` - Image serving

## View Setup

### Directory Structure
Create the view directory:
```bash
mkdir -p resources/views/fruit-veg
```

### Required Views
1. `fruit-veg/index.blade.php` - Dashboard
2. `fruit-veg/availability.blade.php` - Availability management
3. `fruit-veg/prices.blade.php` - Price management
4. `fruit-veg/labels.blade.php` - Label management
5. `fruit-veg/label-preview.blade.php` - Label preview

### Alpine.js Components
The availability view requires Alpine.js for reactive functionality:
- Search with debouncing
- AJAX pagination
- Bulk selection
- Inline editing

## Database Configuration

### Environment Variables
Ensure your `.env` file has both database connections configured:

```env
# Primary database (Laravel)
DB_CONNECTION=sqlite
DB_DATABASE=/path/to/database/database.sqlite

# POS database connection
POS_DB_CONNECTION=mysql
POS_DB_HOST=127.0.0.1
POS_DB_PORT=3306
POS_DB_DATABASE=unicenta_db
POS_DB_USERNAME=pos_user
POS_DB_PASSWORD=pos_password
```

### Database Connections
Update `config/database.php` to include POS connection:

```php
'connections' => [
    // ... existing connections ...
    
    'pos' => [
        'driver' => 'mysql',
        'host' => env('POS_DB_HOST', '127.0.0.1'),
        'port' => env('POS_DB_PORT', '3306'),
        'database' => env('POS_DB_DATABASE'),
        'username' => env('POS_DB_USERNAME'),
        'password' => env('POS_DB_PASSWORD'),
        'charset' => 'utf8mb4',
        'collation' => 'utf8mb4_unicode_ci',
        'prefix' => '',
        'strict' => true,
        'engine' => null,
    ],
],
```

## Development Commands

### Initial Setup
```bash
# Install dependencies
composer install
npm install

# Generate application key
php artisan key:generate

# Run migrations
php artisan migrate

# Build assets
npm run dev
```

### Testing F&V Categories
Verify F&V categories exist in POS database:
```bash
php artisan tinker
```

```php
// Check categories
$categories = \App\Models\Category::whereIn('ID', ['SUB1', 'SUB2', 'SUB3'])->get();
dd($categories->pluck('NAME', 'ID'));

// Check F&V products count
$fruitCount = \App\Models\Product::where('CATEGORY', 'SUB1')->count();
$vegCount = \App\Models\Product::whereIn('CATEGORY', ['SUB2', 'SUB3'])->count();
echo "Fruits: {$fruitCount}, Vegetables: {$vegCount}";
```

### Seeding Test Data
Create a seeder for test availability data:

```bash
php artisan make:seeder FruitVegSeeder
```

```php
public function run()
{
    // Sample availability data
    $products = \App\Models\Product::whereIn('CATEGORY', ['SUB1', 'SUB2', 'SUB3'])
        ->limit(20)
        ->get();
        
    foreach ($products as $product) {
        \DB::table('veg_availability')->insertOrIgnore([
            'product_code' => $product->CODE,
            'is_available' => fake()->boolean(30), // 30% chance of being available
            'current_price' => $product->getGrossPrice(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
```

Run seeder:
```bash
php artisan db:seed --class=FruitVegSeeder
```

## Asset Configuration

### Vite Configuration
Ensure `vite.config.js` includes proper asset handling:

```javascript
import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
        }),
    ],
});
```

### Alpine.js Setup
In `resources/js/app.js`:
```javascript
import Alpine from 'alpinejs';

window.Alpine = Alpine;

Alpine.start();
```

## Performance Optimization

### Database Indexes
The migrations include performance indexes, but verify they're created:

```sql
-- Check indexes
SHOW INDEX FROM veg_availability;
SHOW INDEX FROM veg_price_history;
SHOW INDEX FROM veg_print_queue;
```

### Query Optimization
Use Laravel's query optimization techniques:
- Eager loading relationships
- Select only needed columns
- Use pagination for large datasets
- Cache frequently accessed data

### Image Optimization
For better image serving performance:
```php
// In controller
return response($product->IMAGE, 200, [
    'Content-Type' => 'image/jpeg',
    'Cache-Control' => 'public, max-age=86400', // 24 hours
    'ETag' => md5($product->CODE),
]);
```

## Testing Setup

### Feature Tests
Create tests for F&V functionality:

```bash
php artisan make:test FruitVegTest
```

Essential test cases:
```php
public function test_availability_page_loads()
public function test_product_search_works()
public function test_availability_toggle()
public function test_bulk_availability_update()
public function test_price_update()
public function test_display_name_update()
public function test_country_update()
```

### Test Database
Configure test database in `phpunit.xml`:
```xml
<env name="DB_CONNECTION" value="sqlite"/>
<env name="DB_DATABASE" value=":memory:"/>
```

## Debugging Tools

### Debug Routes
Add debug routes for development (remove in production):

```php
Route::get('/debug/fv-categories', function () {
    $categories = \App\Models\Category::whereIn('ID', ['SUB1', 'SUB2', 'SUB3'])->get();
    return $categories;
});

Route::get('/debug/fv-products/{limit?}', function ($limit = 10) {
    return \App\Models\Product::whereIn('CATEGORY', ['SUB1', 'SUB2', 'SUB3'])
        ->with(['category', 'vegDetails.country'])
        ->limit($limit)
        ->get();
});
```

### Logging
Add logging for debugging:
```php
// In controller methods
\Log::info('F&V availability toggle', [
    'product_code' => $request->product_code,
    'is_available' => $request->is_available,
    'user' => auth()->id()
]);
```

## Common Issues & Solutions

### Category IDs Not Found
**Problem**: Categories SUB1, SUB2, SUB3 don't exist in POS database
**Solution**: Verify category IDs in POS system or update controller logic

### Image Loading Issues
**Problem**: Product images not displaying
**Solution**: Check IMAGE column exists and has data, verify route permissions

### Dual Database Connection Issues
**Problem**: Cannot connect to POS database
**Solution**: Verify POS_DB_* environment variables and database accessibility

### Performance Issues
**Problem**: Page loads slowly with many products
**Solution**: Implement pagination, add database indexes, use eager loading

### AJAX Requests Failing
**Problem**: Search and toggle requests return errors
**Solution**: Check CSRF tokens, verify routes are registered, check network tab

## Production Deployment Notes

### Environment Differences
- Set proper database credentials
- Configure caching (Redis recommended)
- Enable queue workers for background jobs
- Set up log rotation
- Configure proper web server settings

### Security Checklist
- Remove debug routes
- Set `APP_DEBUG=false`
- Configure proper CORS settings
- Set up HTTPS
- Implement rate limiting
- Review file permissions

### Monitoring
Set up monitoring for:
- Database performance
- Queue job failures
- Error rates
- Response times
- Disk space (for images)