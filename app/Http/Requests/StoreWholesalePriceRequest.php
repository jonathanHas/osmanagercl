<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreWholesalePriceRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
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
        $recipe = $this->route('recipe');

        // Category and VAT are only asked for when there is nothing upstream to
        // inherit them from - no retail product and no wholesale product yet.
        $needsClassification = $recipe
            && ! $recipe->hasLinkedProduct()
            && ! $recipe->hasWholesaleProduct();

        return [
            // No floor at cost: a below-cost wholesale price is a business
            // decision, reported as a warning rather than blocked.
            'price_inc_vat' => ['required', 'numeric', 'min:0.01', 'max:99999.99'],
            // Capped below 100 - the target price formula divides by
            // (1 - target/100), which diverges at exactly 100.
            'target_margin' => ['nullable', 'numeric', 'min:0', 'max:99.9'],
            'category' => [$needsClassification ? 'required' : 'nullable', 'string', 'exists:App\Models\Category,ID'],
            'tax_category' => [$needsClassification ? 'required' : 'nullable', 'string', 'exists:App\Models\TaxCategory,ID'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'price_inc_vat.required' => 'Enter a wholesale price (including VAT).',
            'price_inc_vat.min' => 'The wholesale price must be at least €0.01.',
            'category.required' => "Choose a category - this recipe isn't linked to a retail product, so there's nothing to inherit from.",
            'tax_category.required' => "Choose a VAT rate - this recipe isn't linked to a retail product, so there's nothing to inherit from.",
        ];
    }
}
