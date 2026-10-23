<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vouchers', function (Blueprint $table) {
            // Activated by an admin without a sale (a charity or fundraiser):
            // who it went to, and when. Both null for every sold voucher.
            $table->string('sponsored_to', 120)->nullable()->after('face_value');
            $table->timestamp('sponsored_at')->nullable()->index()->after('sponsored_to');
        });
    }

    public function down(): void
    {
        Schema::table('vouchers', function (Blueprint $table) {
            $table->dropIndex(['sponsored_at']);
            $table->dropColumn(['sponsored_to', 'sponsored_at']);
        });
    }
};
