<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Widening an ENUM is MySQL-only DDL. SQLite (used by the test
        // suite) stores these columns as text, so there is nothing to do.
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement("ALTER TABLE accounting_suppliers MODIFY COLUMN supplier_type ENUM('product','service','utility','professional','asset','other') NOT NULL DEFAULT 'other'");
    }

    public function down(): void
    {
        // Widening an ENUM is MySQL-only DDL. SQLite (used by the test
        // suite) stores these columns as text, so there is nothing to do.
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement("UPDATE accounting_suppliers SET supplier_type = 'other' WHERE supplier_type = 'asset'");
        DB::statement("ALTER TABLE accounting_suppliers MODIFY COLUMN supplier_type ENUM('product','service','utility','professional','other') NOT NULL DEFAULT 'other'");
    }
};
