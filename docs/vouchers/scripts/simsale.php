<?php

// DEV/TEST ONLY. Insert a fake till sale into the dev POS copy when no till is
// available: one goods line (€10 + tax) and the voucher's line. Prints the
// ticket id so it can be deleted later (PAYMENTS, TICKETLINES, TICKETS,
// RECEIPTS by that id).
//
// Redemption (default): the voucher line at PRICE 0, a paperin payment of
// TENDER and an optional CASH payment.
//   VOUCHER=GVLH4AU7ASAT TENDER=5 TICKET=999901 php artisan tinker --execute="require 'docs/vouchers/scripts/simsale.php';"
//
// Sale (SALE=1, vouchers cycle 3): the voucher sold as an item. The voucher line
// is at PRICE = the voucher's face value (or PRICE=<n>), UNITS=<n> (default 1),
// and one payment of type PAY (default magcard) for the whole ticket total.
//   SALE=1 VOUCHER=GVxxxxxxxxxx TICKET=999910 php artisan tinker --execute="require 'docs/vouchers/scripts/simsale.php';"
//   SALE=1 UNITS=2 PAY=cash VOUCHER=... TICKET=999911 ...
use App\Models\Voucher;
use App\Services\VoucherPosProductService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

$pos = DB::connection('pos');
$voucher = Voucher::where('code', getenv('VOUCHER') ?: 'GVLH4AU7ASAT')->firstOrFail();
$ticketNo = (int) (getenv('TICKET') ?: 999901);
$sale = (bool) getenv('SALE');

$goods = $pos->table('PRODUCTS')->where('CATEGORY', '!=', app(VoucherPosProductService::class)->categoryId())
    ->where('PRICESELL', '>', 3)->first(['ID', 'NAME', 'TAXCAT']);
$tax = $pos->table('TAXES')->where('CATEGORY', $goods->TAXCAT)->first(['ID', 'RATE']);
$money = $pos->table('RECEIPTS')->orderByDesc('DATENEW')->value('MONEY');

$voucherPrice = $sale ? (float) (getenv('PRICE') !== false ? getenv('PRICE') : $voucher->face_value) : 0.0;
$voucherUnits = $sale ? (float) (getenv('UNITS') ?: 1) : 1.0;

$id = (string) Str::uuid();
$pos->table('RECEIPTS')->insert(['ID' => $id, 'MONEY' => $money, 'DATENEW' => now()->format('Y-m-d H:i:s')]);
$pos->table('TICKETS')->insert(['ID' => $id, 'TICKETTYPE' => 0, 'TICKETID' => $ticketNo, 'PERSON' => '0', 'STATUS' => 0]);
$pos->table('TICKETLINES')->insert(['TICKET' => $id, 'LINE' => 0, 'PRODUCT' => $goods->ID, 'UNITS' => 1, 'PRICE' => 10, 'TAXID' => $tax->ID]);
$pos->table('TICKETLINES')->insert(['TICKET' => $id, 'LINE' => 1, 'PRODUCT' => $voucher->pos_product_id, 'UNITS' => $voucherUnits, 'PRICE' => $voucherPrice, 'TAXID' => '000']);

$payments = [];
if ($sale) {
    $payments[] = [getenv('PAY') ?: 'magcard', round(10 * (1 + (float) $tax->RATE) + $voucherPrice * $voucherUnits, 2)];
} else {
    $payments[] = ['paperin', (float) (getenv('TENDER') ?: 5)];
    if ((float) (getenv('CASH') ?: 0) > 0) {
        $payments[] = ['cash', (float) getenv('CASH')];
    }
}
foreach ($payments as [$type, $total]) {
    $pos->table('PAYMENTS')->insert([
        'ID' => (string) Str::uuid(),
        'RECEIPT' => $id,
        'PAYMENT' => $type,
        'TOTAL' => $total,
        'TENDERED' => $type === 'paperin' ? 0 : $total,
        'TRANSID' => (string) random_int(100000000000, 999999999999),
    ]);
}

echo "ticket {$ticketNo} id={$id} (goods: {$goods->NAME}, tax {$tax->ID}; voucher line ".($sale ? "SALE {$voucherUnits} × €{$voucherPrice}" : 'redemption €0').'; payments '.json_encode($payments).")\n";
