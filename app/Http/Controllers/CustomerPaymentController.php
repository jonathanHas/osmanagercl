<?php

namespace App\Http\Controllers;

use App\Http\Requests\CustomerPaymentRequest;
use App\Models\Customer;
use App\Models\CustomerInvoice;
use App\Models\CustomerPayment;
use App\Repositories\CashReconciliationRepository;
use App\Services\CustomerPaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CustomerPaymentController extends Controller
{
    public function __construct(
        private readonly CustomerPaymentService $service,
        private readonly CashReconciliationRepository $tills,
    ) {}

    public function index(Request $request): View
    {
        $query = CustomerPayment::query()
            ->with('customer', 'allocations.invoice')
            ->notVoid();

        if ($customerId = $request->query('customer_id')) {
            $query->where('customer_id', $customerId);
        }
        if ($method = $request->query('method')) {
            $query->where('method', $method);
        }
        if ($from = $request->query('from')) {
            $query->whereDate('payment_date', '>=', $from);
        }
        if ($to = $request->query('to')) {
            $query->whereDate('payment_date', '<=', $to);
        }

        // Unapplied credit — payments that were never matched to an invoice, or
        // only partly matched. Filtered in SQL so paging stays correct.
        $allocation = $request->query('allocation');
        if ($allocation === 'unallocated') {
            $query->withUnallocatedOver()->doesntHave('allocations');
        } elseif ($allocation === 'partial') {
            $query->partiallyAllocated();
        }

        // Header stat, independent of the current filters.
        $creditTotals = $this->service->unappliedCreditTotals();

        $payments = $query->orderByDesc('payment_date')->orderByDesc('id')
            ->paginate(25)->withQueryString();

        // Outstanding balances widget — top 10 customers with unpaid invoices.
        // Aggregated in SQL: the previous version loaded every customer with
        // their invoices and allocations, then fired two more queries per row
        // via the balance accessor.
        $outstandingCustomers = Customer::query()
            ->withBalanceOver()
            ->withSum(
                ['invoices as invoiced_total' => fn ($q) => $q->where('status', '!=', CustomerInvoice::STATUS_VOID)],
                'total'
            )
            ->withSum('payments as paid_total', 'amount')
            ->orderByRaw('(COALESCE(invoiced_total, 0) - COALESCE(paid_total, 0)) DESC')
            ->limit(10)
            ->get()
            ->map(fn ($c) => (object) [
                'customer' => $c,
                'balance' => round((float) ($c->invoiced_total ?? 0) - (float) ($c->paid_total ?? 0), 2),
            ]);

        return view('customer-payments.index', [
            'payments' => $payments,
            'outstandingCustomers' => $outstandingCustomers,
            'unappliedCreditTotal' => round(array_sum($creditTotals), 2),
            'unappliedCreditCustomers' => count($creditTotals),
        ]);
    }

    public function create(Request $request): View
    {
        $tills = $this->tills->getAvailableTills();

        $preselectCustomerId = $request->query('customer_id');
        $preselectInvoiceId = $request->query('customer_invoice_id');

        $customer = $preselectCustomerId ? Customer::find($preselectCustomerId) : null;
        $invoice = $preselectInvoiceId ? CustomerInvoice::with('customer')->find($preselectInvoiceId) : null;
        if ($invoice && ! $customer) {
            $customer = $invoice->customer;
        }

        return view('customer-payments.create', [
            'tills' => $tills,
            'preselectCustomer' => $customer,
            'preselectInvoice' => $invoice,
        ]);
    }

    public function store(CustomerPaymentRequest $request): RedirectResponse
    {
        $data = $request->safe()->except('allocations');

        // Till payments must record the till; online payments must NOT.
        if (in_array($data['method'], ['card_till', 'cash_till'], true)) {
            if (empty($data['till_id'])) {
                return back()->withInput()->withErrors([
                    'till_id' => 'A till is required for till-based payments.',
                ]);
            }
            // Snapshot the till name from the live mapping if not provided.
            if (empty($data['till_name'])) {
                $data['till_name'] = $this->tills->getAvailableTills()[$data['till_id']] ?? null;
            }
        } else {
            $data['till_id'] = null;
            $data['till_name'] = null;
        }

        try {
            $payment = $this->service->recordPayment($data, $request->allocations());
        } catch (\DomainException $e) {
            return back()->withInput()->withErrors(['allocations' => $e->getMessage()]);
        }

        return redirect()->route('customer-payments.show', $payment)
            ->with('status', 'Payment recorded.');
    }

    public function show(CustomerPayment $customerPayment): View
    {
        // allAllocations so a row against a voided invoice is shown and explained
        // rather than silently dropped; allocations drives the money maths.
        $customerPayment->load('customer', 'allocations.invoice', 'allAllocations.invoice', 'creator', 'voider', 'lastMatcher');

        return view('customer-payments.show', ['payment' => $customerPayment]);
    }

    public function destroy(CustomerPayment $customerPayment): RedirectResponse
    {
        $this->service->void($customerPayment);

        return redirect()->route('customer-payments.index')
            ->with('status', 'Payment voided. Outstanding balances updated.');
    }

    /**
     * JSON endpoint — list of tills available for till-based payments.
     */
    public function tillsApi(): JsonResponse
    {
        $tills = $this->tills->getAvailableTills()
            ->map(fn ($host, $id) => ['id' => (string) $id, 'name' => $host])
            ->values();

        return response()->json(['data' => $tills]);
    }

    /**
     * JSON endpoint — open invoices for a customer (status != void, outstanding > 0).
     * Used by the create form to populate the allocations table after a customer is picked.
     *
     * Shares CustomerPaymentService::invoiceRowsFor() with autoAllocate() and the
     * allocation validator so the three can never disagree about what is payable.
     */
    public function customerOpenInvoicesApi(Customer $customer): JsonResponse
    {
        return response()->json(['data' => $this->service->invoiceRowsFor($customer)]);
    }
}
