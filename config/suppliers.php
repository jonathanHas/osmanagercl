<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Own-Farm Produce Supplier
    |--------------------------------------------------------------------------
    |
    | POS SupplierID for "Jon" — the supplier representing produce grown on our
    | own farm. Used by the Harvest log to list the products to record against.
    |
    */

    'jon' => '2',

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
            'supplier_ids' => [37], // Independent supplier ID

            // External image URL templates (tried in order until one loads)
            // {SUPPLIER_CODE} will be replaced with the supplier's product code
            // Note: Independent uses supplier code, not barcode for images
            'image_url' => 'https://iihealthfoods.com/cdn/shop/files/{SUPPLIER_CODE}_1.webp?width=533',
            'image_url_fallbacks' => [
                'https://iihealthfoods.com/cdn/shop/files/{SUPPLIER_CODE}_1.png?width=533',
                'https://iihealthfoods.com/cdn/shop/files/{SUPPLIER_CODE}_1.jpg?width=533',
                'https://iihealthfoods.com/cdn/shop/products/{SUPPLIER_CODE}_1.jpg?width=533',
            ],

            // Supplier website search URL template
            // {SUPPLIER_CODE} will be replaced with the supplier's product code
            'website_search' => 'https://iihealthfoods.com/search?q={SUPPLIER_CODE}',

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
                    'Price_Valid', 'Calc_Method', 'Expected_Value',
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

        'natural_medicine' => [
            // Supplier IDs in the database for Natural Medicine Company
            'supplier_ids' => [65], // Natural Medicine supplier ID

            // External image URL template
            // {SUPPLIER_CODE} will be replaced with the supplier's 5-digit stock code
            'image_url' => null, // No known image CDN

            // Supplier website search URL template
            // {SUPPLIER_CODE} will be replaced with the supplier's product code
            'website_search' => null, // No known search URL

            // Display name for the supplier
            'display_name' => 'Natural Medicine Company',

            // Enable/disable this integration
            'enabled' => true,

            // CSV format configuration
            'csv_format' => [
                // Standard headers for Natural Medicine invoices
                'headers' => [
                    'Stock_Code', 'Description', 'Unit', 'RRP',
                    'Qty', 'Tr_Price', 'Disc_Percent', 'Total',
                    'VAT_Percent', 'Calculated_Total', 'Validation_Status', 'SourcePDF',
                ],
                'primary_quantity_field' => 'Qty', // Quantity ordered/delivered
                'price_field' => 'Tr_Price', // Trade/wholesale price per unit
                'total_field' => 'Total', // Line total (Qty × Tr_Price)
                'vat_field' => 'VAT_Percent', // VAT rate (23.0, 13.5, or 0.0)
                'stock_code_field' => 'Stock_Code', // 5-digit supplier SKU
                'rrp_field' => 'RRP', // Recommended retail price
                'tax_handling' => 'separate_column', // VAT is separate column
                'price_includes_tax' => false, // Tr_Price excludes VAT
                'has_validation' => true, // Validation_Status column present
                'format_version' => 'tnmc_standard', // Natural Medicine standard format
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
            'iihealthfoods.com',
        ],

        // Maximum image size to display (in pixels)
        'max_image_width' => 800,
        'max_image_height' => 800,
    ],
];
