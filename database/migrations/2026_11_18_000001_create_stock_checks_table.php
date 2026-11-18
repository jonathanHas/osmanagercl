<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One row per confirmed stock check on /stock-review: what the till said
     * and what was counted. The POS stockLastChecked table only keeps a date.
     */
    public function up(): void
    {
        Schema::create('stock_checks', function (Blueprint $table) {
            $table->id();
            $table->string('barcode');
            $table->string('product_id');
            $table->decimal('previous_stock', 10, 2);
            $table->decimal('counted_stock', 10, 2);
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->index(['barcode', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_checks');
    }
};
