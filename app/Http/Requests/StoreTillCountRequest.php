<?php

namespace App\Http\Requests;

use App\Services\Cash\TillCountService;
use Illuminate\Foundation\Http\FormRequest;

class StoreTillCountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('cash_reconciliation.create') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rules = [
            'session_id' => ['required', 'string', 'max:64'],
            'till_id' => ['required', 'integer', 'exists:App\Models\Till,id'],
            'note_float' => ['required', 'numeric', 'min:0', 'max:99999'],
            'coin_float' => ['nullable', 'numeric', 'min:0', 'max:99999'],
            'card' => ['nullable', 'numeric', 'min:0', 'max:999999'],
            'money_added' => ['nullable', 'numeric', 'min:0', 'max:99999'],
            'previous_float' => ['nullable', 'numeric', 'min:0', 'max:99999'],
            'payments' => ['nullable', 'array', 'max:20'],
            'payments.*.id' => ['nullable', 'string', 'max:64'],
            'payments.*.supplier_id' => ['nullable', 'string', 'max:64'],
            'payments.*.payee_name' => ['nullable', 'string', 'max:255'],
            'payments.*.amount' => ['nullable', 'numeric', 'min:0', 'max:99999'],
            'note' => ['nullable', 'string', 'max:1000'],
        ];

        foreach (array_keys(TillCountService::DENOMINATIONS) as $column) {
            $rules[$column] = ['nullable', 'integer', 'min:0', 'max:9999'];
        }

        return $rules;
    }
}
