<?php

namespace Tests\Concerns;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

/**
 * Some models pin `protected $connection = 'mysql'` (ProductTranslation,
 * DeliveryLabelPrint, WasteLog and a handful of others). Under phpunit the default
 * connection is an in-memory sqlite database, so those models would otherwise look for
 * a real MySQL server.
 *
 * Redefining 'mysql' as its own sqlite database is not enough: every `:memory:` PDO is a
 * separate database, and RefreshDatabase only migrates the default connection. So point
 * the 'mysql' name at the *same PDO* the test database already migrated.
 */
trait AliasesMysqlConnection
{
    protected function aliasMysqlConnectionToTestDatabase(): void
    {
        $default = Config::get('database.default');

        if (Config::get("database.connections.{$default}.driver") !== 'sqlite') {
            return;
        }

        Config::set('database.connections.mysql', Config::get("database.connections.{$default}"));

        DB::purge('mysql');

        $pdo = DB::connection($default)->getPdo();

        DB::connection('mysql')->setPdo($pdo)->setReadPdo($pdo);
    }
}
