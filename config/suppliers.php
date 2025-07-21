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
