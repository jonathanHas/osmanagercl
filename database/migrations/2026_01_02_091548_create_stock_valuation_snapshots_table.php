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
        Schema::create('stock_valuation_snapshots', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->date('valuation_date');
            $table->enum('status', ['draft', 'finalized'])->default('draft');
            $table->decimal('calculated_total', 12, 2)->default(0);
            $table->decimal('adjusted_total', 12, 2)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->foreignId('finalized_by')->nullable()->constrained('users');
            $table->timestamp('finalized_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_valuation_snapshots');
    }
};
