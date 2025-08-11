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
        Schema::create('invoice_vat_lines', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('invoice_id');
            
            // VAT line details
            $table->enum('vat_category', ['STANDARD', 'REDUCED', 'SECOND_REDUCED', 'ZERO']);
            $table->decimal('net_amount', 10, 2);
            $table->decimal('vat_rate', 5, 4); // Stores actual rate at time of invoice
            $table->decimal('vat_amount', 10, 2);
            $table->decimal('gross_amount', 10, 2);
            
            // Ordering and organization
            $table->unsignedTinyInteger('line_number')->default(1);
            
            // Audit fields
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            
            // Foreign keys and indexes
            $table->foreign('invoice_id')->references('id')->on('invoices')->onDelete('cascade');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('updated_by')->references('id')->on('users')->onDelete('set null');
            
            $table->index(['invoice_id', 'line_number']);
            $table->index('vat_category');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoice_vat_lines');
    }
};