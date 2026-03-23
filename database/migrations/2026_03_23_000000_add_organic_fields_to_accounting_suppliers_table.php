<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('accounting_suppliers', function (Blueprint $table) {
            $table->string('organic_product_type')->nullable()->after('is_organic');
            $table->string('organic_certification_body')->nullable()->after('organic_product_type');
        });
    }

    public function down(): void
    {
        Schema::table('accounting_suppliers', function (Blueprint $table) {
            $table->dropColumn(['organic_product_type', 'organic_certification_body']);
        });
    }
};
