<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One row per voucher line seen on a uniCenta ticket. The unique pair
     * (ticket, product) is the idempotency backstop for the till sync.
     */
    public function up(): void
    {
        Schema::create('voucher_till_redemptions', function (Blueprint $table) {
            $table->id();
            $table->string('pos_ticket_id', 64);
            $table->string('pos_product_id', 64)->nullable();
            $table->string('voucher_code', 20)->nullable();
            $table->integer('ticket_number');
            $table->unsignedTinyInteger('ticket_type')->default(0);
            $table->dateTime('sold_at')->index();
            $table->decimal('voucher_tender', 16, 2)->default(0);
            $table->decimal('ticket_total', 12, 2)->nullable();
            $table->decimal('amount_deducted', 10, 2)->default(0);
            $table->decimal('shortfall', 16, 2)->default(0);
            $table->string('status', 20)->index();
            $table->foreignId('voucher_id')->nullable()->constrained('vouchers')->nullOnDelete();
            $table->foreignId('voucher_transaction_id')->nullable()->constrained('voucher_transactions')->nullOnDelete();
            $table->string('note', 500)->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['pos_ticket_id', 'pos_product_id']);
            $table->index(['status', 'reviewed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('voucher_till_redemptions');
    }
};
