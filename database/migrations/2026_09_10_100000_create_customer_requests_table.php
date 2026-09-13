<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * A customer request is one customer asking for one or more items — either a
     * pre-order of something we stock or something new to source. Lines live in
     * customer_request_items and carry their own status; the request itself only
     * tracks the customer, the wanted-by date and whether it is still open.
     */
    public function up(): void
    {
        Schema::create('customer_requests', function (Blueprint $table) {
            $table->id();
            $table->string('customer_name', 120)->index();
            $table->string('customer_phone', 40)->nullable();
            $table->date('wanted_on')->nullable()->index();
            $table->text('notes')->nullable();

            // Set when no line is still open (pending / ordered / put_aside), so
            // "open requests" is a cheap whereNull rather than a whereHas per row.
            $table->timestamp('closed_at')->nullable()->index();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customer_requests');
    }
};
