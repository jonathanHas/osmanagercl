<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Append-only audit trail of who moved a request line between statuses.
     * The table is named customer_request_status_logs (not ..._item_status_logs)
     * so the auto-generated foreign key name stays under MySQL's 64-character limit.
     */
    public function up(): void
    {
        Schema::create('customer_request_status_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_request_item_id')->constrained()->cascadeOnDelete();
            $table->string('from_status', 20)->nullable();
            $table->string('to_status', 20);
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('note', 255)->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customer_request_status_logs');
    }
};
