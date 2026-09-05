<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer_payments', function (Blueprint $table) {
            // Set whenever a payment's invoice allocations are edited after the
            // fact. The allocation rows themselves are replaced wholesale, so
            // this is the breadcrumb that the matching changed and who changed it.
            $table->timestamp('last_matched_at')->nullable()->after('voided_by');
            $table->foreignId('last_matched_by')->nullable()->constrained('users')->nullOnDelete()->after('last_matched_at');
        });
    }

    public function down(): void
    {
        Schema::table('customer_payments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('last_matched_by');
            $table->dropColumn('last_matched_at');
        });
    }
};
