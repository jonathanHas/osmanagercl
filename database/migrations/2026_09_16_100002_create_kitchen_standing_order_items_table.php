<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * The standing weekly order is a pre-fill only: quantities (cases) per POS
     * product that populate the create-order page. Keyed by POS product id, not
     * kitchen_products.id, because the kitchen toggle deletes/recreates
     * kitchen_products rows and standing quantities must survive a remove/re-add.
     * No supplier_id — the supplier is derived from the product's current link.
     */
    public function up(): void
    {
        Schema::create('kitchen_standing_order_items', function (Blueprint $table) {
            $table->id();
            $table->string('product_id', 36)->unique();
            $table->unsignedInteger('quantity');
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('kitchen_standing_order_items');
    }
};
