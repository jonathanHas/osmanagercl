<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rtd_vat_fallbacks', function (Blueprint $table) {
            $table->boolean('is_non_retail')->default(false)->after('vat_rate');
        });
    }

    public function down(): void
    {
        Schema::table('rtd_vat_fallbacks', function (Blueprint $table) {
            $table->dropColumn('is_non_retail');
        });
    }
};
