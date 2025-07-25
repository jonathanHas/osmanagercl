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
        Schema::create('product_activity_logs', function (Blueprint $table) {
            $table->id();
            $table->string('product_id', 255);
            $table->string('product_code', 255);
            $table->string('activity_type', 50); // 'added_to_till', 'removed_from_till', 'price_changed', 'display_changed', 'country_changed'
            $table->string('category', 50)->nullable(); // 'fruit_veg', 'coffee', etc.
            $table->json('old_value')->nullable();
            $table->json('new_value')->nullable();
            $table->string('user_id')->nullable();
            $table->timestamps();
            
            // Indexes for performance
            $table->index('product_id');
            $table->index('activity_type');
            $table->index('category');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_activity_logs');
    }
};
