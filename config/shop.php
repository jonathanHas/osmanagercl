<?php

/**
 * Shop mode navigation, as data.
 *
 * `permissions` is passed to User::hasAnyPermission(): a tile is rendered only
 * when the user holds at least one of them. `badge` is a key the Home
 * controller resolves to a count (config files cannot hold closures).
 * `tone => 'sage'` picks the sage icon variant from shop.css.
 *
 * Coffee orders is deliberately not here: baristas land straight on the KDS
 * (UiMode::landingUrl), so a Home tile for it would be a detour.
 */
return [
    'tiles' => [
        ['key' => 'stock-scan', 'label' => 'Stock scan', 'hint' => 'Count and adjust', 'icon' => 'package', 'route' => 'shop.stock-scan', 'permissions' => ['stocking.scan'], 'badge' => null],
        ['key' => 'find-product', 'label' => 'Find product', 'hint' => 'Search and check stock', 'icon' => 'search', 'route' => 'shop.find-product', 'permissions' => ['products.view'], 'badge' => null],
        ['key' => 'deliveries', 'label' => 'Receive delivery', 'hint' => 'Scan a delivery in', 'icon' => 'truck', 'route' => 'shop.deliveries', 'permissions' => ['deliveries.process'], 'badge' => 'deliveries'],
        ['key' => 'labels', 'label' => 'Print labels', 'hint' => 'Shelf and Zebra labels', 'icon' => 'printer', 'route' => 'shop.labels', 'permissions' => ['labels.print'], 'badge' => 'labels'],
        ['key' => 'orders', 'label' => 'Orders', 'hint' => 'Review supplier orders', 'icon' => 'chart', 'route' => 'shop.orders', 'permissions' => ['orders.review'], 'badge' => null],
        ['key' => 'requests', 'label' => 'Customer requests', 'hint' => 'Pre-orders and sourcing', 'icon' => 'requests', 'route' => 'customer-requests.index', 'permissions' => ['customer-requests.manage'], 'badge' => 'requests'],
        ['key' => 'vouchers', 'label' => 'Vouchers', 'hint' => 'Balance and redeem', 'icon' => 'gift', 'route' => 'shop.vouchers', 'permissions' => ['vouchers.redeem'], 'badge' => null],
        ['key' => 'fruit-veg', 'label' => 'Fruit & veg', 'hint' => 'Waste and harvest logs', 'icon' => 'carrot', 'route' => 'shop.fv.waste', 'permissions' => ['fruit_veg.operate'], 'badge' => null, 'tone' => 'sage'],
    ],

    /**
     * Minutes of no input before a trusted shared device locks itself.
     * Owner's trial value (cycle 26): five minutes.
     */
    'idle_lock_minutes' => 5,

    /**
     * Roles whose members may sign in with a PIN. Managers and admins are
     * deliberately absent: a PIN session is confined to the Shop.
     */
    'pin_roles' => ['employee'],

    /**
     * Wrong PINs allowed before that one person's PIN is refused, and for how
     * long. Keyed per user (see SwitchUserController::limiterKey), so one
     * fumbling employee cannot lock the tablet for everyone.
     */
    'pin_attempts' => 5,
    'pin_lockout_minutes' => 15,

    /** Cookie holding the trusted-device token. */
    'device_cookie' => 'shop_device',

    /**
     * Route names a PIN session may reach without confirming a password
     * (Str::is patterns; see App\Http\Middleware\ConfinePinSession).
     *
     * Cycle 26. Everything here is either the Shop shell itself or an office
     * endpoint a Shop screen calls; ShopPinRouteAllowListTest asserts that
     * every route named in resources/views/shop/** is covered, so a new Shop
     * screen that calls a new endpoint fails the suite rather than 403-ing on
     * the shop floor.
     */
    'pin_session_routes' => [
        'shop.*',
        // Procedures (SOPs) from BookStack: the reader, its images and the
        // refresh form are named by Shop views. help.refresh keeps its own
        // role:manager,admin gate, and managers have no PIN.
        'help.*',
        'api.products.search',
        'customer-requests.*',
        'delivery-legacy.items',
        'delivery-legacy.scan-increment',
        'delivery-legacy.update-quantity',
        'delivery-legacy.complete',
        'delivery-legacy.create-session',
        'delivery-legacy.save-outer-barcode',
        'fruit-veg.harvest.rows',
        'fruit-veg.harvest.save-row',
        'fruit-veg.waste.entry',
        'fruit-veg.waste.rows',
        'fruit-veg.waste.search',
        'fruit-veg.product-image',
        // The search API hands Shop screens route('products.image', …) as a
        // product's picture when the POS holds a blob (cycle 27). The route
        // keeps its own products.view gate.
        'products.image',
        'labels.dismiss',
        'labels.dismiss-all',
        'labels.print-a4',
        'labels.queue',
        'labels.scan',
        'labels.shelf-labels',
        'stocking.lookup',
        'stocking.update-stock',
        'vouchers.activate',
        'vouchers.deduct',
        'vouchers.lookup',
        'zebra-labels.print',
        'ui-mode.set',
        'login',
        'logout',
        'logout.get',
        'auth.check',
        'password.confirm',
        'password.confirm.store',
        'verification.*',
    ],
];
