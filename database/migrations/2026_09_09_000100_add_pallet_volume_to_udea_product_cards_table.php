<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Udea publishes per-product pallet volume data only on the logged-in cart page
 * (/orders/cart), and only for products currently in the basket. Product detail
 * pages, search listings and the site JS carry none of it, and there is no API.
 *
 * The values are per-product constants, independent of ordered quantity, so once
 * captured they remain valid. Udea's own calculation (js/orders/order.js:1556) is:
 *
 *     lineVolume = sve * volume * qty
 *     linePct    = round(lineVolume / palletCapacity * 100, 2)   // rounded per line
 *
 * with pallet capacities (Europallet 250, blockpallet 360) held in config/suppliers.php.
 *
 * These columns live here rather than on PRODUCTS because the data is supplier-specific
 * logistics information, not a product attribute, and Udea is one supplier of several.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('udea_product_cards', function (Blueprint $table) {
            $table->decimal('pallet_sve', 10, 4)->nullable()->after('units_per_case')
                ->comment('Udea cart data-sve. Order-unit multiplier in the pallet calc. NOT always the case size and NOT always an integer - 0.22, 1.44, 1.5, 2.5 and 4.5 all observed on weight-priced goods.');
            $table->decimal('pallet_unit_volume', 10, 4)->nullable()->after('pallet_sve')
                ->comment('Udea cart data-volume. Pallet volume units per sve unit. Line volume = pallet_sve * pallet_unit_volume * qty.');
            $table->timestamp('pallet_scraped_at')->nullable()->after('pallet_unit_volume')
                ->comment('When the pallet figures were last read from the basket. Deliberately separate from scraped_at, which drives the 30-day tier-cache staleness check in UdeaScrapingService.');
        });
    }

    public function down(): void
    {
        Schema::table('udea_product_cards', function (Blueprint $table) {
            $table->dropColumn(['pallet_sve', 'pallet_unit_volume', 'pallet_scraped_at']);
        });
    }
};
