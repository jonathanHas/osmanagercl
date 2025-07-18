<?php

namespace App\Http\Controllers;

use App\Repositories\ProductRepository;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProductController extends Controller
{
    /**
     * The product repository instance.
     */
    protected ProductRepository $productRepository;

    /**
     * Create a new controller instance.
     */
    public function __construct(ProductRepository $productRepository)
    {
        $this->productRepository = $productRepository;
    }

    /**
     * Display a listing of products.
     */
    public function index(Request $request): View
    {
        $search = $request->get('search');
        $activeOnly = $request->boolean('active_only');
        $perPage = $request->get('per_page', 20);

        if ($search) {
            $products = $this->productRepository->searchProducts(
                search: $search,
                activeOnly: $activeOnly,
                perPage: $perPage
            );
        } elseif ($activeOnly) {
            $products = $this->productRepository->getActiveProducts($perPage);
        } else {
            $products = $this->productRepository->getAllProducts($perPage);
        }

        $statistics = $this->productRepository->getStatistics();

        return view('products.index', compact('products', 'statistics', 'search', 'activeOnly'));
    }

    /**
     * Display the specified product.
     */
    public function show(string $id): View
    {
        $product = $this->productRepository->findById($id);

        if (!$product) {
            abort(404, 'Product not found');
        }

        return view('products.show', compact('product'));
    }
}