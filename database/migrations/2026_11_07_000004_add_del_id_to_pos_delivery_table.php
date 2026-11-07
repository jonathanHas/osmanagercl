<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Receiving's invoice-line table on the POS (`delivery`) used to be a global
 * scratch copy of one docket, truncated on every send. Each row now carries
 * the scan session it belongs to (`delID`), so several dockets can be in
 * Receiving at once and an undo always sees the lines it completed with.
 * Rows left with a NULL delID are the pre-change scratch copy; the queries
 * ignore them, so the docket in flight at deploy time needs one re-send.
 */
return new class extends Migration
{
    public function up(): void
    {
        // hasColumn() reports false for a table that does not exist at all, so
        // the table has to be checked first - the test suite builds only the
        // POS tables a given test needs.
        if (Schema::connection('pos')->hasTable('delivery')
            && ! Schema::connection('pos')->hasColumn('delivery', 'delID')) {
            DB::connection('pos')->statement('ALTER TABLE delivery ADD COLUMN delID VARCHAR(64) NULL');
            DB::connection('pos')->statement('CREATE INDEX delivery_delid_index ON delivery (delID)');
        }
    }

    public function down(): void
    {
        if (Schema::connection('pos')->hasTable('delivery')
            && Schema::connection('pos')->hasColumn('delivery', 'delID')) {
            DB::connection('pos')->statement('DROP INDEX delivery_delid_index ON delivery');
            DB::connection('pos')->statement('ALTER TABLE delivery DROP COLUMN delID');
        }
    }
};
