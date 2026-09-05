<x-admin-layout>
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
        <div class="flex justify-between items-center mb-6">
            <h2 class="text-2xl font-bold text-gray-100">Customers</h2>
            <div class="flex items-center gap-2">
                <a href="{{ route('customers.debtors') }}"
                   class="bg-gray-700 hover:bg-gray-600 text-white py-2 px-4 rounded inline-flex items-center">
                    Aged Debtors
                </a>
                <a href="{{ route('customers.create') }}"
                   class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded inline-flex items-center">
                    + New Customer
                </a>
            </div>
        </div>

        @if (session('status'))
            <div class="mb-4 rounded bg-green-700 text-white px-4 py-2">{{ session('status') }}</div>
        @endif
        @if (session('error'))
            <div class="mb-4 rounded bg-red-700 text-white px-4 py-2">{{ session('error') }}</div>
        @endif

        <form method="GET" class="grid grid-cols-1 md:grid-cols-3 gap-3 mb-4 bg-gray-800 p-4 rounded">
            <div class="md:col-span-2">
                <label class="block text-xs text-gray-400 mb-1">Search</label>
                <input type="text" name="q" value="{{ request('q') }}"
                       placeholder="Name, email, phone, city, VAT…"
                       class="w-full bg-gray-900 border border-gray-700 rounded px-2 py-2 text-gray-100">
            </div>
            <div class="flex items-end gap-2">
                <label class="inline-flex items-center text-sm text-gray-300">
                    <input type="checkbox" name="wholesale_only" value="1" @checked(request('wholesale_only'))
                           class="bg-gray-900 border-gray-700 rounded mr-2">
                    Wholesale only
                </label>
                <label class="inline-flex items-center text-sm text-gray-300">
                    <input type="checkbox" name="owing" value="1" @checked(request('owing'))
                           class="bg-gray-900 border-gray-700 rounded mr-2">
                    Owing only
                </label>
                <button type="submit" class="bg-gray-700 hover:bg-gray-600 text-white px-4 py-2 rounded">Filter</button>
                <a href="{{ route('customers.index') }}" class="text-gray-400 hover:text-gray-200 px-2 py-2">Reset</a>
            </div>
        </form>

        <div class="bg-gray-800 rounded shadow overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-700 text-sm">
                <thead class="bg-gray-900 text-gray-400">
                    <tr>
                        <th class="px-4 py-2 text-left">Name</th>
                        <th class="px-4 py-2 text-left">Contact</th>
                        <th class="px-4 py-2 text-left">City</th>
                        <th class="px-4 py-2 text-right">Discount</th>
                        <th class="px-4 py-2 text-right">
                            <a href="{{ route('customers.index', array_merge(request()->query(), ['sort' => request('sort') === 'balance' ? null : 'balance'])) }}"
                               class="hover:text-gray-200 {{ request('sort') === 'balance' ? 'text-gray-200' : '' }}">
                                Balance @if (request('sort') === 'balance')↓@endif
                            </a>
                        </th>
                        <th class="px-4 py-2 text-right">Invoices</th>
                        <th class="px-4 py-2"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-700 text-gray-200">
                    @forelse ($customers as $customer)
                        <tr class="hover:bg-gray-700/40">
                            <td class="px-4 py-2 font-medium">
                                <a href="{{ route('customers.show', $customer) }}" class="text-blue-400 hover:text-blue-300">
                                    {{ $customer->name }}
                                </a>
                                @if ($customer->vat_number)
                                    <div class="text-xs text-gray-500">VAT {{ $customer->vat_number }}</div>
                                @endif
                            </td>
                            <td class="px-4 py-2 text-gray-300 text-xs">
                                @if ($customer->email)<div>{{ $customer->email }}</div>@endif
                                @if ($customer->phone)<div>{{ $customer->phone }}</div>@endif
                            </td>
                            <td class="px-4 py-2 text-gray-300">{{ $customer->city }}</td>
                            <td class="px-4 py-2 text-right">
                                @if ($customer->default_discount_percent > 0)
                                    <span class="text-xs px-2 py-0.5 rounded bg-green-800/50 text-green-300">
                                        {{ rtrim(rtrim(number_format($customer->default_discount_percent, 2), '0'), '.') }}%
                                    </span>
                                @else
                                    <span class="text-gray-600">—</span>
                                @endif
                            </td>
                            @php
                                $bal = round((float) ($customer->invoiced_total ?? 0) - (float) ($customer->paid_total ?? 0), 2);
                            @endphp
                            <td class="px-4 py-2 text-right font-mono {{ $bal > 0.005 ? 'text-red-400' : ($bal < -0.005 ? 'text-green-400' : 'text-gray-500') }}">
                                @if ($bal > 0.005)
                                    €{{ number_format($bal, 2) }}
                                @elseif ($bal < -0.005)
                                    €{{ number_format(abs($bal), 2) }} <span class="text-xs">cr</span>
                                @else
                                    —
                                @endif
                            </td>
                            <td class="px-4 py-2 text-right text-gray-300">{{ $customer->invoices_count }}</td>
                            <td class="px-4 py-2 text-right space-x-2 whitespace-nowrap">
                                <a href="{{ route('customers.show', $customer) }}" class="text-blue-400 hover:text-blue-300">View</a>
                                <a href="{{ route('customers.statement', $customer) }}" class="text-purple-400 hover:text-purple-300">Statement</a>
                                <a href="{{ route('customers.edit', $customer) }}" class="text-yellow-400 hover:text-yellow-300">Edit</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="px-4 py-8 text-center text-gray-500">No customers yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">{{ $customers->links() }}</div>
    </div>
</x-admin-layout>
