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
        Schema::create('stock_zero_audits', function (Blueprint $table) {
            $table->id();
            $table->string('category_id');
            $table->string('category_name');
            $table->date('reference_date');
            $table->integer('products_zeroed');
            $table->decimal('total_stock_value_zeroed', 10, 2)->default(0);
            $table->json('product_details')->nullable();
            $table->foreignId('user_id')->constrained();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_zero_audits');
    }
};
