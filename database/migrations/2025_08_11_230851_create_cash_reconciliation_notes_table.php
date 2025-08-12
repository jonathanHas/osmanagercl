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
        Schema::create('cash_reconciliation_notes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('cash_reconciliation_id');
            $table->foreign('cash_reconciliation_id')->references('id')->on('cash_reconciliations')->onDelete('cascade');
            $table->text('message');
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();

            // Index
            $table->index('cash_reconciliation_id', 'cr_notes_reconciliation_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cash_reconciliation_notes');
    }
};
