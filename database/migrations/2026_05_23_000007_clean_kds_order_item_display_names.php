<?php

use App\Models\KdsOrderItem;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Backfill: clean any existing display_name values that still contain
        // the raw POS HTML markup (e.g. "<html>Cappuccino", "<html>Pain<br>au<br>Chocolat").
        DB::table('kds_order_items')
            ->where('display_name', 'like', '%<%')
            ->orderBy('id')
            ->chunkById(500, function ($rows) {
                foreach ($rows as $row) {
                    DB::table('kds_order_items')
                        ->where('id', $row->id)
                        ->update(['display_name' => KdsOrderItem::cleanPosDisplay($row->display_name)]);
                }
            });
    }

    public function down(): void
    {
        // Non-reversible cleanup — no original-value snapshot kept.
        // Re-running the polling code against POS would re-derive DISPLAY if needed.
    }
};
