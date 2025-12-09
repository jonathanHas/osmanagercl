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
        Schema::table('kitchen_recipe_ingredients', function (Blueprint $table) {
            // Make pos_product_id nullable - ingredient can come from profile without direct product link
            $table->string('pos_product_id')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('kitchen_recipe_ingredients', function (Blueprint $table) {
            $table->string('pos_product_id')->nullable(false)->change();
        });
    }
};
