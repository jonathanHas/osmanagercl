<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The owner's price review per deposit product: keep the shelf price,
        // or lower it by the deposit (price_review_from = gross before).
        Schema::table('product_deposits', function (Blueprint $table) {
            $table->string('price_review', 16)->nullable()->after('notes');
            $table->decimal('price_review_from', 10, 4)->nullable()->after('price_review');
            $table->timestamp('price_reviewed_at')->nullable()->after('price_review_from');
            $table->foreignId('price_reviewed_by')->nullable()->after('price_reviewed_at')
                ->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('product_deposits', function (Blueprint $table) {
            $table->dropForeign(['price_reviewed_by']);
            $table->dropColumn(['price_review', 'price_review_from', 'price_reviewed_at', 'price_reviewed_by']);
        });
    }
};
