<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kds_order_items', function (Blueprint $table) {
            $table->enum('kind', ['drink', 'bakery'])->default('drink')->after('display_name');
        });

        // Backfill: items whose product is mapped to 'companion' in kds_products
        // are bakery; everything else stays as the default 'drink'.
        // Multi-table UPDATE JOIN is MySQL syntax, and there is nothing to
        // backfill on the empty database the test suite builds.
        if (DB::getDriverName() === 'mysql') {
            DB::statement("
                UPDATE kds_order_items i
                INNER JOIN kds_products p ON p.product_id = i.product_id
                SET i.kind = 'bakery'
                WHERE p.trigger_mode = 'companion'
            ");
        }
    }

    public function down(): void
    {
        Schema::table('kds_order_items', function (Blueprint $table) {
            $table->dropColumn('kind');
        });
    }
};
