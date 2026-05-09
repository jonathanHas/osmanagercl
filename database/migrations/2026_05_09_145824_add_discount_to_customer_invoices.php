<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer_invoices', function (Blueprint $table) {
            // 0..100 — wholesale percentage discount applied to the whole invoice
            $table->decimal('discount_percent', 5, 2)->default(0)->after('total');
        });

        Schema::table('customers', function (Blueprint $table) {
            // Optional default discount (auto-fills when this customer is picked)
            $table->decimal('default_discount_percent', 5, 2)->nullable()->after('vat_number');
        });
    }

    public function down(): void
    {
        Schema::table('customer_invoices', function (Blueprint $table) {
            $table->dropColumn('discount_percent');
        });
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn('default_discount_percent');
        });
    }
};
