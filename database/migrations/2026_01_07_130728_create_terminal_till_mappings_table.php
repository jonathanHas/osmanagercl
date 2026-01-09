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
        Schema::create('terminal_till_mappings', function (Blueprint $table) {
            $table->id();
            $table->string('terminal_id')->unique();       // Card terminal TID (e.g., "90282976")
            $table->string('terminal_name')->nullable();   // Friendly name (e.g., "Shop Terminal")
            $table->string('pos_host');                    // POS till HOST (e.g., "Till 1")
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('pos_host');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('terminal_till_mappings');
    }
};
