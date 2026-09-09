<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Data fix for duplicate translations left in a mixed auto_print state.
     *
     * The delivery print list used to filter on auto_print *before* picking the newest
     * translation per product_code, so switching off the newest row left an older
     * enabled row still matching — the product kept printing, using stale ZPL.
     * The filter order is fixed in code; this reconciles the existing rows so the
     * older duplicates agree with the intent the user already expressed.
     */
    public function up(): void
    {
        $codes = DB::table('product_translations')
            ->select('product_code')
            ->whereNotNull('product_code')
            ->groupBy('product_code')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('product_code');

        foreach ($codes as $code) {
            $rows = DB::table('product_translations')
                ->where('product_code', $code)
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->get(['id', 'auto_print']);

            $newest = $rows->first();

            // Only act where the newest row is disabled — that is the case where an
            // older enabled row would have resurrected the product.
            if (! $newest || (int) $newest->auto_print === 1) {
                continue;
            }

            $olderIds = $rows->skip(1)->pluck('id')->all();

            if ($olderIds !== []) {
                DB::table('product_translations')
                    ->whereIn('id', $olderIds)
                    ->update(['auto_print' => false]);
            }
        }
    }

    /**
     * Not reversible: the previous per-row auto_print values are not recorded anywhere,
     * and restoring them would reintroduce the stale-ZPL bug.
     */
    public function down(): void
    {
        // Intentionally a no-op.
    }
};
