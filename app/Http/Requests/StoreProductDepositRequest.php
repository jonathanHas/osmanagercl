<?php

namespace App\Http\Requests;

use App\Models\BarrelCode;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Add a product to a deposit tier by hand on /deposits.
 */
class StoreProductDepositRequest extends FormRequest
{
    public function authorize(): bool
    {
        // The route group applies permission:deliveries.manage.
        return true;
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'product_id' => ['required', 'string', 'exists:App\Models\Product,ID'],
            'barrel_code_id' => [
                'required',
                'integer',
                // Only a tier that is charged to customers can be picked.
                Rule::exists(BarrelCode::class, 'id')->where('charge_customer', true),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'product_id.required' => 'Pick a product first.',
            'barrel_code_id.exists' => 'Pick a deposit tier that is charged to customers.',
        ];
    }
}
