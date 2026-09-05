<x-admin-layout>
    <div class="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
        @php
            $willApply = min($availableCredit, $invoice->outstanding_amount);
        @endphp

        <div class="flex flex-wrap justify-between items-center gap-2 mb-6">
            <h2 class="text-2xl font-bold text-gray-100">Apply existing credit</h2>
            <a href="{{ route('customer-invoices.show', $invoice) }}" class="text-gray-400 hover:text-gray-200">← Back to invoice</a>
        </div>

        @if ($errors->any())
            <div class="mb-4 bg-red-800 text-white p-3 rounded text-sm">
                <ul class="list-disc pl-5">
                    @foreach ($errors->all() as $err)<li>{{ $err }}</li>@endforeach
                </ul>
            </div>
        @endif

        <div class="bg-gray-800 p-4 rounded mb-4">
            <div class="text-gray-400 text-xs uppercase mb-1">Invoice</div>
            <div class="text-gray-100 font-mono text-lg">{{ $invoice->invoice_number ?? '(draft)' }}</div>
            <div class="text-gray-300 text-sm mt-1">
                {{ $invoice->customer_name }} ·
                €{{ number_format($invoice->outstanding_amount, 2) }} outstanding of €{{ number_format($invoice->total, 2) }}
            </div>
        </div>

        @if ($creditPayments->isEmpty())
            <div class="bg-gray-800 p-4 rounded text-gray-400">
                {{ $invoice->customer_name }} has no unapplied credit. Record a payment against this
                invoice instead.
                <div class="mt-3">
                    <a href="{{ route('customer-payments.create', ['customer_id' => $invoice->customer_id, 'customer_invoice_id' => $invoice->id]) }}"
                       class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded inline-block">+ Record payment</a>
                </div>
            </div>
        @else
            <div class="bg-gray-800 rounded shadow mb-4">
                <div class="px-4 py-3 border-b border-gray-700">
                    <h3 class="font-semibold text-gray-200">Unapplied credit — oldest first</h3>
                </div>
                <table class="min-w-full divide-y divide-gray-700 text-sm">
                    <thead class="bg-gray-900 text-gray-400">
                        <tr>
                            <th class="px-4 py-2 text-left">Payment</th>
                            <th class="px-4 py-2 text-left">Date</th>
                            <th class="px-4 py-2 text-left">Method</th>
                            <th class="px-4 py-2 text-right">Unapplied</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-700 text-gray-200">
                        @foreach ($creditPayments as $p)
                            <tr>
                                <td class="px-4 py-2">
                                    <a href="{{ route('customer-payments.show', $p) }}"
                                       class="text-blue-400 hover:text-blue-300">#{{ $p->id }}</a>
                                </td>
                                <td class="px-4 py-2">{{ $p->payment_date->format('Y-m-d') }}</td>
                                <td class="px-4 py-2">{{ $p->methodLabel() }}</td>
                                <td class="px-4 py-2 text-right font-mono text-blue-300">€{{ number_format($p->unapplied, 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot class="bg-gray-900 text-gray-300">
                        <tr>
                            <td colspan="3" class="px-4 py-2 text-right">Available:</td>
                            <td class="px-4 py-2 text-right font-mono">€{{ number_format($availableCredit, 2) }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <form method="POST" action="{{ route('customer-invoices.apply-credit.store', $invoice) }}">
                @csrf
                <div class="bg-gray-800 p-4 rounded flex flex-wrap justify-between items-center gap-3">
                    <p class="text-gray-200">
                        Apply <span class="font-mono font-semibold">€{{ number_format($willApply, 2) }}</span>
                        to this invoice, oldest payment first.
                        @if ($availableCredit > $willApply + 0.005)
                            <span class="text-gray-400 text-sm block mt-1">
                                €{{ number_format($availableCredit - $willApply, 2) }} stays as on-account credit.
                            </span>
                        @endif
                    </p>
                    <div class="flex gap-2">
                        <a href="{{ route('customer-invoices.show', $invoice) }}"
                           class="px-4 py-2 text-gray-300 hover:text-white">Cancel</a>
                        <button type="submit" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded">
                            Apply credit
                        </button>
                    </div>
                </div>
            </form>
        @endif
    </div>
</x-admin-layout>
