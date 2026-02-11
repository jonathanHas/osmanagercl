<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->foreignId('rtd_submission_id')
                ->nullable()
                ->after('rtd_accepted_by')
                ->constrained('rtd_submissions')
                ->nullOnDelete();

            $table->index('rtd_submission_id');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropForeign(['rtd_submission_id']);
            $table->dropColumn('rtd_submission_id');
        });
    }
};
