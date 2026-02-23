<?php

namespace App\Http\Controllers;

use App\Models\AccountingSupplier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
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

        // Filter by VAT status
        $vatStatus = $request->get('vat_status');
        if ($vatStatus === 'has_vat') {
            $query->whereNotNull('vat_treatment');
        } elseif ($vatStatus === 'missing_vat') {
            $query->whereNull('vat_treatment');
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

        if ($vatStatus === 'has_vat') {
            $filteredQuery->whereNotNull('vat_treatment');
        } elseif ($vatStatus === 'missing_vat') {
            $filteredQuery->whereNull('vat_treatment');
        }

        // Get stats based on filtered results
        $stats = [
            'total' => $filteredQuery->count(),
            'active' => (clone $filteredQuery)->where('status', 'active')->count(),
            'pos_linked' => (clone $filteredQuery)->where('is_pos_linked', true)->count(),
            'total_spent' => (clone $filteredQuery)->sum('total_spent'),
            'has_vat' => (clone $filteredQuery)->whereNotNull('vat_treatment')->count(),
        ];

        return view('suppliers.index', compact(
            'suppliers',
            'supplierTypes',
            'statuses',
            'stats',
            'search',
            'type',
            'status',
            'vatStatus',
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
        $vatTreatments = AccountingSupplier::VAT_TREATMENTS;
        $rtdClassifications = AccountingSupplier::RTD_CLASSIFICATIONS;
        $countryCodes = $this->getCountryCodes();

        return view('suppliers.create', compact(
            'supplierTypes',
            'statuses',
            'paymentMethods',
            'vatTreatments',
            'rtdClassifications',
            'countryCodes'
        ));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'code' => 'nullable|string|max:50|unique:accounting_suppliers,code',
            'create_in_pos' => 'boolean',
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
            // VAT classification fields
            'country_code' => 'nullable|string|size:2',
            'vat_treatment' => 'nullable|in:irish_vat,eu_goods_zero_rated,eu_reverse_charge_services,postponed_import,outside_scope_or_exempt',
            // RTD classification
            'rtd_classification' => 'nullable|in:goods_simple,goods_parser,service_overhead,not_applicable',
        ]);

        // Auto-generate code if not provided
        if (empty($validated['code'])) {
            $validated['code'] = $this->generateSupplierCode();
        }

        // Set default payment terms if not provided
        if (! isset($validated['payment_terms_days']) || is_null($validated['payment_terms_days'])) {
            $validated['payment_terms_days'] = 30; // Default 30 days
        }

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
            DB::beginTransaction();

            $supplier = AccountingSupplier::create($validated);

            // Create POS supplier if requested
            $createInPos = $request->boolean('create_in_pos');
            if ($createInPos) {
                $this->createPosSupplier($supplier);
            }

            DB::commit();

            $message = 'Supplier created successfully.';
            if ($createInPos) {
                $message .= ' Also created in POS system.';
            }

            return redirect()
                ->route('suppliers.show', $supplier)
                ->with('success', $message);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to create supplier', [
                'error' => $e->getMessage(),
                'data' => $validated,
            ]);

            $errorMessage = 'Failed to create supplier.';

            // Provide more specific error messages
            if (str_contains($e->getMessage(), 'payment_terms_days')) {
                $errorMessage .= ' Payment terms issue detected.';
            } elseif (str_contains($e->getMessage(), 'Duplicate entry')) {
                $errorMessage .= ' This supplier code already exists.';
            } elseif (str_contains($e->getMessage(), 'code')) {
                $errorMessage .= ' Supplier code issue detected.';
            } elseif (str_contains($e->getMessage(), 'connection')) {
                $errorMessage .= ' Database connection issue.';
            } elseif (str_contains($e->getMessage(), 'POS')) {
                $errorMessage .= ' POS system connection issue.';
            }

            return back()
                ->withInput()
                ->with('error', $errorMessage.' Please try again.');
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
        $vatTreatments = AccountingSupplier::VAT_TREATMENTS;
        $rtdClassifications = AccountingSupplier::RTD_CLASSIFICATIONS;
        $countryCodes = $this->getCountryCodes();

        return view('suppliers.edit', compact(
            'supplier',
            'supplierTypes',
            'statuses',
            'paymentMethods',
            'vatTreatments',
            'rtdClassifications',
            'countryCodes'
        ));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, AccountingSupplier $supplier)
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:50', Rule::unique('accounting_suppliers', 'code')->ignore($supplier)],
            'create_in_pos' => 'boolean',
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
            // VAT classification fields
            'country_code' => 'nullable|string|size:2',
            'vat_treatment' => 'nullable|in:irish_vat,eu_goods_zero_rated,eu_reverse_charge_services,postponed_import,outside_scope_or_exempt',
            // RTD classification
            'rtd_classification' => 'nullable|in:goods_simple,goods_parser,service_overhead,not_applicable',
        ]);

        // Process tags
        if ($validated['tags']) {
            $tags = array_map('trim', explode(',', $validated['tags']));
            $validated['tags'] = array_filter($tags);
        } else {
            $validated['tags'] = null;
        }

        // Set default payment terms if not provided
        if (! isset($validated['payment_terms_days']) || is_null($validated['payment_terms_days'])) {
            $validated['payment_terms_days'] = 30; // Default 30 days
        }

        // Update audit fields
        $validated['updated_by'] = Auth::id();
        $validated['is_active'] = $validated['status'] === 'active';

        try {
            DB::beginTransaction();

            $wasLinked = $supplier->is_pos_linked;
            $oldName = $supplier->name;

            $supplier->update($validated);

            // Sync name to POS if already linked and name changed
            if ($wasLinked && $oldName !== $validated['name']) {
                $supplier->syncNameToPos();
            }

            // Create POS supplier if requested and not already linked
            $createInPos = $request->boolean('create_in_pos');
            if ($createInPos && ! $wasLinked) {
                $this->createPosSupplier($supplier->fresh()); // Fresh to get updated data
            }

            DB::commit();

            $message = 'Supplier updated successfully.';
            if ($createInPos && ! $supplier->wasRecentlyLinkedToPos) {
                $message .= ' Also created in POS system.';
            }

            return redirect()
                ->route('suppliers.show', $supplier)
                ->with('success', $message);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to update supplier', [
                'supplier_id' => $supplier->id,
                'error' => $e->getMessage(),
                'data' => $validated,
            ]);

            $errorMessage = 'Failed to update supplier.';

            // Provide more specific error messages
            if (str_contains($e->getMessage(), 'payment_terms_days')) {
                $errorMessage .= ' Payment terms issue detected.';
            } elseif (str_contains($e->getMessage(), 'Duplicate entry')) {
                $errorMessage .= ' This supplier code already exists.';
            } elseif (str_contains($e->getMessage(), 'code')) {
                $errorMessage .= ' Supplier code issue detected.';
            } elseif (str_contains($e->getMessage(), 'connection')) {
                $errorMessage .= ' Database connection issue.';
            } elseif (str_contains($e->getMessage(), 'POS')) {
                $errorMessage .= ' POS system connection issue.';
            }

            return back()
                ->withInput()
                ->with('error', $errorMessage.' Please try again.');
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
                    'supplier_id' => $supplier->id,
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
                    'message' => 'Failed to update supplier status. Please try again.',
                ], 422);
            }

            return back()->with('error', 'Failed to update supplier status. Please try again.');
        }
    }

    /**
     * Update VAT classification fields via AJAX.
     */
    public function updateVatClassification(Request $request, AccountingSupplier $supplier)
    {
        $validated = $request->validate([
            'country_code' => 'nullable|string|size:2',
            'vat_treatment' => 'nullable|in:irish_vat,eu_goods_zero_rated,eu_reverse_charge_services,postponed_import,outside_scope_or_exempt',
        ]);

        try {
            // If country_code changed and vat_treatment not explicitly set, auto-infer it
            if (isset($validated['country_code']) && ! isset($validated['vat_treatment'])) {
                $validated['vat_treatment'] = AccountingSupplier::inferVatTreatment($validated['country_code']);
                $validated['is_eu_supplier'] = AccountingSupplier::isEuCountry($validated['country_code']);
            }

            $validated['updated_by'] = Auth::id();

            $supplier->update($validated);

            Log::info('Supplier VAT classification updated', [
                'supplier_id' => $supplier->id,
                'supplier_name' => $supplier->name,
                'changes' => $validated,
                'updated_by' => Auth::id(),
            ]);

            return response()->json([
                'success' => true,
                'message' => "VAT classification updated for '{$supplier->name}'.",
                'supplier' => [
                    'id' => $supplier->id,
                    'country_code' => $supplier->country_code,
                    'vat_treatment' => $supplier->vat_treatment,
                    'is_eu_supplier' => $supplier->is_eu_supplier,
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to update supplier VAT classification', [
                'supplier_id' => $supplier->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update VAT classification. Please try again.',
            ], 422);
        }
    }

    /**
     * Update RTD classification field via AJAX.
     */
    public function updateRtdClassification(Request $request, AccountingSupplier $supplier)
    {
        $validated = $request->validate([
            'rtd_classification' => 'required|in:goods_simple,goods_parser,service_overhead,not_applicable',
        ]);

        try {
            $validated['updated_by'] = Auth::id();
            $supplier->update($validated);

            Log::info('Supplier RTD classification updated', [
                'supplier_id' => $supplier->id,
                'supplier_name' => $supplier->name,
                'rtd_classification' => $validated['rtd_classification'],
                'updated_by' => Auth::id(),
            ]);

            return response()->json([
                'success' => true,
                'message' => "RTD classification updated for '{$supplier->name}'.",
                'supplier' => [
                    'id' => $supplier->id,
                    'rtd_classification' => $supplier->rtd_classification,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to update supplier RTD classification', [
                'supplier_id' => $supplier->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update RTD classification. Please try again.',
            ], 422);
        }
    }

    /**
     * Generate a unique supplier code.
     */
    private function generateSupplierCode(): string
    {
        // Get the highest existing SUP- code number
        $lastSupplier = AccountingSupplier::where('code', 'LIKE', 'SUP-%')
            ->orderByRaw('CAST(SUBSTRING(code, 5) AS UNSIGNED) DESC')
            ->first();

        if ($lastSupplier) {
            $lastNumber = (int) substr($lastSupplier->code, 4);
            $nextNumber = $lastNumber + 1;
        } else {
            $nextNumber = 1;
        }

        return 'SUP-'.str_pad($nextNumber, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Create a supplier in the POS system.
     */
    private function createPosSupplier(AccountingSupplier $supplier): void
    {
        try {
            // Generate POS ID using Laravel supplier ID
            $posId = 'SUP'.str_pad($supplier->id, 6, '0', STR_PAD_LEFT);

            // Check if POS ID already exists
            $existingPos = DB::connection('pos')->table('suppliers')
                ->where('SupplierID', $posId)
                ->exists();

            if ($existingPos) {
                // Try alternative ID with timestamp suffix
                $posId = 'SUP'.str_pad($supplier->id, 6, '0', STR_PAD_LEFT).now()->format('His');
            }

            // Create in POS database
            DB::connection('pos')->table('suppliers')->insert([
                'SupplierID' => $posId,
                'Supplier' => $supplier->name,
            ]);

            // Update Laravel supplier with POS link
            $supplier->update([
                'external_pos_id' => $posId,
                'is_pos_linked' => true,
            ]);

            Log::info('Created POS supplier', [
                'supplier_id' => $supplier->id,
                'pos_id' => $posId,
                'name' => $supplier->name,
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to create POS supplier', [
                'supplier_id' => $supplier->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Get list of country codes for dropdown selection.
     * Returns common countries first (IE, GB, EU), then others alphabetically.
     */
    private function getCountryCodes(): array
    {
        return [
            // Ireland first (most common)
            'IE' => 'Ireland (IE)',
            // UK
            'GB' => 'United Kingdom (GB)',
            // EU countries (alphabetical)
            'AT' => 'Austria (AT)',
            'BE' => 'Belgium (BE)',
            'BG' => 'Bulgaria (BG)',
            'HR' => 'Croatia (HR)',
            'CY' => 'Cyprus (CY)',
            'CZ' => 'Czech Republic (CZ)',
            'DK' => 'Denmark (DK)',
            'EE' => 'Estonia (EE)',
            'FI' => 'Finland (FI)',
            'FR' => 'France (FR)',
            'DE' => 'Germany (DE)',
            'GR' => 'Greece (GR)',
            'HU' => 'Hungary (HU)',
            'IT' => 'Italy (IT)',
            'LV' => 'Latvia (LV)',
            'LT' => 'Lithuania (LT)',
            'LU' => 'Luxembourg (LU)',
            'MT' => 'Malta (MT)',
            'NL' => 'Netherlands (NL)',
            'PL' => 'Poland (PL)',
            'PT' => 'Portugal (PT)',
            'RO' => 'Romania (RO)',
            'SK' => 'Slovakia (SK)',
            'SI' => 'Slovenia (SI)',
            'ES' => 'Spain (ES)',
            'SE' => 'Sweden (SE)',
            // Non-EU common trading partners
            'US' => 'United States (US)',
            'CA' => 'Canada (CA)',
            'CH' => 'Switzerland (CH)',
            'NO' => 'Norway (NO)',
            'AU' => 'Australia (AU)',
            'NZ' => 'New Zealand (NZ)',
            'CN' => 'China (CN)',
            'JP' => 'Japan (JP)',
        ];
    }
}
