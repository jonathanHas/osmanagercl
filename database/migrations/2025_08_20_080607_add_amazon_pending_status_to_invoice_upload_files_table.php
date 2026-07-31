<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Widening an ENUM is MySQL-only DDL. SQLite (used by the test
        // suite) stores these columns as text, so there is nothing to do.
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        // Add 'amazon_pending' to the status enum
        DB::statement("ALTER TABLE invoice_upload_files MODIFY COLUMN status ENUM(
            'pending',
            'uploading',
            'uploaded',
            'parsing',
            'parsed',
            'review',
            'amazon_pending',
            'completed',
            'failed',
            'rejected'
        ) NOT NULL DEFAULT 'pending'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Widening an ENUM is MySQL-only DDL. SQLite (used by the test
        // suite) stores these columns as text, so there is nothing to do.
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        // Remove 'amazon_pending' from the status enum
        DB::statement("ALTER TABLE invoice_upload_files MODIFY COLUMN status ENUM(
            'pending',
            'uploading',
            'uploaded',
            'parsing',
            'parsed',
            'review',
            'completed',
            'failed',
            'rejected'
        ) NOT NULL DEFAULT 'pending'");
    }
};
