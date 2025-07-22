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
        Schema::table('delivery_items', function (Blueprint $table) {
            $table->boolean('barcode_retrieval_failed')->default(false)->after('barcode');
            $table->string('barcode_retrieval_error')->nullable()->after('barcode_retrieval_failed');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('delivery_items', function (Blueprint $table) {
            $table->dropColumn(['barcode_retrieval_failed', 'barcode_retrieval_error']);
        });
    }
};
