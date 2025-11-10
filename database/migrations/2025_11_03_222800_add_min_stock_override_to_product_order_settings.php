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
            $table->decimal('min_stock_override', 10, 2)->nullable()->after('safety_stock_factor')
                ->comment('User-specified minimum stock level in units. When set, system uses max(calculated, override)');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('product_order_settings', function (Blueprint $table) {
            $table->dropColumn('min_stock_override');
        });
    }
};
