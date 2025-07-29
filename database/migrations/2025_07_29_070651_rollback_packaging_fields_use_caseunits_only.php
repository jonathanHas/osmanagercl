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
        // Remove new fields from supplier_link table
        Schema::connection('pos')->table('supplier_link', function (Blueprint $table) {
            $table->dropColumn(['units_per_retail_package', 'retail_packages_per_case']);
        });

        // Remove new fields from delivery_items table
        Schema::table('delivery_items', function (Blueprint $table) {
            $table->dropColumn(['units_per_retail_package', 'retail_packages_per_case']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Re-add fields to supplier_link if needed to rollback
        Schema::connection('pos')->table('supplier_link', function (Blueprint $table) {
            $table->integer('units_per_retail_package')->nullable()->after('CaseUnits');
            $table->integer('retail_packages_per_case')->nullable()->after('units_per_retail_package');
        });

        // Re-add fields to delivery_items if needed to rollback
        Schema::table('delivery_items', function (Blueprint $table) {
            $table->integer('units_per_retail_package')->nullable()->after('units_per_case');
            $table->integer('retail_packages_per_case')->nullable()->after('units_per_retail_package');
        });
    }
};
