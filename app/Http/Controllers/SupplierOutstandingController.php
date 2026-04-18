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
        $showPreviousPayments = $request->boolean('show_previous_payments', false);

        // If no date provided, show form only
        if (! $request->filled('report_date')) {
            return view('suppliers.outstanding-report', compact('reportDate', 'showPreviousPayments'));
        }

        $reportDateTime = Carbon::parse($reportDate)->endOfDay();

        // Get all invoices that were outstanding on the specified date
        // An invoice was outstanding if it was created before/on the date AND either:
        // 1. Not paid (payment_status is not 'paid')
        // 2. Paid after the report date (payment_status is 'paid' but payment_date > report date)
        $outstandingInvoices = Invoice::with(['supplier', 'vatLines', 'attachments'])
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
        $supplierGroups = $outstandingInvoices->groupBy('supplier_id')->map(function ($invoices, $supplierId) use ($reportDateTime, $showPreviousPayments) {
            $supplier = $invoices->first()->supplier;
            $totalAmount = $invoices->sum('total_amount');

            // Fallback to supplier_name from invoice if no supplier relationship
            $supplierName = $supplier ? $supplier->name : $invoices->first()->supplier_name;

            $allInvoices = $invoices;

            // Get last 2 paid invoices for this supplier if requested
            if ($showPreviousPayments && $supplierId) {
                $previousPayments = Invoice::with('attachments')
                    ->where('supplier_id', $supplierId)
                    ->where('payment_status', 'paid')
                    ->whereNotNull('payment_date')
                    ->where('payment_date', '<=', $reportDateTime)
                    ->orderBy('payment_date', 'desc')
                    ->limit(2)
                    ->get();

                // Merge with outstanding invoices and sort by invoice date
                $allInvoices = $invoices->merge($previousPayments)->sortBy('invoice_date');
            }

            $groupData = [
                'supplier' => $supplier,
                'supplier_name' => $supplierName,
                'invoices' => $invoices,
                'all_invoices' => $allInvoices,
                'total_amount' => $totalAmount,
                'invoice_count' => $invoices->count(),
            ];

            return $groupData;
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
            'unpaidInvoices',
            'showPreviousPayments'
        ));
    }

    public function exportCsv(Request $request)
    {
        $reportDate = $request->get('report_date', now()->format('Y-m-d'));
        $showPreviousPayments = $request->boolean('show_previous_payments', false);
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

        // Merge with previous payments if requested for CSV
        if ($showPreviousPayments) {
            $supplierGroups = $supplierGroups->map(function ($invoices, $supplierId) use ($reportDateTime) {
                if ($supplierId) {
                    $previousPayments = Invoice::where('supplier_id', $supplierId)
                        ->where('payment_status', 'paid')
                        ->whereNotNull('payment_date')
                        ->where('payment_date', '<=', $reportDateTime)
                        ->orderBy('payment_date', 'desc')
                        ->limit(2)
                        ->get();

                    // Merge and sort by invoice date
                    return $invoices->merge($previousPayments)->sortBy('invoice_date');
                }

                return $invoices;
            });
        }

        $filename = 'outstanding-invoices-'.$reportDate.'.csv';

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ];

        $callback = function () use ($supplierGroups, $reportDate, $reportDateTime, $showPreviousPayments) {
            $file = fopen('php://output', 'w');

            // Write header
            fputcsv($file, ['Outstanding Invoices Report - '.$reportDate]);
            if ($showPreviousPayments) {
                fputcsv($file, ['(with previous payments for reference - shown chronologically)']);
            }
            fputcsv($file, []); // Empty row

            $overallTotal = 0;
            $totalOutstandingInvoices = 0;

            foreach ($supplierGroups as $supplierId => $invoices) {
                $supplier = $invoices->first()->supplier;
                $supplierName = $supplier ? $supplier->name : $invoices->first()->supplier_name;

                // Calculate only outstanding total for supplier
                $supplierTotal = $invoices->filter(function ($invoice) use ($reportDateTime) {
                    return in_array($invoice->payment_status, ['pending', 'overdue', 'partial']) ||
                           ($invoice->payment_status === 'paid' && $invoice->payment_date && $invoice->payment_date > $reportDateTime);
                })->sum('total_amount');

                // Supplier header
                fputcsv($file, ['Supplier: '.($supplierName ?: 'Unknown Supplier')]);
                fputcsv($file, ['Invoice Number', 'Invoice Date', 'Total Amount', 'Payment Status', 'Payment Date', 'Type']);

                foreach ($invoices as $invoice) {
                    // Determine if outstanding
                    $isOutstanding = in_array($invoice->payment_status, ['pending', 'overdue', 'partial']) ||
                                    ($invoice->payment_status === 'paid' && $invoice->payment_date && $invoice->payment_date > $reportDateTime);

                    // Determine payment status text for CSV
                    if ($invoice->payment_status === 'paid' && $invoice->payment_date) {
                        if ($invoice->payment_date > $reportDateTime) {
                            $paymentStatus = 'Paid after report date';
                        } else {
                            $paymentStatus = 'Paid';
                        }
                    } else {
                        $paymentStatus = ucfirst($invoice->payment_status);
                    }

                    fputcsv($file, [
                        $invoice->invoice_number,
                        $invoice->invoice_date->format('Y-m-d'),
                        number_format($invoice->total_amount, 2),
                        $paymentStatus,
                        $invoice->payment_date ? $invoice->payment_date->format('Y-m-d') : 'N/A',
                        $isOutstanding ? 'OUTSTANDING' : 'Reference',
                    ]);
                }

                // Supplier total (outstanding only)
                fputcsv($file, ['', 'Outstanding Total:', '€'.number_format($supplierTotal, 2), '', '', '']);
                fputcsv($file, []); // Empty row

                $overallTotal += $supplierTotal;
                $totalOutstandingInvoices += $invoices->filter(function ($invoice) use ($reportDateTime) {
                    return in_array($invoice->payment_status, ['pending', 'overdue', 'partial']) ||
                           ($invoice->payment_status === 'paid' && $invoice->payment_date && $invoice->payment_date > $reportDateTime);
                })->count();
            }

            // Overall totals
            fputcsv($file, ['Overall Summary']);
            fputcsv($file, ['Total Suppliers:', $supplierGroups->count()]);
            fputcsv($file, ['Total Outstanding Invoices:', $totalOutstandingInvoices]);
            fputcsv($file, ['Total Outstanding Amount:', '€'.number_format($overallTotal, 2)]);

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }
}
