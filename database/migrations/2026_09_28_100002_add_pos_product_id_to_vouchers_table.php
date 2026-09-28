<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vouchers', function (Blueprint $table) {
            // uniCenta PRODUCTS.ID of this voucher's hidden redemption product.
            $table->string('pos_product_id', 64)->nullable()->unique()->after('code');
        });
    }

    public function down(): void
    {
        Schema::table('vouchers', function (Blueprint $table) {
            $table->dropUnique(['pos_product_id']);
            $table->dropColumn('pos_product_id');
        });
    }
};
