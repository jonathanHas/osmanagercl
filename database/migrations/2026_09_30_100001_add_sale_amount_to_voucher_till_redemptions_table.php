<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('voucher_till_redemptions', function (Blueprint $table) {
            // What the till charged for the voucher's own line(s) on a sale; NULL on redemptions.
            $table->decimal('sale_amount', 12, 2)->nullable()->after('ticket_total');
        });
    }

    public function down(): void
    {
        Schema::table('voucher_till_redemptions', function (Blueprint $table) {
            $table->dropColumn('sale_amount');
        });
    }
};
