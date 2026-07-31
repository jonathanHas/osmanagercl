<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // hasColumn() reports false for a table that does not exist at all, so
        // the table has to be checked first - the test suite builds only the
        // POS tables a given test needs.
        if (Schema::connection('pos')->hasTable('delivery')
            && ! Schema::connection('pos')->hasColumn('delivery', 'orderNumber')) {
            DB::connection('pos')->statement('ALTER TABLE delivery ADD COLUMN orderNumber VARCHAR(50) NULL');
        }
    }

    public function down(): void
    {
        if (Schema::connection('pos')->hasTable('delivery')
            && Schema::connection('pos')->hasColumn('delivery', 'orderNumber')) {
            DB::connection('pos')->statement('ALTER TABLE delivery DROP COLUMN orderNumber');
        }
    }
};
