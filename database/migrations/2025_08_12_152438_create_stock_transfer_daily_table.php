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
        Schema::create('stock_transfer_daily', function (Blueprint $table) {
            $table->id();
            $table->date('transfer_date');
            $table->string('department', 50); // Kitchen, Coffee
            $table->decimal('vat_rate', 8, 4); // VAT rate for the transfer
            $table->decimal('net_amount', 12, 2)->default(0); // Net transfer amount
            $table->decimal('vat_amount', 12, 2)->default(0); // VAT amount on transfer
            $table->decimal('gross_amount', 12, 2)->default(0); // Total transfer amount
            $table->integer('transaction_count')->default(0); // Number of transfer transactions
            $table->timestamps();

            // Indexes for fast queries
            $table->index(['transfer_date', 'department'], 'idx_date_department');
            $table->index(['transfer_date', 'vat_rate'], 'idx_date_vat');
            $table->index(['department', 'transfer_date'], 'idx_department_date');
            $table->index('transfer_date', 'idx_transfer_date');

            // Unique constraint to prevent duplicates
            $table->unique(['transfer_date', 'department', 'vat_rate'], 'unique_transfer_dept_vat');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_transfer_daily');
    }
};
