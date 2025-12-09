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
        Schema::create('kitchen_recipe_cost_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('recipe_id')->constrained('kitchen_recipes')->cascadeOnDelete();
            $table->decimal('total_cost', 10, 2);
            $table->decimal('cost_per_portion', 10, 2);
            $table->decimal('sell_price', 10, 2)->nullable();
            $table->decimal('margin_percentage', 5, 2)->nullable();
            $table->timestamp('recorded_at');

            $table->index(['recipe_id', 'recorded_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('kitchen_recipe_cost_history');
    }
};
