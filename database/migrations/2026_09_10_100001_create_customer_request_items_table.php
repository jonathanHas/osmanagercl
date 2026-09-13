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
        Schema::create('customer_request_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_request_id')->constrained()->cascadeOnDelete();

            // POS PRODUCTS.CODE (barcode) when an existing product was picked; NULL
            // for a "please source this" line. Cross-database reference, so no FK.
            $table->string('product_code', 64)->nullable();
            // Snapshot of the POS product name at pick time so the request stays
            // readable if the product is later renamed or removed.
            $table->string('product_name', 255)->nullable();
            // Always filled: the product name copied on pick, or the free text
            // describing the new product to source.
            $table->string('description', 255);
            $table->decimal('quantity', 10, 2)->default(1);
            $table->string('notes', 500)->nullable();
            $table->unsignedInteger('position')->default(0);

            // pending | ordered | put_aside | collected | not_available | cancelled
            $table->string('status', 20)->default('pending');
            $table->timestamp('status_changed_at')->nullable();
            $table->foreignId('status_changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('status');
            // Delivery screens look up open lines by barcode.
            $table->index(['status', 'product_code']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customer_request_items');
    }
};
