<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Delivery headers
        Schema::create('deliveries', function (Blueprint $table) {
            $table->id();
            $table->string('delivery_number')->unique();
            $table->unsignedBigInteger('supplier_id');
            $table->date('delivery_date');
            $table->enum('status', ['draft', 'receiving', 'completed', 'cancelled'])->default('draft');
            $table->decimal('total_expected', 10, 2)->nullable();
            $table->decimal('total_received', 10, 2)->nullable();
            $table->json('import_data')->nullable(); // Store original CSV data
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('supplier_id');
            $table->index('status');
            $table->index('delivery_date');
        });

        // Delivery line items
        Schema::create('delivery_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('delivery_id')->constrained()->onDelete('cascade');
            $table->string('supplier_code');
            $table->string('sku')->nullable();
            $table->string('barcode')->nullable();
            $table->string('description');
            $table->integer('units_per_case')->default(1);
            $table->decimal('unit_cost', 10, 4);
            $table->integer('ordered_quantity');
            $table->integer('received_quantity')->default(0);
            $table->decimal('total_cost', 10, 2);
            $table->enum('status', ['pending', 'partial', 'complete', 'missing', 'excess'])->default('pending');
            $table->string('product_id')->nullable(); // Link to POS products table
            $table->boolean('is_new_product')->default(false);
            $table->json('scan_history')->nullable(); // Track individual scans
            $table->timestamps();

            $table->index(['delivery_id', 'status']);
            $table->index('supplier_code');
            $table->index('barcode');
            $table->index('product_id');
        });

        // Scan tracking for real-time verification
        Schema::create('delivery_scans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('delivery_id')->constrained()->onDelete('cascade');
            $table->foreignId('delivery_item_id')->nullable()->constrained()->onDelete('cascade');
            $table->string('barcode');
            $table->integer('quantity')->default(1);
            $table->boolean('matched')->default(false);
            $table->string('scanned_by')->nullable();
            $table->json('metadata')->nullable(); // Store additional scan info
            $table->timestamps();

            $table->index(['delivery_id', 'barcode']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_scans');
        Schema::dropIfExists('delivery_items');
        Schema::dropIfExists('deliveries');
    }
};
