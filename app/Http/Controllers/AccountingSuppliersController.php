<?php

namespace App\Http\Controllers;

use App\Models\AccountingSupplier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class AccountingSuppliersController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = AccountingSupplier::query()
            ->with(['creator', 'updater'])
            ->withCount('invoices');

        // Extract filter parameters for use in view
        $search = $request->get('search');
        $type = $request->get('type');
        $status = $request->get('status');

        // Search functionality
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'LIKE', "%{$search}%")
                  ->orWhere('code', 'LIKE', "%{$search}%")
                  ->orWhere('email', 'LIKE', "%{$search}%")
                  ->orWhere('phone', 'LIKE', "%{$search}%")
                  ->orWhere('contact_person', 'LIKE', "%{$search}%");
            });
        }

        // Filter by type
        if ($type) {
            $query->where('supplier_type', $type);
        }

        // Filter by status
        if ($status) {
            $query->where('status', $status);
        }

        // Filter by POS linked
        if ($request->filled('pos_linked')) {
            $posLinked = $request->boolean('pos_linked');
            $query->where('is_pos_linked', $posLinked);
        }

        // Sort by
        $sortBy = $request->get('sort', 'name');
        $sortDirection = $request->get('direction', 'asc');
        
        $allowedSorts = ['name', 'code', 'supplier_type', 'status', 'total_spent', 'invoice_count', 'last_invoice_date', 'created_at'];
        if (in_array($sortBy, $allowedSorts)) {
            $query->orderBy($sortBy, $sortDirection);
        }

        // Debug: Log query before pagination
        if ($request->hasAny(['type', 'status', 'pos_linked', 'search'])) {
            \Log::info('=== SUPPLIER FILTER DEBUG - BEFORE PAGINATION ===', [
                'request_method' => $request->method(),
                'request_url' => $request->fullUrl(),
                'all_params' => $request->all(),
                'extracted_values' => [
                    'search' => $search,
                    'type' => $type,
                    'status' => $status,
                    'pos_linked' => $request->get('pos_linked'),
                ],
                'query_sql' => $query->toSql(),
                'query_bindings' => $query->getBindings(),
                'total_count_before_pagination' => $query->count(),
            ]);
        }

        $suppliers = $query->paginate(25)->withQueryString();

        // Debug: Log results after pagination
        if ($request->hasAny(['type', 'status', 'pos_linked', 'search'])) {
            \Log::info('=== SUPPLIER FILTER DEBUG - AFTER PAGINATION ===', [
                'results_count_current_page' => $suppliers->count(),
                'results_total_all_pages' => $suppliers->total(),
                'current_page' => $suppliers->currentPage(),
                'per_page' => $suppliers->perPage(),
            ]);
        }

        // Get filter options for dropdowns
        $supplierTypes = AccountingSupplier::distinct()
            ->pluck('supplier_type')
            ->filter()
            ->sort()
            ->values();

        $statuses = AccountingSupplier::distinct()
            ->pluck('status')
            ->filter()
            ->sort()
            ->values();

        // Get stats for the current filtered query (before pagination)
        $filteredQuery = AccountingSupplier::query();
        
        // Apply same filters to stats query
        if ($search) {
            $filteredQuery->where(function ($q) use ($search) {
                $q->where('name', 'LIKE', "%{$search}%")
                  ->orWhere('code', 'LIKE', "%{$search}%")
                  ->orWhere('email', 'LIKE', "%{$search}%")
                  ->orWhere('phone', 'LIKE', "%{$search}%")
                  ->orWhere('contact_person', 'LIKE', "%{$search}%");
            });
        }

        if ($type) {
            $filteredQuery->where('supplier_type', $type);
        }

        if ($status) {
            $filteredQuery->where('status', $status);
        }

        if ($request->filled('pos_linked')) {
            $posLinked = $request->boolean('pos_linked');
            $filteredQuery->where('is_pos_linked', $posLinked);
        }

        // Get stats based on filtered results
        $stats = [
            'total' => $filteredQuery->count(),
            'active' => (clone $filteredQuery)->where('status', 'active')->count(),
            'pos_linked' => (clone $filteredQuery)->where('is_pos_linked', true)->count(),
            'total_spent' => (clone $filteredQuery)->sum('total_spent'),
        ];



        return view('suppliers.index', compact(
            'suppliers',
            'supplierTypes',
            'statuses',
            'stats',
            'search',
            'type',
            'status',
            'sortBy',
            'sortDirection'
        ));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $supplierTypes = ['product', 'service', 'utility', 'professional', 'other'];
        $statuses = ['active', 'inactive', 'suspended', 'archived'];
        $paymentMethods = ['bacs', 'cheque', 'card', 'cash', 'other'];

        return view('suppliers.create', compact(
            'supplierTypes',
            'statuses',
            'paymentMethods'
        ));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'code' => 'required|string|max:50|unique:accounting_suppliers,code',
            'name' => 'required|string|max:255',
            'supplier_type' => 'required|in:product,service,utility,professional,other',
            'address' => 'nullable|string|max:1000',
            'phone' => 'nullable|string|max:20',
            'phone_secondary' => 'nullable|string|max:20',
            'fax' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'website' => 'nullable|url|max:255',
            'contact_person' => 'nullable|string|max:255',
            'vat_number' => 'nullable|string|max:50',
            'company_registration' => 'nullable|string|max:50',
            'tax_reference' => 'nullable|string|max:50',
            'default_vat_code' => 'nullable|string|max:20',
            'default_expense_category' => 'nullable|string|max:50',
            'payment_terms_days' => 'nullable|integer|min:0|max:365',
            'preferred_payment_method' => 'nullable|in:bacs,cheque,card,cash,other',
            'bank_account' => 'nullable|string|max:50',
            'sort_code' => 'nullable|string|max:20',
            'delivery_instructions' => 'nullable|string|max:1000',
            'status' => 'required|in:active,inactive,suspended,archived',
            'notes' => 'nullable|string|max:2000',
            'tags' => 'nullable|string',
        ]);

        // Process tags
        if ($validated['tags']) {
            $tags = array_map('trim', explode(',', $validated['tags']));
            $validated['tags'] = array_filter($tags);
        } else {
            $validated['tags'] = null;
        }

        // Set audit fields
        $validated['created_by'] = Auth::id();
        $validated['updated_by'] = Auth::id();
        $validated['is_active'] = $validated['status'] === 'active';

        try {
            $supplier = AccountingSupplier::create($validated);

            return redirect()
                ->route('suppliers.show', $supplier)
                ->with('success', 'Supplier created successfully.');

        } catch (\Exception $e) {
            Log::error('Failed to create supplier', [
                'error' => $e->getMessage(),
                'data' => $validated,
            ]);

            return back()
                ->withInput()
                ->with('error', 'Failed to create supplier. Please try again.');
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(AccountingSupplier $supplier)
    {
        $supplier->load(['creator', 'updater', 'invoices' => function ($query) {
            $query->latest()->take(10);
        }]);

        // Get recent invoices with summary
        $invoiceStats = $supplier->invoices()
            ->selectRaw('
                COUNT(*) as total_count,
                SUM(CASE WHEN payment_status IN (?, ?, ?) THEN total_amount ELSE 0 END) as total_owed,
                SUM(total_amount) as total_spent,
                MAX(invoice_date) as last_invoice_date
            ', ['pending', 'overdue', 'partial'])
            ->first();

        return view('suppliers.show', compact('supplier', 'invoiceStats'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(AccountingSupplier $supplier)
    {
        $supplierTypes = ['product', 'service', 'utility', 'professional', 'other'];
        $statuses = ['active', 'inactive', 'suspended', 'archived'];
        $paymentMethods = ['bacs', 'cheque', 'card', 'cash', 'other'];

        return view('suppliers.edit', compact(
            'supplier',
            'supplierTypes',
            'statuses',
            'paymentMethods'
        ));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, AccountingSupplier $supplier)
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:50', Rule::unique('accounting_suppliers', 'code')->ignore($supplier)],
            'name' => 'required|string|max:255',
            'supplier_type' => 'required|in:product,service,utility,professional,other',
            'address' => 'nullable|string|max:1000',
            'phone' => 'nullable|string|max:20',
            'phone_secondary' => 'nullable|string|max:20',
            'fax' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'website' => 'nullable|url|max:255',
            'contact_person' => 'nullable|string|max:255',
            'vat_number' => 'nullable|string|max:50',
            'company_registration' => 'nullable|string|max:50',
            'tax_reference' => 'nullable|string|max:50',
            'default_vat_code' => 'nullable|string|max:20',
            'default_expense_category' => 'nullable|string|max:50',
            'payment_terms_days' => 'nullable|integer|min:0|max:365',
            'preferred_payment_method' => 'nullable|in:bacs,cheque,card,cash,other',
            'bank_account' => 'nullable|string|max:50',
            'sort_code' => 'nullable|string|max:20',
            'delivery_instructions' => 'nullable|string|max:1000',
            'status' => 'required|in:active,inactive,suspended,archived',
            'notes' => 'nullable|string|max:2000',
            'tags' => 'nullable|string',
        ]);

        // Process tags
        if ($validated['tags']) {
            $tags = array_map('trim', explode(',', $validated['tags']));
            $validated['tags'] = array_filter($tags);
        } else {
            $validated['tags'] = null;
        }

        // Update audit fields
        $validated['updated_by'] = Auth::id();
        $validated['is_active'] = $validated['status'] === 'active';

        try {
            $supplier->update($validated);

            return redirect()
                ->route('suppliers.show', $supplier)
                ->with('success', 'Supplier updated successfully.');

        } catch (\Exception $e) {
            Log::error('Failed to update supplier', [
                'supplier_id' => $supplier->id,
                'error' => $e->getMessage(),
                'data' => $validated,
            ]);

            return back()
                ->withInput()
                ->with('error', 'Failed to update supplier. Please try again.');
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(AccountingSupplier $supplier)
    {
        // Check if supplier has invoices
        if ($supplier->invoices()->count() > 0) {
            return back()->with('error', 'Cannot delete supplier with existing invoices. Archive it instead.');
        }

        // Check if supplier is POS-linked
        if ($supplier->is_pos_linked) {
            return back()->with('error', 'Cannot delete POS-linked supplier. Archive it instead.');
        }

        try {
            $supplierName = $supplier->name;
            $supplier->delete();

            Log::info('Supplier deleted', [
                'supplier_id' => $supplier->id,
                'supplier_name' => $supplierName,
                'deleted_by' => Auth::id(),
            ]);

            return redirect()
                ->route('suppliers.index')
                ->with('success', "Supplier '{$supplierName}' deleted successfully.");

        } catch (\Exception $e) {
            Log::error('Failed to delete supplier', [
                'supplier_id' => $supplier->id,
                'error' => $e->getMessage(),
            ]);

            return back()->with('error', 'Failed to delete supplier. Please try again.');
        }
    }

    /**
     * Refresh supplier analytics from invoices.
     */
    public function refreshAnalytics(AccountingSupplier $supplier)
    {
        try {
            $supplier->refreshSpendAnalytics();

            return back()->with('success', 'Supplier analytics refreshed successfully.');

        } catch (\Exception $e) {
            Log::error('Failed to refresh supplier analytics', [
                'supplier_id' => $supplier->id,
                'error' => $e->getMessage(),
            ]);

            return back()->with('error', 'Failed to refresh analytics. Please try again.');
        }
    }

    /**
     * Toggle supplier active status.
     */
    public function toggleStatus(AccountingSupplier $supplier)
    {
        try {
            $newStatus = $supplier->status === 'active' ? 'inactive' : 'active';
            $supplier->update([
                'status' => $newStatus,
                'is_active' => $newStatus === 'active',
                'updated_by' => Auth::id(),
            ]);

            $statusText = $newStatus === 'active' ? 'activated' : 'deactivated';
            
            Log::info('Supplier status toggled', [
                'supplier_id' => $supplier->id,
                'supplier_name' => $supplier->name,
                'old_status' => $newStatus === 'active' ? 'inactive' : 'active',
                'new_status' => $newStatus,
                'updated_by' => Auth::id(),
            ]);

            // Return JSON response for AJAX requests
            if (request()->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => "Supplier '{$supplier->name}' has been {$statusText} successfully.",
                    'status' => $newStatus,
                    'supplier_id' => $supplier->id
                ]);
            }

            return back()->with('success', "Supplier '{$supplier->name}' has been {$statusText} successfully.");

        } catch (\Exception $e) {
            Log::error('Failed to toggle supplier status', [
                'supplier_id' => $supplier->id,
                'error' => $e->getMessage(),
            ]);

            // Return JSON response for AJAX requests
            if (request()->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to update supplier status. Please try again.'
                ], 422);
            }

            return back()->with('error', 'Failed to update supplier status. Please try again.');
        }
    }
}
