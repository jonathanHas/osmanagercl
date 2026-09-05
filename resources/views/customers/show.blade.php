<x-admin-layout>
    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
        <div class="flex flex-wrap justify-between items-center gap-2 mb-6">
            <div>
                <h2 class="text-2xl font-bold text-gray-100">{{ $customer->name }}</h2>
                @if ($customer->vat_number)
                    <p class="text-sm text-gray-400">VAT {{ $customer->vat_number }}</p>
                @endif
            </div>
            <div class="space-x-2">
                <a href="{{ route('customers.index') }}" class="text-gray-400 hover:text-gray-200">← All customers</a>
                <a href="{{ route('customers.edit', $customer) }}"
                   class="bg-yellow-600 hover:bg-yellow-700 text-white px-4 py-2 rounded">Edit</a>
                <a href="{{ route('customer-invoices.create') }}?customer_id={{ $customer->id }}"
                   class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded">+ New invoice</a>
                @if (! $customer->invoices()->exists())
                    <form method="POST" action="{{ route('customers.destroy', $customer) }}" class="inline"
                          onsubmit="return confirm('Delete this customer? Soft-deletes only — invoices would block hard delete.');">
                        @csrf @method('DELETE')
                        <button class="bg-red-700 hover:bg-red-800 text-white px-4 py-2 rounded">Delete</button>
                    </form>
                @endif
            </div>
        </div>

        @if (session('status'))
            <div class="mb-4 rounded bg-green-700 text-white px-4 py-2">{{ session('status') }}</div>
        @endif
        @if (session('error'))
            <div class="mb-4 rounded bg-red-700 text-white px-4 py-2">{{ session('error') }}</div>
        @endif

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
            <div class="bg-gray-800 p-4 rounded">
                <h3 class="text-gray-400 text-xs uppercase mb-2">Contact</h3>
                @if ($customer->email)<div class="text-gray-200">{{ $customer->email }}</div>@endif
                @if ($customer->phone)<div class="text-gray-200">{{ $customer->phone }}</div>@endif
                @unless ($customer->email || $customer->phone)
                    <div class="text-gray-500">—</div>
                @endunless
            </div>
            <div class="bg-gray-800 p-4 rounded">
                <h3 class="text-gray-400 text-xs uppercase mb-2">Address</h3>
                @php
                    $addr = array_filter([
                        $customer->address_line1, $customer->address_line2,
                        $customer->city, $customer->postcode, $customer->country,
                    ]);
                @endphp
                @if (count($addr))
                    <div class="text-gray-200 whitespace-pre-line">{{ implode("\n", $addr) }}</div>
                @else
                    <div class="text-gray-500">—</div>
                @endif
            </div>
            <div class="bg-gray-800 p-4 rounded">
                <h3 class="text-gray-400 text-xs uppercase mb-2">Wholesale</h3>
                @if ($customer->default_discount_percent > 0)
                    <div class="text-2xl font-bold text-green-400">
                        {{ rtrim(rtrim(number_format($customer->default_discount_percent, 2), '0'), '.') }}%
                    </div>
                    <div class="text-xs text-gray-500">Auto-applies to new invoices for this customer</div>
                @else
                    <div class="text-gray-500">No default discount</div>
                @endif
            </div>
            <div class="bg-gray-800 p-4 rounded">
                @php
                    $balance = $customer->balance;
                    $invoiced = $customer->total_invoiced;
                    $paid = $customer->total_paid;
                @endphp
                <h3 class="text-gray-400 text-xs uppercase mb-2">Account Balance</h3>
                <div class="flex items-baseline gap-2 mb-2">
                    <div class="text-2xl font-bold {{ $balance > 0.005 ? 'text-red-400' : ($balance < -0.005 ? 'text-green-400' : 'text-gray-200') }}">
                        €{{ number_format(abs($balance), 2) }}
                    </div>
                    @if ($balance > 0.005)
                        <span class="text-xs text-red-300">owing</span>
                    @elseif ($balance < -0.005)
                        <span class="text-xs text-green-300">credit</span>
                    @else
                        <span class="text-xs text-gray-500">settled</span>
                    @endif
                </div>
                <div class="text-xs text-gray-400">
                    Invoiced €{{ number_format($invoiced, 2) }} · Paid €{{ number_format($paid, 2) }}
                </div>
                @if (($unappliedCredit ?? 0) > 0.005)
                    <div class="text-xs mt-1">
                        <a href="{{ route('customer-payments.index', ['customer_id' => $customer->id, 'allocation' => 'unallocated']) }}"
                           class="text-blue-300 hover:text-blue-200"
                           title="Payments received but not yet applied to any invoice">
                            €{{ number_format($unappliedCredit, 2) }} unapplied credit — match to invoices →
                        </a>
                    </div>
                @endif
                <div class="mt-3 flex gap-2">
                    <a href="{{ route('customers.statement', $customer) }}"
                       class="text-xs px-2 py-1 bg-gray-700 hover:bg-gray-600 text-gray-200 rounded">View statement</a>
                    <a href="{{ route('customer-payments.create', ['customer_id' => $customer->id]) }}"
                       class="text-xs px-2 py-1 bg-blue-600 hover:bg-blue-700 text-white rounded">+ Record payment</a>
                </div>
            </div>
            <div class="bg-gray-800 p-4 rounded">
                <h3 class="text-gray-400 text-xs uppercase mb-2">Notes</h3>
                @if ($customer->notes)
                    <div class="text-gray-200 whitespace-pre-line text-sm">{{ $customer->notes }}</div>
                @else
                    <div class="text-gray-500">—</div>
                @endif
            </div>
        </div>

        <div class="bg-gray-800 rounded shadow">
            <div class="px-4 py-3 border-b border-gray-700 flex justify-between items-center">
                <h3 class="font-semibold text-gray-200">Invoices ({{ $customer->invoices->count() }})</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-700 text-sm">
                    <thead class="bg-gray-900 text-gray-400">
                        <tr>
                            <th class="px-4 py-2 text-left">#</th>
                            <th class="px-4 py-2 text-left">Date</th>
                            <th class="px-4 py-2 text-left">Status</th>
                            <th class="px-4 py-2 text-right">Total</th>
                            <th class="px-4 py-2"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-700 text-gray-200">
                        @forelse ($customer->invoices as $invoice)
                            <tr>
                                <td class="px-4 py-2 font-mono">{{ $invoice->invoice_number ?? '—' }}</td>
                                <td class="px-4 py-2">{{ $invoice->issue_date->format('Y-m-d') }}</td>
                                <td class="px-4 py-2">
                                    @php
                                        $color = match ($invoice->status) {
                                            'draft' => 'bg-gray-600',
                                            'issued' => 'bg-green-600',
                                            'void' => 'bg-red-700',
                                            default => 'bg-gray-700',
                                        };
                                    @endphp
                                    <span class="text-xs px-2 py-0.5 rounded {{ $color }} text-white uppercase">{{ $invoice->status }}</span>
                                </td>
                                <td class="px-4 py-2 text-right">€{{ number_format($invoice->total, 2) }}</td>
                                <td class="px-4 py-2 text-right">
                                    <a href="{{ route('customer-invoices.show', $invoice) }}"
                                       class="text-blue-400 hover:text-blue-300">View</a>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="px-4 py-8 text-center text-gray-500">No invoices yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-admin-layout>
