<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('vat_rates', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20);
            $table->string('name', 100);
            $table->decimal('rate', 5, 4);
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->timestamps();

            $table->index(['code', 'effective_from', 'effective_to']);
            $table->index('effective_from');
        });

        // Insert default UK VAT rates
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

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vat_rates');
    }
};
