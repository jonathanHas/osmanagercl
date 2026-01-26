<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Barrel tracking tables for Udea deliveries.
     * - barrel_codes: Reference table of known barrel types (populated from invoices)
     * - delivery_barrels: Line items linking barrels to specific deliveries
     */
    public function up(): void
    {
        // Reference table for barrel codes (populated from supplier invoices)
        Schema::create('barrel_codes', function (Blueprint $table) {
            $table->id();
            $table->string('supplier_code', 20);              // Udea code: "69", "71", "313"
            $table->unsignedBigInteger('supplier_id');        // Link to supplier (Udea)
            $table->string('description', 150);               // "Beutelsb klein leeg - Landpark"
            $table->decimal('unit_price', 8, 2)->default(0);  // Current price per unit
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['supplier_id', 'supplier_code']);
            $table->index('supplier_id');
        });

        // Line items per delivery
        Schema::create('delivery_barrels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('delivery_id')->constrained()->onDelete('cascade');
            $table->foreignId('barrel_code_id')->nullable()->constrained()->nullOnDelete();
            $table->string('supplier_code', 20);              // Store code directly too
            $table->string('description', 150);               // Description from invoice
            $table->integer('quantity')->default(0);          // Quantity received
            $table->decimal('unit_price', 8, 2)->default(0);  // Price at time of delivery
            $table->decimal('total', 10, 2)->default(0);      // Line total
            $table->timestamps();

            $table->index('delivery_id');
            $table->index('barrel_code_id');
            $table->index('supplier_code');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('delivery_barrels');
        Schema::dropIfExists('barrel_codes');
    }
};
