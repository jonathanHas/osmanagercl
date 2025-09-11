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
        Schema::create('cash_lodgement_matches', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('cash_lodgement_id');
            $table->uuid('cash_reconciliation_id');
            $table->date('pos_date'); // The POS date being matched
            $table->decimal('matched_amount', 10, 2); // Amount allocated from this POS day
            $table->enum('match_type', ['exact', 'partial', 'accumulated', 'manual'])->default('manual');
            $table->integer('confidence_score')->default(100); // 0-100, manual matches = 100
            $table->text('match_notes')->nullable();

            // Float considerations
            $table->decimal('cash_taken', 10, 2)->default(0); // POS cash total
            $table->decimal('float_retained', 10, 2)->default(0); // Cash kept as float
            $table->decimal('supplier_payments', 10, 2)->default(0); // Cash paid to suppliers
            $table->decimal('available_to_lodge', 10, 2)->default(0); // Cash actually available for lodgement

            // Audit fields
            $table->uuid('matched_by')->nullable();
            $table->timestamp('matched_at')->nullable();
            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->timestamps();

            // Indexes
            $table->index('cash_lodgement_id');
            $table->index('cash_reconciliation_id');
            $table->index('pos_date');
            $table->index(['pos_date', 'match_type']);

            // Foreign keys
            $table->foreign('cash_lodgement_id')->references('id')->on('cash_lodgements')->onDelete('cascade');
            $table->foreign('cash_reconciliation_id')->references('id')->on('cash_reconciliations')->onDelete('cascade');

            // Prevent duplicate matches for the same reconciliation
            $table->unique(['cash_reconciliation_id', 'cash_lodgement_id'], 'unique_reconciliation_lodgement_match');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cash_lodgement_matches');
    }
};
