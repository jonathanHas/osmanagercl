<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Till redemption — POS product per voucher
    |--------------------------------------------------------------------------
    |
    | Every voucher gets a hidden, zero-price uniCenta product whose barcode is
    | the voucher code, so scanning the label at the till adds a €0.00 line.
    | The product lives in its own category (no PRODUCTS_CAT row, so no till
    | button) and its NAME carries the current balance so the cashier can read
    | it on the till before choosing the Voucher tender amount.
    |
    */

    'pos_category_name' => env('VOUCHER_POS_CATEGORY', 'Gift Voucher Redemption'),

    'pos_category_parent_id' => env('VOUCHER_POS_CATEGORY_PARENT', '033'),

    'pos_taxcat' => '000',

    // First %s is the voucher code, second is the balance text below.
    'pos_name_format' => 'Gift Voucher %s [%s]',

    // Keyed by voucher status; the active text takes the formatted balance.
    'pos_balance_text' => [
        'active' => 'bal €%s',
        'exhausted' => '€0.00 used up',
        'inactive' => 'not active',
        'deactivated' => 'deactivated',
        // An inactive voucher with a face value: its till product is priced to sell.
        'for_sale' => 'for sale €%s',
        // A soft-deleted voucher (admin tools): its label still scans, at €0.00.
        'deleted' => 'deleted',
    ],

    // Highest value a batch of vouchers can be generated with.
    'max_face_value' => 1000,

    /*
    |--------------------------------------------------------------------------
    | Admin changeover tools (vouchers cycle 4)
    |--------------------------------------------------------------------------
    |
    | Bulk make-for-sale / deactivate / reactivate / delete / restore on the
    | voucher list, and vouchers:retire-fixed-products. Changeover tools for
    | bringing pre-2026-09-30 vouchers into the sell-at-the-till process; admins
    | only, and meant to be removed once they are no longer needed (see
    | docs/features/voucher-management.md, "Admin changeover tools").
    |
    */

    'admin_tools' => (bool) env('VOUCHER_ADMIN_TOOLS', true),

    // The old fixed voucher products: 6012 "Voucher 50 Euro", 6013 "Voucher 10 Euro",
    // 6014 "Voucher 20 Euro". Only vouchers:retire-fixed-products touches them.
    'legacy_product_codes' => ['6012', '6013', '6014'],

    /*
    |--------------------------------------------------------------------------
    | Till redemption — sync
    |--------------------------------------------------------------------------
    |
    | vouchers:sync-till runs every minute and also on every voucher lookup
    | (throttled). It reads new tickets carrying a voucher product and deducts
    | the receipt's Voucher (paperin) tender from that voucher.
    |
    */

    'sync' => [
        'lookback_hours' => 24,
        'overlap_minutes' => 15,
        'batch' => 50,
        'on_lookup' => true,
        'lookup_throttle_seconds' => 5,
    ],

    /*
    |--------------------------------------------------------------------------
    | Activity screen (/vouchers/activity)
    |--------------------------------------------------------------------------
    */

    'activity' => [
        'poll_seconds' => 5,              // how often the activity page asks for the feed
        'limit' => 100,                   // newest events returned per poll
        'scheduler_stale_seconds' => 180, // no scheduled till check for this long = warning
    ],

];
