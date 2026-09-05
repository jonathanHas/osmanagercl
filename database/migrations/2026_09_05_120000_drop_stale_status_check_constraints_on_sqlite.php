<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The three "add X status" migrations for the bulk upload tables all skip
 * non-MySQL drivers, on the stated assumption that "SQLite stores these columns
 * as text, so there is nothing to do". That is wrong: Laravel renders
 * $table->enum() on SQLite as a varchar plus a CHECK (status in (...))
 * constraint, frozen with the original value list.
 *
 * The result is that on SQLite — the project's documented default database —
 * every bulk upload fails at the point it sets the batch to 'uploaded':
 *
 *   SQLSTATE[23000]: CHECK constraint failed: status
 *
 * Converting both columns to plain strings drops the stale constraint. Status
 * values are already validated in application code, and MySQL keeps its ENUM.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            return; // the MySQL ENUMs are already widened by the earlier migrations
        }

        Schema::table('invoice_bulk_uploads', function (Blueprint $table) {
            $table->string('status')->default('pending')->change();
        });

        Schema::table('invoice_upload_files', function (Blueprint $table) {
            $table->string('status')->default('pending')->change();
        });
    }

    public function down(): void
    {
        // Deliberately irreversible: restoring the old CHECK constraint would
        // reintroduce the bug, and rows may legitimately hold the newer statuses.
    }
};
