<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Arr;
use Illuminate\Validation\Validator;

class CustomerInvoiceRequest extends FormRequest
{
    /**
     * The only line-item keys accepted from the client.
     *
     * Whitelisted server-side so a stale cached JS bundle (or a crafted payload)
     * cannot smuggle extra attributes into CustomerInvoiceItem::create().
     */
    private const ITEM_KEYS = [
        'pos_product_id',
        'pos_product_code',
        'description',
        'quantity',
        'unit_price',
        'vat_rate',
    ];

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // The route group already applies permission:customer-invoices.manage,
        // and update() keeps its own draft/admin-edit abort_unless check.
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'customer_id' => ['nullable', 'exists:App\Models\Customer,id'],
            'customer_name' => ['required', 'string', 'max:255'],
            'customer_address' => ['nullable', 'string', 'max:1000'],
            'customer_vat_number' => ['nullable', 'string', 'max:64'],
            'customer_email' => ['nullable', 'email', 'max:255'],
            'issue_date' => ['required', 'date'],
            'due_date' => ['nullable', 'date', 'after_or_equal:issue_date'],
            'discount_percent' => ['nullable', 'numeric', 'gte:0', 'lte:100'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'items' => ['required', 'array', 'min:1', 'max:500'],
            'items.*.pos_product_id' => ['nullable', 'string', 'max:64'],
            'items.*.pos_product_code' => ['nullable', 'string', 'max:64'],
            'items.*.description' => ['required', 'string', 'max:255'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0'],
            'items.*.unit_price' => ['required', 'numeric', 'gte:0'],
            'items.*.vat_rate' => ['required', 'numeric', 'gte:0', 'lte:1'],

            // Transport fields (see prepareForValidation) — never persisted.
            'items_json' => ['nullable', 'string'],
            'items_count' => ['nullable', 'integer'],
        ];
    }

    /**
     * Get custom error messages.
     *
     * The :position placeholder turns "The items.0.description field is required."
     * into "Line 1 needs a description." — the raw keys read as noise to users.
     */
    public function messages(): array
    {
        return [
            'customer_name.required' => 'A customer name is required.',
            'items.required' => 'Add at least one line item before saving.',
            'items.min' => 'Add at least one line item before saving.',
            'items.max' => 'An invoice cannot have more than 500 line items.',
            'due_date.after_or_equal' => 'The due date cannot be before the issue date.',
            'items.*.description.required' => 'Line :position needs a description.',
            'items.*.description.max' => 'Line :position has a description longer than 255 characters.',
            'items.*.quantity.required' => 'Line :position needs a quantity.',
            'items.*.quantity.gt' => 'Line :position must have a quantity greater than 0.',
            'items.*.quantity.numeric' => 'Line :position has an invalid quantity.',
            'items.*.unit_price.required' => 'Line :position needs a unit price.',
            'items.*.unit_price.gte' => 'Line :position cannot have a negative unit price.',
            'items.*.unit_price.numeric' => 'Line :position has an invalid unit price.',
            'items.*.vat_rate.required' => 'Line :position needs a VAT rate.',
            'items.*.vat_rate.numeric' => 'Line :position has an invalid VAT rate.',
            'items.*.vat_rate.gte' => 'Line :position has an invalid VAT rate.',
            'items.*.vat_rate.lte' => 'Line :position has an invalid VAT rate.',
        ];
    }

    /**
     * Prepare the data for validation.
     *
     * Line items arrive as a single JSON field rather than 6 inputs per line.
     * The old per-item inputs blew past PHP's max_input_vars (1000) at roughly
     * 165 lines, and PHP truncates silently — the tail simply never arrived and
     * the save failed on a half-delivered item with no explanation.
     *
     * Merging here (rather than decoding in the controller) means the
     * items.*.field rules still apply, the error bag keys stay items.N.field,
     * and the decoded array is what gets flashed for old() repopulation.
     */
    protected function prepareForValidation(): void
    {
        $json = (string) $this->input('items_json', '');

        if ($json !== '') {
            $decoded = json_decode($json, true);

            if (is_array($decoded)) {
                $this->merge([
                    'items' => collect($decoded)
                        ->map(fn ($item) => Arr::only((array) $item, self::ITEM_KEYS))
                        ->values()
                        ->all(),
                ]);
            }
            // A non-empty but undecodable payload falls through to the
            // after() hook, which reports it as corruption in transit rather
            // than the misleading "The items field is required."
        }
    }

    /**
     * Additional validation run after the rules pass.
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                $json = (string) $this->input('items_json', '');

                if ($json !== '' && ! is_array(json_decode($json, true))) {
                    $validator->errors()->add(
                        'items',
                        'Your invoice data was corrupted in transit, so nothing was saved. '.
                        'Please try saving again.'
                    );

                    return;
                }

                $reported = (int) $this->input('items_count', -1);
                $received = count((array) $this->input('items', []));

                if ($reported > 0 && $received < $reported) {
                    $maxInputVars = (int) ini_get('max_input_vars');

                    $validator->errors()->add('items', sprintf(
                        'Only %d of your %d line items reached the server, so nothing was saved. '.
                        'Please split this into two invoices, or ask an admin to raise the '.
                        'max_input_vars limit (currently %d).',
                        $received,
                        $reported,
                        $maxInputVars
                    ));
                }
            },
        ];
    }

    /**
     * The validated invoice attributes and line items, ready for the service.
     *
     * @return array{invoice: array<string, mixed>, items: array<int, array<string, mixed>>}
     */
    public function invoicePayload(): array
    {
        $validated = $this->validated();

        $invoice = collect($validated)
            ->except(['items', 'items_json', 'items_count'])
            ->all();

        $items = collect($validated['items'])->values()->all();

        return ['invoice' => $invoice, 'items' => $items];
    }
}
