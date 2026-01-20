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
        Schema::table('accounting_suppliers', function (Blueprint $table) {
            $table->boolean('is_order_managed')->default(false)->after('is_pos_linked');
            $table->integer('order_manager_threshold')->default(10)->after('is_order_managed');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('accounting_suppliers', function (Blueprint $table) {
            $table->dropColumn(['is_order_managed', 'order_manager_threshold']);
        });
    }
};
