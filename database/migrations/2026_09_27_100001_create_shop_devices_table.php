<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A device a manager has trusted for PIN sign-in (cycle 26). The random token
 * lives in a long-lived cookie on the device; only its SHA-256 hash is stored
 * here, so a database copy cannot be replayed as a device.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shop_devices', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('token_hash', 64)->unique();
            $table->foreignId('registered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('last_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shop_devices');
    }
};
