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
        // Main KDS orders table
        Schema::create('kds_orders', function (Blueprint $table) {
            $table->id();
            $table->string('ticket_id')->index(); // Links to POS TICKETS.ID
            $table->integer('ticket_number'); // Display number
            $table->string('person')->nullable(); // Cashier ID from POS
            $table->enum('status', ['new', 'viewed', 'preparing', 'ready', 'completed', 'cancelled'])->default('new');
            $table->timestamp('order_time'); // When order was placed in POS
            $table->timestamp('viewed_at')->nullable(); // When barista first saw it
            $table->timestamp('started_at')->nullable(); // When preparation started
            $table->timestamp('ready_at')->nullable(); // When marked ready
            $table->timestamp('completed_at')->nullable(); // When picked up/completed
            $table->integer('prep_time')->nullable(); // Actual prep time in seconds
            $table->json('customer_info')->nullable(); // Optional customer name/table
            $table->timestamps();
            
            $table->index(['status', 'order_time']);
            $table->index('created_at');
        });

        // KDS order items table
        Schema::create('kds_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kds_order_id')->constrained('kds_orders')->onDelete('cascade');
            $table->string('product_id'); // Links to POS PRODUCTS.ID
            $table->string('product_name');
            $table->string('display_name')->nullable(); // Custom display name if different
            $table->decimal('quantity', 8, 3);
            $table->json('modifiers')->nullable(); // Size, milk type, extras etc from ATTRIBUTES
            $table->text('notes')->nullable(); // Special instructions
            $table->timestamps();
            
            $table->index('kds_order_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('kds_order_items');
        Schema::dropIfExists('kds_orders');
    }
};
