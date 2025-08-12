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
        Schema::create('sales_accounting_daily', function (Blueprint $table) {
            $table->id();
            $table->date('sale_date');
            $table->string('payment_type', 50); // cash, magcard, debt, free, etc.
            $table->decimal('vat_rate', 8, 4); // 0.0000, 0.1350, 0.2300, etc.
            $table->decimal('net_amount', 12, 2)->default(0); // Net sales amount
            $table->decimal('vat_amount', 12, 2)->default(0); // VAT amount
            $table->decimal('gross_amount', 12, 2)->default(0); // Total including VAT
            $table->integer('transaction_count')->default(0); // Number of transactions
            $table->timestamps();

            // Indexes for fast queries following optimization pattern
            $table->index(['sale_date', 'payment_type'], 'idx_date_payment');
            $table->index(['sale_date', 'vat_rate'], 'idx_date_vat');
            $table->index(['payment_type', 'vat_rate', 'sale_date'], 'idx_payment_vat_date');
            $table->index('sale_date', 'idx_sale_date');

            // Unique constraint to prevent duplicates
            $table->unique(['sale_date', 'payment_type', 'vat_rate'], 'unique_sale_payment_vat');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sales_accounting_daily');
    }
};
