<?php

namespace Tests\Concerns;

use App\Models\Till;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

/**
 * An in-memory POS with the tables the till count reads: CLOSEDCASH, RECEIPTS,
 * PAYMENTS, suppliers, and (optionally) the old system's money / payeePayments /
 * dayNotes. Call setUpPos() after parent::setUp().
 */
trait BuildsPosTillFixture
{
    protected function setUpPos(bool $withLegacy = false): void
    {
        Config::set('database.connections.pos', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
        DB::purge('pos');

        $pos = DB::connection('pos')->getSchemaBuilder();

        $pos->create('CLOSEDCASH', function (Blueprint $table) {
            $table->string('MONEY')->primary();
            $table->string('HOST');
            $table->integer('HOSTSEQUENCE')->default(1);
            $table->dateTime('DATESTART')->nullable();
            $table->dateTime('DATEEND')->nullable();
            $table->integer('NOSALES')->default(0);
        });

        $pos->create('RECEIPTS', function (Blueprint $table) {
            $table->string('ID')->primary();
            $table->string('MONEY');
            $table->dateTime('DATENEW');
            $table->text('ATTRIBUTES')->nullable();
            $table->string('PERSON')->nullable();
        });

        $pos->create('PAYMENTS', function (Blueprint $table) {
            $table->string('ID')->primary();
            $table->string('RECEIPT');
            $table->string('PAYMENT');
            $table->decimal('TOTAL', 10, 2)->default(0);
            $table->decimal('TENDERED', 10, 2)->nullable();
            $table->string('TRANSID')->nullable();
            $table->text('NOTES')->nullable();
        });

        $pos->create('suppliers', function (Blueprint $table) {
            $table->string('SupplierID')->primary();
            $table->string('Supplier');
        });

        if ($withLegacy) {
            $pos->create('money', function (Blueprint $table) {
                $table->string('ID')->primary();
                $table->dateTime('mDate')->nullable();
                foreach (['cash50', 'cash20', 'cash10', 'cash5', 'cash2', 'cash1', 'cash50c', 'cash20c', 'cash10c',
                    'noteFloat', 'coinFloat', 'cashBack', 'card', 'cheque', 'free', 'debt', 'debtPaidCash',
                    'debtPaidCheque', 'debtPaidCard', 'voucherUsed', 'moneyAdded'] as $column) {
                    $table->decimal($column, 10, 2)->nullable();
                }
            });

            $pos->create('payeePayments', function (Blueprint $table) {
                $table->string('ID')->primary();
                $table->dateTime('pDate')->nullable();
                $table->string('payeeID')->nullable();
                $table->decimal('amount', 10, 2)->default(0);
                $table->integer('sequence')->default(0);
                $table->string('closedCashID');
            });

            $pos->create('dayNotes', function (Blueprint $table) {
                $table->string('ID')->primary();
                $table->text('message');
                $table->string('closedCashID');
            });
        }

        DB::connection('pos')->table('suppliers')->insert([
            ['SupplierID' => 'S1', 'Supplier' => 'Udea'],
            ['SupplierID' => 'S2', 'Supplier' => 'Local Eggs'],
        ]);
    }

    /**
     * A till close with its receipts. $payments is a list of [type, total].
     *
     * @param  array<int, array{0: string, 1: float}>  $payments
     */
    protected function closeTill(string $host, string $moneyId, Carbon $end, array $payments = [], ?Carbon $start = null): void
    {
        DB::connection('pos')->table('CLOSEDCASH')->insert([
            'MONEY' => $moneyId,
            'HOST' => $host,
            'HOSTSEQUENCE' => 1,
            'DATESTART' => ($start ?? $end->copy()->subDay())->toDateTimeString(),
            'DATEEND' => $end->toDateTimeString(),
            'NOSALES' => count($payments),
        ]);

        foreach ($payments as $i => [$type, $total]) {
            $receipt = "{$moneyId}-r{$i}";
            DB::connection('pos')->table('RECEIPTS')->insert([
                'ID' => $receipt,
                'MONEY' => $moneyId,
                'DATENEW' => $end->copy()->subMinutes(count($payments) - $i)->toDateTimeString(),
            ]);
            DB::connection('pos')->table('PAYMENTS')->insert([
                'ID' => "{$receipt}-p",
                'RECEIPT' => $receipt,
                'PAYMENT' => $type,
                'TOTAL' => $total,
                'TENDERED' => $total,
            ]);
        }
    }

    protected function openTill(string $host, string $moneyId, Carbon $start): void
    {
        DB::connection('pos')->table('CLOSEDCASH')->insert([
            'MONEY' => $moneyId,
            'HOST' => $host,
            'HOSTSEQUENCE' => 1,
            'DATESTART' => $start->toDateTimeString(),
            'DATEEND' => null,
            'NOSALES' => 0,
        ]);
    }

    protected function till(string $host): Till
    {
        return Till::forHost($host);
    }
}
