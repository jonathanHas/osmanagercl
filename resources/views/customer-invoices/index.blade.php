<x-admin-layout>
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
        <div class="flex justify-between items-center mb-6">
            <h2 class="text-2xl font-bold text-gray-100">Customer Invoices</h2>
            <a href="{{ route('customer-invoices.create') }}"
               class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded inline-flex items-center">
                + New Invoice
            </a>
        </div>

        @if (session('status'))
            <div class="mb-4 rounded bg-green-700 text-white px-4 py-2">{{ session('status') }}</div>
        @endif

        <form method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-3 mb-4 bg-gray-800 p-4 rounded">
            <div>
                <label class="block text-xs text-gray-400 mb-1">Status</label>
                <select name="status" class="w-full bg-gray-900 border border-gray-700 rounded px-2 py-1 text-gray-100">
                    <option value="">All</option>
                    @foreach (['draft' => 'Draft', 'issued' => 'Issued', 'void' => 'Void'] as $val => $label)
                        <option value="{{ $val }}" @selected(request('status') === $val)>{{ $label }}</option>
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
            <div class="flex items-end">
                <button type="submit" class="bg-gray-700 hover:bg-gray-600 text-white px-4 py-1 rounded">Filter</button>
                <a href="{{ route('customer-invoices.index') }}" class="ml-2 text-gray-400 hover:text-gray-200 px-2 py-1">Reset</a>
            </div>
        </form>

        <div class="bg-gray-800 rounded shadow overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-700 text-sm">
                <thead class="bg-gray-900 text-gray-400">
                    <tr>
                        <th class="px-4 py-2 text-left">Invoice #</th>
                        <th class="px-4 py-2 text-left">Date</th>
                        <th class="px-4 py-2 text-left">Customer</th>
                        <th class="px-4 py-2 text-right">Total</th>
                        <th class="px-4 py-2 text-left">Status</th>
                        <th class="px-4 py-2"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-700 text-gray-200">
                    @forelse ($invoices as $invoice)
                        <tr class="hover:bg-gray-700/40">
                            <td class="px-4 py-2 font-mono">{{ $invoice->invoice_number ?? '—' }}</td>
                            <td class="px-4 py-2">{{ $invoice->issue_date->format('Y-m-d') }}</td>
                            <td class="px-4 py-2">{{ $invoice->customer_name }}</td>
                            <td class="px-4 py-2 text-right">€{{ number_format($invoice->total, 2) }}</td>
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
                            <td class="px-4 py-2 text-right space-x-2">
                                <a href="{{ route('customer-invoices.show', $invoice) }}" class="text-blue-400 hover:text-blue-300">View</a>
                                @if ($invoice->isEditable())
                                    <a href="{{ route('customer-invoices.edit', $invoice) }}" class="text-yellow-400 hover:text-yellow-300">Edit</a>
                                @endif
                                @if ($invoice->isIssued())
                                    <a href="{{ route('customer-invoices.pdf', $invoice) }}" class="text-purple-400 hover:text-purple-300">PDF</a>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-8 text-center text-gray-500">No invoices yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">{{ $invoices->links() }}</div>
    </div>
</x-admin-layout>
