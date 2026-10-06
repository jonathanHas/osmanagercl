<?php

namespace Tests\Concerns;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * In-memory POS tables for customer bottle deposits (deposit cycle 2).
 *
 * Just the columns DepositEvidenceService and DepositPosService touch, with the
 * uniCenta UNIQUE constraints on PRODUCTS (NAME, CODE, REFERENCE) and
 * CATEGORIES.NAME.
 */
trait CreatesDepositPosTables
{
    protected function createDepositPosTables(): void
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
            $table->string('NAME')->unique();
            $table->string('CODE')->unique();
            $table->string('REFERENCE')->unique();
            $table->string('CATEGORY');
            $table->string('TAXCAT');
            $table->decimal('PRICEBUY', 10, 4)->default(0);
            $table->decimal('PRICESELL', 10, 4)->default(0);
            $table->boolean('ISSERVICE')->default(false);
            $table->boolean('ISSCALE')->default(false);
            $table->integer('ISVPRICE')->default(0);
            $table->text('ATTRIBUTES')->nullable();
            $table->string('DISPLAY')->nullable();
            $table->binary('IMAGE')->nullable();
        });
        $pos->create('CATEGORIES', function (Blueprint $table) {
            $table->string('ID')->primary();
            $table->string('NAME')->unique();
            $table->string('PARENTID')->nullable();
            $table->boolean('CATSHOWNAME')->default(true);
        });
        $pos->create('PRODUCTS_CAT', function (Blueprint $table) {
            $table->string('PRODUCT')->primary();
            $table->integer('CATORDER')->nullable();
        });
        $pos->create('supplier_link', function (Blueprint $table) {
            $table->increments('ID');
            $table->string('Barcode');
            $table->string('SupplierCode');
            $table->integer('SupplierID');
            $table->integer('CaseUnits')->nullable();
            $table->boolean('stocked')->default(true);
            $table->string('OuterCode')->nullable();
            $table->decimal('Cost', 10, 4)->nullable();
        });
        $pos->create('suppliers', function (Blueprint $table) {
            $table->integer('SupplierID')->primary();
            $table->string('Supplier');
        });
        $pos->create('RESOURCES', function (Blueprint $table) {
            $table->string('ID')->primary();
            $table->string('NAME')->unique();
            $table->integer('RESTYPE')->default(0);
            $table->text('CONTENT')->nullable();
        });
    }

    /**
     * A plain goods product on the till.
     *
     * @return string the product ID
     */
    protected function posProduct(string $code, string $name, array $extra = []): string
    {
        $id = $extra['ID'] ?? (string) Str::uuid();

        DB::connection('pos')->table('PRODUCTS')->insert(array_merge([
            'ID' => $id,
            'NAME' => $name,
            'CODE' => $code,
            'REFERENCE' => $code,
            'CATEGORY' => '010',
            'TAXCAT' => '001',
            'PRICESELL' => 2,
        ], $extra));

        return $id;
    }

    protected function supplierLink(string $barcode, string $code, int $supplierId = 5): void
    {
        DB::connection('pos')->table('supplier_link')->insert([
            'Barcode' => $barcode,
            'SupplierCode' => $code,
            'SupplierID' => $supplierId,
        ]);
    }
}
