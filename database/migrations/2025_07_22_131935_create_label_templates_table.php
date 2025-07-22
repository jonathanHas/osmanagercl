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
        Schema::create('label_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('description')->nullable();
            $table->integer('width_mm'); // Width in millimeters
            $table->integer('height_mm'); // Height in millimeters
            $table->integer('margin_mm')->default(2); // Margin in millimeters
            $table->integer('font_size_name')->default(11); // Font size for product name
            $table->integer('font_size_barcode')->default(10); // Font size for barcode number
            $table->integer('font_size_price')->default(16); // Font size for price
            $table->integer('barcode_height')->default(20); // Barcode height in pixels
            $table->json('layout_config')->nullable(); // Additional layout configuration
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['is_active', 'is_default']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('label_templates');
    }
};
