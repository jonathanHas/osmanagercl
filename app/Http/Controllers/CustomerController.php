<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CustomerController extends Controller
{
    public function search(Request $request): JsonResponse
    {
        $term = trim((string) $request->query('q', ''));

        $query = Customer::query();

        if ($term !== '') {
            $like = '%'.$term.'%';
            $query->where(function ($q) use ($like) {
                $q->where('name', 'like', $like)
                    ->orWhere('email', 'like', $like)
                    ->orWhere('phone', 'like', $like)
                    ->orWhere('vat_number', 'like', $like);
            });
        }

        $customers = $query->orderBy('name')->limit(25)->get([
            'id', 'name', 'email', 'phone',
            'address_line1', 'address_line2', 'city', 'postcode', 'country',
            'vat_number', 'default_discount_percent',
        ]);

        return response()->json(['data' => $customers]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:64'],
            'address_line1' => ['nullable', 'string', 'max:255'],
            'address_line2' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:128'],
            'postcode' => ['nullable', 'string', 'max:32'],
            'country' => ['nullable', 'string', 'size:2'],
            'vat_number' => ['nullable', 'string', 'max:64'],
            'default_discount_percent' => ['nullable', 'numeric', 'gte:0', 'lte:100'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $validated['country'] = $validated['country'] ?? 'IE';
        $validated['created_by'] = Auth::id();

        $customer = Customer::create($validated);

        return response()->json(['data' => $customer], 201);
    }
}
