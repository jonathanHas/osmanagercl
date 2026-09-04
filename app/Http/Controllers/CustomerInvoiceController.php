<?php

namespace App\Http\Controllers;

use App\Http\Requests\CustomerInvoiceRequest;
use App\Models\CustomerInvoice;
use App\Services\CustomerInvoiceService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

class CustomerInvoiceController extends Controller
{
    public function __construct(private readonly CustomerInvoiceService $service) {}

    public function index(Request $request)
    {
        $query = CustomerInvoice::with('customer', 'creator', 'allocations.payment');

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

    public function store(CustomerInvoiceRequest $request)
    {
        $data = $request->invoicePayload();
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

    public function edit(CustomerInvoice $customerInvoice, Request $request)
    {
        $user = $request->user();
        $isAdminEdit = ! $customerInvoice->isEditable() && $customerInvoice->isAdminEditable() && $user->isAdmin();

        abort_unless($customerInvoice->isEditable() || $isAdminEdit, 403, 'This invoice cannot be edited.');

        $customerInvoice->load('items', 'customer');

        return view('customer-invoices.create', [
            'invoice' => $customerInvoice,
            'adminEdit' => $isAdminEdit,
        ]);
    }

    public function update(CustomerInvoiceRequest $request, CustomerInvoice $customerInvoice)
    {
        $user = $request->user();
        $isAdminEdit = ! $customerInvoice->isEditable() && $customerInvoice->isAdminEditable() && $user->isAdmin();

        abort_unless($customerInvoice->isEditable() || $isAdminEdit, 403, 'This invoice cannot be edited.');

        $data = $request->invoicePayload();

        $customerInvoice->update($data['invoice']);
        $this->service->syncItems($customerInvoice, $data['items'], force: $isAdminEdit);

        if ($isAdminEdit) {
            $this->service->markAdminEdited($customerInvoice);

            return redirect()->route('customer-invoices.show', $customerInvoice)
                ->with('status', "Invoice {$customerInvoice->invoice_number} updated (admin edit logged).");
        }

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

    public function unvoid(CustomerInvoice $customerInvoice, Request $request)
    {
        abort_unless($request->user()->isAdmin(), 403, 'Only admins can unvoid an invoice.');
        abort_unless($customerInvoice->isVoid(), 422, 'Only a voided invoice can be unvoided.');

        $this->service->unvoid($customerInvoice);

        return redirect()->route('customer-invoices.show', $customerInvoice)
            ->with('status', 'Invoice unvoided — restored to Issued.');
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
}
