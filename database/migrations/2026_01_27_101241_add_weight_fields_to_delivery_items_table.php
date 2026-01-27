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
        Schema::table('delivery_items', function (Blueprint $table) {
            $table->decimal('weight_per_unit', 8, 4)->nullable()->after('sku');
            $table->string('weight_unit', 10)->nullable()->after('weight_per_unit');
            $table->decimal('total_weight', 10, 4)->nullable()->after('weight_unit');
            $table->boolean('is_weight_based')->default(false)->after('total_weight');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('delivery_items', function (Blueprint $table) {
            $table->dropColumn(['weight_per_unit', 'weight_unit', 'total_weight', 'is_weight_based']);
        });
    }
};
