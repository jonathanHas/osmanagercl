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
        // Delete all existing VAT rates
        DB::table('vat_rates')->truncate();
        
        // Insert correct Irish VAT rates for 2025
        DB::table('vat_rates')->insert([
            [
                'code' => 'STANDARD',
                'name' => 'Standard Rate',
                'rate' => 0.2300,  // 23%
                'effective_from' => '2011-01-01',
                'effective_to' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'code' => 'REDUCED',
                'name' => 'Reduced Rate',
                'rate' => 0.1350,  // 13.5%
                'effective_from' => '2011-01-01',
                'effective_to' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'code' => 'SECOND_REDUCED',
                'name' => 'Second Reduced Rate',
                'rate' => 0.0900,  // 9%
                'effective_from' => '2011-01-01',
                'effective_to' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'code' => 'ZERO',
                'name' => 'Zero Rate',
                'rate' => 0.0000,  // 0%
                'effective_from' => '2011-01-01',
                'effective_to' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Restore UK rates if rolling back
        DB::table('vat_rates')->truncate();
        
        DB::table('vat_rates')->insert([
            [
                'code' => 'STANDARD',
                'name' => 'Standard Rate',
                'rate' => 0.2000,
                'effective_from' => '2011-01-04',
                'effective_to' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'code' => 'REDUCED',
                'name' => 'Reduced Rate',
                'rate' => 0.0500,
                'effective_from' => '2011-01-04',
                'effective_to' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'code' => 'ZERO',
                'name' => 'Zero Rate',
                'rate' => 0.0000,
                'effective_from' => '2011-01-04',
                'effective_to' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'code' => 'EXEMPT',
                'name' => 'Exempt',
                'rate' => 0.0000,
                'effective_from' => '2011-01-04',
                'effective_to' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
};