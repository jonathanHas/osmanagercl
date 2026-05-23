<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kds_orders', function (Blueprint $table) {
            $table->string('person_name')->nullable()->after('person');
        });

        // Backfill historical rows from POS PEOPLE.
        try {
            $pairs = DB::connection('pos')->table('PEOPLE')->pluck('NAME', 'ID');
            foreach ($pairs as $id => $name) {
                DB::table('kds_orders')
                    ->where('person', (string) $id)
                    ->whereNull('person_name')
                    ->update(['person_name' => $name]);
            }
        } catch (\Throwable $e) {
            // POS unreachable; column stays null on historical rows.
        }
    }

    public function down(): void
    {
        Schema::table('kds_orders', function (Blueprint $table) {
            $table->dropColumn('person_name');
        });
    }
};
