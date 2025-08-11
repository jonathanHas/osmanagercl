<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Update cost categories with appropriate Irish VAT defaults
        DB::table('cost_categories')->where('code', 'STOCK')->update(['vat_code_default' => 'STANDARD']);
        DB::table('cost_categories')->where('code', 'UTILITIES')->update(['vat_code_default' => 'REDUCED']);
        DB::table('cost_categories')->where('code', 'RENT')->update(['vat_code_default' => 'ZERO']);  // Commercial rent is VAT exempt
        DB::table('cost_categories')->where('code', 'WAGES')->update(['vat_code_default' => 'ZERO']);  // Wages not subject to VAT
        DB::table('cost_categories')->where('code', 'MARKETING')->update(['vat_code_default' => 'STANDARD']);
        DB::table('cost_categories')->where('code', 'INSURANCE')->update(['vat_code_default' => 'ZERO']);  // Insurance is VAT exempt
        DB::table('cost_categories')->where('code', 'REPAIRS')->update(['vat_code_default' => 'STANDARD']);
        DB::table('cost_categories')->where('code', 'OFFICE')->update(['vat_code_default' => 'STANDARD']);
        DB::table('cost_categories')->where('code', 'PROFESSIONAL')->update(['vat_code_default' => 'STANDARD']);
        DB::table('cost_categories')->where('code', 'OTHER')->update(['vat_code_default' => 'STANDARD']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert to original defaults
        DB::table('cost_categories')->where('code', 'STOCK')->update(['vat_code_default' => 'STANDARD']);
        DB::table('cost_categories')->where('code', 'UTILITIES')->update(['vat_code_default' => 'REDUCED']);
        DB::table('cost_categories')->where('code', 'RENT')->update(['vat_code_default' => 'EXEMPT']);
        DB::table('cost_categories')->where('code', 'WAGES')->update(['vat_code_default' => 'EXEMPT']);
        DB::table('cost_categories')->where('code', 'MARKETING')->update(['vat_code_default' => 'STANDARD']);
        DB::table('cost_categories')->where('code', 'INSURANCE')->update(['vat_code_default' => 'EXEMPT']);
        DB::table('cost_categories')->where('code', 'REPAIRS')->update(['vat_code_default' => 'STANDARD']);
        DB::table('cost_categories')->where('code', 'OFFICE')->update(['vat_code_default' => 'STANDARD']);
        DB::table('cost_categories')->where('code', 'PROFESSIONAL')->update(['vat_code_default' => 'STANDARD']);
        DB::table('cost_categories')->where('code', 'OTHER')->update(['vat_code_default' => 'STANDARD']);
    }
};