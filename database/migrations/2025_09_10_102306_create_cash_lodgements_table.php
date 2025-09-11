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
        Schema::create('cash_lodgements', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('money_id'); // Links to POS CLOSEDCASH.MONEY
            $table->date('lodgement_date');
            $table->decimal('cash_amount', 10, 2)->default(0);
            $table->decimal('cheque_amount', 10, 2)->default(0);
            $table->decimal('total_amount', 10, 2)->default(0);
            $table->string('till_name')->nullable();
            $table->string('till_id')->nullable();
            $table->enum('lodgement_type', ['cash_only', 'cheque_only', 'mixed'])->default('cash_only');
            $table->text('notes')->nullable();

            // Track import source
            $table->boolean('imported_from_legacy')->default(false);
            $table->timestamp('original_lodge_date')->nullable(); // From lodgeCnt.lDate

            // Matching status
            $table->boolean('is_matched')->default(false);
            $table->uuid('bank_transaction_id')->nullable(); // Link to bank_transactions

            // Audit fields
            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->timestamps();

            // Indexes
            $table->index('money_id');
            $table->index('lodgement_date');
            $table->index('is_matched');
            $table->index(['lodgement_date', 'till_name']);

            // Foreign keys
            $table->foreign('bank_transaction_id')->references('id')->on('bank_transactions')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cash_lodgements');
    }
};
