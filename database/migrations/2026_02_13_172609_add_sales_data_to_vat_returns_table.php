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
        Schema::table('vat_returns', function (Blueprint $table) {
            $table->json('sales_vat_data')->nullable()->after('standard_vat');
            $table->decimal('eu_total_amount', 12, 2)->default(0)->after('sales_vat_data');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vat_returns', function (Blueprint $table) {
            $table->dropColumn(['sales_vat_data', 'eu_total_amount']);
        });
    }
};
