<?php

namespace Tests\Concerns;

use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * In-memory POS tables for till-driven voucher redemption (cycle 28).
 *
 * Just the columns VoucherPosProductService and VoucherTillSyncService touch,
 * with the uniCenta UNIQUE constraints on PRODUCTS (NAME, CODE, REFERENCE) and
 * CATEGORIES.NAME. DATENEW is a `Y-m-d H:i:s` string so comparisons work.
 */
trait CreatesVoucherPosTables
{
    protected function createVoucherPosTables(): void
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
            $table->string('DISPLAY')->nullable();
            $table->boolean('ISSERVICE')->default(false);
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
        $pos->create('TICKETS', function (Blueprint $table) {
            $table->string('ID')->primary();
            $table->integer('TICKETID');
            $table->integer('TICKETTYPE')->default(0);
            $table->string('PERSON')->nullable();
            $table->string('CUSTOMER')->nullable();
            $table->integer('STATUS')->default(0);
        });
        $pos->create('RECEIPTS', function (Blueprint $table) {
            $table->string('ID')->primary();
            $table->string('MONEY')->nullable();
            $table->string('DATENEW');
        });
        $pos->create('TICKETLINES', function (Blueprint $table) {
            $table->string('TICKET');
            $table->integer('LINE');
            $table->string('PRODUCT')->nullable();
            $table->double('UNITS')->default(1);
            $table->double('PRICE')->default(0);
            $table->string('TAXID');
            $table->text('ATTRIBUTES')->nullable();
        });
        $pos->create('TAXES', function (Blueprint $table) {
            $table->string('ID')->primary();
            $table->double('RATE')->default(0);
        });
        $pos->create('PAYMENTS', function (Blueprint $table) {
            $table->string('ID')->primary();
            $table->string('RECEIPT');
            $table->string('PAYMENT');
            $table->double('TOTAL')->default(0);
            $table->double('TENDERED')->default(0);
            $table->string('TRANSID')->nullable();
            $table->text('NOTES')->nullable();
            $table->string('CARDNAME')->nullable();
        });

        DB::connection('pos')->table('TAXES')->insert([
            ['ID' => '000', 'RATE' => 0],
            ['ID' => '001', 'RATE' => 0.23],
        ]);
    }

    /**
     * A plain goods product on the till, for the non-voucher lines of a sale.
     */
    protected function posGoods(string $name = 'Oat Milk 1L', string $code = '5000000000001'): string
    {
        $id = (string) Str::uuid();

        DB::connection('pos')->table('PRODUCTS')->insert([
            'ID' => $id,
            'NAME' => $name,
            'CODE' => $code,
            'REFERENCE' => $code,
            'CATEGORY' => '010',
            'TAXCAT' => '001',
            'PRICESELL' => 2,
        ]);

        return $id;
    }

    /**
     * Record a till sale (or refund, $type = 1) the way uniCenta does.
     *
     * @param  array<int, array{product: string, price?: float, units?: float, tax?: string}>  $lines  in LINE order
     * @param  array<int, array{payment: string, total: float}>  $payments
     * @return string the ticket (= receipt) id
     */
    protected function posSale(int $ticketNo, array $lines, array $payments, Carbon $at, int $type = 0): string
    {
        $pos = DB::connection('pos');
        $id = (string) Str::uuid();

        $pos->table('RECEIPTS')->insert(['ID' => $id, 'MONEY' => 'cash-1', 'DATENEW' => $at->format('Y-m-d H:i:s')]);
        $pos->table('TICKETS')->insert(['ID' => $id, 'TICKETID' => $ticketNo, 'TICKETTYPE' => $type, 'PERSON' => '0']);

        foreach (array_values($lines) as $i => $line) {
            $pos->table('TICKETLINES')->insert([
                'TICKET' => $id,
                'LINE' => $i,
                'PRODUCT' => $line['product'],
                'UNITS' => $line['units'] ?? 1,
                'PRICE' => $line['price'] ?? 0,
                'TAXID' => $line['tax'] ?? '000',
            ]);
        }

        foreach ($payments as $payment) {
            $pos->table('PAYMENTS')->insert([
                'ID' => (string) Str::uuid(),
                'RECEIPT' => $id,
                'PAYMENT' => $payment['payment'],
                'TOTAL' => $payment['total'],
                'TENDERED' => 0,
                'TRANSID' => (string) random_int(100000000000, 999999999999),
            ]);
        }

        return $id;
    }
}
