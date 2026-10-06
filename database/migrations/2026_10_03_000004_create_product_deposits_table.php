<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Which till product carries which deposit tier (barrel code).
        Schema::create('product_deposits', function (Blueprint $table) {
            $table->id();
            $table->string('product_id', 36)->unique();
            $table->string('product_code', 32)->nullable();
            $table->foreignId('barrel_code_id')->constrained('barrel_codes')->restrictOnDelete();
            $table->string('status', 16)->default('suggested');
            $table->string('source', 16);
            $table->unsignedInteger('sightings_units')->default(0);
            $table->unsignedInteger('sightings_count')->default(0);
            $table->unsignedInteger('conflicting_units')->default(0);
            $table->date('last_seen_on')->nullable();
            $table->foreignId('confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('pos_synced_at')->nullable();
            $table->string('pos_sync_error')->nullable();
            $table->string('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_deposits');
    }
};
