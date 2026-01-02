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
        Schema::create('stock_valuation_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('snapshot_id')->constrained('stock_valuation_snapshots')->cascadeOnDelete();
            $table->foreignId('category_record_id')->constrained('stock_valuation_categories')->cascadeOnDelete();
            $table->string('product_id');
            $table->string('product_code');
            $table->string('product_name');
            $table->decimal('unit_cost', 10, 4);
            $table->decimal('stock_units', 10, 2);
            $table->decimal('line_value', 12, 2);
            $table->timestamps();

            $table->index(['snapshot_id', 'category_record_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_valuation_items');
    }
};
