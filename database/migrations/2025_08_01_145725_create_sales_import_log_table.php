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
        Schema::create('sales_import_log', function (Blueprint $table) {
            $table->id();
            $table->enum('import_type', ['daily', 'monthly', 'historical', 'incremental'])->index();
            $table->date('start_date')->nullable()->index();
            $table->date('end_date')->nullable()->index();
            $table->integer('records_processed')->default(0);
            $table->integer('records_inserted')->default(0);
            $table->integer('records_updated')->default(0);
            $table->decimal('execution_time_seconds', 8, 2)->nullable();
            $table->enum('status', ['running', 'completed', 'failed'])->default('running')->index();
            $table->text('error_message')->nullable();
            $table->timestamps();

            // Indexes for fast queries
            $table->index(['import_type', 'status']);
            $table->index(['start_date', 'end_date']);
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sales_import_log');
    }
};
