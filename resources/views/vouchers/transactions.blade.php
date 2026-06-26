<x-admin-layout>
    <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
        <div class="flex justify-between items-center mb-6">
            <div>
                <h2 class="text-2xl font-bold text-gray-100 font-mono">{{ $voucher->code }}</h2>
                <p class="text-sm text-gray-400 mt-1">
                    Created by {{ $voucher->creator?->name ?? '—' }} on {{ $voucher->created_at?->format('d M Y H:i') }}
                </p>
            </div>
            <a href="{{ route('vouchers.list') }}" class="text-blue-400 hover:text-blue-300 text-sm">Back to list</a>
        </div>

        <div class="grid grid-cols-3 gap-3 mb-6">
            <div class="bg-gray-800 rounded p-4 text-center">
                <p class="text-xs text-gray-400">Status</p>
                <p class="text-lg font-semibold text-gray-100">{{ ucfirst($voucher->status) }}</p>
            </div>
            <div class="bg-gray-800 rounded p-4 text-center">
                <p class="text-xs text-gray-400">Initial</p>
                <p class="text-lg font-semibold text-gray-100">{{ $voucher->initial_value !== null ? '€'.number_format($voucher->initial_value, 2) : '—' }}</p>
            </div>
            <div class="bg-gray-800 rounded p-4 text-center">
                <p class="text-xs text-gray-400">Balance</p>
                <p class="text-lg font-semibold text-green-400">€{{ number_format($voucher->current_balance, 2) }}</p>
            </div>
        </div>

        <div class="bg-gray-800 rounded shadow overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-700 text-sm">
                <thead class="bg-gray-900 text-gray-400">
                    <tr>
                        <th class="px-4 py-2 text-left">When</th>
                        <th class="px-4 py-2 text-left">Type</th>
                        <th class="px-4 py-2 text-right">Amount</th>
                        <th class="px-4 py-2 text-right">Balance after</th>
                        <th class="px-4 py-2 text-left">By</th>
                        <th class="px-4 py-2 text-left">Note</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-700 text-gray-200">
                    @forelse ($voucher->transactions as $tx)
                        @php
                            [$badge, $amountClass, $sign] = match ($tx->type) {
                                'issue' => ['bg-green-800/50 text-green-300', 'text-green-400', '+'],
                                'deduct' => ['bg-blue-800/50 text-blue-300', 'text-blue-300', '−'],
                                'deactivate' => ['bg-red-800/50 text-red-300', 'text-gray-500', null],
                                'activate' => ['bg-green-800/50 text-green-300', 'text-gray-500', null],
                                default => ['bg-gray-700 text-gray-300', 'text-gray-400', null],
                            };
                        @endphp
                        <tr>
                            <td class="px-4 py-2 text-gray-400 text-xs">{{ $tx->created_at?->format('d M Y H:i') }}</td>
                            <td class="px-4 py-2">
                                <span class="text-xs px-2 py-0.5 rounded {{ $badge }}">{{ ucfirst($tx->type) }}</span>
                            </td>
                            <td class="px-4 py-2 text-right {{ $amountClass }}">
                                @if ($sign !== null)
                                    {{ $sign }}€{{ number_format($tx->amount, 2) }}
                                @else
                                    —
                                @endif
                            </td>
                            <td class="px-4 py-2 text-right">€{{ number_format($tx->balance_after, 2) }}</td>
                            <td class="px-4 py-2 text-gray-300">{{ $tx->user?->name ?? '—' }}</td>
                            <td class="px-4 py-2 text-gray-400 text-xs">{{ $tx->note ?? '' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-4 py-8 text-center text-gray-500">No transactions yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-admin-layout>
