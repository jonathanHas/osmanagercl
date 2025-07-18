<?php

/**
 * Demo script to show how products can be accessed from the POS database
 * 
 * This demonstrates the ProductRepository functionality without needing
 * to set up the full test environment.
 * 
 * To run this demo:
 * 1. Ensure your POS database credentials are set in .env
 * 2. Run: php demo-products.php
 */

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Repositories\ProductRepository;
use App\Models\Product;

echo "=== Product Repository Demo ===\n\n";

$repository = new ProductRepository();

// Try to connect and get statistics
try {
    $stats = $repository->getStatistics();
    
    echo "Product Statistics:\n";
    echo "- Total Products: " . $stats['total_products'] . "\n";
    echo "- Active Products: " . $stats['active_products'] . "\n";
    echo "- Service Products: " . $stats['service_products'] . "\n";
    echo "- In Stock: " . $stats['in_stock'] . "\n";
    echo "- Out of Stock: " . $stats['out_of_stock'] . "\n\n";
    
    // Get first 5 products
    $products = Product::limit(5)->get();
    
    if ($products->count() > 0) {
        echo "First 5 Products:\n";
        echo str_pad("Code", 15) . str_pad("Name", 30) . str_pad("Price", 10) . str_pad("Stock", 10) . "\n";
        echo str_repeat("-", 65) . "\n";
        
        foreach ($products as $product) {
            echo str_pad($product->CODE, 15);
            echo str_pad(substr($product->NAME, 0, 28), 30);
            echo str_pad('$' . number_format($product->PRICESELL, 2), 10);
            echo str_pad($product->isService() ? 'N/A' : number_format($product->STOCKUNITS, 0), 10);
            echo "\n";
        }
    } else {
        echo "No products found in the database.\n";
    }
    
} catch (\Exception $e) {
    echo "Error connecting to POS database:\n";
    echo $e->getMessage() . "\n\n";
    echo "Please ensure:\n";
    echo "1. POS database credentials are set in .env\n";
    echo "2. The POS database is accessible\n";
    echo "3. The PRODUCTS table exists in the POS database\n";
}

echo "\n";