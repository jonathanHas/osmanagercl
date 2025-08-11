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
        Schema::create('invoice_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->onDelete('cascade');
            
            // File information
            $table->string('original_filename'); // Original filename when uploaded
            $table->string('stored_filename'); // Unique filename on disk (UUID-based)
            $table->string('file_path'); // Relative path from storage disk
            $table->string('mime_type'); // application/pdf, image/jpeg, etc.
            $table->unsignedBigInteger('file_size'); // File size in bytes
            $table->string('file_hash')->nullable(); // SHA-256 hash for duplicate detection
            
            // Metadata
            $table->string('description')->nullable(); // User description of the file
            $table->enum('attachment_type', ['invoice_scan', 'receipt', 'delivery_note', 'other'])->default('invoice_scan');
            $table->boolean('is_primary')->default(false); // Mark one attachment as primary
            
            // Audit fields
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('uploaded_at');
            
            // OSAccounts migration tracking
            $table->string('external_osaccounts_path')->nullable()->index(); // Track original OSAccounts path
            
            $table->timestamps();
            
            // Indexes for performance
            $table->index(['invoice_id', 'attachment_type']);
            $table->index(['uploaded_by', 'uploaded_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoice_attachments');
    }
};
