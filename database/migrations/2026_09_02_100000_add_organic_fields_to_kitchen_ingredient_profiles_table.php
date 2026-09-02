<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Organic Trust requires a Multi-Ingredient Product Registration Form for every product produced
 * on site, listing each input's weight and whether it is organic.
 *
 * Supplier-level `is_organic` is too coarse: an organic wholesaler still sells non-organic lines
 * (salt, baking powder), so organic status is recorded per ingredient and defaults to organic.
 *
 * `unit_weight_grams` closes the last gap in the form's "% Weight Of Overall Product" column —
 * weight units convert directly and volume units convert via `density`, but count units ("2 eggs")
 * have no weight source at all.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kitchen_ingredient_profiles', function (Blueprint $table) {
            $table->string('organic_status', 20)
                ->default('organic')
                ->after('density')
                ->comment('organic|non_organic - printed on the Organic Trust registration form');

            $table->decimal('unit_weight_grams', 10, 3)
                ->nullable()
                ->after('organic_status')
                ->comment('Weight of one countable unit in grams; only needed for count-unit profiles');
        });
    }

    public function down(): void
    {
        Schema::table('kitchen_ingredient_profiles', function (Blueprint $table) {
            $table->dropColumn(['organic_status', 'unit_weight_grams']);
        });
    }
};
