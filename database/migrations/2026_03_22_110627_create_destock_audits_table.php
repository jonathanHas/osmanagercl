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
        Schema::create('destock_audits', function (Blueprint $table) {
            $table->id();
            $table->string('barcode');
            $table->string('product_name');
            $table->string('action'); // 'destock' or 'restock'
            $table->foreignId('user_id')->constrained();
            $table->string('source', 50)->nullable();
            $table->timestamps();

            $table->index('barcode');
            $table->index('user_id');
            $table->index('action');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('destock_audits');
    }
};
