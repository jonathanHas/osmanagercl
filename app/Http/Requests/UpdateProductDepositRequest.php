<?php

namespace App\Http\Requests;

use App\Models\ProductDeposit;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Confirm, reject or re-tier one product deposit row on /deposits.
 */
class UpdateProductDepositRequest extends FormRequest
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
            'status' => ['required_without:barrel_code_id', Rule::in(ProductDeposit::STATUSES)],
            'barrel_code_id' => ['required_without:status', 'integer', 'exists:App\Models\BarrelCode,id'],
        ];
    }
}
