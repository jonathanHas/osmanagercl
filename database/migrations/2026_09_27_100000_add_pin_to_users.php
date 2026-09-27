<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Shop PIN sign-in (cycle 26). Employees get a 4–6 digit PIN so they can take
 * over a trusted shared device without typing a password on a touchscreen.
 * The PIN itself is never stored — only its hash, its length (so the PIN pad
 * can draw the right number of dots) and when it was set.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('pin_hash')->nullable()->after('password');
            $table->unsignedTinyInteger('pin_length')->nullable()->after('pin_hash');
            $table->timestamp('pin_set_at')->nullable()->after('pin_length');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['pin_hash', 'pin_length', 'pin_set_at']);
        });
    }
};
