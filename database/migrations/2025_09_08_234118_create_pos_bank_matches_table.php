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
        Schema::create('pos_bank_matches', function (Blueprint $table) {
            $table->id();
            $table->date('pos_date');
            $table->uuid('bank_transaction_id');
            $table->decimal('matched_amount', 10, 2);
            $table->enum('match_type', ['exact', 'partial', 'combined', 'split'])->default('exact');
            $table->integer('confidence_score')->default(50);
            $table->text('notes')->nullable();
            $table->unsignedInteger('matched_by')->nullable();
            $table->timestamp('matched_at')->nullable();
            $table->timestamps();

            $table->index('pos_date');
            $table->foreign('bank_transaction_id')->references('id')->on('bank_transactions');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pos_bank_matches');
    }
};
