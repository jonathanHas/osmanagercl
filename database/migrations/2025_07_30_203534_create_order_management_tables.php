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
        // Order sessions table
        Schema::create('order_sessions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('supplier_id')->index(); // Links to SUPPLIERS table
            $table->date('order_date');
            $table->enum('status', ['draft', 'submitted', 'completed', 'cancelled'])->default('draft');
            $table->integer('total_items')->default(0);
            $table->decimal('total_value', 10, 2)->default(0.00);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->index(['supplier_id', 'status']);
            $table->index('order_date');
        });

        // Order items table
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('order_session_id');
            $table->string('product_id')->index(); // Links to PRODUCTS table
            $table->decimal('suggested_quantity', 10, 3)->default(0.000);
            $table->decimal('final_quantity', 10, 3)->default(0.000);
            $table->decimal('unit_cost', 10, 2)->default(0.00);
            $table->decimal('total_cost', 10, 2)->default(0.00);
            $table->enum('review_priority', ['safe', 'standard', 'review'])->default('standard');
            $table->text('adjustment_reason')->nullable();
            $table->boolean('auto_approved')->default(false);
            $table->json('context_data')->nullable(); // Store sales data, stock levels, etc.
            $table->timestamps();

            $table->foreign('order_session_id')->references('id')->on('order_sessions')->onDelete('cascade');
            $table->index(['product_id', 'review_priority']);
            $table->index('final_quantity');
        });

        // Order adjustments table for learning
        Schema::create('order_adjustments', function (Blueprint $table) {
            $table->id();
            $table->string('product_id')->index();
            $table->unsignedBigInteger('user_id');
            $table->decimal('original_quantity', 10, 3);
            $table->decimal('adjusted_quantity', 10, 3);
            $table->decimal('adjustment_factor', 5, 4); // e.g., 1.2500 for 25% increase
            $table->json('context_data'); // Stock levels, sales data at time of adjustment
            $table->date('order_date');
            $table->text('reason')->nullable();
            $table->timestamp('created_at');

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->index(['product_id', 'order_date']);
            $table->index('adjustment_factor');
        });

        // Product order settings table
        Schema::create('product_order_settings', function (Blueprint $table) {
            $table->id();
            $table->string('product_id')->unique();
            $table->enum('review_priority', ['safe', 'standard', 'review'])->default('standard');
            $table->boolean('auto_approve')->default(false);
            $table->decimal('safety_stock_factor', 5, 2)->default(1.50); // Default 1.5 weeks
            $table->decimal('min_order_quantity', 10, 3)->nullable();
            $table->decimal('max_order_quantity', 10, 3)->nullable();
            $table->integer('shelf_life_days')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('last_updated');
            $table->timestamps();

            $table->index('review_priority');
            $table->index('auto_approve');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_order_settings');
        Schema::dropIfExists('order_adjustments');
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('order_sessions');
    }
};
