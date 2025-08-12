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
        Schema::create('cost_categories', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name', 100);
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->string('vat_code_default', 20)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('parent_id')->references('id')->on('cost_categories');
            $table->index('parent_id');
            $table->index('code');
        });

        // Insert default cost categories
        DB::table('cost_categories')->insert([
            ['code' => 'STOCK', 'name' => 'Stock Purchases', 'vat_code_default' => 'STANDARD', 'created_at' => now(), 'updated_at' => now()],
            ['code' => 'UTILITIES', 'name' => 'Utilities', 'vat_code_default' => 'REDUCED', 'created_at' => now(), 'updated_at' => now()],
            ['code' => 'RENT', 'name' => 'Rent & Rates', 'vat_code_default' => 'EXEMPT', 'created_at' => now(), 'updated_at' => now()],
            ['code' => 'WAGES', 'name' => 'Wages & Salaries', 'vat_code_default' => 'EXEMPT', 'created_at' => now(), 'updated_at' => now()],
            ['code' => 'MARKETING', 'name' => 'Marketing & Advertising', 'vat_code_default' => 'STANDARD', 'created_at' => now(), 'updated_at' => now()],
            ['code' => 'INSURANCE', 'name' => 'Insurance', 'vat_code_default' => 'EXEMPT', 'created_at' => now(), 'updated_at' => now()],
            ['code' => 'REPAIRS', 'name' => 'Repairs & Maintenance', 'vat_code_default' => 'STANDARD', 'created_at' => now(), 'updated_at' => now()],
            ['code' => 'OFFICE', 'name' => 'Office Supplies', 'vat_code_default' => 'STANDARD', 'created_at' => now(), 'updated_at' => now()],
            ['code' => 'PROFESSIONAL', 'name' => 'Professional Fees', 'vat_code_default' => 'STANDARD', 'created_at' => now(), 'updated_at' => now()],
            ['code' => 'OTHER', 'name' => 'Other Expenses', 'vat_code_default' => 'STANDARD', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cost_categories');
    }
};
