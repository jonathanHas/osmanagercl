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
            // Density in g/ml for weight↔volume conversions
            // e.g., ground cinnamon = 0.533 g/ml (8g per 15ml tbsp)
            $table->decimal('density', 8, 4)->nullable()->after('base_unit');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('kitchen_ingredient_profiles', function (Blueprint $table) {
            $table->dropColumn('density');
        });
    }
};
