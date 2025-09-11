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
        Schema::create('bank_import_mappings', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->unsignedTinyInteger('date_col');
            $table->unsignedTinyInteger('description_col');
            $table->unsignedTinyInteger('debit_col');
            $table->unsignedTinyInteger('credit_col');
            $table->unsignedTinyInteger('balance_col')->nullable();
            $table->string('date_format')->default('d/m/Y');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bank_import_mappings');
    }
};
