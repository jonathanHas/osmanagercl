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
        Schema::create('bank_transaction_allocations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('bank_transaction_id');
            $table->unsignedBigInteger('invoice_id');
            $table->decimal('allocated_amount', 10, 2);
            $table->string('allocation_type', 50)->default('standard'); // standard, partial, overpayment, discount, fee
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();

            // Foreign keys
            $table->foreign('bank_transaction_id')
                ->references('id')
                ->on('bank_transactions')
                ->onDelete('cascade');

            $table->foreign('invoice_id')
                ->references('id')
                ->on('invoices')
                ->onDelete('restrict');

            // Indexes for performance
            $table->index('bank_transaction_id', 'bta_transaction_idx');
            $table->index('invoice_id', 'bta_invoice_idx');
            $table->index(['bank_transaction_id', 'invoice_id'], 'bta_transaction_invoice_idx');
            $table->index('allocation_type', 'bta_type_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bank_transaction_allocations');
    }
};
