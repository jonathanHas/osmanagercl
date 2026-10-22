<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Shop mode print labels, Shelf labels tab: the shelf-label queue.
 *
 * The queue and today's prints are read client-side from `labels.queue` and
 * `labels.printed-today`, and printing posts to the office `labels.print-a4`
 * page, so there is nothing to prepare here beyond what the view cannot work out
 * for itself: whether this user may empty the queue (and undo that).
 */
class LabelsController extends Controller
{
    public function index(Request $request): View
    {
        return view('shop.labels', [
            'canClear' => $request->user()->hasPermission('labels.manage'),
        ]);
    }
}
