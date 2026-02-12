<?php

namespace App\Http\Controllers;

use App\Models\AccountingSupplier;
use App\Models\CostCategory;
use App\Models\Invoice;
use App\Models\InvoiceVatLine;
use App\Models\VatRate;
use App\Repositories\InvoiceRepository;
use App\Rules\RepairablePdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class InvoiceController extends Controller
{
    public function __construct(
        protected InvoiceRepository $invoiceRepository
    ) {}

    /**
     * Display a listing of invoices.
     */
    public function index(Request $request)
    {
        $query = Invoice::with(['supplier', 'vatLines', 'attachments']);

        // Default to last 3 months if no date filters provided
        $fromDate = $request->filled('from_date') ? $request->from_date : now()->subMonths(3)->format('Y-m-d');
        $toDate = $request->filled('to_date') ? $request->to_date : null;

        // Apply filters
        if ($request->filled('supplier_id')) {
            $query->where('supplier_id', $request->supplier_id);
        }

        if ($request->filled('payment_status')) {
            if ($request->payment_status === 'unpaid') {
                // "All Unpaid" includes pending, overdue, and partial
                $query->whereIn('payment_status', ['pending', 'overdue', 'partial']);
            } else {
                $query->where('payment_status', $request->payment_status);
            }
        }

        // Apply date filters (default to 2025 onwards)
        $query->where('invoice_date', '>=', $fromDate);

        if ($toDate) {
            $query->where('invoice_date', '<=', $toDate);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('invoice_number', 'like', "%{$search}%")
                    ->orWhere('supplier_name', 'like', "%{$search}%")
                    ->orWhere('notes', 'like', "%{$search}%");
            });
        }

        // Handle sorting
        $sortField = $request->get('sort', 'invoice_date');
        $sortDirection = $request->get('direction', 'desc');

        // Validate sort field
        $allowedSortFields = [
            'invoice_number',
            'supplier_name',
            'invoice_date',
            'payment_status',
            'payment_date',
            'subtotal',
            'vat_amount',
            'total_amount',
        ];

        if (! in_array($sortField, $allowedSortFields)) {
            $sortField = 'invoice_date';
        }

        // Validate sort direction
        if (! in_array($sortDirection, ['asc', 'desc'])) {
            $sortDirection = 'desc';
        }

        // Handle payment_date sorting with special logic for NULL values
        if ($sortField === 'payment_date') {
            if ($sortDirection === 'desc') {
                // Most recent payments first, then unpaid invoices
                $invoices = $query->orderByRaw('payment_date IS NULL ASC, payment_date DESC')
                    ->orderBy('id', 'desc')
                    ->get();
            } else {
                // Oldest payments first, then unpaid invoices
                $invoices = $query->orderByRaw('payment_date IS NULL ASC, payment_date ASC')
                    ->orderBy('id', 'desc')
                    ->get();
            }
        } else {
            $invoices = $query->orderBy($sortField, $sortDirection)
                ->orderBy('id', 'desc') // Secondary sort for consistency
                ->get();
        }

        // Get suppliers for filter dropdown
        $suppliers = AccountingSupplier::activeOnly()
            ->orderBy('name')
            ->pluck('name', 'id');

        // Build stats query with same filters as main query (but without eager loading)
        $statsQuery = Invoice::query();
        if ($request->filled('supplier_id')) {
            $statsQuery->where('supplier_id', $request->supplier_id);
        }
        if ($request->filled('payment_status')) {
            if ($request->payment_status === 'unpaid') {
                $statsQuery->whereIn('payment_status', ['pending', 'overdue', 'partial']);
            } else {
                $statsQuery->where('payment_status', $request->payment_status);
            }
        }
        $statsQuery->where('invoice_date', '>=', $fromDate);
        if ($toDate) {
            $statsQuery->where('invoice_date', '<=', $toDate);
        }
        if ($request->filled('search')) {
            $search = $request->search;
            $statsQuery->where(function ($q) use ($search) {
                $q->where('invoice_number', 'like', "%{$search}%")
                    ->orWhere('supplier_name', 'like', "%{$search}%")
                    ->orWhere('notes', 'like', "%{$search}%");
            });
        }

        // Get all statistics in optimized single queries (replaces 14+ queries with 3)
        $filteredStats = $this->invoiceRepository->getFilteredStatistics($statsQuery);
        $stats = $this->invoiceRepository->getOverallStatistics();
        $monthlyTotals = $this->invoiceRepository->getMonthlyTotals();
        $amazonPendingCount = $this->invoiceRepository->getAmazonPendingCount();

        return view('invoices.index', compact(
            'invoices',
            'suppliers',
            'stats',
            'filteredStats',
            'monthlyTotals',
            'amazonPendingCount',
            'sortField',
            'sortDirection'
        ));
    }

    /**
     * Show the form for creating a new invoice.
     */
    public function create()
    {
        $suppliers = AccountingSupplier::activeOnly()
            ->orderBy('name')
            ->pluck('name', 'id');

        $categories = CostCategory::getForDropdown();
        $vatRates = VatRate::getAvailableCodes();

        return view('invoices.create', compact('suppliers', 'categories', 'vatRates'));
    }

    /**
     * Show the form for creating a simple invoice (VAT totals only).
     */
    public function createSimple()
    {
        $suppliers = AccountingSupplier::activeOnly()
            ->orderBy('name')
            ->pluck('name', 'id');

        $categories = CostCategory::getForDropdown();

        return view('invoices.create-simple', compact('suppliers', 'categories'));
    }

    /**
     * Store a newly created invoice in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'invoice_number' => 'required|string|max:100',
            'supplier_id' => 'nullable|exists:accounting_suppliers,id',
            'supplier_name' => 'required|string|max:255',
            'invoice_date' => 'required|date',
            'due_date' => 'nullable|date|after_or_equal:invoice_date',
            'expense_category' => 'nullable|string|max:50',
            'notes' => 'nullable|string',

            // VAT lines
            'vat_lines' => 'required|array|min:1',
            'vat_lines.*.vat_category' => 'required|string|in:STANDARD,REDUCED,SECOND_REDUCED,ZERO',
            'vat_lines.*.net_amount' => 'required|numeric',
        ]);

        DB::beginTransaction();
        try {
            // Get supplier defaults if supplier is selected
            if ($validated['supplier_id']) {
                $supplier = AccountingSupplier::find($validated['supplier_id']);
                if ($supplier) {
                    $validated['supplier_name'] = $supplier->name;
                    if (! $validated['expense_category'] && $supplier->default_expense_category) {
                        $validated['expense_category'] = $supplier->default_expense_category;
                    }
                    if (! $validated['due_date'] && $supplier->payment_terms_days) {
                        $validated['due_date'] = Carbon::parse($validated['invoice_date'])
                            ->addDays($supplier->payment_terms_days);
                    }
                }
            }

            // Create invoice
            $invoice = Invoice::create([
                'invoice_number' => $validated['invoice_number'],
                'supplier_id' => $validated['supplier_id'],
                'supplier_name' => $validated['supplier_name'],
                'invoice_date' => $validated['invoice_date'],
                'due_date' => $validated['due_date'],
                'expense_category' => $validated['expense_category'],
                'notes' => $validated['notes'] ?? null,
                'payment_status' => 'pending',
                'created_by' => auth()->id(),
                'updated_by' => auth()->id(),
            ]);

            // Create VAT lines
            foreach ($validated['vat_lines'] as $index => $lineData) {
                InvoiceVatLine::create([
                    'invoice_id' => $invoice->id,
                    'vat_category' => $lineData['vat_category'],
                    'net_amount' => $lineData['net_amount'],
                    'line_number' => $index + 1,
                    'created_by' => auth()->id(),
                ]);
            }

            // Calculate totals from VAT lines
            $invoice->calculateTotals();

            DB::commit();

            return redirect()->route('invoices.show', $invoice)
                ->with('success', 'Invoice created successfully.');

        } catch (\Exception $e) {
            DB::rollBack();

            return back()->withInput()
                ->with('error', 'Failed to create invoice: '.$e->getMessage());
        }
    }

    /**
     * Store a simple invoice (VAT totals only).
     */
    public function storeSimple(Request $request)
    {
        $validated = $request->validate([
            'supplier_invoice_reference' => 'nullable|string|max:255',  // Supplier's original invoice number
            'supplier_id' => 'nullable|exists:accounting_suppliers,id',
            'supplier_name' => 'required|string|max:255',
            'invoice_date' => 'required|date',
            'due_date' => 'nullable|date|after_or_equal:invoice_date',
            'expense_category' => 'nullable|string|max:50',
            'notes' => 'nullable|string',

            // VAT breakdown
            'standard_net' => 'nullable|numeric|min:0',
            'standard_vat' => 'nullable|numeric|min:0',
            'reduced_net' => 'nullable|numeric|min:0',
            'reduced_vat' => 'nullable|numeric|min:0',
            'second_reduced_net' => 'nullable|numeric|min:0',
            'second_reduced_vat' => 'nullable|numeric|min:0',
            'zero_net' => 'nullable|numeric|min:0',
            'zero_vat' => 'nullable|numeric|min:0',
            'subtotal' => 'required|numeric|min:0',
            'vat_amount' => 'required|numeric|min:0',
            'total_amount' => 'required|numeric|min:0',
            // Optional file upload
            'invoice_document' => [
                'nullable',
                'file',
                'max:'.(config('invoices.bulk_upload.max_file_size_mb') * 1024), // Convert MB to KB
                new RepairablePdf, // This handles both file validation and PDF repair
            ],
            // Force create duplicate
            'force' => 'nullable|boolean',
        ]);

        // Get supplier defaults if supplier is selected
        if ($validated['supplier_id']) {
            $supplier = AccountingSupplier::find($validated['supplier_id']);
            if ($supplier) {
                $validated['supplier_name'] = $supplier->name;
                if (! $validated['expense_category'] && $supplier->default_expense_category) {
                    $validated['expense_category'] = $supplier->default_expense_category;
                }
                if (! $validated['due_date'] && $supplier->payment_terms_days) {
                    $validated['due_date'] = Carbon::parse($validated['invoice_date'])
                        ->addDays($supplier->payment_terms_days);
                }
            }
        }

        // Check for duplicate invoices (unless force is true)
        if (! ($request->input('force') == 1)) {
            $duplicates = $this->checkForDuplicates(
                $validated['supplier_id'],
                $validated['supplier_name'],
                $validated['invoice_date'],
                $validated['total_amount']
            );

            if ($duplicates->isNotEmpty()) {
                // Found duplicate(s), redirect back with warning
                return redirect()->back()
                    ->withInput()
                    ->with('duplicate_found', [
                        'invoices' => $duplicates->map(function ($invoice) {
                            return [
                                'id' => $invoice->id,
                                'invoice_number' => $invoice->invoice_number,
                                'supplier_name' => $invoice->supplier_name,
                                'invoice_date' => $invoice->invoice_date->format('d/m/Y'),
                                'total_amount' => $invoice->total_amount,
                            ];
                        })->toArray(),
                    ]);
            }
        }

        return DB::transaction(function () use ($validated, $request) {
            // Create invoice with temporary number
            $invoice = Invoice::create([
                'invoice_number' => 'TEMP-'.uniqid(),  // Temporary, will be updated
                'supplier_invoice_reference' => $validated['supplier_invoice_reference'] ?? null,
                'supplier_id' => $validated['supplier_id'],
                'supplier_name' => $validated['supplier_name'],
                'invoice_date' => $validated['invoice_date'],
                'due_date' => $validated['due_date'],
                'expense_category' => $validated['expense_category'],
                'notes' => $validated['notes'] ?? null,
                'payment_status' => 'pending',
                'subtotal' => $validated['subtotal'],
                'vat_amount' => $validated['vat_amount'],
                'total_amount' => $validated['total_amount'],
                'standard_net' => $validated['standard_net'] ?? 0,
                'standard_vat' => $validated['standard_vat'] ?? 0,
                'reduced_net' => $validated['reduced_net'] ?? 0,
                'reduced_vat' => $validated['reduced_vat'] ?? 0,
                'second_reduced_net' => $validated['second_reduced_net'] ?? 0,
                'second_reduced_vat' => $validated['second_reduced_vat'] ?? 0,
                'zero_net' => $validated['zero_net'] ?? 0,
                'zero_vat' => $validated['zero_vat'] ?? 0,
                'created_by' => auth()->id(),
                'updated_by' => auth()->id(),
            ]);

            // Generate invoice number based on invoice ID (consistent with bulk upload)
            $invoiceNumber = 'INV-'.date('Y').'-'.str_pad($invoice->id, 6, '0', STR_PAD_LEFT);
            $invoice->update(['invoice_number' => $invoiceNumber]);

            // Handle file upload if present
            if ($request->hasFile('invoice_document')) {
                $this->handleInvoiceAttachment($invoice, $request->file('invoice_document'));
            }

            // Redirect back to create form with supplier preserved for batch entry
            return redirect()->route('invoices.create-simple', ['supplier_id' => $validated['supplier_id']])
                ->with('invoice_created', [
                    'id' => $invoice->id,
                    'invoice_number' => $invoice->invoice_number,
                    'supplier_name' => $invoice->supplier_name,
                    'total_amount' => $invoice->total_amount,
                    'invoice_date' => $invoice->invoice_date->format('d/m/Y'),
                ]);
        });
    }

    /**
     * Display the specified invoice.
     */
    public function show(Invoice $invoice)
    {
        // Debug logging to track invoice access attempts
        \Log::info('Invoice show accessed', [
            'invoice_id' => $invoice->id,
            'invoice_number' => $invoice->invoice_number,
            'supplier_name' => $invoice->supplier_name,
            'supplier_id' => $invoice->supplier_id,
            'user_id' => auth()->id(),
            'user_agent' => request()->userAgent(),
            'ip' => request()->ip(),
        ]);

        $invoice->load(['supplier', 'vatLines', 'creator', 'updater']);

        $vatBreakdown = $invoice->getVatBreakdown();

        return view('invoices.show', compact('invoice', 'vatBreakdown'));
    }

    /**
     * Show the form for editing the specified invoice.
     */
    public function edit(Invoice $invoice)
    {
        $invoice->load('vatLines');

        $suppliers = AccountingSupplier::activeOnly()
            ->orderBy('name')
            ->pluck('name', 'id');

        $categories = CostCategory::getForDropdown();
        $vatRates = VatRate::getAvailableCodes($invoice->invoice_date);

        // Prepare VAT lines for JavaScript with proper type casting
        $invoiceVatLines = old('vat_lines', $invoice->vatLines->map(function ($line) {
            return [
                'id' => $line->id,
                'vat_category' => $line->vat_category,
                'net_amount' => (float) $line->net_amount,
                'vat_rate' => (float) $line->vat_rate,
                'vat_amount' => (float) $line->vat_amount,
                'gross_amount' => (float) $line->gross_amount,
                'line_number' => (int) $line->line_number,
            ];
        })->toArray());

        return view('invoices.edit', compact('invoice', 'suppliers', 'categories', 'vatRates', 'invoiceVatLines'));
    }

    /**
     * Update the specified invoice in storage.
     */
    public function update(Request $request, Invoice $invoice)
    {
        $validated = $request->validate([
            'invoice_number' => 'required|string|max:100',
            'supplier_id' => 'nullable|exists:accounting_suppliers,id',
            'supplier_name' => 'required|string|max:255',
            'invoice_date' => 'required|date',
            'due_date' => 'nullable|date|after_or_equal:invoice_date',
            'expense_category' => 'nullable|string|max:50',
            'notes' => 'nullable|string',

            // VAT lines
            'vat_lines' => 'required|array|min:1',
            'vat_lines.*.id' => 'nullable|exists:invoice_vat_lines,id',
            'vat_lines.*.vat_category' => 'required|string|in:STANDARD,REDUCED,SECOND_REDUCED,ZERO',
            'vat_lines.*.net_amount' => 'required|numeric',
        ]);

        DB::beginTransaction();
        try {
            // Update invoice
            $invoice->update([
                'invoice_number' => $validated['invoice_number'],
                'supplier_id' => $validated['supplier_id'],
                'supplier_name' => $validated['supplier_name'],
                'invoice_date' => $validated['invoice_date'],
                'due_date' => $validated['due_date'],
                'expense_category' => $validated['expense_category'],
                'notes' => $validated['notes'],
                'updated_by' => auth()->id(),
            ]);

            // Track existing VAT lines
            $existingLineIds = $invoice->vatLines->pluck('id')->toArray();
            $updatedLineIds = [];

            // Update or create VAT lines
            foreach ($validated['vat_lines'] as $index => $lineData) {
                if (isset($lineData['id']) && $lineData['id']) {
                    // Update existing line
                    $line = InvoiceVatLine::find($lineData['id']);
                    if ($line && $line->invoice_id == $invoice->id) {
                        $line->update([
                            'vat_category' => $lineData['vat_category'],
                            'net_amount' => $lineData['net_amount'],
                            'vat_rate' => InvoiceVatLine::getDefaultVatRate($lineData['vat_category']),
                            'line_number' => $index + 1,
                            'updated_by' => auth()->id(),
                        ]);
                        $updatedLineIds[] = $line->id;
                    }
                } else {
                    // Create new line
                    $line = InvoiceVatLine::create([
                        'invoice_id' => $invoice->id,
                        'vat_category' => $lineData['vat_category'],
                        'net_amount' => $lineData['net_amount'],
                        'line_number' => $index + 1,
                        'created_by' => auth()->id(),
                    ]);
                    $updatedLineIds[] = $line->id;
                }
            }

            // Delete removed lines
            $linesToDelete = array_diff($existingLineIds, $updatedLineIds);
            if (! empty($linesToDelete)) {
                InvoiceVatLine::whereIn('id', $linesToDelete)
                    ->where('invoice_id', $invoice->id)
                    ->delete();
            }

            // Refresh the invoice's VAT lines relationship and recalculate totals
            $invoice->refresh();
            $invoice->load('vatLines');
            $invoice->calculateTotals();

            DB::commit();

            return redirect()->route('invoices.show', $invoice)
                ->with('success', 'Invoice updated successfully.');

        } catch (\Exception $e) {
            DB::rollBack();

            return back()->withInput()
                ->with('error', 'Failed to update invoice: '.$e->getMessage());
        }
    }

    /**
     * Remove the specified invoice from storage.
     */
    public function destroy(Request $request, Invoice $invoice)
    {
        try {
            // Block deletion if invoice is assigned to a VAT return
            if ($invoice->vat_return_id) {
                $vatReturn = $invoice->vatReturn;

                if ($vatReturn && ! $vatReturn->canBeModified()) {
                    // Finalized/submitted/paid VAT return
                    if (abs($invoice->vat_amount) > 0) {
                        return back()->with('error',
                            'Cannot delete this invoice — it contributes VAT to '
                            .$vatReturn->status.' VAT return: '.$vatReturn->return_period
                            .'. You must revert the VAT return to draft first.');
                    }

                    // No VAT impact — allow only if user confirmed
                    if (! $request->boolean('force')) {
                        return back()->with('warning',
                            'This invoice is assigned to '.$vatReturn->status
                            .' VAT return: '.$vatReturn->return_period
                            .'. It has no VAT impact so it can be removed.');
                    }
                }

                // Unlink from VAT return and recalculate if draft
                $invoice->update(['vat_return_id' => null]);
                if ($vatReturn && $vatReturn->canBeModified()) {
                    $vatReturn->calculateTotals();
                }
            }

            // Block deletion if invoice is part of a submitted RTD submission
            if ($invoice->rtd_submission_id) {
                $submission = $invoice->rtdSubmission;
                if ($submission && $submission->isSubmitted()) {
                    return back()->with('error',
                        'Cannot delete this invoice — it is part of submitted RTD submission: '
                        .$submission->reference_number);
                }

                // Unlink from draft submission and recalculate its totals
                $invoice->update(['rtd_submission_id' => null]);
                $submission->calculateTotalsSnapshot();
            }

            $invoice->delete();

            return redirect()->route('invoices.index')
                ->with('success', 'Invoice deleted successfully.');
        } catch (\Exception $e) {
            return back()->with('error', 'Failed to delete invoice: '.$e->getMessage());
        }
    }

    /**
     * Mark invoice as paid.
     */
    public function markPaid(Request $request, Invoice $invoice)
    {
        $validated = $request->validate([
            'payment_date' => 'required|date',
            'payment_method' => 'nullable|string|max:50',
            'payment_reference' => 'nullable|string|max:100',
        ]);

        $invoice->update([
            'payment_status' => 'paid',
            'payment_date' => $validated['payment_date'],
            'payment_method' => $validated['payment_method'],
            'payment_reference' => $validated['payment_reference'],
            'updated_by' => auth()->id(),
        ]);

        return back()->with('success', 'Invoice marked as paid.');
    }

    /**
     * Mark multiple invoices as paid (bulk operation).
     */
    public function bulkMarkPaid(Request $request)
    {
        $validated = $request->validate([
            'invoice_ids' => 'required|array|min:1',
            'invoice_ids.*' => 'exists:invoices,id',
            'payment_date' => 'required|date',
            'payment_method' => 'nullable|string|max:50',
            'payment_reference' => 'nullable|string|max:100',
        ]);

        try {
            DB::beginTransaction();

            $invoices = Invoice::whereIn('id', $validated['invoice_ids'])->get();
            $updatedCount = 0;

            foreach ($invoices as $invoice) {
                // Only update if not already paid
                if ($invoice->payment_status !== 'paid') {
                    $invoice->update([
                        'payment_status' => 'paid',
                        'payment_date' => $validated['payment_date'],
                        'payment_method' => $validated['payment_method'],
                        'payment_reference' => $validated['payment_reference'],
                        'updated_by' => auth()->id(),
                    ]);
                    $updatedCount++;
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => "{$updatedCount} invoice(s) marked as paid successfully.",
                'updated_count' => $updatedCount,
            ]);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'error' => 'Failed to mark invoices as paid: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Mark invoice as unpaid.
     */
    public function markUnpaid(Invoice $invoice)
    {
        try {
            $invoice->update([
                'payment_status' => 'pending',
                'payment_date' => null,
                'payment_method' => null,
                'payment_reference' => null,
                'updated_by' => auth()->id(),
            ]);

            return back()->with('success', 'Invoice marked as unpaid.');
        } catch (\Exception $e) {
            return back()->with('error', 'Failed to mark invoice as unpaid: '.$e->getMessage());
        }
    }

    /**
     * Get VAT rate for AJAX requests.
     */
    public function getVatRate(Request $request)
    {
        $validated = $request->validate([
            'vat_code' => 'required|string',
            'date' => 'required|date',
        ]);

        $vatRate = VatRate::getRateByCode(
            $validated['vat_code'],
            Carbon::parse($validated['date'])
        );

        if ($vatRate) {
            return response()->json([
                'success' => true,
                'rate' => $vatRate->rate,
                'formatted_rate' => $vatRate->formatted_rate,
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'VAT rate not found',
        ], 404);
    }

    /**
     * Export invoices to CSV
     */
    public function exportCsv(Request $request)
    {
        // Use the same filtering logic as index method
        $query = Invoice::with(['supplier', 'vatLines']);

        // Default to last 3 months if no date filters provided
        $fromDate = $request->filled('from_date') ? $request->from_date : now()->subMonths(3)->format('Y-m-d');
        $toDate = $request->filled('to_date') ? $request->to_date : null;

        // Apply filters (same logic as index)
        if ($request->filled('supplier_id')) {
            $query->where('supplier_id', $request->supplier_id);
        }

        if ($request->filled('payment_status')) {
            if ($request->payment_status === 'unpaid') {
                $query->whereIn('payment_status', ['pending', 'overdue', 'partial']);
            } else {
                $query->where('payment_status', $request->payment_status);
            }
        }

        $query->where('invoice_date', '>=', $fromDate);
        if ($toDate) {
            $query->where('invoice_date', '<=', $toDate);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('invoice_number', 'like', "%{$search}%")
                    ->orWhere('supplier_name', 'like', "%{$search}%")
                    ->orWhere('notes', 'like', "%{$search}%");
            });
        }

        // Apply sorting (same logic as index)
        $sortField = $request->get('sort', 'invoice_date');
        $sortDirection = $request->get('direction', 'desc');

        $allowedSortFields = [
            'invoice_number', 'supplier_name', 'invoice_date',
            'payment_status', 'payment_date', 'subtotal',
            'vat_amount', 'total_amount',
        ];

        if (! in_array($sortField, $allowedSortFields)) {
            $sortField = 'invoice_date';
        }
        if (! in_array($sortDirection, ['asc', 'desc'])) {
            $sortDirection = 'desc';
        }

        // Apply sorting
        if ($sortField === 'payment_date') {
            if ($sortDirection === 'desc') {
                $invoices = $query->orderByRaw('payment_date IS NULL ASC, payment_date DESC')
                    ->orderBy('id', 'desc')
                    ->get();
            } else {
                $invoices = $query->orderByRaw('payment_date IS NULL ASC, payment_date ASC')
                    ->orderBy('id', 'desc')
                    ->get();
            }
        } else {
            $invoices = $query->orderBy($sortField, $sortDirection)
                ->orderBy('id', 'desc')
                ->get();
        }

        // Build stats query with same filters (but without eager loading)
        $statsQuery = Invoice::query();
        if ($request->filled('supplier_id')) {
            $statsQuery->where('supplier_id', $request->supplier_id);
        }
        if ($request->filled('payment_status')) {
            if ($request->payment_status === 'unpaid') {
                $statsQuery->whereIn('payment_status', ['pending', 'overdue', 'partial']);
            } else {
                $statsQuery->where('payment_status', $request->payment_status);
            }
        }
        $statsQuery->where('invoice_date', '>=', $fromDate);
        if ($toDate) {
            $statsQuery->where('invoice_date', '<=', $toDate);
        }
        if ($request->filled('search')) {
            $search = $request->search;
            $statsQuery->where(function ($q) use ($search) {
                $q->where('invoice_number', 'like', "%{$search}%")
                    ->orWhere('supplier_name', 'like', "%{$search}%")
                    ->orWhere('notes', 'like', "%{$search}%");
            });
        }

        // Calculate statistics using optimized repository
        $filteredStats = $this->invoiceRepository->getFilteredStatistics($statsQuery);
        $stats = $this->invoiceRepository->getOverallStatistics();
        $monthlyTotals = $this->invoiceRepository->getMonthlyTotals();

        // Generate filename with current date
        $filename = 'invoices_'.now()->format('Y-m-d').'.csv';

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        $callback = function () use ($invoices, $stats, $filteredStats, $monthlyTotals, $request, $fromDate, $toDate) {
            $file = fopen('php://output', 'w');

            // Header section
            fputcsv($file, ['Invoices Export']);
            fputcsv($file, ['Generated:', now()->format('M j, Y H:i:s')]);

            // Show applied filters
            $activeFilters = [];
            if ($request->filled('supplier_id')) {
                $supplier = \App\Models\AccountingSupplier::find($request->supplier_id);
                $activeFilters[] = 'Supplier: '.($supplier ? $supplier->name : 'Unknown');
            }
            if ($request->filled('payment_status')) {
                $activeFilters[] = 'Status: '.ucfirst($request->payment_status);
            }
            if ($request->filled('from_date') || $fromDate !== '2025-01-01') {
                $activeFilters[] = 'From: '.\Carbon\Carbon::parse($fromDate)->format('M j, Y');
            }
            if ($request->filled('to_date')) {
                $activeFilters[] = 'To: '.\Carbon\Carbon::parse($toDate)->format('M j, Y');
            }
            if ($request->filled('search')) {
                $activeFilters[] = 'Search: '.$request->search;
            }

            if (! empty($activeFilters)) {
                fputcsv($file, ['Filters Applied:', implode(', ', $activeFilters)]);
            } else {
                fputcsv($file, ['Filters Applied: None']);
            }

            fputcsv($file, []); // Empty row

            // Overall statistics section
            fputcsv($file, ['OVERALL STATISTICS']);
            fputcsv($file, ['Total Unpaid:', '€'.number_format($stats['total_unpaid'], 2), '('.$stats['count_unpaid'].' invoices)']);
            fputcsv($file, ['Overdue:', '€'.number_format($stats['total_overdue'], 2), '('.$stats['count_overdue'].' invoices)']);
            fputcsv($file, ['This Month:', '€'.number_format($monthlyTotals['this_month'], 2)]);
            fputcsv($file, ['Last Month:', '€'.number_format($monthlyTotals['last_month'], 2)]);
            fputcsv($file, []); // Empty row

            // Filtered results section (if filters are active)
            if (! empty($activeFilters)) {
                fputcsv($file, ['FILTERED RESULTS']);
                fputcsv($file, ['Total Invoices:', $filteredStats['total_count']]);
                fputcsv($file, ['Total Amount:', '€'.number_format($filteredStats['total_amount'], 2)]);
                fputcsv($file, ['Total Net:', '€'.number_format($filteredStats['total_subtotal'], 2)]);
                fputcsv($file, ['Total VAT:', '€'.number_format($filteredStats['total_vat'], 2)]);
                fputcsv($file, ['Paid Invoices:', $filteredStats['paid_count']]);
                fputcsv($file, ['Unpaid Invoices:', $filteredStats['unpaid_count'], '(€'.number_format($filteredStats['unpaid_total'], 2).')']);
                fputcsv($file, []); // Empty row
            }

            // Invoice table header
            fputcsv($file, ['INVOICES']);
            fputcsv($file, [
                'Invoice #',
                'Supplier',
                'Date',
                'Status',
                'Paid On',
                'Net',
                'VAT',
                'Total',
                'Payment Method',
                'Payment Reference',
                'Due Date',
                'Notes',
            ]);

            // Invoice data rows
            foreach ($invoices as $invoice) {
                fputcsv($file, [
                    $invoice->invoice_number,
                    $invoice->supplier_name,
                    $invoice->invoice_date->format('Y-m-d'),
                    ucfirst($invoice->payment_status),
                    $invoice->payment_date ? $invoice->payment_date->format('Y-m-d') : '',
                    number_format($invoice->subtotal, 2),
                    number_format($invoice->vat_amount, 2),
                    number_format($invoice->total_amount, 2),
                    $invoice->payment_method ?? '',
                    $invoice->payment_reference ?? '',
                    $invoice->due_date ? $invoice->due_date->format('Y-m-d') : '',
                    $invoice->notes ?? '',
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Handle invoice document attachment upload
     */
    private function handleInvoiceAttachment(Invoice $invoice, $uploadedFile): void
    {
        try {
            // Generate unique filename
            $originalName = $uploadedFile->getClientOriginalName();
            $extension = $uploadedFile->getClientOriginalExtension();
            $filename = pathinfo($originalName, PATHINFO_FILENAME).'_'.time().'.'.$extension;

            // Create storage path using config pattern
            $invoiceDate = Carbon::parse($invoice->invoice_date);
            $storagePath = str_replace(
                ['{year}', '{month}', '{invoice_id}'],
                [$invoiceDate->format('Y'), $invoiceDate->format('m'), $invoice->id],
                config('invoices.storage.path_pattern')
            );

            $fullPath = $storagePath.'/'.$filename;

            // Store the file
            $uploadedFile->storeAs(
                dirname($fullPath),
                $filename,
                config('invoices.storage.disk')
            );

            // Fix permissions for the created directory and file
            $this->fixAttachmentPermissions($fullPath);

            // Generate file hash for integrity checking
            $fileContent = $uploadedFile->get();
            $fileHash = hash('sha256', $fileContent);

            // Create attachment record
            $invoice->attachments()->create([
                'original_filename' => $originalName,
                'stored_filename' => $filename,
                'file_path' => $fullPath,
                'mime_type' => $uploadedFile->getMimeType(),
                'file_size' => $uploadedFile->getSize(),
                'file_hash' => $fileHash,
                'attachment_type' => 'invoice_scan',
                'is_primary' => true,
                'uploaded_by' => auth()->id(),
                'uploaded_at' => now(),
            ]);

            Log::info('Invoice attachment uploaded successfully', [
                'invoice_id' => $invoice->id,
                'filename' => $originalName,
                'path' => $fullPath,
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to upload invoice attachment', [
                'invoice_id' => $invoice->id,
                'filename' => $uploadedFile->getClientOriginalName(),
                'error' => $e->getMessage(),
            ]);

            // Don't fail the entire invoice creation, just log the error
            // The invoice will be created without the attachment
        }
    }

    /**
     * Fix permissions for attachment files and directories
     */
    private function fixAttachmentPermissions(string $filePath): void
    {
        try {
            // Get full system paths
            $fullStoragePath = Storage::disk(config('invoices.storage.disk'))->path($filePath);
            $directory = dirname($fullStoragePath);

            // Fix directory permissions recursively
            $this->fixDirectoryPermissions($directory);

            // Fix file permissions
            if (file_exists($fullStoragePath)) {
                chmod($fullStoragePath, 0664);
                try {
                    chgrp($fullStoragePath, 'www-data');
                } catch (\Exception $e) {
                    Log::debug('Could not change file group ownership', [
                        'file' => $fullStoragePath,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

        } catch (\Exception $e) {
            Log::warning('Failed to set permissions for invoice attachment', [
                'path' => $filePath,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Fix directory permissions recursively
     */
    private function fixDirectoryPermissions(string $directory): void
    {
        try {
            // Set directory permissions (775 = rwxrwxr-x)
            chmod($directory, 0775);

            // Try to set group ownership if possible
            try {
                chgrp($directory, 'www-data');
            } catch (\Exception $e) {
                Log::debug('Could not change directory group ownership', [
                    'directory' => $directory,
                    'error' => $e->getMessage(),
                ]);
            }

            // Also ensure parent directories have correct permissions
            $parentDir = dirname($directory);
            if (is_dir($parentDir) && $parentDir !== $directory) {
                // Only process invoices subdirectories, not higher-level storage dirs
                if (strpos($parentDir, '/invoices/') !== false) {
                    $this->fixDirectoryPermissions($parentDir);
                }
            }

        } catch (\Exception $e) {
            Log::debug('Could not fix directory permissions', [
                'directory' => $directory,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Check for duplicate invoices based on supplier, date, and amount.
     *
     * Duplicates are defined as invoices with:
     * - Same supplier (by ID or name)
     * - Invoice date within ±1 day
     * - Total amount within ±€0.50
     *
     * @param  int|null  $supplierId
     * @param  string  $supplierName
     * @param  string  $invoiceDate
     * @param  float  $totalAmount
     * @return \Illuminate\Database\Eloquent\Collection
     */
    private function checkForDuplicates($supplierId, $supplierName, $invoiceDate, $totalAmount)
    {
        $date = Carbon::parse($invoiceDate);
        $dateStart = $date->copy()->subDay();
        $dateEnd = $date->copy()->addDay();

        $amountMin = $totalAmount - 0.50;
        $amountMax = $totalAmount + 0.50;

        $query = Invoice::query();

        // Match by supplier ID if available, otherwise by supplier name
        if ($supplierId) {
            $query->where('supplier_id', $supplierId);
        } else {
            $query->where('supplier_name', $supplierName);
        }

        // Match by date range (±1 day)
        $query->whereBetween('invoice_date', [$dateStart, $dateEnd]);

        // Match by amount range (±€0.50)
        $query->whereBetween('total_amount', [$amountMin, $amountMax]);

        return $query->get();
    }
}
