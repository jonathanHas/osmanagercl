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
            $table->integer('coverage_days')->default(7)->after('order_date');
            $table->date('coverage_ends_on')->nullable()->after('coverage_days');
            $table->unsignedTinyInteger('sales_history_weeks')->default(8)->after('coverage_ends_on');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('order_sessions', function (Blueprint $table) {
            $table->dropColumn(['coverage_days', 'coverage_ends_on', 'sales_history_weeks']);
        });
    }
};
