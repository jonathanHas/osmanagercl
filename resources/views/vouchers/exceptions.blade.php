<x-admin-layout>
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
        <div class="flex justify-between items-center mb-2">
            <h2 class="text-2xl font-bold text-gray-100">Till voucher exceptions</h2>
            <div class="flex gap-2">
                @if ($showAll)
                    <a href="{{ route('vouchers.exceptions') }}"
                       class="bg-gray-700 hover:bg-gray-600 text-white font-medium py-2 px-4 rounded">Hide reviewed</a>
                @else
                    <a href="{{ route('vouchers.exceptions', ['all' => 1]) }}"
                       class="bg-gray-700 hover:bg-gray-600 text-white font-medium py-2 px-4 rounded">Show reviewed</a>
                @endif
                <a href="{{ route('vouchers.activity') }}"
                   class="bg-gray-700 hover:bg-gray-600 text-white font-medium py-2 px-4 rounded">Activity</a>
                <a href="{{ route('vouchers.list') }}"
                   class="bg-gray-700 hover:bg-gray-600 text-white font-medium py-2 px-4 rounded">All vouchers</a>
            </div>
        </div>
        <p class="text-sm text-gray-400 mb-4">
            Voucher lines on till tickets that were not simply applied. Anything that needs a correction
            (a re-credit, a manual deduct) is done on the voucher itself; mark the row reviewed once handled.
        </p>

        @if (session('status'))
            <div class="mb-4 rounded px-4 py-3 bg-green-700 text-white text-sm">{{ session('status') }}</div>
        @endif

        <div class="bg-gray-800 rounded shadow overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-700 text-sm">
                <thead class="bg-gray-900 text-gray-400">
                    <tr>
                        <th class="px-4 py-2 text-left">When</th>
                        <th class="px-4 py-2 text-left">Ticket #</th>
                        <th class="px-4 py-2 text-left">Voucher</th>
                        <th class="px-4 py-2 text-left">Status</th>
                        <th class="px-4 py-2 text-right">Voucher tender</th>
                        <th class="px-4 py-2 text-right">Sale</th>
                        <th class="px-4 py-2 text-right">Deducted</th>
                        <th class="px-4 py-2 text-right">Shortfall</th>
                        <th class="px-4 py-2 text-right">Ticket total</th>
                        <th class="px-4 py-2 text-left">Note</th>
                        <th class="px-4 py-2 text-left">Reviewed</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-700 text-gray-200">
                    @forelse ($redemptions as $row)
                        @php
                            [$pill, $label] = match ($row->status) {
                                'partial' => ['bg-red-800/50 text-red-300', 'Partial'],
                                'sale_flagged' => ['bg-red-800/50 text-red-300', 'Sale not activated'],
                                'no_tender' => ['bg-yellow-800/50 text-yellow-300', 'No voucher tender'],
                                'inactive' => ['bg-yellow-800/50 text-yellow-300', 'Not active'],
                                'unknown' => ['bg-gray-700 text-gray-300', 'Unknown voucher'],
                                'refund' => ['bg-blue-800/50 text-blue-300', 'Refund'],
                                default => ['bg-gray-700 text-gray-300', ucfirst($row->status)],
                            };
                        @endphp
                        <tr class="hover:bg-gray-700/40 align-top">
                            <td class="px-4 py-2 text-gray-400 text-xs whitespace-nowrap">{{ $row->sold_at?->format('d M Y H:i') }}</td>
                            <td class="px-4 py-2 font-mono">{{ $row->ticket_number }}</td>
                            <td class="px-4 py-2 font-mono">
                                @if ($row->voucher)
                                    <a href="{{ route('vouchers.transactions', $row->voucher) }}" class="text-blue-400 hover:text-blue-300">{{ $row->voucher->code }}</a>
                                @else
                                    {{ $row->voucher_code ?? '—' }}
                                @endif
                            </td>
                            <td class="px-4 py-2">
                                <span class="text-xs px-2 py-0.5 rounded whitespace-nowrap {{ $pill }}">{{ $label }}</span>
                            </td>
                            <td class="px-4 py-2 text-right whitespace-nowrap">€{{ number_format((float) $row->voucher_tender, 2) }}</td>
                            <td class="px-4 py-2 text-right whitespace-nowrap">{{ $row->sale_amount !== null ? '€'.number_format((float) $row->sale_amount, 2) : '—' }}</td>
                            <td class="px-4 py-2 text-right whitespace-nowrap">€{{ number_format((float) $row->amount_deducted, 2) }}</td>
                            <td class="px-4 py-2 text-right whitespace-nowrap {{ (float) $row->shortfall > 0 ? 'text-red-300 font-medium' : 'text-gray-500' }}">
                                €{{ number_format((float) $row->shortfall, 2) }}
                            </td>
                            <td class="px-4 py-2 text-right whitespace-nowrap">
                                {{ $row->ticket_total !== null ? '€'.number_format((float) $row->ticket_total, 2) : '—' }}
                            </td>
                            <td class="px-4 py-2 text-gray-400 text-xs max-w-xs">{{ $row->note }}</td>
                            <td class="px-4 py-2 text-xs">
                                @if ($row->reviewed_at)
                                    <span class="text-gray-300">{{ $row->reviewer?->name ?? '—' }}</span>
                                    <span class="block text-gray-500">{{ $row->reviewed_at->format('d M Y H:i') }}</span>
                                @else
                                    <form method="POST" action="{{ route('vouchers.exceptions.reviewed', $row) }}" class="flex gap-1">
                                        @csrf
                                        <input type="text" name="note" maxlength="500" placeholder="Note (optional)"
                                               class="w-36 bg-gray-900 border border-gray-700 rounded px-2 py-1 text-gray-100 text-xs">
                                        <button type="submit"
                                                class="bg-blue-600 hover:bg-blue-700 text-white px-2 py-1 rounded whitespace-nowrap">Mark reviewed</button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="11" class="px-4 py-8 text-center text-gray-500">
                                {{ $showAll ? 'No till voucher exceptions yet.' : 'Nothing to review. Every till voucher redemption was applied.' }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">{{ $redemptions->links() }}</div>
    </div>
</x-admin-layout>
