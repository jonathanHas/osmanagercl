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
        Schema::table('kitchen_ingredient_profiles', function (Blueprint $table) {
            // Drop the unique constraint on pos_product_id
            $table->dropUnique(['pos_product_id']);
        });

        Schema::table('kitchen_ingredient_profiles', function (Blueprint $table) {
            // Make pos_product_id nullable
            $table->string('pos_product_id')->nullable()->change();

            // Add manual_cost field for profiles without linked products
            $table->decimal('manual_cost', 10, 2)->nullable()->after('pos_product_id')
                ->comment('Manual supplier cost when no POS product linked');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('kitchen_ingredient_profiles', function (Blueprint $table) {
            $table->dropColumn('manual_cost');
        });

        Schema::table('kitchen_ingredient_profiles', function (Blueprint $table) {
            // Restore non-nullable and unique constraint
            $table->string('pos_product_id')->nullable(false)->change();
            $table->unique('pos_product_id');
        });
    }
};
