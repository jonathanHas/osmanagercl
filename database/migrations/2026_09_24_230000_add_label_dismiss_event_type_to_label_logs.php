<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Allow `label_dismiss` on label_logs.event_type.
 *
 * A dismissal is a product taken off the shelf-label queue without a label being
 * printed. It must be its own event so the office "Recent Label Prints" history
 * keeps showing only real prints.
 *
 * MySQL only: an ENUM has to be redefined with every value it already holds, or
 * rows using an omitted value are truncated to ''. On other drivers the column is
 * already a plain string — see
 * 2026_09_24_220000_widen_label_logs_event_type_on_non_mysql.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement("ALTER TABLE label_logs MODIFY COLUMN event_type ENUM('new_product', 'price_update', 'label_print', 'requeue_label', 'barcode_change', 'label_dismiss')");
    }

    /**
     * Only safe before any dismissal has been written: rolling back with
     * `label_dismiss` rows present truncates them to an empty string, and those
     * products would silently reappear in the queue.
     */
    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement("ALTER TABLE label_logs MODIFY COLUMN event_type ENUM('new_product', 'price_update', 'label_print', 'requeue_label', 'barcode_change')");
    }
};
