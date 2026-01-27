<?php

use App\Http\Controllers\CategoriesController;
use App\Http\Controllers\CoffeeController;
use App\Http\Controllers\CoffeeMetadataController;
use App\Http\Controllers\BarrelCodeController;
use App\Http\Controllers\DeliveryController;
use App\Http\Controllers\DeliveryDocumentController;
use App\Http\Controllers\DeliveryLegacyController;
use App\Http\Controllers\Financials\BankStatementController;
use App\Http\Controllers\Financials\CardReconciliationController;
use App\Http\Controllers\FruitVegController;
use App\Http\Controllers\KdsController;
use App\Http\Controllers\KitchenController;
use App\Http\Controllers\KitchenIngredientProfileController;
use App\Http\Controllers\KitchenProductController;
use App\Http\Controllers\LabelAreaController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SalesImportController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\StockingController;
use App\Http\Controllers\TestScraperController;
use App\Http\Controllers\UdeaDiagnosticsController;
use App\Http\Controllers\UserManagementController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/dashboard', function () {
    $productRepository = new \App\Repositories\ProductRepository;
    $statistics = $productRepository->getStatistics();

    // Add Amazon pending count for dashboard widget
    $amazonPendingCount = \App\Models\AmazonInvoicePending::pending()->count();

    return view('dashboard', compact('statistics', 'amazonPendingCount'));
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
    Route::get('/products/{id}/edit', [ProductController::class, 'edit'])->name('products.edit');
    Route::put('/products/{id}', [ProductController::class, 'update'])->name('products.update');
    Route::get('/products/{id}/sales-data', [ProductController::class, 'salesData'])->name('products.sales-data');
    Route::get('/products/{id}/weekly-sales', [ProductController::class, 'weeklySalesData'])->name('products.weekly-sales');
    Route::get('/products/{id}/daily-sales', [ProductController::class, 'dailySalesData'])->name('products.daily-sales');
    Route::get('/products/{id}/transaction-details', [ProductController::class, 'transactionDetailsData'])->name('products.transaction-details');
    Route::get('/products/{id}/image', [ProductController::class, 'image'])->name('products.image');
    Route::post('/products/{id}/update-image', [ProductController::class, 'updateProductImage'])->name('products.update-image');
    Route::get('/products/{id}/refresh-udea-pricing', [ProductController::class, 'refreshUdeaPricing'])->name('products.refresh-udea-pricing');
    Route::get('/products/udea-pricing', [ProductController::class, 'getUdeaPricing'])->name('products.udea-pricing');
    Route::patch('/products/{id}/name', [ProductController::class, 'updateName'])->name('products.update-name');
    Route::patch('/products/{id}/tax', [ProductController::class, 'updateTax'])->name('products.update-tax');
    Route::patch('/products/{id}/category', [ProductController::class, 'updateCategory'])->name('products.update-category');
    Route::patch('/products/{id}/price', [ProductController::class, 'updatePrice'])->name('products.update-price');
    Route::patch('/products/{id}/cost', [ProductController::class, 'updateCost'])->name('products.update-cost');
    Route::patch('/products/{id}/min-stock-override', [ProductController::class, 'updateMinStockOverride'])->name('products.update-min-stock-override');
    Route::patch('/products/{id}/short-dated-settings', [ProductController::class, 'updateShortDatedSettings'])->name('products.update-short-dated-settings');
    Route::patch('/products/{id}/barcode', [ProductController::class, 'updateBarcode'])->name('products.update-barcode');
    Route::post('/products/{id}/create-alternate', [ProductController::class, 'createAlternateBarcode'])->name('products.create-alternate');
    Route::patch('/products/{id}/display', [ProductController::class, 'updateDisplay'])->name('products.update-display');
    Route::post('/products/{id}/update-stock', [ProductController::class, 'updateStock'])->name('products.update-stock');
    Route::post('/products/{id}/toggle-stocking', [ProductController::class, 'toggleStocking'])->name('products.toggle-stocking');
    Route::post('/products/{id}/toggle-till-visibility', [ProductController::class, 'toggleTillVisibility'])->name('products.toggle-till-visibility');
    Route::get('/products/{id}/print-label', [ProductController::class, 'printLabel'])->name('products.print-label');
    Route::get('/tools/udea-debug', UdeaDiagnosticsController::class)->name('tools.udea-debug');

    // Product AJAX API routes (for real-time validation)
    Route::post('/api/products/check-barcode-duplicate', [ProductController::class, 'checkBarcodeDuplicate'])->name('api.products.check-barcode-duplicate');
    Route::post('/api/products/check-supplier-link-duplicate', [ProductController::class, 'checkSupplierLinkDuplicate'])->name('api.products.check-supplier-link-duplicate');

    // Stocking scanner routes
    Route::get('/stocking', [StockingController::class, 'index'])->name('stocking.index');
    Route::post('/stocking/lookup', [StockingController::class, 'lookup'])->name('stocking.lookup');
    Route::post('/stocking/update-stock', [StockingController::class, 'updateStock'])->name('stocking.update-stock');
    Route::get('/stocking/logs', [StockingController::class, 'logs'])->name('stocking.logs')->middleware('role:admin');

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
        Route::get('/viewer-minimal', [\App\Http\Controllers\InvoiceAttachmentController::class, 'viewEmbeddedMinimal'])->name('viewer-minimal');
        Route::get('/download', [\App\Http\Controllers\InvoiceAttachmentController::class, 'download'])->name('download');
        Route::patch('/', [\App\Http\Controllers\InvoiceAttachmentController::class, 'update'])->name('update');
        Route::delete('/', [\App\Http\Controllers\InvoiceAttachmentController::class, 'destroy'])->name('destroy');
    });

    // Bulk Upload Routes (must be before resource route)
    Route::prefix('invoices/bulk-upload')->name('invoices.bulk-upload.')->group(function () {
        Route::get('/', [\App\Http\Controllers\InvoiceBulkUploadController::class, 'index'])->name('index');
        Route::post('/upload', [\App\Http\Controllers\InvoiceBulkUploadController::class, 'upload'])->name('upload');
        Route::get('/status/{batchId}', [\App\Http\Controllers\InvoiceBulkUploadController::class, 'status'])->name('status');
        Route::get('/preview/{batchId}', [\App\Http\Controllers\InvoiceBulkUploadController::class, 'preview'])->name('preview');
        Route::get('/amazon-pending', [\App\Http\Controllers\InvoiceBulkUploadController::class, 'amazonPending'])->name('amazon-pending');
        Route::delete('/amazon-pending/files', [\App\Http\Controllers\InvoiceBulkUploadController::class, 'deleteAmazonPendingFiles'])->name('delete-amazon-pending-files');
        Route::post('/{batchId}/cancel', [\App\Http\Controllers\InvoiceBulkUploadController::class, 'cancel'])->name('cancel');
        Route::post('/{batchId}/process', [\App\Http\Controllers\InvoiceBulkUploadController::class, 'startProcessing'])->name('process');
        Route::post('/{batchId}/create-from-review', [\App\Http\Controllers\InvoiceBulkUploadController::class, 'createFromReview'])->name('create-from-review');
        Route::get('/check-parser', [\App\Http\Controllers\InvoiceBulkUploadController::class, 'checkParserConfiguration'])->name('check-parser');
        Route::get('/{batchId}/file/{fileId}/viewer', [\App\Http\Controllers\InvoiceBulkUploadController::class, 'fileViewer'])->name('file-viewer');
        Route::get('/{batchId}/file/{fileId}/view', [\App\Http\Controllers\InvoiceBulkUploadController::class, 'viewFile'])->name('view-file');
        Route::delete('/{batchId}/file/{fileId}', [\App\Http\Controllers\InvoiceBulkUploadController::class, 'deleteFile'])->name('delete-file');
        Route::post('/{batchId}/file/{fileId}/retry', [\App\Http\Controllers\InvoiceBulkUploadController::class, 'retryFile'])->name('retry-file');
        Route::get('/{batchId}/file/{fileId}/thumbnails', [\App\Http\Controllers\InvoiceBulkUploadController::class, 'getThumbnails'])->name('get-thumbnails');
        Route::post('/{batchId}/file/{fileId}/split', [\App\Http\Controllers\InvoiceBulkUploadController::class, 'splitPdf'])->name('split-pdf');
        Route::put('/{batchId}/file/{fileId}/parsed-data', [\App\Http\Controllers\InvoiceBulkUploadController::class, 'updateParsedData'])->name('update-parsed-data');
    });

    Route::post('/invoices/bulk-mark-paid', [\App\Http\Controllers\InvoiceController::class, 'bulkMarkPaid'])->name('invoices.bulk-mark-paid');
    Route::patch('/invoices/{invoice}/mark-unpaid', [\App\Http\Controllers\InvoiceController::class, 'markUnpaid'])->name('invoices.mark-unpaid');

    // Amazon Pending Invoices (must be before resource route)
    Route::prefix('invoices/amazon-pending')->name('amazon-pending.')->group(function () {
        Route::get('/', [\App\Http\Controllers\AmazonPendingInvoiceController::class, 'index'])->name('index');
        Route::get('/{pending}', [\App\Http\Controllers\AmazonPendingInvoiceController::class, 'show'])->name('show');
        Route::get('/{pending}/viewer', [\App\Http\Controllers\AmazonPendingInvoiceController::class, 'viewer'])->name('viewer');
        Route::get('/{pending}/view-invoice', [\App\Http\Controllers\AmazonPendingInvoiceController::class, 'viewInvoice'])->name('view-invoice');
        Route::put('/{pending}/payment', [\App\Http\Controllers\AmazonPendingInvoiceController::class, 'updatePayment'])->name('update-payment');
        Route::post('/{pending}/process', [\App\Http\Controllers\AmazonPendingInvoiceController::class, 'process'])->name('process');
        Route::delete('/{pending}', [\App\Http\Controllers\AmazonPendingInvoiceController::class, 'cancel'])->name('cancel');
        Route::post('/bulk-process', [\App\Http\Controllers\AmazonPendingInvoiceController::class, 'bulkProcess'])->name('bulk-process');
        Route::get('/ajax/summary', [\App\Http\Controllers\AmazonPendingInvoiceController::class, 'summary'])->name('ajax.summary');
        Route::post('/{pending}/preview-calculation', [\App\Http\Controllers\AmazonPendingInvoiceController::class, 'previewCalculation'])->name('preview-calculation');
    });

    Route::get('/invoices/export', [\App\Http\Controllers\InvoiceController::class, 'exportCsv'])->name('invoices.export');
    Route::resource('invoices', \App\Http\Controllers\InvoiceController::class);

    // VAT Rates Management
    Route::get('/vat-rates', [\App\Http\Controllers\VatRateController::class, 'index'])->name('vat-rates.index');
    Route::post('/vat-rates', [\App\Http\Controllers\VatRateController::class, 'store'])->name('vat-rates.store');
    Route::put('/vat-rates/{vatRate}', [\App\Http\Controllers\VatRateController::class, 'update'])->name('vat-rates.update');
    Route::delete('/vat-rates/{vatRate}', [\App\Http\Controllers\VatRateController::class, 'destroy'])->name('vat-rates.destroy');

    // Supplier Management routes
    Route::post('/suppliers/{supplier}/refresh-analytics', [\App\Http\Controllers\AccountingSuppliersController::class, 'refreshAnalytics'])->name('suppliers.refresh-analytics');
    Route::post('/suppliers/{supplier}/toggle-status', [\App\Http\Controllers\AccountingSuppliersController::class, 'toggleStatus'])->name('suppliers.toggle-status');
    Route::get('/suppliers/outstanding-report', [\App\Http\Controllers\SupplierOutstandingController::class, 'index'])->name('suppliers.outstanding-report');
    Route::get('/suppliers/outstanding-report/export', [\App\Http\Controllers\SupplierOutstandingController::class, 'exportCsv'])->name('suppliers.outstanding-report.export');
    Route::get('/suppliers/payments', [\App\Http\Controllers\SupplierPaymentsController::class, 'index'])->name('suppliers.payments');
    Route::get('/suppliers/payments/export', [\App\Http\Controllers\SupplierPaymentsController::class, 'exportCsv'])->name('suppliers.payments.export');
    Route::resource('suppliers', \App\Http\Controllers\AccountingSuppliersController::class);

    // Order Manager routes
    Route::prefix('order-manager')->name('order-manager.')->group(function () {
        Route::get('/', [\App\Http\Controllers\OrderManagerController::class, 'index'])->name('index');
        Route::get('/check', [\App\Http\Controllers\OrderManagerController::class, 'check'])->name('check');
        Route::post('/{supplier}/toggle', [\App\Http\Controllers\OrderManagerController::class, 'toggleManaged'])->name('toggle');
        Route::patch('/{supplier}/threshold', [\App\Http\Controllers\OrderManagerController::class, 'updateThreshold'])->name('threshold');
        Route::get('/{supplier}/products', [\App\Http\Controllers\OrderManagerController::class, 'products'])->name('products');
    });

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
        Route::post('/labels/print', [FruitVegController::class, 'printLabels'])->name('labels.print');
        Route::post('/labels/printed', [FruitVegController::class, 'markLabelsPrinted'])->name('labels.printed');
        Route::post('/labels/clear-all', [FruitVegController::class, 'clearAllLabels'])->name('labels.clear-all');
        Route::post('/labels/restore-last', [FruitVegController::class, 'restoreLastPrintedBatch'])->name('labels.restore-last');
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
        Route::get('/price-sync', [FruitVegController::class, 'priceSync'])->name('price-sync');
        Route::post('/price-sync/sync', [FruitVegController::class, 'syncPrice'])->name('price-sync.sync');
        Route::post('/price-sync/bulk-sync', [FruitVegController::class, 'bulkSyncPrices'])->name('price-sync.bulk-sync');

        // F&V Order Generation
        Route::get('/orders', [FruitVegController::class, 'orders'])->name('orders');
        Route::post('/orders', [FruitVegController::class, 'generateOrder'])->name('orders.generate');
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

    // Kitchen/Recipe Management routes
    Route::prefix('kitchen')->name('kitchen.')->group(function () {
        // Static routes first
        Route::get('/', [KitchenController::class, 'index'])->name('index');
        Route::get('/create', [KitchenController::class, 'create'])->name('create');
        Route::post('/', [KitchenController::class, 'store'])->name('store');

        // Kitchen Products (must be before {recipe} wildcard)
        Route::prefix('products')->name('products.')->group(function () {
            Route::get('/', [KitchenProductController::class, 'index'])->name('index');
            Route::get('/search', [KitchenProductController::class, 'search'])->name('search');
            Route::post('/toggle', [KitchenProductController::class, 'toggle'])->name('toggle');
            Route::delete('/{kitchenProduct}', [KitchenProductController::class, 'destroy'])->name('destroy');
        });

        // Ingredient Profiles (must be before {recipe} wildcard)
        Route::prefix('profiles')->name('profiles.')->group(function () {
            Route::get('/', [KitchenIngredientProfileController::class, 'index'])->name('index');
            Route::get('/create', [KitchenIngredientProfileController::class, 'create'])->name('create');
            Route::post('/', [KitchenIngredientProfileController::class, 'store'])->name('store');
            Route::get('/{profile}/edit', [KitchenIngredientProfileController::class, 'edit'])->name('edit');
            Route::put('/{profile}', [KitchenIngredientProfileController::class, 'update'])->name('update');
            Route::delete('/{profile}', [KitchenIngredientProfileController::class, 'destroy'])->name('destroy');
            Route::post('/{profile}/recalculate', [KitchenIngredientProfileController::class, 'recalculate'])->name('recalculate');
        });

        // AJAX API endpoints (must be before {recipe} wildcard)
        Route::get('/api/products/search', [KitchenController::class, 'searchProducts'])->name('api.products.search');
        Route::get('/api/recipes/{recipe}/costs', [KitchenController::class, 'getRecipeCosts'])->name('api.costs');
        Route::post('/api/recipes/{recipe}/recalculate', [KitchenController::class, 'recalculateCosts'])->name('api.recalculate');
        Route::get('/api/profiles/search', [KitchenIngredientProfileController::class, 'search'])->name('api.profiles.search');
        Route::get('/api/profiles/products/search', [KitchenIngredientProfileController::class, 'searchProducts'])->name('api.profiles.products.search');

        // Ingredient management
        Route::put('/ingredients/{ingredient}', [KitchenController::class, 'updateIngredient'])->name('ingredients.update');
        Route::delete('/ingredients/{ingredient}', [KitchenController::class, 'removeIngredient'])->name('ingredients.remove');

        // Recipe wildcard routes (must be last)
        Route::get('/{recipe}', [KitchenController::class, 'show'])->name('show');
        Route::get('/{recipe}/edit', [KitchenController::class, 'edit'])->name('edit');
        Route::put('/{recipe}', [KitchenController::class, 'update'])->name('update');
        Route::delete('/{recipe}', [KitchenController::class, 'destroy'])->name('destroy');
        Route::post('/{recipe}/ingredients', [KitchenController::class, 'addIngredient'])->name('ingredients.add');
        Route::post('/{recipe}/scale', [KitchenController::class, 'saveScaledRecipe'])->name('scale');
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
        Route::post('/category-visibility/toggle', [CategoriesController::class, 'toggleCategoryVisibility'])->name('category-visibility.toggle');
        Route::get('/product-image/{code}', [CategoriesController::class, 'productImage'])->name('product-image');
    });

    // Delivery management routes
    Route::resource('deliveries', DeliveryController::class);
    Route::post('/deliveries/detect-supplier', [DeliveryController::class, 'detectSupplier'])->name('deliveries.detect-supplier');
    Route::post('/deliveries/parse-pdf', [DeliveryController::class, 'parsePdf'])->name('deliveries.parse-pdf');
    Route::post('/deliveries/store-pdf', [DeliveryController::class, 'storePdf'])->name('deliveries.store-pdf');
    Route::get('/deliveries/{delivery}/scan', [DeliveryController::class, 'scan'])->name('deliveries.scan');
    Route::post('/deliveries/{delivery}/scan', [DeliveryController::class, 'processScan'])->name('deliveries.process-scan');
    Route::patch('/deliveries/{delivery}/items/{item}/quantity', [DeliveryController::class, 'adjustQuantity'])->name('deliveries.adjust-quantity');
    Route::patch('/deliveries/{delivery}/items/{item}/price', [DeliveryController::class, 'updateItemPrice'])->name('deliveries.update-item-price');
    Route::get('/deliveries/{delivery}/stats', [DeliveryController::class, 'getStats'])->name('deliveries.stats');
    Route::get('/deliveries/{delivery}/summary', [DeliveryController::class, 'summary'])->name('deliveries.summary');
    Route::post('/deliveries/{delivery}/complete', [DeliveryController::class, 'complete'])->name('deliveries.complete');
    Route::post('/deliveries/{delivery}/cancel', [DeliveryController::class, 'cancel'])->name('deliveries.cancel');
    Route::post('/deliveries/{delivery}/toggle-order-stock', [DeliveryController::class, 'toggleOrderStock'])->name('deliveries.toggle-order-stock');
    Route::get('/deliveries/{delivery}/export-discrepancies', [DeliveryController::class, 'exportDiscrepancies'])->name('deliveries.export-discrepancies');
    Route::post('/deliveries/{delivery}/update-costs', [DeliveryController::class, 'updateCosts'])->name('deliveries.update-costs');
    Route::post('/deliveries/{delivery}/sync-legacy', [DeliveryController::class, 'syncToLegacy'])->name('deliveries.sync-legacy');
    Route::post('/delivery-items/{item}/refresh-barcode', [DeliveryController::class, 'refreshBarcode'])->name('delivery-items.refresh-barcode');

    // Delivery Documents
    Route::get('/deliveries/{delivery}/documents', [DeliveryDocumentController::class, 'index'])->name('deliveries.documents.index');
    Route::get('/delivery-documents/{document}/view', [DeliveryDocumentController::class, 'view'])->name('delivery-documents.view');
    Route::get('/delivery-documents/{document}/viewer', [DeliveryDocumentController::class, 'viewEmbedded'])->name('delivery-documents.viewer');
    Route::get('/delivery-documents/{document}/viewer-minimal', [DeliveryDocumentController::class, 'viewEmbeddedMinimal'])->name('delivery-documents.viewer-minimal');
    Route::get('/delivery-documents/{document}/download', [DeliveryDocumentController::class, 'download'])->name('delivery-documents.download');
    Route::delete('/delivery-documents/{document}', [DeliveryDocumentController::class, 'destroy'])->name('delivery-documents.destroy');

    // Barrel Codes management (deposit items from deliveries)
    Route::get('/barrel-codes', [BarrelCodeController::class, 'index'])->name('barrel-codes.index');
    Route::get('/barrel-codes/{barrelCode}/edit', [BarrelCodeController::class, 'edit'])->name('barrel-codes.edit');
    Route::put('/barrel-codes/{barrelCode}', [BarrelCodeController::class, 'update'])->name('barrel-codes.update');
    Route::get('/barrel-codes/{barrelCode}/image', [BarrelCodeController::class, 'image'])->name('barrel-codes.image');
    Route::post('/barrel-codes/{barrelCode}/image', [BarrelCodeController::class, 'updateImage'])->name('barrel-codes.update-image');
    Route::delete('/barrel-codes/{barrelCode}/image', [BarrelCodeController::class, 'removeImage'])->name('barrel-codes.remove-image');

    // Delivery Legacy (Invoice Match) - replicates legacy PHP workflow
    Route::prefix('delivery-legacy')->name('delivery-legacy.')->group(function () {
        Route::get('/', [DeliveryLegacyController::class, 'index'])->name('index');
        Route::get('/match', [DeliveryLegacyController::class, 'match'])->name('match');
        Route::post('/create-session', [DeliveryLegacyController::class, 'createSession'])->name('create-session');
        Route::patch('/scan-item', [DeliveryLegacyController::class, 'updateScannedQuantity'])->name('update-quantity');
        Route::patch('/update-case-units', [DeliveryLegacyController::class, 'updateCaseUnits'])->name('update-case-units');
        Route::post('/complete', [DeliveryLegacyController::class, 'completeDelivery'])->name('complete');
    });

    // Order Management mockup routes (for UI testing)
    Route::get('/orders/mockups', fn () => view('orders.mockup-index'))->name('orders.mockups');
    Route::get('/orders/mockup/1-charts', fn () => view('orders.mockup-1-charts'))->name('orders.mockup.1');
    Route::get('/orders/mockup/2-compact', fn () => view('orders.mockup-2-compact'))->name('orders.mockup.2');
    Route::get('/orders/mockup/3-dashboard', fn () => view('orders.mockup-3-dashboard'))->name('orders.mockup.3');
    Route::get('/orders/mockup/layout-experiments', fn () => view('orders.mockup-layout-experiments'))->name('orders.mockup.layout-experiments');
    Route::get('/orders/mockup/layout-experiments2', fn () => view('orders.mockup-layout-experiments2'))->name('orders.mockup.layout-experiments2');
    Route::get('/orders/mockup/layout-experiments4', fn () => view('orders.mockup-layout-experiments4'))->name('orders.mockup.layout-experiments4');
    Route::get('/orders/mockup/layout-experiments3', fn () => view('orders.mockup-layout-experiments3'))->name('orders.mockup.layout-experiments3');

    // Real data mockup (Vico supplier)
    Route::get('/orders/mockup/vico-live', [OrderController::class, 'mockupVicoLive'])->name('orders.mockup.vico-live');

    // Order Management routes
    Route::resource('orders', OrderController::class);
    Route::post('/orders/{order}/complete', [OrderController::class, 'complete'])->name('orders.complete');
    Route::post('/orders/{order}/duplicate', [OrderController::class, 'duplicate'])->name('orders.duplicate');
    Route::get('/orders/{order}/export', [OrderController::class, 'export'])->name('orders.export');
    Route::get('/orders/{order}/grid-view', [OrderController::class, 'gridView'])->name('orders.grid-view');
    Route::get('/orders/{order}/layout-a2', [OrderController::class, 'showLayoutA2'])->name('orders.layout-a2');
    Route::get('/orders/{order}/layout-a2-dense', [OrderController::class, 'showLayoutA2Dense'])->name('orders.layout-a2-dense');
    Route::get('/orders/{order}/christmas-review', [OrderController::class, 'showChristmasReview'])->name('orders.christmas-review');
    Route::get('/orders/{order}/statistics', [OrderController::class, 'statistics'])->name('orders.statistics');
    Route::patch('/orders/{order}/coverage-overrides', [OrderController::class, 'updateCategoryCoverage'])->name('orders.coverage-overrides');
    Route::patch('/order-items/{orderItem}/quantity', [OrderController::class, 'updateQuantity'])->name('order-items.update-quantity');
    Route::patch('/order-items/{orderItem}/cases', [OrderController::class, 'updateCaseQuantity'])->name('order-items.update-cases');
    Route::patch('/order-items/{orderItem}/cost', [OrderController::class, 'updateItemCost'])->name('order-items.update-cost');
    Route::patch('/order-items/{orderItem}/priority', [OrderController::class, 'updateItemPriority'])->name('order-items.update-priority');
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
        Route::post('/find-gaps', [SalesImportController::class, 'findGaps'])->name('find-gaps');
        Route::post('/find-daily-discrepancies', [SalesImportController::class, 'findDailyDiscrepancies'])->name('find-daily-discrepancies');
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
        // Bank Statement Import
        Route::prefix('bank-statements')->name('bank-statements.')->group(function () {
            Route::get('/', [BankStatementController::class, 'index'])->name('index');
            Route::post('/', [BankStatementController::class, 'store'])->name('store');
            Route::get('/reconciliation', [BankStatementController::class, 'reconciliation'])->name('reconciliation');
            Route::get('/status', [BankStatementController::class, 'getProcessingStatus'])->name('status');
            Route::get('/history', [BankStatementController::class, 'getUploadHistory'])->name('history');
            Route::delete('/delete', [BankStatementController::class, 'deleteUpload'])->name('delete');
            Route::post('/cleanup-duplicates', [BankStatementController::class, 'cleanupDuplicates'])->name('cleanup-duplicates');

            // Analysis routes
            Route::get('/analysis', [\App\Http\Controllers\Financials\BankStatementAnalysisController::class, 'index'])->name('analysis');
            Route::post('/analysis/match', [\App\Http\Controllers\Financials\BankStatementAnalysisController::class, 'matchTransaction'])->name('analysis.match');
            Route::post('/analysis/unmatch', [\App\Http\Controllers\Financials\BankStatementAnalysisController::class, 'unmatchTransaction'])->name('analysis.unmatch');
            Route::post('/analysis/refresh-pos', [\App\Http\Controllers\Financials\BankStatementAnalysisController::class, 'refreshPOSData'])->name('analysis.refresh-pos');
            Route::get('/analysis/export', [\App\Http\Controllers\Financials\BankStatementAnalysisController::class, 'export'])->name('analysis.export');
            Route::post('/analysis/suggest', [\App\Http\Controllers\Financials\BankStatementAnalysisController::class, 'suggestMatches'])->name('analysis.suggest');
        });

        // Card Transaction Reconciliation
        Route::prefix('card-reconciliation')->name('card-reconciliation.')->group(function () {
            Route::get('/', [CardReconciliationController::class, 'index'])->name('index');
            Route::post('/', [CardReconciliationController::class, 'store'])->name('store');
            Route::get('/status/{batchId}', [CardReconciliationController::class, 'status'])->name('status');
            Route::get('/transactions', [CardReconciliationController::class, 'transactions'])->name('transactions');
            Route::post('/match', [CardReconciliationController::class, 'match'])->name('match');
            Route::post('/unmatch', [CardReconciliationController::class, 'unmatch'])->name('unmatch');
            Route::get('/nearby-payments', [CardReconciliationController::class, 'nearbyPayments'])->name('nearby-payments');
            Route::get('/export', [CardReconciliationController::class, 'export'])->name('export');
            Route::match(['get', 'post'], '/settings', [CardReconciliationController::class, 'settings'])->name('settings');
            Route::delete('/delete', [CardReconciliationController::class, 'deleteBatch'])->name('delete');
            Route::post('/reprocess', [CardReconciliationController::class, 'reprocess'])->name('reprocess');
            Route::get('/preview-auto-match', [CardReconciliationController::class, 'previewAutoMatch'])->name('preview-auto-match');
            Route::post('/auto-match', [CardReconciliationController::class, 'autoMatchBatch'])->name('auto-match');

            // Terminal-Till Mappings
            Route::get('/terminal-mappings', [CardReconciliationController::class, 'terminalMappings'])->name('terminal-mappings');
            Route::post('/terminal-mappings', [CardReconciliationController::class, 'saveTerminalMapping'])->name('save-terminal-mapping');
            Route::delete('/terminal-mappings/{mapping}', [CardReconciliationController::class, 'deleteTerminalMapping'])->name('delete-terminal-mapping');
        });

        // Financial Dashboard
        Route::get('/financial/dashboard', [\App\Http\Controllers\Management\FinancialDashboardController::class, 'index'])
            ->name('financial.dashboard');

        // Sales Review
        Route::get('/sales-review', [\App\Http\Controllers\Management\SalesReviewController::class, 'index'])
            ->name('sales-review.index');

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
            Route::post('/sync-payment-status', [\App\Http\Controllers\Management\OSAccountsImportController::class, 'syncPaymentStatus'])->name('sync-payment-status');
            Route::post('/import-attachments', [\App\Http\Controllers\Management\OSAccountsImportController::class, 'importAttachments'])->name('import-attachments');
            Route::post('/import-vat-returns', [\App\Http\Controllers\Management\OSAccountsImportController::class, 'importVatReturns'])->name('import-vat-returns');
            Route::get('/stats', [\App\Http\Controllers\Management\OSAccountsImportController::class, 'getImportStats'])->name('stats');
            Route::get('/test-stream', [\App\Http\Controllers\Management\OSAccountsImportController::class, 'testStream'])->name('test-stream');
        });

        // Cash Lodgements Management
        Route::prefix('cash-lodgements')->name('cash-lodgements.')->group(function () {
            Route::get('/', [\App\Http\Controllers\Management\CashLodgementController::class, 'index'])->name('index');
            Route::get('/diagnostic', [\App\Http\Controllers\Management\CashLodgementDiagnosticController::class, 'index'])->name('diagnostic');
            Route::get('/diagnostic/export', [\App\Http\Controllers\Management\CashLodgementDiagnosticController::class, 'export'])->name('diagnostic.export');
            Route::get('/{lodgement}', [\App\Http\Controllers\Management\CashLodgementController::class, 'show'])->name('show');
            Route::get('/export/csv', [\App\Http\Controllers\Management\CashLodgementController::class, 'export'])->name('export');
        });

        // Stock Valuation Management
        Route::prefix('stock-valuation')->name('stock-valuation.')->group(function () {
            Route::get('/', [\App\Http\Controllers\Management\StockValuationController::class, 'index'])->name('index');
            Route::get('/live', [\App\Http\Controllers\Management\StockValuationController::class, 'live'])->name('live');
            Route::get('/live/category/{category}', [\App\Http\Controllers\Management\StockValuationController::class, 'liveCategory'])->name('live.category');
            Route::get('/create', [\App\Http\Controllers\Management\StockValuationController::class, 'create'])->name('create');
            Route::post('/', [\App\Http\Controllers\Management\StockValuationController::class, 'store'])->name('store');
            Route::get('/{snapshot}', [\App\Http\Controllers\Management\StockValuationController::class, 'show'])->name('show');
            Route::get('/{snapshot}/category/{category}', [\App\Http\Controllers\Management\StockValuationController::class, 'category'])->name('category');
            Route::post('/{snapshot}/override', [\App\Http\Controllers\Management\StockValuationController::class, 'override'])->name('override');
            Route::post('/{snapshot}/refresh', [\App\Http\Controllers\Management\StockValuationController::class, 'refresh'])->name('refresh');
            Route::post('/{snapshot}/finalize', [\App\Http\Controllers\Management\StockValuationController::class, 'finalize'])->name('finalize');
            Route::get('/{snapshot}/export', [\App\Http\Controllers\Management\StockValuationController::class, 'export'])->name('export');
            Route::delete('/{snapshot}', [\App\Http\Controllers\Management\StockValuationController::class, 'destroy'])->name('destroy');
        });
    });
});

require __DIR__.'/auth.php';
