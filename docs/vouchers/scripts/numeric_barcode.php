<?php

// DEV/TEST ONLY. Give a voucher's hidden till product a numeric barcode so it
// can be keyed on uniCenta's numeric on-screen keypad. REFERENCE keeps the GV
// code. The till sync matches by PRODUCTS.ID (vouchers.pos_product_id), not by
// CODE, and VoucherPosProductService::sync() only renames, so the numeric CODE
// survives later syncs.
//
// Usage (from the repo root):
//   VOUCHER=GVLH4AU7ASAT php artisan tinker --execute="require 'docs/vouchers/scripts/numeric_barcode.php';"
use App\Models\Voucher;
use App\Services\VoucherPosProductService;
use Illuminate\Support\Facades\DB;

$code = getenv('VOUCHER') ?: 'GVVUF2SUACUM';
$pos = DB::connection('pos');
$voucher = Voucher::where('code', $code)->firstOrFail();

app(VoucherPosProductService::class)->sync($voucher);
$voucher->refresh();

$current = $pos->table('PRODUCTS')->where('ID', $voucher->pos_product_id)->value('CODE');
if (ctype_digit((string) $current)) {
    echo "Already numeric: {$current}\n";
} else {
    $numeric = null;
    for ($n = 1; $n <= 50; $n++) {
        $body = '299000000'.str_pad((string) $n, 3, '0', STR_PAD_LEFT);
        $candidate = $body.((10 - (array_sum(array_map(fn ($d, $i) => $d * ($i % 2 ? 3 : 1), str_split($body), array_keys(str_split($body)))) % 10)) % 10);
        if (! $pos->table('PRODUCTS')->where('CODE', $candidate)->exists()) {
            $numeric = $candidate;
            break;
        }
    }
    if (! $numeric) {
        exit("No free candidate barcode\n");
    }
    $pos->table('PRODUCTS')->where('ID', $voucher->pos_product_id)->update(['CODE' => $numeric]);
    $current = $numeric;
}

$p = $pos->table('PRODUCTS')->where('ID', $voucher->pos_product_id)->first(['NAME', 'CODE', 'REFERENCE', 'PRICESELL']);
echo "Voucher {$voucher->code}: status {$voucher->status}, balance {$voucher->current_balance}\n";
echo "Till product: NAME={$p->NAME}  CODE={$p->CODE}  REFERENCE={$p->REFERENCE}  PRICESELL={$p->PRICESELL}\n";
echo "\n>>> Key this on the till: {$current}\n";
