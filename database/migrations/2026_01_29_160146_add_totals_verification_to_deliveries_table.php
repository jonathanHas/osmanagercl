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
        Schema::table('deliveries', function (Blueprint $table) {
            // Stated totals from invoice PDF (what the invoice claims)
            $table->decimal('invoice_stated_total', 10, 2)->nullable()->after('total_expected');

            // Calculated totals (sum of parsed line items)
            $table->decimal('calculated_total', 10, 2)->nullable()->after('invoice_stated_total');

            // Discrepancy between stated and calculated
            $table->decimal('total_discrepancy', 10, 2)->nullable()->after('calculated_total');

            // Flag for quick filtering of deliveries with issues
            $table->boolean('has_discrepancy')->default(false)->after('total_discrepancy');
        });

        // Add parsing metadata to delivery_documents for audit trail
        Schema::table('delivery_documents', function (Blueprint $table) {
            $table->json('parsing_metadata')->nullable()->after('is_primary');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('deliveries', function (Blueprint $table) {
            $table->dropColumn([
                'invoice_stated_total',
                'calculated_total',
                'total_discrepancy',
                'has_discrepancy',
            ]);
        });

        Schema::table('delivery_documents', function (Blueprint $table) {
            $table->dropColumn('parsing_metadata');
        });
    }
};
