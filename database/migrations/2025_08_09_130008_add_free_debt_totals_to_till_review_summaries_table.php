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
        Schema::table('till_review_summaries', function (Blueprint $table) {
            $table->decimal('free_total', 10, 2)->default(0)->after('other_total');
            $table->decimal('debt_total', 10, 2)->default(0)->after('free_total');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('till_review_summaries', function (Blueprint $table) {
            $table->dropColumn(['free_total', 'debt_total']);
        });
    }
};
