<?php

/**
 * Deposit cycle 1 (spike) setup — dev POS database only.
 *
 * Run:  php artisan tinker --execute="require 'docs/deposit/scripts/deposit-spike-setup.php';"
 * Undo: docs/deposit/scripts/deposit-spike-rollback.php
 *
 * Idempotent: every part skips what already exists, so a second run changes
 * nothing. Parts:
 *   0. Ticket.Buttons backup (file + RESOURCES row Ticket.Buttons.pre-deposit)
 *   1. CATEGORIES row "Bottle Deposits"
 *   2. Six deposit / refund service products (TAXCAT 000)
 *   3. PRODUCTS_CAT rows for the three refund products (catalogue buttons)
 *   4. deposit.* properties in ATTRIBUTES of the two test products
 *   5. RESOURCES script.Deposit.AddLine / script.Deposit.Change from resources/pos/deposit/*.bsh
 *   6. Ticket.Buttons wired to the two scripts (result also written to a file)
 * Restart the till in the VM after running it.
 *
 * CATEGORIES, PRODUCTS_CAT and RESOURCES have no Eloquent model; the query
 * builder is used for them (plan Context: recorded exception for this spike).
 */

use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

$pos = DB::connection('pos');

if (! ($pos->getConfig('host') === '127.0.0.1'
    && (int) $pos->getConfig('port') === 3307
    && $pos->getConfig('database') === 'unicenta2016')) {
    throw new RuntimeException('Refusing to run: the pos connection is not the dev POS database (127.0.0.1:3307/unicenta2016).');
}

$dir = base_path('docs/deposit/scripts');
$say = function (string $part, string $what) {
    echo str_pad($part, 26).$what."\n";
};

// 0. Ticket.Buttons backup ---------------------------------------------------
$origFile = "$dir/Ticket.Buttons.2026-10-03.orig.xml";
$backup = $pos->table('RESOURCES')->where('NAME', 'Ticket.Buttons.pre-deposit')->value('CONTENT');

if ($backup === null) {
    $current = $pos->table('RESOURCES')->where('NAME', 'Ticket.Buttons')->value('CONTENT');
    if (str_contains($current, 'script.Deposit.')) {
        throw new RuntimeException('Ticket.Buttons is already wired but no backup row exists: restore it by hand from '.$origFile);
    }
    $pos->table('RESOURCES')->insert([
        'ID' => (string) Str::uuid(),
        'NAME' => 'Ticket.Buttons.pre-deposit',
        'RESTYPE' => 0,
        'CONTENT' => $current,
    ]);
    $backup = $current;
    $say('backup row', 'created, md5 '.md5($backup));
} else {
    $say('backup row', 'already present, md5 '.md5($backup));
}

if (! is_file($origFile)) {
    file_put_contents($origFile, $backup);
    $say('backup file', 'written');
} elseif (md5_file($origFile) !== md5($backup)) {
    throw new RuntimeException("$origFile differs from the Ticket.Buttons.pre-deposit row: check by hand.");
} else {
    $say('backup file', 'already present, matches the row');
}

// 1. Category ------------------------------------------------------------------
$categoryId = $pos->table('CATEGORIES')->where('NAME', 'Bottle Deposits')->value('ID');

if ($categoryId === null) {
    $categoryId = (string) Str::uuid();
    $pos->table('CATEGORIES')->insert([
        'ID' => $categoryId,
        'NAME' => 'Bottle Deposits',
        'PARENTID' => null,
        'CATSHOWNAME' => 1,
    ]);
    $say('category', "created $categoryId");
} else {
    $say('category', "already present $categoryId");
}

// 2. Products ------------------------------------------------------------------
$products = [
    'DEP-025' => ['Bottle deposit 0.25', 0.25],
    'DEP-070' => ['Bottle deposit 0.70', 0.70],
    'DEP-010' => ['Bottle deposit 0.10', 0.10],
    'DEP-025-RET' => ['Bottle deposit refund 0.25', -0.25],
    'DEP-070-RET' => ['Bottle deposit refund 0.70', -0.70],
    'DEP-010-RET' => ['Bottle deposit refund 0.10', -0.10],
];
$ids = [];

foreach ($products as $code => [$name, $price]) {
    $product = Product::where('CODE', $code)->first();

    if ($product) {
        $say("product $code", "already present {$product->ID}");
    } else {
        $product = Product::create([
            'ID' => (string) Str::uuid(),
            'NAME' => $name,
            'CODE' => $code,
            'REFERENCE' => $code,
            'CATEGORY' => $categoryId,
            'TAXCAT' => '000',
            'PRICESELL' => $price,
            'PRICEBUY' => 0,
        ]);
        // A service product: the till records no stock movement for it.
        $product->forceFill(['ISSERVICE' => 1])->save();
        $say("product $code", "created {$product->ID}");
    }

    $ids[$code] = $product->ID;
}

// 3. Catalogue buttons for the refund products only -----------------------------
$order = 0;
foreach (['DEP-025-RET', 'DEP-070-RET', 'DEP-010-RET'] as $code) {
    $order++;
    if ($pos->table('PRODUCTS_CAT')->where('PRODUCT', $ids[$code])->exists()) {
        $say("catalogue $code", 'already present');
    } else {
        $pos->table('PRODUCTS_CAT')->insert(['PRODUCT' => $ids[$code], 'CATORDER' => $order]);
        $say("catalogue $code", "created, CATORDER $order");
    }
}

// 4. Mark the two test products -------------------------------------------------
$depositXml = function (string $id, string $name, string $price): string {
    return '<?xml version="1.0" encoding="UTF-8" standalone="no"?>'."\n"
        .'<!DOCTYPE properties SYSTEM "http://java.sun.com/dtd/properties.dtd">'."\n"
        ."<properties>\n"
        ."<comment>osmanager deposit</comment>\n"
        ."<entry key=\"deposit.id\">$id</entry>\n"
        ."<entry key=\"deposit.name\">$name</entry>\n"
        ."<entry key=\"deposit.price\">$price</entry>\n"
        ."</properties>\n";
};
$marks = [
    '8711521947614' => $depositXml($ids['DEP-025'], 'Bottle deposit 0.25', '0.25'),
    '8714728001004' => $depositXml($ids['DEP-070'], 'Bottle deposit 0.70', '0.70'),
];

foreach ($marks as $code => $xml) {
    $current = $pos->table('PRODUCTS')->where('CODE', $code)->value('ATTRIBUTES');
    if ($current === $xml) {
        $say("attributes $code", 'already present');
    } else {
        $pos->table('PRODUCTS')->where('CODE', $code)->update(['ATTRIBUTES' => $xml]);
        $say("attributes $code", $current === null ? 'set' : 'updated');
    }
}

// 5. Till scripts ----------------------------------------------------------------
foreach (['script.Deposit.AddLine', 'script.Deposit.Change'] as $name) {
    // The deployable copies (installed by `deposits:install-till`) are the single source.
    $bytes = file_get_contents(resource_path("pos/deposit/$name.bsh"));
    $current = $pos->table('RESOURCES')->where('NAME', $name)->first();

    if ($current === null) {
        $pos->table('RESOURCES')->insert([
            'ID' => (string) Str::uuid(),
            'NAME' => $name,
            'RESTYPE' => 0,
            'CONTENT' => $bytes,
        ]);
        $say($name, 'created, md5 '.md5($bytes));
    } elseif ($current->CONTENT === $bytes) {
        $say($name, 'already present, md5 '.md5($bytes));
    } else {
        $pos->table('RESOURCES')->where('NAME', $name)->update(['CONTENT' => $bytes]);
        $say($name, 'updated, md5 '.md5($bytes));
    }
}

// 6. Wire the events in Ticket.Buttons -------------------------------------------
$anchor = '        <event key="ticket.close" code="Ticket.Close"/>'."\n";
if (substr_count($backup, $anchor) !== 1) {
    throw new RuntimeException('The ticket.close event line was not found exactly once in the Ticket.Buttons backup.');
}
$wired = str_replace($anchor, $anchor
    .'        <event key="ticket.addline" code="script.Deposit.AddLine"/>'."\n"
    .'        <event key="ticket.change" code="script.Deposit.Change"/>'."\n", $backup);
file_put_contents("$dir/Ticket.Buttons.2026-10-03.deposit.xml", $wired);

$current = $pos->table('RESOURCES')->where('NAME', 'Ticket.Buttons')->value('CONTENT');
if ($current === $wired) {
    $say('Ticket.Buttons', 'already wired, md5 '.md5($wired));
} else {
    $pos->table('RESOURCES')->where('NAME', 'Ticket.Buttons')->update(['CONTENT' => $wired]);
    $say('Ticket.Buttons', 'wired, md5 '.md5($wired));
}

echo "Done. Restart the till in the VM.\n";
