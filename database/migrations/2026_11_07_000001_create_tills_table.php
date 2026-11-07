<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A stable identity for each till (till count rewrite, 2026-11-07).
 *
 * Until now `till_id` was the position of the POS HOST in an alphabetical list of
 * the hosts seen in the last six months, cached for five minutes. That number moved
 * whenever a host appeared or dropped out, and six reconciliations already carried
 * `till_id = 6` for both tills. This table gives each POS HOST a fixed id.
 *
 * The seed numbers the hosts already present in `cash_reconciliations` in
 * alphabetical order, which reproduces the ids most rows already have (OrgStore=1,
 * Till 2=2 on the live database), then rewrites `till_id` by name everywhere the
 * positional number was stored.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tills', function (Blueprint $table) {
            $table->id();
            $table->string('pos_host')->unique(); // CLOSEDCASH.HOST
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        $hosts = DB::table('cash_reconciliations')
            ->whereNotNull('till_name')
            ->distinct()
            ->orderBy('till_name')
            ->pluck('till_name');

        foreach ($hosts as $i => $host) {
            $id = DB::table('tills')->insertGetId([
                'pos_host' => $host,
                'name' => $host,
                'is_active' => true,
                'sort_order' => $i + 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            foreach (['cash_reconciliations', 'customer_payments', 'cash_lodgements'] as $table) {
                if (Schema::hasTable($table)) {
                    DB::table($table)->where('till_name', $host)->update(['till_id' => $id]);
                }
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('tills');
    }
};
