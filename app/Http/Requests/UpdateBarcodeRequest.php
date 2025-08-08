<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateBarcodeRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Only allow authenticated users (can be made more restrictive if needed)
        return auth()->check();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $productId = $this->route('product');

        return [
            'barcode' => [
                'required',
                'string',
                'max:255',
                // Ensure barcode is unique in PRODUCTS table, excluding current product
                Rule::unique('pos.PRODUCTS', 'CODE')->ignore($productId, 'ID'),
            ],
            'confirm' => 'required|accepted', // Require confirmation checkbox
        ];
    }

    /**
     * Get custom error messages.
     */
    public function messages(): array
    {
        return [
            'barcode.required' => 'Barcode is required.',
            'barcode.unique' => 'This barcode is already in use by another product.',
            'barcode.max' => 'Barcode cannot be longer than 255 characters.',
            'confirm.required' => 'You must confirm that you understand the implications of changing the barcode.',
            'confirm.accepted' => 'You must check the confirmation box to proceed.',
        ];
    }
}
