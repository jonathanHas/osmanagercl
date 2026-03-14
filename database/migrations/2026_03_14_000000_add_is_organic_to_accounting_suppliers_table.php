<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('accounting_suppliers', function (Blueprint $table) {
            $table->boolean('is_organic')->default(false)->after('rtd_classification');
        });
    }

    public function down(): void
    {
        Schema::table('accounting_suppliers', function (Blueprint $table) {
            $table->dropColumn('is_organic');
        });
    }
};
