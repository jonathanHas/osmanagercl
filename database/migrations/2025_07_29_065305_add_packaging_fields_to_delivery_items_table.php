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
            // Add new fields to clarify packaging structure
            $table->integer('units_per_retail_package')->nullable()->after('units_per_case')
                ->comment('Number of individual items in each retail package');

            $table->integer('retail_packages_per_case')->nullable()->after('units_per_retail_package')
                ->comment('Number of retail packages in each wholesale case');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('delivery_items', function (Blueprint $table) {
            $table->dropColumn(['units_per_retail_package', 'retail_packages_per_case']);
        });
    }
};
