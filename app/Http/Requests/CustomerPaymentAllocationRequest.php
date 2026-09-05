<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Shared allocation-row validation for every path that matches a payment to
 * invoices — recording a new payment and re-allocating an existing one.
 *
 * Amount/customer-level invariants (allocations vs payment total, same customer,
 * per-invoice headroom) live in CustomerPaymentService and surface as
 * DomainException, since they need database state this class does not have.
 */
class CustomerPaymentAllocationRequest extends FormRequest
{
    public function authorize(): bool
    {
        // The route group already applies permission:customer-invoices.manage.
        return true;
    }

    /**
     * Drop untouched allocation rows (amount blank or 0) so validation only sees
     * invoices the user actually intends to pay. The allocation table always
     * submits a row per open invoice, most with amount = 0.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'allocations' => collect($this->input('allocations', []))
                ->filter(fn ($row) => is_array($row)
                    && isset($row['amount'])
                    && $row['amount'] !== ''
                    && (float) $row['amount'] > 0)
                ->values()
                ->all(),
        ]);
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'allocations' => ['nullable', 'array'],
            'allocations.*.customer_invoice_id' => ['required_with:allocations.*.amount', 'integer', 'exists:App\Models\CustomerInvoice,id'],
            'allocations.*.amount' => ['required_with:allocations.*.customer_invoice_id', 'numeric', 'gt:0'],
        ];
    }

    /**
     * @return array<int, array{customer_invoice_id:int, amount:float}>
     */
    public function allocations(): array
    {
        return $this->validated('allocations') ?? [];
    }
}
