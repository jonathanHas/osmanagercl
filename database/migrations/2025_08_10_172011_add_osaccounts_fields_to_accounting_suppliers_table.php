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
        Schema::table('accounting_suppliers', function (Blueprint $table) {
            // OSAccounts integration fields
            $table->string('external_osaccounts_id')->nullable()->after('external_pos_id')->index();
            $table->boolean('is_osaccounts_linked')->default(false)->after('is_pos_linked');
            $table->timestamp('osaccounts_last_sync')->nullable()->after('is_osaccounts_linked');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('accounting_suppliers', function (Blueprint $table) {
            $table->dropColumn([
                'external_osaccounts_id',
                'is_osaccounts_linked',
                'osaccounts_last_sync',
            ]);
        });
    }
};
