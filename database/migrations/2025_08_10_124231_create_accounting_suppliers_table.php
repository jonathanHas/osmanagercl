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
        Schema::create('accounting_suppliers', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('name', 255);
            
            // Contact details
            $table->text('address')->nullable();
            $table->string('phone', 50)->nullable();
            $table->string('email', 255)->nullable();
            $table->string('website', 255)->nullable();
            
            // Financial details
            $table->string('vat_number', 50)->nullable();
            $table->string('default_vat_code', 20)->nullable();
            $table->string('default_expense_category', 50)->nullable();
            $table->integer('payment_terms_days')->default(30);
            
            // Integration
            $table->string('external_id', 100)->nullable();
            $table->string('integration_type', 50)->nullable();
            
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            $table->index('name');
            $table->index('vat_number');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('accounting_suppliers');
    }
};