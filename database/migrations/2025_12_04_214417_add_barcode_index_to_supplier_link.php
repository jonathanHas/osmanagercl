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
        Schema::connection('pos')->table('supplier_link', function (Blueprint $table) {
            $table->index('Barcode', 'idx_barcode');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('pos')->table('supplier_link', function (Blueprint $table) {
            $table->dropIndex('idx_barcode');
        });
    }
};
