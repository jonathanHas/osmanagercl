<?php

namespace App\Http\Controllers;

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

        $payments = $query->orderByDesc('payment_date')->orderByDesc('id')
            ->paginate(25)->withQueryString();

        // Outstanding balances widget — top 10 customers with unpaid invoices.
        $outstandingCustomers = Customer::query()
            ->whereHas('invoices', fn ($q) => $q->where('status', '!=', CustomerInvoice::STATUS_VOID))
            ->with(['invoices' => fn ($q) => $q->where('status', '!=', CustomerInvoice::STATUS_VOID)
                ->with('allocations')])
            ->get()
            ->map(fn ($c) => (object) [
                'customer' => $c,
                'balance' => $c->balance,
            ])
            ->filter(fn ($row) => $row->balance > 0.005)
            ->sortByDesc('balance')
            ->take(10)
            ->values();

        return view('customer-payments.index', compact('payments', 'outstandingCustomers'));
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

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'customer_id' => ['required', 'exists:App\Models\Customer,id'],
            'payment_date' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'method' => ['required', 'in:card_till,cash_till,online'],
            'till_id' => ['nullable', 'string', 'max:32'],
            'till_name' => ['nullable', 'string', 'max:255'],
            'reference' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'allocations' => ['nullable', 'array'],
            'allocations.*.customer_invoice_id' => ['required_with:allocations.*.amount', 'integer', 'exists:App\Models\CustomerInvoice,id'],
            'allocations.*.amount' => ['required_with:allocations.*.customer_invoice_id', 'numeric', 'gt:0'],
        ]);

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

        $allocations = $data['allocations'] ?? [];
        unset($data['allocations']);

        try {
            $payment = $this->service->recordPayment($data, $allocations);
        } catch (\DomainException $e) {
            return back()->withInput()->withErrors(['allocations' => $e->getMessage()]);
        }

        return redirect()->route('customer-payments.show', $payment)
            ->with('status', 'Payment recorded.');
    }

    public function show(CustomerPayment $customerPayment): View
    {
        $customerPayment->load('customer', 'allocations.invoice', 'creator', 'voider');

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
     */
    public function customerOpenInvoicesApi(Customer $customer): JsonResponse
    {
        $invoices = $customer->invoices()
            ->where('status', '!=', CustomerInvoice::STATUS_VOID)
            ->with('allocations')
            ->orderBy('issue_date')
            ->orderBy('id')
            ->get()
            ->filter(fn ($inv) => $inv->outstanding_amount > 0.005)
            ->map(fn ($inv) => [
                'id' => $inv->id,
                'invoice_number' => $inv->invoice_number,
                'issue_date' => optional($inv->issue_date)->toDateString(),
                'total' => (float) $inv->total,
                'outstanding' => $inv->outstanding_amount,
            ])
            ->values();

        return response()->json(['data' => $invoices]);
    }
}
