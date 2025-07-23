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
        Schema::create('veg_availability', function (Blueprint $table) {
            $table->id();
            $table->string('product_code')->unique();
            $table->boolean('is_available')->default(false);
            $table->decimal('current_price', 10, 2)->nullable();
            $table->timestamps();

            $table->index('is_available');
            $table->index('updated_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('veg_availability');
    }
};
