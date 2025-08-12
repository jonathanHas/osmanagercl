<?php

namespace App\Http\Controllers\Management;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\VatReturn;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class VatReturnController extends Controller
{
    /**
     * Display a listing of VAT returns.
     */
    public function index()
    {
        $vatReturns = VatReturn::with('creator', 'finalizer')
            ->orderBy('period_end', 'desc')
            ->orderBy('created_at', 'desc')
            ->paginate(15);

        return view('management.vat-returns.index', compact('vatReturns'));
    }

    /**
     * Show the form for creating a new VAT return.
     */
    public function create(Request $request)
    {
        $endDate = $request->get('end_date') ? Carbon::parse($request->get('end_date')) : null;

        // Get unassigned invoices query
        $query = Invoice::with(['supplier', 'vatLines'])
            ->unassigned()
            ->orderBy('supplier_name')
            ->orderBy('invoice_date')
            ->orderBy('invoice_number');

        // Apply date filter if provided
        if ($endDate) {
            $query->upToDate($endDate);
        }

        $invoices = $query->get();

        // Group invoices by supplier
        $invoicesBySupplier = $invoices->groupBy('supplier_name');

        // Calculate totals by supplier
        $supplierTotals = [];
        foreach ($invoicesBySupplier as $supplierName => $supplierInvoices) {
            $supplierTotals[$supplierName] = [
                'zero_net' => $supplierInvoices->sum('zero_net'),
                'zero_vat' => $supplierInvoices->sum('zero_vat'),
                'second_reduced_net' => $supplierInvoices->sum('second_reduced_net'),
                'second_reduced_vat' => $supplierInvoices->sum('second_reduced_vat'),
                'reduced_net' => $supplierInvoices->sum('reduced_net'),
                'reduced_vat' => $supplierInvoices->sum('reduced_vat'),
                'standard_net' => $supplierInvoices->sum('standard_net'),
                'standard_vat' => $supplierInvoices->sum('standard_vat'),
                'total' => $supplierInvoices->sum('total_amount'),
            ];
        }

        // Calculate grand totals
        $grandTotals = [
            'zero_net' => $invoices->sum('zero_net'),
            'zero_vat' => $invoices->sum('zero_vat'),
            'second_reduced_net' => $invoices->sum('second_reduced_net'),
            'second_reduced_vat' => $invoices->sum('second_reduced_vat'),
            'reduced_net' => $invoices->sum('reduced_net'),
            'reduced_vat' => $invoices->sum('reduced_vat'),
            'standard_net' => $invoices->sum('standard_net'),
            'standard_vat' => $invoices->sum('standard_vat'),
            'total_net' => $invoices->sum('subtotal'),
            'total_vat' => $invoices->sum('vat_amount'),
            'total_gross' => $invoices->sum('total_amount'),
        ];

        return view('management.vat-returns.create', compact(
            'invoicesBySupplier',
            'supplierTotals',
            'grandTotals',
            'endDate',
            'invoices'
        ));
    }

    /**
     * Store a newly created VAT return (assign invoices).
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'return_period' => 'required|string|max:100|unique:vat_returns,return_period',
            'period_end' => 'required|date',
            'notes' => 'nullable|string',
            'invoice_ids' => 'required|array|min:1',
            'invoice_ids.*' => 'exists:invoices,id',
        ]);

        DB::beginTransaction();
        try {
            // Determine period start based on period end (assuming monthly returns)
            $periodEnd = Carbon::parse($validated['period_end']);
            $periodStart = $periodEnd->copy()->startOfMonth();

            // Create VAT return
            $vatReturn = VatReturn::create([
                'return_period' => $validated['return_period'],
                'period_start' => $periodStart,
                'period_end' => $periodEnd,
                'status' => 'draft',
                'notes' => $validated['notes'],
                'created_by' => auth()->id(),
            ]);

            // Assign invoices to this VAT return
            Invoice::whereIn('id', $validated['invoice_ids'])
                ->unassigned()
                ->update(['vat_return_id' => $vatReturn->id]);

            // Calculate totals
            $vatReturn->calculateTotals();

            DB::commit();

            return redirect()->route('management.vat-returns.show', $vatReturn)
                ->with('success', 'VAT return created successfully with '.count($validated['invoice_ids']).' invoices assigned.');

        } catch (\Exception $e) {
            DB::rollBack();

            return back()->withInput()
                ->with('error', 'Failed to create VAT return: '.$e->getMessage());
        }
    }

    /**
     * Display the specified VAT return.
     */
    public function show(VatReturn $vatReturn)
    {
        $vatReturn->load(['invoices.supplier', 'creator', 'finalizer']);

        // Group invoices by supplier
        $invoicesBySupplier = $vatReturn->invoices->groupBy('supplier_name');

        // Calculate totals by supplier
        $supplierTotals = [];
        foreach ($invoicesBySupplier as $supplierName => $supplierInvoices) {
            $supplierTotals[$supplierName] = [
                'zero_net' => $supplierInvoices->sum('zero_net'),
                'zero_vat' => $supplierInvoices->sum('zero_vat'),
                'second_reduced_net' => $supplierInvoices->sum('second_reduced_net'),
                'second_reduced_vat' => $supplierInvoices->sum('second_reduced_vat'),
                'reduced_net' => $supplierInvoices->sum('reduced_net'),
                'reduced_vat' => $supplierInvoices->sum('reduced_vat'),
                'standard_net' => $supplierInvoices->sum('standard_net'),
                'standard_vat' => $supplierInvoices->sum('standard_vat'),
                'total' => $supplierInvoices->sum('total_amount'),
            ];
        }

        $vatBreakdown = $vatReturn->getVatBreakdown();

        return view('management.vat-returns.show', compact(
            'vatReturn',
            'invoicesBySupplier',
            'supplierTotals',
            'vatBreakdown'
        ));
    }

    /**
     * Finalize a VAT return.
     */
    public function finalize(VatReturn $vatReturn)
    {
        try {
            $vatReturn->finalize();

            return redirect()->route('management.vat-returns.show', $vatReturn)
                ->with('success', 'VAT return has been finalized successfully.');
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * Export VAT return to CSV.
     */
    public function export(VatReturn $vatReturn)
    {
        $vatReturn->load('invoices.supplier');

        $filename = 'vat-return-'.$vatReturn->return_period.'.csv';

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ];

        $callback = function () use ($vatReturn) {
            $file = fopen('php://output', 'w');

            // Write header
            fputcsv($file, [
                'Invoice Number',
                'Supplier',
                'Invoice Date',
                'Net 0%',
                'VAT 0%',
                'Net 9%',
                'VAT 9%',
                'Net 13.5%',
                'VAT 13.5%',
                'Net 23%',
                'VAT 23%',
                'Total',
            ]);

            // Write invoice data
            foreach ($vatReturn->invoices as $invoice) {
                fputcsv($file, [
                    $invoice->invoice_number,
                    $invoice->supplier_name,
                    $invoice->invoice_date->format('Y-m-d'),
                    number_format($invoice->zero_net, 2, '.', ''),
                    number_format($invoice->zero_vat, 2, '.', ''),
                    number_format($invoice->second_reduced_net, 2, '.', ''),
                    number_format($invoice->second_reduced_vat, 2, '.', ''),
                    number_format($invoice->reduced_net, 2, '.', ''),
                    number_format($invoice->reduced_vat, 2, '.', ''),
                    number_format($invoice->standard_net, 2, '.', ''),
                    number_format($invoice->standard_vat, 2, '.', ''),
                    number_format($invoice->total_amount, 2, '.', ''),
                ]);
            }

            // Write totals
            fputcsv($file, []); // Empty row
            fputcsv($file, [
                'TOTALS',
                '',
                '',
                number_format($vatReturn->zero_net, 2, '.', ''),
                number_format($vatReturn->zero_vat, 2, '.', ''),
                number_format($vatReturn->second_reduced_net, 2, '.', ''),
                number_format($vatReturn->second_reduced_vat, 2, '.', ''),
                number_format($vatReturn->reduced_net, 2, '.', ''),
                number_format($vatReturn->reduced_vat, 2, '.', ''),
                number_format($vatReturn->standard_net, 2, '.', ''),
                number_format($vatReturn->standard_vat, 2, '.', ''),
                number_format($vatReturn->total_gross, 2, '.', ''),
            ]);

            // Write summary
            fputcsv($file, []); // Empty row
            fputcsv($file, ['SUMMARY']);
            fputcsv($file, ['Total Net', number_format($vatReturn->total_net, 2, '.', '')]);
            fputcsv($file, ['Total VAT', number_format($vatReturn->total_vat, 2, '.', '')]);
            fputcsv($file, ['Total Gross', number_format($vatReturn->total_gross, 2, '.', '')]);

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Remove invoice from VAT return.
     */
    public function removeInvoice(VatReturn $vatReturn, Invoice $invoice)
    {
        if (! $vatReturn->canBeModified()) {
            return back()->with('error', 'Cannot modify a finalized VAT return.');
        }

        if ($invoice->vat_return_id !== $vatReturn->id) {
            return back()->with('error', 'Invoice does not belong to this VAT return.');
        }

        $invoice->update(['vat_return_id' => null]);
        $vatReturn->calculateTotals();

        return back()->with('success', 'Invoice removed from VAT return.');
    }

    /**
     * Delete a draft VAT return.
     */
    public function destroy(VatReturn $vatReturn)
    {
        if (! $vatReturn->canBeModified()) {
            return back()->with('error', 'Cannot delete a finalized VAT return.');
        }

        DB::beginTransaction();
        try {
            // Unassign all invoices
            $vatReturn->invoices()->update(['vat_return_id' => null]);

            // Delete the VAT return
            $vatReturn->delete();

            DB::commit();

            return redirect()->route('management.vat-returns.index')
                ->with('success', 'VAT return deleted successfully.');

        } catch (\Exception $e) {
            DB::rollBack();

            return back()->with('error', 'Failed to delete VAT return: '.$e->getMessage());
        }
    }
}
