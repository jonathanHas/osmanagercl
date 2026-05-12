<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers')->restrictOnDelete();
            $table->date('payment_date');
            $table->decimal('amount', 10, 2);
            $table->enum('method', ['card_till', 'cash_till', 'online']);
            // till_id is the integer key returned by CashReconciliationRepository::getAvailableTills()
            // (1-indexed, mapping to a POS HOST). Stored as string so it can hold either format
            // historically. till_name is a snapshot of the POS HOST string itself.
            $table->string('till_id')->nullable();
            $table->string('till_name')->nullable();
            $table->string('reference')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('voided_at')->nullable();
            $table->foreignId('voided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['customer_id']);
            $table->index(['payment_date']);
            $table->index(['method', 'till_id', 'payment_date'], 'cust_pay_recon_lookup');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_payments');
    }
};
