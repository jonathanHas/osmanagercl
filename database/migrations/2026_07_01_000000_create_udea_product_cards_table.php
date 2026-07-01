<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Persistent cache of parsed Udea webshop-card data (case / single-unit buy tiers),
     * keyed by supplier_code. Populated lazily on scrape (write-through) so order pages
     * read instantly and only missing/stale (>30 day) products are re-scraped.
     */
    public function up(): void
    {
        Schema::create('udea_product_cards', function (Blueprint $table) {
            $table->id();
            $table->string('supplier_code', 50)->unique();
            $table->unsignedInteger('supplier_id')->nullable();
            $table->unsignedInteger('case_qty')->nullable();
            $table->boolean('single_unit_available')->default(false);
            $table->string('single_unit_price', 20)->nullable();
            $table->string('per_unit_case_price', 20)->nullable();
            $table->string('case_price', 20)->nullable();
            $table->string('unit_price', 20)->nullable();
            $table->unsignedInteger('units_per_case')->nullable();
            $table->string('description', 255)->nullable();
            $table->json('purchase_tiers')->nullable();
            $table->boolean('not_found')->default(false);
            $table->timestamp('scraped_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('udea_product_cards');
    }
};
