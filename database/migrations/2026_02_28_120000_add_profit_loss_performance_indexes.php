<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cash_reconciliation_payments', function (Blueprint $table) {
            $table->index('created_at');
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->index(['invoice_date', 'payment_status']);
        });
    }

    public function down(): void
    {
        Schema::table('cash_reconciliation_payments', function (Blueprint $table) {
            $table->dropIndex(['created_at']);
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->dropIndex(['invoice_date', 'payment_status']);
        });
    }
};
