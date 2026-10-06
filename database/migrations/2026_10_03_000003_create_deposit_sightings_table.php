<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One row per document line on which a supplier showed a deposit code
        // for one of its products: the evidence behind product_deposits.
        Schema::create('deposit_sightings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('supplier_id');
            $table->string('supplier_code', 20);
            $table->string('barrel_code', 20);
            $table->unsignedInteger('units');
            $table->string('source_type', 32);
            $table->unsignedBigInteger('source_id');
            $table->date('seen_on')->nullable();
            $table->timestamps();

            $table->unique(['source_type', 'source_id', 'supplier_code', 'barrel_code'], 'deposit_sightings_source_unique');
            $table->index('supplier_code');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deposit_sightings');
    }
};
