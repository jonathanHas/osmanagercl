<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A docket (/deliveries) is received through one Receiving scan session
 * (POS `deliveriesScan.ID`). The link lets Receiving's Complete mark the
 * docket completed and fill its received quantities, and replaces the single
 * `app_settings.legacy_synced_delivery_id` pointer. No foreign key: the
 * session lives on the POS database.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('deliveries', function (Blueprint $table) {
            $table->string('scan_session_id', 64)->nullable()->after('status')->index();
            $table->timestamp('received_at')->nullable()->after('scan_session_id');
        });
    }

    public function down(): void
    {
        Schema::table('deliveries', function (Blueprint $table) {
            $table->dropIndex(['scan_session_id']);
            $table->dropColumn(['scan_session_id', 'received_at']);
        });
    }
};
