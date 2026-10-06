<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('barrel_codes', function (Blueprint $table) {
            // Pass this deposit on to customers at the till.
            $table->boolean('charge_customer')->default(false)->after('is_active');
            // uniCenta PRODUCTS.ID of the tier's deposit and refund products.
            $table->string('pos_product_id', 36)->nullable()->after('charge_customer');
            $table->string('pos_refund_product_id', 36)->nullable()->after('pos_product_id');
        });
    }

    public function down(): void
    {
        Schema::table('barrel_codes', function (Blueprint $table) {
            $table->dropColumn(['charge_customer', 'pos_product_id', 'pos_refund_product_id']);
        });
    }
};
