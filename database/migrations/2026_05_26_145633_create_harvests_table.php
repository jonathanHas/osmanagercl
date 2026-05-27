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
        Schema::create('harvests', function (Blueprint $table) {
            $table->id();
            $table->date('harvest_date');
            $table->string('product_code'); // POS Product CODE (barcode); cross-connection, no FK
            $table->string('product_name');  // snapshot of the name at entry time
            $table->decimal('quantity', 10, 2);
            $table->string('unit', 20)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['harvest_date', 'product_code']); // upsert / edit-recent target
            $table->index('product_code');
            $table->index('harvest_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('harvests');
    }
};
