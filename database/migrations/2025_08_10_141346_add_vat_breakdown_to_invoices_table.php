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
        Schema::table('invoices', function (Blueprint $table) {
            // VAT breakdown by rate - store net and VAT amounts for each rate
            $table->decimal('standard_net', 10, 2)->default(0)->after('total_amount');
            $table->decimal('standard_vat', 10, 2)->default(0)->after('standard_net');
            $table->decimal('reduced_net', 10, 2)->default(0)->after('standard_vat');
            $table->decimal('reduced_vat', 10, 2)->default(0)->after('reduced_net');
            $table->decimal('second_reduced_net', 10, 2)->default(0)->after('reduced_vat');
            $table->decimal('second_reduced_vat', 10, 2)->default(0)->after('second_reduced_net');
            $table->decimal('zero_net', 10, 2)->default(0)->after('second_reduced_vat');
            $table->decimal('zero_vat', 10, 2)->default(0)->after('zero_net');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn([
                'standard_net', 'standard_vat',
                'reduced_net', 'reduced_vat',
                'second_reduced_net', 'second_reduced_vat',
                'zero_net', 'zero_vat',
            ]);
        });
    }
};
