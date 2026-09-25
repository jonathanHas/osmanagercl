<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Shop mode print labels: the shelf-label queue.
 *
 * The queue itself is read client-side from `labels.queue`, and printing posts to
 * the office `labels.print-a4` page, so there is nothing to prepare here beyond
 * what the view cannot work out for itself: where Zebra labels live, and whether
 * this user may empty the queue.
 */
class LabelsController extends Controller
{
    public function index(Request $request): View
    {
        return view('shop.labels', [
            'zebraUrl' => route('labels.zebra'),
            'canClear' => $request->user()->hasPermission('labels.manage'),
        ]);
    }
}
