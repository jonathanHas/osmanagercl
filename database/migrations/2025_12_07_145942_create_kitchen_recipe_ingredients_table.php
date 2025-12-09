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
        Schema::create('kitchen_recipe_ingredients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('recipe_id')->constrained('kitchen_recipes')->cascadeOnDelete();
            $table->string('pos_product_id')->comment('Links to POS PRODUCTS.ID - the ingredient');
            $table->decimal('quantity', 10, 4);
            $table->string('unit_type')->comment('kg, g, ml, L, unit, etc.');
            $table->decimal('waste_factor', 5, 2)->default(0)->comment('Percentage waste allowance');
            $table->string('notes')->nullable();
            $table->timestamps();

            $table->index('recipe_id');
            $table->index('pos_product_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('kitchen_recipe_ingredients');
    }
};
