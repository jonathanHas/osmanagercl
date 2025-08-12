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
            $table->boolean('is_historical')->default(false)->after('reference_number')
                ->comment('Indicates if this VAT return was imported from historical data');
            $table->index('is_historical');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vat_returns', function (Blueprint $table) {
            $table->dropColumn('is_historical');
        });
    }
};
