<?php

namespace App\Http\Controllers;

use App\Services\TillProductBrowser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TillProductBrowserController extends Controller
{
    public function __construct(private readonly TillProductBrowser $browser) {}

    public function categories(): JsonResponse
    {
        $categories = $this->browser->categories()->map(fn ($c) => [
            'id' => $c->ID,
            'name' => $c->NAME,
            'parent_id' => $c->PARENTID ?? null,
        ]);

        return response()->json(['data' => $categories]);
    }

    public function products(Request $request): JsonResponse
    {
        $categoryId = (string) $request->query('category', '');
        if ($categoryId === '') {
            return response()->json(['data' => []]);
        }

        $products = $this->browser->productsInCategory($categoryId);

        return response()->json(['data' => $this->mapProducts($products)]);
    }

    public function search(Request $request): JsonResponse
    {
        $term = (string) $request->query('q', '');
        $products = $this->browser->searchTillVisible($term, (int) $request->query('limit', 25));

        return response()->json(['data' => $this->mapProducts($products)]);
    }

    private function mapProducts($products): array
    {
        return $products->map(fn ($p) => [
            'id' => $p->ID,
            'code' => $p->CODE,
            'name' => $p->NAME,
            'category_id' => $p->CATEGORY,
            'unit_price_net' => (float) $p->PRICESELL,
            'vat_rate' => (float) $p->getVatRate(),
            'gross_price' => round((float) $p->PRICESELL * (1 + (float) $p->getVatRate()), 2),
        ])->all();
    }
}
