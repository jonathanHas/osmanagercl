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
        Schema::create('cash_reconciliation_payments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('cash_reconciliation_id');
            $table->foreign('cash_reconciliation_id')->references('id')->on('cash_reconciliations')->onDelete('cascade');
            $table->string('supplier_id')->nullable(); // References POS suppliers.SupplierID
            $table->string('payee_name')->nullable(); // For non-supplier payments or supplier name
            $table->decimal('amount', 10, 2);
            $table->integer('sequence')->default(0);
            $table->text('description')->nullable();
            $table->timestamps();
            
            // Indexes
            $table->index(['cash_reconciliation_id', 'sequence'], 'cr_payments_reconciliation_seq');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cash_reconciliation_payments');
    }
};
