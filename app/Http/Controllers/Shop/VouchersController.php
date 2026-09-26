<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Shop mode vouchers: scan a voucher, read its balance and history, deduct.
 *
 * Every write goes to the office endpoints (`vouchers.lookup`, `vouchers.deduct`,
 * `vouchers.activate`), which already hold the locking and the rules, so there is
 * nothing to prepare here beyond the one thing the view cannot work out: whether
 * this user may activate a voucher. Employees get no activate URL and no activate
 * markup; the server refuses them regardless.
 */
class VouchersController extends Controller
{
    public function index(Request $request): View
    {
        return view('shop.vouchers', [
            'canActivate' => $request->user()->hasPermission('vouchers.manage'),
        ]);
    }
}
