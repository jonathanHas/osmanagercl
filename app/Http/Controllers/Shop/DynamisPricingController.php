<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\Delivery;
use App\Services\DynamisPricingService;
use Illuminate\View\View;

/**
 * Shop mode Dynamis pricing (managers and admins): the office pricing page laid
 * out for a phone, to be used beside the boxes — count what is in a box, settle
 * the price, apply it to the till.
 *
 * Nothing is written here. The screen calls the office page's
 * `deliveries.dynamis-pricing.*` endpoints, so both pages share every rule.
 * Uploading an invoice, new products and the transport / markup figures stay on
 * the office page. See docs/features/dynamis-pricing.md.
 */
class DynamisPricingController extends Controller
{
    public function __construct(private DynamisPricingService $pricing) {}

    public function index(): View
    {
        return view('shop.fv-pricing', [
            'invoices' => $this->pricing->recentInvoices(),
        ]);
    }

    public function show(Delivery $delivery): View
    {
        abort_unless($this->pricing->hasPricing($delivery), 404);

        // Till visibility changes through the week; unlinked lines follow it.
        $this->pricing->refreshGuesses($delivery);

        $lines = $this->pricing->lines($delivery);

        return view('shop.fv-pricing-invoice', [
            'delivery' => $delivery,
            'invoice' => $delivery->import_data['dynamis'] ?? [],
            'settings' => $this->pricing->settings($delivery),
            'lines' => $lines['produce'],
            'barcodedCount' => count($lines['barcoded']),
            'warnings' => $delivery->import_data['parser_warnings'] ?? [],
        ]);
    }
}
