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
        Schema::table('sales_daily_summary', function (Blueprint $table) {
            // Extend category_id from VARCHAR(10) to VARCHAR(50) to support UUID categories
            $table->string('category_id', 50)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sales_daily_summary', function (Blueprint $table) {
            //
        });
    }
};
