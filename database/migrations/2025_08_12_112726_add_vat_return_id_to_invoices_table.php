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
        Schema::table('invoices', function (Blueprint $table) {
            $table->unsignedBigInteger('vat_return_id')->nullable()->after('external_osaccounts_id');
            $table->foreign('vat_return_id')->references('id')->on('vat_returns')->onDelete('set null');
            $table->index('vat_return_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropForeign(['vat_return_id']);
            $table->dropColumn('vat_return_id');
        });
    }
};
