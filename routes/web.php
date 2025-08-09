<?php

use App\Http\Controllers\CategoriesController;
use App\Http\Controllers\CoffeeController;
use App\Http\Controllers\DeliveryController;
use App\Http\Controllers\FruitVegController;
use App\Http\Controllers\LabelAreaController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SalesImportController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\TestScraperController;
use App\Http\Controllers\UserManagementController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/dashboard', function () {
    $productRepository = new \App\Repositories\ProductRepository;
    $statistics = $productRepository->getStatistics();

    return view('dashboard', compact('statistics'));
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // Role & Permission Test Routes
    Route::prefix('roles-test')->name('roles.')->group(function () {
        Route::get('/', [\App\Http\Controllers\RoleTestController::class, 'index'])->name('test');
        Route::get('/admin-only', [\App\Http\Controllers\RoleTestController::class, 'adminOnly'])
            ->middleware('role:admin')
            ->name('admin-only');
        Route::get('/manager-only', [\App\Http\Controllers\RoleTestController::class, 'managerOnly'])
            ->middleware('role:manager,admin')
            ->name('manager-only');
        Route::get('/sales-reports', [\App\Http\Controllers\RoleTestController::class, 'salesReports'])
            ->middleware('permission:sales.view_reports')
            ->name('sales-reports');
    });

    // Product routes
    Route::get('/products', [ProductController::class, 'index'])->name('products.index');
    Route::get('/products/create', [ProductController::class, 'create'])->name('products.create');
    Route::get('/products/independent-test', [\App\Http\Controllers\IndependentTestController::class, 'index'])->name('products.independent-test');
    Route::post('/products', [ProductController::class, 'store'])->name('products.store');
    Route::get('/products/suppliers', [ProductController::class, 'suppliersIndex'])->name('products.suppliers');
    Route::get('/products/{id}', [ProductController::class, 'show'])->name('products.show');
    Route::get('/products/{id}/sales-data', [ProductController::class, 'salesData'])->name('products.sales-data');
    Route::get('/products/{id}/refresh-udea-pricing', [ProductController::class, 'refreshUdeaPricing'])->name('products.refresh-udea-pricing');
    Route::get('/products/udea-pricing', [ProductController::class, 'getUdeaPricing'])->name('products.udea-pricing');
    Route::patch('/products/{id}/name', [ProductController::class, 'updateName'])->name('products.update-name');
    Route::patch('/products/{id}/tax', [ProductController::class, 'updateTax'])->name('products.update-tax');
    Route::patch('/products/{id}/category', [ProductController::class, 'updateCategory'])->name('products.update-category');
    Route::patch('/products/{id}/price', [ProductController::class, 'updatePrice'])->name('products.update-price');
    Route::patch('/products/{id}/cost', [ProductController::class, 'updateCost'])->name('products.update-cost');
    Route::patch('/products/{id}/barcode', [ProductController::class, 'updateBarcode'])->name('products.update-barcode');
    Route::patch('/products/{id}/display', [ProductController::class, 'updateDisplay'])->name('products.update-display');
    Route::post('/products/{id}/toggle-stocking', [ProductController::class, 'toggleStocking'])->name('products.toggle-stocking');
    Route::post('/products/{id}/toggle-till-visibility', [ProductController::class, 'toggleTillVisibility'])->name('products.toggle-till-visibility');
    Route::get('/products/{id}/print-label', [ProductController::class, 'printLabel'])->name('products.print-label');

    // Label area routes
    Route::get('/labels', [LabelAreaController::class, 'index'])->name('labels.index');
    Route::post('/labels/print-a4', [LabelAreaController::class, 'printA4'])->name('labels.print-a4');
    Route::get('/labels/preview-a4', [LabelAreaController::class, 'previewA4'])->name('labels.preview-a4');
    Route::get('/labels/preview/{productId}', [LabelAreaController::class, 'previewLabel'])->name('labels.preview');

    // Requeue product route
    Route::post('/labels/requeue', [LabelAreaController::class, 'requeueProduct'])->name('labels.requeue');

    // Clear all labels route
    Route::post('/labels/clear-all', [LabelAreaController::class, 'clearAllLabels'])->name('labels.clear-all');

    // Restore batch of labels route
    Route::post('/labels/restore-batch', [LabelAreaController::class, 'restoreBatch'])->name('labels.restore-batch');

    // Fruit & Veg routes
    Route::prefix('fruit-veg')->name('fruit-veg.')->group(function () {
        Route::get('/', [FruitVegController::class, 'index'])->name('index');
        Route::get('/availability', [FruitVegController::class, 'availability'])->name('availability');
        Route::post('/availability/toggle', [FruitVegController::class, 'toggleAvailability'])->name('availability.toggle');
        Route::post('/availability/bulk', [FruitVegController::class, 'bulkAvailability'])->name('availability.bulk');
        Route::get('/prices', [FruitVegController::class, 'prices'])->name('prices');
        Route::post('/prices/update', [FruitVegController::class, 'updatePrice'])->name('prices.update');
        Route::get('/manage', [FruitVegController::class, 'manage'])->name('manage');
        Route::get('/labels', [FruitVegController::class, 'labels'])->name('labels');
        Route::get('/labels/preview', [FruitVegController::class, 'previewLabels'])->name('labels.preview');
        Route::post('/labels/printed', [FruitVegController::class, 'markLabelsPrinted'])->name('labels.printed');
        Route::post('/labels/clear-all', [FruitVegController::class, 'clearAllLabels'])->name('labels.clear-all');
        Route::post('/labels/remove', [FruitVegController::class, 'removeFromLabels'])->name('labels.remove');
        Route::post('/labels/add', [FruitVegController::class, 'addToLabels'])->name('labels.add');
        Route::post('/display/update', [FruitVegController::class, 'updateDisplay'])->name('display.update');
        Route::post('/country/update', [FruitVegController::class, 'updateCountry'])->name('country.update');
        Route::post('/unit/update', [FruitVegController::class, 'updateUnit'])->name('unit.update');
        Route::post('/class/update', [FruitVegController::class, 'updateClass'])->name('class.update');
        Route::get('/countries', [FruitVegController::class, 'getCountries'])->name('countries');
        Route::get('/units', [FruitVegController::class, 'getUnits'])->name('units');
        Route::get('/classes', [FruitVegController::class, 'getClasses'])->name('classes');
        Route::get('/search', [FruitVegController::class, 'searchProducts'])->name('search');
        Route::get('/quick-search', [FruitVegController::class, 'quickSearch'])->name('quick-search');
        Route::get('/product/{code}', [FruitVegController::class, 'editProduct'])->name('product.edit');
        Route::get('/product/{code}/sales-data', [FruitVegController::class, 'salesData'])->name('product.sales-data');
        Route::post('/product/{code}/update-image', [FruitVegController::class, 'updateProductImage'])->name('product.update-image');
        Route::get('/product-image/{code}', [FruitVegController::class, 'productImage'])->name('product-image');
        Route::get('/sales', [FruitVegController::class, 'sales'])->name('sales');
        Route::get('/sales/data', [FruitVegController::class, 'getSalesData'])->name('sales.data');
        Route::get('/sales/product/{code}/daily', [FruitVegController::class, 'getProductDailySales'])->name('sales.product.daily');
    });

    // Coffee routes
    Route::prefix('coffee')->name('coffee.')->group(function () {
        Route::get('/', [CoffeeController::class, 'index'])->name('index');
        Route::get('/products', [CoffeeController::class, 'products'])->name('products');
        Route::post('/visibility/toggle', [CoffeeController::class, 'toggleVisibility'])->name('visibility.toggle');
        Route::get('/sales', [CoffeeController::class, 'sales'])->name('sales');
        Route::get('/sales/data', [CoffeeController::class, 'getSalesData'])->name('sales.data');
        Route::get('/sales/product/{code}/daily', [CoffeeController::class, 'getProductDailySales'])->name('sales.product.daily');
        Route::get('/product-image/{code}', [CoffeeController::class, 'productImage'])->name('product-image');
    });

    // Categories management routes
    Route::prefix('categories')->name('categories.')->group(function () {
        Route::get('/', [CategoriesController::class, 'index'])->name('index');
        Route::get('/{category}', [CategoriesController::class, 'show'])->name('show');
        Route::get('/{category}/products', [CategoriesController::class, 'products'])->name('products');
        Route::get('/{category}/sales', [CategoriesController::class, 'sales'])->name('sales');
        Route::get('/{category}/sales/data', [CategoriesController::class, 'getSalesData'])->name('sales.data');
        Route::get('/{category}/sales/product/{code}/daily', [CategoriesController::class, 'getProductDailySales'])->name('sales.product.daily');
        Route::get('/{category}/dashboard-data', [CategoriesController::class, 'getDashboardData'])->name('dashboard.data');
        Route::post('/visibility/toggle', [CategoriesController::class, 'toggleVisibility'])->name('visibility.toggle');
        Route::get('/product-image/{code}', [CategoriesController::class, 'productImage'])->name('product-image');
    });

    // Delivery management routes
    Route::resource('deliveries', DeliveryController::class);
    Route::get('/deliveries/{delivery}/scan', [DeliveryController::class, 'scan'])->name('deliveries.scan');
    Route::post('/deliveries/{delivery}/scan', [DeliveryController::class, 'processScan'])->name('deliveries.process-scan');
    Route::patch('/deliveries/{delivery}/items/{item}/quantity', [DeliveryController::class, 'adjustQuantity'])->name('deliveries.adjust-quantity');
    Route::patch('/deliveries/{delivery}/items/{item}/price', [DeliveryController::class, 'updateItemPrice'])->name('deliveries.update-item-price');
    Route::get('/deliveries/{delivery}/stats', [DeliveryController::class, 'getStats'])->name('deliveries.stats');
    Route::get('/deliveries/{delivery}/summary', [DeliveryController::class, 'summary'])->name('deliveries.summary');
    Route::post('/deliveries/{delivery}/complete', [DeliveryController::class, 'complete'])->name('deliveries.complete');
    Route::post('/deliveries/{delivery}/cancel', [DeliveryController::class, 'cancel'])->name('deliveries.cancel');
    Route::get('/deliveries/{delivery}/export-discrepancies', [DeliveryController::class, 'exportDiscrepancies'])->name('deliveries.export-discrepancies');
    Route::post('/deliveries/{delivery}/update-costs', [DeliveryController::class, 'updateCosts'])->name('deliveries.update-costs');
    Route::post('/delivery-items/{item}/refresh-barcode', [DeliveryController::class, 'refreshBarcode'])->name('delivery-items.refresh-barcode');

    // Order Management routes
    Route::resource('orders', OrderController::class);
    Route::post('/orders/{order}/complete', [OrderController::class, 'complete'])->name('orders.complete');
    Route::post('/orders/{order}/duplicate', [OrderController::class, 'duplicate'])->name('orders.duplicate');
    Route::get('/orders/{order}/export', [OrderController::class, 'export'])->name('orders.export');
    Route::get('/orders/{order}/statistics', [OrderController::class, 'statistics'])->name('orders.statistics');
    Route::patch('/order-items/{orderItem}/quantity', [OrderController::class, 'updateQuantity'])->name('order-items.update-quantity');
    Route::patch('/order-items/{orderItem}/cases', [OrderController::class, 'updateCaseQuantity'])->name('order-items.update-cases');
    Route::patch('/order-items/{orderItem}/cost', [OrderController::class, 'updateItemCost'])->name('order-items.update-cost');
    Route::post('/orders/{order}/bulk-update', [OrderController::class, 'bulkUpdate'])->name('orders.bulk-update');
    Route::post('/orders/{order}/auto-approve-safe', [OrderController::class, 'autoApproveSafeItems'])->name('orders.auto-approve-safe');
    Route::post('/products/update-priority', [OrderController::class, 'updateProductPriority'])->name('products.update-priority');

    // User Management routes (protected by permissions)
    Route::prefix('users')->name('users.')->group(function () {
        Route::get('/', [UserManagementController::class, 'index'])
            ->middleware('permission:users.view')
            ->name('index');
        Route::get('/create', [UserManagementController::class, 'create'])
            ->middleware('permission:users.create')
            ->name('create');
        Route::post('/', [UserManagementController::class, 'store'])
            ->middleware('permission:users.create')
            ->name('store');
        Route::get('/{user}', [UserManagementController::class, 'show'])
            ->middleware('permission:users.view')
            ->name('show');
        Route::get('/{user}/edit', [UserManagementController::class, 'edit'])
            ->middleware('permission:users.edit')
            ->name('edit');
        Route::patch('/{user}', [UserManagementController::class, 'update'])
            ->middleware('permission:users.edit')
            ->name('update');
        Route::delete('/{user}', [UserManagementController::class, 'destroy'])
            ->middleware('permission:users.delete')
            ->name('destroy');
    });

    // Settings routes
    Route::get('/settings', [SettingsController::class, 'index'])->name('settings.index');
    Route::post('/settings/clear-cache', [SettingsController::class, 'clearCache'])->name('settings.clear-cache');
    Route::get('/settings/system-info', [SettingsController::class, 'systemInfo'])->name('settings.system-info');

    // Sales Import routes
    Route::prefix('sales-import')->name('sales-import.')->group(function () {
        Route::get('/', [SalesImportController::class, 'index'])->name('index');
        Route::post('/daily', [SalesImportController::class, 'runDailyImport'])->name('run-daily');
        Route::post('/monthly', [SalesImportController::class, 'runMonthlySummaries'])->name('run-monthly');
        Route::post('/create-test-data', [SalesImportController::class, 'createTestData'])->name('create-test-data');
        Route::post('/performance-test', [SalesImportController::class, 'performanceTest'])->name('performance-test');
        Route::get('/logs', [SalesImportController::class, 'getImportLogs'])->name('logs');
        Route::post('/clear-data', [SalesImportController::class, 'clearData'])->name('clear-data');

        // Validation routes
        Route::get('/validation', [SalesImportController::class, 'validation'])->name('validation');
        Route::post('/validate-data', [SalesImportController::class, 'validateData'])->name('validate-data');
        Route::post('/comparison-data', [SalesImportController::class, 'getComparisonData'])->name('comparison-data');
        Route::post('/daily-summary', [SalesImportController::class, 'getDailySummary'])->name('daily-summary');
        Route::post('/category-validation', [SalesImportController::class, 'getCategoryValidation'])->name('category-validation');
    });

    // Udea scraping test routes
    Route::prefix('tests')->name('tests.')->group(function () {
        Route::get('/guzzle', [TestScraperController::class, 'guzzleLogin'])->name('guzzle');
        Route::get('/client', [TestScraperController::class, 'clientFetch'])->name('client');
        Route::get('/dashboard', [TestScraperController::class, 'dashboard'])->name('dashboard');
        Route::get('/customer-price/{productCode}', [TestScraperController::class, 'testCustomerPrice'])->name('customer-price');
        Route::get('/language-debug', [\App\Http\Controllers\LanguageDebugController::class, 'testLanguageControl'])->name('language-debug');
        Route::get('/english-search-test', [\App\Http\Controllers\EnglishSearchTestController::class, 'testEnglishSearch'])->name('english-search-test');
        Route::get('/authentication-test', [\App\Http\Controllers\AuthenticationTestController::class, 'testAuthentication'])->name('authentication-test');
        Route::get('/language-flag-test', [\App\Http\Controllers\LanguageFlagTestController::class, 'testLanguageFlag'])->name('language-flag-test');
        Route::get('/specific-product-test', [\App\Http\Controllers\SpecificProductTestController::class, 'testSpecificProduct'])->name('specific-product-test');

        // Phase 2 component testing
        Route::get('/phase2-components', function () {
            return view('test-phase2');
        })->name('phase2-components');

        // Phase 3 tab group component testing
        Route::get('/tab-group', function () {
            return view('test-tab-group');
        })->name('tab-group');
    });

    // Debug routes for testing supplier tables
    Route::get('/debug/suppliers', function () {
        try {
            // Test 1: Check if we can query suppliers table
            $suppliers = \DB::connection('pos')->table('suppliers')->limit(5)->get();

            // Test 2: Check if we can query supplier_link table
            $supplierLinks = \DB::connection('pos')->table('supplier_link')->limit(5)->get();

            // Test 3: Get table structure
            $supplierColumns = \DB::connection('pos')->getSchemaBuilder()->getColumnListing('suppliers');
            $linkColumns = \DB::connection('pos')->getSchemaBuilder()->getColumnListing('supplier_link');

            // Test 4: Try using the models
            $supplierModel = \App\Models\Supplier::first();
            $linkModel = \App\Models\SupplierLink::first();

            return view('debug.suppliers', compact(
                'suppliers',
                'supplierLinks',
                'supplierColumns',
                'linkColumns',
                'supplierModel',
                'linkModel'
            ));
        } catch (\Exception $e) {
            return 'Error: '.$e->getMessage();
        }
    });

    Route::get('/debug/product-suppliers', function () {
        // Get some products and check if they have supplier links
        $products = \App\Models\Product::limit(10)->get();
        $results = [];

        foreach ($products as $product) {
            $supplierLink = \App\Models\SupplierLink::where('Barcode', $product->CODE)->first();
            $stocking = \App\Models\Stocking::where('Barcode', $product->CODE)->first();
            $results[] = [
                'product_id' => $product->ID,
                'product_code' => $product->CODE,
                'product_name' => $product->NAME,
                'has_supplier_link' => $supplierLink ? 'YES' : 'NO',
                'supplier_id' => $supplierLink ? $supplierLink->SupplierID : null,
                'supplier_name' => $supplierLink && $supplierLink->supplier ? $supplierLink->supplier->Supplier : null,
                'is_stocked' => $stocking ? 'YES' : 'NO',
            ];
        }

        return view('debug.product-suppliers', compact('results'));
    });

    Route::get('/debug/stock', function () {
        try {
            // Test 1: Check raw STOCKCURRENT data
            $stockData = \DB::connection('pos')->table('STOCKCURRENT')->limit(10)->get();

            // Test 2: Check specific product IDs and their stock
            $products = \App\Models\Product::limit(5)->get();
            $stockTests = [];

            foreach ($products as $product) {
                $rawStock = \DB::connection('pos')
                    ->table('STOCKCURRENT')
                    ->where('PRODUCT', $product->ID)
                    ->first();

                $modelStock = \App\Models\StockCurrent::where('PRODUCT', $product->ID)->first();

                $stockTests[] = [
                    'product_id' => $product->ID,
                    'product_name' => $product->NAME,
                    'raw_stock_query' => $rawStock ? $rawStock->UNITS : 'NOT FOUND',
                    'model_stock_query' => $modelStock ? $modelStock->UNITS : 'NOT FOUND',
                    'getCurrentStock_method' => $product->getCurrentStock(),
                ];
            }

            // Test 3: Check if any products have stock relationships
            $productsWithStock = \App\Models\Product::with('stockCurrent')->limit(10)->get();

            return view('debug.stock', compact('stockData', 'stockTests', 'productsWithStock'));

        } catch (\Exception $e) {
            return 'Error: '.$e->getMessage();
        }
    });

    // Till Review routes
    Route::prefix('till-review')->name('till-review.')->group(function () {
        Route::get('/', [\App\Http\Controllers\TillReviewController::class, 'index'])->name('index');
        Route::get('/summary', [\App\Http\Controllers\TillReviewController::class, 'getSummary'])->name('summary');
        Route::get('/transactions', [\App\Http\Controllers\TillReviewController::class, 'getTransactions'])->name('transactions');
        Route::post('/refresh-cache', [\App\Http\Controllers\TillReviewController::class, 'refreshCache'])->name('refresh-cache');
        Route::get('/export', [\App\Http\Controllers\TillReviewController::class, 'export'])->name('export');
    });
});

require __DIR__.'/auth.php';
