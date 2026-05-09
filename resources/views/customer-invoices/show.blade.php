<x-admin-layout>
    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
        <div class="flex justify-between items-center mb-6">
            <div>
                <h2 class="text-2xl font-bold text-gray-100">
                    Invoice {{ $invoice->invoice_number ?? '(Draft)' }}
                </h2>
                <p class="text-gray-400 text-sm mt-1">
                    Issued {{ $invoice->issue_date->format('Y-m-d') }} ·
                    Status:
                    <span class="uppercase font-semibold text-gray-200">{{ $invoice->status }}</span>
                </p>
            </div>
            <div class="space-x-2">
                <a href="{{ route('customer-invoices.index') }}" class="text-gray-400 hover:text-gray-200">← Back</a>
                @if ($invoice->isEditable())
                    <a href="{{ route('customer-invoices.edit', $invoice) }}"
                       class="bg-yellow-600 hover:bg-yellow-700 text-white px-4 py-2 rounded">Edit Draft</a>
                    <form action="{{ route('customer-invoices.issue', $invoice) }}" method="POST" class="inline">
                        @csrf
                        <button class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded">Issue Invoice</button>
                    </form>
                @endif
                @if ($invoice->isIssued())
                    <a href="{{ route('customer-invoices.pdf', $invoice) }}"
                       class="bg-purple-600 hover:bg-purple-700 text-white px-4 py-2 rounded">Download PDF</a>
                    <form action="{{ route('customer-invoices.void', $invoice) }}" method="POST" class="inline"
                          onsubmit="return confirm('Void this invoice? The number will be retained for audit trail.');">
                        @csrf
                        <button class="bg-red-700 hover:bg-red-800 text-white px-4 py-2 rounded">Void</button>
                    </form>
                @endif
            </div>
        </div>

        @if (session('status'))
            <div class="mb-4 rounded bg-green-700 text-white px-4 py-2">{{ session('status') }}</div>
        @endif

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
            <div class="bg-gray-800 p-4 rounded">
                <h3 class="text-gray-400 text-xs uppercase mb-2">Bill To</h3>
                <div class="text-gray-100 font-semibold">{{ $invoice->customer_name }}</div>
                @if ($invoice->customer_address)
                    <div class="text-gray-300 whitespace-pre-line text-sm mt-1">{{ $invoice->customer_address }}</div>
                @endif
                @if ($invoice->customer_email)
                    <div class="text-gray-400 text-sm mt-1">{{ $invoice->customer_email }}</div>
                @endif
                @if ($invoice->customer_vat_number)
                    <div class="text-gray-400 text-sm mt-1">VAT: {{ $invoice->customer_vat_number }}</div>
                @endif
            </div>
            <div class="bg-gray-800 p-4 rounded text-sm text-gray-300">
                <div><span class="text-gray-500">Issue date:</span> {{ $invoice->issue_date->format('Y-m-d') }}</div>
                @if ($invoice->due_date)
                    <div><span class="text-gray-500">Due date:</span> {{ $invoice->due_date->format('Y-m-d') }}</div>
                @endif
                @if ($invoice->notes)
                    <div class="mt-2"><span class="text-gray-500">Notes:</span><div class="whitespace-pre-line">{{ $invoice->notes }}</div></div>
                @endif
            </div>
        </div>

        <div class="bg-gray-800 rounded shadow overflow-x-auto mb-4">
            <table class="min-w-full divide-y divide-gray-700 text-sm">
                <thead class="bg-gray-900 text-gray-400">
                    <tr>
                        <th class="px-4 py-2 text-left">Description</th>
                        <th class="px-4 py-2 text-right">Qty</th>
                        <th class="px-4 py-2 text-right">Unit (net)</th>
                        <th class="px-4 py-2 text-right">VAT %</th>
                        <th class="px-4 py-2 text-right">Net</th>
                        <th class="px-4 py-2 text-right">VAT</th>
                        <th class="px-4 py-2 text-right">Gross</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-700 text-gray-200">
                    @foreach ($invoice->items as $item)
                        <tr>
                            <td class="px-4 py-2">
                                {{ $item->description }}
                                @if ($item->pos_product_code)
                                    <span class="text-gray-500 text-xs ml-2">{{ $item->pos_product_code }}</span>
                                @endif
                            </td>
                            <td class="px-4 py-2 text-right">{{ rtrim(rtrim(number_format($item->quantity, 3), '0'), '.') }}</td>
                            <td class="px-4 py-2 text-right">€{{ number_format($item->unit_price, 4) }}</td>
                            <td class="px-4 py-2 text-right">{{ number_format($item->vat_rate * 100, 1) }}%</td>
                            <td class="px-4 py-2 text-right">€{{ number_format($item->net_amount, 2) }}</td>
                            <td class="px-4 py-2 text-right">€{{ number_format($item->vat_amount, 2) }}</td>
                            <td class="px-4 py-2 text-right">€{{ number_format($item->gross_amount, 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div class="bg-gray-800 p-4 rounded text-sm">
                <h3 class="text-gray-400 text-xs uppercase mb-2">VAT Breakdown</h3>
                @forelse ($invoice->getVatBreakdown() as $band)
                    <div class="flex justify-between text-gray-200 py-1">
                        <span>{{ $band['code'] }} ({{ number_format($band['rate'] * 100, 1) }}%)</span>
                        <span>€{{ number_format($band['net_amount'], 2) }} + €{{ number_format($band['vat_amount'], 2) }}</span>
                    </div>
                @empty
                    <div class="text-gray-500">—</div>
                @endforelse
            </div>
            <div class="bg-gray-800 p-4 rounded text-right">
                <div class="flex justify-between text-gray-300 py-1">
                    <span>Subtotal (net):</span><span>€{{ number_format($invoice->subtotal, 2) }}</span>
                </div>
                <div class="flex justify-between text-gray-300 py-1">
                    <span>VAT:</span><span>€{{ number_format($invoice->vat_total, 2) }}</span>
                </div>
                <div class="flex justify-between text-gray-100 text-lg font-bold py-2 border-t border-gray-700 mt-2">
                    <span>Total:</span><span>€{{ number_format($invoice->total, 2) }}</span>
                </div>
            </div>
        </div>
    </div>
</x-admin-layout>
