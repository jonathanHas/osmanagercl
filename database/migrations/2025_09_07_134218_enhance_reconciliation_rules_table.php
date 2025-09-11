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
        Schema::table('reconciliation_rules', function (Blueprint $table) {
            // Add description fingerprint for exact matches
            $table->string('description_fingerprint')->nullable()->after('match_pattern');

            // Add usage tracking
            $table->integer('match_count')->default(0)->after('description_fingerprint');
            $table->timestamp('last_matched_at')->nullable()->after('match_count');

            // Add confidence score based on historical success
            $table->integer('confidence_score')->default(50)->after('last_matched_at');

            // Add index for fast description lookups
            $table->index(['description_fingerprint']);
            $table->index(['supplier_id', 'match_count']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('reconciliation_rules', function (Blueprint $table) {
            $table->dropIndex(['description_fingerprint']);
            $table->dropIndex(['supplier_id', 'match_count']);

            $table->dropColumn([
                'description_fingerprint',
                'match_count',
                'last_matched_at',
                'confidence_score',
            ]);
        });
    }
};
