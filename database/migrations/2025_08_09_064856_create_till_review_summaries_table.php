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
        Schema::create('till_review_summaries', function (Blueprint $table) {
            $table->id();
            $table->date('summary_date')->unique();
            $table->decimal('total_sales', 12, 2)->default(0);
            $table->integer('total_transactions')->default(0);
            $table->decimal('cash_total', 12, 2)->default(0);
            $table->decimal('card_total', 12, 2)->default(0);
            $table->decimal('other_total', 12, 2)->default(0);
            $table->json('vat_breakdown')->nullable();
            $table->integer('drawer_opens')->default(0);
            $table->integer('no_sales')->default(0);
            $table->decimal('voided_items_total', 10, 2)->default(0);
            $table->integer('voided_items_count')->default(0);
            $table->json('hourly_breakdown')->nullable();
            $table->json('terminal_breakdown')->nullable();
            $table->json('cashier_breakdown')->nullable();
            $table->timestamps();
            
            $table->index('summary_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('till_review_summaries');
    }
};