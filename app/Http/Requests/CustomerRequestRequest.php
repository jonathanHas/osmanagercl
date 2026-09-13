<?php

namespace App\Http\Requests;

use App\Models\CustomerRequest;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Arr;
use Illuminate\Validation\Validator;

class CustomerRequestRequest extends FormRequest
{
    /** The only line keys accepted from the client. */
    private const ITEM_KEYS = ['id', 'product_code', 'product_name', 'description', 'quantity', 'notes'];

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // The route group already applies permission:customer-requests.manage.
        return true;
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'customer_name' => ['required', 'string', 'max:120'],
            'customer_phone' => ['nullable', 'string', 'max:40'],
            'wanted_on' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'items' => ['required', 'array', 'min:1', 'max:50'],
            'items.*.id' => ['nullable', 'integer', 'exists:App\Models\CustomerRequestItem,id'],
            'items.*.product_code' => ['nullable', 'string', 'max:64'],
            'items.*.product_name' => ['nullable', 'string', 'max:255'],
            // A stocked line can arrive with only its barcode; the service fills the
            // description from the POS product name. Free-text lines need a description.
            'items.*.description' => ['required_without:items.*.product_code', 'nullable', 'string', 'max:255'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0', 'max:9999'],
            'items.*.notes' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'customer_name.required' => 'A customer name is required.',
            'items.required' => 'Add at least one item before saving.',
            'items.min' => 'Add at least one item before saving.',
            'items.max' => 'A request cannot have more than 50 items.',
            'items.*.description.required_without' => 'Line :position needs a product or description.',
            'items.*.description.max' => 'Line :position has a description longer than 255 characters.',
            'items.*.quantity.required' => 'Line :position needs a quantity.',
            'items.*.quantity.numeric' => 'Line :position has an invalid quantity.',
            'items.*.quantity.gt' => 'Line :position must have a quantity greater than 0.',
            'items.*.quantity.max' => 'Line :position has a quantity that is too large.',
            'items.*.id.exists' => 'Line :position no longer exists — reload the page and try again.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $items = $this->input('items');

        if (is_array($items)) {
            $this->merge([
                'items' => collect($items)
                    ->map(fn ($item) => Arr::only((array) $item, self::ITEM_KEYS))
                    ->values()
                    ->all(),
            ]);
        }
    }

    /**
     * On update, every line id must belong to the request being edited.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            /** @var CustomerRequest|null $request */
            $request = $this->route('customerRequest');

            foreach ((array) $this->input('items', []) as $index => $item) {
                $id = $item['id'] ?? null;
                if ($id === null || $id === '') {
                    continue;
                }

                if ($request === null || ! $request->items()->whereKey((int) $id)->exists()) {
                    $validator->errors()->add("items.{$index}.id", 'Line '.($index + 1).' does not belong to this request.');
                }
            }
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function requestPayload(): array
    {
        return Arr::only($this->validated(), ['customer_name', 'customer_phone', 'wanted_on', 'notes']);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function itemsPayload(): array
    {
        return array_values($this->validated()['items'] ?? []);
    }
}
