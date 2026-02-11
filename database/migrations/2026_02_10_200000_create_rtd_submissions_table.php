<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rtd_submissions', function (Blueprint $table) {
            $table->id();
            $table->date('period_start');
            $table->date('period_end');
            $table->string('status', 20)->default('draft'); // draft, submitted
            $table->date('submitted_date')->nullable();
            $table->string('reference_number', 100)->nullable();
            $table->text('notes')->nullable();
            $table->json('totals_snapshot')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users');
            $table->timestamps();

            $table->index('status');
            $table->index(['period_start', 'period_end']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rtd_submissions');
    }
};
