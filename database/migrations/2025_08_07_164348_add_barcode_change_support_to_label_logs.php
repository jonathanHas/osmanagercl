<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // First, modify the enum to include 'barcode_change' and keep 'requeue_label'.
        // MySQL-only DDL. SQLite: Laravel's enum() adds a CHECK constraint, so this
        // widening is needed there too; it is done by
        // 2026_09_24_220000_widen_label_logs_event_type_on_non_mysql.
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE label_logs MODIFY COLUMN event_type ENUM('new_product', 'price_update', 'label_print', 'requeue_label', 'barcode_change')");
        }

        // Add metadata column for storing additional information
        Schema::table('label_logs', function (Blueprint $table) {
            $table->json('metadata')->nullable()->after('event_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove metadata column
        Schema::table('label_logs', function (Blueprint $table) {
            $table->dropColumn('metadata');
        });

        // Revert enum back to original values (including requeue_label which was added later)
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE label_logs MODIFY COLUMN event_type ENUM('new_product', 'price_update', 'label_print', 'requeue_label')");
        }
    }
};
