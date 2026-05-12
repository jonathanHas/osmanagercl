<x-admin-layout>
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
        <div class="flex justify-between items-center mb-6">
            <h2 class="text-2xl font-bold text-gray-100">Customer Payments</h2>
            <a href="{{ route('customer-payments.create') }}"
               class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded inline-flex items-center">
                + Record Payment
            </a>
        </div>

        @if (session('status'))
            <div class="mb-4 rounded bg-green-700 text-white px-4 py-2">{{ session('status') }}</div>
        @endif

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 mb-4">
            {{-- Outstanding balances widget --}}
            <div class="lg:col-span-1 bg-gray-800 rounded p-4">
                <h3 class="text-gray-200 font-semibold mb-3">Outstanding Balances</h3>
                @if ($outstandingCustomers->isEmpty())
                    <p class="text-gray-500 text-sm">No customers currently owe.</p>
                @else
                    <ul class="divide-y divide-gray-700">
                        @foreach ($outstandingCustomers as $row)
                            <li class="py-2 flex justify-between items-center">
                                <a href="{{ route('customers.show', $row->customer) }}"
                                   class="text-blue-400 hover:text-blue-300 text-sm truncate">{{ $row->customer->name }}</a>
                                <span class="flex items-center gap-2">
                                    <span class="text-red-400 font-mono text-sm">€{{ number_format($row->balance, 2) }}</span>
                                    <a href="{{ route('customer-payments.create', ['customer_id' => $row->customer->id]) }}"
                                       class="text-xs px-2 py-0.5 bg-blue-600 hover:bg-blue-700 text-white rounded">Pay</a>
                                </span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>

            {{-- Filters --}}
            <form method="GET" class="lg:col-span-2 bg-gray-800 rounded p-4 grid grid-cols-1 md:grid-cols-4 gap-3">
                <div>
                    <label class="block text-xs text-gray-400 mb-1">Method</label>
                    <select name="method" class="w-full bg-gray-900 border border-gray-700 rounded px-2 py-1 text-gray-100">
                        <option value="">All</option>
                        @foreach (\App\Models\CustomerPayment::METHODS as $val => $label)
                            <option value="{{ $val }}" @selected(request('method') === $val)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs text-gray-400 mb-1">From</label>
                    <input type="date" name="from" value="{{ request('from') }}"
                           class="w-full bg-gray-900 border border-gray-700 rounded px-2 py-1 text-gray-100">
                </div>
                <div>
                    <label class="block text-xs text-gray-400 mb-1">To</label>
                    <input type="date" name="to" value="{{ request('to') }}"
                           class="w-full bg-gray-900 border border-gray-700 rounded px-2 py-1 text-gray-100">
                </div>
                <div class="flex items-end gap-2">
                    <button type="submit" class="bg-gray-700 hover:bg-gray-600 text-white px-4 py-1 rounded">Filter</button>
                    <a href="{{ route('customer-payments.index') }}" class="text-gray-400 hover:text-gray-200 px-2 py-1">Reset</a>
                </div>
            </form>
        </div>

        <div class="bg-gray-800 rounded shadow overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-700 text-sm">
                <thead class="bg-gray-900 text-gray-400">
                    <tr>
                        <th class="px-4 py-2 text-left">Date</th>
                        <th class="px-4 py-2 text-left">Customer</th>
                        <th class="px-4 py-2 text-right">Amount</th>
                        <th class="px-4 py-2 text-left">Method</th>
                        <th class="px-4 py-2 text-left">Till / Ref</th>
                        <th class="px-4 py-2 text-left">Allocated to</th>
                        <th class="px-4 py-2"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-700 text-gray-200">
                    @forelse ($payments as $payment)
                        <tr class="hover:bg-gray-700/40">
                            <td class="px-4 py-2">{{ $payment->payment_date->format('Y-m-d') }}</td>
                            <td class="px-4 py-2">
                                <a href="{{ route('customers.show', $payment->customer) }}"
                                   class="text-blue-400 hover:text-blue-300">{{ $payment->customer->name }}</a>
                            </td>
                            <td class="px-4 py-2 text-right font-mono">€{{ number_format($payment->amount, 2) }}</td>
                            <td class="px-4 py-2">
                                @php
                                    $color = match ($payment->method) {
                                        'card_till' => 'bg-blue-700',
                                        'cash_till' => 'bg-green-700',
                                        'online' => 'bg-purple-700',
                                    };
                                @endphp
                                <span class="text-xs px-2 py-0.5 rounded {{ $color }} text-white">{{ $payment->methodLabel() }}</span>
                            </td>
                            <td class="px-4 py-2 text-xs text-gray-400">
                                @if ($payment->till_name)
                                    <span class="text-gray-300">{{ $payment->till_name }}</span><br>
                                @endif
                                @if ($payment->reference)
                                    <span>{{ $payment->reference }}</span>
                                @endif
                            </td>
                            <td class="px-4 py-2 text-xs">
                                @forelse ($payment->allocations as $a)
                                    <div>
                                        <a href="{{ route('customer-invoices.show', $a->invoice) }}"
                                           class="text-blue-400 hover:text-blue-300 font-mono">{{ $a->invoice->invoice_number ?? '(draft)' }}</a>
                                        <span class="text-gray-400">€{{ number_format($a->amount, 2) }}</span>
                                    </div>
                                @empty
                                    <span class="text-gray-500">on-account credit</span>
                                @endforelse
                            </td>
                            <td class="px-4 py-2 text-right">
                                <a href="{{ route('customer-payments.show', $payment) }}" class="text-blue-400 hover:text-blue-300">View</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="px-4 py-8 text-center text-gray-500">No payments recorded yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">{{ $payments->links() }}</div>
    </div>
</x-admin-layout>
