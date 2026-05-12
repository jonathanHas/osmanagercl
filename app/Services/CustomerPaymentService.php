<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\CustomerInvoice;
use App\Models\CustomerPayment;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class CustomerPaymentService
{
    /**
     * Record a customer payment with optional invoice allocations.
     *
     * @param  array  $paymentData  customer_id, payment_date, amount, method, till_id?, till_name?, reference?, notes?
     * @param  array  $allocations  list of ['customer_invoice_id' => int, 'amount' => float]
     */
    public function recordPayment(array $paymentData, array $allocations = []): CustomerPayment
    {
        return DB::transaction(function () use ($paymentData, $allocations) {
            $allocations = array_values(array_filter($allocations, fn ($a) => (float) ($a['amount'] ?? 0) > 0));
            $allocSum = array_sum(array_map(fn ($a) => (float) $a['amount'], $allocations));
            $amount = (float) $paymentData['amount'];

            if ($allocSum > $amount + 0.005) {
                throw new \DomainException(sprintf(
                    'Allocations (€%.2f) exceed payment amount (€%.2f).',
                    $allocSum,
                    $amount,
                ));
            }

            // Same-customer guard — every allocated invoice must belong to the payment's customer.
            if (! empty($allocations)) {
                $invoiceIds = array_column($allocations, 'customer_invoice_id');
                $mismatch = CustomerInvoice::whereIn('id', $invoiceIds)
                    ->where('customer_id', '!=', $paymentData['customer_id'])
                    ->exists();
                if ($mismatch) {
                    throw new \DomainException('All allocated invoices must belong to the same customer as the payment.');
                }
            }

            $payment = CustomerPayment::create(array_merge($paymentData, [
                'created_by' => Auth::id(),
            ]));

            foreach ($allocations as $alloc) {
                $payment->allocations()->create([
                    'customer_invoice_id' => $alloc['customer_invoice_id'],
                    'amount' => $alloc['amount'],
                ]);
            }

            return $payment->load('allocations.invoice', 'customer');
        });
    }

    public function void(CustomerPayment $payment): CustomerPayment
    {
        if ($payment->isVoid()) {
            return $payment;
        }
        $payment->voided_at = now();
        $payment->voided_by = Auth::id();
        $payment->save();

        return $payment;
    }

    /**
     * Allocate $amount across this customer's outstanding invoices, oldest first.
     * Returns an allocation array suitable for recordPayment().
     */
    public function autoAllocate(Customer $customer, float $amount): array
    {
        $remaining = round($amount, 2);
        $allocations = [];

        $openInvoices = $customer->invoices()
            ->where('status', '!=', CustomerInvoice::STATUS_VOID)
            ->with('allocations')
            ->orderBy('issue_date')
            ->orderBy('id')
            ->get()
            ->filter(fn ($inv) => $inv->outstanding_amount > 0.005);

        foreach ($openInvoices as $invoice) {
            if ($remaining <= 0.005) {
                break;
            }
            $apply = min($remaining, $invoice->outstanding_amount);
            $allocations[] = [
                'customer_invoice_id' => $invoice->id,
                'amount' => round($apply, 2),
            ];
            $remaining = round($remaining - $apply, 2);
        }

        return $allocations;
    }
}
