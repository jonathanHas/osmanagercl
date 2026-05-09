<?php

namespace App\Http\Controllers;

use App\Models\CustomerInvoice;
use App\Services\CustomerInvoiceService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

class CustomerInvoiceController extends Controller
{
    public function __construct(private readonly CustomerInvoiceService $service) {}

    public function index(Request $request)
    {
        $query = CustomerInvoice::with('customer', 'creator');

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        if ($customerId = $request->query('customer_id')) {
            $query->where('customer_id', $customerId);
        }

        if ($from = $request->query('from')) {
            $query->whereDate('issue_date', '>=', $from);
        }
        if ($to = $request->query('to')) {
            $query->whereDate('issue_date', '<=', $to);
        }

        $invoices = $query->orderByDesc('issue_date')->orderByDesc('id')->paginate(25)->withQueryString();

        return view('customer-invoices.index', compact('invoices'));
    }

    public function create()
    {
        return view('customer-invoices.create', [
            'invoice' => null,
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validateInvoice($request);
        $invoice = $this->service->createDraft($data['invoice'], $data['items']);

        if ($request->boolean('issue')) {
            $this->service->issue($invoice);

            return redirect()->route('customer-invoices.show', $invoice)->with('status', 'Invoice issued.');
        }

        return redirect()->route('customer-invoices.show', $invoice)->with('status', 'Draft saved.');
    }

    public function show(CustomerInvoice $customerInvoice)
    {
        $customerInvoice->load('items', 'customer', 'creator', 'voider');

        return view('customer-invoices.show', ['invoice' => $customerInvoice]);
    }

    public function edit(CustomerInvoice $customerInvoice)
    {
        abort_unless($customerInvoice->isEditable(), 403, 'Issued invoices cannot be edited.');
        $customerInvoice->load('items', 'customer');

        return view('customer-invoices.create', ['invoice' => $customerInvoice]);
    }

    public function update(Request $request, CustomerInvoice $customerInvoice)
    {
        abort_unless($customerInvoice->isEditable(), 403, 'Issued invoices cannot be edited.');

        $data = $this->validateInvoice($request);

        $customerInvoice->update($data['invoice']);
        $this->service->syncItems($customerInvoice, $data['items']);

        if ($request->boolean('issue')) {
            $this->service->issue($customerInvoice);

            return redirect()->route('customer-invoices.show', $customerInvoice)->with('status', 'Invoice issued.');
        }

        return redirect()->route('customer-invoices.show', $customerInvoice)->with('status', 'Draft updated.');
    }

    public function issue(CustomerInvoice $customerInvoice)
    {
        $this->service->issue($customerInvoice);

        return redirect()->route('customer-invoices.show', $customerInvoice)->with('status', 'Invoice issued.');
    }

    public function void(CustomerInvoice $customerInvoice)
    {
        $this->service->void($customerInvoice);

        return redirect()->route('customer-invoices.show', $customerInvoice)->with('status', 'Invoice voided.');
    }

    public function downloadPdf(CustomerInvoice $customerInvoice)
    {
        $customerInvoice->load('items', 'customer');

        $filename = $customerInvoice->invoice_number
            ? "invoice-{$customerInvoice->invoice_number}.pdf"
            : "invoice-draft-{$customerInvoice->id}.pdf";

        return Pdf::loadView('customer-invoices._pdf', ['invoice' => $customerInvoice])
            ->setPaper('a4')
            ->download($filename);
    }

    public function destroy(CustomerInvoice $customerInvoice)
    {
        abort_unless($customerInvoice->isEditable(), 403, 'Only draft invoices can be deleted.');
        $customerInvoice->delete();

        return redirect()->route('customer-invoices.index')->with('status', 'Draft deleted.');
    }

    private function validateInvoice(Request $request): array
    {
        $validated = $request->validate([
            'customer_id' => ['nullable', 'exists:App\Models\Customer,id'],
            'customer_name' => ['required', 'string', 'max:255'],
            'customer_address' => ['nullable', 'string', 'max:1000'],
            'customer_vat_number' => ['nullable', 'string', 'max:64'],
            'customer_email' => ['nullable', 'email', 'max:255'],
            'issue_date' => ['required', 'date'],
            'due_date' => ['nullable', 'date', 'after_or_equal:issue_date'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.pos_product_id' => ['nullable', 'string', 'max:64'],
            'items.*.pos_product_code' => ['nullable', 'string', 'max:64'],
            'items.*.description' => ['required', 'string', 'max:255'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0'],
            'items.*.unit_price' => ['required', 'numeric', 'gte:0'],
            'items.*.vat_rate' => ['required', 'numeric', 'gte:0', 'lte:1'],
        ]);

        $invoiceData = collect($validated)->except('items')->all();
        $items = collect($validated['items'])->values()->all();

        return ['invoice' => $invoiceData, 'items' => $items];
    }
}
