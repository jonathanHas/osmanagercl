<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_invoice_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_invoice_id')->constrained()->cascadeOnDelete();

            // Snapshots (POS product is on a separate connection, so no FK)
            $table->string('pos_product_id')->nullable()->index();
            $table->string('pos_product_code')->nullable();
            $table->string('description');

            $table->decimal('quantity', 10, 3)->default(1);
            $table->decimal('unit_price', 10, 4)->default(0); // stored as net
            $table->decimal('vat_rate', 5, 4)->default(0);

            $table->decimal('net_amount', 10, 2)->default(0);
            $table->decimal('vat_amount', 10, 2)->default(0);
            $table->decimal('gross_amount', 10, 2)->default(0);

            $table->unsignedInteger('position')->default(0);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_invoice_items');
    }
};
