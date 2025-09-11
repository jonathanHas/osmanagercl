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
        Schema::table('reconciliation_rules', function (Blueprint $table) {
            $table->string('credit_category')->nullable()->after('expense_description')
                ->comment('Credit categories: card_lodgement, cash_lodgement, other_credit');
            $table->string('reconciliation_type')->nullable()->after('credit_category')
                ->comment('Type of reconciliation: invoice, credit_categorization, expense');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('reconciliation_rules', function (Blueprint $table) {
            $table->dropColumn(['credit_category', 'reconciliation_type']);
        });
    }
};
