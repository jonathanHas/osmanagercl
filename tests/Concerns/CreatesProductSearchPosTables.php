<?php

namespace Tests\Concerns;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The product search reads PRODUCTS, stocking, supplier_link, suppliers,
 * STOCKCURRENT, CATEGORIES, TAXCATEGORIES and TAXES from the external
 * uniCenta database, which has no migrations. Build the minimum shape in the
 * sqlite `pos` connection for feature tests (modelled on
 * CreatesKitchenOrderPosTables, with the extra tables and columns the search
 * selects and eager-loads).
 */
trait CreatesProductSearchPosTables
{
    protected function createProductSearchPosTables(): void
    {
        $pos = Schema::connection('pos');

        $pos->create('PRODUCTS', function (Blueprint $table) {
            $table->string('ID')->primary();
            $table->string('REFERENCE')->nullable();
            $table->string('CODE')->nullable();
            $table->string('NAME')->nullable();
            $table->double('PRICEBUY')->default(0);
            $table->double('PRICESELL')->default(0);
            $table->string('CATEGORY')->nullable();
            $table->string('TAXCAT')->nullable();
            $table->binary('IMAGE')->nullable();
            $table->boolean('ISSERVICE')->default(false);
            $table->boolean('ISSCALE')->default(false);
            $table->boolean('ISKITCHEN')->default(false);
            $table->string('DISPLAY')->nullable();
            $table->double('STOCKUNITS')->default(0);
        });

        $pos->create('stocking', function (Blueprint $table) {
            $table->string('Barcode')->primary();
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

        $pos->create('CATEGORIES', function (Blueprint $table) {
            $table->string('ID')->primary();
            $table->string('NAME');
            $table->string('PARENTID')->nullable();
            $table->boolean('CATSHOWNAME')->default(true);
        });

        $pos->create('TAXCATEGORIES', function (Blueprint $table) {
            $table->string('ID')->primary();
            $table->string('NAME');
        });

        $pos->create('TAXES', function (Blueprint $table) {
            $table->string('ID')->primary();
            $table->string('NAME');
            $table->string('CATEGORY');
            $table->double('RATE')->default(0);
            $table->double('RATECASCADE')->nullable();
            $table->integer('RATEORDER')->nullable();
        });
    }

    protected function dropProductSearchPosTables(): void
    {
        foreach (['PRODUCTS', 'stocking', 'supplier_link', 'suppliers', 'STOCKCURRENT', 'CATEGORIES', 'TAXCATEGORIES', 'TAXES'] as $table) {
            Schema::connection('pos')->dropIfExists($table);
        }
    }

    /**
     * Suppliers 5 "Udea", 37 "Independent" and 65 "Natural Medicine" (no website
     * search URL in config); category "Chocolate" and
     * "Drinks"; one 23% tax. Products:
     *   P1 Chocolatemakers forest fruit milk chocolate 100 gram — 8721325594341, Udea 6001397, stocked, no blob
     *   P2 Chocolatemakers Puffed Quinoa and Ginger 80g       — 8719324515672, Udea 6001398, stocked
     *   P3 Milk Chocolate Bar                                  — 5000000000001, no supplier, NOT stocked
     *   P4 Apple Juice 1L                                      — 1000001, Independent IND-A, stocked, blob image
     *   P5 Old Delisted Thing                                  — 1000009, Natural Medicine NM-1, not stocked
     *
     * @return array{P1: string, P2: string, P3: string, P4: string, P5: string}
     */
    protected function seedProductSearchFixture(): array
    {
        $pos = DB::connection('pos');

        $pos->table('suppliers')->insert([
            ['SupplierID' => '5', 'Supplier' => 'Udea'],
            ['SupplierID' => '37', 'Supplier' => 'Independent'],
            ['SupplierID' => '65', 'Supplier' => 'Natural Medicine'],
        ]);

        $pos->table('CATEGORIES')->insert([
            ['ID' => 'cat-choc', 'NAME' => 'Chocolate'],
            ['ID' => 'cat-drinks', 'NAME' => 'Drinks'],
        ]);

        $pos->table('TAXCATEGORIES')->insert([['ID' => 'tax-std', 'NAME' => 'Standard']]);
        $pos->table('TAXES')->insert([['ID' => 'tax-23', 'NAME' => 'VAT 23%', 'CATEGORY' => 'tax-std', 'RATE' => 0.23, 'RATEORDER' => 1]]);

        $products = [
            ['ID' => 'p1', 'NAME' => 'Chocolatemakers forest fruit milk chocolate 100 gram', 'CODE' => '8721325594341', 'CATEGORY' => 'cat-choc', 'PRICESELL' => 5.08, 'IMAGE' => null],
            ['ID' => 'p2', 'NAME' => 'Chocolatemakers Puffed Quinoa and Ginger 80g', 'CODE' => '8719324515672', 'CATEGORY' => 'cat-choc', 'PRICESELL' => 4.00, 'IMAGE' => null],
            ['ID' => 'p3', 'NAME' => 'Milk Chocolate Bar', 'CODE' => '5000000000001', 'CATEGORY' => 'cat-choc', 'PRICESELL' => 2.00, 'IMAGE' => null],
            ['ID' => 'p4', 'NAME' => 'Apple Juice 1L', 'CODE' => '1000001', 'CATEGORY' => 'cat-drinks', 'PRICESELL' => 3.00, 'IMAGE' => 'not-really-a-jpeg'],
            ['ID' => 'p5', 'NAME' => 'Old Delisted Thing', 'CODE' => '1000009', 'CATEGORY' => 'cat-drinks', 'PRICESELL' => 1.00, 'IMAGE' => null],
        ];

        foreach ($products as $product) {
            $pos->table('PRODUCTS')->insert($product + [
                'REFERENCE' => $product['CODE'],
                'TAXCAT' => 'tax-std',
                'PRICEBUY' => 1.00,
            ]);
        }

        $pos->table('stocking')->insert([
            ['Barcode' => '8721325594341'],
            ['Barcode' => '8719324515672'],
            ['Barcode' => '1000001'],
        ]);

        $pos->table('supplier_link')->insert([
            ['Barcode' => '8721325594341', 'SupplierCode' => '6001397', 'SupplierID' => '5'],
            ['Barcode' => '8719324515672', 'SupplierCode' => '6001398', 'SupplierID' => '5'],
            ['Barcode' => '1000001', 'SupplierCode' => 'IND-A', 'SupplierID' => '37'],
            ['Barcode' => '1000009', 'SupplierCode' => 'NM-1', 'SupplierID' => '65'],
        ]);

        $pos->table('STOCKCURRENT')->insert([
            ['LOCATION' => '0', 'PRODUCT' => 'p1', 'UNITS' => 12],
            ['LOCATION' => '0', 'PRODUCT' => 'p4', 'UNITS' => 3.5],
        ]);

        return ['P1' => 'p1', 'P2' => 'p2', 'P3' => 'p3', 'P4' => 'p4', 'P5' => 'p5'];
    }
}
