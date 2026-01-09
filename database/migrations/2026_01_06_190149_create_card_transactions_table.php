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
        Schema::create('card_transactions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('upload_batch_id')->index();
            $table->string('source_filename');
            $table->dateTime('transaction_datetime')->index();
            $table->string('terminal_id')->nullable();
            $table->string('terminal_name')->nullable();
            $table->string('transaction_type'); // Payment, Refund
            $table->string('transaction_reference')->unique();
            $table->string('transaction_status'); // Approved, Declined
            $table->string('payment_status')->nullable(); // Settled, Pending
            $table->string('card_masked')->nullable(); // *5275
            $table->decimal('amount', 10, 2);
            $table->string('currency', 3)->default('EUR');
            $table->dateTime('settlement_datetime')->nullable();
            $table->decimal('settlement_amount', 10, 2)->nullable();
            $table->decimal('fee', 10, 2)->nullable();
            $table->string('processor')->nullable(); // Mastercard, Visa
            $table->string('card_type')->nullable(); // Debit World, Visa Classic
            $table->string('auth_code')->nullable();
            $table->enum('reconciliation_status', ['pending', 'matched', 'mismatch', 'declined', 'orphan'])->default('pending')->index();
            $table->string('pos_payment_id')->nullable()->index(); // FK to POS PAYMENTS.ID
            $table->integer('confidence_score')->nullable();
            $table->decimal('variance_amount', 10, 2)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['transaction_datetime', 'amount']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('card_transactions');
    }
};
