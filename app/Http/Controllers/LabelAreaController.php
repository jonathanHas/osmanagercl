<?php

namespace App\Http\Controllers;

use App\Models\Country;
use App\Models\LabelLog;
use App\Models\LabelTemplate;
use App\Models\Product;
use App\Models\ProductTranslation;
use App\Models\ZebraLabel;
use App\Services\LabelService;
use App\Services\SupplierService;
use App\Services\TillVisibilityService;
use App\Services\ZplGeneratorService;
use Gemini\Data\Blob;
use Gemini\Enums\MimeType;
use Gemini\Laravel\Facades\Gemini;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class LabelAreaController extends Controller
{
    protected LabelService $labelService;

    protected ZplGeneratorService $zplGenerator;

    protected TillVisibilityService $tillVisibilityService;

    public function __construct(LabelService $labelService, ZplGeneratorService $zplGenerator, TillVisibilityService $tillVisibilityService)
    {
        $this->labelService = $labelService;
        $this->zplGenerator = $zplGenerator;
        $this->tillVisibilityService = $tillVisibilityService;
    }

    /**
     * Label hub landing page.
     */
    public function hub(Request $request): View
    {
        $zebraLabelCount = ZebraLabel::active()->whereNotNull('product_code')->count();
        $translationCount = ProductTranslation::count();
        $labelCounts = $this->getLabelCountsByEventType();
        $needsLabelsCount = array_sum($labelCounts);

        return view('labels.hub', compact('zebraLabelCount', 'translationCount', 'needsLabelsCount'));
    }

    /**
     * Zebra labels page — products on till with saved labels.
     */
    public function zebra(Request $request): View
    {
        $search = $request->input('search');
        $view = $request->input('view', 'zebra');

        // Default empty values
        $zebraLabels = [];
        $otherLabels = [];
        $standAloneLabels = [];
        $translationsByCategory = [];

        $supplierService = null;
        if ($view === 'translations') {
            $supplierService = app(SupplierService::class);
            [$translationsByCategory, $translationProducts] = $this->loadTranslationsByCategory($search);
        } else {
            $zebraLabelsQuery = ZebraLabel::active()->whereNotNull('product_code');

            if ($search) {
                $zebraLabelsQuery->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('product_code', 'like', "%{$search}%");
                });
            }

            $allZebraLabels = $zebraLabelsQuery->get();

            $standAloneQuery = ZebraLabel::active()->whereNull('product_code');
            if ($search) {
                $standAloneQuery->where('name', 'like', "%{$search}%");
            }
            $standAloneLabels = $standAloneQuery->get()->map(fn ($label) => [
                'id' => $label->id,
                'name' => $label->name,
                'product_name' => null,
                'product_code' => null,
                'width_mm' => $label->label_width_mm,
                'height_mm' => $label->label_height_mm,
                'default_copies' => $label->default_copies ?? 1,
                'mismatches' => null,
                'fields' => ZebraLabel::extractTextFields($label->zpl_content),
            ])->values()->toArray();

            $countryNames = Country::pluck('name')->toArray();

            foreach ($allZebraLabels as $label) {
                $product = $label->product;
                if (! $product) {
                    continue;
                }

                $isOnTill = $this->tillVisibilityService->isVisibleOnTill($product->ID);

                $fields = ZebraLabel::extractTextFields($label->zpl_content);
                $mismatches = [];

                $priceField = ZebraLabel::findPriceField($fields);
                if ($priceField) {
                    $dbPrice = (float) $product->getGrossPrice();
                    if (abs($priceField[1] - $dbPrice) > 0.005) {
                        $mismatches['price'] = [
                            'field_index' => $priceField[0],
                            'label_value' => number_format($priceField[1], 2),
                            'db_value' => number_format($dbPrice, 2),
                            'new_field' => '\\15'.number_format($dbPrice, 2),
                        ];
                    }
                }

                $product->load('vegDetails.country');
                $countryField = ZebraLabel::findCountryField($fields, $countryNames);
                $dbCountry = $product->vegDetails?->country?->name;
                if ($countryField && $dbCountry && $countryField[1] !== $dbCountry) {
                    $mismatches['country'] = [
                        'field_index' => $countryField[0],
                        'label_value' => $countryField[1],
                        'db_value' => $dbCountry,
                        'new_field' => $dbCountry,
                    ];
                }

                $labelData = [
                    'id' => $label->id,
                    'name' => $label->name,
                    'product_name' => $product->NAME,
                    'product_code' => $label->product_code,
                    'width_mm' => $label->label_width_mm,
                    'height_mm' => $label->label_height_mm,
                    'default_copies' => $label->default_copies ?? 1,
                    'mismatches' => $mismatches ?: null,
                    'fields' => $fields,
                ];

                if ($isOnTill) {
                    $zebraLabels[] = $labelData;
                } else {
                    $otherLabels[] = $labelData;
                }
            }
        }

        $translationProducts = $translationProducts ?? collect();

        return view('labels.zebra', compact('zebraLabels', 'otherLabels', 'standAloneLabels', 'search', 'view', 'translationsByCategory', 'supplierService', 'translationProducts'));
    }

    /**
     * Load translated labels grouped by product category.
     */
    private function loadTranslationsByCategory(?string $search): array
    {
        $query = ProductTranslation::with('user')->latest();

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('product_code', 'like', "%{$search}%")
                    ->orWhereRaw("JSON_UNQUOTE(JSON_EXTRACT(label_data, '$.product_name')) LIKE ?", ["%{$search}%"]);
            });
        }

        $translations = $query->get();

        // Batch-load products to avoid N+1
        $productCodes = $translations->pluck('product_code')->filter()->unique()->values()->toArray();
        $products = Product::with(['category', 'supplierLink'])
            ->whereIn('CODE', $productCodes)
            ->get()
            ->keyBy('CODE');

        $grouped = [];
        foreach ($translations as $t) {
            $product = $products->get($t->product_code);
            $category = $product?->category_name ?? 'Uncategorized';

            $grouped[$category][] = [
                'id' => $t->id,
                'product_name' => $t->label_data['product_name'] ?? 'Unknown',
                'db_product_name' => $product?->NAME,
                'product_code' => $t->product_code,
                'label_size' => $t->label_size,
                'created_at' => $t->created_at->format('M j, Y'),
                'user_name' => $t->user?->name ?? '',
            ];
        }

        ksort($grouped);

        return [$grouped, $products];
    }

    /**
     * Shelf labels dashboard (products needing labels, A4 print queue).
     */
    public function shelfLabels(Request $request): View
    {
        // Get filter parameters from request
        $filters = $request->input('filters', []); // Array of event types to show

        // Get recent label print requests (last 7 days)
        // Increased limit to 200 to allow more restoration options
        $recentLabelPrints = LabelLog::with('product')
            ->eventType(LabelLog::EVENT_LABEL_PRINT)
            ->where('created_at', '>=', now()->subDays(7))
            ->orderBy('created_at', 'desc')
            ->limit(200)
            ->get();

        // Group recent prints by time windows (5-minute intervals)
        $groupedLabelPrints = $this->groupLabelPrintsByTime($recentLabelPrints);

        // Get products that need labels (with optional filtering)
        $productsNeedingLabels = $this->getProductsNeedingLabels($filters);

        // Get counts by event type for filter display
        $labelCounts = $this->getLabelCountsByEventType();

        // Get available label templates
        $labelTemplates = LabelTemplate::active()->orderBy('name')->get();
        $defaultTemplate = LabelTemplate::getDefault();

        // No longer using session-based print queue

        return view('labels.index', compact(
            'recentLabelPrints',
            'groupedLabelPrints',
            'productsNeedingLabels',
            'labelCounts',
            'filters',
            'labelTemplates',
            'defaultTemplate'
        ));
    }

    /**
     * Group label prints by time windows (5-minute intervals).
     */
    private function groupLabelPrintsByTime($labelPrints)
    {
        $groups = [];

        foreach ($labelPrints as $labelPrint) {
            // Round to nearest 5-minute interval for grouping
            $timestamp = $labelPrint->created_at;
            $roundedMinutes = floor($timestamp->minute / 5) * 5;
            $groupKey = $timestamp->copy()->setMinute($roundedMinutes)->setSecond(0)->format('Y-m-d H:i:s');

            if (! isset($groups[$groupKey])) {
                $groups[$groupKey] = [
                    'timestamp' => $timestamp->copy()->setMinute($roundedMinutes)->setSecond(0),
                    'display_time' => $timestamp->copy()->setMinute($roundedMinutes)->setSecond(0)->format('M j, Y H:i'),
                    'products' => [],
                    'count' => 0,
                ];
            }

            $groups[$groupKey]['products'][] = $labelPrint;
            $groups[$groupKey]['count']++;
        }

        // Sort groups by timestamp (newest first)
        uasort($groups, function ($a, $b) {
            return $b['timestamp']->timestamp - $a['timestamp']->timestamp;
        });

        return $groups;
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

        // Calculate how many A4 sheets we need
        $labelsPerSheet = $template->labels_per_a4;
        $totalProducts = $products->count();
        $totalSheets = ceil($totalProducts / $labelsPerSheet);

        // Split products into sheets
        $sheets = [];
        for ($sheet = 0; $sheet < $totalSheets; $sheet++) {
            $startIndex = $sheet * $labelsPerSheet;
            $sheetProducts = $products->slice($startIndex, $labelsPerSheet)->values();
            $sheets[] = [
                'number' => $sheet + 1,
                'products' => $sheetProducts,
                'labels_count' => $sheetProducts->count(),
            ];
        }

        return view('labels.a4-print', compact('products', 'template', 'sheets', 'totalSheets'));
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
            $sheetProducts = $products->slice($startIndex, $labelsPerSheet)->values();
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
    private function getProductsNeedingLabels(array $filters = [])
    {
        // Get all products with any label-related events in the last 30 days
        $candidateEvents = LabelLog::whereIn('event_type', [
            LabelLog::EVENT_NEW_PRODUCT,
            LabelLog::EVENT_PRICE_UPDATE,
            LabelLog::EVENT_REQUEUE_LABEL,
        ])
            ->where('created_at', '>=', now()->subDays(30))
            ->orderBy('created_at', 'desc')
            ->get();

        $needsLabelsData = collect();

        // Group events by barcode and find the most recent for each
        $eventsByBarcode = $candidateEvents->groupBy('barcode');

        foreach ($eventsByBarcode as $barcode => $events) {
            // Get the most recent print event for this barcode (last 30 days - matching the event window)
            $mostRecentPrint = LabelLog::where('barcode', $barcode)
                ->where('event_type', LabelLog::EVENT_LABEL_PRINT)
                ->where('created_at', '>=', now()->subDays(30))
                ->orderBy('created_at', 'desc')
                ->first();

            // Get the most recent non-print event for this barcode
            $mostRecentEvent = $events->first(); // Already ordered by created_at desc

            // Product needs a label if:
            // 1. There's a recent event (new_product, price_update, or requeue_label)
            // 2. AND either no recent print OR the event is more recent than the print
            if ($mostRecentEvent && (! $mostRecentPrint || $mostRecentEvent->created_at > $mostRecentPrint->created_at)) {
                // Apply filter if specified
                if (empty($filters) || in_array($mostRecentEvent->event_type, $filters)) {
                    $needsLabelsData->push([
                        'barcode' => $barcode,
                        'event_type' => $mostRecentEvent->event_type,
                        'created_at' => $mostRecentEvent->created_at,
                    ]);
                }
            }
        }

        $needsLabelsBarcodes = $needsLabelsData->pluck('barcode');

        return Product::whereIn('CODE', $needsLabelsBarcodes)
            ->orderBy('NAME')
            ->get()
            ->map(function ($product) use ($needsLabelsData) {
                $eventData = $needsLabelsData->firstWhere('barcode', $product->CODE);
                $product->label_event_type = $eventData['event_type'];
                $product->label_event_date = $eventData['created_at'];

                return $product;
            });
    }

    /**
     * Get counts of products needing labels by event type.
     */
    private function getLabelCountsByEventType(): array
    {
        $counts = [
            LabelLog::EVENT_NEW_PRODUCT => 0,
            LabelLog::EVENT_PRICE_UPDATE => 0,
            LabelLog::EVENT_REQUEUE_LABEL => 0,
        ];

        // Get all products with any label-related events in the last 30 days
        $candidateEvents = LabelLog::whereIn('event_type', [
            LabelLog::EVENT_NEW_PRODUCT,
            LabelLog::EVENT_PRICE_UPDATE,
            LabelLog::EVENT_REQUEUE_LABEL,
        ])
            ->where('created_at', '>=', now()->subDays(30))
            ->orderBy('created_at', 'desc')
            ->get();

        // Group events by barcode and find the most recent for each
        $eventsByBarcode = $candidateEvents->groupBy('barcode');

        foreach ($eventsByBarcode as $barcode => $events) {
            // Get the most recent print event for this barcode (last 30 days - matching the event window)
            $mostRecentPrint = LabelLog::where('barcode', $barcode)
                ->where('event_type', LabelLog::EVENT_LABEL_PRINT)
                ->where('created_at', '>=', now()->subDays(30))
                ->orderBy('created_at', 'desc')
                ->first();

            // Get the most recent non-print event for this barcode
            $mostRecentEvent = $events->first(); // Already ordered by created_at desc

            // Product needs a label if:
            // 1. There's a recent event (new_product, price_update, or requeue_label)
            // 2. AND either no recent print OR the event is more recent than the print
            if ($mostRecentEvent && (! $mostRecentPrint || $mostRecentEvent->created_at > $mostRecentPrint->created_at)) {
                $counts[$mostRecentEvent->event_type]++;
            }
        }

        $counts['total'] = array_sum(array_filter($counts, 'is_numeric'));

        return $counts;
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
     * Clear products from the "Products Needing Labels" section.
     * This moves products to "Recent Label Prints" by logging label_print events.
     * Respects the current filter selection.
     */
    public function clearAllLabels(Request $request)
    {
        try {
            // Get filter parameters from request (same as index method)
            $filters = $request->input('filters', []);

            // Get products that currently need labels with the applied filters
            $productsNeedingLabels = $this->getProductsNeedingLabels($filters);
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

            // Build a descriptive message based on filters
            $filterDescription = '';
            if (! empty($filters)) {
                $filterNames = array_map(function ($filter) {
                    return ucwords(str_replace('_', ' ', $filter));
                }, $filters);
                $filterDescription = ' ('.implode(', ', $filterNames).')';
            }

            return response()->json([
                'success' => true,
                'message' => "Cleared {$clearedCount} products{$filterDescription} from labels queue",
                'cleared_count' => $clearedCount,
                'filters_applied' => $filters,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to clear labels: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Restore a batch of products from a specific time group back to "Products Needing Labels".
     */
    public function restoreBatch(Request $request)
    {
        $request->validate([
            'timestamp' => 'required|string',
            'product_ids' => 'required|array|min:1',
            'product_ids.*' => 'required|string',
        ]);

        try {
            $timestamp = $request->input('timestamp');
            $productIds = $request->input('product_ids');
            $restoredCount = 0;

            // Find products and requeue them
            foreach ($productIds as $productId) {
                $product = Product::find($productId);
                if ($product) {
                    LabelLog::logRequeueLabel($product->CODE);
                    $restoredCount++;
                }
            }

            return response()->json([
                'success' => true,
                'message' => "Restored {$restoredCount} products from {$timestamp} session",
                'restored_count' => $restoredCount,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to restore batch: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Lookup a product by barcode for the scanner.
     */
    public function lookupBarcode(Request $request)
    {
        $request->validate([
            'barcode' => 'required|string|max:255',
        ]);

        $barcode = $request->input('barcode');

        // Find the product by barcode
        $product = Product::where('CODE', $barcode)->first();

        if ($product) {
            // Eager load relationships for product info
            $product->load(['category', 'tax', 'stockCurrent', 'supplierLink.supplier']);

            return response()->json([
                'success' => true,
                'product' => [
                    'id' => $product->ID,
                    'code' => $product->CODE,
                    'name' => $product->NAME,
                    'price_net' => number_format($product->PRICESELL, 2),
                    'price_buy' => number_format($product->PRICEBUY, 2),
                    'formatted_price' => $product->getFormattedPriceWithVatAttribute(),
                    'vat_rate' => $product->getFormattedVatRateAttribute(),
                    'category' => $product->category_name,
                    'stock' => $product->getCurrentStock(),
                    'supplier' => $product->supplierLink?->supplier?->Supplier ?? null,
                    'reference' => $product->REFERENCE,
                    'case_units' => $product->supplierLink?->CaseUnits ?? null,
                ],
            ]);
        } else {
            return response()->json([
                'success' => false,
                'message' => 'Product not found with barcode: '.$barcode,
            ]);
        }
    }

    /**
     * Process a barcode scan and add the product to the labels queue.
     */
    public function processBarcodeScan(Request $request)
    {
        $request->validate([
            'barcode' => 'required|string|max:255',
        ]);

        $barcode = $request->input('barcode');

        // Find the product by barcode
        $product = Product::where('CODE', $barcode)->first();

        if (! $product) {
            return response()->json([
                'success' => false,
                'message' => 'Product not found with barcode: '.$barcode,
            ]);
        }

        try {
            // Create a requeue event to add the product to "Products Needing Labels"
            LabelLog::logRequeueLabel($product->CODE);

            return response()->json([
                'success' => true,
                'message' => 'Product added to labels queue successfully',
                'product' => [
                    'code' => $product->CODE,
                    'name' => $product->NAME,
                    'formatted_price' => $product->getFormattedPriceWithVatAttribute(),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to add product to labels queue: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Display the camera test page.
     */
    public function cameraTest(): View
    {
        $images = collect();
        $disk = Storage::disk('public');

        if ($disk->exists('labels')) {
            $files = $disk->files('labels');
            $images = collect($files)
                ->filter(fn ($file) => preg_match('/\.(jpg|jpeg|png|gif|webp)$/i', $file))
                ->sortByDesc(fn ($file) => $disk->lastModified($file))
                ->map(fn ($file) => [
                    'path' => $file,
                    'url' => asset('storage/'.$file),
                    'name' => basename($file),
                    'size' => round($disk->size($file) / 1024),
                    'date' => date('M j, Y H:i', $disk->lastModified($file)),
                ])
                ->values();
        }

        return view('labels.camera-test', compact('images'));
    }

    /**
     * Handle photo upload from camera test page and translate via Gemini.
     */
    public function uploadPhoto(Request $request)
    {
        $request->validate([
            'label_image' => 'required|image|max:10240',
        ]);

        if (! $request->hasFile('label_image')) {
            return back()->with('error', 'No photo was captured.');
        }

        $path = $request->file('label_image')->store('labels', 'public');

        try {
            // Resize image to reduce Gemini API payload (1.7MB → ~100-200KB)
            $fullPath = Storage::disk('public')->path($path);
            $img = imagecreatefromjpeg($fullPath) ?: imagecreatefrompng($fullPath);
            $origW = imagesx($img);
            $origH = imagesy($img);
            $maxDim = 1200;

            if (max($origW, $origH) > $maxDim) {
                $scale = $maxDim / max($origW, $origH);
                $newW = (int) ($origW * $scale);
                $newH = (int) ($origH * $scale);
                $resized = imagecreatetruecolor($newW, $newH);
                imagecopyresampled($resized, $img, 0, 0, 0, 0, $newW, $newH, $origW, $origH);
                imagedestroy($img);
                $img = $resized;
            }

            ob_start();
            imagejpeg($img, null, 80);
            $imageData = base64_encode(ob_get_clean());
            imagedestroy($img);

            $prompt = "Analyze this food label photo. You are a ZPL expert for 300dpi Zebra printers.\n"
                ."Label dimensions: 50mm wide x 76mm high (600 x 900 dots).\n\n"
                ."STRICT OUTPUT RULES:\n"
                ."1. NO PROSE: Return ONLY raw ZPL code.\n"
                ."2. CANVAS: Use ^PW900 and ^LL600 (Landscape Orientation).\n"
                ."3. ORIENTATION: Add ^FWB immediately after ^LL600 to rotate all fields 90 degrees.\n"
                ."4. COORDINATES (Adjusted for Landscape):\n"
                ."   - Name: ^FO800,20 (Since it's rotated, X is now the long side)\n"
                ."   - Ingredients: ^FO700,20\n"
                ."   - Nutrition: ^FO400,20\n"
                ."   - Storage/Weight: ^FO100,20\n"
                ."5. FORMATTING:\n"
                ."   - For Name: Use ^A0B,40,40 and ^FB560,2,,C.\n"
                ."   - For Ingredients: Use ^A0B,28,28 and ^FB560,12,,L.\n"
                ."   - TRANSLATE EVERYTHING TO ENGLISH.\n"
                ."   - BOLD allergens by using CAPITAL LETTERS within the text (standard Zebra fonts don't support inline bolding easily, so CAPS is the safest way to satisfy HSE for clear emphasis).\n"
                ."6. NUTRITION: Format as a simple list. Use ^A0B,24,24.\n\n"
                ."Example structure:\n"
                ."^XA\n"
                ."^PW900\n"
                ."^LL600\n"
                ."^FWB\n"
                ."^FO800,20^A0B,40,40^FB560,2,,C^FDSUN-DRIED TOMATOES IN OIL^FS\n"
                ."...\n"
                .'^XZ';

            $result = Gemini::generativeModel(model: 'gemini-2.5-flash')
                ->generateContent([
                    $prompt,
                    new Blob(
                        mimeType: MimeType::IMAGE_JPEG,
                        data: $imageData,
                    ),
                ]);

            $zpl = $result->text();

            // Strip markdown code fencing if Gemini wraps it
            $zpl = preg_replace('/^```(?:zpl|ZPL)?\s*\n?/m', '', $zpl);
            $zpl = preg_replace('/\n?```\s*$/m', '', $zpl);
            $zpl = trim($zpl);

            return view('labels.review', [
                'original_image' => asset('storage/'.$path),
                'zpl' => $zpl,
                'image_path' => $path,
            ]);
        } catch (\Exception $e) {
            return back()->with('error', 'Gemini API error: '.$e->getMessage());
        }
    }

    /**
     * Send a test print to the Zebra printer.
     */
    public function testPrint(Request $request)
    {
        $mode = $request->input('mode', 'hardcoded');
        $results = [];

        // Collect debug info
        $results['debug'] = [
            'php_user' => trim(shell_exec('whoami 2>&1') ?? ''),
            'lp_path' => trim(shell_exec('which lp 2>&1') ?? ''),
            'lpstat' => trim(shell_exec('lpstat -r 2>&1') ?? ''),
            'env_host' => config('services.zebra.host'),
            'env_port' => config('services.zebra.port'),
            'env_name' => config('services.zebra.name'),
        ];

        if ($mode === 'hardcoded') {
            // Exact replica of the working shell command
            $command = 'echo "^XA^FO50,50^A0N,50,50^FDREMOTE SUCCESS^FS^XZ" | lp -h 10.42.1.71:631/version=1.1 -d ZTC-GX430t -o raw 2>&1';
        } elseif ($mode === 'config') {
            // Using .env config values
            $host = config('services.zebra.host', '10.42.1.71');
            $port = config('services.zebra.port', '631');
            $printer = config('services.zebra.name', 'ZTC-GX430t');
            $command = "echo \"^XA^FO50,50^A0N,50,50^FDCONFIG TEST^FS^XZ\" | lp -h {$host}:{$port}/version=1.1 -d {$printer} -o raw 2>&1";
        } elseif ($mode === 'escaped') {
            // Using escapeshellarg
            $host = config('services.zebra.host', '10.42.1.71');
            $port = config('services.zebra.port', '631');
            $printer = config('services.zebra.name', 'ZTC-GX430t');
            $zpl = '^XA^FO50,50^A0N,50,50^FDESCAPED TEST^FS^XZ';
            $command = sprintf(
                'echo %s | lp -h %s:%s/version=1.1 -d %s -o raw 2>&1',
                escapeshellarg($zpl),
                escapeshellarg($host),
                escapeshellarg($port),
                escapeshellarg($printer)
            );
        } elseif ($mode === 'lpstat') {
            // Just list available printers
            $command = 'lpstat -p -d 2>&1';
        } elseif ($mode === 'network') {
            // Test network connectivity to printer
            $host = config('services.zebra.host', '10.42.1.71');
            $command = "nc -z -w3 {$host} 631 2>&1 && echo 'PORT 631 OPEN' || echo 'PORT 631 CLOSED'";
        } else {
            $command = 'echo "unknown mode"';
        }

        $results['mode'] = $mode;
        $results['command'] = $command;
        $results['output'] = trim(shell_exec($command) ?? 'No output');
        $results['success'] = str_contains($results['output'], 'request id')
            || str_contains($results['output'], 'OPEN')
            || ($mode === 'lpstat');

        return response()->json($results);
    }

    /**
     * Save ZPL code alongside its source image.
     */
    public function saveZpl(Request $request)
    {
        $request->validate([
            'zpl' => 'required|string',
            'image_path' => 'required|string',
            'label_data' => 'nullable|array',
        ]);

        $imagePath = $request->input('image_path');
        $zplPath = preg_replace('/\.(jpg|jpeg|png|gif|webp)$/i', '.zpl', $imagePath);

        Storage::disk('public')->put($zplPath, $request->input('zpl'));

        // Save JSON data alongside for re-generation support
        if ($request->has('label_data')) {
            $jsonPath = preg_replace('/\.(jpg|jpeg|png|gif|webp)$/i', '.json', $imagePath);
            Storage::disk('public')->put($jsonPath, json_encode($request->input('label_data'), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        }

        return response()->json([
            'success' => true,
            'message' => 'ZPL saved to '.$zplPath,
        ]);
    }

    /**
     * Print ZPL code to the Zebra printer.
     */
    public function printZpl(Request $request)
    {
        $request->validate([
            'zpl' => 'required|string',
        ]);

        $zpl = $request->input('zpl');

        // Write ZPL to a temp file to avoid shell escaping issues
        $tmpFile = tempnam(sys_get_temp_dir(), 'zpl_');
        file_put_contents($tmpFile, $zpl);

        $command = "lp -h 10.42.1.71:631/version=1.1 -d ZTC-GX430t -o raw {$tmpFile} 2>&1";
        $output = shell_exec($command);

        unlink($tmpFile);

        $success = $output && str_contains($output, 'request id');

        return response()->json([
            'success' => $success,
            'message' => $success ? 'Print job sent' : 'Print failed',
            'output' => trim($output ?? 'No output'),
        ], $success ? 200 : 500);
    }

    /**
     * Show saved label translations with their images and ZPL files.
     */
    public function labelHistory(): View
    {
        $disk = Storage::disk('public');
        $labels = collect();

        if ($disk->exists('labels')) {
            $zplFiles = collect($disk->files('labels'))
                ->filter(fn ($file) => str_ends_with($file, '.zpl'));

            $labels = $zplFiles->map(function ($zplFile) use ($disk) {
                $baseName = preg_replace('/\.zpl$/', '', basename($zplFile));

                // Find matching image and JSON data
                $imageFile = collect($disk->files('labels'))
                    ->first(fn ($f) => preg_match('/^labels\/'.preg_quote($baseName, '/').'\\.(jpg|jpeg|png|gif|webp)$/i', $f));

                $jsonPath = 'labels/'.$baseName.'.json';
                $labelData = null;
                if ($disk->exists($jsonPath)) {
                    $labelData = json_decode($disk->get($jsonPath), true);
                }

                return [
                    'zpl_path' => $zplFile,
                    'zpl_url' => asset('storage/'.$zplFile),
                    'image_url' => $imageFile ? asset('storage/'.$imageFile) : null,
                    'image_path' => $imageFile ?? '',
                    'name' => $baseName,
                    'date' => date('M j, Y H:i', $disk->lastModified($zplFile)),
                    'zpl_size' => round($disk->size($zplFile) / 1024, 1),
                    'label_data' => $labelData,
                ];
            })
                ->sortByDesc(fn ($item) => $item['date'])
                ->values();
        }

        return view('labels.history', compact('labels'));
    }

    /**
     * Edit a previously saved label translation.
     */
    public function editLabel(string $name): View
    {
        $disk = Storage::disk('public');
        $zplPath = 'labels/'.$name.'.zpl';

        if (! $disk->exists($zplPath)) {
            abort(404, 'ZPL file not found.');
        }

        $zpl = $disk->get($zplPath);

        // Find matching image
        $imageFile = collect($disk->files('labels'))
            ->first(fn ($f) => preg_match('/^labels\/'.preg_quote($name, '/').'\\.(jpg|jpeg|png|gif|webp)$/i', $f));

        $imagePath = $imageFile ?? '';

        // Load JSON data if saved (enables size/font controls)
        $jsonPath = 'labels/'.$name.'.json';
        $labelData = null;
        if ($disk->exists($jsonPath)) {
            $labelData = json_decode($disk->get($jsonPath), true);
        }

        return view('labels.review', [
            'original_image' => $imageFile ? asset('storage/'.$imageFile) : null,
            'zpl' => $zpl,
            'image_path' => $imagePath,
            'label_data' => $labelData,
            'label_size' => 'large',
            'font_scale' => 2.0,
        ]);
    }

    /**
     * Camera test 2 - JSON-based label translation with Laravel ZPL generation.
     */
    public function cameraTest2(): View
    {
        return view('labels.camera-test2');
    }

    /**
     * Handle photo upload for camera-test2, get JSON from Gemini, generate ZPL server-side.
     */
    public function uploadPhoto2(Request $request)
    {
        $request->validate([
            'label_image' => 'required|image|max:10240',
            'label_size' => 'required|in:'.$this->zplGenerator->getValidSizeKeys(),
        ]);

        if (! $request->hasFile('label_image')) {
            return back()->with('error', 'No photo was captured.');
        }

        $path = $request->file('label_image')->store('labels', 'public');
        $labelSize = $request->input('label_size', 'large');

        try {
            // Resize image to reduce Gemini API payload
            $fullPath = Storage::disk('public')->path($path);
            $img = imagecreatefromjpeg($fullPath) ?: imagecreatefrompng($fullPath);
            $origW = imagesx($img);
            $origH = imagesy($img);
            $maxDim = 1200;

            if (max($origW, $origH) > $maxDim) {
                $scale = $maxDim / max($origW, $origH);
                $newW = (int) ($origW * $scale);
                $newH = (int) ($origH * $scale);
                $resized = imagecreatetruecolor($newW, $newH);
                imagecopyresampled($resized, $img, 0, 0, 0, 0, $newW, $newH, $origW, $origH);
                imagedestroy($img);
                $img = $resized;
            }

            ob_start();
            imagejpeg($img, null, 80);
            $imageData = base64_encode(ob_get_clean());
            imagedestroy($img);

            $prompt = "Extract data from this food label into JSON.\n\n"
                ."Translate to English.\n\n"
                ."STRICT VERBATIM: Only include information physically present on the label.\n\n"
                .'CONDITIONAL FIELDS: For fields like address, origin, or nutrition_inline, '
                .'if the information is NOT present on the label, set the value to null. '
                ."Do not guess or use external knowledge.\n\n"
                .'ALLERGENS: Format the ingredients string with EU allergens in ALL CAPS. '
                .'The 14 EU allergens: Cereals (GLUTEN), CRUSTACEANS, EGGS, FISH, PEANUTS, '
                ."SOYBEANS, MILK, NUTS, CELERY, MUSTARD, SESAME, SULPHITES, LUPIN, MOLLUSCS.\n\n"
                .'ANNOTATIONS: Preserve asterisk annotations (*, **) on ingredients and include '
                .'their explanations at the end of the ingredients string. '
                ."Example: 'tomatoes** 65%, olive oil*, salt. *from organic farming. **from biodynamic agriculture.'\n\n"
                .'JSON STRUCTURE: Return only the JSON object with keys: '
                ."product_name, ingredients, nutrition_inline, storage, address, origin.\n"
                ."Do NOT include net_weight — it is already on the packaging.\n\n"
                ."Example output:\n"
                .'{"product_name":"Sun-Dried Tomatoes in Oil",'
                .'"ingredients":"Sun-dried tomatoes** 60%, sunflower oil*, SULPHITES (as preservative), salt, garlic, oregano. *from organic farming. **from organic and biodynamic agriculture.",'
                .'"nutrition_inline":"Energy 245kcal | Fat 18g | Sat 2.1g | Carbs 12g | Sugar 8g | Protein 5g | Salt 1.2g",'
                .'"storage":"Store in a cool, dry place. Once opened, refrigerate and use within 3 days.",'
                .'"address":"Via Roma 12, 80100 Naples, Italy",'
                .'"origin":null}';

            $result = Gemini::generativeModel(model: 'gemini-2.5-flash')
                ->generateContent([
                    $prompt,
                    new Blob(
                        mimeType: MimeType::IMAGE_JPEG,
                        data: $imageData,
                    ),
                ]);

            $text = $result->text();

            // Strip markdown code fencing if Gemini wraps it
            $text = preg_replace('/^```(?:json)?\s*\n?/m', '', $text);
            $text = preg_replace('/\n?```\s*$/m', '', $text);
            $text = trim($text);

            $data = json_decode($text, true);

            if (! $data || ! array_key_exists('product_name', $data)) {
                return back()->with('error', 'Gemini returned invalid JSON: '.$text);
            }

            // Default to max fit — auto-clamps to largest scale that fits
            [$zpl, $effectiveScale] = $this->generateZplWithScale($data, $labelSize, 2.0);

            return view('labels.review', [
                'original_image' => asset('storage/'.$path),
                'zpl' => $zpl,
                'image_path' => $path,
                'label_data' => $data,
                'label_size' => $labelSize,
                'font_scale' => $effectiveScale,
            ]);
        } catch (\Exception $e) {
            return back()->with('error', 'Gemini API error: '.$e->getMessage());
        }
    }

    /**
     * Generate ZPL code from structured label data (delegates to ZplGeneratorService).
     */
    public function generateZpl(array $data, string $labelSize = 'large', float $fontScale = 1.0): string
    {
        return $this->zplGenerator->generateZpl($data, $labelSize, $fontScale);
    }

    /**
     * Generate ZPL and return [zpl_string, effective_scale] (delegates to ZplGeneratorService).
     */
    public function generateZplWithScale(array $data, string $labelSize = 'large', float $fontScale = 1.0): array
    {
        return $this->zplGenerator->generateZplWithScale($data, $labelSize, $fontScale);
    }

    /**
     * Regenerate ZPL from saved JSON data with a different label size or font scale.
     */
    public function regenerateZpl(Request $request)
    {
        $request->validate([
            'label_data' => 'required|array',
            'label_size' => 'required|in:'.$this->zplGenerator->getValidSizeKeys(),
            'font_scale' => 'nullable|numeric|min:0.5|max:2.0',
            'image_path' => 'nullable|string',
        ]);

        $data = $request->input('label_data');
        $labelSize = $request->input('label_size');
        $fontScale = (float) $request->input('font_scale', 1.0);
        [$zpl, $effectiveScale] = $this->generateZplWithScale($data, $labelSize, $fontScale);

        return response()->json([
            'success' => true,
            'zpl' => $zpl,
            'font_scale' => $effectiveScale,
        ]);
    }
}
