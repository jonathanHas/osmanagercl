<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Who counted, when, from where, and against which previous float (till count
 * rewrite, 2026-11-07).
 *
 * `counted_at IS NULL` now means "no human count yet": the row exists only because
 * POS totals were pulled. That replaces the old ambiguity where an auto-created row
 * of zero denominations looked like a count of nothing.
 *
 * `previous_float` is the float carried in from the previous counted session, as
 * used in the variance. Storing it makes the variance reproducible instead of
 * depending on whichever row happened to exist at the time it was computed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cash_reconciliations', function (Blueprint $table) {
            $table->foreignId('counted_by')->nullable()->after('updated_by')->constrained('users');
            $table->timestamp('counted_at')->nullable()->after('counted_by');
            $table->string('source', 20)->default('app')->after('counted_at');
            $table->decimal('previous_float', 10, 2)->nullable()->after('source');
            $table->uuid('previous_reconciliation_id')->nullable()->after('previous_float');
        });

        // Every existing row was mirrored from the old system's `money` table.
        DB::table('cash_reconciliations')->update(['source' => 'legacy']);
        DB::table('cash_reconciliations')
            ->where('total_cash_counted', '>', 0)
            ->update(['counted_at' => DB::raw('created_at')]);
    }

    public function down(): void
    {
        Schema::table('cash_reconciliations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('counted_by');
            $table->dropColumn(['counted_at', 'source', 'previous_float', 'previous_reconciliation_id']);
        });
    }
};
