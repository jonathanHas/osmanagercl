<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Product Category Barcode Patterns
    |--------------------------------------------------------------------------
    |
    | This configuration defines barcode numbering patterns for different
    | product categories. Each category can have its own numbering scheme,
    | preferred ranges, and logic for finding the next available barcode.
    |
    */

    'categories' => [

        // Coffee Fresh - Uses 4000s range, overlaps with Bakery
        '081' => [
            'name' => 'Coffee Fresh',
            'ranges' => [[4000, 4999]],
            'priority' => 'fill_gaps', // Fill gaps in sequence first, then increment
            'description' => 'Coffee Fresh products use the 4000s numbering sequence',
        ],

        // Fruit - Uses 1000s-2000s range
        'SUB1' => [
            'name' => 'Fruit', 
            'ranges' => [[1000, 2999]],
            'priority' => 'increment', // Always increment from highest
            'description' => 'Fresh fruit products use sequential numbering in 1000s-2000s range',
        ],

        // Vegetables - Uses 1000s-2000s range (as requested)
        'SUB2' => [
            'name' => 'Vegetables',
            'ranges' => [[1000, 2999]], 
            'priority' => 'increment', // Always increment from highest
            'description' => 'Fresh vegetable products use sequential numbering in 1000s-2000s range',
        ],

        // Bakery - Uses 4000s range, overlaps with Coffee Fresh
        '082' => [
            'name' => 'Bakery',
            'ranges' => [[4000, 4999]],
            'priority' => 'fill_gaps', // Fill gaps in sequence first, then increment
            'description' => 'Bakery products use the 4000s numbering sequence',
        ],

        // Zero Waste / Lunches - Uses 7000s range
        '083' => [
            'name' => 'Zero Waste Food',
            'ranges' => [[7000, 7999]],
            'priority' => 'increment', // Always increment from highest
            'description' => 'Zero waste and lunch products use the 7000s numbering sequence',
        ],

        '50918faf-1f2e-4f78-b621-57923c49b7b5' => [
            'name' => 'Lunches',
            'ranges' => [[4000, 4999]],
            'priority' => 'increment', // Always increment from highest
            'description' => 'lunch products use the 4000s numbering sequence',
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Global Settings
    |--------------------------------------------------------------------------
    */

    'settings' => [
        // Maximum number of codes to check when looking for gaps or next available
        'max_search_range' => 200,
        
        // Exclude codes above this threshold (to avoid EAN/UPC barcodes)
        'max_internal_code' => 99999,
        
        // Default fallback starting code for new categories
        'default_start' => 1000,
    ],

];