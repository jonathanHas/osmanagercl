<?php

/**
 * Deposit cycle 1 (spike) rollback — dev POS database only.
 *
 * Run:  php artisan tinker --execute="require 'docs/deposit/scripts/deposit-spike-rollback.php';"
 *
 * Restores Ticket.Buttons from the Ticket.Buttons.pre-deposit backup row,
 * deletes the two deposit event scripts, and clears ATTRIBUTES on the two test
 * products. The deposit products, the Bottle Deposits category, its
 * PRODUCTS_CAT rows and the backup row stay (inert; cycle 2 reuses them).
 * Restart the till in the VM afterwards.
 */

use Illuminate\Support\Facades\DB;

$pos = DB::connection('pos');

if (! ($pos->getConfig('host') === '127.0.0.1'
    && (int) $pos->getConfig('port') === 3307
    && $pos->getConfig('database') === 'unicenta2016')) {
    throw new RuntimeException('Refusing to run: the pos connection is not the dev POS database (127.0.0.1:3307/unicenta2016).');
}

$backup = $pos->table('RESOURCES')->where('NAME', 'Ticket.Buttons.pre-deposit')->value('CONTENT');

if ($backup === null) {
    echo "Ticket.Buttons.pre-deposit backup row not found: Ticket.Buttons left as it is.\n";
} else {
    $n = $pos->table('RESOURCES')->where('NAME', 'Ticket.Buttons')->update(['CONTENT' => $backup]);
    echo 'Ticket.Buttons restored from backup ('.($n ? 'changed' : 'already the backup')."), md5 ".md5($backup)."\n";
}

$n = $pos->table('RESOURCES')->whereIn('NAME', ['script.Deposit.AddLine', 'script.Deposit.Change'])->delete();
echo "Deleted {$n} deposit script resource(s).\n";

$n = $pos->table('PRODUCTS')->whereIn('CODE', ['8711521947614', '8714728001004'])->update(['ATTRIBUTES' => null]);
echo "Cleared ATTRIBUTES on {$n} test product(s).\n";

echo "Done. Restart the till in the VM.\n";
