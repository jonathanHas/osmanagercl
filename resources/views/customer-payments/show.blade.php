<x-admin-layout>
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
        <div class="flex flex-wrap justify-between items-center gap-2 mb-6">
            <div>
                <h2 class="text-2xl font-bold text-gray-100">
                    Payment #{{ $payment->id }}
                    @if ($payment->isVoid())
                        <span class="text-xs bg-red-700 text-white px-2 py-0.5 rounded uppercase ml-2">Void</span>
                    @endif
                </h2>
                <p class="text-gray-400 text-sm mt-1">
                    {{ $payment->payment_date->format('d M Y') }} ·
                    <a href="{{ route('customers.show', $payment->customer) }}" class="text-blue-400 hover:text-blue-300">{{ $payment->customer->name }}</a>
                </p>
            </div>
            <div class="space-x-2">
                <a href="{{ route('customer-payments.index') }}" class="text-gray-400 hover:text-gray-200">← All payments</a>
                @if (! $payment->isVoid())
                    <form action="{{ route('customer-payments.destroy', $payment) }}" method="POST" class="inline"
                          onsubmit="return confirm('Void this payment? Outstanding balances will be recalculated.');">
                        @csrf @method('DELETE')
                        <button class="bg-red-700 hover:bg-red-800 text-white px-4 py-2 rounded">Void payment</button>
                    </form>
                @endif
            </div>
        </div>

        @if (session('status'))
            <div class="mb-4 rounded bg-green-700 text-white px-4 py-2">{{ session('status') }}</div>
        @endif

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
            <div class="bg-gray-800 p-4 rounded">
                <h3 class="text-gray-400 text-xs uppercase mb-2">Payment</h3>
                <div class="text-3xl font-bold text-gray-100 font-mono">€{{ number_format($payment->amount, 2) }}</div>
                <div class="text-gray-300 mt-2">{{ $payment->methodLabel() }}</div>
                @if ($payment->till_name)
                    <div class="text-gray-400 text-sm">Till: {{ $payment->till_name }}@if ($payment->till_id) (#{{ $payment->till_id }})@endif</div>
                @endif
                @if ($payment->reference)
                    <div class="text-gray-400 text-sm">Ref: {{ $payment->reference }}</div>
                @endif
            </div>
            <div class="bg-gray-800 p-4 rounded">
                <h3 class="text-gray-400 text-xs uppercase mb-2">Details</h3>
                <div class="text-gray-300 text-sm">
                    Recorded by {{ $payment->creator?->name ?? 'unknown' }}
                    on {{ $payment->created_at->format('Y-m-d H:i') }}
                </div>
                @if ($payment->isVoid())
                    <div class="text-red-400 text-sm mt-2">
                        Voided on {{ $payment->voided_at->format('Y-m-d H:i') }}
                        @if ($payment->voider) by {{ $payment->voider->name }}@endif
                    </div>
                @endif
                @if ($payment->notes)
                    <div class="mt-2 text-gray-300 text-sm whitespace-pre-line">{{ $payment->notes }}</div>
                @endif
            </div>
        </div>

        <div class="bg-gray-800 rounded shadow">
            <div class="px-4 py-3 border-b border-gray-700">
                <h3 class="font-semibold text-gray-200">Allocations</h3>
            </div>
            <table class="min-w-full divide-y divide-gray-700 text-sm">
                <thead class="bg-gray-900 text-gray-400">
                    <tr>
                        <th class="px-4 py-2 text-left">Invoice</th>
                        <th class="px-4 py-2 text-left">Invoice date</th>
                        <th class="px-4 py-2 text-left">Paid</th>
                        <th class="px-4 py-2 text-right">Invoice total</th>
                        <th class="px-4 py-2 text-right">Applied</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-700 text-gray-200">
                    @forelse ($payment->allocations as $a)
                        <tr>
                            <td class="px-4 py-2 font-mono">
                                <a href="{{ route('customer-invoices.show', $a->invoice) }}"
                                   class="text-blue-400 hover:text-blue-300">{{ $a->invoice->invoice_number ?? '(draft)' }}</a>
                            </td>
                            <td class="px-4 py-2">{{ $a->invoice->issue_date?->format('Y-m-d') }}</td>
                            <td class="px-4 py-2">{{ $payment->payment_date->format('Y-m-d') }}</td>
                            <td class="px-4 py-2 text-right font-mono">€{{ number_format($a->invoice->total, 2) }}</td>
                            <td class="px-4 py-2 text-right font-mono">€{{ number_format($a->amount, 2) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-4 py-6 text-center text-gray-500">No invoice allocations — recorded as on-account credit.</td></tr>
                    @endforelse
                </tbody>
                @if ($payment->unallocated_amount > 0.005)
                    <tfoot class="bg-gray-900 text-gray-300 text-sm">
                        <tr>
                            <td colspan="4" class="px-4 py-2 text-right">On-account credit:</td>
                            <td class="px-4 py-2 text-right font-mono text-blue-300">€{{ number_format($payment->unallocated_amount, 2) }}</td>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>
    </div>
</x-admin-layout>
