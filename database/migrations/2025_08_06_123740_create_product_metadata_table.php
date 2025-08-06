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
        Schema::create('product_metadata', function (Blueprint $table) {
            $table->id();
            $table->string('product_id')->unique()->comment('UUID from POS PRODUCTS.ID');
            $table->string('product_code')->index()->comment('CODE from POS PRODUCTS.CODE for easy lookup');
            $table->unsignedBigInteger('created_by')->nullable()->comment('User who created the product');
            $table->json('metadata')->nullable()->comment('Additional metadata for future extensibility');
            $table->timestamps();

            // Foreign key to users table
            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');

            // Composite index for efficient latest products queries
            $table->index(['created_at', 'product_id'], 'idx_created_product');

            // Index for product code lookups
            $table->index('product_code', 'idx_product_code');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_metadata');
    }
};
