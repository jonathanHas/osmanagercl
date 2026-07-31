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
        // supplier_link lives in the external uniCenta database. The test
        // suite builds only the POS tables a test needs, so skip if absent.
        if (! Schema::connection('pos')->hasTable('supplier_link')) {
            return;
        }

        Schema::connection('pos')->table('supplier_link', function (Blueprint $table) {
            // Add new fields to clarify packaging structure
            $table->integer('units_per_retail_package')->nullable()->after('CaseUnits')
                ->comment('Number of individual items in each retail package (what customer buys)');

            $table->integer('retail_packages_per_case')->nullable()->after('units_per_retail_package')
                ->comment('Number of retail packages in each wholesale case (what we receive from supplier)');

            // Add a comment to clarify the existing CaseUnits field
            $table->integer('CaseUnits')->change()
                ->comment('DEPRECATED: Use units_per_retail_package and retail_packages_per_case instead');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // supplier_link lives in the external uniCenta database. The test
        // suite builds only the POS tables a test needs, so skip if absent.
        if (! Schema::connection('pos')->hasTable('supplier_link')) {
            return;
        }

        Schema::connection('pos')->table('supplier_link', function (Blueprint $table) {
            $table->dropColumn(['units_per_retail_package', 'retail_packages_per_case']);

            // Remove the comment from CaseUnits
            $table->integer('CaseUnits')->change();
        });
    }
};
