<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kds_products', function (Blueprint $table) {
            $table->enum('trigger_mode', ['primary', 'companion'])
                ->default('primary')
                ->after('is_active')
                ->index();
        });

        // Seed Bakery (POS category 082) as companions, skipping any product
        // already present in the allow-list.
        try {
            $existingIds = DB::table('kds_products')->pluck('product_id')->all();

            $rows = DB::connection('pos')
                ->table('PRODUCTS as p')
                ->leftJoin('CATEGORIES as c', 'p.CATEGORY', '=', 'c.ID')
                ->where('p.CATEGORY', '082')
                ->when(! empty($existingIds), fn ($q) => $q->whereNotIn('p.ID', $existingIds))
                ->select(
                    'p.ID as product_id',
                    'p.NAME as product_name',
                    'p.CATEGORY as category_id',
                    'c.NAME as category_name',
                )
                ->get()
                ->map(fn ($r) => [
                    'product_id' => $r->product_id,
                    'product_name' => $r->product_name,
                    'category_id' => $r->category_id,
                    'category_name' => $r->category_name,
                    'is_active' => true,
                    'trigger_mode' => 'companion',
                    'notes' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ])
                ->all();

            if (! empty($rows)) {
                DB::table('kds_products')->insert($rows);
            }
        } catch (\Throwable $e) {
            // POS unreachable in some environments; bakery can be bulk-added via UI.
        }
    }

    public function down(): void
    {
        // Remove bakery rows seeded by this migration before dropping the column.
        DB::table('kds_products')->where('category_id', '082')->delete();

        Schema::table('kds_products', function (Blueprint $table) {
            $table->dropColumn('trigger_mode');
        });
    }
};
