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
        Schema::create('till_review_cache', function (Blueprint $table) {
            $table->id();
            $table->date('transaction_date');
            $table->dateTime('transaction_time');
            $table->enum('transaction_type', ['receipt', 'drawer', 'removed', 'card']);
            $table->json('transaction_data');
            $table->string('receipt_id')->nullable()->index();
            $table->string('ticket_id')->nullable()->index();
            $table->string('terminal')->nullable();
            $table->string('cashier')->nullable();
            $table->decimal('amount', 10, 2)->nullable();
            $table->timestamp('cached_at')->useCurrent();
            $table->timestamps();
            
            $table->index(['transaction_date', 'transaction_type']);
            $table->index(['transaction_date', 'terminal']);
            $table->index(['transaction_date', 'cashier']);
            $table->index('transaction_time');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('till_review_cache');
    }
};