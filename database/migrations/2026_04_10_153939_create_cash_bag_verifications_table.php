<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cash_bag_verifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('cash_reconciliation_id')->unique()->constrained('cash_reconciliations')->cascadeOnDelete();

            // Denomination counts (same pattern as cash_reconciliations)
            $table->unsignedInteger('cash_50')->default(0);
            $table->unsignedInteger('cash_20')->default(0);
            $table->unsignedInteger('cash_10')->default(0);
            $table->unsignedInteger('cash_5')->default(0);
            $table->unsignedInteger('cash_2')->default(0);
            $table->unsignedInteger('cash_1')->default(0);
            $table->unsignedInteger('cash_50c')->default(0);
            $table->unsignedInteger('cash_20c')->default(0);
            $table->unsignedInteger('cash_10c')->default(0);

            $table->decimal('counted_total', 10, 2)->default(0);
            $table->decimal('expected_total', 10, 2)->default(0);
            $table->decimal('variance', 10, 2)->default(0);

            // Lodgement link (null until included in a lodgement)
            $table->foreignUuid('cash_lodgement_id')->nullable()->constrained('cash_lodgements')->nullOnDelete();

            $table->foreignId('verified_by')->constrained('users');
            $table->timestamp('verified_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_bag_verifications');
    }
};
