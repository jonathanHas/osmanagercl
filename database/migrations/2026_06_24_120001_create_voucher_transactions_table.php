<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('voucher_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('voucher_id')->constrained('vouchers')->cascadeOnDelete();
            $table->string('type', 20)->index(); // issue, deduct
            $table->decimal('amount', 10, 2);
            $table->decimal('balance_after', 10, 2);
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('voucher_transactions');
    }
};
