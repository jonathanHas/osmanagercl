<?php

// DEV/TEST ONLY. Insert a fake till sale into the dev POS copy when no till is
// available: one goods line (€10 + tax), the voucher's line, a paperin payment
// of TENDER and optional CASH. Prints the ticket id so it can be deleted later
// (PAYMENTS, TICKETLINES, TICKETS, RECEIPTS by that id).
//
// Usage: VOUCHER=GVLH4AU7ASAT TENDER=5 TICKET=999901 php artisan tinker --execute="require 'docs/vouchers/scripts/simsale.php';"
use App\Models\Voucher;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

$pos = DB::connection('pos');
$voucher = Voucher::where('code', getenv('VOUCHER') ?: 'GVLH4AU7ASAT')->firstOrFail();
$tender = (float) (getenv('TENDER') ?: 5);
$cash = (float) (getenv('CASH') ?: 0);
$ticketNo = (int) (getenv('TICKET') ?: 999901);

$goods = $pos->table('PRODUCTS')->where('CATEGORY', '!=', app(App\Services\VoucherPosProductService::class)->categoryId())
    ->where('PRICESELL', '>', 3)->first(['ID', 'NAME', 'TAXCAT']);
$tax = $pos->table('TAXES')->where('CATEGORY', $goods->TAXCAT)->value('ID');
$money = $pos->table('RECEIPTS')->orderByDesc('DATENEW')->value('MONEY');

$id = (string) Str::uuid();
$pos->table('RECEIPTS')->insert(['ID' => $id, 'MONEY' => $money, 'DATENEW' => now()->format('Y-m-d H:i:s')]);
$pos->table('TICKETS')->insert(['ID' => $id, 'TICKETTYPE' => 0, 'TICKETID' => $ticketNo, 'PERSON' => '0', 'STATUS' => 0]);
$pos->table('TICKETLINES')->insert(['TICKET' => $id, 'LINE' => 0, 'PRODUCT' => $goods->ID, 'UNITS' => 1, 'PRICE' => 10, 'TAXID' => $tax]);
$pos->table('TICKETLINES')->insert(['TICKET' => $id, 'LINE' => 1, 'PRODUCT' => $voucher->pos_product_id, 'UNITS' => 1, 'PRICE' => 0, 'TAXID' => '000']);
$pos->table('PAYMENTS')->insert(['ID' => (string) Str::uuid(), 'RECEIPT' => $id, 'PAYMENT' => 'paperin', 'TOTAL' => $tender, 'TENDERED' => 0, 'TRANSID' => (string) random_int(100000000000, 999999999999)]);
if ($cash > 0) {
    $pos->table('PAYMENTS')->insert(['ID' => (string) Str::uuid(), 'RECEIPT' => $id, 'PAYMENT' => 'cash', 'TOTAL' => $cash, 'TENDERED' => $cash]);
}

echo "ticket {$ticketNo} id={$id} (goods: {$goods->NAME}, tax {$tax})\n";
