<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\RtdVatFallback;
use App\Services\RtdResolutionService;
use Illuminate\Http\Request;

class RtdFallbackController extends Controller
{
    /**
     * List all fallback entries.
     */
    public function index(Request $request)
    {
        $search = $request->get('search');

        $fallbacks = RtdVatFallback::with(['creator', 'supplier'])
            ->when($search, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('article_code', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%")
                        ->orWhereHas('supplier', function ($sq) use ($search) {
                            $sq->where('name', 'like', "%{$search}%");
                        });
                });
            })
            ->orderBy('article_code')
            ->paginate(50)
            ->appends(['search' => $search]);

        return view('rtd-fallbacks.index', compact('fallbacks', 'search'));
    }

    /**
     * Show unresolved items aggregated across non-frozen invoices.
     * Can be filtered to a specific invoice via ?invoice_id=X
     */
    public function unresolved(Request $request)
    {
        $invoiceId = $request->get('invoice_id');
        $filteredInvoice = null;

        // Build query for non-frozen invoices with RTD resolution issues
        $query = Invoice::with('supplier')
            ->whereNotNull('rtd_resolution_issues')
            ->where('rtd_status', '!=', 'frozen');

        // Filter to specific invoice if requested
        if ($invoiceId) {
            $query->where('id', $invoiceId);
            $filteredInvoice = Invoice::with('supplier')->find($invoiceId);
        }

        $invoices = $query->get();

        // Get supplier from first invoice (they all share same supplier for RTD)
        $supplier = $invoices->first()?->supplier;
        $supplierId = $supplier?->id;

        // Get all unresolved items
        $unresolvedItems = $invoices
            ->flatMap(fn ($inv) => collect($inv->rtd_resolution_issues ?? [])->map(fn ($issue) => array_merge($issue, ['invoice_id' => $inv->id])))
            ->filter(fn ($issue) => ($issue['reason'] ?? '') === 'no_product_match')
            ->groupBy('article_code')
            ->map(function ($items) {
                return [
                    'article_code' => $items->first()['article_code'],
                    'description' => $items->first()['description'] ?? '',
                    'total_value' => round($items->sum('line_total'), 2),
                    'occurrence_count' => $items->count(),
                    'invoice_ids' => $items->pluck('invoice_id')->unique()->values()->toArray(),
                ];
            })
            ->sortBy('article_code')
            ->values();

        // Get existing fallbacks to show which are already assigned
        $existingFallbacks = $supplierId
            ? RtdVatFallback::forSupplier($supplierId)->get()->keyBy('article_code')->map(fn ($f) => [
                'vat_rate' => $f->vat_rate,
                'is_non_retail' => $f->is_non_retail,
            ])->toArray()
            : [];

        return view('rtd-fallbacks.unresolved', compact('unresolvedItems', 'existingFallbacks', 'supplierId', 'supplier', 'filteredInvoice'));
    }

    /**
     * Bulk assign VAT rate to selected article codes.
     */
    public function bulkAssign(Request $request)
    {
        $validated = $request->validate([
            'article_codes' => 'required|array|min:1',
            'article_codes.*' => 'required|string|max:50',
            'vat_rate' => 'required|in:0,9,13.5,23',
            'supplier_id' => 'required|exists:accounting_suppliers,id',
            'descriptions' => 'nullable|array',
            'is_non_retail' => 'sometimes|boolean',
        ]);

        $supplierId = $validated['supplier_id'];
        $isNonRetail = (bool) ($validated['is_non_retail'] ?? false);

        $created = 0;
        $updated = 0;

        foreach ($validated['article_codes'] as $code) {
            $existing = RtdVatFallback::where('article_code', $code)
                ->where('supplier_id', $supplierId)
                ->first();

            RtdVatFallback::updateOrCreate(
                ['article_code' => $code, 'supplier_id' => $supplierId],
                [
                    'vat_rate' => $validated['vat_rate'],
                    'is_non_retail' => $isNonRetail,
                    'description' => $validated['descriptions'][$code] ?? null,
                    'created_by' => $existing ? $existing->created_by : auth()->id(),
                    'updated_by' => auth()->id(),
                ]
            );

            if ($existing) {
                $updated++;
            } else {
                $created++;
            }
        }

        $message = '';
        if ($created > 0) {
            $message .= "{$created} new fallback entries created. ";
        }
        if ($updated > 0) {
            $message .= "{$updated} existing entries updated.";
        }

        return back()->with('success', trim($message));
    }

    /**
     * Recompute RTD for all affected invoices (those with unresolved issues).
     */
    public function recomputeAffected(RtdResolutionService $rtdService)
    {
        $invoices = Invoice::whereNotNull('rtd_resolution_issues')
            ->where('rtd_status', '!=', 'frozen')
            ->get();

        $recomputed = 0;
        $improved = 0;

        foreach ($invoices as $invoice) {
            $previousUnresolved = $invoice->rtd_breakdown['unresolved']['count'] ?? 0;

            $result = $rtdService->computeRtd($invoice);
            $invoice->update([
                'rtd_breakdown' => $result['breakdown'],
                'rtd_resolution_issues' => $result['issues'],
                'rtd_status' => 'computed',
                'rtd_computed_at' => now(),
            ]);

            $recomputed++;

            $newUnresolved = $result['breakdown']['unresolved']['count'] ?? 0;
            if ($newUnresolved < $previousUnresolved) {
                $improved++;
            }
        }

        return back()->with('success', "{$recomputed} invoices recomputed. {$improved} invoices have fewer unresolved items.");
    }

    /**
     * Delete a fallback entry.
     */
    public function destroy(RtdVatFallback $fallback)
    {
        $articleCode = $fallback->article_code;
        $fallback->delete();

        return back()->with('success', "Fallback entry for article code '{$articleCode}' deleted.");
    }

    /**
     * Edit a single fallback entry.
     */
    public function update(Request $request, RtdVatFallback $fallback)
    {
        $validated = $request->validate([
            'vat_rate' => 'required|in:0,9,13.5,23',
            'description' => 'nullable|string|max:100',
            'notes' => 'nullable|string|max:500',
            'is_non_retail' => 'sometimes|boolean',
        ]);

        $fallback->update([
            'vat_rate' => $validated['vat_rate'],
            'is_non_retail' => (bool) ($validated['is_non_retail'] ?? false),
            'description' => $validated['description'],
            'notes' => $validated['notes'],
            'updated_by' => auth()->id(),
        ]);

        return back()->with('success', "Fallback entry for '{$fallback->article_code}' updated.");
    }
}
