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
        Schema::create('harvest_product_units', function (Blueprint $table) {
            $table->id();
            $table->string('product_code')->unique(); // POS Product CODE (barcode)
            $table->string('unit', 20)->default('kg'); // 'kg' (weight) or 'unit' (count)
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('harvest_product_units');
    }
};
