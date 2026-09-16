<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Order lines are server-side snapshots: supplier_code, product_name and
     * case_units are copied from POS data at confirm time so the CSV is stable.
     * quantity is whole cases.
     */
    public function up(): void
    {
        Schema::create('kitchen_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kitchen_order_id')->constrained('kitchen_orders')->cascadeOnDelete();
            // POS PRODUCTS.ID (UUID). No FK across databases.
            $table->string('product_id', 36)->index();
            $table->string('supplier_code', 50)->nullable();
            $table->string('product_name', 255);
            $table->unsignedInteger('case_units')->default(1);
            $table->unsignedInteger('quantity');
            $table->timestamps();

            $table->unique(['kitchen_order_id', 'product_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('kitchen_order_items');
    }
};
