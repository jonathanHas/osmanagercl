<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class CustomerController extends Controller
{
    /**
     * Validation rules shared by HTML and API store/update.
     */
    private function rules(?Customer $existing = null): array
    {
        return [
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
        ];
    }

    // ==================== HTML CRUD ====================

    public function index(Request $request): View
    {
        $query = Customer::query()->withCount('invoices');

        if ($term = trim((string) $request->query('q', ''))) {
            $like = '%'.$term.'%';
            $query->where(function ($q) use ($like) {
                $q->where('name', 'like', $like)
                    ->orWhere('email', 'like', $like)
                    ->orWhere('phone', 'like', $like)
                    ->orWhere('city', 'like', $like)
                    ->orWhere('vat_number', 'like', $like);
            });
        }

        if ($request->boolean('wholesale_only')) {
            $query->where('default_discount_percent', '>', 0);
        }

        $customers = $query->orderBy('name')->paginate(25)->withQueryString();

        return view('customers.index', compact('customers'));
    }

    public function create(): View
    {
        return view('customers.create', ['customer' => null]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate($this->rules());
        $data['country'] = $data['country'] ?? 'IE';
        $data['created_by'] = Auth::id();

        $customer = Customer::create($data);

        return redirect()->route('customers.show', $customer)->with('status', 'Customer created.');
    }

    public function show(Customer $customer): View
    {
        $customer->load(['invoices' => fn ($q) => $q->orderByDesc('issue_date')->orderByDesc('id')->limit(50)]);

        return view('customers.show', compact('customer'));
    }

    public function edit(Customer $customer): View
    {
        return view('customers.create', ['customer' => $customer]);
    }

    public function update(Request $request, Customer $customer): RedirectResponse
    {
        $data = $request->validate($this->rules($customer));
        $data['country'] = $data['country'] ?? $customer->country ?? 'IE';
        $customer->update($data);

        return redirect()->route('customers.show', $customer)->with('status', 'Customer updated.');
    }

    public function destroy(Customer $customer): RedirectResponse
    {
        if ($customer->invoices()->exists()) {
            return redirect()->route('customers.show', $customer)
                ->with('error', 'This customer has invoices and cannot be deleted. Soft-delete only.');
        }

        $customer->delete();

        return redirect()->route('customers.index')->with('status', 'Customer deleted.');
    }

    // ==================== JSON API (used by the invoice composer modal/typeahead) ====================

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

    public function apiStore(Request $request): JsonResponse
    {
        $data = $request->validate($this->rules());
        $data['country'] = $data['country'] ?? 'IE';
        $data['created_by'] = Auth::id();

        $customer = Customer::create($data);

        return response()->json(['data' => $customer], 201);
    }
}
