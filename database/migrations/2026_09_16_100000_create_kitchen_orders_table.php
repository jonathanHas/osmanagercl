<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * A kitchen order is one confirmed order to one supplier. Confirming is a
     * single step, so created_at is the order moment. supplier_name is a
     * snapshot so history and CSV re-downloads never change when POS data does.
     */
    public function up(): void
    {
        Schema::create('kitchen_orders', function (Blueprint $table) {
            $table->id();
            // No FK — deleting a user must not delete order history (same as order_sessions).
            $table->unsignedBigInteger('user_id')->nullable()->index();
            // POS suppliers.SupplierID is a string.
            $table->string('supplier_id', 20)->index();
            $table->string('supplier_name', 100);
            $table->text('notes')->nullable();
            $table->unsignedInteger('total_cases')->default(0);
            $table->unsignedInteger('line_count')->default(0);
            $table->timestamps();

            $table->index(['supplier_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('kitchen_orders');
    }
};
