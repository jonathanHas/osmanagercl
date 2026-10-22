<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\Country;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Shop mode print labels, Zebra labels tab: print a saved Zebra label or a
 * translated label on the counter printer.
 *
 * The list comes client-side from `labels.zebra-list` (each item carries its
 * print URL) and the spool from `labels.printer-queue`, which can take seconds,
 * so nothing is prepared here beyond whether this user may cancel spool jobs
 * and the countries for the country of origin select.
 */
class ZebraController extends Controller
{
    public function index(Request $request): View
    {
        return view('shop.zebra', [
            'canCancel' => $request->user()->hasPermission('labels.manage'),
            'countries' => Country::orderBy('name')->get(['id', 'name']),
        ]);
    }
}
