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
        Schema::create('pos_daily_summaries', function (Blueprint $table) {
            $table->id();
            $table->date('sale_date')->unique();
            $table->decimal('cash_sales', 10, 2)->default(0);
            $table->decimal('cash_refunds', 10, 2)->default(0);
            $table->decimal('card_sales', 10, 2)->default(0);
            $table->decimal('card_refunds', 10, 2)->default(0);
            $table->decimal('debt_sales', 10, 2)->default(0);
            $table->decimal('free_sales', 10, 2)->default(0);
            $table->integer('total_transactions')->default(0);
            $table->decimal('till_count_variance', 10, 2)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('sale_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pos_daily_summaries');
    }
};
