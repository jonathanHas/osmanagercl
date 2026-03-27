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
        Schema::create('supplier_image_cache', function (Blueprint $table) {
            $table->id();
            $table->string('supplier_code', 50);
            $table->unsignedInteger('supplier_id');
            $table->string('image_url', 500)->nullable();
            $table->boolean('not_found')->default(false);
            $table->timestamps();

            $table->unique(['supplier_code', 'supplier_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('supplier_image_cache');
    }
};
