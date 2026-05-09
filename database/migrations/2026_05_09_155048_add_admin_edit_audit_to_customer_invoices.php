<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer_invoices', function (Blueprint $table) {
            // Set whenever an admin edits an issued (or otherwise post-draft) invoice.
            $table->timestamp('last_edited_at')->nullable()->after('voided_by');
            $table->foreignId('last_edited_by')->nullable()->constrained('users')->nullOnDelete()->after('last_edited_at');
        });
    }

    public function down(): void
    {
        Schema::table('customer_invoices', function (Blueprint $table) {
            $table->dropConstrainedForeignId('last_edited_by');
            $table->dropColumn('last_edited_at');
        });
    }
};
