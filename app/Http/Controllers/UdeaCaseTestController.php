<?php

namespace App\Http\Controllers;

use App\Models\OrderSession;
use App\Services\SupplierService;
use App\Services\UdeaScrapingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Feasibility test page for reading Udea case vs single-unit buy options.
 *
 * The page renders immediately with a row per single-unit line item (case units == 1) and
 * then scrapes each product's Udea webshop card in the background via scrape(), filling the
 * row in real time. It shows the parsed purchase tiers (units-per-case, single-unit
 * availability/price, per-unit case price) next to our stored CaseUnits, plus the raw card
 * HTML so the heuristic parser in UdeaScrapingService::extractPurchaseTiers() can be refined.
 * Diagnostic only — no order-page integration.
 */
class UdeaCaseTestController extends Controller
{
    public function __invoke(
        Request $request,
        OrderSession $order,
        SupplierService $supplierService
    ): View {
        $udeaIds = config('suppliers.external_links.udea.supplier_ids', [5, 44, 85]);
        $isUdeaOrder = in_array((int) $order->supplier_id, array_map('intval', $udeaIds), true);

        // ?all=1 also lists case products (image only, never scraped).
        $showAll = $request->boolean('all');
        $forceRefresh = $request->boolean('fresh');

        $order->load(['items.product.supplierLink', 'supplier']);

        $rows = [];
        $skippedNoCode = 0;
        $skippedCaseProduct = 0;

        foreach ($order->items as $item) {
            $product = $item->product;
            if (! $product) {
                continue;
            }

            $context = $item->context_data ?? [];
            $supplierCode = $context['supplier_code'] ?? optional($product->supplierLink)->SupplierCode;
            $ourCaseUnits = optional($product->supplierLink)->CaseUnits
                ?? ($context['case_units'] ?? null);

            if (empty($supplierCode)) {
                $skippedNoCode++;

                continue;
            }

            // Only single-unit products (case units == 1) are worth scraping for a case option.
            $isSingleUnit = $ourCaseUnits !== null && (int) $ourCaseUnits === 1;
            if (! $isSingleUnit) {
                $skippedCaseProduct++;
                if ($showAll) {
                    $rows[] = [
                        'product' => $product,
                        'supplier_code' => $supplierCode,
                        'our_case_units' => $ourCaseUnits,
                        'scrape' => false,
                    ];
                }

                continue;
            }

            $rows[] = [
                'product' => $product,
                'supplier_code' => (string) $supplierCode,
                'our_case_units' => $ourCaseUnits,
                'scrape' => true,
            ];
        }

        return view('tools.udea-case-test', [
            'order' => $order,
            'isUdeaOrder' => $isUdeaOrder,
            'rows' => $rows,
            'showAll' => $showAll,
            'forceRefresh' => $forceRefresh,
            'toScrapeCount' => collect($rows)->where('scrape', true)->count(),
            'skippedNoCode' => $skippedNoCode,
            'skippedCaseProduct' => $skippedCaseProduct,
            'supplierService' => $supplierService,
        ]);
    }

    /**
     * Scrape a small batch of Udea supplier codes and return the parsed card data as JSON.
     * Called repeatedly by the page's JS so rows fill in progressively. One service instance
     * authenticates once and reuses the session across the batch's codes.
     */
    public function scrape(Request $request, UdeaScrapingService $udeaService): JsonResponse
    {
        $codes = array_slice((array) $request->input('codes', []), 0, 8);
        $fresh = $request->boolean('fresh');

        $results = [];
        foreach ($codes as $code) {
            $code = trim((string) $code);
            if ($code === '') {
                continue;
            }

            try {
                $debug = $udeaService->debugProductCard($code, $fresh);
                $results[$code] = [
                    'data' => $debug['data'] ?? null,
                    'card_html' => $debug['card_html'] ?? null,
                    'from_cache' => $debug['from_cache'] ?? false,
                    'scraped_at' => $debug['scraped_at'] ?? null,
                    'error' => null,
                ];
            } catch (\Throwable $e) {
                $results[$code] = [
                    'data' => null,
                    'card_html' => null,
                    'from_cache' => false,
                    'scraped_at' => null,
                    'error' => $e->getMessage(),
                ];
            }
        }

        return response()->json(['results' => $results]);
    }
}
