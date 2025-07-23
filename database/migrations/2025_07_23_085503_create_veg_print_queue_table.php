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
        Schema::create('veg_print_queue', function (Blueprint $table) {
            $table->id();
            $table->string('product_code')->unique();
            $table->timestamp('added_at')->useCurrent();
            $table->string('reason')->default('manual'); // 'price_change', 'marked_available', 'manual'
            $table->timestamps();

            $table->index('product_code');
            $table->index('added_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('veg_print_queue');
    }
};
