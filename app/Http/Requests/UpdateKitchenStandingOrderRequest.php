<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateKitchenStandingOrderRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     * The auth middleware on the kitchen route group gates access.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // Standing weekly quantities are whole cases, keyed by POS product id.
            'qty' => ['nullable', 'array'],
            'qty.*' => ['nullable', 'integer', 'min:0', 'max:999'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'qty.*.integer' => 'Quantities must be whole cases.',
            'qty.*.min' => 'Quantities cannot be negative.',
            'qty.*.max' => 'Quantities cannot exceed 999 cases.',
        ];
    }
}
