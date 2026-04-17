<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE accounting_suppliers MODIFY COLUMN supplier_type ENUM('product','service','utility','professional','asset','other') NOT NULL DEFAULT 'other'");
    }

    public function down(): void
    {
        DB::statement("UPDATE accounting_suppliers SET supplier_type = 'other' WHERE supplier_type = 'asset'");
        DB::statement("ALTER TABLE accounting_suppliers MODIFY COLUMN supplier_type ENUM('product','service','utility','professional','other') NOT NULL DEFAULT 'other'");
    }
};
