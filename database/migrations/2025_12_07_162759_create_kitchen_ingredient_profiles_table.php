<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('kitchen_ingredient_profiles', function (Blueprint $table) {
            $table->id();
            $table->string('pos_product_id')->unique()->comment('Links to POS PRODUCTS.ID');
            $table->string('name')->comment('Display name (auto-filled from product, editable)');
            $table->decimal('purchase_quantity', 10, 4)->comment('e.g., 25 for a 25kg bag');
            $table->string('purchase_unit')->comment('kg, g, L, ml, unit, dozen, pack');
            $table->decimal('cost_per_base_unit', 10, 6)->default(0)->comment('Calculated: SupplierLink.Cost / purchase_quantity in base units');
            $table->string('base_unit')->comment('g, ml, or unit (normalized base)');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('pos_product_id');
            $table->index('name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('kitchen_ingredient_profiles');
    }
};
