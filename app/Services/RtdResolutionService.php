<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\InvoiceUploadFile;
use App\Models\Product;
use App\Models\RtdVatFallback;
use App\Models\SupplierLink;

class RtdResolutionService
{
    // Valid Irish VAT rates (exact match required, in percentage)
    public const VALID_VAT_RATES = [0.0, 9.0, 13.5, 23.0];

    /**
     * Parse a monetary string to float, handling European format.
     * Strips currency symbols, spaces, and non-numeric chars.
     * "€32,70" -> 32.70, "1.234,56" -> 1234.56, "32.70" -> 32.70
     */
    public function parseMonetaryValue($value): float
    {
        if (is_numeric($value)) {
            return (float) $value;
        }

        if (! is_string($value) || trim($value) === '') {
            return 0.0;
        }

        $value = trim($value);

        // Strip currency symbols, spaces, and non-numeric chars (keep digits, comma, dot, minus)
        $value = preg_replace('/[^\d,.\-]/', '', $value);

        if ($value === '' || $value === '-') {
            return 0.0;
        }

        // Determine format: European (comma decimal) or US (dot decimal)
        // European format: dots are thousands, comma is decimal
        // US format: commas are thousands, dot is decimal
        if (str_contains($value, ',')) {
            $lastComma = strrpos($value, ',');
            $lastDot = strrpos($value, '.');

            if ($lastDot !== false && $lastDot > $lastComma) {
                // US format with comma as thousands: "1,234.56"
                $value = str_replace(',', '', $value);
            } else {
                // European format: "1.234,56" or "32,70"
                $value = str_replace('.', '', $value);
                $value = str_replace(',', '.', $value);
            }
        }

        return (float) $value;
    }

    /**
     * Convert VAT rate to percentage, handling both fraction and percent formats.
     * 0.23 -> 23.0, 23 -> 23.0, 0.135 -> 13.5
     */
    public function normalizeVatRateToPercent(?float $rate): ?float
    {
        if ($rate === null) {
            return null;
        }

        // If rate is <= 1, treat as fraction and multiply by 100
        // If rate > 1, assume it's already in percentage
        if ($rate <= 1 && $rate >= 0) {
            return round($rate * 100, 1);
        }

        return round($rate, 1);
    }

    /**
     * Get the appropriate upload file for RTD computation.
     * Selects Udea, Dynamis, or Independent invoice files with parsed line data.
     */
    public function getSourceUploadFile(Invoice $invoice): ?InvoiceUploadFile
    {
        return $invoice->uploadFiles()
            ->where(function ($q) {
                $q->where('supplier_detected', 'Udea')
                    ->orWhere('supplier_detected', 'Dynamis')
                    ->orWhere('supplier_detected', 'Independent');
            })
            ->whereNotNull('parsed_data')
            ->where('status', 'completed')
            // Prefer files that have invoice-type parsed data (lines array or vat_summary for IIH)
            ->where(function ($q) {
                $q->whereRaw("JSON_EXTRACT(parsed_data, '$.lines') IS NOT NULL")
                    ->orWhereRaw("JSON_EXTRACT(parsed_data, '$.vat_summary') IS NOT NULL");
            })
            // Prefer files parsed by invoice parser (check for invoice header data)
            ->whereRaw("JSON_EXTRACT(parsed_data, '$.header.invoice_number') IS NOT NULL")
            ->orderByDesc('parsed_at')
            ->first();
    }

    /**
     * Compute RTD breakdown from parsed invoice lines.
     * Routes to appropriate method based on supplier RTD classification.
     */
    public function computeRtd(Invoice $invoice): array
    {
        // Ensure supplier relationship is loaded for classification check
        $invoice->loadMissing('supplier');

        // Check supplier RTD classification
        $supplier = $invoice->supplier;
        if ($supplier && $supplier->rtd_classification) {
            // Route based on supplier classification
            switch ($supplier->rtd_classification) {
                case 'goods_simple':
                    return $this->computeRtdFromInvoiceVat($invoice);

                case 'service_overhead':
                    return $this->computeRtdForServiceSupplier($invoice);

                case 'goods_parser':
                    // Fall through to parser-based computation
                    break;

                case 'not_applicable':
                default:
                    // Return empty breakdown for non-tracked suppliers
                    return [
                        'breakdown' => [
                            'goods_for_resale' => ['0' => 0, '9' => 0, '13.5' => 0, '23' => 0],
                            'excluded' => ['freight' => 0, 'deposits' => 0, 'drs' => 0],
                            'unresolved' => ['count' => 0, 'net_total' => 0],
                            'stats' => ['total_lines' => 0, 'resolved_lines' => 0, 'excluded_lines' => 0, 'method' => 'not_applicable'],
                        ],
                        'issues' => [],
                        'source_file_id' => null,
                    ];
            }
        }

        // Default: parser-based computation for legacy suppliers (Udea, Dynamis, IIH)
        return $this->computeRtdWithParser($invoice);
    }

    /**
     * Compute RTD breakdown using parser-based line item resolution.
     * Used for suppliers with dedicated parsers (Udea, Dynamis, IIH).
     */
    protected function computeRtdWithParser(Invoice $invoice): array
    {
        // Initialize breakdown structure
        $breakdown = [
            'goods_for_resale' => ['0' => 0, '9' => 0, '13.5' => 0, '23' => 0],
            'excluded' => ['freight' => 0, 'deposits' => 0, 'drs' => 0, 'vat' => 0, 'service_overhead' => 0],
            'unresolved' => ['count' => 0, 'net_total' => 0],
            'stats' => ['total_lines' => 0, 'resolved_lines' => 0, 'excluded_lines' => 0],
        ];
        $issues = [];
        $sourceFileId = null;

        // Get parsed data from appropriate upload file
        $uploadFile = $this->getSourceUploadFile($invoice);
        if (! $uploadFile || ! $uploadFile->parsed_data) {
            return ['breakdown' => $breakdown, 'issues' => $issues, 'source_file_id' => null];
        }

        $sourceFileId = $uploadFile->id;
        $parsedData = $uploadFile->parsed_data;

        // Check if this is an Independent (IIH) invoice - uses VAT summary approach
        if ($uploadFile->supplier_detected === 'Independent') {
            return $this->computeRtdFromVatSummary($invoice, $parsedData, $sourceFileId);
        }

        $lines = $parsedData['lines'] ?? [];
        $barrels = $parsedData['barrels'] ?? ['total' => 0];
        $costs = $parsedData['costs'] ?? ['total' => 0];

        // Add excluded totals (parse safely)
        $breakdown['excluded']['freight'] = round($this->parseMonetaryValue($costs['total'] ?? 0), 2);
        $breakdown['excluded']['deposits'] = round($this->parseMonetaryValue($barrels['total'] ?? 0), 2);
        $breakdown['stats']['total_lines'] = count($lines);

        // Process product lines
        foreach ($lines as $line) {
            $lineType = $line['line_type'] ?? 'unknown';
            $articleCode = $line['article_code'] ?? null;
            $lineTotal = $this->parseMonetaryValue($line['line_total'] ?? 0);
            $description = $line['description'] ?? '';

            // Skip only known non-product line types (barrels, costs, deposits)
            // Attempt resolution for both 'product_for_resale' AND 'unknown' lines
            // Unknown lines may still have valid article codes that can be resolved
            if (! in_array($lineType, ['product_for_resale', 'unknown'])) {
                $breakdown['stats']['excluded_lines']++;

                continue;
            }

            // Resolve article code to product and VAT rate
            $resolution = $this->resolveArticleCode($articleCode, $invoice->supplier_id);

            if ($resolution['status'] === 'no_product_match') {
                // No SupplierLink or no Product
                $issues[] = [
                    'article_code' => $articleCode,
                    'line_total' => round($lineTotal, 2),
                    'description' => substr($description, 0, 50),
                    'reason' => 'no_product_match',
                ];
                $breakdown['unresolved']['count']++;
                $breakdown['unresolved']['net_total'] += $lineTotal;

                continue;
            }

            if ($resolution['status'] === 'no_tax_category') {
                // Product found but no VAT rate
                $issues[] = [
                    'article_code' => $articleCode,
                    'line_total' => round($lineTotal, 2),
                    'description' => substr($description, 0, 50),
                    'reason' => 'no_tax_category',
                    'product_code' => $resolution['product_code'],
                ];
                $breakdown['unresolved']['count']++;
                $breakdown['unresolved']['net_total'] += $lineTotal;

                continue;
            }

            $vatRate = $resolution['vat_rate'];

            // Check if VAT rate is valid Irish rate
            if (! $this->isValidIrishVatRate($vatRate)) {
                $issues[] = [
                    'article_code' => $articleCode,
                    'line_total' => round($lineTotal, 2),
                    'description' => substr($description, 0, 50),
                    'reason' => 'invalid_vat_rate',
                    'vat_rate_found' => $vatRate,
                    'product_code' => $resolution['product_code'],
                ];
                $breakdown['unresolved']['count']++;
                $breakdown['unresolved']['net_total'] += $lineTotal;

                continue;
            }

            // Non-retail fallback items go to excluded.service_overhead instead of goods_for_resale
            if (! empty($resolution['is_non_retail'])) {
                $breakdown['excluded']['service_overhead'] += $lineTotal;
                $breakdown['stats']['resolved_lines']++;
            } else {
                // Add to correct VAT bucket
                $bucketKey = $this->vatRateToBucketKey($vatRate);
                $breakdown['goods_for_resale'][$bucketKey] += $lineTotal;
                $breakdown['stats']['resolved_lines']++;
            }
        }

        // Round all values
        foreach ($breakdown['goods_for_resale'] as $key => $value) {
            $breakdown['goods_for_resale'][$key] = round($value, 2);
        }
        $breakdown['excluded']['service_overhead'] = round($breakdown['excluded']['service_overhead'], 2);
        $breakdown['unresolved']['net_total'] = round($breakdown['unresolved']['net_total'], 2);

        // Track VAT amount as excluded (not part of goods for resale)
        $breakdown['excluded']['vat'] = round((float) ($invoice->vat_amount ?? 0), 2);

        // RTD integrity check: verify totals reconcile
        $validation = $parsedData['validation'] ?? [];
        if (isset($validation['products_total'])) {
            $expectedProductsTotal = $this->parseMonetaryValue($validation['products_total']);
            $resolvedTotal = array_sum($breakdown['goods_for_resale']);
            $nonRetailTotal = $breakdown['excluded']['service_overhead'];
            $unresolvedTotal = $breakdown['unresolved']['net_total'];
            $calculatedTotal = $resolvedTotal + $nonRetailTotal + $unresolvedTotal;
            $difference = abs($calculatedTotal - $expectedProductsTotal);

            $breakdown['integrity'] = [
                'expected_products_total' => round($expectedProductsTotal, 2),
                'calculated_total' => round($calculatedTotal, 2),
                'difference' => round($difference, 2),
                'reconciled' => $difference < 1.00, // Allow €1 tolerance for rounding
            ];
        }

        return [
            'breakdown' => $breakdown,
            'issues' => $issues,
            'source_file_id' => $sourceFileId,
        ];
    }

    /**
     * Compute RTD from VAT summary (for Independent/IIH invoices).
     * IIH invoices already have VAT categorization, so we use it directly.
     * DRS amounts are excluded from 0% goods.
     *
     * Supports two data formats:
     * 1. New format: vat_summary with keys '0', '9', '13.5', '23'
     * 2. Legacy format: vat_breakdown with keys 'vat_0', 'vat_9', 'vat_13_5', 'vat_23'
     */
    private function computeRtdFromVatSummary(Invoice $invoice, array $parsedData, int $sourceFileId): array
    {
        $breakdown = [
            'goods_for_resale' => ['0' => 0, '9' => 0, '13.5' => 0, '23' => 0],
            'excluded' => ['freight' => 0, 'deposits' => 0, 'drs' => 0],
            'unresolved' => ['count' => 0, 'net_total' => 0],
            'stats' => ['total_lines' => 0, 'resolved_lines' => 0, 'excluded_lines' => 0],
        ];
        $issues = [];

        // Extract VAT data - support both formats
        $vatSummary = [];
        if (isset($parsedData['vat_summary'])) {
            // New format from invoice_iih_rtd.py
            $vatSummary = $parsedData['vat_summary'];
        } elseif (isset($parsedData['vat_breakdown'])) {
            // Legacy format from bulk upload parser
            $vb = $parsedData['vat_breakdown'];
            $vatSummary['0'] = $this->parseMonetaryValue($vb['vat_0']['net'] ?? 0);
            $vatSummary['9'] = $this->parseMonetaryValue($vb['vat_9']['net'] ?? 0);
            $vatSummary['13.5'] = $this->parseMonetaryValue($vb['vat_13_5']['net'] ?? 0);
            $vatSummary['23'] = $this->parseMonetaryValue($vb['vat_23']['net'] ?? 0);
        } elseif (isset($parsedData['lines'])) {
            // Lines with vat_rate field - aggregate by rate
            foreach ($parsedData['lines'] as $line) {
                if (isset($line['vat_rate']) && isset($line['line_total'])) {
                    $rate = (string) $line['vat_rate'];
                    // Normalize rate key
                    if ($rate === '0' || $rate === '0.0') {
                        $rate = '0';
                    } elseif ($rate === '13.5' || abs((float) $rate - 13.5) < 0.1) {
                        $rate = '13.5';
                    } elseif ($rate === '23' || abs((float) $rate - 23) < 0.1) {
                        $rate = '23';
                    } elseif ($rate === '9' || abs((float) $rate - 9) < 0.1) {
                        $rate = '9';
                    }
                    $vatSummary[$rate] = ($vatSummary[$rate] ?? 0) + $this->parseMonetaryValue($line['line_total']);
                }
            }
        }

        $drs = $parsedData['drs'] ?? ['total' => 0];

        // Get DRS total (to be excluded from 0% goods)
        $drsTotal = round($this->parseMonetaryValue($drs['total'] ?? 0), 2);
        $breakdown['excluded']['drs'] = $drsTotal;

        // Map VAT summary to goods for resale
        foreach (['0', '9', '13.5', '23'] as $rate) {
            $amount = $this->parseMonetaryValue($vatSummary[$rate] ?? 0);

            // For 0% rate, subtract DRS (deposit return scheme amounts)
            if ($rate === '0' && $drsTotal > 0) {
                $amount -= $drsTotal;
            }

            $breakdown['goods_for_resale'][$rate] = round($amount, 2);
            if ($amount > 0) {
                $breakdown['stats']['resolved_lines']++;
            }
        }

        // Count total "lines" from VAT summary categories
        $breakdown['stats']['total_lines'] = count(array_filter($vatSummary, fn ($v) => $v > 0));

        // Validation: check if totals reconcile
        $header = $parsedData['header'] ?? [];

        // Extract VAT amount from header (IIH invoices have tax_amount)
        $vatAmount = round($this->parseMonetaryValue($header['tax_amount'] ?? 0), 2);
        $breakdown['excluded']['vat'] = $vatAmount;

        // For IIH: Goods (net) + DRS + VAT = Invoice Total (gross)
        $expectedGrossTotal = $this->parseMonetaryValue($header['gross_total'] ?? $invoice->total_amount ?? 0);
        $calculatedTotal = array_sum($breakdown['goods_for_resale']) + $drsTotal + $vatAmount;
        $difference = abs($calculatedTotal - $expectedGrossTotal);

        $breakdown['integrity'] = [
            'expected_products_total' => round($expectedGrossTotal, 2),
            'calculated_total' => round($calculatedTotal, 2),
            'difference' => round($difference, 2),
            'drs_excluded' => $drsTotal,
            'vat_amount' => $vatAmount,
            'reconciled' => $difference < 1.00,
        ];

        return [
            'breakdown' => $breakdown,
            'issues' => $issues,
            'source_file_id' => $sourceFileId,
        ];
    }

    /**
     * Compute RTD from invoice VAT breakdown fields.
     * Used for suppliers classified as 'goods_simple' - no parser, just uses invoice VAT fields.
     * This is T1 (goods for resale).
     */
    public function computeRtdFromInvoiceVat(Invoice $invoice): array
    {
        $breakdown = [
            'goods_for_resale' => [
                '0' => round((float) ($invoice->zero_net ?? 0), 2),
                '9' => round((float) ($invoice->second_reduced_net ?? 0), 2),
                '13.5' => round((float) ($invoice->reduced_net ?? 0), 2),
                '23' => round((float) ($invoice->standard_net ?? 0), 2),
            ],
            'excluded' => [
                'freight' => 0,
                'deposits' => 0,
                'drs' => 0,
                'vat' => round((float) ($invoice->vat_amount ?? 0), 2),
            ],
            'unresolved' => ['count' => 0, 'net_total' => 0],
            'stats' => [
                'total_lines' => 0,
                'resolved_lines' => 4, // 4 VAT categories
                'excluded_lines' => 0,
                'method' => 'simple_vat',
            ],
        ];

        // Verify totals reconcile
        $goodsTotal = array_sum($breakdown['goods_for_resale']);
        $vatAmount = $breakdown['excluded']['vat'];
        $calculatedTotal = $goodsTotal + $vatAmount;
        $invoiceTotal = (float) ($invoice->total_amount ?? 0);
        $difference = abs($calculatedTotal - $invoiceTotal);

        $breakdown['integrity'] = [
            'expected_products_total' => round($invoiceTotal, 2),
            'calculated_total' => round($calculatedTotal, 2),
            'difference' => round($difference, 2),
            'reconciled' => $difference < 1.00,
        ];

        return [
            'breakdown' => $breakdown,
            'issues' => [],
            'source_file_id' => null,
        ];
    }

    /**
     * Compute RTD for service/overhead suppliers (T2).
     * Uses invoice VAT breakdown, tracked as overhead, not goods for resale.
     */
    public function computeRtdForServiceSupplier(Invoice $invoice): array
    {
        // Calculate service total from invoice VAT breakdown fields
        $serviceByVat = [
            '0' => round((float) ($invoice->zero_net ?? 0), 2),
            '9' => round((float) ($invoice->second_reduced_net ?? 0), 2),
            '13.5' => round((float) ($invoice->reduced_net ?? 0), 2),
            '23' => round((float) ($invoice->standard_net ?? 0), 2),
        ];
        $serviceTotal = array_sum($serviceByVat);
        $vatAmount = round((float) ($invoice->vat_amount ?? 0), 2);
        if ($vatAmount == 0) {
            // VAT is missing - calculate from net amounts and VAT rates
            $vatAmount = round(
                ($serviceByVat['0'] * 0.00) +
                ($serviceByVat['9'] * 0.09) +
                ($serviceByVat['13.5'] * 0.135) +
                ($serviceByVat['23'] * 0.23),
                2
            );
        }
        $invoiceTotal = (float) ($invoice->total_amount ?? 0);

        $breakdown = [
            // T1 goods for resale: always zero for service suppliers
            'goods_for_resale' => ['0' => 0, '9' => 0, '13.5' => 0, '23' => 0],
            // All service amounts go into excluded (not contributing to T1)
            'excluded' => [
                'freight' => 0,
                'deposits' => 0,
                'drs' => 0,
                'vat' => $vatAmount,
                'service_overhead' => round($serviceTotal, 2),
            ],
            'unresolved' => ['count' => 0, 'net_total' => 0],
            'stats' => [
                'total_lines' => 0,
                'resolved_lines' => 0,
                'excluded_lines' => 1,
                'method' => 'service_overhead',
                'is_service' => true,
                // Keep VAT breakdown for display purposes
                'service_by_vat' => $serviceByVat,
            ],
        ];

        // Reconciliation: excluded total (service + VAT) should match invoice total
        $excludedTotal = $serviceTotal + $vatAmount;
        $difference = abs($excludedTotal - $invoiceTotal);

        $breakdown['integrity'] = [
            'expected_products_total' => round($invoiceTotal, 2),
            'calculated_total' => round($excludedTotal, 2),
            'difference' => round($difference, 2),
            'reconciled' => $difference < 1.00,
        ];

        return [
            'breakdown' => $breakdown,
            'issues' => [],
            'source_file_id' => null,
        ];
    }

    /**
     * Resolve article code to product and VAT rate.
     * Resolution priority:
     * 1. EAN barcode direct lookup (for Dynamis grocery invoices with 13-digit EAN)
     * 2. SupplierLink lookup (for Udea and other suppliers)
     * 3. RtdVatFallback table (manual assignments)
     *
     * @param  int|null  $supplierId  The accounting supplier ID for fallback lookup
     * @return array{status: string, source?: string, product_code?: string, product_name?: string, vat_rate?: float}
     */
    public function resolveArticleCode(?string $articleCode, ?int $supplierId = null): array
    {
        if (! $articleCode) {
            return ['status' => 'no_product_match', 'source' => null];
        }

        // Check if article_code is an EAN barcode (13 digits) - Dynamis grocery invoices
        if (strlen($articleCode) === 13 && ctype_digit($articleCode)) {
            // Direct product barcode lookup
            $product = Product::where('CODE', $articleCode)->first();

            if ($product) {
                $vatRate = $product->getVatRate();

                if ($vatRate !== null) {
                    $vatRatePercent = $this->normalizeVatRateToPercent($vatRate);

                    return [
                        'status' => 'resolved',
                        'product_code' => $product->CODE,
                        'product_name' => $product->NAME,
                        'vat_rate' => $vatRatePercent,
                        'source' => 'product_barcode',
                    ];
                }

                return [
                    'status' => 'no_tax_category',
                    'product_code' => $product->CODE,
                    'product_name' => $product->NAME,
                    'source' => 'product_barcode',
                ];
            }
            // EAN not found in products - continue to fallback resolution
        }

        // Try SupplierLink (primary resolution for Udea)
        $link = SupplierLink::where('SupplierCode', $articleCode)->first();

        if ($link) {
            // Get product via barcode
            $product = Product::where('CODE', $link->Barcode)->first();

            if ($product) {
                // Get VAT rate from product
                $vatRate = $product->getVatRate();

                if ($vatRate !== null) {
                    // Normalize VAT rate to percentage (handles both 0.23 and 23 formats)
                    $vatRatePercent = $this->normalizeVatRateToPercent($vatRate);

                    return [
                        'status' => 'resolved',
                        'product_code' => $product->CODE,
                        'product_name' => $product->NAME,
                        'vat_rate' => $vatRatePercent,
                        'source' => 'supplier_link',
                    ];
                }

                // Product found but no VAT rate assigned
                return [
                    'status' => 'no_tax_category',
                    'product_code' => $product->CODE,
                    'product_name' => $product->NAME,
                    'source' => 'supplier_link',
                ];
            }
        }

        // Try fallback table for manual VAT rate assignments (requires supplier_id)
        if ($supplierId !== null) {
            $fallbackData = RtdVatFallback::findFallback($articleCode, $supplierId);
            if ($fallbackData !== null) {
                return [
                    'status' => 'resolved',
                    'vat_rate' => $fallbackData['vat_rate'],
                    'is_non_retail' => $fallbackData['is_non_retail'],
                    'source' => 'fallback',
                ];
            }
        }

        return ['status' => 'no_product_match', 'source' => null];
    }

    /**
     * Check if VAT rate is a valid Irish rate.
     */
    public function isValidIrishVatRate(?float $rate): bool
    {
        if ($rate === null) {
            return false;
        }
        // Check exact match with tolerance for floating point
        foreach (self::VALID_VAT_RATES as $validRate) {
            if (abs($rate - $validRate) < 0.01) {
                return true;
            }
        }

        return false;
    }

    /**
     * Map VAT rate percentage to bucket key string.
     */
    public function vatRateToBucketKey(float $rate): string
    {
        if (abs($rate - 0) < 0.01) {
            return '0';
        }
        if (abs($rate - 9) < 0.01) {
            return '9';
        }
        if (abs($rate - 13.5) < 0.01) {
            return '13.5';
        }
        if (abs($rate - 23) < 0.01) {
            return '23';
        }

        return '0'; // Fallback (shouldn't reach here if validated)
    }

    /**
     * Freeze RTD data, creating an immutable snapshot.
     */
    public function freezeRtd(Invoice $invoice, int $userId): bool
    {
        if ($invoice->rtd_status === 'frozen') {
            return false;
        }

        // Get source file ID from current breakdown computation context
        $uploadFile = $this->getSourceUploadFile($invoice);
        $sourceFileId = $uploadFile?->id;

        $invoice->update([
            'rtd_snapshot' => [
                'breakdown' => $invoice->rtd_breakdown,
                'issues' => $invoice->rtd_resolution_issues,
                'computed_at' => $invoice->rtd_computed_at?->toIso8601String(),
                'source_file_id' => $sourceFileId,
                'frozen_at' => now()->toIso8601String(),
            ],
            'rtd_status' => 'frozen',
            'rtd_accepted_at' => now(),
            'rtd_accepted_by' => $userId,
        ]);

        return true;
    }

    /**
     * Unfreeze a frozen RTD invoice so it can be reparsed/recomputed.
     * Blocks if invoice is in a submitted submission.
     */
    public function unfreezeRtd(Invoice $invoice): bool
    {
        if ($invoice->rtd_status !== 'frozen') {
            return false;
        }

        // Block if in a submitted submission
        if ($invoice->rtd_submission_id) {
            $submission = $invoice->rtdSubmission;
            if ($submission && $submission->isSubmitted()) {
                return false;
            }
        }

        $invoice->update([
            'rtd_status' => 'needs_computation',
            'rtd_snapshot' => null,
            'rtd_accepted_at' => null,
            'rtd_accepted_by' => null,
        ]);

        return true;
    }

    /**
     * Check if RTD can be recomputed.
     */
    public function canRecompute(Invoice $invoice): bool
    {
        return $invoice->rtd_status !== 'frozen';
    }
}
