<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dynamis_product_links', function (Blueprint $table) {
            $table->id();
            $table->string('dynamis_code', 32)->unique();
            $table->string('dynamis_description')->nullable();
            $table->string('pos_product_id', 64);
            $table->string('pos_product_code', 64)->nullable();
            $table->enum('matched_by', ['manual', 'fuzzy', 'ai'])->default('manual');
            $table->decimal('confidence', 4, 3)->nullable();
            $table->string('ai_model')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->unsignedBigInteger('verified_by')->nullable();
            $table->timestamps();

            $table->index('pos_product_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dynamis_product_links');
    }
};
