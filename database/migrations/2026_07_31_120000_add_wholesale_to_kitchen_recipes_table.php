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
            // The POS product sold as a full wholesale batch of this recipe. No
            // foreign key is possible - kitchen_recipes lives on the Laravel
            // connection and PRODUCTS on the POS connection - so the relation
            // has to tolerate a dangling ID.
            $table->string('wholesale_pos_product_id')->nullable()->after('pos_product_id')->index();

            // The margin the wholesale price was set to hit. Ingredient costs
            // move with every delivery, so without this the page can only show
            // today's margin, not that a recipe has slipped below its target.
            $table->decimal('wholesale_target_margin', 5, 2)->nullable()->after('packaging_cost_per_portion');

            $table->timestamp('wholesale_priced_at')->nullable()->after('wholesale_target_margin');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('kitchen_recipes', function (Blueprint $table) {
            $table->dropIndex(['wholesale_pos_product_id']);
            $table->dropColumn([
                'wholesale_pos_product_id',
                'wholesale_target_margin',
                'wholesale_priced_at',
            ]);
        });
    }
};
