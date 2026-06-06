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
        Schema::create('fv_waste_logs', function (Blueprint $table) {
            $table->id();
            $table->date('waste_date');
            $table->string('product_code'); // POS Product CODE (barcode); cross-connection, no FK
            $table->string('product_name');  // snapshot of the name at entry time
            $table->decimal('quantity', 10, 2);
            $table->string('unit', 20)->default('kg'); // 'kg' | 'unit'
            $table->decimal('unit_price', 10, 2)->nullable(); // € price snapshot for stable historical totals
            $table->decimal('value', 10, 2)->nullable(); // qty * unit_price when unit matches the priced unit
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['waste_date', 'product_code']); // upsert / edit target
            $table->index('product_code');
            $table->index('waste_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fv_waste_logs');
    }
};
