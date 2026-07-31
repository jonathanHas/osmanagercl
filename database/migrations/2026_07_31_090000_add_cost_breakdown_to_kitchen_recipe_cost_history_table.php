<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * The history table only stored totals, so there was no way to see how a
     * recipe's cost was composed at the time it was recorded - or to chart
     * labour against energy over time. These columns are nullable because
     * rows recorded before this migration cannot be broken down after the
     * fact; a null means "not captured", not zero.
     */
    public function up(): void
    {
        Schema::table('kitchen_recipe_cost_history', function (Blueprint $table) {
            $table->decimal('ingredient_cost', 10, 2)->nullable()->after('recipe_id');
            $table->decimal('labour_cost', 10, 2)->nullable()->after('ingredient_cost');
            $table->decimal('labour_minutes', 8, 1)->nullable()->after('labour_cost');
            $table->decimal('electricity_cost', 10, 2)->nullable()->after('labour_minutes');
            $table->decimal('packaging_cost', 10, 2)->nullable()->after('electricity_cost');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('kitchen_recipe_cost_history', function (Blueprint $table) {
            $table->dropColumn([
                'ingredient_cost',
                'labour_cost',
                'labour_minutes',
                'electricity_cost',
                'packaging_cost',
            ]);
        });
    }
};
