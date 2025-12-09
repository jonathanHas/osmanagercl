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
        Schema::table('kitchen_ingredient_profiles', function (Blueprint $table) {
            // Delivery markup for imported products (Udea, Dynamis)
            $table->boolean('apply_delivery_markup')->default(false)->after('density');
            $table->decimal('delivery_markup_percent', 5, 2)->default(15.00)->after('apply_delivery_markup');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('kitchen_ingredient_profiles', function (Blueprint $table) {
            $table->dropColumn(['apply_delivery_markup', 'delivery_markup_percent']);
        });
    }
};
