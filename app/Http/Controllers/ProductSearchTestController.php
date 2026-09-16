<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

/**
 * Demo / diagnostics page for <x-product-search>: picker mode, list mode and
 * a debug panel showing the request URL, meta and recent took_ms values.
 */
class ProductSearchTestController extends Controller
{
    public function __invoke(): View
    {
        return view('products.search-test');
    }
}
