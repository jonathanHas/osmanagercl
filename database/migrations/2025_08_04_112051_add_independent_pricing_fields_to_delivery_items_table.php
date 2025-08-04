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
            // Independent Health Foods specific pricing fields
            $table->decimal('sale_price', 10, 4)->nullable()->after('unit_cost')
                ->comment('Recommended selling price (RSP) from Independent CSV');

            $table->decimal('tax_amount', 10, 4)->nullable()->after('sale_price')
                ->comment('Total tax amount for the line from Independent CSV');

            $table->decimal('tax_rate', 5, 2)->nullable()->after('tax_amount')
                ->comment('Calculated tax rate percentage (Tax/Value * 100)');

            $table->decimal('line_value_ex_vat', 10, 2)->nullable()->after('tax_rate')
                ->comment('Total line value excluding VAT (Value field from Independent CSV)');

            $table->decimal('unit_cost_including_tax', 10, 4)->nullable()->after('line_value_ex_vat')
                ->comment('Unit cost including tax (calculated field)');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('delivery_items', function (Blueprint $table) {
            $table->dropColumn([
                'sale_price',
                'tax_amount',
                'tax_rate',
                'line_value_ex_vat',
                'unit_cost_including_tax',
            ]);
        });
    }
};
