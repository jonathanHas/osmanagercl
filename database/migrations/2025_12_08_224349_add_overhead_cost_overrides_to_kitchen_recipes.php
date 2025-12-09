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
        Schema::table('kitchen_recipes', function (Blueprint $table) {
            // Per-recipe rate overrides (null = use global defaults from config)
            $table->decimal('labour_rate_override', 8, 2)->nullable()->after('notes');
            $table->decimal('electricity_rate_override', 8, 4)->nullable()->after('labour_rate_override');
            $table->decimal('cooking_power_override', 8, 2)->nullable()->after('electricity_rate_override');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('kitchen_recipes', function (Blueprint $table) {
            $table->dropColumn([
                'labour_rate_override',
                'electricity_rate_override',
                'cooking_power_override',
            ]);
        });
    }
};
