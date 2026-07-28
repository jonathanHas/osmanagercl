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
        Schema::create('cost_price_adjustments', function (Blueprint $table) {
            $table->id();
            $table->string('product_id');
            $table->string('product_code')->nullable();
            $table->string('product_name')->nullable();
            // PRICEBUY lives in the POS database, which keeps no history of its
            // own. Recording both sides here is the only way back if a wrong
            // divisor is applied.
            $table->decimal('old_cost', 10, 4);
            $table->decimal('new_cost', 10, 4);
            $table->decimal('sell_price', 10, 4)->nullable();
            $table->decimal('divisor', 10, 4)->nullable();
            $table->string('source')->default('valuation_anomaly');
            $table->foreignId('user_id')->constrained();
            $table->timestamps();

            $table->index('product_id');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cost_price_adjustments');
    }
};
