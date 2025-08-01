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
        Schema::table('order_items', function (Blueprint $table) {
            $table->integer('case_units')->default(1)->after('total_cost');
            $table->decimal('suggested_cases', 8, 3)->default(0)->after('case_units');
            $table->decimal('final_cases', 8, 3)->default(0)->after('suggested_cases');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn(['case_units', 'suggested_cases', 'final_cases']);
        });
    }
};
