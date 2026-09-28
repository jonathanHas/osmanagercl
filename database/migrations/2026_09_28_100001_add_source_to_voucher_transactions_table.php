<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('voucher_transactions', function (Blueprint $table) {
            $table->string('source', 10)->default('manual')->after('type'); // manual, till
        });
    }

    public function down(): void
    {
        Schema::table('voucher_transactions', function (Blueprint $table) {
            $table->dropColumn('source');
        });
    }
};
