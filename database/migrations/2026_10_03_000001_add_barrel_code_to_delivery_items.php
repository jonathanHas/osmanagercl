<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('delivery_items', function (Blueprint $table) {
            // Udea per-line deposit (Brl) code, e.g. "313" for a 0.25 bottle deposit.
            $table->string('barrel_code', 20)->nullable()->index()->after('description');
        });
    }

    public function down(): void
    {
        Schema::table('delivery_items', function (Blueprint $table) {
            $table->dropIndex(['barrel_code']);
            $table->dropColumn('barrel_code');
        });
    }
};
