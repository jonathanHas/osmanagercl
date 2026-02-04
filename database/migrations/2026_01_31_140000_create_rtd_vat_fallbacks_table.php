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
        Schema::create('rtd_vat_fallbacks', function (Blueprint $table) {
            $table->id();
            $table->string('article_code', 50);
            $table->string('supplier_identifier', 50)->default('Udea');
            $table->decimal('vat_rate', 4, 1);
            $table->string('description', 100)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users');
            $table->timestamps();

            // Unique per article code per supplier
            $table->unique(['article_code', 'supplier_identifier']);
            $table->index('supplier_identifier');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rtd_vat_fallbacks');
    }
};
