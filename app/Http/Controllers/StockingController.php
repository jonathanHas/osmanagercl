<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\Request;

class StockingController extends Controller
{
    /**
     * Display the stocking scanner page.
     */
    public function index()
    {
        return view('stocking.index');
    }

    /**
     * Lookup a product by barcode and return stock information.
     */
    public function lookup(Request $request)
    {
        $request->validate(['barcode' => 'required|string']);

        $product = Product::with('stockCurrent', 'category')
            ->where('CODE', $request->barcode)
            ->first();

        if (! $product) {
            return response()->json([
                'found' => false,
                'message' => 'Product not found',
            ]);
        }

        return response()->json([
            'found' => true,
            'product' => [
                'name' => $product->NAME,
                'code' => $product->CODE,
                'category' => $product->category?->NAME ?? 'Uncategorized',
            ],
            'stock' => $product->getCurrentStock(),
        ]);
    }
}
