<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Adds VAT classification fields to support future VAT3 + RTD automation.
     */
    public function up(): void
    {
        Schema::table('accounting_suppliers', function (Blueprint $table) {
            // VAT treatment - how VAT is accounted for based on supplier location/type
            $table->enum('vat_treatment', [
                'irish_vat',                    // Standard Irish VAT (domestic suppliers)
                'eu_goods_zero_rated',          // EU goods - zero rated (Intrastat)
                'eu_reverse_charge_services',   // EU services - reverse charge
                'postponed_import',             // Non-EU imports with postponed accounting
                'outside_scope_or_exempt',      // Outside scope or exempt supplies
            ])->nullable()->after('is_eu_supplier');

            // Default purchase use - what the purchases are typically used for
            $table->enum('default_purchase_use', [
                'resale',    // Goods for resale (T1 on RTD)
                'overhead',  // Business overheads/expenses (T2 on RTD)
                'mixed',     // Mixed use - requires manual classification per invoice
            ])->default('resale')->after('vat_treatment');

            // Indexes for efficient filtering
            $table->index('vat_treatment', 'idx_supplier_vat_treatment');
            $table->index('default_purchase_use', 'idx_supplier_purchase_use');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('accounting_suppliers', function (Blueprint $table) {
            $table->dropIndex('idx_supplier_vat_treatment');
            $table->dropIndex('idx_supplier_purchase_use');
            $table->dropColumn(['vat_treatment', 'default_purchase_use']);
        });
    }
};
