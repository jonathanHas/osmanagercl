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
        Schema::create('vat_returns', function (Blueprint $table) {
            $table->id();
            $table->string('return_period', 100); // e.g., "2025-Q1" or "2025-01"
            $table->date('period_start');
            $table->date('period_end');
            $table->enum('status', ['draft', 'finalized', 'submitted', 'paid'])->default('draft');

            // Summary totals (calculated and stored for performance)
            $table->decimal('total_net', 12, 2)->default(0);
            $table->decimal('total_vat', 12, 2)->default(0);
            $table->decimal('total_gross', 12, 2)->default(0);

            // VAT breakdown by rate
            $table->decimal('zero_net', 12, 2)->default(0);
            $table->decimal('zero_vat', 12, 2)->default(0);
            $table->decimal('second_reduced_net', 12, 2)->default(0); // 9%
            $table->decimal('second_reduced_vat', 12, 2)->default(0);
            $table->decimal('reduced_net', 12, 2)->default(0); // 13.5%
            $table->decimal('reduced_vat', 12, 2)->default(0);
            $table->decimal('standard_net', 12, 2)->default(0); // 23%
            $table->decimal('standard_vat', 12, 2)->default(0);

            // Metadata
            $table->text('notes')->nullable();
            $table->date('submitted_date')->nullable();
            $table->string('reference_number')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('finalized_by')->nullable();
            $table->timestamps();

            $table->foreign('created_by')->references('id')->on('users');
            $table->foreign('finalized_by')->references('id')->on('users');

            $table->unique('return_period');
            $table->index('status');
            $table->index('period_end');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vat_returns');
    }
};
