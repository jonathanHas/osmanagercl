<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use Carbon\Carbon;
use Illuminate\Http\Request;

class SupplierOutstandingController extends Controller
{
    public function index(Request $request)
    {
        $reportDate = $request->get('report_date', now()->format('Y-m-d'));

        // If no date provided, show form only
        if (! $request->filled('report_date')) {
            return view('suppliers.outstanding-report', compact('reportDate'));
        }

        $reportDateTime = Carbon::parse($reportDate)->endOfDay();

        // Get all invoices that were outstanding on the specified date
        // An invoice was outstanding if it was created before/on the date AND either:
        // 1. Not paid (payment_status is not 'paid')
        // 2. Paid after the report date (payment_status is 'paid' but payment_date > report date)
        $outstandingInvoices = Invoice::with(['supplier', 'vatLines'])
            ->where('invoice_date', '<=', $reportDateTime)
            ->where(function ($query) use ($reportDateTime) {
                // Include invoices that are not paid (pending, overdue, partial)
                $query->where('payment_status', '!=', 'paid')
                      // Or invoices that were paid after the report date
                    ->orWhere(function ($q) use ($reportDateTime) {
                        $q->where('payment_status', 'paid')
                            ->where('payment_date', '>', $reportDateTime);
                    });
            })
            // Exclude cancelled invoices as they shouldn't be considered outstanding
            ->where('payment_status', '!=', 'cancelled')
            ->orderBy('supplier_id')
            ->orderBy('invoice_date')
            ->get();

        // Group by supplier and calculate totals
        $supplierGroups = $outstandingInvoices->groupBy('supplier_id')->map(function ($invoices, $supplierId) {
            $supplier = $invoices->first()->supplier;
            $totalAmount = $invoices->sum('total_amount');

            // Fallback to supplier_name from invoice if no supplier relationship
            $supplierName = $supplier ? $supplier->name : $invoices->first()->supplier_name;

            return [
                'supplier' => $supplier,
                'supplier_name' => $supplierName,
                'invoices' => $invoices,
                'total_amount' => $totalAmount,
                'invoice_count' => $invoices->count(),
            ];
        })->sortBy('supplier_name');

        // Calculate overall total
        $overallTotal = $supplierGroups->sum('total_amount');
        $totalInvoiceCount = $supplierGroups->sum('invoice_count');

        // Get invoices that are still unpaid for manual review
        $unpaidInvoices = $outstandingInvoices->filter(function ($invoice) {
            return $invoice->payment_status !== 'paid';
        });

        return view('suppliers.outstanding-report', compact(
            'reportDate',
            'supplierGroups',
            'overallTotal',
            'totalInvoiceCount',
            'unpaidInvoices'
        ));
    }

    public function exportCsv(Request $request)
    {
        $reportDate = $request->get('report_date', now()->format('Y-m-d'));
        $reportDateTime = Carbon::parse($reportDate)->endOfDay();

        $outstandingInvoices = Invoice::with(['supplier', 'vatLines'])
            ->where('invoice_date', '<=', $reportDateTime)
            ->where(function ($query) use ($reportDateTime) {
                // Include invoices that are not paid (pending, overdue, partial)
                $query->where('payment_status', '!=', 'paid')
                      // Or invoices that were paid after the report date
                    ->orWhere(function ($q) use ($reportDateTime) {
                        $q->where('payment_status', 'paid')
                            ->where('payment_date', '>', $reportDateTime);
                    });
            })
            // Exclude cancelled invoices as they shouldn't be considered outstanding
            ->where('payment_status', '!=', 'cancelled')
            ->orderBy('supplier_id')
            ->orderBy('invoice_date')
            ->get();

        $supplierGroups = $outstandingInvoices->groupBy('supplier_id');

        // Sort supplier groups alphabetically by supplier name for CSV export
        $supplierGroups = $supplierGroups->sortBy(function ($invoices) {
            $supplier = $invoices->first()->supplier;

            return $supplier ? $supplier->name : $invoices->first()->supplier_name;
        });

        $filename = 'outstanding-invoices-'.$reportDate.'.csv';

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ];

        $callback = function () use ($supplierGroups, $reportDate) {
            $file = fopen('php://output', 'w');

            // Write header
            fputcsv($file, ['Outstanding Invoices Report - '.$reportDate]);
            fputcsv($file, []); // Empty row

            $overallTotal = 0;
            $totalInvoices = 0;

            foreach ($supplierGroups as $supplierId => $invoices) {
                $supplier = $invoices->first()->supplier;
                $supplierTotal = $invoices->sum('total_amount');

                // Supplier header
                $supplier = $invoices->first()->supplier;
                $supplierName = $supplier ? $supplier->name : $invoices->first()->supplier_name;
                fputcsv($file, ['Supplier: '.($supplierName ?: 'Unknown Supplier')]);
                fputcsv($file, ['Invoice Number', 'Invoice Date', 'Total Amount', 'Payment Status']);

                foreach ($invoices as $invoice) {
                    // Determine payment status text for CSV
                    if ($invoice->payment_status === 'paid' && $invoice->payment_date) {
                        $paymentStatus = 'Paid after report date ('.$invoice->payment_date->format('Y-m-d').')';
                    } else {
                        $paymentStatus = ucfirst($invoice->payment_status);
                    }

                    fputcsv($file, [
                        $invoice->invoice_number,
                        $invoice->invoice_date->format('Y-m-d'),
                        number_format($invoice->total_amount, 2),
                        $paymentStatus,
                    ]);
                }

                // Supplier total
                fputcsv($file, ['', 'Supplier Total:', '€'.number_format($supplierTotal, 2), '']);
                fputcsv($file, []); // Empty row

                $overallTotal += $supplierTotal;
                $totalInvoices += $invoices->count();
            }

            // Overall totals
            fputcsv($file, ['Overall Summary']);
            fputcsv($file, ['Total Suppliers:', $supplierGroups->count()]);
            fputcsv($file, ['Total Invoices:', $totalInvoices]);
            fputcsv($file, ['Total Outstanding Amount:', '€'.number_format($overallTotal, 2)]);

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }
}
