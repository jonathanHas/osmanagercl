<?php

namespace App\Http\Controllers;

use App\Models\AccountingSupplier;
use App\Models\Invoice;
use App\Models\InvoiceUploadFile;
use App\Services\RtdResolutionService;
use Illuminate\Http\Request;

class RtdController extends Controller
{
    public function __construct(
        protected RtdResolutionService $rtdService
    ) {}

    /**
     * Display the RTD management dashboard.
     */
    public function index(Request $request)
    {
        $filter = $request->get('filter', 'all');
        $search = $request->get('search', '');
        $supplierType = $request->get('supplier_type', 'all'); // all, parser, simple, service

        // Get all RTD-supported supplier invoices
        // Now queries by supplier rtd_classification OR legacy name-based detection
        $query = Invoice::with(['supplier', 'attachments', 'uploadFiles'])
            ->where(function ($q) {
                // New: Suppliers with rtd_classification set (not 'not_applicable')
                $q->whereHas('supplier', function ($sq) {
                    $sq->where('rtd_classification', '!=', 'not_applicable')
                        ->whereNotNull('rtd_classification');
                })
                // Legacy: Udea, Dynamis, Independent (for backwards compatibility)
                    ->orWhere('supplier_name', 'like', '%udea%')
                    ->orWhereHas('supplier', function ($sq) {
                        $sq->where('name', 'like', '%udea%');
                    })
                    ->orWhere('supplier_name', 'like', '%dynamis%')
                    ->orWhereHas('supplier', function ($sq) {
                        $sq->where('name', 'like', '%dynamis%');
                    })
                    ->orWhere('supplier_name', 'like', '%independent%')
                    ->orWhereHas('supplier', function ($sq) {
                        $sq->where('name', 'like', '%independent%');
                    });
            })
            ->orderByDesc('invoice_date');

        // Filter by supplier type
        if ($supplierType === 'parser') {
            $query->whereHas('supplier', function ($sq) {
                $sq->where('rtd_classification', 'goods_parser');
            });
        } elseif ($supplierType === 'simple') {
            $query->whereHas('supplier', function ($sq) {
                $sq->where('rtd_classification', 'goods_simple');
            });
        } elseif ($supplierType === 'service') {
            $query->whereHas('supplier', function ($sq) {
                $sq->where('rtd_classification', 'service_overhead');
            });
        }

        // Apply search filter
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('invoice_number', 'like', "%{$search}%")
                    ->orWhere('supplier_name', 'like', "%{$search}%");
            });
        }

        // Get all invoices for stats calculation
        $allInvoices = $query->get();

        // Calculate stats
        $stats = $this->calculateStats($allInvoices);

        // Apply status filter
        if ($filter !== 'all') {
            $allInvoices = $allInvoices->filter(function ($invoice) use ($filter) {
                return $this->getInvoiceRtdStatus($invoice) === $filter;
            });
        }

        // Paginate manually since we filtered in memory
        $page = $request->get('page', 1);
        $perPage = 20;
        $total = $allInvoices->count();
        $invoices = $allInvoices->forPage($page, $perPage)->values();

        // Add RTD status to each invoice, auto-computing for simple VAT and service suppliers
        $invoices = $invoices->map(function ($invoice) {
            // Auto-compute RTD for simple VAT and service suppliers that haven't been computed yet
            if ($invoice->supplier &&
                in_array($invoice->supplier->rtd_classification, ['goods_simple', 'service_overhead']) &&
                !$invoice->hasRtdData() &&
                $invoice->rtd_status !== 'frozen') {

                // Check invoice has VAT data to compute from
                $hasVatData = ($invoice->standard_net > 0 || $invoice->reduced_net > 0 ||
                               $invoice->second_reduced_net > 0 || $invoice->zero_net > 0 ||
                               $invoice->total_net > 0);

                if ($hasVatData) {
                    $rtdResult = $this->rtdService->computeRtd($invoice);
                    $invoice->update([
                        'rtd_breakdown' => $rtdResult['breakdown'],
                        'rtd_resolution_issues' => $rtdResult['issues'],
                        'rtd_status' => 'computed',
                        'rtd_computed_at' => now(),
                    ]);
                    $invoice->refresh();
                }
            }

            $invoice->rtd_display_status = $this->getInvoiceRtdStatus($invoice);

            return $invoice;
        });

        return view('rtd.index', [
            'invoices' => $invoices,
            'stats' => $stats,
            'filter' => $filter,
            'search' => $search,
            'supplierType' => $supplierType,
            'currentPage' => $page,
            'lastPage' => ceil($total / $perPage),
            'total' => $total,
        ]);
    }

    /**
     * Calculate RTD statistics for all Udea invoices.
     */
    private function calculateStats($invoices): array
    {
        $stats = [
            'total' => $invoices->count(),
            'needs_parsing' => 0,
            'pdf_missing' => 0,
            'needs_computation' => 0,
            'has_issues' => 0,
            'computed' => 0,
            'frozen' => 0,
        ];

        foreach ($invoices as $invoice) {
            $status = $this->getInvoiceRtdStatus($invoice);
            if (isset($stats[$status])) {
                $stats[$status]++;
            }
        }

        return $stats;
    }

    /**
     * Determine the RTD status of an invoice.
     */
    private function getInvoiceRtdStatus(Invoice $invoice): string
    {
        // Check if frozen
        if ($invoice->rtd_status === 'frozen') {
            return 'frozen';
        }

        // Check if has RTD data with issues
        if ($invoice->hasRtdData()) {
            $unresolvedCount = $invoice->rtd_breakdown['unresolved']['count'] ?? 0;
            if ($unresolvedCount > 0) {
                return 'has_issues';
            }

            return 'computed';
        }

        // Check if can compute (has parsed line data)
        if ($invoice->canComputeRtd()) {
            return 'needs_computation';
        }

        // Check if can parse (has PDF but no line data)
        if ($invoice->canReparseForRtd()) {
            return 'needs_parsing';
        }

        // Check if has attachment record but file missing from disk
        $hasPdfRecord = $invoice->attachments()->where('mime_type', 'application/pdf')->exists();
        if ($hasPdfRecord && ! $invoice->hasPdfOnDisk()) {
            return 'pdf_missing';
        }

        // No PDF at all
        return 'needs_parsing';
    }

    /**
     * Parse a PDF for RTD line extraction.
     */
    public function parse(Invoice $invoice)
    {
        // Check if invoice can be parsed
        if (! $invoice->canReparseForRtd()) {
            return $this->rtdResponse(false, 'This invoice cannot be parsed for RTD.', $invoice);
        }

        // Get the PDF attachment
        $attachment = $invoice->attachments()->where('mime_type', 'application/pdf')->first();
        if (! $attachment || ! $attachment->exists()) {
            return $this->rtdResponse(false, 'PDF attachment not found.', $invoice);
        }

        // Detect supplier type for parser selection
        $supplierName = strtolower($invoice->supplier_name ?? '');
        $isDynamis = str_contains($supplierName, 'dynamis');
        $isUdea = str_contains($supplierName, 'udea');
        $isIndependent = str_contains($supplierName, 'independent') || str_contains($supplierName, 'iih');

        if (! $isDynamis && ! $isUdea && ! $isIndependent) {
            return $this->rtdResponse(false, 'No RTD parser available for this supplier.', $invoice);
        }

        if ($isIndependent) {
            $supplierDetected = 'Independent';
            $parserScriptName = 'invoice_iih_rtd.py';
        } elseif ($isDynamis) {
            $supplierDetected = 'Dynamis';
            $parserScriptName = 'invoice_dynamis_rtd.py';
        } else {
            $supplierDetected = 'Udea';
            $parserScriptName = 'invoice_udea.py';
        }

        // Get or create the upload file to update
        $uploadFile = $invoice->uploadFiles()
            ->where('supplier_detected', $supplierDetected)
            ->whereNotNull('parsed_data')
            ->first();

        // If no upload file exists, create one for this attachment
        if (! $uploadFile) {
            $uploadFile = InvoiceUploadFile::create([
                'bulk_upload_id' => null,
                'invoice_id' => $invoice->id,
                'original_filename' => $attachment->original_filename ?? basename($attachment->file_path),
                'stored_filename' => basename($attachment->file_path),
                'mime_type' => 'application/pdf',
                'supplier_detected' => $supplierDetected,
                'parsed_data' => [],
                'status' => 'pending',
            ]);
        }

        try {
            $pdfPath = $attachment->full_storage_path;
            $parserScript = base_path("scripts/invoice-parser/parsers/{$parserScriptName}");
            $venvPython = base_path('scripts/invoice-parser/venv/bin/python');

            if (! file_exists($parserScript)) {
                return $this->rtdResponse(false, "{$supplierDetected} invoice parser script not found.", $invoice);
            }

            if (! file_exists($pdfPath)) {
                return $this->rtdResponse(false, 'PDF file not found on disk.', $invoice);
            }

            $pythonExecutable = file_exists($venvPython) ? $venvPython : 'python3';

            // Run the parser
            $command = sprintf(
                '%s %s %s 2>/dev/null',
                escapeshellarg($pythonExecutable),
                escapeshellarg($parserScript),
                escapeshellarg($pdfPath)
            );

            $output = shell_exec($command);

            if (! $output) {
                return $this->rtdResponse(false, 'Parser returned no output.', $invoice);
            }

            $result = json_decode($output, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                return $this->rtdResponse(false, 'Parser returned invalid JSON.', $invoice);
            }

            if (! isset($result['lines']) || empty($result['lines'])) {
                return $this->rtdResponse(false, 'Parser found no line items.', $invoice);
            }

            // Merge new line data into existing parsed_data
            $parsedData = $uploadFile->parsed_data ?? [];
            $parsedData['lines'] = $result['lines'];
            $parsedData['barrels'] = $result['barrels'] ?? [];
            $parsedData['costs'] = $result['costs'] ?? [];
            $parsedData['validation'] = $result['validation'] ?? [];
            $parsedData['header'] = $result['header'] ?? [];
            // IIH-specific fields for VAT summary approach
            $parsedData['vat_summary'] = $result['vat_summary'] ?? [];
            $parsedData['drs'] = $result['drs'] ?? [];

            $uploadFile->update([
                'parsed_data' => $parsedData,
                'parsed_at' => now(),
                'status' => 'completed',
            ]);

            $lineCount = count($result['lines']);

            // Auto-compute RTD after successful parse
            $invoice->refresh();
            $rtdResult = $this->rtdService->computeRtd($invoice);
            $invoice->update([
                'rtd_breakdown' => $rtdResult['breakdown'],
                'rtd_resolution_issues' => $rtdResult['issues'],
                'rtd_status' => 'computed',
                'rtd_computed_at' => now(),
            ]);

            $unresolvedCount = $rtdResult['breakdown']['unresolved']['count'] ?? 0;
            $message = "Invoice #{$invoice->invoice_number} parsed and computed. Found {$lineCount} lines, {$unresolvedCount} unresolved.";

            // Refresh invoice to get updated status
            $invoice->refresh();

            return $this->rtdResponse(true, $message, $invoice);

        } catch (\Exception $e) {
            \Log::error('RTD parse failed', [
                'invoice_id' => $invoice->id,
                'error' => $e->getMessage(),
            ]);

            return $this->rtdResponse(false, 'Parse failed: '.$e->getMessage(), $invoice);
        }
    }

    /**
     * Force parse a PDF for RTD line extraction.
     * Bypasses canReparseForRtd() check and clears existing parsed data.
     */
    public function forceParse(Invoice $invoice)
    {
        // Only check if PDF exists on disk (bypass canReparseForRtd)
        if (! $invoice->hasPdfOnDisk()) {
            return $this->rtdResponse(false, 'PDF file not found on disk.', $invoice);
        }

        // Get the PDF attachment
        $attachment = $invoice->attachments()->where('mime_type', 'application/pdf')->first();
        if (! $attachment || ! $attachment->exists()) {
            return $this->rtdResponse(false, 'PDF attachment not found.', $invoice);
        }

        // Detect supplier type for parser selection
        $supplierName = strtolower($invoice->supplier_name ?? '');
        $isDynamis = str_contains($supplierName, 'dynamis');
        $isUdea = str_contains($supplierName, 'udea');
        $isIndependent = str_contains($supplierName, 'independent') || str_contains($supplierName, 'iih');

        if (! $isDynamis && ! $isUdea && ! $isIndependent) {
            return $this->rtdResponse(false, 'No RTD parser available for this supplier.', $invoice);
        }

        if ($isIndependent) {
            $supplierDetected = 'Independent';
            $parserScriptName = 'invoice_iih_rtd.py';
        } elseif ($isDynamis) {
            $supplierDetected = 'Dynamis';
            $parserScriptName = 'invoice_dynamis_rtd.py';
        } else {
            $supplierDetected = 'Udea';
            $parserScriptName = 'invoice_udea.py';
        }

        // Delete existing upload file for this supplier to force fresh parse
        $invoice->uploadFiles()
            ->where('supplier_detected', $supplierDetected)
            ->delete();

        // Create fresh upload file for this attachment
        $uploadFile = InvoiceUploadFile::create([
            'bulk_upload_id' => null,
            'invoice_id' => $invoice->id,
            'original_filename' => $attachment->original_filename ?? basename($attachment->file_path),
            'stored_filename' => basename($attachment->file_path),
            'mime_type' => 'application/pdf',
            'supplier_detected' => $supplierDetected,
            'parsed_data' => [],
            'status' => 'pending',
        ]);

        try {
            $pdfPath = $attachment->full_storage_path;
            $parserScript = base_path("scripts/invoice-parser/parsers/{$parserScriptName}");
            $venvPython = base_path('scripts/invoice-parser/venv/bin/python');

            if (! file_exists($parserScript)) {
                return $this->rtdResponse(false, "{$supplierDetected} invoice parser script not found.", $invoice);
            }

            if (! file_exists($pdfPath)) {
                return $this->rtdResponse(false, 'PDF file not found on disk.', $invoice);
            }

            $pythonExecutable = file_exists($venvPython) ? $venvPython : 'python3';

            // Run the parser
            $command = sprintf(
                '%s %s %s 2>/dev/null',
                escapeshellarg($pythonExecutable),
                escapeshellarg($parserScript),
                escapeshellarg($pdfPath)
            );

            $output = shell_exec($command);

            if (! $output) {
                return $this->rtdResponse(false, 'Parser returned no output.', $invoice);
            }

            $result = json_decode($output, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                return $this->rtdResponse(false, 'Parser returned invalid JSON.', $invoice);
            }

            if (! isset($result['lines']) || empty($result['lines'])) {
                return $this->rtdResponse(false, 'Parser found no line items.', $invoice);
            }

            // Save all parsed data (fresh, not merged)
            $parsedData = [
                'lines' => $result['lines'],
                'barrels' => $result['barrels'] ?? [],
                'costs' => $result['costs'] ?? [],
                'validation' => $result['validation'] ?? [],
                'header' => $result['header'] ?? [],
                // IIH-specific fields for VAT summary approach
                'vat_summary' => $result['vat_summary'] ?? [],
                'drs' => $result['drs'] ?? [],
            ];

            $uploadFile->update([
                'parsed_data' => $parsedData,
                'parsed_at' => now(),
                'status' => 'completed',
            ]);

            $lineCount = count($result['lines']);

            // Auto-compute RTD after successful parse
            $invoice->refresh();
            $rtdResult = $this->rtdService->computeRtd($invoice);
            $invoice->update([
                'rtd_breakdown' => $rtdResult['breakdown'],
                'rtd_resolution_issues' => $rtdResult['issues'],
                'rtd_status' => 'computed',
                'rtd_computed_at' => now(),
            ]);

            $unresolvedCount = $rtdResult['breakdown']['unresolved']['count'] ?? 0;
            $message = "Invoice #{$invoice->invoice_number} parsed and computed. Found {$lineCount} lines, {$unresolvedCount} unresolved.";

            // Refresh invoice to get updated status
            $invoice->refresh();

            return $this->rtdResponse(true, $message, $invoice);

        } catch (\Exception $e) {
            \Log::error('RTD force parse failed', [
                'invoice_id' => $invoice->id,
                'error' => $e->getMessage(),
            ]);

            return $this->rtdResponse(false, 'Force parse failed: '.$e->getMessage(), $invoice);
        }
    }

    /**
     * Compute RTD breakdown for an invoice.
     */
    public function compute(Invoice $invoice)
    {
        if (! $invoice->canModifyRtd()) {
            return $this->rtdResponse(false, 'RTD is frozen and cannot be recomputed.', $invoice);
        }

        $result = $this->rtdService->computeRtd($invoice);

        $invoice->update([
            'rtd_breakdown' => $result['breakdown'],
            'rtd_resolution_issues' => $result['issues'],
            'rtd_status' => 'computed',
            'rtd_computed_at' => now(),
        ]);

        $stats = $result['breakdown']['stats'] ?? [];
        $unresolvedCount = $result['breakdown']['unresolved']['count'] ?? 0;
        $message = sprintf(
            'RTD computed for #%s: %d lines resolved, %d unresolved.',
            $invoice->invoice_number,
            $stats['resolved_lines'] ?? 0,
            $unresolvedCount
        );

        // Refresh invoice to get updated status
        $invoice->refresh();

        return $this->rtdResponse(true, $message, $invoice);
    }

    /**
     * Accept and freeze RTD breakdown for an invoice.
     */
    public function accept(Invoice $invoice)
    {
        if (! $invoice->canModifyRtd()) {
            return $this->rtdResponse(false, 'RTD is already frozen.', $invoice);
        }

        if ($this->rtdService->freezeRtd($invoice, auth()->id())) {
            $invoice->refresh();

            return $this->rtdResponse(true, "RTD for #{$invoice->invoice_number} accepted and frozen.", $invoice);
        }

        return $this->rtdResponse(false, 'Failed to freeze RTD.', $invoice);
    }

    /**
     * Recompute RTD for all invoices with issues.
     */
    public function recomputeAll()
    {
        $invoices = Invoice::where('rtd_status', '!=', 'frozen')
            ->whereNotNull('rtd_resolution_issues')
            ->where(function ($q) {
                // Udea
                $q->where('supplier_name', 'like', '%udea%')
                    ->orWhereHas('supplier', function ($sq) {
                        $sq->where('name', 'like', '%udea%');
                    })
                    // Dynamis
                    ->orWhere('supplier_name', 'like', '%dynamis%')
                    ->orWhereHas('supplier', function ($sq) {
                        $sq->where('name', 'like', '%dynamis%');
                    })
                    // Independent Irish Health Foods
                    ->orWhere('supplier_name', 'like', '%independent%')
                    ->orWhereHas('supplier', function ($sq) {
                        $sq->where('name', 'like', '%independent%');
                    });
            })
            ->get();

        $improved = 0;
        $recomputed = 0;

        foreach ($invoices as $invoice) {
            $previousCount = $invoice->rtd_breakdown['unresolved']['count'] ?? 0;

            $result = $this->rtdService->computeRtd($invoice);

            $invoice->update([
                'rtd_breakdown' => $result['breakdown'],
                'rtd_resolution_issues' => $result['issues'],
                'rtd_status' => 'computed',
                'rtd_computed_at' => now(),
            ]);

            $recomputed++;
            $newCount = $result['breakdown']['unresolved']['count'] ?? 0;
            if ($newCount < $previousCount) {
                $improved++;
            }
        }

        return back()->with('success', "{$recomputed} invoices recomputed. {$improved} invoices improved.");
    }

    /**
     * Display RTD Accounting Year report with aggregated totals.
     * Now supports T1 (goods for resale) and T2 (service/overhead) separation.
     */
    public function yearReport(Request $request)
    {
        // Default to current year
        $year = $request->get('year', now()->year);
        $startDate = $request->get('start_date', "{$year}-01-01");
        $endDate = $request->get('end_date', "{$year}-12-31");

        // Base query for RTD-supported supplier invoices in date range
        $baseQuery = Invoice::with('supplier')->where(function ($q) {
            // New: Suppliers with rtd_classification set (not 'not_applicable')
            $q->whereHas('supplier', function ($sq) {
                $sq->where('rtd_classification', '!=', 'not_applicable')
                    ->whereNotNull('rtd_classification');
            })
            // Legacy: Udea, Dynamis, Independent
                ->orWhere('supplier_name', 'like', '%udea%')
                ->orWhereHas('supplier', function ($sq) {
                    $sq->where('name', 'like', '%udea%');
                })
                ->orWhere('supplier_name', 'like', '%dynamis%')
                ->orWhereHas('supplier', function ($sq) {
                    $sq->where('name', 'like', '%dynamis%');
                })
                ->orWhere('supplier_name', 'like', '%independent%')
                ->orWhereHas('supplier', function ($sq) {
                    $sq->where('name', 'like', '%independent%');
                });
        })
            ->whereBetween('invoice_date', [$startDate, $endDate]);

        // Get frozen invoices for totals
        $frozenInvoices = (clone $baseQuery)
            ->where('rtd_status', 'frozen')
            ->whereNotNull('rtd_snapshot')
            ->orderBy('invoice_date')
            ->get();

        // Get non-frozen invoices (warnings)
        $nonFrozenInvoices = (clone $baseQuery)
            ->where('rtd_status', '!=', 'frozen')
            ->orderBy('invoice_date')
            ->get();

        // Aggregate totals from frozen invoices - separate T1 (goods) and T2 (service/overhead)
        $totals = [
            '0' => 0,
            '9' => 0,
            '13.5' => 0,
            '23' => 0,
        ];
        $serviceTotals = [
            '0' => 0,
            '9' => 0,
            '13.5' => 0,
            '23' => 0,
        ];
        $excludedTotals = [
            'freight' => 0,
            'deposits' => 0,
            'drs' => 0,
            'vat' => 0,
        ];
        $grandTotal = 0;
        $serviceGrandTotal = 0;

        // Separate frozen invoices into T1 (goods) and T2 (service)
        $goodsInvoices = collect();
        $serviceInvoices = collect();

        foreach ($frozenInvoices as $invoice) {
            $snapshot = $invoice->rtd_snapshot;
            if (! $snapshot) {
                continue;
            }

            // Check if service/overhead invoice
            $isService = ($snapshot['breakdown']['stats']['is_service'] ?? false)
                || ($invoice->supplier && $invoice->supplier->rtd_classification === 'service_overhead');

            if ($isService) {
                $serviceInvoices->push($invoice);

                // Aggregate service totals from service_overhead breakdown
                $serviceBreakdown = $snapshot['breakdown']['service_overhead'] ?? $snapshot['breakdown']['goods_for_resale'] ?? [];
                foreach ($serviceTotals as $rate => $value) {
                    $serviceTotals[$rate] += (float) ($serviceBreakdown[$rate] ?? 0);
                }
                $serviceGrandTotal += array_sum(array_map('floatval', $serviceBreakdown));
            } else {
                $goodsInvoices->push($invoice);

                if (! isset($snapshot['breakdown']['goods_for_resale'])) {
                    continue;
                }

                $gfr = $snapshot['breakdown']['goods_for_resale'];
                foreach ($totals as $rate => $value) {
                    $totals[$rate] += (float) ($gfr[$rate] ?? 0);
                }
                $grandTotal += array_sum(array_map('floatval', $gfr));
            }

            // Excluded totals are aggregated regardless of type
            $excluded = $snapshot['breakdown']['excluded'] ?? [];
            $excludedTotals['freight'] += (float) ($excluded['freight'] ?? 0);
            $excludedTotals['deposits'] += (float) ($excluded['deposits'] ?? 0);
            $excludedTotals['drs'] += (float) ($excluded['drs'] ?? 0);
            $excludedTotals['vat'] += (float) ($excluded['vat'] ?? 0);
        }

        // Available years for dropdown (from earliest RTD-supported supplier invoice to current year)
        $earliestYear = Invoice::where(function ($q) {
            // Udea
            $q->where('supplier_name', 'like', '%udea%')
                ->orWhereHas('supplier', function ($sq) {
                    $sq->where('name', 'like', '%udea%');
                })
                // Dynamis
                ->orWhere('supplier_name', 'like', '%dynamis%')
                ->orWhereHas('supplier', function ($sq) {
                    $sq->where('name', 'like', '%dynamis%');
                })
                // Independent Irish Health Foods
                ->orWhere('supplier_name', 'like', '%independent%')
                ->orWhereHas('supplier', function ($sq) {
                    $sq->where('name', 'like', '%independent%');
                });
        })
            ->min('invoice_date');

        $availableYears = [];
        if ($earliestYear) {
            $startYear = (int) date('Y', strtotime($earliestYear));
            for ($y = $startYear; $y <= now()->year; $y++) {
                $availableYears[] = $y;
            }
        } else {
            $availableYears = [now()->year];
        }

        return view('rtd.year-report', [
            'frozenInvoices' => $frozenInvoices,
            'goodsInvoices' => $goodsInvoices,
            'serviceInvoices' => $serviceInvoices,
            'nonFrozenInvoices' => $nonFrozenInvoices,
            'totals' => $totals,
            'serviceTotals' => $serviceTotals,
            'excludedTotals' => $excludedTotals,
            'grandTotal' => $grandTotal,
            'serviceGrandTotal' => $serviceGrandTotal,
            'startDate' => $startDate,
            'endDate' => $endDate,
            'year' => $year,
            'availableYears' => $availableYears,
        ]);
    }

    /**
     * Display RTD Issues work queue - invoices with unresolved items.
     */
    public function issues(Request $request)
    {
        // Get RTD-supported supplier invoices with unresolved RTD items
        $invoices = Invoice::with(['supplier'])
            ->where(function ($q) {
                // Udea
                $q->where('supplier_name', 'like', '%udea%')
                    ->orWhereHas('supplier', function ($sq) {
                        $sq->where('name', 'like', '%udea%');
                    })
                    // Dynamis
                    ->orWhere('supplier_name', 'like', '%dynamis%')
                    ->orWhereHas('supplier', function ($sq) {
                        $sq->where('name', 'like', '%dynamis%');
                    })
                    // Independent Irish Health Foods
                    ->orWhere('supplier_name', 'like', '%independent%')
                    ->orWhereHas('supplier', function ($sq) {
                        $sq->where('name', 'like', '%independent%');
                    });
            })
            ->where('rtd_status', '!=', 'frozen')
            ->where(function ($q) {
                // Has unresolved count > 0 OR has resolution issues
                $q->whereRaw("JSON_EXTRACT(rtd_breakdown, '$.unresolved.count') > 0")
                    ->orWhere(function ($sq) {
                        $sq->whereNotNull('rtd_resolution_issues')
                            ->whereRaw('JSON_LENGTH(rtd_resolution_issues) > 0');
                    });
            })
            ->orderByDesc('invoice_date')
            ->paginate(20);

        // Calculate summary stats
        $totalUnresolvedValue = 0;
        $totalUnresolvedCount = 0;

        foreach ($invoices as $invoice) {
            $totalUnresolvedCount += $invoice->rtd_breakdown['unresolved']['count'] ?? 0;
            $totalUnresolvedValue += $invoice->rtd_breakdown['unresolved']['net_total'] ?? 0;
        }

        return view('rtd.issues', [
            'invoices' => $invoices,
            'totalUnresolvedCount' => $totalUnresolvedCount,
            'totalUnresolvedValue' => $totalUnresolvedValue,
        ]);
    }

    /**
     * Return appropriate response for RTD actions (JSON for AJAX, redirect for form).
     */
    private function rtdResponse(bool $success, string $message, Invoice $invoice)
    {
        if (request()->wantsJson() || request()->ajax()) {
            $status = $this->getInvoiceRtdStatus($invoice);
            $breakdown = $invoice->rtd_breakdown ?? [];
            $unresolvedCount = $breakdown['unresolved']['count'] ?? 0;

            return response()->json([
                'success' => $success,
                'message' => $message,
                'invoice_id' => $invoice->id,
                'new_status' => $status,
                'can_compute' => $invoice->canComputeRtd(),
                'can_parse' => $invoice->canReparseForRtd(),
                'has_rtd_data' => $invoice->hasRtdData(),
                'is_frozen' => $invoice->rtd_status === 'frozen',
                'unresolved_count' => $unresolvedCount,
                // RTD breakdown data for detail row update
                'rtd_breakdown' => $breakdown,
                'rtd_total' => $invoice->hasRtdData() ? $invoice->getRtdTotal() : 0,
                'invoice_total' => $invoice->total_amount,
                'rtd_resolution_issues' => $invoice->rtd_resolution_issues ?? [],
                'rtd_accepted_at' => $invoice->rtd_accepted_at?->format('d M Y H:i'),
                'rtd_accepted_by' => $invoice->rtdAcceptedByUser?->name,
            ]);
        }

        if ($success) {
            return back()->with('success', $message);
        }

        return back()->with('error', $message);
    }

    /**
     * Display the supplier RTD classification editor.
     */
    public function suppliers(Request $request)
    {
        $search = $request->get('search', '');
        $filterClassification = $request->get('classification', 'all');
        $filterType = $request->get('type', 'all');

        $query = AccountingSupplier::query()
            ->withCount('invoices')
            ->orderBy('name');

        // Search filter
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%");
            });
        }

        // Classification filter
        if ($filterClassification !== 'all') {
            $query->where('rtd_classification', $filterClassification);
        }

        // Supplier type filter
        if ($filterType !== 'all') {
            $query->where('supplier_type', $filterType);
        }

        $suppliers = $query->get();

        // Get stats
        $stats = [
            'total' => AccountingSupplier::count(),
            'goods_parser' => AccountingSupplier::where('rtd_classification', 'goods_parser')->count(),
            'goods_simple' => AccountingSupplier::where('rtd_classification', 'goods_simple')->count(),
            'service_overhead' => AccountingSupplier::where('rtd_classification', 'service_overhead')->count(),
            'not_applicable' => AccountingSupplier::where('rtd_classification', 'not_applicable')->count(),
        ];

        // Get unique supplier types for filter
        $supplierTypes = AccountingSupplier::distinct()
            ->pluck('supplier_type')
            ->filter()
            ->sort()
            ->values();

        $rtdClassifications = AccountingSupplier::RTD_CLASSIFICATIONS;

        return view('rtd.suppliers', [
            'suppliers' => $suppliers,
            'stats' => $stats,
            'search' => $search,
            'filterClassification' => $filterClassification,
            'filterType' => $filterType,
            'supplierTypes' => $supplierTypes,
            'rtdClassifications' => $rtdClassifications,
        ]);
    }

    /**
     * Update a supplier's RTD classification via AJAX.
     */
    public function classifySupplier(Request $request, AccountingSupplier $supplier)
    {
        $validated = $request->validate([
            'rtd_classification' => 'required|in:goods_simple,goods_parser,service_overhead,not_applicable',
        ]);

        try {
            $oldClassification = $supplier->rtd_classification;
            $supplier->update([
                'rtd_classification' => $validated['rtd_classification'],
                'updated_by' => auth()->id(),
            ]);

            \Log::info('Supplier RTD classification updated', [
                'supplier_id' => $supplier->id,
                'supplier_name' => $supplier->name,
                'old_classification' => $oldClassification,
                'new_classification' => $validated['rtd_classification'],
                'updated_by' => auth()->id(),
            ]);

            return response()->json([
                'success' => true,
                'message' => "Classification updated for '{$supplier->name}'",
                'supplier_id' => $supplier->id,
                'rtd_classification' => $supplier->rtd_classification,
            ]);

        } catch (\Exception $e) {
            \Log::error('Failed to update supplier RTD classification', [
                'supplier_id' => $supplier->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update classification',
            ], 422);
        }
    }
}
