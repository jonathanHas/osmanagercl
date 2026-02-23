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
            $table->dropIndex('idx_supplier_purchase_use');
            $table->dropColumn('default_purchase_use');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('accounting_suppliers', function (Blueprint $table) {
            $table->enum('default_purchase_use', [
                'resale', 'overhead', 'mixed',
            ])->default('resale')->after('vat_treatment');
            $table->index('default_purchase_use', 'idx_supplier_purchase_use');
        });
    }
};
