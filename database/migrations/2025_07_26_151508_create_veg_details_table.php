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
        Schema::create('veg_details', function (Blueprint $table) {
            $table->id();
            $table->string('product_code')->unique(); // Links to products.CODE
            $table->foreignId('country_id')->nullable()->constrained('countries')->nullOnDelete();
            $table->foreignId('class_id')->nullable()->constrained('veg_classes')->nullOnDelete();
            $table->foreignId('unit_id')->nullable()->constrained('veg_units')->nullOnDelete();
            $table->timestamps();

            $table->index('product_code');
            $table->index('country_id');
            $table->index('class_id');
            $table->index('unit_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('veg_details');
    }
};
