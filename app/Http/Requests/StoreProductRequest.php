<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreProductRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Allow authenticated users to create products
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            // Basic Information
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:50|unique:pos.PRODUCTS,CODE',
            'reference' => 'nullable|string|max:100',

            // Pricing
            'price_buy' => 'required|numeric|min:0|max:999999.99',
            'price_sell' => 'required|numeric|min:0|max:999999.9999',
            'tax_category' => 'required|string|exists:pos.TAXCATEGORIES,ID',

            // Category
            'category' => 'nullable|string|exists:pos.CATEGORIES,ID',

            // Configuration
            'is_service' => 'boolean',
            'is_scale' => 'boolean',
            'is_kitchen' => 'boolean',
            'print_kb' => 'boolean',
            'send_status' => 'boolean',
            'is_com' => 'boolean',
            'is_vprice' => 'integer|min:0|max:255',
            'is_ver_patrib' => 'integer|min:0|max:255',
            'warranty' => 'nullable|integer|min:0|max:9999',

            // Stock Information
            'initial_stock' => 'nullable|numeric|min:0|max:999999.99',
            'stock_cost' => 'nullable|numeric|min:0|max:999999.99',
            'stock_volume' => 'nullable|numeric|min:0|max:999999.99',

            // Supplier Information (optional)
            'supplier_id' => 'nullable|exists:pos.suppliers,SupplierID',
            'supplier_code' => 'nullable|string|max:100',
            'units_per_case' => 'nullable|integer|min:1|max:9999',
            'supplier_cost' => 'nullable|numeric|min:0|max:999999.99',

            // Stock Management
            'include_in_stocking' => 'boolean',
        ];
    }

    /**
     * Get custom error messages.
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Product name is required.',
            'code.required' => 'Product code is required.',
            'code.unique' => 'This product code already exists in the system.',
            'price_buy.required' => 'Cost price is required.',
            'price_sell.required' => 'Selling price is required.',
            'tax_category.required' => 'Tax category is required.',
            'tax_category.exists' => 'Selected tax category does not exist.',
            'category.exists' => 'Selected category does not exist.',
        ];
    }

    /**
     * Get custom attribute names for validation errors.
     */
    public function attributes(): array
    {
        return [
            'name' => 'product name',
            'code' => 'product code',
            'price_buy' => 'cost price',
            'price_sell' => 'selling price',
            'tax_category' => 'tax category',
            'initial_stock' => 'initial stock quantity',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Convert checkbox values to proper booleans
        $this->merge([
            'is_service' => $this->boolean('is_service'),
            'is_scale' => $this->boolean('is_scale'),
            'is_kitchen' => $this->boolean('is_kitchen'),
            'print_kb' => $this->boolean('print_kb'),
            'send_status' => $this->boolean('send_status'),
            'is_com' => $this->boolean('is_com'),
            'include_in_stocking' => $this->boolean('include_in_stocking'),
        ]);

        // Convert integer fields
        $this->merge([
            'is_vprice' => $this->integer('is_vprice') ?? 0,
            'is_ver_patrib' => $this->integer('is_ver_patrib') ?? 0,
        ]);
    }
}
