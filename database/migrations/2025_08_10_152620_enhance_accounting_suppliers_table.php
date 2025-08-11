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
        Schema::table('accounting_suppliers', function (Blueprint $table) {
            // POS Integration
            $table->string('external_pos_id')->nullable()->after('code')->index();
            $table->boolean('is_pos_linked')->default(false)->after('external_pos_id');
            
            // Supplier Classification
            $table->enum('supplier_type', ['product', 'service', 'utility', 'professional', 'other'])
                  ->default('other')->after('is_pos_linked');
            
            // Enhanced Contact Information
            $table->string('contact_person')->nullable()->after('website');
            $table->string('phone_secondary')->nullable()->after('phone');
            $table->string('fax')->nullable()->after('phone_secondary');
            
            // Financial Enhancement
            $table->string('bank_account')->nullable()->after('payment_terms_days');
            $table->string('sort_code')->nullable()->after('bank_account');
            $table->enum('preferred_payment_method', ['bacs', 'cheque', 'card', 'cash', 'other'])
                  ->nullable()->after('sort_code');
            
            // Business Information
            $table->string('company_registration')->nullable()->after('vat_number');
            $table->string('tax_reference')->nullable()->after('company_registration');
            $table->text('delivery_instructions')->nullable()->after('tax_reference');
            
            // Spend Analytics
            $table->decimal('total_spent', 12, 2)->default(0)->after('delivery_instructions');
            $table->integer('invoice_count')->default(0)->after('total_spent');
            $table->date('last_invoice_date')->nullable()->after('invoice_count');
            $table->date('last_payment_date')->nullable()->after('last_invoice_date');
            
            // Performance Metrics
            $table->decimal('average_invoice_value', 10, 2)->default(0)->after('last_payment_date');
            $table->integer('days_since_last_order')->nullable()->after('average_invoice_value');
            
            // Status & Settings
            $table->enum('status', ['active', 'inactive', 'suspended', 'archived'])->default('active')->after('is_active');
            $table->text('notes')->nullable()->after('status');
            $table->json('tags')->nullable()->after('notes');
            
            // Audit fields
            $table->unsignedBigInteger('created_by')->nullable()->after('tags');
            $table->unsignedBigInteger('updated_by')->nullable()->after('created_by');
            
            // Foreign keys
            $table->foreign('created_by')->references('id')->on('users');
            $table->foreign('updated_by')->references('id')->on('users');
            
            // Additional indexes
            $table->index('supplier_type');
            $table->index('status');
            $table->index('last_invoice_date');
            $table->index(['supplier_type', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('accounting_suppliers', function (Blueprint $table) {
            $table->dropForeign(['created_by']);
            $table->dropForeign(['updated_by']);
            
            $table->dropColumn([
                'external_pos_id', 'is_pos_linked', 'supplier_type',
                'contact_person', 'phone_secondary', 'fax',
                'bank_account', 'sort_code', 'preferred_payment_method',
                'company_registration', 'tax_reference', 'delivery_instructions',
                'total_spent', 'invoice_count', 'last_invoice_date', 'last_payment_date',
                'average_invoice_value', 'days_since_last_order',
                'status', 'notes', 'tags', 'created_by', 'updated_by'
            ]);
        });
    }
};