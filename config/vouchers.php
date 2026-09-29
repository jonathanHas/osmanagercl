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
    ],

    // Highest value a batch of vouchers can be generated with.
    'max_face_value' => 1000,

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
