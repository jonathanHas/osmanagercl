<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE kds_products MODIFY COLUMN trigger_mode ENUM('primary','companion','excluder') NOT NULL DEFAULT 'primary'");

        // Seed the Served Already marker product as an excluder.
        try {
            $row = DB::connection('pos')
                ->table('PRODUCTS as p')
                ->leftJoin('CATEGORIES as c', 'p.CATEGORY', '=', 'c.ID')
                ->where('p.ID', 'eb8b7ea6-9bab-431d-8a94-ef25c97fb501')
                ->select(
                    'p.ID as product_id',
                    'p.NAME as product_name',
                    'p.CATEGORY as category_id',
                    'c.NAME as category_name',
                )
                ->first();

            if ($row && ! DB::table('kds_products')->where('product_id', $row->product_id)->exists()) {
                DB::table('kds_products')->insert([
                    'product_id' => $row->product_id,
                    'product_name' => $row->product_name,
                    'category_id' => $row->category_id,
                    'category_name' => $row->category_name,
                    'is_active' => true,
                    'trigger_mode' => 'excluder',
                    'notes' => 'Marker: suppresses KDS entry on tickets where the drink was already served.',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        } catch (\Throwable $e) {
            // POS unreachable; user can add the marker via the /kds/products UI.
        }
    }

    public function down(): void
    {
        DB::table('kds_products')->where('trigger_mode', 'excluder')->delete();
        DB::statement("ALTER TABLE kds_products MODIFY COLUMN trigger_mode ENUM('primary','companion') NOT NULL DEFAULT 'primary'");
    }
};
