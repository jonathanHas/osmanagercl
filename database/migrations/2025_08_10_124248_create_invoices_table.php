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
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_number', 100);
            $table->unsignedBigInteger('supplier_id')->nullable();
            $table->string('supplier_name', 255);
            $table->date('invoice_date');
            $table->date('due_date')->nullable();
            
            // Financial totals (stored for performance)
            $table->decimal('subtotal', 10, 2)->default(0);
            $table->decimal('vat_amount', 10, 2)->default(0);
            $table->decimal('total_amount', 10, 2)->default(0);
            
            // Payment tracking
            $table->enum('payment_status', ['pending', 'partial', 'paid', 'overdue', 'cancelled'])->default('pending');
            $table->date('payment_date')->nullable();
            $table->string('payment_method', 50)->nullable();
            $table->string('payment_reference', 100)->nullable();
            
            // Categorization
            $table->string('expense_category', 50)->nullable();
            $table->string('cost_center', 50)->nullable();
            
            // Metadata
            $table->text('notes')->nullable();
            $table->string('attachment_path', 500)->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            
            $table->foreign('supplier_id')->references('id')->on('accounting_suppliers');
            $table->foreign('created_by')->references('id')->on('users');
            $table->foreign('updated_by')->references('id')->on('users');
            
            $table->index('supplier_id');
            $table->index('invoice_date');
            $table->index('payment_status');
            $table->index('expense_category');
            $table->unique(['invoice_number', 'supplier_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};