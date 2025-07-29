<?php

namespace App\Services;

use App\Jobs\RetrieveBarcodeJob;
use App\Models\Delivery;
use App\Models\DeliveryItem;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\SupplierLink;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use League\Csv\Reader;

class DeliveryService
{
    private UdeaScrapingService $udeaService;

    public function __construct(UdeaScrapingService $udeaService)
    {
        $this->udeaService = $udeaService;
    }

    /**
     * Import delivery from CSV file
     */
    public function importFromCsv(string $filePath, int $supplierId, ?string $deliveryDate = null): Delivery
    {
        $csv = Reader::createFromPath($filePath, 'r');
        $csv->setHeaderOffset(0);

        return DB::transaction(function () use ($csv, $supplierId, $deliveryDate, $filePath) {
            // Create delivery header
            $delivery = Delivery::create([
                'delivery_number' => 'DEL-'.date('Ymd-His'),
                'supplier_id' => $supplierId,
                'delivery_date' => $deliveryDate ? \Carbon\Carbon::parse($deliveryDate) : now(),
                'status' => 'draft',
                'import_data' => ['filename' => basename($filePath), 'imported_at' => now()],
            ]);

            $totalExpected = 0;
            $records = $csv->getRecords();

            foreach ($records as $record) {
                // Parse CSV row
                $item = $this->parseDeliveryRow($record);

                // Check if product exists in our system
                $product = $this->findProductBySupplierCode($item['code'], $supplierId);

                // Create delivery item
                $deliveryItem = DeliveryItem::create([
                    'delivery_id' => $delivery->id,
                    'supplier_code' => $item['code'],
                    'sku' => $item['sku'],
                    'description' => $item['description'],
                    'units_per_case' => $item['units_per_case'],
                    'unit_cost' => $item['unit_cost'],
                    'ordered_quantity' => $item['ordered_quantity'],
                    'total_cost' => $item['total_cost'],
                    'product_id' => $product?->ID,
                    'is_new_product' => ! $product,
                    'barcode' => $product?->CODE, // Use existing barcode if available
                ]);

                $totalExpected += $item['total_cost'];

                // If new product, queue barcode retrieval
                if (! $product) {
                    $this->queueBarcodeRetrieval($deliveryItem);
                }
            }

            $delivery->update(['total_expected' => $totalExpected]);

            return $delivery;
        });
    }

    /**
     * Parse a CSV row into structured data
     */
    private function parseDeliveryRow(array $row): array
    {
        // The SKU field contains the number of retail units per wholesale case
        // For example: if SKU = 5 and Content = "19 pc", then we receive 5 retail packs per case
        $unitsPerCase = (int) $row['SKU'];

        return [
            'code' => $row['Code'],
            'ordered_quantity' => (int) $row['Ordered'],
            'quantity' => (int) $row['Qty'],
            'sku' => $row['SKU'],
            'content' => $row['Content'],
            'description' => $row['Description'],
            'unit_cost' => (float) $row['Price'],
            'sale_price' => (float) $row['Sale'],
            'total_cost' => (float) $row['Total'],
            'units_per_case' => $unitsPerCase,
        ];
    }

    /**
     * Find product by supplier code
     */
    private function findProductBySupplierCode(string $code, int $supplierId): ?Product
    {
        $supplierLink = SupplierLink::where('SupplierID', $supplierId)
            ->where('SupplierCode', $code)
            ->first();

        if ($supplierLink && $supplierLink->product) {
            return $supplierLink->product;
        }

        return null;
    }

    /**
     * Queue barcode retrieval for new products
     */
    private function queueBarcodeRetrieval(DeliveryItem $item): void
    {
        // Dispatch proper Laravel job with retry mechanism
        RetrieveBarcodeJob::dispatch($item)
            ->delay(now()->addSeconds(5)) // Small delay to let the response finish first
            ->onQueue('barcode-retrieval'); // Use dedicated queue for barcode jobs
    }

    /**
     * Process barcode scan during delivery
     */
    public function processScan(int $deliveryId, string $barcode, int $quantity = 1, ?string $scannedBy = null): array
    {
        $delivery = Delivery::findOrFail($deliveryId);

        // Try to find matching delivery item by barcode only
        // Note: Supplier codes cannot be scanned as they are internal codes only
        $item = DeliveryItem::where('delivery_id', $deliveryId)
            ->where('barcode', $barcode)
            ->first();

        if ($item) {
            // Update received quantity
            $newQuantity = $item->received_quantity + $quantity;
            $item->update([
                'received_quantity' => $newQuantity,
                'status' => $this->calculateItemStatus($item->ordered_quantity, $newQuantity),
            ]);

            // Record scan
            $scan = $delivery->scans()->create([
                'delivery_item_id' => $item->id,
                'barcode' => $barcode,
                'quantity' => $quantity,
                'matched' => true,
                'scanned_by' => $scannedBy ?? 'System',
            ]);

            return [
                'success' => true,
                'item' => $item->fresh(),
                'message' => "Scanned: {$item->description} (Total: {$newQuantity}/{$item->ordered_quantity})",
            ];
        } else {
            // Record unmatched scan
            $scan = $delivery->scans()->create([
                'barcode' => $barcode,
                'quantity' => $quantity,
                'matched' => false,
                'scanned_by' => $scannedBy ?? 'System',
            ]);

            return [
                'success' => false,
                'message' => "Unknown product: {$barcode}",
                'barcode' => $barcode,
            ];
        }
    }

    /**
     * Calculate item status based on quantities
     */
    private function calculateItemStatus(int $ordered, int $received): string
    {
        if ($received == 0) {
            return 'pending';
        }
        if ($received < $ordered) {
            return 'partial';
        }
        if ($received == $ordered) {
            return 'complete';
        }

        return 'excess';
    }

    /**
     * Get delivery summary with discrepancies
     */
    public function getDeliverySummary(int $deliveryId): array
    {
        $delivery = Delivery::with(['items', 'scans'])->findOrFail($deliveryId);

        $summary = [
            'total_items' => $delivery->items->count(),
            'complete_items' => $delivery->items->where('status', 'complete')->count(),
            'partial_items' => $delivery->items->where('status', 'partial')->count(),
            'missing_items' => $delivery->items->where('status', 'pending')->count(),
            'excess_items' => $delivery->items->where('status', 'excess')->count(),
            'unmatched_scans' => $delivery->scans->where('matched', false)->count(),
            'total_expected_value' => $delivery->total_expected,
            'total_received_value' => $delivery->items->sum(function ($item) {
                return $item->unit_cost * $item->received_quantity;
            }),
        ];

        $summary['discrepancies'] = $delivery->items
            ->whereIn('status', ['partial', 'pending', 'excess'])
            ->map(function ($item) {
                return [
                    'code' => $item->supplier_code,
                    'description' => $item->description,
                    'ordered' => $item->ordered_quantity,
                    'received' => $item->received_quantity,
                    'difference' => $item->received_quantity - $item->ordered_quantity,
                    'value_difference' => ($item->received_quantity - $item->ordered_quantity) * $item->unit_cost,
                ];
            });

        return $summary;
    }

    /**
     * Complete delivery and update stock
     */
    public function completeDelivery(int $deliveryId): void
    {
        DB::transaction(function () use ($deliveryId) {
            $delivery = Delivery::with('items')->findOrFail($deliveryId);

            $processedItems = 0;
            $skippedNewProducts = [];

            foreach ($delivery->items as $item) {
                if ($item->product_id && $item->received_quantity > 0) {
                    // Update stock in POS system for existing products
                    $this->updateProductStock($item->product_id, $item->received_quantity);

                    // Update cost price if different
                    if ($item->unit_cost != $item->product->PRICEBUY) {
                        $item->product->update(['PRICEBUY' => $item->unit_cost]);
                    }

                    $processedItems++;
                } elseif ($item->is_new_product && $item->received_quantity > 0) {
                    // Track new products that need manual POS integration
                    $skippedNewProducts[] = [
                        'supplier_code' => $item->supplier_code,
                        'description' => $item->description,
                        'barcode' => $item->barcode,
                        'received_quantity' => $item->received_quantity,
                        'unit_cost' => $item->unit_cost,
                    ];
                }
            }

            // Log information about the completion
            Log::info('Delivery completed', [
                'delivery_id' => $deliveryId,
                'processed_items' => $processedItems,
                'new_products_requiring_pos_integration' => count($skippedNewProducts),
                'new_products' => $skippedNewProducts,
            ]);

            $delivery->update([
                'status' => 'completed',
                'total_received' => $delivery->items->sum(function ($item) {
                    return $item->unit_cost * $item->received_quantity;
                }),
            ]);
        });
    }

    /**
     * Update product stock (implement based on your POS system)
     */
    private function updateProductStock(string $productId, int $quantity): void
    {
        // This would integrate with your POS stock management
        Log::info("Stock update: Product {$productId} increased by {$quantity}");
    }

    /**
     * Retrieve barcode for a specific supplier code
     */
    public function retrieveBarcode(string $supplierCode): ?string
    {
        try {
            $productData = $this->udeaService->getProductData($supplierCode);

            if ($productData && isset($productData['barcode'])) {
                return $productData['barcode'];
            }

            return null;

        } catch (\Exception $e) {
            Log::error('Failed to retrieve barcode for '.$supplierCode, [
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Create a new delivery item manually
     */
    public function createDeliveryItem(Delivery $delivery, array $itemData): DeliveryItem
    {
        // Try to find existing product by supplier code
        $product = $this->findProductBySupplierCode($delivery->supplier_id, $itemData['supplier_code']);

        // Calculate total cost
        $totalCost = $itemData['ordered_quantity'] * $itemData['unit_cost'];

        // Create the delivery item
        $deliveryItem = DeliveryItem::create([
            'delivery_id' => $delivery->id,
            'supplier_code' => $itemData['supplier_code'],
            'description' => $itemData['description'],
            'barcode' => $itemData['barcode'] ?: ($product?->CODE ?? null),
            'units_per_case' => $itemData['units_per_case'] ?? 1,
            'unit_cost' => $itemData['unit_cost'],
            'ordered_quantity' => $itemData['ordered_quantity'],
            'total_cost' => $totalCost,
            'product_id' => $product?->ID,
            'is_new_product' => ! $product,
            'received_quantity' => 0,
            'status' => 'pending',
        ]);

        // If this is a new product and we don't have a barcode, queue barcode retrieval
        if ($deliveryItem->is_new_product && ! $deliveryItem->barcode) {
            RetrieveBarcodeJob::dispatch($deliveryItem)
                ->delay(now()->addSeconds(2))
                ->onQueue('barcode-retrieval');
        }

        return $deliveryItem;
    }
}
