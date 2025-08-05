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
            // Add explicit case and unit quantity fields
            $table->integer('case_ordered_quantity')->nullable()->after('ordered_quantity')
                ->comment('Number of cases ordered (from CSV)');
            $table->integer('case_received_quantity')->default(0)->after('case_ordered_quantity')
                ->comment('Number of cases received (via case barcode scanning)');
            $table->integer('unit_ordered_quantity')->nullable()->after('case_received_quantity')
                ->comment('Number of individual units ordered (calculated from cases)');
            $table->integer('unit_received_quantity')->default(0)->after('unit_ordered_quantity')
                ->comment('Number of individual units received (via unit barcode scanning)');

            // Add outer case barcode field
            $table->string('outer_code')->nullable()->after('barcode')
                ->comment('Case/outer barcode from SupplierLink for case scanning');

            // Add quantity type indicator
            $table->enum('quantity_type', ['case', 'unit', 'mixed'])->default('case')->after('outer_code')
                ->comment('Primary quantity type for this item based on supplier format');

            // Add case units from supplier link for validation
            $table->integer('supplier_case_units')->nullable()->after('units_per_case')
                ->comment('Case units from SupplierLink table for validation');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('delivery_items', function (Blueprint $table) {
            $table->dropColumn([
                'case_ordered_quantity',
                'case_received_quantity',
                'unit_ordered_quantity',
                'unit_received_quantity',
                'outer_code',
                'quantity_type',
                'supplier_case_units',
            ]);
        });
    }
};
