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
        Schema::create('sales_monthly_summary', function (Blueprint $table) {
            $table->id();
            $table->string('product_id')->index();
            $table->string('product_code', 50)->index();
            $table->string('product_name');
            $table->string('category_id', 10)->index();
            $table->year('year')->index();
            $table->tinyInteger('month')->index();
            $table->decimal('total_units', 10, 2)->default(0);
            $table->decimal('total_revenue', 10, 2)->default(0);
            $table->integer('transaction_count')->default(0);
            $table->decimal('avg_price', 8, 2)->default(0);
            $table->tinyInteger('days_with_sales')->default(0);
            $table->timestamps();
            
            // Indexes for fast queries
            $table->index(['year', 'month', 'category_id']);
            $table->index(['product_id', 'year', 'month']);
            $table->index(['category_id', 'year', 'month', 'total_units']);
            $table->unique(['product_id', 'year', 'month']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sales_monthly_summary');
    }
};
