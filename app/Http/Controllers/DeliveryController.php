<?php

namespace App\Http\Controllers;

use App\Models\Delivery;
use App\Models\DeliveryItem;
use App\Models\LabelLog;
use App\Models\LegacyDelivery;
use App\Models\Product;
use App\Models\Supplier;
use App\Services\DeliveryParsingService;
use App\Services\DeliveryService;
use App\Services\SupplierService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class DeliveryController extends Controller
{
    private DeliveryService $deliveryService;

    private SupplierService $supplierService;

    private DeliveryParsingService $deliveryParsingService;

    public function __construct(
        DeliveryService $deliveryService,
        SupplierService $supplierService,
        DeliveryParsingService $deliveryParsingService
    ) {
        $this->deliveryService = $deliveryService;
        $this->supplierService = $supplierService;
        $this->deliveryParsingService = $deliveryParsingService;
    }

    /**
     * Display a listing of deliveries
     */
    public function index(Request $request): View
    {
        $query = Delivery::with(['supplier', 'items']);

        // Handle search functionality
        if ($search = $request->get('search')) {
            $query->where(function ($q) use ($search) {
                // Search in delivery items for barcode, supplier code, or description
                $q->whereHas('items', function ($itemQuery) use ($search) {
                    $itemQuery->where('barcode', 'LIKE', "%{$search}%")
                        ->orWhere('supplier_code', 'LIKE', "%{$search}%")
                        ->orWhere('description', 'LIKE', "%{$search}%");
                })
                // Also search delivery number
                    ->orWhere('delivery_number', 'LIKE', "%{$search}%");
            });

            // Handle supplier name search separately due to cross-database relationship
            // Get supplier IDs that match the search term
            $matchingSupplierIds = \DB::connection('pos')
                ->table('suppliers')
                ->where('Supplier', 'LIKE', "%{$search}%")
                ->pluck('SupplierID')
                ->toArray();

            if (! empty($matchingSupplierIds)) {
                $query->orWhereIn('supplier_id', $matchingSupplierIds);
            }
        }

        $deliveries = $query->orderBy('delivery_date', 'desc')->paginate(20);

        // Preserve search parameter in pagination links
        if ($search) {
            $deliveries->appends(['search' => $search]);
        }

        return view('deliveries.index', compact('deliveries'));
    }

    /**
     * Show the form for creating a new delivery
     */
    public function create(): View
    {
        $suppliers = Supplier::orderBy('Supplier')->get();

        return view('deliveries.create', compact('suppliers'));
    }

    /**
     * Store a newly created delivery in storage
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'supplier_id' => 'required|exists:App\Models\Supplier,SupplierID',
            'delivery_date' => 'required|date',
            'csv_file' => 'required|file|mimes:csv,txt|max:10240',
        ]);

        // Additional validation for CSV format
        $this->validateCsvFormat($request->file('csv_file'), $request->supplier_id);

        try {
            // Validate that file upload was successful
            $uploadedFile = $request->file('csv_file');
            if (! $uploadedFile || ! $uploadedFile->isValid()) {
                throw new \Exception('File upload failed or file is invalid');
            }

            // Store the uploaded file
            $csvPath = $uploadedFile->store('temp');
            if (! $csvPath) {
                throw new \Exception('Failed to store uploaded file');
            }

            $fullPath = Storage::disk('local')->path($csvPath);

            // Verify the file exists after upload
            if (! file_exists($fullPath)) {
                throw new \Exception('Uploaded file not found at: '.$fullPath);
            }

            // Verify file is readable
            if (! is_readable($fullPath)) {
                throw new \Exception('Uploaded file is not readable: '.$fullPath);
            }

            $delivery = $this->deliveryService->importFromCsv(
                $fullPath,
                $request->supplier_id,
                $request->delivery_date
            );

            // Clean up temp file
            if (file_exists($fullPath)) {
                unlink($fullPath);
            }

            return redirect()
                ->route('deliveries.show', $delivery)
                ->with('success', 'Delivery imported successfully. '.
                       $delivery->items->count().' items loaded.');

        } catch (\Exception $e) {
            // Clean up temp file if it exists
            if (isset($fullPath) && file_exists($fullPath)) {
                unlink($fullPath);
            }

            return back()
                ->withInput()
                ->withErrors(['csv_file' => 'Failed to import CSV: '.$e->getMessage()]);
        }
    }

    /**
     * Detect supplier from uploaded PDF without requiring supplier selection first.
     */
    public function detectSupplier(Request $request): JsonResponse
    {
        $storedPath = null;

        try {
            $uploadedFile = $request->file('pdf_file');

            if (! $uploadedFile || ! $uploadedFile->isValid()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No valid PDF file uploaded',
                ], 422);
            }

            if ($uploadedFile->getMimeType() !== 'application/pdf' && ! str_ends_with(strtolower($uploadedFile->getClientOriginalName()), '.pdf')) {
                return response()->json([
                    'success' => false,
                    'message' => 'File is not a PDF',
                ], 422);
            }

            // Store temporarily
            $pdfPath = $uploadedFile->store('temp');
            $storedPath = Storage::disk('local')->path($pdfPath);

            // Parse PDF without supplier hint to detect supplier
            $result = $this->deliveryParsingService->parseDeliveryPdf($storedPath);

            // Clean up temp file
            if (file_exists($storedPath)) {
                unlink($storedPath);
            }

            $detectedName = $result['metadata']['supplier_detected'] ?? null;
            $supplierId = $this->mapSupplierNameToId($detectedName);

            return response()->json([
                'success' => true,
                'detected_name' => $detectedName,
                'supplier_id' => $supplierId,
                'confidence' => $result['metadata']['confidence'] ?? null,
            ]);

        } catch (\Exception $e) {
            // Clean up temp file if it exists
            if ($storedPath && file_exists($storedPath)) {
                unlink($storedPath);
            }

            return response()->json([
                'success' => false,
                'message' => 'Failed to detect supplier: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Map detected supplier name to database supplier ID.
     */
    private function mapSupplierNameToId(?string $detectedName): ?int
    {
        if (! $detectedName) {
            return null;
        }

        // Normalize the detected name for matching
        $normalized = strtolower(trim($detectedName));

        $mapping = [
            'independent' => 37,
            'independent health food distributors' => 37,
            'independent health foods' => 37,
            'udea' => 5,
            'natural medicine' => 65,
            'natural medicine company' => 65,
            'the natural medicine company' => 65,
        ];

        // Try exact match first
        if (isset($mapping[$normalized])) {
            return $mapping[$normalized];
        }

        // Try partial match
        foreach ($mapping as $key => $id) {
            if (str_contains($normalized, $key) || str_contains($key, $normalized)) {
                return $id;
            }
        }

        return null;
    }

    /**
     * Parse delivery PDF(s) and show preview before import.
     * Supports both single file and multiple file uploads.
     */
    public function parsePdf(Request $request): JsonResponse
    {
        $request->validate([
            'supplier_id' => 'required|exists:App\Models\Supplier,SupplierID',
        ]);

        $storedPaths = [];

        try {
            // Handle both single file and multiple files
            $uploadedFiles = $request->file('pdf_file');

            // Normalize to array
            if (! is_array($uploadedFiles)) {
                $uploadedFiles = [$uploadedFiles];
            }

            // Filter out null values and validate each file
            $uploadedFiles = array_filter($uploadedFiles, fn ($f) => $f !== null);

            if (empty($uploadedFiles)) {
                return response()->json([
                    'success' => false,
                    'message' => 'No PDF files uploaded',
                ], 422);
            }

            // Validate and store each file
            foreach ($uploadedFiles as $index => $file) {
                if (! $file->isValid()) {
                    return response()->json([
                        'success' => false,
                        'message' => 'File upload failed for file '.($index + 1),
                    ], 422);
                }

                if ($file->getMimeType() !== 'application/pdf' && ! str_ends_with(strtolower($file->getClientOriginalName()), '.pdf')) {
                    return response()->json([
                        'success' => false,
                        'message' => "File {$file->getClientOriginalName()} is not a PDF",
                    ], 422);
                }

                if ($file->getSize() > 20 * 1024 * 1024) {
                    return response()->json([
                        'success' => false,
                        'message' => "File {$file->getClientOriginalName()} exceeds 20MB limit",
                    ], 422);
                }

                $pdfPath = $file->store('temp');
                $storedPaths[] = Storage::disk('local')->path($pdfPath);
            }

            // Get supplier hint based on supplier ID
            $supplierHint = $this->getSupplierHint($request->supplier_id);

            // Parse PDF(s)
            if (count($storedPaths) === 1) {
                $result = $this->deliveryParsingService->parseDeliveryPdf($storedPaths[0], $supplierHint);
            } else {
                $result = $this->deliveryParsingService->parseMultipleDeliveryPdfs($storedPaths, $supplierHint);
            }

            // Clean up temp files
            foreach ($storedPaths as $path) {
                if (file_exists($path)) {
                    unlink($path);
                }
            }

            if (! $this->deliveryParsingService->wasSuccessful($result)) {
                $errors = $this->deliveryParsingService->getErrors($result);
                $errorMsg = ! empty($errors)
                    ? (is_array($errors[0]) ? $errors[0]['message'] : $errors[0])
                    : 'Failed to parse PDF';

                return response()->json([
                    'success' => false,
                    'message' => $errorMsg,
                    'errors' => $errors,
                ], 422);
            }

            // Convert to delivery items format
            $items = $this->deliveryParsingService->convertToDeliveryItems($result);
            $totals = $this->deliveryParsingService->getTotals($result);
            $warnings = $this->deliveryParsingService->getWarnings($result);
            $confidence = $this->deliveryParsingService->getConfidence($result);

            $response = [
                'success' => true,
                'items' => $items,
                'totals' => $totals,
                'barrels' => $result['data']['barrels'] ?? ['items' => [], 'total' => 0],
                'warnings' => $warnings,
                'confidence' => $confidence,
                'supplier_detected' => $result['metadata']['supplier_detected'] ?? 'Unknown',
                'unmatched_lines' => $result['metadata']['unmatched_lines'] ?? [],
            ];

            // Include file results for multi-file uploads
            if (isset($result['file_results'])) {
                $response['file_results'] = $result['file_results'];
            }

            return response()->json($response);

        } catch (\Exception $e) {
            // Clean up temp files if they exist
            foreach ($storedPaths as $path) {
                if (file_exists($path)) {
                    unlink($path);
                }
            }

            return response()->json([
                'success' => false,
                'message' => 'Failed to parse PDF: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Store a newly created delivery from PDF upload.
     * Supports both single file and multiple file uploads.
     */
    public function storePdf(Request $request): RedirectResponse
    {
        $request->validate([
            'supplier_id' => 'required|exists:App\Models\Supplier,SupplierID',
            'delivery_date' => 'required|date',
        ]);

        $storedPaths = [];
        $originalFilenames = [];

        try {
            // Handle both single file and multiple files
            $uploadedFiles = $request->file('pdf_file');

            // Normalize to array
            if (! is_array($uploadedFiles)) {
                $uploadedFiles = [$uploadedFiles];
            }

            // Filter out null values
            $uploadedFiles = array_filter($uploadedFiles, fn ($f) => $f !== null);

            if (empty($uploadedFiles)) {
                throw new \Exception('No PDF files uploaded');
            }

            // Validate and store each file
            foreach ($uploadedFiles as $index => $file) {
                if (! $file->isValid()) {
                    throw new \Exception('File upload failed for file '.($index + 1));
                }

                if ($file->getMimeType() !== 'application/pdf' && ! str_ends_with(strtolower($file->getClientOriginalName()), '.pdf')) {
                    throw new \Exception("File {$file->getClientOriginalName()} is not a PDF");
                }

                if ($file->getSize() > 20 * 1024 * 1024) {
                    throw new \Exception("File {$file->getClientOriginalName()} exceeds 20MB limit");
                }

                $pdfPath = $file->store('temp');
                $storedPaths[] = Storage::disk('local')->path($pdfPath);
                $originalFilenames[] = $file->getClientOriginalName();
            }

            // Get supplier hint based on supplier ID
            $supplierHint = $this->getSupplierHint($request->supplier_id);

            // Parse PDF(s)
            if (count($storedPaths) === 1) {
                $result = $this->deliveryParsingService->parseDeliveryPdf($storedPaths[0], $supplierHint);
            } else {
                $result = $this->deliveryParsingService->parseMultipleDeliveryPdfs($storedPaths, $supplierHint);
            }

            if (! $this->deliveryParsingService->wasSuccessful($result)) {
                $errors = $this->deliveryParsingService->getErrors($result);
                $errorMsg = ! empty($errors)
                    ? (is_array($errors[0]) ? $errors[0]['message'] : $errors[0])
                    : 'Failed to parse PDF';
                throw new \Exception($errorMsg);
            }

            // Convert to delivery items format
            $items = $this->deliveryParsingService->convertToDeliveryItems($result);

            if (empty($items)) {
                throw new \Exception('No products found in PDF(s)');
            }

            // Build filename for delivery record
            $filenameForRecord = count($originalFilenames) > 1
                ? implode(', ', array_slice($originalFilenames, 0, 3)).(count($originalFilenames) > 3 ? '...' : '')
                : $originalFilenames[0];

            // Create delivery using the parsed data
            $delivery = $this->deliveryService->importFromPdfData(
                $items,
                $request->supplier_id,
                $request->delivery_date,
                $filenameForRecord
            );

            // Store barrel items if present (Udea deliveries)
            $barrels = $result['data']['barrels'] ?? ['items' => [], 'total' => 0];
            if (! empty($barrels['items'])) {
                $this->deliveryService->storeBarrelItems(
                    $delivery,
                    $barrels['items'],
                    $request->supplier_id
                );
            }

            // Clean up temp files
            foreach ($storedPaths as $path) {
                if (file_exists($path)) {
                    unlink($path);
                }
            }

            $totals = $this->deliveryParsingService->getTotals($result);
            $warnings = $this->deliveryParsingService->getWarnings($result);
            $barrels = $result['data']['barrels'] ?? ['items' => [], 'total' => 0];

            $fileCount = count($originalFilenames);
            $successMsg = 'Delivery imported successfully from '.($fileCount > 1 ? "{$fileCount} PDFs" : 'PDF').'. '.
                          count($items).' items loaded. '.
                          'Total value: '.number_format($totals['total_value'], 2);

            if (! empty($warnings)) {
                $successMsg .= ' ('.count($warnings).' warnings - check items)';
            }

            // Flash import summary with barrels breakdown
            session()->flash('import_summary', [
                'products_total' => $totals['products_total'] ?? $totals['total_value'],
                'barrels_total' => $totals['barrels_total'] ?? 0,
                'grand_total' => $totals['total_value'],
                'barrel_items' => $barrels['items'] ?? [],
            ]);

            // Flash unmatched lines for user review
            $unmatchedLines = $result['metadata']['unmatched_lines'] ?? [];
            if (! empty($unmatchedLines)) {
                session()->flash('import_warnings', [
                    'unmatched_count' => count($unmatchedLines),
                    'unmatched_lines' => $unmatchedLines,
                ]);
            }

            return redirect()
                ->route('deliveries.show', $delivery)
                ->with('success', $successMsg);

        } catch (\Exception $e) {
            // Clean up temp files if they exist
            foreach ($storedPaths as $path) {
                if (file_exists($path)) {
                    unlink($path);
                }
            }

            return back()
                ->withInput()
                ->withErrors(['pdf_file' => 'Failed to import PDF: '.$e->getMessage()]);
        }
    }

    /**
     * Get supplier hint for PDF parsing based on supplier ID.
     */
    private function getSupplierHint(int $supplierId): ?string
    {
        // Get Independent supplier configuration
        $independentConfig = config('suppliers.external_links.independent');
        if ($independentConfig && in_array($supplierId, $independentConfig['supplier_ids'] ?? [])) {
            return 'independent';
        }

        // Get UDEA supplier configuration
        $udeaConfig = config('suppliers.external_links.udea');
        if ($udeaConfig && in_array($supplierId, $udeaConfig['supplier_ids'] ?? [])) {
            return 'udea';
        }

        // Get Natural Medicine supplier configuration
        $naturalMedicineConfig = config('suppliers.external_links.natural_medicine');
        if ($naturalMedicineConfig && in_array($supplierId, $naturalMedicineConfig['supplier_ids'] ?? [])) {
            return 'natural_medicine';
        }

        return null;
    }

    /**
     * Display the specified delivery
     */
    public function show(Delivery $delivery, Request $request)
    {
        $delivery->load(['supplier', 'items.product.supplier', 'items.product.taxCategory.primaryTax', 'scans']);

        $summary = $this->deliveryService->getDeliverySummary($delivery->id);

        // Handle AJAX requests for auto-refresh
        if ($request->wantsJson()) {
            return response()->json([
                'items' => $delivery->items->map(function ($item) {
                    $imageUrl = null;
                    $hasIntegration = false;

                    if ($item->product && $item->product->supplier && $this->supplierService->hasExternalIntegration($item->product->supplier->SupplierID)) {
                        // Existing product with supplier integration
                        $imageUrl = $this->supplierService->getExternalImageUrl($item->product);
                        $hasIntegration = true;
                    } elseif ($item->barcode && $item->is_new_product && $this->supplierService->hasExternalIntegration($item->delivery->supplier_id)) {
                        // New product with barcode and supplier integration
                        $imageUrl = $this->supplierService->getExternalImageUrlByBarcode($item->delivery->supplier_id, $item->barcode);
                        $hasIntegration = true;
                    }

                    // Check if barcode already exists in products database
                    $existingProduct = $item->barcode ? Product::where('CODE', $item->barcode)->first() : null;

                    return [
                        'id' => $item->id,
                        'barcode' => $item->barcode,
                        'barcode_retrieval_failed' => $item->barcode_retrieval_failed,
                        'barcode_retrieval_error' => $item->barcode_retrieval_error,
                        'image_url' => $imageUrl,
                        'has_integration' => $hasIntegration,
                        'is_new_product' => $item->is_new_product,
                        'description' => $item->description,
                        'exists_in_database' => $existingProduct !== null,
                        'existing_product' => $existingProduct ? [
                            'id' => $existingProduct->ID,
                            'name' => $existingProduct->NAME,
                        ] : null,
                    ];
                }),
                'summary' => $summary,
            ]);
        }

        return view('deliveries.show', compact('delivery', 'summary'))->with('supplierService', $this->supplierService);
    }

    /**
     * Show the scanning interface for delivery
     */
    public function scan(Delivery $delivery): View
    {
        $delivery->load(['supplier', 'items.product.supplier']);

        // Update delivery status to receiving if still draft
        if ($delivery->status === 'draft') {
            $delivery->update(['status' => 'receiving']);
        }

        // Process items with supplier integration data
        $processedItems = $delivery->items->map(fn ($item) => $this->formatDeliveryItem($item, $delivery));

        return view('deliveries.scan', compact('delivery', 'processedItems'));
    }

    /**
     * Process a barcode scan
     */
    public function processScan(Request $request, Delivery $delivery): JsonResponse
    {
        $request->validate([
            'barcode' => 'required|string',
            'quantity' => 'integer|min:1|max:9999',
        ]);

        $result = $this->deliveryService->processScan(
            $delivery->id,
            $request->barcode,
            $request->quantity ?? 1,
            auth()->user()->name ?? 'System'
        );

        // Format the item data if scan was successful
        if ($result['success'] && isset($result['item'])) {
            $item = \App\Models\DeliveryItem::find($result['item']['id']);
            if ($item) {
                $item->load('product.supplier');
                $result['item'] = $this->formatDeliveryItem($item, $delivery);
            }
        }

        return response()->json($result);
    }

    /**
     * Manually adjust item quantity
     */
    public function adjustQuantity(Request $request, Delivery $delivery, DeliveryItem $item): JsonResponse
    {
        $request->validate([
            'quantity' => 'required|integer|min:0|max:9999',
        ]);

        $newQuantity = $request->quantity;

        // Update the new quantity fields based on quantity type
        if ($item->quantity_type === 'case' && $item->getEffectiveCaseUnits() > 1) {
            // For case-based items, convert total units to cases and units
            $caseUnits = $item->getEffectiveCaseUnits();
            $cases = intval($newQuantity / $caseUnits);
            $units = $newQuantity % $caseUnits;

            $item->update([
                'case_received_quantity' => $cases,
                'unit_received_quantity' => $units,
            ]);
        } else {
            // For unit-based items, update unit quantity directly
            $item->update([
                'unit_received_quantity' => $newQuantity,
                'case_received_quantity' => 0,
            ]);
        }

        // Update legacy fields and status
        $item->updateLegacyQuantities();
        $item->updateStatus();

        return response()->json([
            'success' => true,
            'item' => $this->formatDeliveryItem($item->fresh(['product.supplier']), $delivery),
            'message' => 'Quantity updated successfully',
        ]);
    }

    /**
     * Update price for a delivery item's product
     */
    public function updateItemPrice(Request $request, Delivery $delivery, DeliveryItem $item): JsonResponse
    {
        $request->validate([
            'price_input_mode' => 'required|in:gross,net',
            'gross_price' => 'required_if:price_input_mode,gross|nullable|numeric|min:0|max:999999.99',
            'net_price' => 'required_if:price_input_mode,net|nullable|numeric|min:0|max:999999.99',
            'final_net_price' => 'nullable|numeric|min:0|max:999999.99', // JavaScript-calculated fallback
        ]);

        // Check if item has a product
        if (! $item->product) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot update price for items without linked products',
            ], 422);
        }

        try {
            // Calculate net price based on input mode
            if ($request->price_input_mode === 'gross') {
                // Get tax rate for conversion
                $taxRate = 0;
                if ($item->product->taxCategory && $item->product->taxCategory->primaryTax) {
                    $taxRate = $item->product->taxCategory->primaryTax->RATE;
                }
                $netPrice = $request->gross_price / (1 + $taxRate);
            } elseif ($request->has('final_net_price')) {
                // Use JavaScript-calculated net price (most accurate)
                $netPrice = $request->final_net_price;
            } else {
                // User entered net price directly
                $netPrice = $request->net_price;
            }

            // Update the product's price
            $item->product->update([
                'PRICESELL' => $netPrice,
            ]);

            // Log the price update for label printing
            LabelLog::logPriceUpdate($item->product->CODE);

            // Calculate new margin for response
            $deliveryCost = $item->unit_cost;
            $margin = $netPrice - $deliveryCost;
            $marginPercent = $netPrice > 0 ? ($margin / $netPrice) * 100 : 0;

            return response()->json([
                'success' => true,
                'message' => 'Price updated successfully and added to label queue',
                'data' => [
                    'new_net_price' => number_format($netPrice, 2),
                    'new_gross_price' => number_format($netPrice * (1 + ($taxRate ?? 0)), 2),
                    'margin' => number_format($margin, 2),
                    'margin_percent' => number_format($marginPercent, 1),
                    'added_to_labels' => true,
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update price: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get delivery statistics
     */
    public function getStats(Delivery $delivery): JsonResponse
    {
        $summary = $this->deliveryService->getDeliverySummary($delivery->id);

        return response()->json($summary);
    }

    /**
     * Show delivery summary with discrepancies
     */
    public function summary(Delivery $delivery): View
    {
        $delivery->load(['supplier', 'items.product.supplier', 'scans']);
        $summary = $this->deliveryService->getDeliverySummary($delivery->id);

        return view('deliveries.summary', compact('delivery', 'summary'))->with('supplierService', $this->supplierService);
    }

    /**
     * Complete the delivery and update stock
     */
    public function complete(Request $request, Delivery $delivery): RedirectResponse
    {
        if ($delivery->status !== 'receiving') {
            return back()->withErrors(['delivery' => 'Only deliveries in receiving status can be completed.']);
        }

        try {
            $this->deliveryService->completeDelivery($delivery->id);

            return redirect()
                ->route('deliveries.show', $delivery)
                ->with('success', 'Delivery completed successfully. Stock levels have been updated.');

        } catch (\Exception $e) {
            return back()->withErrors(['delivery' => 'Failed to complete delivery: '.$e->getMessage()]);
        }
    }

    /**
     * Cancel a delivery
     */
    public function cancel(Delivery $delivery): RedirectResponse
    {
        if ($delivery->status === 'completed') {
            return back()->withErrors(['delivery' => 'Cannot cancel a completed delivery.']);
        }

        $delivery->update(['status' => 'cancelled']);

        return redirect()
            ->route('deliveries.index')
            ->with('success', 'Delivery cancelled successfully.');
    }

    /**
     * Toggle whether delivery quantities are included in order stock calculations
     */
    public function toggleOrderStock(Delivery $delivery): RedirectResponse
    {
        $delivery->update([
            'include_in_order_stock' => ! $delivery->include_in_order_stock,
        ]);

        $message = $delivery->include_in_order_stock
            ? 'Delivery will be included in order stock calculations.'
            : 'Delivery removed from order stock calculations.';

        return back()->with('success', $message);
    }

    /**
     * Remove the specified delivery from storage
     */
    public function destroy(Delivery $delivery): RedirectResponse
    {
        // Only allow deletion of draft or cancelled deliveries
        if (! in_array($delivery->status, ['draft', 'cancelled'])) {
            return back()->withErrors(['delivery' => 'Only draft or cancelled deliveries can be deleted.']);
        }

        try {
            // Get delivery number for success message before deletion
            $deliveryNumber = $delivery->delivery_number;

            // Delete related records explicitly to ensure clean deletion
            $delivery->scans()->delete();
            $delivery->items()->delete();

            // Delete the delivery itself
            $delivery->delete();

            return redirect()
                ->route('deliveries.index')
                ->with('success', "Delivery {$deliveryNumber} has been deleted successfully.");

        } catch (\Exception $e) {
            return back()->withErrors(['delivery' => 'Failed to delete delivery: '.$e->getMessage()]);
        }
    }

    /**
     * Export discrepancy report
     */
    public function exportDiscrepancies(Delivery $delivery): JsonResponse
    {
        $summary = $this->deliveryService->getDeliverySummary($delivery->id);

        return response()->json([
            'delivery' => $delivery->only(['delivery_number', 'delivery_date']),
            'supplier' => $delivery->supplier->only(['Supplier']),
            'discrepancies' => $summary['discrepancies'],
            'totals' => [
                'expected_value' => $summary['total_expected_value'],
                'received_value' => $summary['total_received_value'],
                'difference' => $summary['total_expected_value'] - $summary['total_received_value'],
            ],
        ]);
    }

    /**
     * Refresh barcode for new product
     */
    public function refreshBarcode(DeliveryItem $item): JsonResponse
    {
        if (! $item->is_new_product) {
            return response()->json([
                'success' => false,
                'message' => 'This item is not a new product',
            ]);
        }

        try {
            $barcode = $this->deliveryService->retrieveBarcode($item->supplier_code);

            if ($barcode) {
                $item->update(['barcode' => $barcode]);

                // Check if barcode already exists in products database
                $existingProduct = Product::where('CODE', $barcode)->first();

                // Check for image URL if supplier has external integration
                $imageUrl = null;
                $hasIntegration = false;
                if ($item->is_new_product && $this->supplierService->hasExternalIntegration($item->delivery->supplier_id)) {
                    $imageUrl = $this->supplierService->getExternalImageUrlByBarcode($item->delivery->supplier_id, $barcode);
                    $hasIntegration = true;
                }

                return response()->json([
                    'success' => true,
                    'barcode' => $barcode,
                    'message' => 'Barcode retrieved successfully',
                    'image_url' => $imageUrl,
                    'has_integration' => $hasIntegration,
                    'item_id' => $item->id,
                    'description' => $item->description,
                    'exists_in_database' => $existingProduct !== null,
                    'existing_product' => $existingProduct ? [
                        'id' => $existingProduct->ID,
                        'name' => $existingProduct->NAME,
                    ] : null,
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Could not retrieve barcode from supplier website',
                ]);
            }
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving barcode: '.$e->getMessage(),
            ]);
        }
    }

    /**
     * Create a new delivery item manually
     */
    public function createDeliveryItem(Request $request, Delivery $delivery): JsonResponse
    {
        // Validate the request
        $request->validate([
            'supplier_code' => 'required|string|max:255',
            'description' => 'required|string|max:255',
            'barcode' => 'nullable|string|max:255',
            'ordered_quantity' => 'required|integer|min:1|max:9999',
            'unit_cost' => 'required|numeric|min:0|max:999999.99',
            'units_per_case' => 'nullable|integer|min:1|max:9999',
        ]);

        // Only allow creating items for non-completed deliveries
        if ($delivery->status === 'completed') {
            return response()->json([
                'success' => false,
                'message' => 'Cannot add items to completed delivery',
            ], 422);
        }

        try {
            // Create the delivery item
            $deliveryItem = $this->deliveryService->createDeliveryItem($delivery, [
                'supplier_code' => $request->supplier_code,
                'description' => $request->description,
                'barcode' => $request->barcode,
                'unit_cost' => $request->unit_cost,
                'ordered_quantity' => $request->ordered_quantity,
                'units_per_case' => $request->units_per_case ?? 1,
            ]);

            // Load relationships for response
            $deliveryItem->load(['product.supplier']);

            // Prepare response data similar to what the scan interface expects
            $itemData = [
                'id' => $deliveryItem->id,
                'supplier_code' => $deliveryItem->supplier_code,
                'description' => $deliveryItem->description,
                'barcode' => $deliveryItem->barcode,
                'ordered_quantity' => $deliveryItem->ordered_quantity,
                'received_quantity' => $deliveryItem->received_quantity,
                'unit_cost' => $deliveryItem->unit_cost,
                'status' => $deliveryItem->status,
                'is_new_product' => $deliveryItem->is_new_product,
                'has_external_integration' => false,
                'external_image_url' => null,
                'product' => null,
            ];

            // Check for external integration
            if ($deliveryItem->barcode && $this->supplierService->hasExternalIntegration($delivery->supplier_id)) {
                $itemData['has_external_integration'] = true;
                $itemData['external_image_url'] = $this->supplierService->getExternalImageUrlByBarcode($delivery->supplier_id, $deliveryItem->barcode);
            }

            return response()->json([
                'success' => true,
                'message' => 'Product added successfully',
                'item' => $itemData,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create product: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Format delivery item with consistent supplier integration data
     */
    private function formatDeliveryItem($item, $delivery)
    {
        $itemData = $item->toArray();

        // Add enhanced quantity information
        $itemData['total_ordered_units'] = $item->total_ordered_units;
        $itemData['total_received_units'] = $item->total_received_units;
        $itemData['effective_case_units'] = $item->getEffectiveCaseUnits();
        $itemData['has_case_barcode'] = $item->hasCaseBarcode();
        $itemData['formatted_ordered_quantity'] = $item->formatted_ordered_quantity;
        $itemData['formatted_received_quantity'] = $item->formatted_received_quantity;

        if ($item->product && $item->product->supplier) {
            $itemData['has_external_integration'] = $this->supplierService->hasExternalIntegration($item->product->supplier->SupplierID);
            $itemData['external_image_url'] = $this->supplierService->getExternalImageUrl($item->product);
            $itemData['product'] = [
                'id' => $item->product->ID,
                'name' => $item->product->NAME,
                'supplier' => $item->product->supplier ? $item->product->supplier->toArray() : null,
            ];
        } else {
            // For new products, check if we have barcode and supplier integration
            if ($item->barcode && $item->is_new_product) {
                // Use delivery supplier ID for integration check
                $supplierId = $delivery->supplier_id;
                $itemData['has_external_integration'] = $this->supplierService->hasExternalIntegration($supplierId);
                $itemData['external_image_url'] = $this->supplierService->getExternalImageUrlByBarcode($supplierId, $item->barcode);
            } else {
                $itemData['has_external_integration'] = false;
                $itemData['external_image_url'] = null;
            }
            $itemData['product'] = null;
        }

        return $itemData;
    }

    /**
     * Validate CSV format based on supplier and headers
     */
    private function validateCsvFormat($file, int $supplierId): void
    {
        $tempPath = $file->getPathname();

        try {
            $csv = \League\Csv\Reader::createFromPath($tempPath, 'r');
            $csv->setHeaderOffset(0);
            $headers = $csv->getHeader();

            // Get supplier configuration
            $independentConfig = config('suppliers.external_links.independent');
            $naturalMedicineConfig = config('suppliers.external_links.natural_medicine');

            $isIndependentSupplier = $independentConfig && in_array($supplierId, $independentConfig['supplier_ids'] ?? []);
            $isNaturalMedicineSupplier = $naturalMedicineConfig && in_array($supplierId, $naturalMedicineConfig['supplier_ids'] ?? []);

            // Detect format based on headers
            $independentHeaders = ['Code', 'Product', 'Ordered', 'Qty', 'RSP', 'Price', 'Tax', 'Value'];
            $udeaHeaders = ['Code', 'Description', 'SKU', 'Content', 'Ordered', 'Qty', 'Price', 'Sale', 'Total'];
            $naturalMedicineHeaders = ['Stock_Code', 'Description', 'Qty', 'Tr_Price', 'Total', 'VAT_Percent'];

            $matchingIndependentHeaders = array_intersect($headers, $independentHeaders);
            $matchingUdeaHeaders = array_intersect($headers, $udeaHeaders);
            $matchingNaturalMedicineHeaders = array_intersect($headers, $naturalMedicineHeaders);

            // Validate Natural Medicine format
            if ($isNaturalMedicineSupplier || count($matchingNaturalMedicineHeaders) >= 5) {
                $requiredNaturalMedicineHeaders = ['Stock_Code', 'Description', 'Qty', 'Tr_Price', 'Total'];
                $missingHeaders = array_diff($requiredNaturalMedicineHeaders, $headers);
                if (! empty($missingHeaders)) {
                    throw new \Exception('Natural Medicine CSV format missing required headers: '.implode(', ', $missingHeaders));
                }

                // Validate at least one row of data
                $records = iterator_to_array($csv->getRecords());
                if (empty($records)) {
                    throw new \Exception('CSV file contains no data rows');
                }

                // Validate first row has required fields
                $firstRow = reset($records);
                if (empty($firstRow['Stock_Code']) || empty($firstRow['Description'])) {
                    throw new \Exception('Natural Medicine CSV format requires Stock_Code and Description fields to be populated');
                }
            }
            // Validate Independent format
            elseif ($isIndependentSupplier || count($matchingIndependentHeaders) >= 6) {
                $missingHeaders = array_diff($independentHeaders, $headers);
                if (! empty($missingHeaders)) {
                    throw new \Exception('Independent CSV format missing required headers: '.implode(', ', $missingHeaders));
                }

                // Validate at least one row of data
                $records = iterator_to_array($csv->getRecords());
                if (empty($records)) {
                    throw new \Exception('CSV file contains no data rows');
                }

                // Validate first row has required fields
                $firstRow = reset($records);
                if (empty($firstRow['Code']) || empty($firstRow['Product'])) {
                    throw new \Exception('Independent CSV format requires Code and Product fields to be populated');
                }
            }
            // Validate Udea format
            elseif (count($matchingUdeaHeaders) >= 6) {
                $requiredUdeaHeaders = ['Code', 'Description', 'Ordered', 'Qty', 'Price', 'Total'];
                $missingHeaders = array_diff($requiredUdeaHeaders, $headers);
                if (! empty($missingHeaders)) {
                    throw new \Exception('Udea CSV format missing required headers: '.implode(', ', $missingHeaders));
                }
            }
            // Unknown format
            else {
                throw new \Exception('CSV format not recognized. Expected Natural Medicine format with headers: '.
                                   implode(', ', $naturalMedicineHeaders).' OR Independent Health Foods format with headers: '.
                                   implode(', ', $independentHeaders).' OR Udea format with headers: '.
                                   implode(', ', $udeaHeaders));
            }

        } catch (\Exception $e) {
            throw new \Exception('CSV validation failed: '.$e->getMessage());
        }
    }

    /**
     * Update costs for products with significant price differences
     */
    public function updateCosts(Request $request, Delivery $delivery): JsonResponse
    {
        try {
            $request->validate([
                'threshold' => 'numeric|min:0|max:100',
                'items' => 'array',
            ]);

            $threshold = $request->get('threshold', 5); // Default 5% threshold
            $specificItems = $request->get('items', []); // Specific item IDs to update

            $updatedCount = 0;
            $skippedCount = 0;
            $errors = [];

            $zeroCostCount = 0;

            foreach ($delivery->items as $item) {
                // Skip if no product exists or specific items requested but this isn't one
                if (! $item->product || (! empty($specificItems) && ! in_array($item->id, $specificItems))) {
                    continue;
                }

                // Skip items with zero delivery cost (likely not delivered)
                if ($item->unit_cost <= 0) {
                    $zeroCostCount++;
                    $skippedCount++;

                    continue;
                }

                $currentCost = $item->product->PRICEBUY;
                $newCost = $item->unit_cost;

                // Calculate percentage difference
                $difference = $currentCost > 0 ? abs($newCost - $currentCost) / $currentCost * 100 : 0;

                // Only update if difference meets threshold
                if ($difference >= $threshold && abs($newCost - $currentCost) > 0.01) {
                    try {
                        // Update the product cost in POS system
                        $item->product->update(['PRICEBUY' => $newCost]);
                        $updatedCount++;

                        \Log::info('Delivery cost update', [
                            'delivery_id' => $delivery->id,
                            'product_id' => $item->product->ID,
                            'product_name' => $item->product->NAME,
                            'old_cost' => $currentCost,
                            'new_cost' => $newCost,
                            'difference_percent' => round($difference, 2),
                        ]);
                    } catch (\Exception $e) {
                        $errors[] = "Failed to update {$item->description}: {$e->getMessage()}";
                        $skippedCount++;
                    }
                } else {
                    $skippedCount++;
                }
            }

            $message = "Updated costs for {$updatedCount} products";

            $skipReasons = [];
            if ($zeroCostCount > 0) {
                $skipReasons[] = "{$zeroCostCount} with €0.00 cost (not delivered)";
            }
            if ($skippedCount - $zeroCostCount > 0) {
                $skipReasons[] = ($skippedCount - $zeroCostCount).' below threshold or no change';
            }

            if (! empty($skipReasons)) {
                $message .= ', skipped '.implode(', ', $skipReasons);
            }

            if (! empty($errors)) {
                $message .= '. Errors: '.implode(', ', array_slice($errors, 0, 3));
                if (count($errors) > 3) {
                    $message .= ' and '.(count($errors) - 3).' more';
                }
            }

            return response()->json([
                'success' => true,
                'message' => $message,
                'updated_count' => $updatedCount,
                'skipped_count' => $skippedCount,
                'zero_cost_count' => $zeroCostCount,
                'errors' => $errors,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Cost update failed: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Sync delivery items to the legacy POS delivery table.
     * This clears existing data and populates with current delivery items.
     */
    public function syncToLegacy(Delivery $delivery): RedirectResponse
    {
        try {
            $itemCount = $delivery->items->count();

            if ($itemCount === 0) {
                return back()->withErrors(['sync' => 'Cannot sync empty delivery to legacy system.']);
            }

            DB::connection('pos')->transaction(function () use ($delivery) {
                // Clear existing legacy delivery data
                LegacyDelivery::truncate();

                // Helper function to sync a single item to legacy
                $syncItemToLegacy = function ($item, $isOOS = false) {
                    $unitsPerCase = $item->units_per_case ?? 1;
                    $cases = $item->case_ordered_quantity ?? 0;
                    $looseUnits = $item->unit_ordered_quantity ?? 0;

                    // Calculate case units (same logic for OOS and delivered items)
                    if ($unitsPerCase > 1 && $cases > 0) {
                        if ($looseUnits > 0) {
                            // Mixed order (cases + loose units) - treat as units for legacy
                            $syncCaseUnits = 1;
                            $syncMyOrder = ($cases * $unitsPerCase) + $looseUnits;
                        } else {
                            // Full case order - use actual case units
                            $syncCaseUnits = $unitsPerCase;
                            $syncMyOrder = $cases;
                        }
                    } else {
                        // Unit-based order or no case info
                        $syncCaseUnits = 1;
                        $syncMyOrder = $item->ordered_quantity;
                    }

                    // For OOS items, set myOrder to 0 (but keep correct caseUnits)
                    if ($isOOS) {
                        $syncMyOrder = 0;
                    }

                    LegacyDelivery::create([
                        'prodName' => $item->description,
                        'supCode' => $item->supplier_code,
                        'cost' => $item->unit_cost,
                        'caseUnits' => $syncCaseUnits,
                        'myOrder' => $syncMyOrder,
                        'rrPrice' => $item->sale_price ?? 0,
                    ]);
                };

                // First pass: Sync items that were delivered (non-OOS)
                foreach ($delivery->items as $item) {
                    // Skip OOS items in first pass (ordered but not delivered by supplier)
                    if ($item->ordered_quantity > 0 && $item->invoice_delivered_quantity == 0) {
                        continue;
                    }
                    $syncItemToLegacy($item);
                }

                // Second pass: Sync OOS items (grouped at bottom) with myOrder=0
                foreach ($delivery->items as $item) {
                    // Only process OOS items in second pass
                    if (! ($item->ordered_quantity > 0 && $item->invoice_delivered_quantity == 0)) {
                        continue;
                    }
                    $syncItemToLegacy($item, true);
                }
            });

            return redirect()->route('delivery-legacy.index')
                ->with('success', "Synced {$itemCount} items to legacy. Select a scan session to compare.");
        } catch (\Exception $e) {
            return back()->withErrors(['sync' => 'Failed to sync to legacy: '.$e->getMessage()]);
        }
    }
}
