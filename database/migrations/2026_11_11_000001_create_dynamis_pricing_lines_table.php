<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Dynamis invoice pricing (docs/features/dynamis-pricing.md).
 *
 * One row per invoice line of a Dynamis docket: what the invoice said, and how
 * the line was priced for the till. The pricing choice per Dynamis code (per kg
 * or each, and how many are in a box) is remembered on dynamis_product_links.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dynamis_pricing_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('delivery_id')->constrained('deliveries')->cascadeOnDelete();
            $table->foreignId('delivery_item_id')->nullable()->constrained('delivery_items')->nullOnDelete();
            $table->unsignedInteger('line_no');

            // As printed on the invoice
            $table->string('dynamis_code', 32);
            $table->string('description');
            $table->string('origin', 8)->nullable();
            $table->string('unit', 8);                       // KG, COL (box) or PCE (piece)
            $table->unsignedInteger('packages');             // boxes delivered
            $table->decimal('quantity', 10, 3);              // kg, boxes or pieces, per `unit`
            $table->decimal('net_unit_price', 10, 4);        // per `unit`, ex VAT
            $table->decimal('line_total', 10, 2);
            $table->string('ean', 20)->nullable();
            $table->boolean('is_barcoded')->default(false);

            // A name-based guess the manager has not confirmed yet
            $table->string('suggested_product_id', 64)->nullable();
            $table->decimal('suggested_confidence', 4, 3)->nullable();

            // How the line is priced for the till
            $table->string('pricing_mode', 16)->nullable();  // per_kg, each, skip
            $table->decimal('units_per_box', 8, 2)->nullable();
            $table->decimal('kg_per_box', 8, 3)->nullable();
            $table->string('count_source', 16)->nullable();  // saved, pieces, size
            $table->decimal('applied_price', 10, 2)->nullable();
            $table->timestamp('applied_at')->nullable();
            $table->unsignedBigInteger('applied_by')->nullable();
            $table->timestamps();

            $table->index(['delivery_id', 'line_no']);
            $table->index('dynamis_code');
        });

        Schema::table('dynamis_product_links', function (Blueprint $table) {
            $table->string('pricing_mode', 16)->nullable()->after('pos_product_code');
            $table->decimal('units_per_box', 8, 2)->nullable()->after('pricing_mode');
            $table->decimal('kg_per_box', 8, 3)->nullable()->after('units_per_box');
        });
    }

    public function down(): void
    {
        Schema::table('dynamis_product_links', function (Blueprint $table) {
            $table->dropColumn(['pricing_mode', 'units_per_box', 'kg_per_box']);
        });

        Schema::dropIfExists('dynamis_pricing_lines');
    }
};
