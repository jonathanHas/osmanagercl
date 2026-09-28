<?php

// DEV/TEST ONLY. Put a voucher back to active with a balance so the till test
// can be repeated. Writes an audit row (type activate) rather than deleting
// history, then renames the till product to the new balance.
//
// Usage: VOUCHER=GVVUF2SUACUM BALANCE=20 php artisan tinker --execute="require 'docs/vouchers/scripts/reset_voucher.php';"
use App\Models\Voucher;
use App\Models\VoucherTransaction;
use App\Services\VoucherPosProductService;

$code = getenv('VOUCHER') ?: 'GVVUF2SUACUM';
$balance = round((float) (getenv('BALANCE') ?: 20), 2);

$voucher = Voucher::where('code', $code)->firstOrFail();
$voucher->forceFill(['status' => Voucher::STATUS_ACTIVE, 'current_balance' => $balance])->save();
$voucher->transactions()->create([
    'type' => VoucherTransaction::TYPE_ACTIVATE,
    'amount' => 0,
    'balance_after' => $balance,
    'note' => 'Dev reset for till testing',
]);
app(VoucherPosProductService::class)->sync($voucher->fresh());

echo "{$code}: active, balance {$balance}\n";
