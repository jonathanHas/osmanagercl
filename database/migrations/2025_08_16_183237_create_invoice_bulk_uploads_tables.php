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
        // Main bulk upload batch table
        Schema::create('invoice_bulk_uploads', function (Blueprint $table) {
            $table->id();
            $table->string('batch_id')->unique();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->integer('total_files')->default(0);
            $table->integer('processed_files')->default(0);
            $table->integer('successful_files')->default(0);
            $table->integer('failed_files')->default(0);
            $table->enum('status', ['pending', 'uploading', 'processing', 'completed', 'failed', 'cancelled'])
                ->default('pending');
            $table->json('metadata')->nullable(); // Store additional info
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index('batch_id');
            $table->index('status');
            $table->index('user_id');
        });

        // Individual file tracking table
        Schema::create('invoice_upload_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bulk_upload_id')->constrained('invoice_bulk_uploads')->onDelete('cascade');
            $table->string('original_filename');
            $table->string('stored_filename')->nullable();
            $table->string('temp_path')->nullable();
            $table->string('mime_type')->nullable();
            $table->bigInteger('file_size')->nullable();
            $table->string('file_hash')->nullable();
            $table->enum('status', [
                'pending',
                'uploading',
                'uploaded',
                'parsing',
                'parsed',
                'review',
                'completed',
                'failed',
                'rejected',
            ])->default('pending');
            $table->json('parsed_data')->nullable(); // Store extracted invoice data
            $table->json('parsing_errors')->nullable(); // Store any parsing errors
            $table->float('parsing_confidence')->nullable(); // OCR/parsing confidence score
            $table->foreignId('invoice_id')->nullable()->constrained()->onDelete('set null'); // Link to created invoice
            $table->text('error_message')->nullable();
            $table->integer('upload_progress')->default(0); // 0-100
            $table->timestamp('uploaded_at')->nullable();
            $table->timestamp('parsed_at')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->index('bulk_upload_id');
            $table->index('status');
            $table->index('invoice_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoice_upload_files');
        Schema::dropIfExists('invoice_bulk_uploads');
    }
};
