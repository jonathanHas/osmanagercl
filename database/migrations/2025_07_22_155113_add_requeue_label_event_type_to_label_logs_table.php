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
        // Widening an ENUM is MySQL-only DDL. SQLite: Laravel's enum() adds a
        // CHECK constraint, so this widening is needed there too; it is done by
        // 2026_09_24_220000_widen_label_logs_event_type_on_non_mysql.
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        // For MySQL, we need to alter the enum column to add the new value
        DB::statement("ALTER TABLE label_logs MODIFY COLUMN event_type ENUM('new_product', 'price_update', 'label_print', 'requeue_label')");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Widening an ENUM is MySQL-only DDL. SQLite: Laravel's enum() adds a
        // CHECK constraint, so this widening is needed there too; it is done by
        // 2026_09_24_220000_widen_label_logs_event_type_on_non_mysql.
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        // Remove the requeue_label from the enum
        DB::statement("ALTER TABLE label_logs MODIFY COLUMN event_type ENUM('new_product', 'price_update', 'label_print')");
    }
};
