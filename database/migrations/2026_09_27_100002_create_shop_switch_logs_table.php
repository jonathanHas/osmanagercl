<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Who took over which shared device, and when (cycle 26). The Laravel-side
 * audit fields (waste, harvest, labels, requests) record auth()->id(), so this
 * table is what explains a change of hands on one tablet.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shop_switch_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_device_id')->nullable()->constrained('shop_devices')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('event', 20);
            $table->string('ip', 45)->nullable();
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shop_switch_logs');
    }
};
