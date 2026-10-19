<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The PDF parser's Qty × Price × SKU check per line: false = failed,
        // true = passed, NULL = not checked (CSV, manual add, XLSX).
        Schema::table('delivery_items', function (Blueprint $table) {
            $table->boolean('price_valid')->nullable()->after('total_cost');
            $table->string('price_note', 255)->nullable()->after('price_valid');
        });
    }

    public function down(): void
    {
        Schema::table('delivery_items', function (Blueprint $table) {
            $table->dropColumn(['price_valid', 'price_note']);
        });
    }
};
