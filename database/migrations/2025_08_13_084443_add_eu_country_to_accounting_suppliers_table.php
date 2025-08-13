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
            $table->string('country_code', 2)->nullable()->after('address');
            $table->boolean('is_eu_supplier')->default(false)->after('country_code');
            $table->index('is_eu_supplier', 'idx_eu_suppliers');
        });
        
        // Update known EU suppliers (Dynamis from France and Udea from Netherlands)
        DB::table('accounting_suppliers')
            ->where('name', 'like', '%Dynamis%')
            ->update([
                'country_code' => 'FR',
                'is_eu_supplier' => true,
            ]);
            
        DB::table('accounting_suppliers')
            ->where('name', 'like', '%Udea%')
            ->orWhere('name', 'like', '%UDEA%')
            ->update([
                'country_code' => 'NL',
                'is_eu_supplier' => true,
            ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('accounting_suppliers', function (Blueprint $table) {
            $table->dropIndex('idx_eu_suppliers');
            $table->dropColumn(['country_code', 'is_eu_supplier']);
        });
    }
};