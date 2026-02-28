<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wage_entries', function (Blueprint $table) {
            $table->id();
            $table->integer('year');
            $table->integer('week_number');
            $table->date('week_start_date');
            $table->date('week_end_date');
            $table->decimal('gross_pay', 10, 2)->default(0);
            $table->decimal('taxable_benefits', 10, 2)->default(0);
            $table->decimal('taxable_adds', 10, 2)->default(0);
            $table->decimal('allow_deds', 10, 2)->default(0);
            $table->decimal('tax', 10, 2)->default(0);
            $table->decimal('usc_levy', 10, 2)->default(0);
            $table->decimal('prsi_ee', 10, 2)->default(0);
            $table->decimal('lpt', 10, 2)->default(0);
            $table->decimal('non_tax_adds', 10, 2)->default(0);
            $table->decimal('non_allow_deds', 10, 2)->default(0);
            $table->decimal('net_pay', 10, 2)->default(0);
            $table->decimal('prsi_er', 10, 2)->default(0);
            $table->timestamps();

            $table->unique(['year', 'week_number']);
            $table->index(['week_start_date', 'week_end_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wage_entries');
    }
};
