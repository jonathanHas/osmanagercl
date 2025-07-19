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
    ],
];