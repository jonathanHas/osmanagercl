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
        Schema::table('invoice_upload_files', function (Blueprint $table) {
            // Add supplier detection field
            $table->string('supplier_detected')->nullable()->after('parsing_confidence');
            
            // Add VAT breakdown JSON field
            $table->json('parsed_vat_data')->nullable()->after('supplier_detected');
            
            // Add anomaly warnings field
            $table->json('anomaly_warnings')->nullable()->after('parsed_vat_data');
            
            // Add invoice metadata
            $table->string('parsed_invoice_number')->nullable()->after('anomaly_warnings');
            $table->date('parsed_invoice_date')->nullable()->after('parsed_invoice_number');
            $table->decimal('parsed_total_amount', 10, 2)->nullable()->after('parsed_invoice_date');
            $table->boolean('is_tax_free')->default(false)->after('parsed_total_amount');
            $table->boolean('is_credit_note')->default(false)->after('is_tax_free');
            
            // Add index for supplier searches
            $table->index('supplier_detected');
            $table->index('parsed_invoice_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoice_upload_files', function (Blueprint $table) {
            $table->dropIndex(['supplier_detected']);
            $table->dropIndex(['parsed_invoice_date']);
            
            $table->dropColumn([
                'supplier_detected',
                'parsed_vat_data',
                'anomaly_warnings',
                'parsed_invoice_number',
                'parsed_invoice_date',
                'parsed_total_amount',
                'is_tax_free',
                'is_credit_note'
            ]);
        });
    }
};
