<?php

namespace Tests\Concerns;

use App\Models\KitchenProduct;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\SupplierLink;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The kitchen order pages read PRODUCTS, supplier_link, suppliers and
 * STOCKCURRENT from the external uniCenta database, which has no migrations.
 * Build the minimum shape in the sqlite `pos` connection for feature tests.
 *
 * IMAGE is required because KitchenOrderService selects
 * `LENGTH(IMAGE)` raw to derive the has_image flag.
 */
trait CreatesKitchenOrderPosTables
{
    protected function createPosTables(): void
    {
        $pos = Schema::connection('pos');

        $pos->create('PRODUCTS', function (Blueprint $table) {
            $table->string('ID')->primary();
            $table->string('NAME')->nullable();
            $table->string('CODE')->nullable();
            $table->string('REFERENCE')->nullable();
            $table->string('CATEGORY')->nullable();
            $table->binary('IMAGE')->nullable();
        });

        $pos->create('supplier_link', function (Blueprint $table) {
            $table->increments('ID');
            $table->string('Barcode');
            $table->string('SupplierCode')->nullable();
            $table->string('SupplierID');
            $table->integer('CaseUnits')->nullable();
            $table->boolean('stocked')->default(0);
            $table->string('OuterCode')->nullable();
            $table->decimal('Cost', 10, 2)->nullable();
        });

        $pos->create('suppliers', function (Blueprint $table) {
            $table->string('SupplierID')->primary();
            $table->string('Supplier');
        });

        $pos->create('STOCKCURRENT', function (Blueprint $table) {
            $table->string('LOCATION')->nullable();
            $table->string('PRODUCT')->nullable();
            $table->string('ATTRIBUTESETINSTANCE_ID')->nullable();
            $table->decimal('UNITS', 10, 4)->default(0);
        });
    }

    protected function dropPosTables(): void
    {
        foreach (['PRODUCTS', 'supplier_link', 'suppliers', 'STOCKCURRENT'] as $table) {
            Schema::connection('pos')->dropIfExists($table);
        }
    }

    /**
     * Suppliers 5 => Udea and 37 => Independent; products A (Udea, 6/case,
     * code U-A), B (Udea, no case units, no code), C (Independent, 12/case);
     * all three flagged as kitchen products.
     *
     * @return array{A: string, B: string, C: string}
     */
    protected function seedKitchenProducts(): array
    {
        $this->seedSupplier('5', 'Udea');
        $this->seedSupplier('37', 'Independent');

        $this->seedProduct('prod-a', 'Apple Juice 1L', '1000001', '5', 6, 'U-A');
        $this->seedProduct('prod-b', 'Barley Flakes', '1000002', '5', null, null);
        $this->seedProduct('prod-c', 'Cashew Butter', '1000003', '37', 12, 'IND-C');

        foreach (['prod-a', 'prod-b', 'prod-c'] as $id) {
            KitchenProduct::create(['product_id' => $id]);
        }

        return ['A' => 'prod-a', 'B' => 'prod-b', 'C' => 'prod-c'];
    }

    protected function seedSupplier(string $id, string $name): Supplier
    {
        $supplier = new Supplier;
        $supplier->SupplierID = $id;
        $supplier->Supplier = $name;
        $supplier->save();

        return $supplier;
    }

    protected function seedProduct(
        string $id,
        string $name,
        string $barcode,
        string $supplierId,
        ?int $caseUnits,
        ?string $supplierCode
    ): Product {
        $product = new Product;
        $product->ID = $id;
        $product->NAME = $name;
        $product->CODE = $barcode;
        $product->REFERENCE = $barcode;
        $product->save();

        SupplierLink::create([
            'Barcode' => $barcode,
            'SupplierCode' => $supplierCode,
            'SupplierID' => $supplierId,
            'CaseUnits' => $caseUnits,
        ]);

        return $product;
    }
}
