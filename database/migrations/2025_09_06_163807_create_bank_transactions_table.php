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
        Schema::create('bank_transactions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->date('transaction_date');
            $table->text('description');
            $table->decimal('debit_amount', 10, 2)->default(0);
            $table->decimal('credit_amount', 10, 2)->default(0);
            $table->decimal('balance', 10, 2)->nullable();
            $table->string('source_filename');
            $table->string('status')->default('unmatched'); // statuses: unmatched, pending_review, matched, partially_matched, ignored, disputed
            $table->string('reconciliation_type')->nullable(); // e.g., supplier_payment, bank_fee, other_expense
            $table->uuid('reconciliation_id')->nullable();
            $table->string('reconciliation_model')->nullable();
            $table->timestamp('reconciled_at')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('set null');
            $table->timestamps();

            $table->index('transaction_date');
            $table->index('status');
            $table->index('reconciliation_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bank_transactions');
    }
};
