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
        Schema::table('invoices', function (Blueprint $table) {
            // RTD data stored as JSON
            $table->json('rtd_breakdown')->nullable()->after('notes');
            $table->string('rtd_status', 20)->default('pending')->after('rtd_breakdown');
            $table->json('rtd_resolution_issues')->nullable()->after('rtd_status');
            $table->json('rtd_snapshot')->nullable()->after('rtd_resolution_issues');

            // Audit fields
            $table->timestamp('rtd_computed_at')->nullable()->after('rtd_snapshot');
            $table->timestamp('rtd_accepted_at')->nullable()->after('rtd_computed_at');
            $table->foreignId('rtd_accepted_by')->nullable()->after('rtd_accepted_at')
                ->constrained('users')->nullOnDelete();

            // Index for RTD reporting
            $table->index(['rtd_status', 'invoice_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropIndex(['rtd_status', 'invoice_date']);
            $table->dropForeign(['rtd_accepted_by']);
            $table->dropColumn([
                'rtd_breakdown',
                'rtd_status',
                'rtd_resolution_issues',
                'rtd_snapshot',
                'rtd_computed_at',
                'rtd_accepted_at',
                'rtd_accepted_by',
            ]);
        });
    }
};
