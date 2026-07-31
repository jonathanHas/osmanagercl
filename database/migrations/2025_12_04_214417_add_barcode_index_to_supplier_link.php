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
        // supplier_link lives in the external uniCenta database. The test
        // suite builds only the POS tables a test needs, so skip if absent.
        if (! Schema::connection('pos')->hasTable('supplier_link')) {
            return;
        }

        Schema::connection('pos')->table('supplier_link', function (Blueprint $table) {
            $table->index('Barcode', 'idx_barcode');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // supplier_link lives in the external uniCenta database. The test
        // suite builds only the POS tables a test needs, so skip if absent.
        if (! Schema::connection('pos')->hasTable('supplier_link')) {
            return;
        }

        Schema::connection('pos')->table('supplier_link', function (Blueprint $table) {
            $table->dropIndex('idx_barcode');
        });
    }
};
