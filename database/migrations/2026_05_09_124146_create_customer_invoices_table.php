<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_invoices', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_number')->nullable()->unique();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->restrictOnDelete();

            // Snapshot fields (preserved even if customer record changes/deletes)
            $table->string('customer_name');
            $table->text('customer_address')->nullable();
            $table->string('customer_vat_number')->nullable();
            $table->string('customer_email')->nullable();

            $table->date('issue_date');
            $table->date('due_date')->nullable();

            // Totals
            $table->decimal('subtotal', 10, 2)->default(0);
            $table->decimal('vat_total', 10, 2)->default(0);
            $table->decimal('total', 10, 2)->default(0);

            // VAT band breakdown (mirrors invoices table)
            $table->decimal('standard_net', 10, 2)->default(0);
            $table->decimal('standard_vat', 10, 2)->default(0);
            $table->decimal('reduced_net', 10, 2)->default(0);
            $table->decimal('reduced_vat', 10, 2)->default(0);
            $table->decimal('second_reduced_net', 10, 2)->default(0);
            $table->decimal('second_reduced_vat', 10, 2)->default(0);
            $table->decimal('zero_net', 10, 2)->default(0);
            $table->decimal('zero_vat', 10, 2)->default(0);

            $table->enum('status', ['draft', 'issued', 'void'])->default('draft');
            $table->text('notes')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('voided_at')->nullable();
            $table->foreignId('voided_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->index('issue_date');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_invoices');
    }
};
