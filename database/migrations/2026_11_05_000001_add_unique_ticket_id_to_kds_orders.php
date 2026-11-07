<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One KDS order per POS ticket, enforced by the database.
     *
     * The importers checked "does this ticket exist?" and then inserted, so
     * two checks running at the same moment (two KDS screens polling, or a
     * poll overlapping the queue job) both inserted and the ticket showed
     * twice. Existing duplicates are removed first, keeping the earliest row.
     */
    public function up(): void
    {
        $duplicates = DB::table('kds_orders')
            ->select('ticket_id', DB::raw('MIN(id) as keep_id'))
            ->groupBy('ticket_id')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        foreach ($duplicates as $duplicate) {
            $extraIds = DB::table('kds_orders')
                ->where('ticket_id', $duplicate->ticket_id)
                ->where('id', '!=', $duplicate->keep_id)
                ->pluck('id');

            // Explicit, rather than relying on the cascade being enforced.
            DB::table('kds_order_items')->whereIn('kds_order_id', $extraIds)->delete();
            DB::table('kds_orders')->whereIn('id', $extraIds)->delete();
        }

        Schema::table('kds_orders', function (Blueprint $table) {
            $table->dropIndex(['ticket_id']);
            $table->unique('ticket_id');
        });
    }

    public function down(): void
    {
        Schema::table('kds_orders', function (Blueprint $table) {
            $table->dropUnique(['ticket_id']);
            $table->index('ticket_id');
        });
    }
};
