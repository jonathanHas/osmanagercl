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
        Schema::table('order_sessions', function (Blueprint $table) {
            $table->boolean('christmas_comparison_enabled')->default(false)->after('sales_history_weeks');
            $table->json('christmas_window_config')->nullable()->after('christmas_comparison_enabled');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('order_sessions', function (Blueprint $table) {
            $table->dropColumn(['christmas_comparison_enabled', 'christmas_window_config']);
        });
    }
};
