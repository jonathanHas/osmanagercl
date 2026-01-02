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
        Schema::create('stock_valuation_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('snapshot_id')->constrained('stock_valuation_snapshots')->cascadeOnDelete();
            $table->string('category_id');
            $table->string('category_name');
            $table->integer('product_count')->default(0);
            $table->decimal('calculated_value', 12, 2)->default(0);
            $table->decimal('override_value', 12, 2)->nullable();
            $table->string('override_reason')->nullable();
            $table->timestamps();

            $table->unique(['snapshot_id', 'category_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_valuation_categories');
    }
};
