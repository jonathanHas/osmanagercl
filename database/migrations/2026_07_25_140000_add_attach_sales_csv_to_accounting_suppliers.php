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
            $table->boolean('attach_sales_csv')
                ->default(true)
                ->after('include_sales_values')
                ->comment('Attach the CSV of the daily sales figures to this supplier\'s email');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('accounting_suppliers', function (Blueprint $table) {
            $table->dropColumn('attach_sales_csv');
        });
    }
};
