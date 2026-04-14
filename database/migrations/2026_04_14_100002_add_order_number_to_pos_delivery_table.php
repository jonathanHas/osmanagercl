<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::connection('pos')->hasColumn('delivery', 'orderNumber')) {
            DB::connection('pos')->statement('ALTER TABLE delivery ADD COLUMN orderNumber VARCHAR(50) NULL');
        }
    }

    public function down(): void
    {
        if (Schema::connection('pos')->hasColumn('delivery', 'orderNumber')) {
            DB::connection('pos')->statement('ALTER TABLE delivery DROP COLUMN orderNumber');
        }
    }
};
