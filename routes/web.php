<?php

use App\Http\Controllers\CategoriesController;
use App\Http\Controllers\CoffeeController;
use App\Http\Controllers\CoffeeMetadataController;
use App\Http\Controllers\DeliveryController;
use App\Http\Controllers\FruitVegController;
use App\Http\Controllers\KdsController;
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
    Route::post('/products/{id}/update-stock', [ProductController::class, 'updateStock'])->name('products.update-stock');
    Route::post('/products/{id}/toggle-stocking', [ProductController::class, 'toggleStocking'])->name('products.toggle-stocking');
    Route::post('/products/{id}/toggle-till-visibility', [ProductController::class, 'toggleTillVisibility'])->name('products.toggle-till-visibility');
    Route::get('/products/{id}/print-label', [ProductController::class, 'printLabel'])->name('products.print-label');

    // Invoice Management routes - specific routes BEFORE resource routes
    Route::get('/invoices/create-simple', [\App\Http\Controllers\InvoiceController::class, 'createSimple'])->name('invoices.create-simple');
    Route::post('/invoices/store-simple', [\App\Http\Controllers\InvoiceController::class, 'storeSimple'])->name('invoices.store-simple');
    Route::post('/invoices/vat-rate', [\App\Http\Controllers\InvoiceController::class, 'getVatRate'])->name('invoices.vat-rate');
    Route::post('/invoices/{invoice}/mark-paid', [\App\Http\Controllers\InvoiceController::class, 'markPaid'])->name('invoices.mark-paid');

    // Invoice Attachments routes
    Route::prefix('invoices/{invoice}/attachments')->name('invoices.attachments.')->group(function () {
        Route::get('/', [\App\Http\Controllers\InvoiceAttachmentController::class, 'index'])->name('index');
        Route::post('/', [\App\Http\Controllers\InvoiceAttachmentController::class, 'store'])->name('store');
        Route::get('/config', [\App\Http\Controllers\InvoiceAttachmentController::class, 'getUploadConfig'])->name('config');
    });
    Route::prefix('invoice-attachments/{attachment}')->name('invoices.attachments.')->group(function () {
        Route::get('/view', [\App\Http\Controllers\InvoiceAttachmentController::class, 'view'])->name('view');
        Route::get('/viewer', [\App\Http\Controllers\InvoiceAttachmentController::class, 'viewEmbedded'])->name('viewer');
        Route::get('/download', [\App\Http\Controllers\InvoiceAttachmentController::class, 'download'])->name('download');
        Route::patch('/', [\App\Http\Controllers\InvoiceAttachmentController::class, 'update'])->name('update');
        Route::delete('/', [\App\Http\Controllers\InvoiceAttachmentController::class, 'destroy'])->name('destroy');
    });

    Route::resource('invoices', \App\Http\Controllers\InvoiceController::class);

    // VAT Rates Management
    Route::get('/vat-rates', [\App\Http\Controllers\VatRateController::class, 'index'])->name('vat-rates.index');
    Route::post('/vat-rates', [\App\Http\Controllers\VatRateController::class, 'store'])->name('vat-rates.store');
    Route::put('/vat-rates/{vatRate}', [\App\Http\Controllers\VatRateController::class, 'update'])->name('vat-rates.update');
    Route::delete('/vat-rates/{vatRate}', [\App\Http\Controllers\VatRateController::class, 'destroy'])->name('vat-rates.destroy');

    // Supplier Management routes
    Route::post('/suppliers/{supplier}/refresh-analytics', [\App\Http\Controllers\AccountingSuppliersController::class, 'refreshAnalytics'])->name('suppliers.refresh-analytics');
    Route::post('/suppliers/{supplier}/toggle-status', [\App\Http\Controllers\AccountingSuppliersController::class, 'toggleStatus'])->name('suppliers.toggle-status');
    Route::resource('suppliers', \App\Http\Controllers\AccountingSuppliersController::class);

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

    // Scanner routes
    Route::post('/labels/lookup-barcode', [LabelAreaController::class, 'lookupBarcode'])->name('labels.lookup-barcode');
    Route::post('/labels/scan', [LabelAreaController::class, 'processBarcodeScan'])->name('labels.scan');

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

        // Coffee KDS Metadata management
        Route::get('/metadata', [CoffeeMetadataController::class, 'index'])->name('metadata.index');
        Route::put('/metadata/{metadata}', [CoffeeMetadataController::class, 'update'])->name('metadata.update');
        Route::post('/metadata', [CoffeeMetadataController::class, 'store'])->name('metadata.store');
        Route::delete('/metadata/{metadata}', [CoffeeMetadataController::class, 'destroy'])->name('metadata.destroy');
        Route::post('/metadata/add-syrups', [CoffeeMetadataController::class, 'addSpecificSyrups'])->name('metadata.add-syrups');
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

    // KDS (Kitchen Display System) routes
    Route::prefix('kds')->name('kds.')->middleware('permission:kds.access')->group(function () {
        Route::get('/', [KdsController::class, 'index'])->name('index');
        Route::get('/orders', [KdsController::class, 'getOrders'])->name('orders');
        Route::post('/orders/{kdsOrder}/status', [KdsController::class, 'updateStatus'])->name('update-status');
        Route::get('/stream', [KdsController::class, 'stream'])->name('stream');
        Route::post('/poll', [KdsController::class, 'pollNewOrders'])->name('poll');
        Route::post('/clear-completed', [KdsController::class, 'clearCompleted'])->name('clear-completed');
        Route::post('/clear-all', [KdsController::class, 'clearAll'])->name('clear-all');
        Route::get('/realtime-check', [\App\Http\Controllers\KdsRealtimeController::class, 'checkNewOrders'])->name('realtime-check');
    });

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

    // Cash Reconciliation routes
    Route::prefix('cash-reconciliation')->name('cash-reconciliation.')->middleware('permission:cash_reconciliation.view')->group(function () {
        Route::get('/', [\App\Http\Controllers\Management\CashReconciliationController::class, 'index'])->name('index');
        Route::post('/store', [\App\Http\Controllers\Management\CashReconciliationController::class, 'store'])
            ->middleware('permission:cash_reconciliation.create')
            ->name('store');
        Route::get('/previous-float', [\App\Http\Controllers\Management\CashReconciliationController::class, 'getPreviousFloat'])->name('previous-float');
        Route::get('/reconciliation', [\App\Http\Controllers\Management\CashReconciliationController::class, 'getReconciliation'])->name('get-reconciliation');
        Route::get('/export', [\App\Http\Controllers\Management\CashReconciliationController::class, 'export'])
            ->middleware('permission:cash_reconciliation.export')
            ->name('export');
    });

    // Financial Management routes
    Route::prefix('management')->name('management.')->middleware(['role:admin,manager'])->group(function () {
        // Financial Dashboard
        Route::get('/financial/dashboard', [\App\Http\Controllers\Management\FinancialDashboardController::class, 'index'])
            ->name('financial.dashboard');

        // Profit & Loss
        Route::get('/profit-loss', [\App\Http\Controllers\Management\ProfitLossController::class, 'index'])
            ->name('profit-loss.index');

        // VAT Dashboard
        Route::prefix('vat-dashboard')->name('vat-dashboard.')->group(function () {
            Route::get('/', [\App\Http\Controllers\Management\VatDashboardController::class, 'index'])->name('index');
            Route::get('/history', [\App\Http\Controllers\Management\VatDashboardController::class, 'history'])->name('history');
        });

        // VAT Returns Management
        Route::prefix('vat-returns')->name('vat-returns.')->group(function () {
            Route::get('/', [\App\Http\Controllers\Management\VatReturnController::class, 'index'])->name('index');
            Route::get('/create', [\App\Http\Controllers\Management\VatReturnController::class, 'create'])->name('create');
            Route::post('/', [\App\Http\Controllers\Management\VatReturnController::class, 'store'])->name('store');
            Route::get('/{vatReturn}', [\App\Http\Controllers\Management\VatReturnController::class, 'show'])->name('show');
            Route::patch('/{vatReturn}/finalize', [\App\Http\Controllers\Management\VatReturnController::class, 'finalize'])->name('finalize');
            Route::get('/{vatReturn}/export', [\App\Http\Controllers\Management\VatReturnController::class, 'export'])->name('export');
            Route::post('/export-preview', [\App\Http\Controllers\Management\VatReturnController::class, 'exportPreview'])->name('export-preview');
            Route::delete('/{vatReturn}/invoices/{invoice}', [\App\Http\Controllers\Management\VatReturnController::class, 'removeInvoice'])->name('remove-invoice');
            Route::delete('/{vatReturn}', [\App\Http\Controllers\Management\VatReturnController::class, 'destroy'])->name('destroy');
        });

        // Sales Accounting Reports
        Route::prefix('sales-accounting')->name('sales-accounting.')->group(function () {
            Route::get('/', [\App\Http\Controllers\Management\SalesAccountingReportController::class, 'index'])->name('index');
            Route::get('/export-csv', [\App\Http\Controllers\Management\SalesAccountingReportController::class, 'exportCsv'])->name('export-csv');
        });

        // OSAccounts Import Management
        Route::prefix('osaccounts-import')->name('osaccounts-import.')->group(function () {
            Route::get('/', [\App\Http\Controllers\Management\OSAccountsImportController::class, 'index'])->name('index');
            Route::get('/validate-connection', [\App\Http\Controllers\Management\OSAccountsImportController::class, 'validateConnection'])->name('validate-connection');
            Route::get('/check-supplier-mapping', [\App\Http\Controllers\Management\OSAccountsImportController::class, 'checkSupplierMapping'])->name('check-supplier-mapping');
            Route::post('/import-suppliers', [\App\Http\Controllers\Management\OSAccountsImportController::class, 'importSuppliers'])->name('import-suppliers');
            Route::post('/sync-suppliers', [\App\Http\Controllers\Management\OSAccountsImportController::class, 'syncSuppliers'])->name('sync-suppliers');
            Route::post('/import-invoices', [\App\Http\Controllers\Management\OSAccountsImportController::class, 'importInvoices'])->name('import-invoices');
            Route::post('/import-vat-lines', [\App\Http\Controllers\Management\OSAccountsImportController::class, 'importVatLines'])->name('import-vat-lines');
            Route::post('/import-attachments', [\App\Http\Controllers\Management\OSAccountsImportController::class, 'importAttachments'])->name('import-attachments');
            Route::post('/import-vat-returns', [\App\Http\Controllers\Management\OSAccountsImportController::class, 'importVatReturns'])->name('import-vat-returns');
            Route::get('/stats', [\App\Http\Controllers\Management\OSAccountsImportController::class, 'getImportStats'])->name('stats');
            Route::get('/test-stream', [\App\Http\Controllers\Management\OSAccountsImportController::class, 'testStream'])->name('test-stream');
        });
    });
});

require __DIR__.'/auth.php';
