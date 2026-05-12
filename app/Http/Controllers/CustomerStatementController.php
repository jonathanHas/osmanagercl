<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\CustomerInvoice;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CustomerStatementController extends Controller
{
    public function show(Customer $customer, Request $request): View
    {
        return view('customer-statements.show', $this->buildContext($customer, $request));
    }

    public function downloadPdf(Customer $customer, Request $request)
    {
        $ctx = $this->buildContext($customer, $request);
        $filename = sprintf(
            'statement-%s-%s.pdf',
            preg_replace('/[^A-Za-z0-9]+/', '-', strtolower($customer->name)),
            $ctx['to']->format('Ymd'),
        );

        return Pdf::loadView('customer-statements._pdf', $ctx)
            ->setPaper('a4')
            ->download($filename);
    }

    /**
     * Build the chronological event list (invoices + payments) with running balance.
     * Returns the view context shared by HTML and PDF.
     */
    private function buildContext(Customer $customer, Request $request): array
    {
        $from = $request->filled('from') ? Carbon::parse($request->query('from')) : null;
        $to = $request->filled('to') ? Carbon::parse($request->query('to')) : Carbon::today();

        // Opening balance is everything strictly before $from. With no $from set,
        // the statement covers all activity from the start, so opening = 0.
        $openingBalance = 0.0;
        if ($from) {
            $openingInvoices = $customer->invoices()
                ->where('status', '!=', CustomerInvoice::STATUS_VOID)
                ->whereDate('issue_date', '<', $from)
                ->sum('total');
            $openingPayments = $customer->payments()
                ->whereDate('payment_date', '<', $from)
                ->sum('amount');
            $openingBalance = round((float) $openingInvoices - (float) $openingPayments, 2);
        }

        // In-range events.
        $invoices = $customer->invoices()
            ->where('status', '!=', CustomerInvoice::STATUS_VOID)
            ->when($from, fn ($q) => $q->whereDate('issue_date', '>=', $from))
            ->whereDate('issue_date', '<=', $to)
            ->orderBy('issue_date')
            ->orderBy('id')
            ->get();

        $payments = $customer->payments()
            ->with('allocations.invoice')
            ->when($from, fn ($q) => $q->whereDate('payment_date', '>=', $from))
            ->whereDate('payment_date', '<=', $to)
            ->orderBy('payment_date')
            ->orderBy('id')
            ->get();

        // Merge into a chronological event list.
        $events = collect();
        foreach ($invoices as $inv) {
            $events->push([
                'date' => $inv->issue_date,
                'sort_key' => $inv->issue_date->format('Ymd').'_inv_'.str_pad((string) $inv->id, 8, '0', STR_PAD_LEFT),
                'kind' => 'invoice',
                'description' => 'Invoice '.($inv->invoice_number ?? '(draft)'),
                'reference' => $inv->invoice_number,
                'debit' => (float) $inv->total,
                'credit' => 0.0,
                'invoice' => $inv,
            ]);
        }
        foreach ($payments as $pay) {
            $allocSummary = $pay->allocations
                ->map(fn ($a) => $a->invoice?->invoice_number ?? '(unallocated)')
                ->implode(', ');
            $tillBit = $pay->isTillPayment() && $pay->till_name ? ' via '.$pay->till_name : '';
            $description = 'Payment — '.$pay->methodLabel().$tillBit;
            if ($allocSummary !== '') {
                $description .= ' (applied to '.$allocSummary.')';
            }
            $events->push([
                'date' => $pay->payment_date,
                'sort_key' => $pay->payment_date->format('Ymd').'_pay_'.str_pad((string) $pay->id, 8, '0', STR_PAD_LEFT),
                'kind' => 'payment',
                'description' => $description,
                'reference' => $pay->reference,
                'debit' => 0.0,
                'credit' => (float) $pay->amount,
                'payment' => $pay,
            ]);
        }
        $events = $events->sortBy('sort_key')->values()->all(); // plain array so we can mutate by index

        // Running balance.
        $balance = $openingBalance;
        $rangeInvoiced = 0.0;
        $rangePaid = 0.0;
        foreach ($events as $i => $e) {
            $balance = round($balance + $e['debit'] - $e['credit'], 2);
            $events[$i]['balance'] = $balance;
            $rangeInvoiced += $e['debit'];
            $rangePaid += $e['credit'];
        }

        return [
            'customer' => $customer,
            'events' => $events,
            'opening_balance' => $openingBalance,
            'closing_balance' => $balance,
            'range_invoiced' => round($rangeInvoiced, 2),
            'range_paid' => round($rangePaid, 2),
            'from' => $from,
            'to' => $to,
        ];
    }
}
