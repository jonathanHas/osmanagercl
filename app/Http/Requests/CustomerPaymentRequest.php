<?php

namespace App\Http\Requests;

/**
 * Recording a new payment: the payment's own fields plus the shared allocation
 * rows. Till handling (a till is required for till methods and forbidden for
 * online ones) stays in the controller, which has the till repository.
 */
class CustomerPaymentRequest extends CustomerPaymentAllocationRequest
{
    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return array_merge([
            'customer_id' => ['required', 'exists:App\Models\Customer,id'],
            'payment_date' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'method' => ['required', 'in:card_till,cash_till,online'],
            'till_id' => ['nullable', 'string', 'max:32'],
            'till_name' => ['nullable', 'string', 'max:255'],
            'reference' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ], parent::rules());
    }
}
