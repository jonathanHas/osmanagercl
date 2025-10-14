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
            if (! Schema::hasColumn('order_sessions', 'coverage_overrides')) {
                $table->json('coverage_overrides')->nullable()->after('coverage_ends_on');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('order_sessions', function (Blueprint $table) {
            if (Schema::hasColumn('order_sessions', 'coverage_overrides')) {
                $table->dropColumn('coverage_overrides');
            }
        });
    }
};
