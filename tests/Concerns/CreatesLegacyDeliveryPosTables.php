<?php

namespace Tests\Concerns;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

/**
 * In-memory POS tables for the legacy delivery scan flow.
 *
 * Every table the legacy match / scan queries touch. The schema is the same as
 * CustomerRequestDeliveryFlagTest builds inline, plus the `supID` and
 * `dateUpload` columns on `deliveriesScan` that the session list and the items
 * endpoint select. That duplication is deliberate for now — see the report's
 * Notes for Planner.
 */
trait CreatesLegacyDeliveryPosTables
{
    protected function createLegacyDeliveryPosTables(): void
    {
        Config::set('database.connections.pos', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
        DB::purge('pos');

        $pos = DB::connection('pos')->getSchemaBuilder();

        $pos->create('PRODUCTS', function (Blueprint $table) {
            $table->string('ID')->primary();
            $table->string('NAME')->nullable();
            $table->string('CODE')->unique();
            $table->string('REFERENCE')->nullable();
            $table->string('CATEGORY')->nullable();
            $table->string('TAXCAT')->nullable();
            $table->decimal('PRICEBUY', 10, 4)->default(0);
            $table->decimal('PRICESELL', 10, 4)->default(0);
        });
        $pos->create('CATEGORIES', function (Blueprint $table) {
            $table->string('ID')->primary();
            $table->string('NAME')->nullable();
        });
        $pos->create('TAXES', function (Blueprint $table) {
            $table->string('ID')->primary();
            $table->string('CATEGORY')->nullable();
            $table->decimal('RATE', 8, 6)->default(0);
        });
        $pos->create('STOCKCURRENT', function (Blueprint $table) {
            $table->string('PRODUCT');
            $table->decimal('UNITS', 10, 2)->default(0);
        });
        $pos->create('suppliers', function (Blueprint $table) {
            $table->string('SupplierID')->primary();
            $table->string('Supplier')->nullable();
        });
        $pos->create('supplier_link', function (Blueprint $table) {
            $table->string('Barcode');
            $table->string('SupplierCode')->nullable();
            $table->string('SupplierID')->nullable();
            $table->integer('CaseUnits')->default(1);
            $table->string('OuterCode')->nullable();
        });
        $pos->create('delivery', function (Blueprint $table) {
            $table->increments('id');
            $table->string('prodName')->nullable();
            $table->string('supCode')->nullable();
            $table->decimal('rrPrice', 10, 2)->nullable();
            $table->decimal('cost', 10, 4)->nullable();
            $table->decimal('myOrder', 10, 3)->default(0);
            $table->integer('caseUnits')->default(1);
            $table->string('orderNumber')->nullable();
        });
        $pos->create('deliveriesScan', function (Blueprint $table) {
            $table->string('ID')->primary();
            $table->string('supID')->nullable();
            $table->dateTime('dateUpload')->nullable();
            $table->integer('status')->default(0);
        });
        $pos->create('deliveriesScanItems', function (Blueprint $table) {
            $table->string('ID')->primary();
            $table->string('delID');
            $table->string('barcode');
            $table->decimal('quantity', 10, 3)->default(0);
            $table->dateTime('dateScan')->nullable();
        });
    }

    /**
     * One supplier, two invoice lines and three scans:
     *  - p1 "Oat drink 1 L": 2 cases of 6 expected (12), 12 scanned  => ok
     *  - p2 "Leeks":         8 units expected,           6 scanned   => short
     *  - an unknown barcode:                             3 scanned   => unexpected
     */
    protected function seedLegacyDelivery(): void
    {
        $pos = DB::connection('pos');

        $pos->table('CATEGORIES')->insert([
            ['ID' => 'c1', 'NAME' => 'Dairy Alternatives'],
            ['ID' => 'c2', 'NAME' => 'Vegetables'],
        ]);
        $pos->table('TAXES')->insert(['ID' => 't1', 'CATEGORY' => '001', 'RATE' => 0]);

        $pos->table('PRODUCTS')->insert([
            ['ID' => 'p1', 'NAME' => 'Oat drink 1 L', 'CODE' => '5000000000017', 'CATEGORY' => 'c1', 'TAXCAT' => '001', 'PRICEBUY' => 1.2, 'PRICESELL' => 2.1],
            ['ID' => 'p2', 'NAME' => 'Leeks', 'CODE' => '5000000000024', 'CATEGORY' => 'c2', 'TAXCAT' => '001', 'PRICEBUY' => 0.8, 'PRICESELL' => 1.5],
        ]);
        $pos->table('STOCKCURRENT')->insert([
            ['PRODUCT' => 'p1', 'UNITS' => 3],
            ['PRODUCT' => 'p2', 'UNITS' => 0],
        ]);

        $pos->table('suppliers')->insert(['SupplierID' => '999', 'Supplier' => 'Hof Linde']);
        $pos->table('supplier_link')->insert([
            ['Barcode' => '5000000000017', 'SupplierCode' => 'S1', 'SupplierID' => '999', 'CaseUnits' => 6, 'OuterCode' => '15000000000014'],
            ['Barcode' => '5000000000024', 'SupplierCode' => 'S2', 'SupplierID' => '999', 'CaseUnits' => 1, 'OuterCode' => null],
        ]);

        $pos->table('delivery')->insert([
            ['prodName' => 'OAT DRINK ORG 1L', 'supCode' => 'S1', 'rrPrice' => 2.1, 'cost' => 1.2, 'myOrder' => 2, 'caseUnits' => 6, 'orderNumber' => 'INV-1'],
            ['prodName' => 'LEEKS ORG', 'supCode' => 'S2', 'rrPrice' => 1.5, 'cost' => 0.8, 'myOrder' => 8, 'caseUnits' => 1, 'orderNumber' => 'INV-1'],
        ]);

        $pos->table('deliveriesScan')->insert([
            'ID' => 'd-1', 'supID' => '999', 'dateUpload' => now(), 'status' => 0,
        ]);
        $pos->table('deliveriesScanItems')->insert([
            ['ID' => 'i1', 'delID' => 'd-1', 'barcode' => '5000000000017', 'quantity' => 12, 'dateScan' => now()],
            ['ID' => 'i2', 'delID' => 'd-1', 'barcode' => '5000000000024', 'quantity' => 6, 'dateScan' => now()],
            ['ID' => 'i3', 'delID' => 'd-1', 'barcode' => '4260009912200', 'quantity' => 3, 'dateScan' => now()],
        ]);
    }
}
