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

    private ?IndependentScrapingService $independentService;

    public function __construct(UdeaScrapingService $udeaService, ?IndependentScrapingService $independentService = null)
    {
        $this->udeaService = $udeaService;
        $this->independentService = $independentService;
    }

    /**
     * Import delivery from parsed PDF data.
     *
     * This method creates a delivery from the structured data returned by
     * the DeliveryParsingService. The PDF parser provides pre-calculated
     * unit quantities which are more reliable than CSV interpretation.
     *
     * @param  array  $items  Array of parsed items from PDF
     * @param  int  $supplierId  Supplier ID
     * @param  string|null  $deliveryDate  Delivery date
     * @param  string|null  $filename  Original filename for reference
     */
    public function importFromPdfData(
        array $items,
        int $supplierId,
        ?string $deliveryDate = null,
        ?string $filename = null,
        ?array $totals = null
    ): Delivery {
        return DB::transaction(function () use ($items, $supplierId, $deliveryDate, $filename, $totals) {
            // Extract totals verification data
            $invoiceStatedTotal = $totals['grand_stated'] ?? null;
            $calculatedTotal = $totals['grand_calculated'] ?? null;
            $discrepancy = $totals['discrepancy'] ?? null;
            $hasDiscrepancy = isset($totals['totals_match']) ? ! $totals['totals_match'] : false;

            // Create delivery header
            $delivery = Delivery::create([
                'delivery_number' => 'DEL-'.date('Ymd-His'),
                'supplier_id' => $supplierId,
                'delivery_date' => $deliveryDate ? \Carbon\Carbon::parse($deliveryDate) : now(),
                'status' => 'draft',
                'import_data' => [
                    'filename' => $filename ?? 'pdf_import',
                    'imported_at' => now(),
                    'format' => 'pdf_direct',
                    'source' => 'delivery_parsing_service',
                ],
                // Totals verification
                'invoice_stated_total' => $invoiceStatedTotal,
                'calculated_total' => $calculatedTotal,
                'total_discrepancy' => $discrepancy,
                'has_discrepancy' => $hasDiscrepancy,
            ]);

            $totalExpected = 0;

            foreach ($items as $record) {
                // The PDF parser provides clearly named fields
                $productCode = $record['Code'];
                $productName = $record['Product'];

                // These are already calculated total units from the PDF parser
                $totalOrderedUnits = (int) ($record['Total_Ordered_Units'] ?? 0);
                $totalDeliveredUnits = (int) ($record['Total_Delivered_Units'] ?? 0);

                // Case size from product description
                $caseSize = (int) ($record['Case_Size'] ?? 1);
                if ($caseSize == 0) {
                    $caseSize = 1;
                }

                // Unit cost is already per-unit from the PDF parser
                $unitCost = (float) ($record['Unit_Cost'] ?? 0);
                $casePrice = (float) ($record['Price'] ?? 0);
                $lineTotal = (float) ($record['Value'] ?? 0);

                // Tax and RSP if available
                $tax = (float) ($record['Tax'] ?? 0);
                $rsp = (float) ($record['RSP'] ?? 0);

                // Weight-based product fields
                $isWeightBased = (bool) ($record['is_weight_based'] ?? false);
                $weightPerUnit = $isWeightBased ? (float) ($record['weight_per_unit'] ?? 0) : null;
                $weightUnit = $isWeightBased ? ($record['weight_unit'] ?? null) : null;
                // Use total_weight from parser if available, otherwise calculate
                $totalWeight = $isWeightBased ? (float) ($record['total_weight'] ?? ($weightPerUnit * $totalOrderedUnits)) : null;

                // Calculate tax rate from tax amount and line value
                $taxRate = null;
                $normalizedTaxRate = null;
                if ($lineTotal > 0) {
                    if ($tax > 0) {
                        $taxRate = ($tax / $lineTotal) * 100;
                        $taxRate = round($taxRate, 2);
                    } else {
                        $taxRate = 0.0;  // Explicitly set to 0% for zero tax
                    }
                    $normalizedTaxRate = $this->normalizeIrishVatRate($taxRate);
                }

                // Check if product exists in our system
                $product = $this->findProductBySupplierCode($productCode, $supplierId);
                $supplierLink = SupplierLink::where('SupplierID', $supplierId)
                    ->where('SupplierCode', $productCode)
                    ->first();

                // Calculate case quantities for display (how many full cases + remaining units)
                $orderedCases = $caseSize > 1 ? intval($totalOrderedUnits / $caseSize) : 0;
                $orderedUnits = $caseSize > 1 ? $totalOrderedUnits % $caseSize : $totalOrderedUnits;

                $deliveredCases = $caseSize > 1 ? intval($totalDeliveredUnits / $caseSize) : 0;
                $deliveredUnits = $caseSize > 1 ? $totalDeliveredUnits % $caseSize : $totalDeliveredUnits;

                // Create delivery item with correct quantity interpretation
                $deliveryItem = DeliveryItem::create([
                    'delivery_id' => $delivery->id,
                    'supplier_code' => $productCode,
                    'description' => $productName,
                    'units_per_case' => $caseSize,
                    'supplier_case_units' => $supplierLink?->CaseUnits,

                    // Unit cost is per individual unit
                    'unit_cost' => $unitCost,

                    // Legacy ordered_quantity field - now stores TOTAL UNITS (not cases)
                    'ordered_quantity' => $totalOrderedUnits,
                    'invoice_delivered_quantity' => $totalDeliveredUnits,

                    // New quantity fields for clarity
                    'case_ordered_quantity' => $orderedCases,
                    'unit_ordered_quantity' => $orderedUnits,
                    'quantity_type' => 'unit', // We're tracking in units

                    // Total cost is the line value
                    'total_cost' => $lineTotal,

                    // Product linking
                    'product_id' => $product?->ID,
                    'is_new_product' => ! $product,
                    'barcode' => $product?->CODE,
                    'outer_code' => $supplierLink?->OuterCode,

                    // Additional fields
                    'sale_price' => $rsp,
                    'tax_amount' => $tax,
                    'tax_rate' => $taxRate,
                    'normalized_tax_rate' => $normalizedTaxRate,
                    'sku' => $caseSize,

                    // Weight-based product fields
                    'is_weight_based' => $isWeightBased,
                    'weight_per_unit' => $weightPerUnit,
                    'weight_unit' => $weightUnit,
                    'total_weight' => $totalWeight,
                ]);

                $totalExpected += $lineTotal;

                // If new product, queue barcode retrieval
                if (! $product) {
                    $this->queueBarcodeRetrieval($deliveryItem, true);
                }
            }

            $delivery->update(['total_expected' => $totalExpected]);

            return $delivery;
        });
    }

    /**
     * Import delivery from CSV file
     */
    public function importFromCsv(string $filePath, int $supplierId, ?string $deliveryDate = null): Delivery
    {
        $csv = Reader::createFromPath($filePath, 'r');
        $csv->setHeaderOffset(0);

        return DB::transaction(function () use ($csv, $supplierId, $deliveryDate, $filePath) {
            // Detect CSV format based on headers and supplier
            $headers = $csv->getHeader();
            $isIndependentFormat = $this->detectIndependentCsvFormat($headers, $supplierId);
            $isNaturalMedicineFormat = $this->detectNaturalMedicineCsvFormat($headers, $supplierId);

            // Determine format name for logging
            $formatName = $isNaturalMedicineFormat ? 'natural_medicine' : ($isIndependentFormat ? 'independent' : 'udea');

            // Create delivery header
            $delivery = Delivery::create([
                'delivery_number' => 'DEL-'.date('Ymd-His'),
                'supplier_id' => $supplierId,
                'delivery_date' => $deliveryDate ? \Carbon\Carbon::parse($deliveryDate) : now(),
                'status' => 'draft',
                'import_data' => [
                    'filename' => basename($filePath),
                    'imported_at' => now(),
                    'format' => $formatName,
                ],
            ]);

            $totalExpected = 0;
            $records = $csv->getRecords();

            foreach ($records as $record) {
                // Parse CSV row based on detected format
                if ($isNaturalMedicineFormat) {
                    $item = $this->parseNaturalMedicineCsv($record);
                } elseif ($isIndependentFormat) {
                    $item = $this->parseIndependentCsv($record);
                } else {
                    $item = $this->parseDeliveryRow($record);
                }

                // Check if product exists in our system and get SupplierLink data
                $product = $this->findProductBySupplierCode($item['code'], $supplierId);
                $supplierLink = SupplierLink::where('SupplierID', $supplierId)
                    ->where('SupplierCode', $item['code'])
                    ->first();

                // Get case/unit info
                $unitsPerCase = $item['units_per_case'];
                $supplierCaseUnits = $supplierLink?->CaseUnits;

                // CRITICAL FIX: Independent format already provides quantities in UNITS, not cases
                // The parseIndependentCsv method calculates total units from the cases/units notation
                // Other formats (Udea, legacy) provide quantities in CASES
                if ($isIndependentFormat || $isNaturalMedicineFormat) {
                    // Independent/Natural Medicine: ordered_quantity IS total units
                    $totalOrderedUnits = (int) $item['ordered_quantity'];
                    $quantityType = 'unit';

                    // Calculate how many full cases + remaining units this represents
                    if ($unitsPerCase > 1) {
                        $orderedCases = intval($totalOrderedUnits / $unitsPerCase);
                        $orderedIndividualUnits = $totalOrderedUnits % $unitsPerCase;
                    } else {
                        $orderedCases = 0;
                        $orderedIndividualUnits = $totalOrderedUnits;
                    }
                } else {
                    // Udea/legacy format: ordered_quantity IS number of cases
                    $orderedCases = (int) $item['ordered_quantity'];
                    $orderedIndividualUnits = 0;
                    $totalOrderedUnits = $orderedCases * $unitsPerCase;
                    $quantityType = 'case';
                }

                // Validate against SupplierLink if available
                if ($supplierCaseUnits && $supplierCaseUnits !== $unitsPerCase && $unitsPerCase > 1) {
                    Log::warning('Case units mismatch for delivery import', [
                        'supplier_code' => $item['code'],
                        'csv_units_per_case' => $unitsPerCase,
                        'supplier_link_case_units' => $supplierCaseUnits,
                        'delivery_id' => $delivery->id,
                    ]);
                }

                // Create delivery item with enhanced quantity fields
                // ordered_quantity now stores TOTAL UNITS for consistency with PDF import
                $deliveryItemData = [
                    'delivery_id' => $delivery->id,
                    'supplier_code' => $item['code'],
                    'sku' => $item['sku'],
                    'description' => $item['description'],
                    'units_per_case' => $unitsPerCase,
                    'supplier_case_units' => $supplierCaseUnits,
                    'unit_cost' => $item['unit_cost'],
                    'ordered_quantity' => $totalOrderedUnits, // Now stores TOTAL UNITS (not cases)
                    'case_ordered_quantity' => $orderedCases,
                    'unit_ordered_quantity' => $orderedIndividualUnits,
                    'quantity_type' => $quantityType,
                    'total_cost' => $item['total_cost'],
                    'product_id' => $product?->ID,
                    'is_new_product' => ! $product,
                    'barcode' => $product?->CODE, // Individual unit barcode
                    'outer_code' => $supplierLink?->OuterCode, // Case barcode
                ];

                // Add supplier-specific pricing fields if available
                if ($isIndependentFormat) {
                    $deliveryItemData = array_merge($deliveryItemData, [
                        'sale_price' => $item['sale_price'] ?? null,
                        'tax_amount' => $item['tax_amount'] ?? null,
                        'tax_rate' => $item['tax_rate'] ?? null,
                        'normalized_tax_rate' => $item['normalized_tax_rate'] ?? null,
                        'line_value_ex_vat' => $item['line_value_ex_vat'] ?? null,
                        'unit_cost_including_tax' => $item['unit_cost_including_tax'] ?? null,
                    ]);
                } elseif ($isNaturalMedicineFormat) {
                    $deliveryItemData = array_merge($deliveryItemData, [
                        'sale_price' => $item['sale_price'] ?? null,
                        'tax_rate' => $item['tax_rate'] ?? null,
                        'normalized_tax_rate' => $item['normalized_tax_rate'] ?? null,
                    ]);
                }

                $deliveryItem = DeliveryItem::create($deliveryItemData);

                $totalExpected += $item['total_cost'];

                // If new product, queue barcode retrieval
                if (! $product) {
                    // For Natural Medicine, use the same retrieval as Independent (no special scraping service yet)
                    $this->queueBarcodeRetrieval($deliveryItem, $isIndependentFormat || $isNaturalMedicineFormat);
                }
            }

            $delivery->update(['total_expected' => $totalExpected]);

            return $delivery;
        });
    }

    /**
     * Detect if CSV is in Independent Health Foods format
     */
    private function detectIndependentCsvFormat(array $headers, int $supplierId): bool
    {
        // Get Independent supplier configuration
        $independentConfig = config('suppliers.external_links.independent');

        // Check if supplier ID matches Independent suppliers
        if ($independentConfig && in_array($supplierId, $independentConfig['supplier_ids'] ?? [])) {
            return true;
        }

        // Check for enhanced CSV format headers first
        $enhancedHeaders = ['Total_Ordered_Units', 'Total_Delivered_Units', 'Case_Size', 'Unit_Cost', 'Price_Valid'];
        $matchingEnhanced = array_intersect($headers, $enhancedHeaders);

        if (count($matchingEnhanced) >= 3) {
            // This is the enhanced format
            return true;
        }

        // Fall back to checking original format headers
        $originalHeaders = ['Code', 'Product', 'Ordered', 'Qty', 'RSP', 'Price', 'Tax', 'Value'];
        $matchingOriginal = array_intersect($headers, $originalHeaders);

        // Consider it Independent format if most key headers match
        return count($matchingOriginal) >= 6; // At least 6 out of 8 headers match
    }

    /**
     * Detect if CSV is in Natural Medicine Company format
     */
    private function detectNaturalMedicineCsvFormat(array $headers, int $supplierId): bool
    {
        // Get Natural Medicine supplier configuration
        $naturalMedicineConfig = config('suppliers.external_links.natural_medicine');

        // Check if supplier ID matches Natural Medicine suppliers
        if ($naturalMedicineConfig && in_array($supplierId, $naturalMedicineConfig['supplier_ids'] ?? [])) {
            return true;
        }

        // Check for Natural Medicine-specific headers
        $naturalMedicineHeaders = ['Stock_Code', 'Tr_Price', 'VAT_Percent', 'Validation_Status', 'SourcePDF'];
        $matchingHeaders = array_intersect($headers, $naturalMedicineHeaders);

        // Consider it Natural Medicine format if at least 4 key headers match
        return count($matchingHeaders) >= 4;
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
     * Parse Independent Health Foods Enhanced CSV row into structured data
     */
    private function parseIndependentCsv(array $row): array
    {
        // Enhanced CSV format with clearer unit breakdown
        $productCode = trim($row['Code'] ?? '');
        $productName = trim($row['Product'] ?? '');

        // Use the pre-calculated total units from the enhanced CSV
        $totalOrderedUnits = (float) ($row['Total_Ordered_Units'] ?? 0);
        $totalDeliveredUnits = (float) ($row['Total_Delivered_Units'] ?? 0);

        // Case size information (units per case)
        $caseSize = (int) ($row['Case_Size'] ?? 1);
        if ($caseSize == 0) {
            $caseSize = 1; // Default to 1 if not specified
        }

        // Check for DRS (Deposit Return Scheme) items
        $isDrsItem = str_contains($productName, '(DRS)') || str_ends_with($productCode, 'D');
        $isDrsDeposit = str_contains($productName, 'Deposit') || str_contains($productName, 'DRS-1');

        if ($isDrsItem || $isDrsDeposit) {
            Log::warning('DRS item detected in Independent delivery - requires special handling', [
                'code' => $productCode,
                'product' => $productName,
                'is_deposit' => $isDrsDeposit,
                'total_units' => $totalDeliveredUnits,
            ]);
        }

        // Extract product attributes from name
        $attributes = $this->extractIndependentProductAttributes($productName);

        // Pricing information
        $unitCost = (float) ($row['Unit_Cost'] ?? $row['Price'] ?? 0);
        $taxAmount = (float) ($row['Tax'] ?? 0);
        $rsp = (float) ($row['RSP'] ?? 0);
        $lineValue = (float) ($row['Value'] ?? 0);

        // Calculate tax rate if needed
        $taxRate = null;
        $normalizedTaxRate = null;
        if ($lineValue > 0) {
            if ($taxAmount > 0) {
                $taxRate = ($taxAmount / $lineValue) * 100;
                $taxRate = round($taxRate, 2);
            } else {
                $taxRate = 0.0;  // Explicitly set to 0% for zero tax
            }
            $normalizedTaxRate = $this->normalizeIrishVatRate($taxRate);
        }

        // Calculate per-unit tax amount based on delivered units
        $unitTaxAmount = $totalDeliveredUnits > 0 ? $taxAmount / $totalDeliveredUnits : 0;
        $unitCostIncludingTax = $unitCost + $unitTaxAmount;

        // Use total delivered units as the primary quantity (what we're being charged for)
        // This is the most accurate representation of what was delivered
        $deliveredQuantity = $totalDeliveredUnits;
        $orderedQuantity = $totalOrderedUnits;

        return [
            'code' => $productCode,
            'ordered_quantity' => $orderedQuantity, // Total units ordered
            'quantity' => $deliveredQuantity, // Total units delivered (what we're charged for)
            'description' => $productName,
            'unit_cost' => $unitCost,
            'sale_price' => $rsp,
            'tax_amount' => $taxAmount,
            'tax_rate' => $taxRate,
            'normalized_tax_rate' => $normalizedTaxRate,
            'line_value_ex_vat' => $lineValue,
            'unit_cost_including_tax' => $unitCostIncludingTax,
            'total_cost' => $lineValue,
            'units_per_case' => $caseSize,
            'attributes' => $attributes,
            'sku' => $caseSize, // Use case size as SKU for consistency
            'content' => $this->formatContentString($caseSize),
            // Legacy fields for backward compatibility
            'rsp' => $rsp,
            'unit_cost_including_tax_legacy' => $unitCost + $unitTaxAmount,
        ];
    }

    /**
     * Parse Natural Medicine Company CSV row into structured data
     */
    private function parseNaturalMedicineCsv(array $row): array
    {
        // Extract basic fields from Natural Medicine CSV format
        $stockCode = trim($row['Stock_Code'] ?? '');
        $description = trim($row['Description'] ?? '');
        $quantity = (int) ($row['Qty'] ?? 0);
        $tradePrice = (float) ($row['Tr_Price'] ?? 0);
        $total = (float) ($row['Total'] ?? 0);
        $vatPercent = (float) ($row['VAT_Percent'] ?? 0);
        $rrp = (float) ($row['RRP'] ?? 0);

        // Natural Medicine supplies in individual units (not cases)
        // Unless the product description indicates otherwise
        $unitsPerCase = 1;

        return [
            'code' => $stockCode,
            'ordered_quantity' => $quantity,
            'quantity' => $quantity,
            'description' => $description,
            'unit_cost' => $tradePrice,
            'sale_price' => $rrp,
            'tax_rate' => $vatPercent,
            'normalized_tax_rate' => $this->normalizeIrishVatRate($vatPercent),
            'total_cost' => $total,
            'units_per_case' => $unitsPerCase,
            'sku' => $unitsPerCase, // Use units per case as SKU for consistency
            'content' => $this->formatContentString($unitsPerCase),
            // Additional Natural Medicine specific fields
            'rrp' => $rrp,
            'vat_percent' => $vatPercent,
        ];
    }

    /**
     * Extract product attributes from Independent product names
     * Example: "All About KombuchaRaspberry Can (Org)(DRS) 1x330ml"
     */
    private function extractIndependentProductAttributes(string $productName): array
    {
        $attributes = [];

        // Extract organic indicator
        if (preg_match('/\(Org\)/', $productName)) {
            $attributes['organic'] = true;
        }

        // Extract deposit return scheme
        if (preg_match('/\(DRS\)/', $productName)) {
            $attributes['deposit_return_scheme'] = true;
        }

        // Extract packaging size (e.g., "1x330ml", "6x250g")
        if (preg_match('/(\d+)x(\d+(?:\.\d+)?)(ml|g|kg|l|litre)/', $productName, $matches)) {
            $attributes['package_quantity'] = (int) $matches[1];
            $attributes['package_size'] = $matches[2];
            $attributes['package_unit'] = $matches[3];
        }

        return $attributes;
    }

    /**
     * Extract units per case from product name
     */
    private function extractUnitsFromProductName(string $productName): int
    {
        // Look for patterns like "1x330ml", "6x250g", etc.
        if (preg_match('/(\d+)x\d+(?:\.\d+)?(?:ml|g|kg|l|litre)/', $productName, $matches)) {
            return (int) $matches[1];
        }

        // Default to 1 if no clear indication
        return 1;
    }

    /**
     * Format content string for Independent products
     */
    private function formatContentString(int $unitsPerCase): string
    {
        return $unitsPerCase === 1 ? '1 unit' : "{$unitsPerCase} units";
    }

    /**
     * Normalize calculated tax rate to standard Irish VAT rates
     */
    private function normalizeIrishVatRate(?float $calculatedRate): ?float
    {
        if (is_null($calculatedRate)) {
            return null;
        }

        // Irish VAT rates as of 2025
        $standardRates = [
            0.0,    // Zero rate (essential foods, books, etc.)
            4.8,    // Reduced rate (newspapers, magazines)
            9.0,    // Reduced rate (tourism, restaurants)
            13.5,   // Reduced rate (fuel, electricity, building materials)
            23.0,   // Standard rate (most goods and services)
        ];

        // Find the closest standard rate within a reasonable tolerance
        $tolerance = 1.0; // Allow 1.0% variation for rounding differences

        foreach ($standardRates as $standardRate) {
            if (abs($calculatedRate - $standardRate) <= $tolerance) {
                return $standardRate;
            }
        }

        // If no standard rate matches, return the calculated rate
        // This handles special cases like deposits or unusual items
        return $calculatedRate;
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
     * Find product and supplier link using outer case barcode
     */
    private function findProductByOuterCode(string $outerCode, int $supplierId): ?array
    {
        $supplierLink = SupplierLink::where('SupplierID', $supplierId)
            ->where('OuterCode', $outerCode)
            ->first();

        if ($supplierLink && $supplierLink->product) {
            return [
                'product' => $supplierLink->product,
                'supplier_link' => $supplierLink,
                'case_units' => $supplierLink->CaseUnits ?? 1,
            ];
        }

        return null;
    }

    /**
     * Find delivery item by barcode (individual unit) or outer code (case)
     */
    private function findDeliveryItemByBarcode(int $deliveryId, string $barcode): ?array
    {
        // First try to find by individual unit barcode
        $item = DeliveryItem::where('delivery_id', $deliveryId)
            ->where('barcode', $barcode)
            ->first();

        if ($item) {
            return [
                'item' => $item,
                'scan_type' => 'unit',
                'quantity_per_scan' => 1,
            ];
        }

        // Then try to find by case barcode (outer_code)
        $item = DeliveryItem::where('delivery_id', $deliveryId)
            ->where('outer_code', $barcode)
            ->first();

        if ($item) {
            return [
                'item' => $item,
                'scan_type' => 'case',
                'quantity_per_scan' => $item->getEffectiveCaseUnits(),
            ];
        }

        return null;
    }

    /**
     * Queue barcode retrieval for new products
     */
    private function queueBarcodeRetrieval(DeliveryItem $item, bool $isIndependentFormat = false): void
    {
        // Dispatch proper Laravel job with retry mechanism
        $jobClass = $isIndependentFormat ? 'RetrieveIndependentBarcodeJob' : 'RetrieveBarcodeJob';

        if ($isIndependentFormat && class_exists('\App\Jobs\RetrieveIndependentBarcodeJob')) {
            \App\Jobs\RetrieveIndependentBarcodeJob::dispatch($item)
                ->delay(now()->addSeconds(5))
                ->onQueue('barcode-retrieval');
        } else {
            RetrieveBarcodeJob::dispatch($item)
                ->delay(now()->addSeconds(5))
                ->onQueue('barcode-retrieval');
        }
    }

    /**
     * Process barcode scan during delivery - supports both unit and case barcodes
     */
    public function processScan(int $deliveryId, string $barcode, int $quantity = 1, ?string $scannedBy = null): array
    {
        $delivery = Delivery::findOrFail($deliveryId);

        // Use enhanced barcode finder that checks both unit and case barcodes
        $scanResult = $this->findDeliveryItemByBarcode($deliveryId, $barcode);

        if ($scanResult) {
            $item = $scanResult['item'];
            $scanType = $scanResult['scan_type'];
            $unitsPerScan = $scanResult['quantity_per_scan'];

            // Calculate total units being added
            $totalUnitsAdded = $quantity * $unitsPerScan;

            if ($scanType === 'case') {
                // Case barcode scanned - add to case quantity
                $item->addCaseScan($quantity, $scannedBy);
                $message = "Case scanned: {$item->description} (+{$quantity} cases = {$totalUnitsAdded} units)";
                $scanTypeMessage = 'case';
            } else {
                // Unit barcode scanned - add to unit quantity
                $item->addUnitScan($totalUnitsAdded, $scannedBy);
                $message = "Unit scanned: {$item->description} (+{$totalUnitsAdded} units)";
                $scanTypeMessage = 'unit';
            }

            // Record scan in delivery_scans table
            $scan = $delivery->scans()->create([
                'delivery_item_id' => $item->id,
                'barcode' => $barcode,
                'quantity' => $quantity,
                'matched' => true,
                'scanned_by' => $scannedBy ?? 'System',
                'metadata' => [
                    'scan_type' => $scanType,
                    'units_per_scan' => $unitsPerScan,
                    'total_units_added' => $totalUnitsAdded,
                    'case_units' => $item->getEffectiveCaseUnits(),
                ],
            ]);

            // Get updated quantities for display
            $totalReceived = $item->fresh()->total_received_units;
            $totalOrdered = $item->total_ordered_units;

            return [
                'success' => true,
                'item' => $item->fresh(),
                'scan_type' => $scanTypeMessage,
                'units_added' => $totalUnitsAdded,
                'message' => "{$message} (Total: {$totalReceived}/{$totalOrdered} units)",
            ];
        } else {
            // Record unmatched scan
            $scan = $delivery->scans()->create([
                'barcode' => $barcode,
                'quantity' => $quantity,
                'matched' => false,
                'scanned_by' => $scannedBy ?? 'System',
                'metadata' => [
                    'scan_type' => 'unknown',
                    'delivery_supplier_id' => $delivery->supplier_id,
                ],
            ]);

            return [
                'success' => false,
                'message' => "Unknown barcode: {$barcode} (not found in delivery items)",
                'barcode' => $barcode,
                'scan_type' => 'unknown',
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
    public function retrieveBarcode(string $supplierCode, ?int $supplierId = null): ?string
    {
        try {
            // Determine which service to use based on supplier
            $isIndependentSupplier = false;
            if ($supplierId) {
                $independentConfig = config('suppliers.external_links.independent');
                $isIndependentSupplier = $independentConfig && in_array($supplierId, $independentConfig['supplier_ids'] ?? []);
            }

            if ($isIndependentSupplier && $this->independentService) {
                $productData = $this->independentService->getProductData($supplierCode);
            } else {
                $productData = $this->udeaService->getProductData($supplierCode);
            }

            if ($productData && isset($productData['barcode'])) {
                return $productData['barcode'];
            }

            return null;

        } catch (\Exception $e) {
            Log::error('Failed to retrieve barcode for '.$supplierCode, [
                'error' => $e->getMessage(),
                'supplier_id' => $supplierId,
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

    /**
     * Store barrel deposit items for a delivery.
     *
     * Creates or updates barrel code references and stores line items
     * linking barrels to the delivery. Used for Udea deliveries that
     * include returnable items like crates, bottles, and pallets.
     *
     * @param  Delivery  $delivery  The delivery to attach barrels to
     * @param  array  $barrelItems  Array of barrel items from PDF parser
     * @param  int  $supplierId  Supplier ID for barrel code reference
     */
    public function storeBarrelItems(Delivery $delivery, array $barrelItems, int $supplierId): void
    {
        foreach ($barrelItems as $item) {
            // Find or create barrel code reference
            $barrelCode = \App\Models\BarrelCode::firstOrCreate(
                ['supplier_id' => $supplierId, 'supplier_code' => $item['code']],
                ['description' => $item['description'], 'unit_price' => $item['price']]
            );

            // Update price if changed
            if ((float) $barrelCode->unit_price !== (float) $item['price']) {
                $barrelCode->update(['unit_price' => $item['price']]);
            }

            // Store barrel line item
            \App\Models\DeliveryBarrel::create([
                'delivery_id' => $delivery->id,
                'barrel_code_id' => $barrelCode->id,
                'supplier_code' => $item['code'],
                'description' => $item['description'],
                'quantity' => $item['qty'],
                'unit_price' => $item['price'],
                'total' => $item['total'],
            ]);
        }

        Log::info('Stored barrel items for delivery', [
            'delivery_id' => $delivery->id,
            'barrel_count' => count($barrelItems),
            'total_value' => array_sum(array_column($barrelItems, 'total')),
        ]);
    }
}
