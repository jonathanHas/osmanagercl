<?php

/**
 * Shop mode navigation, as data.
 *
 * `permissions` is passed to User::hasAnyPermission(): a tile is rendered only
 * when the user holds at least one of them. `badge` is a key the Home
 * controller resolves to a count (config files cannot hold closures).
 * `tone => 'sage'` picks the sage icon variant from shop.css.
 */
return [
    'tiles' => [
        ['key' => 'stock-scan', 'label' => 'Stock scan', 'hint' => 'Count and adjust', 'icon' => 'package', 'route' => 'shop.stock-scan', 'permissions' => ['stocking.scan'], 'badge' => null],
        ['key' => 'find-product', 'label' => 'Find product', 'hint' => 'Search and check stock', 'icon' => 'search', 'route' => 'shop.find-product', 'permissions' => ['products.view'], 'badge' => null],
        ['key' => 'deliveries', 'label' => 'Receive delivery', 'hint' => 'Scan a delivery in', 'icon' => 'truck', 'route' => 'shop.deliveries', 'permissions' => ['deliveries.process'], 'badge' => 'deliveries'],
        ['key' => 'labels', 'label' => 'Print labels', 'hint' => 'Shelf and Zebra labels', 'icon' => 'printer', 'route' => 'labels.index', 'permissions' => ['labels.print'], 'badge' => null],
        ['key' => 'kds', 'label' => 'Coffee orders', 'hint' => 'Kitchen display', 'icon' => 'coffee', 'route' => 'kds.index', 'permissions' => ['kds.access'], 'badge' => null],
        ['key' => 'requests', 'label' => 'Customer requests', 'hint' => 'Pre-orders and sourcing', 'icon' => 'requests', 'route' => 'customer-requests.index', 'permissions' => ['customer-requests.manage'], 'badge' => 'requests'],
        ['key' => 'vouchers', 'label' => 'Vouchers', 'hint' => 'Balance and redeem', 'icon' => 'gift', 'route' => 'vouchers.index', 'permissions' => ['vouchers.redeem'], 'badge' => null],
        ['key' => 'fruit-veg', 'label' => 'Fruit & veg', 'hint' => 'Availability, waste, harvest', 'icon' => 'carrot', 'route' => 'fruit-veg.availability', 'permissions' => ['fruit_veg.manage'], 'badge' => null, 'tone' => 'sage'],
    ],
];
