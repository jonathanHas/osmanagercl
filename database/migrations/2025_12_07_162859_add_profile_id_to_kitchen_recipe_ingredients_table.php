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
            $table->foreignId('ingredient_profile_id')
                ->nullable()
                ->after('recipe_id')
                ->constrained('kitchen_ingredient_profiles')
                ->nullOnDelete();

            $table->index('ingredient_profile_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('kitchen_recipe_ingredients', function (Blueprint $table) {
            $table->dropForeign(['ingredient_profile_id']);
            $table->dropColumn('ingredient_profile_id');
        });
    }
};
