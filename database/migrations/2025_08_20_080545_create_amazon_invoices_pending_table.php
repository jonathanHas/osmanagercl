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
        Schema::create('amazon_invoices_pending', function (Blueprint $table) {
            $table->id();

            // Link to the uploaded file
            $table->foreignId('invoice_upload_file_id')
                ->unique()
                ->constrained('invoice_upload_files')
                ->onDelete('cascade');

            // Batch information from bulk upload
            $table->string('batch_id');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');

            // Invoice metadata extracted from parser
            $table->date('invoice_date')->nullable();
            $table->string('invoice_number')->nullable();
            $table->decimal('gbp_amount', 10, 2)->nullable(); // Amount shown on invoice in GBP

            // Complete parsed data for reference
            $table->json('parsed_data')->nullable();

            // EUR payment information (filled by user)
            $table->decimal('actual_payment_eur', 10, 2)->nullable();
            $table->foreignId('payment_entered_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('payment_entered_at')->nullable();

            // Status tracking
            $table->enum('status', ['pending', 'processing', 'completed', 'cancelled'])
                ->default('pending');

            // Optional notes about exchange rate, discrepancies, etc.
            $table->text('notes')->nullable();

            $table->timestamps();

            // Indexes for performance
            $table->index('status');
            $table->index('user_id');
            $table->index('batch_id');
            $table->index(['status', 'user_id']); // Common query pattern
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('amazon_invoices_pending');
    }
};
