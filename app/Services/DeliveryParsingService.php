<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;

class DeliveryParsingService
{
    protected string $pythonPath;

    protected string $parserScript;

    protected string $venvPath;

    protected int $timeout;

    public function __construct()
    {
        $this->pythonPath = config('invoices.parsing.python_executable', '/usr/bin/python3');
        $this->parserScript = base_path('scripts/invoice-parser/delivery_parser_laravel.py');
        $this->venvPath = config('invoices.parsing.python_venv_path', base_path('scripts/invoice-parser/venv'));
        $this->timeout = config('invoices.parsing.max_parse_time', 120);
    }

    /**
     * Parse a delivery PDF file and return structured product data.
     *
     * @param  string  $pdfPath  Path to the PDF file
     * @param  string|null  $supplierHint  Optional supplier name to help detection
     * @return array Parsed delivery data
     *
     * @throws \Exception
     */
    public function parseDeliveryPdf(string $pdfPath, ?string $supplierHint = null): array
    {
        if (! file_exists($pdfPath)) {
            throw new \Exception("PDF file not found: {$pdfPath}");
        }

        // Build the command to execute Python with virtual environment
        $venvPython = $this->venvPath.'/bin/python';

        // Use venv Python if it exists, otherwise fall back to system Python
        $pythonExecutable = file_exists($venvPython) ? $venvPython : $this->pythonPath;

        // Build command array
        $command = [
            $pythonExecutable,
            $this->parserScript,
            '--file', $pdfPath,
            '--output', 'json',
        ];

        // Add supplier hint if provided
        if ($supplierHint) {
            $command[] = '--supplier';
            $command[] = $supplierHint;
        }

        Log::info('Executing delivery parser command', [
            'command' => implode(' ', $command),
            'file_path' => $pdfPath,
            'supplier_hint' => $supplierHint,
        ]);

        // Execute the Python parser
        $result = Process::timeout($this->timeout)->run($command);

        // Log the output for debugging
        Log::debug('Delivery parser output', [
            'stdout' => $result->output(),
            'stderr' => $result->errorOutput(),
            'exit_code' => $result->exitCode(),
        ]);

        // Check if the command was successful
        if (! $result->successful()) {
            $errorMsg = 'Delivery parser execution failed: '.$result->errorOutput();
            Log::error($errorMsg, [
                'exit_code' => $result->exitCode(),
                'stderr' => $result->errorOutput(),
            ]);

            throw new \Exception($errorMsg);
        }

        // Parse the JSON output
        $output = json_decode($result->output(), true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $errorMsg = 'Invalid JSON from delivery parser: '.json_last_error_msg();
            Log::error($errorMsg, [
                'raw_output' => $result->output(),
            ]);

            throw new \Exception($errorMsg);
        }

        return $output;
    }

    /**
     * Parse multiple delivery PDF files and return merged product data.
     *
     * @param  array  $pdfPaths  Array of paths to PDF files
     * @param  string|null  $supplierHint  Optional supplier name to help detection
     * @return array Merged parsed delivery data with per-file results
     */
    public function parseMultipleDeliveryPdfs(array $pdfPaths, ?string $supplierHint = null): array
    {
        $allItems = [];
        $totalValue = 0.0;
        $allWarnings = [];
        $allErrors = [];
        $allUnmatchedLines = [];
        $fileResults = [];
        $totalValidations = 0;
        $passedValidations = 0;
        $detectedSupplier = null;
        $allBarrelItems = [];
        $totalBarrelsValue = 0.0;

        foreach ($pdfPaths as $pdfPath) {
            $filename = basename($pdfPath);

            try {
                $result = $this->parseDeliveryPdf($pdfPath, $supplierHint);

                $itemCount = count($result['data']['items'] ?? []);
                $barrelsTotal = $result['data']['totals']['barrels_total'] ?? 0;
                $productsTotal = $result['data']['totals']['products_total'] ?? $result['data']['totals']['total_value'] ?? 0;

                // Extract totals verification data from parser
                $totals = $result['data']['totals'] ?? [];
                $totalsMatch = $totals['totals_match'] ?? true;
                $discrepancy = $totals['discrepancy'] ?? null;

                $fileResults[] = [
                    'filename' => $filename,
                    'success' => $result['success'],
                    'item_count' => $itemCount,
                    'products_total' => $productsTotal,
                    'barrels_total' => $barrelsTotal,
                    'total_value' => $productsTotal + $barrelsTotal,
                    // Stated totals from PDF
                    'products_stated' => $totals['products_stated'] ?? null,
                    'barrels_stated' => $totals['barrels_stated'] ?? null,
                    'grand_stated' => $totals['grand_stated'] ?? null,
                    // Calculated totals (sum of parsed items)
                    'products_calculated' => $totals['products_calculated'] ?? $productsTotal,
                    'barrels_calculated' => $totals['barrels_calculated'] ?? $barrelsTotal,
                    'grand_calculated' => $totals['grand_calculated'] ?? ($productsTotal + $barrelsTotal),
                    // Verification
                    'totals_match' => $totalsMatch,
                    'discrepancy' => $discrepancy,
                ];

                if ($result['success']) {
                    // Track supplier from first successful parse
                    if (! $detectedSupplier) {
                        $detectedSupplier = $result['data']['supplier'] ?? $result['metadata']['supplier_detected'] ?? null;
                    }

                    // Merge items
                    $allItems = array_merge($allItems, $result['data']['items']);
                    $totalValue += $productsTotal;

                    // Merge barrel items (Udea-specific: crates, bottles, pallets)
                    $barrels = $result['data']['barrels'] ?? ['items' => [], 'total' => 0];
                    foreach ($barrels['items'] as $barrelItem) {
                        $barrelItem['filename'] = $filename;
                        $allBarrelItems[] = $barrelItem;
                    }
                    $totalBarrelsValue += $barrels['total'];

                    // Track validation stats
                    $stats = $result['metadata']['stats'] ?? [];
                    $totalValidations += $stats['parsed_lines'] ?? $itemCount;
                    $passedValidations += $stats['price_validations_passed'] ?? $itemCount;

                    // Merge warnings with file context
                    foreach ($result['warnings'] ?? [] as $warning) {
                        $allWarnings[] = "[{$filename}] {$warning}";
                    }

                    // Collect unmatched lines with file context
                    $unmatchedLines = $result['metadata']['unmatched_lines'] ?? $result['data']['metadata']['unmatched_lines'] ?? [];
                    foreach ($unmatchedLines as $line) {
                        $allUnmatchedLines[] = [
                            'filename' => $filename,
                            'line_num' => $line['line_num'],
                            'content' => $line['content'],
                        ];
                    }
                } else {
                    // Add errors with file context
                    foreach ($result['errors'] ?? [] as $error) {
                        $errorMsg = is_array($error) ? ($error['message'] ?? json_encode($error)) : $error;
                        $allErrors[] = "[{$filename}] {$errorMsg}";
                    }
                }
            } catch (\Exception $e) {
                Log::error("Failed to parse PDF: {$filename}", [
                    'error' => $e->getMessage(),
                ]);

                $fileResults[] = [
                    'filename' => $filename,
                    'success' => false,
                    'item_count' => 0,
                    'total_value' => 0,
                    'error' => $e->getMessage(),
                ];

                $allErrors[] = "[{$filename}] {$e->getMessage()}";
            }
        }

        // Calculate overall confidence
        $confidence = $totalValidations > 0
            ? round(($passedValidations / $totalValidations) * 100, 1)
            : 0.0;

        // Aggregate stated totals from all files
        $totalStatedProducts = 0.0;
        $totalStatedBarrels = 0.0;
        $totalStatedGrand = 0.0;
        $hasStatedTotals = false;
        $allTotalsMatch = true;
        $totalDiscrepancy = 0.0;

        foreach ($fileResults as $fileResult) {
            if (isset($fileResult['products_stated']) && $fileResult['products_stated'] !== null) {
                $totalStatedProducts += $fileResult['products_stated'];
                $hasStatedTotals = true;
            }
            if (isset($fileResult['barrels_stated']) && $fileResult['barrels_stated'] !== null) {
                $totalStatedBarrels += $fileResult['barrels_stated'];
            }
            if (isset($fileResult['grand_stated']) && $fileResult['grand_stated'] !== null) {
                $totalStatedGrand += $fileResult['grand_stated'];
                $hasStatedTotals = true;
            }
            if (isset($fileResult['totals_match']) && ! $fileResult['totals_match']) {
                $allTotalsMatch = false;
            }
            if (isset($fileResult['discrepancy']) && $fileResult['discrepancy'] !== null) {
                $totalDiscrepancy += $fileResult['discrepancy'];
            }
        }

        return [
            'success' => ! empty($allItems),
            'data' => [
                'supplier' => $detectedSupplier ?? $supplierHint ?? 'Multiple',
                'items' => $allItems,
                'barrels' => [
                    'items' => $allBarrelItems,
                    'total' => round($totalBarrelsValue, 2),
                ],
                'totals' => [
                    'line_count' => count($allItems),
                    'products_total' => round($totalValue, 2),
                    'barrels_total' => round($totalBarrelsValue, 2),
                    'total_value' => round($totalValue + $totalBarrelsValue, 2),
                    // Stated totals from PDF (aggregated)
                    'products_stated' => $hasStatedTotals ? round($totalStatedProducts, 2) : null,
                    'barrels_stated' => $totalStatedBarrels > 0 ? round($totalStatedBarrels, 2) : null,
                    'grand_stated' => $hasStatedTotals ? round($totalStatedGrand, 2) : null,
                    // Calculated totals
                    'products_calculated' => round($totalValue, 2),
                    'barrels_calculated' => round($totalBarrelsValue, 2),
                    'grand_calculated' => round($totalValue + $totalBarrelsValue, 2),
                    // Verification
                    'totals_match' => $allTotalsMatch,
                    'discrepancy' => round($totalDiscrepancy, 2),
                ],
            ],
            'file_results' => $fileResults,
            'confidence' => $confidence,
            'warnings' => $allWarnings,
            'errors' => $allErrors,
            'metadata' => [
                'supplier_detected' => $detectedSupplier,
                'files_processed' => count($pdfPaths),
                'files_successful' => count(array_filter($fileResults, fn ($f) => $f['success'])),
                'unmatched_lines' => $allUnmatchedLines,
            ],
        ];
    }

    /**
     * Convert parsed PDF data to CSV-compatible format for DeliveryService.
     *
     * This transforms the JSON output from the PDF parser into the enhanced
     * format that DeliveryService expects. Handles both Independent and UDEA formats.
     *
     * @param  array  $parsedData  Output from parseDeliveryPdf() or parseMultipleDeliveryPdfs()
     * @return array Array of rows compatible with delivery import
     */
    public function convertToDeliveryItems(array $parsedData): array
    {
        if (! $parsedData['success'] || empty($parsedData['data']['items'])) {
            return [];
        }

        $items = [];

        foreach ($parsedData['data']['items'] as $item) {
            // Handle both Independent format (with cases/units) and UDEA format (totals only)
            $hasDetailedQty = isset($item['ordered_cases']) && isset($item['delivered_cases']);

            $items[] = [
                'Code' => $item['code'],
                'Product' => $item['product'],
                'Ordered' => $hasDetailedQty
                    ? $this->formatQuantityString($item['ordered_cases'], $item['ordered_units'])
                    : (string) ($item['total_ordered_units'] ?? 0),
                'Qty' => $hasDetailedQty
                    ? $this->formatQuantityString($item['delivered_cases'], $item['delivered_units'])
                    : (string) ($item['total_delivered_units'] ?? 0),
                'Case_Size' => $item['case_size'] ?? 1,
                'Total_Ordered_Units' => $item['total_ordered_units'] ?? 0,
                'Total_Delivered_Units' => $item['total_delivered_units'] ?? 0,
                'RSP' => $item['rsp'] ?? 0,
                'Price' => $item['case_price'] ?? $item['unit_cost'] ?? 0,
                'Unit_Cost' => $item['unit_cost'] ?? 0,
                'Tax' => $item['tax'] ?? 0,
                'Value' => $item['line_total'] ?? 0,
                'Price_Valid' => ($item['price_valid'] ?? true) ? '✓' : '✗',
                'is_weight_based' => $item['is_weight_based'] ?? false,
                'weight_per_unit' => $item['weight_per_unit'] ?? null,
                'weight_unit' => $item['weight_unit'] ?? null,
                'total_weight' => $item['total_weight'] ?? null,
            ];
        }

        return $items;
    }

    /**
     * Format quantity as cases/units string.
     */
    private function formatQuantityString(int $cases, int $units): string
    {
        return "{$cases}/{$units}";
    }

    /**
     * Get supplier type from parsed data.
     */
    public function getSupplierType(array $parsedData): string
    {
        return $parsedData['data']['supplier'] ?? $parsedData['metadata']['supplier_detected'] ?? 'Unknown';
    }

    /**
     * Get confidence score from parsed data.
     */
    public function getConfidence(array $parsedData): float
    {
        return $parsedData['confidence'] ?? 0.0;
    }

    /**
     * Check if parsing was successful.
     */
    public function wasSuccessful(array $parsedData): bool
    {
        return $parsedData['success'] ?? false;
    }

    /**
     * Get errors from parsed data.
     */
    public function getErrors(array $parsedData): array
    {
        return $parsedData['errors'] ?? [];
    }

    /**
     * Get warnings from parsed data.
     */
    public function getWarnings(array $parsedData): array
    {
        return $parsedData['warnings'] ?? [];
    }

    /**
     * Get totals from parsed data.
     */
    public function getTotals(array $parsedData): array
    {
        return $parsedData['data']['totals'] ?? [
            'line_count' => 0,
            'total_value' => 0.0,
        ];
    }

    /**
     * Check if the Python parser is properly configured.
     */
    public function checkConfiguration(): array
    {
        $checks = [
            'python_exists' => false,
            'parser_script_exists' => false,
            'venv_exists' => false,
            'errors' => [],
        ];

        // Check Python executable
        $pythonCheck = Process::run([$this->pythonPath, '--version']);
        if ($pythonCheck->successful()) {
            $checks['python_exists'] = true;
            $checks['python_version'] = trim($pythonCheck->output());
        } else {
            $checks['errors'][] = 'Python not found at: '.$this->pythonPath;
        }

        // Check parser script
        if (file_exists($this->parserScript)) {
            $checks['parser_script_exists'] = true;
        } else {
            $checks['errors'][] = 'Delivery parser script not found at: '.$this->parserScript;
        }

        // Check virtual environment
        $venvPython = $this->venvPath.'/bin/python';
        if (file_exists($venvPython)) {
            $checks['venv_exists'] = true;
        } else {
            $checks['errors'][] = 'Virtual environment not found at: '.$this->venvPath;
        }

        $checks['all_checks_passed'] = empty($checks['errors']);

        return $checks;
    }

    /**
     * Detect supplier from PDF file.
     *
     * @return string|null Supplier name or null if unknown
     */
    public function detectSupplier(string $pdfPath): ?string
    {
        try {
            $result = $this->parseDeliveryPdf($pdfPath);

            return $result['metadata']['supplier_detected'] ?? null;
        } catch (\Exception $e) {
            Log::warning('Failed to detect supplier from PDF', [
                'path' => $pdfPath,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }
}
