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
        Schema::create('sales_daily_summary', function (Blueprint $table) {
            $table->id();
            $table->string('product_id')->index();
            $table->string('product_code', 50)->index();
            $table->string('product_name');
            $table->string('category_id', 10)->index();
            $table->date('sale_date')->index();
            $table->decimal('total_units', 10, 2)->default(0);
            $table->decimal('total_revenue', 10, 2)->default(0);
            $table->integer('transaction_count')->default(0);
            $table->decimal('avg_price', 8, 2)->default(0);
            $table->timestamps();
            
            // Indexes for fast queries
            $table->index(['sale_date', 'category_id']);
            $table->index(['product_id', 'sale_date']);
            $table->index(['product_code', 'sale_date']);
            $table->index(['category_id', 'sale_date', 'total_units']);
            $table->index(['sale_date', 'total_revenue']);
            $table->unique(['product_id', 'sale_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sales_daily_summary');
    }
};
