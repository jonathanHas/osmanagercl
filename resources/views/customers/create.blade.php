<x-admin-layout>
    @php
        $isEdit = ! is_null($customer);
        $action = $isEdit ? route('customers.update', $customer) : route('customers.store');
    @endphp

    <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
        <div class="flex justify-between items-center mb-6">
            <h2 class="text-2xl font-bold text-gray-100">
                {{ $isEdit ? 'Edit Customer' : 'New Customer' }}
            </h2>
            <a href="{{ route('customers.index') }}" class="text-gray-400 hover:text-gray-200">← Back</a>
        </div>

        @if ($errors->any())
            <div class="mb-4 bg-red-800 text-white p-3 rounded text-sm">
                <ul class="list-disc pl-5">
                    @foreach ($errors->all() as $err)
                        <li>{{ $err }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ $action }}" class="bg-gray-800 p-6 rounded space-y-4">
            @csrf
            @if ($isEdit) @method('PUT') @endif

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="md:col-span-2">
                    <label class="block text-xs text-gray-400 mb-1">Name *</label>
                    <input type="text" name="name" required value="{{ old('name', $customer?->name) }}"
                           class="w-full bg-gray-900 border border-gray-700 rounded px-3 py-2 text-gray-100">
                </div>
                <div>
                    <label class="block text-xs text-gray-400 mb-1">Email</label>
                    <input type="email" inputmode="email" name="email" value="{{ old('email', $customer?->email) }}"
                           class="w-full bg-gray-900 border border-gray-700 rounded px-3 py-2 text-gray-100">
                </div>
                <div>
                    <label class="block text-xs text-gray-400 mb-1">Phone</label>
                    <input type="tel" inputmode="tel" name="phone" value="{{ old('phone', $customer?->phone) }}"
                           class="w-full bg-gray-900 border border-gray-700 rounded px-3 py-2 text-gray-100">
                </div>
                <div class="md:col-span-2">
                    <label class="block text-xs text-gray-400 mb-1">Address line 1</label>
                    <input type="text" name="address_line1" value="{{ old('address_line1', $customer?->address_line1) }}"
                           class="w-full bg-gray-900 border border-gray-700 rounded px-3 py-2 text-gray-100">
                </div>
                <div class="md:col-span-2">
                    <label class="block text-xs text-gray-400 mb-1">Address line 2</label>
                    <input type="text" name="address_line2" value="{{ old('address_line2', $customer?->address_line2) }}"
                           class="w-full bg-gray-900 border border-gray-700 rounded px-3 py-2 text-gray-100">
                </div>
                <div>
                    <label class="block text-xs text-gray-400 mb-1">City</label>
                    <input type="text" name="city" value="{{ old('city', $customer?->city) }}"
                           class="w-full bg-gray-900 border border-gray-700 rounded px-3 py-2 text-gray-100">
                </div>
                <div>
                    <label class="block text-xs text-gray-400 mb-1">Postcode</label>
                    <input type="text" name="postcode" value="{{ old('postcode', $customer?->postcode) }}"
                           class="w-full bg-gray-900 border border-gray-700 rounded px-3 py-2 text-gray-100">
                </div>
                <div>
                    <label class="block text-xs text-gray-400 mb-1">Country (ISO 2)</label>
                    <input type="text" name="country" maxlength="2" value="{{ old('country', $customer?->country ?? 'IE') }}"
                           class="w-full bg-gray-900 border border-gray-700 rounded px-3 py-2 text-gray-100 uppercase">
                </div>
                <div>
                    <label class="block text-xs text-gray-400 mb-1">VAT Number</label>
                    <input type="text" name="vat_number" value="{{ old('vat_number', $customer?->vat_number) }}"
                           class="w-full bg-gray-900 border border-gray-700 rounded px-3 py-2 text-gray-100">
                </div>
                <div class="md:col-span-2">
                    <label class="block text-xs text-gray-400 mb-1">
                        Default wholesale discount %
                        <span class="text-gray-500 ml-1">(auto-applies on new invoices for this customer)</span>
                    </label>
                    <input type="number" step="0.01" min="0" max="100" inputmode="decimal" name="default_discount_percent"
                           value="{{ old('default_discount_percent', $customer?->default_discount_percent ?? 0) }}"
                           class="w-32 bg-gray-900 border border-gray-700 rounded px-3 py-2 text-gray-100 text-right">
                </div>
                <div class="md:col-span-2">
                    <label class="block text-xs text-gray-400 mb-1">
                        Payment terms (days)
                        <span class="text-gray-500 ml-1">(used to age invoices that have no due date set)</span>
                    </label>
                    <input type="number" step="1" min="0" max="365" inputmode="numeric" name="payment_terms_days"
                           value="{{ old('payment_terms_days', $customer?->payment_terms_days ?? \App\Models\Customer::DEFAULT_PAYMENT_TERMS_DAYS) }}"
                           class="w-32 bg-gray-900 border border-gray-700 rounded px-3 py-2 text-gray-100 text-right">
                </div>
                <div class="md:col-span-2">
                    <label class="inline-flex items-center text-sm text-gray-300">
                        <input type="checkbox" name="send_statements" value="1"
                               @checked(old('send_statements', $customer?->send_statements ?? false))
                               class="bg-gray-900 border-gray-700 rounded mr-2">
                        Email statements of account to this customer
                    </label>
                    <p class="text-xs text-gray-500 mt-1 ml-6">
                        Included in the monthly statement run. Requires an email address.
                    </p>
                </div>
                <div class="md:col-span-2">
                    <label class="block text-xs text-gray-400 mb-1">Notes</label>
                    <textarea name="notes" rows="3"
                              class="w-full bg-gray-900 border border-gray-700 rounded px-3 py-2 text-gray-100">{{ old('notes', $customer?->notes) }}</textarea>
                </div>
            </div>

            <div class="flex justify-end gap-2 pt-4 border-t border-gray-700">
                <a href="{{ $isEdit ? route('customers.show', $customer) : route('customers.index') }}"
                   class="px-4 py-2 text-gray-300 hover:text-white">Cancel</a>
                <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded">
                    {{ $isEdit ? 'Save changes' : 'Create customer' }}
                </button>
            </div>
        </form>
    </div>
</x-admin-layout>
