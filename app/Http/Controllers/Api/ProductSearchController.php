<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ProductSearch\ProductSearchCriteria;
use App\Services\ProductSearch\ProductSearchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * GET /api/products/search — the canonical product search JSON used by
 * <x-product-search>. Response shape is documented in
 * docs/features/product-search.md; do not add per-page variations.
 */
class ProductSearchController extends Controller
{
    public function __invoke(Request $request, ProductSearchService $search): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'stocked' => ['nullable', 'boolean'],
            'supplier_id' => ['nullable', 'string', 'max:50'],
            'category_id' => ['nullable', 'string', 'max:50'],
            'exclude' => ['nullable', 'string', 'max:8000'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:'.ProductSearchCriteria::MAX_PER_PAGE],
        ]);

        $exclude = array_values(array_filter(array_map('trim', explode(',', $validated['exclude'] ?? ''))));

        $criteria = new ProductSearchCriteria(
            q: $validated['q'] ?? null,
            stocked: $request->has('stocked') ? $request->boolean('stocked') : true,
            supplierId: $validated['supplier_id'] ?? null,
            categoryId: $validated['category_id'] ?? null,
            excludeIds: array_slice($exclude, 0, 200),
            page: (int) ($validated['page'] ?? 1),
            perPage: (int) ($validated['per_page'] ?? 20),
        );

        return response()->json($search->search($criteria));
    }
}
