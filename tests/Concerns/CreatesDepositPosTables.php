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
        $pos->create('TAXCATEGORIES', function (Blueprint $table) {
            $table->string('ID')->primary();
            $table->string('NAME');
        });
        $pos->create('TAXES', function (Blueprint $table) {
            $table->string('ID')->primary();
            $table->string('NAME');
            $table->string('CATEGORY');
            $table->decimal('RATE', 10, 6);
            $table->integer('RATEORDER')->nullable();
        });
        $pos->create('RECEIPTS', function (Blueprint $table) {
            $table->string('ID')->primary();
            $table->string('MONEY')->nullable();
            $table->dateTime('DATENEW');
        });
        $pos->create('TICKETS', function (Blueprint $table) {
            $table->string('ID')->primary();
            $table->integer('TICKETTYPE')->default(0);
            $table->integer('TICKETID');
            $table->string('PERSON')->nullable();
            $table->string('CUSTOMER')->nullable();
            $table->integer('STATUS')->default(0);
        });
        $pos->create('TICKETLINES', function (Blueprint $table) {
            $table->string('TICKET');
            $table->integer('LINE');
            $table->string('PRODUCT')->nullable();
            $table->string('ATTRIBUTESETINSTANCE_ID')->nullable();
            $table->decimal('UNITS', 10, 3);
            $table->decimal('PRICE', 10, 4);
            $table->string('TAXID');
            $table->text('ATTRIBUTES')->nullable();
            $table->primary(['TICKET', 'LINE']);
        });
    }

    /**
     * One till sale: a receipt, its ticket and one line. A discounted line
     * carries the " - 5%" suffix on product.name in its ATTRIBUTES, as the
     * till writes it.
     */
    protected function posSale(string $productId, string $date, float $price, int $units = 1, bool $discounted = false, string $name = 'Lime juice'): void
    {
        static $ticketId = 0;
        $id = (string) Str::uuid();
        $pos = DB::connection('pos');
        $entry = $discounted ? "{$name} - 5%" : $name;

        $pos->table('RECEIPTS')->insert(['ID' => $id, 'MONEY' => 'test', 'DATENEW' => "{$date} 12:00:00"]);
        $pos->table('TICKETS')->insert(['ID' => $id, 'TICKETID' => ++$ticketId]);
        $pos->table('TICKETLINES')->insert([
            'TICKET' => $id,
            'LINE' => 0,
            'PRODUCT' => $productId,
            'UNITS' => $units,
            'PRICE' => $price,
            'TAXID' => '000',
            'ATTRIBUTES' => '<?xml version="1.0" encoding="UTF-8" standalone="no"?>'
                .'<!DOCTYPE properties SYSTEM "http://java.sun.com/dtd/properties.dtd">'
                .'<properties><entry key="product.taxcategoryid">000</entry>'
                ."<entry key=\"product.name\">{$entry}</entry></properties>",
        ]);
    }

    /** One tax category with one rate, as Product::tax() reads it. */
    protected function posTaxRate(string $taxcat, float $rate): void
    {
        DB::connection('pos')->table('TAXCATEGORIES')->insert(['ID' => $taxcat, 'NAME' => "Tax $taxcat"]);
        DB::connection('pos')->table('TAXES')->insert([
            'ID' => $taxcat,
            'NAME' => "Tax $taxcat",
            'CATEGORY' => $taxcat,
            'RATE' => $rate,
            'RATEORDER' => 0,
        ]);
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
