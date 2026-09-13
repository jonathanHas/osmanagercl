<?php

namespace App\Http\Requests;

use App\Models\CustomerRequestItem;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCustomerRequestItemStatusRequest extends FormRequest
{
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
            'status' => ['required', 'string', Rule::in(CustomerRequestItem::STATUSES)],
            'note' => ['nullable', 'string', 'max:255'],
        ];
    }
}
