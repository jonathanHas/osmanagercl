<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Requests cycle 3: a request line learns that its product has arrived.
 *
 * Receiving writes every scanned barcode to the POS deliveriesScanItems table,
 * which is indexed by session only, so the board never searches it. Instead the
 * scan endpoints stamp the open request lines for the barcode at scan time:
 * when, how many units the session has for it, and which session.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer_request_items', function (Blueprint $table) {
            $table->timestamp('arrived_at')->nullable()->after('hold_for_collection');
            $table->decimal('arrived_quantity', 10, 3)->nullable()->after('arrived_at');
            // POS deliveriesScan.ID; crosses databases, so no foreign key.
            $table->string('arrived_session_id', 64)->nullable()->after('arrived_quantity');
        });
    }

    public function down(): void
    {
        Schema::table('customer_request_items', function (Blueprint $table) {
            $table->dropColumn(['arrived_at', 'arrived_quantity', 'arrived_session_id']);
        });
    }
};
