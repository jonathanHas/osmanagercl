<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * RTD Classification Field for AccountingSupplier
     * - goods_simple: T1 - Use invoice VAT breakdown fields (no parser)
     * - goods_parser: T1 - Use dedicated parser (Udea, Dynamis, IIH)
     * - service_overhead: T2 - Service/overhead supplier
     * - not_applicable: No RTD tracking
     */
    public function up(): void
    {
        Schema::table('accounting_suppliers', function (Blueprint $table) {
            $table->enum('rtd_classification', [
                'goods_simple',
                'goods_parser',
                'service_overhead',
                'not_applicable',
            ])->default('not_applicable')->after('default_purchase_use');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('accounting_suppliers', function (Blueprint $table) {
            $table->dropColumn('rtd_classification');
        });
    }
};
