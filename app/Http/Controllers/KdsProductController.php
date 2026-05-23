<?php

namespace App\Http\Controllers;

use App\Models\KdsProduct;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class KdsProductController extends Controller
{
    public function index()
    {
        $products = KdsProduct::orderBy('category_name')
            ->orderBy('product_name')
            ->get();

        return view('kds.products', [
            'products' => $products,
            'activeCount' => $products->where('is_active', true)->count(),
            'inactiveCount' => $products->where('is_active', false)->count(),
        ]);
    }

    public function searchPos(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));

        if ($q === '' || mb_strlen($q) < 2) {
            return response()->json(['results' => []]);
        }

        $existingIds = KdsProduct::pluck('product_id')->all();

        $like = '%'.$q.'%';

        $rows = DB::connection('pos')
            ->table('PRODUCTS as p')
            ->leftJoin('CATEGORIES as c', 'p.CATEGORY', '=', 'c.ID')
            ->where(function ($query) use ($like) {
                $query->where('p.NAME', 'like', $like)
                    ->orWhere('p.CODE', 'like', $like)
                    ->orWhere('p.REFERENCE', 'like', $like);
            })
            ->when(! empty($existingIds), fn ($query) => $query->whereNotIn('p.ID', $existingIds))
            ->select(
                'p.ID as product_id',
                'p.NAME as product_name',
                'p.CODE as code',
                'p.CATEGORY as category_id',
                'c.NAME as category_name',
            )
            ->orderBy('p.NAME')
            ->limit(25)
            ->get();

        return response()->json(['results' => $rows]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'product_id' => 'required|string|max:50|unique:kds_products,product_id',
            'notes' => 'nullable|string|max:255',
        ]);

        $pos = DB::connection('pos')
            ->table('PRODUCTS as p')
            ->leftJoin('CATEGORIES as c', 'p.CATEGORY', '=', 'c.ID')
            ->where('p.ID', $validated['product_id'])
            ->select(
                'p.ID as product_id',
                'p.NAME as product_name',
                'p.CATEGORY as category_id',
                'c.NAME as category_name',
            )
            ->first();

        if (! $pos) {
            return response()->json([
                'success' => false,
                'message' => 'Product not found in POS database.',
            ], 404);
        }

        $kdsProduct = KdsProduct::create([
            'product_id' => $pos->product_id,
            'product_name' => $pos->product_name,
            'category_id' => $pos->category_id,
            'category_name' => $pos->category_name,
            'is_active' => true,
            'notes' => $validated['notes'] ?? null,
        ]);

        return response()->json([
            'success' => true,
            'product' => $kdsProduct,
        ]);
    }

    public function update(Request $request, KdsProduct $kdsProduct): JsonResponse
    {
        $validated = $request->validate([
            'is_active' => 'sometimes|boolean',
            'notes' => 'sometimes|nullable|string|max:255',
        ]);

        $kdsProduct->update($validated);

        return response()->json([
            'success' => true,
            'product' => $kdsProduct->fresh(),
        ]);
    }

    public function destroy(KdsProduct $kdsProduct): JsonResponse
    {
        $kdsProduct->delete();

        return response()->json(['success' => true]);
    }
}
