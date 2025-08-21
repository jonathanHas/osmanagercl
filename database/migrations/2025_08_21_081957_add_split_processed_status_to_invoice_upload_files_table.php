<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Add 'split_processed' to the status enum
        DB::statement("ALTER TABLE invoice_upload_files MODIFY COLUMN status ENUM('pending', 'uploading', 'uploaded', 'parsing', 'parsed', 'review', 'completed', 'failed', 'rejected', 'amazon_pending', 'split_processed') NOT NULL DEFAULT 'pending'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove 'split_processed' from the status enum
        DB::statement("ALTER TABLE invoice_upload_files MODIFY COLUMN status ENUM('pending', 'uploading', 'uploaded', 'parsing', 'parsed', 'review', 'completed', 'failed', 'rejected', 'amazon_pending') NOT NULL DEFAULT 'pending'");
    }
};
