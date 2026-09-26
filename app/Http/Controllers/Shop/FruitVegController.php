<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

/**
 * Shop mode fruit & veg: the waste log and the harvest log.
 *
 * Both screens read and write through the office endpoints under `fruit-veg.`,
 * which hold all the rules — what counts as F&V, what belongs to Jon, whether a
 * day's entry is replaced or accumulated — so there is nothing to prepare here.
 * Availability and F&V labels are separate screens in later cycles; the shared
 * nav lists only these two until they exist.
 */
class FruitVegController extends Controller
{
    public function waste(): View
    {
        return view('shop.fv-waste');
    }

    public function harvest(): View
    {
        return view('shop.fv-harvest');
    }
}
