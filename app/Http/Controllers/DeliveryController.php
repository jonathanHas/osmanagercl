<?php

namespace App\Http\Controllers;

use App\Models\Delivery;
use App\Models\DeliveryItem;
use App\Models\Supplier;
use App\Services\DeliveryService;
use App\Services\SupplierService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class DeliveryController extends Controller
{
    private DeliveryService $deliveryService;

    private SupplierService $supplierService;

    public function __construct(DeliveryService $deliveryService, SupplierService $supplierService)
    {
        $this->deliveryService = $deliveryService;
        $this->supplierService = $supplierService;
    }

    /**
     * Display a listing of deliveries
     */
    public function index(): View
    {
        $deliveries = Delivery::with(['supplier', 'items'])
            ->orderBy('delivery_date', 'desc')
            ->paginate(20);

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

        try {
            // Validate that file upload was successful
            $uploadedFile = $request->file('csv_file');
            if (!$uploadedFile || !$uploadedFile->isValid()) {
                throw new \Exception('File upload failed or file is invalid');
            }

            // Store the uploaded file
            $csvPath = $uploadedFile->store('temp');
            if (!$csvPath) {
                throw new \Exception('Failed to store uploaded file');
            }

            $fullPath = Storage::disk('local')->path($csvPath);
            
            // Verify the file exists after upload
            if (!file_exists($fullPath)) {
                throw new \Exception('Uploaded file not found at: '.$fullPath);
            }

            // Verify file is readable
            if (!is_readable($fullPath)) {
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
     * Display the specified delivery
     */
    public function show(Delivery $delivery): View
    {
        $delivery->load(['supplier', 'items.product.supplier', 'scans']);

        $summary = $this->deliveryService->getDeliverySummary($delivery->id);

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

        return view('deliveries.scan', compact('delivery'))->with('supplierService', $this->supplierService);
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

        $item->update(['received_quantity' => $request->quantity]);
        $item->updateStatus();

        return response()->json([
            'success' => true,
            'item' => $item->fresh(),
            'message' => 'Quantity updated successfully',
        ]);
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

                return response()->json([
                    'success' => true,
                    'barcode' => $barcode,
                    'message' => 'Barcode retrieved successfully',
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
}
