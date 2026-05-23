<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kds_products', function (Blueprint $table) {
            $table->id();
            $table->string('product_id', 50)->unique();
            $table->string('product_name');
            $table->string('category_id', 50)->nullable();
            $table->string('category_name')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->string('notes')->nullable();
            $table->timestamps();
        });

        // Seed with current POS category 081 (Coffee Fresh) products so the
        // switch from hard-coded category filter to allow-list is a no-op.
        try {
            $rows = DB::connection('pos')
                ->table('PRODUCTS as p')
                ->leftJoin('CATEGORIES as c', 'p.CATEGORY', '=', 'c.ID')
                ->where('p.CATEGORY', '081')
                ->select(
                    'p.ID as product_id',
                    'p.NAME as product_name',
                    'p.CATEGORY as category_id',
                    'c.NAME as category_name',
                )
                ->get()
                ->map(fn ($r) => [
                    'product_id' => $r->product_id,
                    'product_name' => $r->product_name,
                    'category_id' => $r->category_id,
                    'category_name' => $r->category_name,
                    'is_active' => true,
                    'notes' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ])
                ->all();

            if (! empty($rows)) {
                DB::table('kds_products')->insert($rows);
            }
        } catch (\Throwable $e) {
            // POS connection may be unavailable in some environments (CI/local).
            // Leave the table empty in that case; products can be added via UI.
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('kds_products');
    }
};
