<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_translations', function (Blueprint $table) {
            $table->id();
            $table->string('product_id')->nullable()->comment('UUID from POS PRODUCTS.ID');
            $table->string('product_code')->nullable()->index()->comment('Barcode from POS PRODUCTS.CODE');
            $table->json('label_data')->comment('Structured JSON from Gemini');
            $table->string('label_size', 20)->default('large');
            $table->decimal('font_scale', 4, 2)->default(1.00);
            $table->json('original_photos')->nullable()->comment('Array of storage paths');
            $table->text('zpl_content')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
            $table->index(['product_code', 'created_at']);
            $table->index('product_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_translations');
    }
};
