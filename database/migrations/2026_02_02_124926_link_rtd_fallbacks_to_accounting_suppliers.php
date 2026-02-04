<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Step 1: Add supplier_id column (nullable initially)
        Schema::table('rtd_vat_fallbacks', function (Blueprint $table) {
            $table->foreignId('supplier_id')
                ->nullable()
                ->after('id')
                ->constrained('accounting_suppliers')
                ->onDelete('cascade');
        });

        // Step 2: Migrate existing data - Find Udea supplier and link
        $udeaSupplier = DB::table('accounting_suppliers')
            ->where('name', 'like', '%udea%')
            ->first();

        if ($udeaSupplier) {
            DB::table('rtd_vat_fallbacks')
                ->where('supplier_identifier', 'Udea')
                ->update(['supplier_id' => $udeaSupplier->id]);
        }

        // Step 3: Drop old constraints and column, add new constraint
        Schema::table('rtd_vat_fallbacks', function (Blueprint $table) {
            // Drop old unique constraint and index
            $table->dropUnique(['article_code', 'supplier_identifier']);
            $table->dropIndex(['supplier_identifier']);

            // Drop the old column
            $table->dropColumn('supplier_identifier');

            // Add new unique constraint
            $table->unique(['article_code', 'supplier_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('rtd_vat_fallbacks', function (Blueprint $table) {
            // Drop new constraint
            $table->dropUnique(['article_code', 'supplier_id']);

            // Add back the old column
            $table->string('supplier_identifier', 50)->default('Udea')->after('id');
        });

        // Migrate data back
        $udeaSupplier = DB::table('accounting_suppliers')
            ->where('name', 'like', '%udea%')
            ->first();

        if ($udeaSupplier) {
            DB::table('rtd_vat_fallbacks')
                ->where('supplier_id', $udeaSupplier->id)
                ->update(['supplier_identifier' => 'Udea']);
        }

        Schema::table('rtd_vat_fallbacks', function (Blueprint $table) {
            // Drop foreign key and column
            $table->dropForeign(['supplier_id']);
            $table->dropColumn('supplier_id');

            // Add back old constraints
            $table->unique(['article_code', 'supplier_identifier']);
            $table->index('supplier_identifier');
        });
    }
};
