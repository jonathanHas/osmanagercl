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
        // For MySQL, we need to modify the enum column
        DB::statement("ALTER TABLE invoice_bulk_uploads MODIFY COLUMN status ENUM('pending', 'uploading', 'uploaded', 'processing', 'completed', 'failed', 'cancelled') DEFAULT 'pending'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert to original enum values
        DB::statement("ALTER TABLE invoice_bulk_uploads MODIFY COLUMN status ENUM('pending', 'uploading', 'processing', 'completed', 'failed', 'cancelled') DEFAULT 'pending'");
    }
};
