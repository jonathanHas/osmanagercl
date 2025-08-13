<?php

namespace App\Http\Controllers;

use App\Models\AccountingSupplier;
use App\Models\CostCategory;
use App\Models\Invoice;
use App\Models\InvoiceVatLine;
use App\Models\VatRate;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InvoiceController extends Controller
{
    /**
     * Display a listing of invoices.
     */
    public function index(Request $request)
    {
        $query = Invoice::with(['supplier', 'vatLines']);

        // Apply filters
        if ($request->filled('supplier_id')) {
            $query->where('supplier_id', $request->supplier_id);
        }

        if ($request->filled('payment_status')) {
            $query->where('payment_status', $request->payment_status);
        }

        if ($request->filled('from_date')) {
            $query->where('invoice_date', '>=', $request->from_date);
        }

        if ($request->filled('to_date')) {
            $query->where('invoice_date', '<=', $request->to_date);
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

        // Clone query for statistics before pagination
        $statsQuery = clone $query;

        $invoices = $query->orderBy($sortField, $sortDirection)
            ->orderBy('id', 'desc') // Secondary sort for consistency
            ->paginate(20);

        // Get suppliers for filter dropdown
        $suppliers = AccountingSupplier::activeOnly()
            ->orderBy('name')
            ->pluck('name', 'id');

        // Calculate summary statistics for filtered results (always calculate for consistency)
        $filteredStats = [
            'total_count' => $statsQuery->count(),
            'total_amount' => $statsQuery->sum('total_amount'),
            'total_subtotal' => $statsQuery->sum('subtotal'),
            'total_vat' => $statsQuery->sum('vat_amount'),
            'standard_net' => $statsQuery->sum('standard_net'),
            'standard_vat' => $statsQuery->sum('standard_vat'),
            'reduced_net' => $statsQuery->sum('reduced_net'),
            'reduced_vat' => $statsQuery->sum('reduced_vat'),
            'second_reduced_net' => $statsQuery->sum('second_reduced_net'),
            'second_reduced_vat' => $statsQuery->sum('second_reduced_vat'),
            'zero_net' => $statsQuery->sum('zero_net'),
            'paid_count' => (clone $statsQuery)->where('payment_status', 'paid')->count(),
            'unpaid_count' => (clone $statsQuery)->whereIn('payment_status', ['pending', 'overdue', 'partial'])->count(),
            'unpaid_total' => (clone $statsQuery)->whereIn('payment_status', ['pending', 'overdue', 'partial'])->sum('total_amount'),
        ];

        // Calculate overall statistics (unfiltered)
        $stats = [
            'total_unpaid' => Invoice::unpaid()->sum('total_amount'),
            'total_overdue' => Invoice::unpaid()
                ->where('due_date', '<', now())
                ->sum('total_amount'),
            'count_unpaid' => Invoice::unpaid()->count(),
            'count_overdue' => Invoice::unpaid()
                ->where('due_date', '<', now())
                ->count(),
        ];

        return view('invoices.index', compact('invoices', 'suppliers', 'stats', 'filteredStats', 'sortField', 'sortDirection'));
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
            'vat_lines.*.net_amount' => 'required|numeric|min:0',
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
            'invoice_number' => 'required|string|max:100',
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

        return redirect()->route('invoices.show', $invoice)
            ->with('success', 'Invoice created successfully.');
    }

    /**
     * Display the specified invoice.
     */
    public function show(Invoice $invoice)
    {
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
            'payment_status' => 'required|in:pending,partial,paid,overdue,cancelled',
            'payment_date' => 'nullable|required_if:payment_status,paid|date',
            'payment_method' => 'nullable|string|max:50',
            'payment_reference' => 'nullable|string|max:100',
            'expense_category' => 'nullable|string|max:50',
            'notes' => 'nullable|string',

            // VAT lines
            'vat_lines' => 'required|array|min:1',
            'vat_lines.*.id' => 'nullable|exists:invoice_vat_lines,id',
            'vat_lines.*.vat_category' => 'required|string|in:STANDARD,REDUCED,SECOND_REDUCED,ZERO',
            'vat_lines.*.net_amount' => 'required|numeric|min:0',
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
                'payment_status' => $validated['payment_status'],
                'payment_date' => $validated['payment_date'],
                'payment_method' => $validated['payment_method'],
                'payment_reference' => $validated['payment_reference'],
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

            // Recalculate totals
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
    public function destroy(Invoice $invoice)
    {
        try {
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
}
