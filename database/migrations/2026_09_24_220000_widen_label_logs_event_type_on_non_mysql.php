<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Let non-MySQL databases store every event_type the model defines.
 *
 * The original table used `$table->enum('event_type', ['new_product',
 * 'price_update', 'label_print'])`. On MySQL two later migrations widened that
 * enum to include `requeue_label` and `barcode_change`. Both skip non-MySQL
 * drivers on the stated grounds that "SQLite stores these columns as text, so
 * there is nothing to do" — but Laravel's enum() on SQLite is a varchar plus a
 * CHECK constraint, so the narrower list is still enforced and writing
 * `requeue_label` fails with:
 *
 *   SQLSTATE[23000]: CHECK constraint failed: event_type
 *
 * The app runs MySQL, so this never bit production; the test suite runs SQLite,
 * which is why the label re-queue path had no coverage. Rebuilding the column as
 * a plain string removes the constraint. MySQL is left alone: its enum already
 * lists every value.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            return;
        }

        Schema::table('label_logs', function (Blueprint $table) {
            $table->string('event_type')->change();
        });
    }

    public function down(): void
    {
        // Deliberately not reinstating the constraint: it only ever rejected
        // values the application legitimately writes.
    }
};
