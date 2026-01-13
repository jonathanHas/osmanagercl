<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use Carbon\Carbon;
use Illuminate\Http\Request;

class SupplierPaymentsController extends Controller
{
    public function index(Request $request)
    {
        // Default to current month
        $startDate = $request->get('start_date', now()->startOfMonth()->format('Y-m-d'));
        $endDate = $request->get('end_date', now()->format('Y-m-d'));
        $sort = $request->get('sort', 'date_desc');
        $groupBy = $request->get('group_by', 'none');

        // If no dates provided, show form only
        if (! $request->filled('start_date')) {
            return view('suppliers.payments', compact('startDate', 'endDate', 'sort', 'groupBy'));
        }

        $startDateTime = Carbon::parse($startDate)->startOfDay();
        $endDateTime = Carbon::parse($endDate)->endOfDay();

        // Get paid invoices within the date range
        $paidInvoices = Invoice::with('supplier')
            ->where('payment_status', 'paid')
            ->whereNotNull('payment_date')
            ->whereBetween('payment_date', [$startDateTime, $endDateTime])
            ->get();

        // Map to payment records
        $payments = $paidInvoices->map(function ($invoice) {
            return [
                'date' => $invoice->payment_date,
                'invoice_date' => $invoice->invoice_date,
                'supplier_name' => $invoice->supplier->name ?? $invoice->supplier_name ?? 'Unknown',
                'supplier_id' => $invoice->supplier_id,
                'amount' => (float) $invoice->total_amount,
                'type' => $this->formatPaymentMethod($invoice->payment_method),
                'reference' => $invoice->payment_reference,
                'invoice_number' => $invoice->invoice_number,
                'payment_method' => $invoice->payment_method,
            ];
        });

        // Apply sorting
        $payments = match ($sort) {
            'date_asc' => $payments->sortBy('date'),
            'supplier_asc' => $payments->sortBy('supplier_name')->values(),
            'supplier_desc' => $payments->sortByDesc('supplier_name')->values(),
            default => $payments->sortByDesc('date'),
        };

        // Calculate summary stats
        $totalPayments = $payments->sum('amount');
        $paymentCount = $payments->count();

        // Group by payment method for breakdown
        $methodTotals = $payments->groupBy('payment_method')->map(fn ($group) => $group->sum('amount'));

        // Group by supplier if requested
        $groupedPayments = null;
        if ($groupBy === 'supplier') {
            $groupedPayments = $payments->groupBy('supplier_name')->map(function ($group) {
                return [
                    'payments' => $group->values(),
                    'total' => $group->sum('amount'),
                    'count' => $group->count(),
                ];
            })->sortKeys();
        }

        return view('suppliers.payments', compact(
            'startDate',
            'endDate',
            'sort',
            'groupBy',
            'payments',
            'groupedPayments',
            'totalPayments',
            'paymentCount',
            'methodTotals'
        ));
    }

    private function formatPaymentMethod(?string $method): string
    {
        if (! $method) {
            return 'Unknown';
        }

        $methods = [
            'bank_transfer' => 'Bank Transfer',
            'bacs' => 'BACS',
            'cash' => 'Cash',
            'cheque' => 'Cheque',
            'card' => 'Card',
            'credit_card' => 'Credit Card',
            'other' => 'Other',
        ];

        return $methods[$method] ?? ucfirst(str_replace('_', ' ', $method));
    }

    public function exportCsv(Request $request)
    {
        $startDate = $request->get('start_date', now()->startOfMonth()->format('Y-m-d'));
        $endDate = $request->get('end_date', now()->format('Y-m-d'));
        $sort = $request->get('sort', 'date_desc');
        $groupBy = $request->get('group_by', 'none');

        $startDateTime = Carbon::parse($startDate)->startOfDay();
        $endDateTime = Carbon::parse($endDate)->endOfDay();

        // Get paid invoices within the date range
        $paidInvoices = Invoice::with('supplier')
            ->where('payment_status', 'paid')
            ->whereNotNull('payment_date')
            ->whereBetween('payment_date', [$startDateTime, $endDateTime])
            ->get();

        // Map to payment records
        $payments = $paidInvoices->map(function ($invoice) {
            return [
                'date' => $invoice->payment_date,
                'invoice_date' => $invoice->invoice_date,
                'supplier_name' => $invoice->supplier->name ?? $invoice->supplier_name ?? 'Unknown',
                'amount' => (float) $invoice->total_amount,
                'type' => $this->formatPaymentMethod($invoice->payment_method),
                'reference' => $invoice->payment_reference,
                'invoice_number' => $invoice->invoice_number,
            ];
        });

        // Apply sorting
        $payments = match ($sort) {
            'date_asc' => $payments->sortBy('date'),
            'supplier_asc' => $payments->sortBy('supplier_name')->values(),
            'supplier_desc' => $payments->sortByDesc('supplier_name')->values(),
            default => $payments->sortByDesc('date'),
        };

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
            fputcsv($file, ['Payment Date', 'Supplier', 'Amount', 'Payment Method', 'Reference', 'Invoice #', 'Invoice Date']);

            $totalAmount = 0;

            foreach ($payments as $payment) {
                fputcsv($file, [
                    $payment['date']->format('d/m/Y'),
                    $payment['supplier_name'],
                    number_format($payment['amount'], 2),
                    $payment['type'],
                    $payment['reference'] ?? '',
                    $payment['invoice_number'] ?? '',
                    $payment['invoice_date'] ? $payment['invoice_date']->format('d/m/Y') : '',
                ]);

                $totalAmount += $payment['amount'];
            }

            fputcsv($file, []); // Empty row
            fputcsv($file, ['Summary']);
            fputcsv($file, ['Total Payments:', '€'.number_format($totalAmount, 2)]);
            fputcsv($file, ['Payment Count:', $payments->count()]);

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }
}
