<?php

namespace App\Http\Controllers;

use App\Models\BankTransactionAllocation;
use App\Models\CashReconciliationPayment;
use Carbon\Carbon;
use Illuminate\Http\Request;

class SupplierPaymentsController extends Controller
{
    public function index(Request $request)
    {
        // Default to current month
        $startDate = $request->get('start_date', now()->startOfMonth()->format('Y-m-d'));
        $endDate = $request->get('end_date', now()->format('Y-m-d'));

        // If no dates provided, show form only
        if (! $request->filled('start_date')) {
            return view('suppliers.payments', compact('startDate', 'endDate'));
        }

        $startDateTime = Carbon::parse($startDate)->startOfDay();
        $endDateTime = Carbon::parse($endDate)->endOfDay();

        // Get bank payments (allocations to invoices)
        $bankPayments = BankTransactionAllocation::with(['bankTransaction', 'invoice.supplier'])
            ->whereHas('bankTransaction', function ($query) use ($startDateTime, $endDateTime) {
                $query->whereBetween('transaction_date', [$startDateTime, $endDateTime]);
            })
            ->get()
            ->map(function ($allocation) {
                return [
                    'date' => $allocation->bankTransaction->transaction_date,
                    'supplier_name' => $allocation->invoice->supplier->name ?? $allocation->invoice->supplier_name ?? 'Unknown',
                    'amount' => (float) $allocation->allocated_amount,
                    'type' => 'Bank',
                    'reference' => $allocation->bankTransaction->description,
                    'invoice_number' => $allocation->invoice->invoice_number ?? null,
                    'allocation_type' => $allocation->formatted_type,
                ];
            });

        // Get cash payments
        $cashPayments = CashReconciliationPayment::with(['reconciliation', 'supplier'])
            ->whereHas('reconciliation', function ($query) use ($startDateTime, $endDateTime) {
                $query->whereBetween('date', [$startDateTime, $endDateTime]);
            })
            ->get()
            ->map(function ($payment) {
                return [
                    'date' => $payment->reconciliation->date,
                    'supplier_name' => $payment->payee_display_name,
                    'amount' => (float) $payment->amount,
                    'type' => 'Cash',
                    'reference' => $payment->description,
                    'invoice_number' => null,
                    'allocation_type' => null,
                ];
            });

        // Merge and sort by date descending
        $payments = $bankPayments->concat($cashPayments)
            ->sortByDesc('date')
            ->values();

        // Calculate summary stats
        $totalPayments = $payments->sum('amount');
        $paymentCount = $payments->count();
        $bankTotal = $bankPayments->sum('amount');
        $cashTotal = $cashPayments->sum('amount');

        return view('suppliers.payments', compact(
            'startDate',
            'endDate',
            'payments',
            'totalPayments',
            'paymentCount',
            'bankTotal',
            'cashTotal'
        ));
    }

    public function exportCsv(Request $request)
    {
        $startDate = $request->get('start_date', now()->startOfMonth()->format('Y-m-d'));
        $endDate = $request->get('end_date', now()->format('Y-m-d'));

        $startDateTime = Carbon::parse($startDate)->startOfDay();
        $endDateTime = Carbon::parse($endDate)->endOfDay();

        // Get bank payments
        $bankPayments = BankTransactionAllocation::with(['bankTransaction', 'invoice.supplier'])
            ->whereHas('bankTransaction', function ($query) use ($startDateTime, $endDateTime) {
                $query->whereBetween('transaction_date', [$startDateTime, $endDateTime]);
            })
            ->get()
            ->map(function ($allocation) {
                return [
                    'date' => $allocation->bankTransaction->transaction_date,
                    'supplier_name' => $allocation->invoice->supplier->name ?? $allocation->invoice->supplier_name ?? 'Unknown',
                    'amount' => (float) $allocation->allocated_amount,
                    'type' => 'Bank',
                    'reference' => $allocation->bankTransaction->description,
                    'invoice_number' => $allocation->invoice->invoice_number ?? null,
                ];
            });

        // Get cash payments
        $cashPayments = CashReconciliationPayment::with(['reconciliation', 'supplier'])
            ->whereHas('reconciliation', function ($query) use ($startDateTime, $endDateTime) {
                $query->whereBetween('date', [$startDateTime, $endDateTime]);
            })
            ->get()
            ->map(function ($payment) {
                return [
                    'date' => $payment->reconciliation->date,
                    'supplier_name' => $payment->payee_display_name,
                    'amount' => (float) $payment->amount,
                    'type' => 'Cash',
                    'reference' => $payment->description,
                    'invoice_number' => null,
                ];
            });

        // Merge and sort by date descending
        $payments = $bankPayments->concat($cashPayments)
            ->sortByDesc('date')
            ->values();

        $filename = 'supplier-payments-'.$startDate.'-to-'.$endDate.'.csv';

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ];

        $callback = function () use ($payments, $startDate, $endDate) {
            $file = fopen('php://output', 'w');

            // Write header
            fputcsv($file, ['Supplier Payments Report']);
            fputcsv($file, ['Date Range: '.$startDate.' to '.$endDate]);
            fputcsv($file, []); // Empty row

            // Column headers
            fputcsv($file, ['Date', 'Supplier', 'Amount', 'Type', 'Reference', 'Invoice #']);

            $totalAmount = 0;
            $bankTotal = 0;
            $cashTotal = 0;

            foreach ($payments as $payment) {
                fputcsv($file, [
                    $payment['date']->format('Y-m-d'),
                    $payment['supplier_name'],
                    number_format($payment['amount'], 2),
                    $payment['type'],
                    $payment['reference'] ?? '',
                    $payment['invoice_number'] ?? '',
                ]);

                $totalAmount += $payment['amount'];
                if ($payment['type'] === 'Bank') {
                    $bankTotal += $payment['amount'];
                } else {
                    $cashTotal += $payment['amount'];
                }
            }

            fputcsv($file, []); // Empty row
            fputcsv($file, ['Summary']);
            fputcsv($file, ['Total Payments:', '€'.number_format($totalAmount, 2)]);
            fputcsv($file, ['Bank Payments:', '€'.number_format($bankTotal, 2)]);
            fputcsv($file, ['Cash Payments:', '€'.number_format($cashTotal, 2)]);
            fputcsv($file, ['Payment Count:', $payments->count()]);

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }
}
