<?php

return [
    /*
    |--------------------------------------------------------------------------
    | External Supplier Integrations
    |--------------------------------------------------------------------------
    |
    | Configuration for external supplier image and website integrations.
    | Currently supporting Udea supplier with Ekoplaza CDN images.
    |
    */

    'external_links' => [
        'udea' => [
            // Supplier IDs in the database for Udea variants
            'supplier_ids' => [5, 44, 85], // Udea, Udea Veg, Udea Frozen

            // External image URL template
            // {CODE} will be replaced with the product CODE/barcode
            'image_url' => 'https://cdn.ekoplaza.nl/ekoplaza/producten/small/{CODE}.jpg',

            // Supplier website search URL template
            // {SUPPLIER_CODE} will be replaced with the supplier's product code
            'website_search' => 'https://www.udea.nl/search/?qry={SUPPLIER_CODE}',

            // Display name for the supplier
            'display_name' => 'Udea',

            // Enable/disable this integration
            'enabled' => true,
        ],

        'independent' => [
            // Supplier IDs in the database for Independent Health Foods
            'supplier_ids' => [], // To be configured based on actual supplier IDs

            // External image URL template (if available)
            // {CODE} will be replaced with the product CODE/barcode
            'image_url' => null, // To be configured if Independent provides image URLs

            // Supplier website search URL template
            // {SUPPLIER_CODE} will be replaced with the supplier's product code
            'website_search' => 'https://www.independenthealthfoods.ie/search?q={SUPPLIER_CODE}',

            // Display name for the supplier
            'display_name' => 'Independent Health Foods',

            // Enable/disable this integration
            'enabled' => true,

            // Enhanced CSV format configuration
            'csv_format' => [
                // Enhanced format headers with detailed unit breakdown
                'headers' => [
                    'Filename', 'Code', 'Product', 'Ordered', 'Qty',
                    'Ordered_Cases', 'Ordered_Units', 'Delivered_Cases', 'Delivered_Units',
                    'Case_Size', 'Total_Ordered_Units', 'Total_Delivered_Units',
                    'RSP', 'Price', 'Unit_Cost', 'Tax', 'Value',
                    'Price_Valid', 'Calc_Method', 'Expected_Value'
                ],
                'primary_quantity_field' => 'Total_Delivered_Units', // What we're being charged for
                'case_size_field' => 'Case_Size', // Units per case
                'quantity_notation' => 'supports_ordered_received', // Supports "x/y" format in Ordered/Qty fields
                'tax_handling' => 'separate_column', // Tax is provided as separate column
                'price_includes_tax' => false, // Price excludes tax
                'has_validation' => true, // Price_Valid column for data integrity
                'format_version' => 'enhanced', // Using enhanced format with detailed breakdown
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Settings
    |--------------------------------------------------------------------------
    */

    'defaults' => [
        // Default image dimensions for thumbnails in product list
        'thumbnail_width' => 40,
        'thumbnail_height' => 40,

        // Lazy loading for performance
        'lazy_load_images' => true,

        // Cache external images for this many seconds (0 to disable)
        'image_cache_ttl' => 86400, // 24 hours
    ],

    /*
    |--------------------------------------------------------------------------
    | Security Settings
    |--------------------------------------------------------------------------
    */

    'security' => [
        // Allowed external image domains for Content Security Policy
        'allowed_image_domains' => [
            'cdn.ekoplaza.nl',
        ],

        // Maximum image size to display (in pixels)
        'max_image_width' => 800,
        'max_image_height' => 800,
    ],
];
