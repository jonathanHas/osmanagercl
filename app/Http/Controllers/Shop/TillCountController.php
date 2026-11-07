<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Services\Cash\TillCountPage;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Shop mode: the end-of-day till count. The office page shows the same form.
 */
class TillCountController extends Controller
{
    public function __construct(private readonly TillCountPage $page) {}

    public function index(Request $request): View
    {
        $page = $this->page->build($request);

        return view('shop.till-count', $page + [
            'clientState' => $this->page->clientState($page),
        ]);
    }
}
