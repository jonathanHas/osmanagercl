<?php

namespace App\Http\Controllers;

use App\Models\LabelLog;
use App\Models\Product;
use App\Services\LabelService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LabelAreaController extends Controller
{
    /**
     * The label service instance.
     */
    protected LabelService $labelService;

    /**
     * Create a new controller instance.
     */
    public function __construct(LabelService $labelService)
    {
        $this->labelService = $labelService;
    }

    /**
     * Display the label area dashboard.
     */
    public function index(): View
    {
        // Get recent label print requests (last 7 days)
        $recentLabelPrints = LabelLog::with('product')
            ->eventType(LabelLog::EVENT_LABEL_PRINT)
            ->where('created_at', '>=', now()->subDays(7))
            ->orderBy('created_at', 'desc')
            ->limit(50)
            ->get();

        // Get products that need labels (new products or price updates without recent label prints)
        $productsNeedingLabels = $this->getProductsNeedingLabels();

        return view('labels.index', compact('recentLabelPrints', 'productsNeedingLabels'));
    }

    /**
     * Display multiple labels on A4 page for printing.
     */
    public function printA4(Request $request)
    {
        $productIds = $request->input('products', []);
        
        if (empty($productIds)) {
            return back()->withErrors(['error' => 'No products selected for printing.']);
        }

        $products = Product::whereIn('ID', $productIds)->get();
        
        if ($products->isEmpty()) {
            return back()->withErrors(['error' => 'No valid products found.']);
        }

        // Log the label print events
        foreach ($products as $product) {
            LabelLog::logLabelPrint($product->CODE);
        }

        return view('labels.a4-print', compact('products'));
    }

    /**
     * Get products that likely need labels printed.
     */
    private function getProductsNeedingLabels()
    {
        // Get products with new_product or price_update events in last 30 days
        // that don't have recent label_print events
        $recentEventBarcodes = LabelLog::whereIn('event_type', [
            LabelLog::EVENT_NEW_PRODUCT,
            LabelLog::EVENT_PRICE_UPDATE
        ])
            ->where('created_at', '>=', now()->subDays(30))
            ->pluck('barcode')
            ->unique();

        // Get barcodes that have been printed recently (last 7 days)
        $recentlyPrintedBarcodes = LabelLog::eventType(LabelLog::EVENT_LABEL_PRINT)
            ->where('created_at', '>=', now()->subDays(7))
            ->pluck('barcode')
            ->unique();

        // Products that need labels are those with recent events but no recent prints
        $needsLabelsBarcodes = $recentEventBarcodes->diff($recentlyPrintedBarcodes);

        return Product::whereIn('CODE', $needsLabelsBarcodes)
            ->orderBy('NAME')
            ->get();
    }

    /**
     * Generate preview image for a single label.
     */
    public function previewLabel(string $productId)
    {
        $product = Product::findOrFail($productId);
        
        // Return the same label HTML as the print function
        $labelHtml = $this->labelService->generateLabelHtml($product);
        
        return response($labelHtml)
            ->header('Content-Type', 'text/html; charset=utf-8');
    }
}
