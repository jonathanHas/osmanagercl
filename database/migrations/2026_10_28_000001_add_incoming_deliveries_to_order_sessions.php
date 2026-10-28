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
        // Deliveries an order counted as incoming stock (a late delivery whose
        // invoice arrived before the goods).
        Schema::table('order_sessions', function (Blueprint $table) {
            $table->json('incoming_deliveries')->nullable()->after('christmas_window_config');
        });

        // The order that counted a delivery; it is unticked from "Include in
        // Orders" at that point so it does not carry over to the next order.
        Schema::table('deliveries', function (Blueprint $table) {
            $table->unsignedBigInteger('counted_in_order_session_id')->nullable()->after('include_in_order_stock');
            $table->timestamp('counted_in_order_at')->nullable()->after('counted_in_order_session_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('deliveries', function (Blueprint $table) {
            $table->dropColumn(['counted_in_order_session_id', 'counted_in_order_at']);
        });

        Schema::table('order_sessions', function (Blueprint $table) {
            $table->dropColumn('incoming_deliveries');
        });
    }
};
