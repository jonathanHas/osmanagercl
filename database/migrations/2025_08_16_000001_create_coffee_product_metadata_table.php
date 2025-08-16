<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('coffee_product_metadata', function (Blueprint $table) {
            $table->id();
            $table->string('product_id', 50)->index(); // POS product ID
            $table->string('product_name'); // POS product name for reference
            $table->enum('type', ['coffee', 'option'])->default('coffee');
            $table->string('short_name', 20); // Short name for KDS display
            $table->string('group_name', 50)->nullable(); // Group name for related options (e.g., "Syrups", "Milk")
            $table->integer('display_order')->default(0); // Order within group
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique('product_id');
            $table->index(['type', 'is_active']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('coffee_product_metadata');
    }
};
