<?php

namespace App\Http\Controllers;

use App\Http\Requests\CustomerPaymentAllocationRequest;
use App\Models\CustomerInvoice;
use App\Models\CustomerPayment;
use App\Services\CustomerPaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Matching already-recorded payments to invoices, from both directions:
 * editing a payment's allocations, and pulling a customer's unapplied credit
 * onto one of their outstanding invoices.
 *
 * The payment itself is never edited here — amount, date, method and till are
 * fixed once banked. Only the matching changes.
 */
class CustomerPaymentAllocationController extends Controller
{
    public function __construct(
        private readonly CustomerPaymentService $service,
    ) {}

    public function edit(CustomerPayment $customerPayment): View
    {
        abort_if($customerPayment->isVoid(), 422, 'A voided payment cannot be re-allocated.');

        $customerPayment->load('customer', 'allocations.invoice', 'creator', 'lastMatcher');

        return view('customer-payments.allocations', [
            'payment' => $customerPayment,
            'invoiceRows' => $this->service->invoiceRowsFor($customerPayment->customer, $customerPayment->id),
        ]);
    }

    public function update(CustomerPaymentAllocationRequest $request, CustomerPayment $customerPayment): RedirectResponse
    {
        abort_if($customerPayment->isVoid(), 422, 'A voided payment cannot be re-allocated.');

        try {
            $this->service->reallocate($customerPayment, $request->allocations());
        } catch (\DomainException $e) {
            return back()->withInput()->withErrors(['allocations' => $e->getMessage()]);
        }

        return redirect()->route('customer-payments.show', $customerPayment)
            ->with('status', 'Allocations updated.');
    }

    /**
     * One-click oldest-first matching for a payment that is sitting on credit.
     */
    public function auto(CustomerPayment $customerPayment): RedirectResponse
    {
        abort_if($customerPayment->isVoid(), 422, 'A voided payment cannot be re-allocated.');

        $allocations = $this->service->autoAllocate(
            $customerPayment->customer,
            (float) $customerPayment->amount,
            $customerPayment->id,
        );

        try {
            $this->service->reallocate($customerPayment, $allocations);
        } catch (\DomainException $e) {
            return back()->withErrors(['allocations' => $e->getMessage()]);
        }

        $payment = $customerPayment->fresh();
        $status = $payment->unallocated_amount > 0.005
            ? sprintf('Applied €%.2f oldest first. €%.2f remains as on-account credit.',
                $payment->total_allocated, $payment->unallocated_amount)
            : sprintf('Applied €%.2f oldest first. Payment fully allocated.', $payment->total_allocated);

        return redirect()->route('customer-payments.show', $customerPayment)->with('status', $status);
    }

    /**
     * Confirmation page for applying a customer's unapplied credit to one invoice.
     */
    public function creditForm(CustomerInvoice $customerInvoice): View
    {
        abort_if($customerInvoice->status === CustomerInvoice::STATUS_VOID, 422, 'This invoice is void.');

        $customerInvoice->load('customer');
        $credit = $this->service->unappliedCreditFor($customerInvoice->customer);

        return view('customer-invoices.apply-credit', [
            'invoice' => $customerInvoice,
            'creditPayments' => $credit,
            'availableCredit' => round($credit->sum('unapplied'), 2),
        ]);
    }

    public function applyCredit(CustomerInvoice $customerInvoice): RedirectResponse
    {
        abort_if($customerInvoice->status === CustomerInvoice::STATUS_VOID, 422, 'This invoice is void.');

        try {
            $result = $this->service->applyCreditToInvoice($customerInvoice);
        } catch (\DomainException $e) {
            return back()->withErrors(['credit' => $e->getMessage()]);
        }

        $status = $result['applied'] > 0.005
            ? sprintf('Applied €%.2f from %d payment(s).', $result['applied'], count($result['rows']))
            : 'No unapplied credit was available to apply.';

        return redirect()->route('customer-invoices.show', $customerInvoice)->with('status', $status);
    }

    /**
     * JSON endpoint — invoices this payment may be allocated to, with headroom
     * that ignores the payment's own existing rows.
     */
    public function allocatableInvoicesApi(CustomerPayment $customerPayment): JsonResponse
    {
        return response()->json([
            'data' => $this->service->invoiceRowsFor($customerPayment->customer, $customerPayment->id),
        ]);
    }
}
