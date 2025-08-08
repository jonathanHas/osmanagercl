<?php

namespace App\Http\Controllers;

use App\Models\LabelLog;
use App\Models\LabelTemplate;
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

        // Get available label templates
        $labelTemplates = LabelTemplate::active()->orderBy('name')->get();
        $defaultTemplate = LabelTemplate::getDefault();

        // No longer using session-based print queue

        return view('labels.index', compact(
            'recentLabelPrints',
            'productsNeedingLabels',
            'labelTemplates',
            'defaultTemplate'
        ));
    }

    /**
     * Display multiple labels on A4 page for printing.
     */
    public function printA4(Request $request)
    {
        $productIds = $request->input('products', []);
        $templateId = $request->input('template_id');

        if (empty($productIds)) {
            return back()->withErrors(['error' => 'No products selected for printing.']);
        }

        $products = Product::whereIn('ID', $productIds)->get();

        if ($products->isEmpty()) {
            return back()->withErrors(['error' => 'No valid products found.']);
        }

        // Get the selected template or use default
        $template = null;
        if ($templateId) {
            $template = LabelTemplate::active()->find($templateId);
        }
        $template = $template ?? LabelTemplate::getDefault();

        if (! $template) {
            return back()->withErrors(['error' => 'No valid label template found.']);
        }

        // Log the label print events
        foreach ($products as $product) {
            LabelLog::logLabelPrint($product->CODE);
        }

        return view('labels.a4-print', compact('products', 'template'));
    }

    /**
     * Preview multiple labels on A4 pages.
     */
    public function previewA4(Request $request)
    {
        $productIds = $request->input('products', []);
        $templateId = $request->input('template_id');

        if (empty($productIds)) {
            return back()->withErrors(['error' => 'No products selected for preview.']);
        }

        $products = Product::whereIn('ID', $productIds)->get();

        if ($products->isEmpty()) {
            return back()->withErrors(['error' => 'No valid products found.']);
        }

        // Get the selected template or use default
        $template = null;
        if ($templateId) {
            $template = LabelTemplate::active()->find($templateId);
        }
        $template = $template ?? LabelTemplate::getDefault();

        if (! $template) {
            return back()->withErrors(['error' => 'No valid label template found.']);
        }

        // Calculate how many A4 sheets we need
        $labelsPerSheet = $template->labels_per_a4;
        $totalProducts = $products->count();
        $totalSheets = ceil($totalProducts / $labelsPerSheet);

        // Split products into sheets
        $sheets = [];
        for ($sheet = 0; $sheet < $totalSheets; $sheet++) {
            $startIndex = $sheet * $labelsPerSheet;
            $sheetProducts = $products->slice($startIndex, $labelsPerSheet);
            $sheets[] = [
                'number' => $sheet + 1,
                'products' => $sheetProducts,
                'labels_count' => $sheetProducts->count(),
            ];
        }

        return view('labels.a4-preview', compact('products', 'template', 'sheets', 'totalSheets'));
    }

    /**
     * Get products that likely need labels printed.
     */
    private function getProductsNeedingLabels()
    {
        // Get all products with any label-related events in the last 30 days
        $candidateBarcodes = LabelLog::whereIn('event_type', [
            LabelLog::EVENT_NEW_PRODUCT,
            LabelLog::EVENT_PRICE_UPDATE,
            LabelLog::EVENT_REQUEUE_LABEL,
        ])
            ->where('created_at', '>=', now()->subDays(30))
            ->pluck('barcode')
            ->unique();

        $needsLabelsBarcodes = collect();

        // For each candidate barcode, check if it needs a label
        foreach ($candidateBarcodes as $barcode) {
            // Get the most recent print event for this barcode (last 7 days)
            $mostRecentPrint = LabelLog::where('barcode', $barcode)
                ->where('event_type', LabelLog::EVENT_LABEL_PRINT)
                ->where('created_at', '>=', now()->subDays(7))
                ->orderBy('created_at', 'desc')
                ->first();

            // Get the most recent non-print event for this barcode (last 30 days)
            $mostRecentEvent = LabelLog::where('barcode', $barcode)
                ->whereIn('event_type', [
                    LabelLog::EVENT_NEW_PRODUCT,
                    LabelLog::EVENT_PRICE_UPDATE,
                    LabelLog::EVENT_REQUEUE_LABEL,
                ])
                ->where('created_at', '>=', now()->subDays(30))
                ->orderBy('created_at', 'desc')
                ->first();

            // Product needs a label if:
            // 1. There's a recent event (new_product, price_update, or requeue_label)
            // 2. AND either no recent print OR the event is more recent than the print
            if ($mostRecentEvent && (! $mostRecentPrint || $mostRecentEvent->created_at > $mostRecentPrint->created_at)) {
                $needsLabelsBarcodes->push($barcode);
            }
        }

        return Product::whereIn('CODE', $needsLabelsBarcodes)
            ->orderBy('NAME')
            ->get();
    }

    /**
     * Generate preview image for a single label.
     */
    public function previewLabel(string $productId, Request $request)
    {
        $product = Product::findOrFail($productId);
        $templateId = $request->input('template_id');

        // Get the selected template or use default
        $template = null;
        if ($templateId) {
            $template = LabelTemplate::active()->find($templateId);
        }
        $template = $template ?? LabelTemplate::getDefault();

        // Return the label HTML with the specified template
        $labelHtml = $this->labelService->generateLabelHtml($product, $template);

        return response($labelHtml)
            ->header('Content-Type', 'text/html; charset=utf-8');
    }

    /**
     * Add a product back to the "Products Needing Labels" section.
     */
    public function requeueProduct(Request $request)
    {
        $productId = $request->input('product_id');

        if (! $productId) {
            return response()->json(['error' => 'Product ID required'], 400);
        }

        // Find the product
        $product = Product::find($productId);
        if (! $product) {
            return response()->json(['error' => 'Product not found'], 404);
        }

        // Create a requeue event
        LabelLog::logRequeueLabel($product->CODE);

        return response()->json([
            'success' => true,
            'message' => 'Added back to Products Needing Labels',
        ]);
    }

    /**
     * Clear all products from the "Products Needing Labels" section.
     * This moves all products to "Recent Label Prints" by logging label_print events.
     */
    public function clearAllLabels(Request $request)
    {
        try {
            // Get all products that currently need labels
            $productsNeedingLabels = $this->getProductsNeedingLabels();
            $clearedCount = $productsNeedingLabels->count();

            if ($clearedCount === 0) {
                return response()->json([
                    'success' => true,
                    'message' => 'No products needed labels to clear',
                    'cleared_count' => 0,
                ]);
            }

            // Log a label_print event for each product to move it to Recent Label Prints
            foreach ($productsNeedingLabels as $product) {
                LabelLog::logLabelPrint($product->CODE);
            }

            return response()->json([
                'success' => true,
                'message' => "Cleared {$clearedCount} products from labels queue",
                'cleared_count' => $clearedCount,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to clear labels: '.$e->getMessage(),
            ], 500);
        }
    }
}
