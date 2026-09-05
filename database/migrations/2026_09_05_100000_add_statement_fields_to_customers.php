<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Statement support: payment terms drive the aging fallback (invoice
     * due_date is optional and in practice left blank), and the two send_*
     * columns gate/track emailed statements.
     */
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->unsignedSmallInteger('payment_terms_days')->nullable()->default(30)->after('default_discount_percent');
            $table->boolean('send_statements')->default(false)->after('payment_terms_days');
            $table->timestamp('statement_last_sent_at')->nullable()->after('send_statements');
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn(['payment_terms_days', 'send_statements', 'statement_last_sent_at']);
        });
    }
};
