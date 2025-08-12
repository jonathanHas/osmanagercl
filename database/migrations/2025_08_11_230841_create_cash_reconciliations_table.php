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
        Schema::create('cash_reconciliations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('closed_cash_id'); // Links to POS CLOSEDCASH.MONEY
            $table->date('date');
            $table->string('till_name');
            $table->integer('till_id');

            // Cash denominations
            $table->integer('cash_50')->default(0);
            $table->integer('cash_20')->default(0);
            $table->integer('cash_10')->default(0);
            $table->integer('cash_5')->default(0);
            $table->integer('cash_2')->default(0);
            $table->integer('cash_1')->default(0);
            $table->integer('cash_50c')->default(0);
            $table->integer('cash_20c')->default(0);
            $table->integer('cash_10c')->default(0);

            // Float management
            $table->decimal('note_float', 10, 2)->default(0);
            $table->decimal('coin_float', 10, 2)->default(0);

            // Payment types
            $table->decimal('card', 10, 2)->default(0);
            $table->decimal('cash_back', 10, 2)->default(0);
            $table->decimal('cheque', 10, 2)->default(0);
            $table->decimal('debt', 10, 2)->default(0);
            $table->decimal('debt_paid_cash', 10, 2)->default(0);
            $table->decimal('debt_paid_cheque', 10, 2)->default(0);
            $table->decimal('debt_paid_card', 10, 2)->default(0);
            $table->decimal('free', 10, 2)->default(0);
            $table->decimal('voucher_used', 10, 2)->default(0);
            $table->decimal('money_added', 10, 2)->default(0);

            // Calculated totals
            $table->decimal('total_cash_counted', 10, 2)->default(0);
            $table->decimal('pos_cash_total', 10, 2)->default(0);
            $table->decimal('pos_card_total', 10, 2)->default(0);
            $table->decimal('variance', 10, 2)->default(0);

            // Metadata
            $table->foreignId('created_by')->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users');
            $table->timestamps();

            // Indexes
            $table->index(['date', 'till_id']);
            $table->unique(['closed_cash_id']);
            $table->index('date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cash_reconciliations');
    }
};
