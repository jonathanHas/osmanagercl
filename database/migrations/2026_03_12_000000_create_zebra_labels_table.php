<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('zebra_labels', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('product_code')->nullable()->index()->comment('Barcode from POS PRODUCTS.CODE');
            $table->string('product_id')->nullable()->index()->comment('UUID from POS PRODUCTS.ID');
            $table->text('description')->nullable();
            $table->mediumText('zpl_content')->comment('ZPL/PRN content for printing');
            $table->string('original_filename')->nullable();
            $table->decimal('label_width_mm', 6, 2)->nullable();
            $table->decimal('label_height_mm', 6, 2)->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('zebra_labels');
    }
};
