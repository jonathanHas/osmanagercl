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
        Schema::table('product_order_settings', function (Blueprint $table) {
            $table->boolean('is_short_dated')->default(false)->after('shelf_life_days')
                ->comment('Flag indicating this product has a short shelf life requiring special attention');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('product_order_settings', function (Blueprint $table) {
            $table->dropColumn('is_short_dated');
        });
    }
};
