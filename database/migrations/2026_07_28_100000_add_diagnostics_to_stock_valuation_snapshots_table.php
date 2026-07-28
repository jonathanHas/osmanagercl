<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('stock_valuation_snapshots', function (Blueprint $table) {
            // Records what was excluded at the time the valuation was taken --
            // negative stock lines and cost price anomalies -- so a finalized
            // snapshot stays defensible for accounts after the data moves on.
            $table->json('diagnostics')->nullable()->after('adjusted_total');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stock_valuation_snapshots', function (Blueprint $table) {
            $table->dropColumn('diagnostics');
        });
    }
};
