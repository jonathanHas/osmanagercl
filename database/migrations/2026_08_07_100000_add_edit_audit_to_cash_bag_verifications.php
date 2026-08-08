<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A verified bag can now have its count corrected before it is lodged. verified_by/verified_at
     * stay as the record of who first counted it, so the correction needs its own stamp — same two
     * columns customer_invoices uses for admin edits.
     */
    public function up(): void
    {
        Schema::table('cash_bag_verifications', function (Blueprint $table) {
            $table->timestamp('last_edited_at')->nullable()->after('verified_at');
            $table->foreignId('last_edited_by')->nullable()->constrained('users')->nullOnDelete()->after('last_edited_at');
        });
    }

    public function down(): void
    {
        Schema::table('cash_bag_verifications', function (Blueprint $table) {
            $table->dropConstrainedForeignId('last_edited_by');
            $table->dropColumn('last_edited_at');
        });
    }
};
