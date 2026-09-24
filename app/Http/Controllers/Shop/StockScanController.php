<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

/**
 * Shop mode stock scan: scan a product, adjust its stock, save.
 *
 * The screen talks to the existing stocking endpoints (`stocking.lookup`,
 * `stocking.update-stock`), so there is nothing to prepare server-side.
 */
class StockScanController extends Controller
{
    public function index(): View
    {
        return view('shop.stock-scan');
    }
}
