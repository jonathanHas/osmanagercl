<?php

use App\Http\Controllers\DeliveryController;
use App\Http\Controllers\FruitVegController;
use App\Http\Controllers\LabelAreaController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\TestScraperController;
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

    // Product routes
    Route::get('/products', [ProductController::class, 'index'])->name('products.index');
    Route::get('/products/create', [ProductController::class, 'create'])->name('products.create');
    Route::post('/products', [ProductController::class, 'store'])->name('products.store');
    Route::get('/products/suppliers', [ProductController::class, 'suppliersIndex'])->name('products.suppliers');
    Route::get('/products/{id}', [ProductController::class, 'show'])->name('products.show');
    Route::get('/products/{id}/sales-data', [ProductController::class, 'salesData'])->name('products.sales-data');
    Route::get('/products/{id}/refresh-udea-pricing', [ProductController::class, 'refreshUdeaPricing'])->name('products.refresh-udea-pricing');
    Route::get('/products/udea-pricing', [ProductController::class, 'getUdeaPricing'])->name('products.udea-pricing');
    Route::patch('/products/{id}/tax', [ProductController::class, 'updateTax'])->name('products.update-tax');
    Route::patch('/products/{id}/price', [ProductController::class, 'updatePrice'])->name('products.update-price');
    Route::patch('/products/{id}/cost', [ProductController::class, 'updateCost'])->name('products.update-cost');
    Route::get('/products/{id}/print-label', [ProductController::class, 'printLabel'])->name('products.print-label');

    // Label area routes
    Route::get('/labels', [LabelAreaController::class, 'index'])->name('labels.index');
    Route::post('/labels/print-a4', [LabelAreaController::class, 'printA4'])->name('labels.print-a4');
    Route::get('/labels/preview-a4', [LabelAreaController::class, 'previewA4'])->name('labels.preview-a4');
    Route::get('/labels/preview/{productId}', [LabelAreaController::class, 'previewLabel'])->name('labels.preview');

    // Requeue product route
    Route::post('/labels/requeue', [LabelAreaController::class, 'requeueProduct'])->name('labels.requeue');

    // Fruit & Veg routes
    Route::prefix('fruit-veg')->name('fruit-veg.')->group(function () {
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

    // Delivery management routes
    Route::resource('deliveries', DeliveryController::class);
    Route::get('/deliveries/{delivery}/scan', [DeliveryController::class, 'scan'])->name('deliveries.scan');
    Route::post('/deliveries/{delivery}/scan', [DeliveryController::class, 'processScan'])->name('deliveries.process-scan');
    Route::patch('/deliveries/{delivery}/items/{item}/quantity', [DeliveryController::class, 'adjustQuantity'])->name('deliveries.adjust-quantity');
    Route::get('/deliveries/{delivery}/stats', [DeliveryController::class, 'getStats'])->name('deliveries.stats');
    Route::get('/deliveries/{delivery}/summary', [DeliveryController::class, 'summary'])->name('deliveries.summary');
    Route::post('/deliveries/{delivery}/complete', [DeliveryController::class, 'complete'])->name('deliveries.complete');
    Route::post('/deliveries/{delivery}/cancel', [DeliveryController::class, 'cancel'])->name('deliveries.cancel');
    Route::get('/deliveries/{delivery}/export-discrepancies', [DeliveryController::class, 'exportDiscrepancies'])->name('deliveries.export-discrepancies');
    Route::post('/delivery-items/{item}/refresh-barcode', [DeliveryController::class, 'refreshBarcode'])->name('delivery-items.refresh-barcode');

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
});

require __DIR__.'/auth.php';
