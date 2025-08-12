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
        Schema::create('invoice_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('invoice_id');

            // Item details
            $table->string('description', 500);
            $table->decimal('quantity', 10, 3)->default(1);
            $table->decimal('unit_price', 10, 4);

            // VAT handling (stores actual rate at time of invoice)
            $table->string('vat_code', 20);
            $table->decimal('vat_rate', 5, 4);
            $table->decimal('vat_amount', 10, 2);

            // Totals
            $table->decimal('net_amount', 10, 2);
            $table->decimal('gross_amount', 10, 2);

            // Categorization
            $table->string('expense_category', 50)->nullable();
            $table->string('cost_center', 50)->nullable();

            // GL coding (future expansion)
            $table->string('gl_code', 20)->nullable();
            $table->string('department', 50)->nullable();

            $table->timestamps();

            $table->foreign('invoice_id')->references('id')->on('invoices')->onDelete('cascade');
            $table->index('invoice_id');
            $table->index('vat_code');
            $table->index('expense_category');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoice_items');
    }
};
