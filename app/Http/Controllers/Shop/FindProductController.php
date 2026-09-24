<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

/**
 * Shop mode find product: search by name or barcode, see price and stock.
 *
 * Read-only by design. The screen talks to the canonical product search
 * endpoint (`api.products.search`), so there is nothing to prepare
 * server-side; creating, editing and stock changes stay on the office pages.
 */
class FindProductController extends Controller
{
    public function index(): View
    {
        return view('shop.find-product');
    }
}
