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
            // Add missing columns that the model expects
            $table->string('expense_description')->nullable()
                ->comment('Description of the expense for non-supplier transactions');
            $table->boolean('is_non_supplier_expense')->default(false)
                ->comment('Whether this rule is for non-supplier expenses (wages, taxes, etc.)');

            // Add the originally intended columns
            $table->string('credit_category')->nullable()
                ->comment('Credit categories: card_lodgement, cash_lodgement, other_credit');
            $table->string('reconciliation_type')->nullable()
                ->comment('Type of reconciliation: invoice, credit_categorization, expense');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('reconciliation_rules', function (Blueprint $table) {
            $table->dropColumn([
                'expense_description',
                'is_non_supplier_expense',
                'credit_category',
                'reconciliation_type',
            ]);
        });
    }
};
