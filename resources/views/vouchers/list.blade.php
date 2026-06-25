<x-admin-layout>
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
        <div class="flex justify-between items-center mb-6">
            <h2 class="text-2xl font-bold text-gray-100">Vouchers</h2>
            <div class="flex gap-2">
                <a href="{{ route('vouchers.index') }}"
                   class="bg-gray-700 hover:bg-gray-600 text-white font-medium py-2 px-4 rounded">Till screen</a>
                <a href="{{ route('vouchers.generate') }}"
                   class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">+ Generate</a>
            </div>
        </div>

        <form method="GET" class="grid grid-cols-1 md:grid-cols-3 gap-3 mb-4 bg-gray-800 p-4 rounded">
            <div class="md:col-span-2">
                <label class="block text-xs text-gray-400 mb-1">Search code</label>
                <input type="text" name="q" value="{{ request('q') }}" placeholder="Voucher code…"
                       class="w-full bg-gray-900 border border-gray-700 rounded px-2 py-2 text-gray-100">
            </div>
            <div class="flex items-end gap-2">
                <select name="status" class="bg-gray-900 border border-gray-700 rounded px-2 py-2 text-gray-100">
                    <option value="">All statuses</option>
                    <option value="inactive" @selected(request('status') === 'inactive')>Inactive</option>
                    <option value="active" @selected(request('status') === 'active')>Active</option>
                    <option value="exhausted" @selected(request('status') === 'exhausted')>Exhausted</option>
                </select>
                <button type="submit" class="bg-gray-700 hover:bg-gray-600 text-white px-4 py-2 rounded">Filter</button>
                <a href="{{ route('vouchers.list') }}" class="text-gray-400 hover:text-gray-200 px-2 py-2">Reset</a>
            </div>
        </form>

        <div class="bg-gray-800 rounded shadow overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-700 text-sm">
                <thead class="bg-gray-900 text-gray-400">
                    <tr>
                        <th class="px-4 py-2 text-left">Code</th>
                        <th class="px-4 py-2 text-left">Status</th>
                        <th class="px-4 py-2 text-right">Initial</th>
                        <th class="px-4 py-2 text-right">Balance</th>
                        <th class="px-4 py-2 text-left">Created by</th>
                        <th class="px-4 py-2 text-left">Created</th>
                        <th class="px-4 py-2"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-700 text-gray-200">
                    @forelse ($vouchers as $voucher)
                        <tr class="hover:bg-gray-700/40">
                            <td class="px-4 py-2 font-mono font-medium">{{ $voucher->code }}</td>
                            <td class="px-4 py-2">
                                @php
                                    $badge = match ($voucher->status) {
                                        'active' => 'bg-green-800/50 text-green-300',
                                        'exhausted' => 'bg-gray-700 text-gray-400',
                                        default => 'bg-yellow-800/50 text-yellow-300',
                                    };
                                @endphp
                                <span class="text-xs px-2 py-0.5 rounded {{ $badge }}">{{ ucfirst($voucher->status) }}</span>
                            </td>
                            <td class="px-4 py-2 text-right">{{ $voucher->initial_value !== null ? '€'.number_format($voucher->initial_value, 2) : '—' }}</td>
                            <td class="px-4 py-2 text-right font-medium">€{{ number_format($voucher->current_balance, 2) }}</td>
                            <td class="px-4 py-2 text-gray-300">{{ $voucher->creator?->name ?? '—' }}</td>
                            <td class="px-4 py-2 text-gray-400 text-xs">{{ $voucher->created_at?->format('d M Y H:i') }}</td>
                            <td class="px-4 py-2 text-right space-x-3 whitespace-nowrap">
                                <a href="{{ route('vouchers.transactions', $voucher) }}" class="text-blue-400 hover:text-blue-300">Log ({{ $voucher->transactions_count }})</a>
                                <a href="{{ route('vouchers.print', ['ids' => $voucher->id]) }}" target="_blank" class="text-gray-400 hover:text-gray-200">Print</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="px-4 py-8 text-center text-gray-500">No vouchers yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">{{ $vouchers->links() }}</div>
    </div>
</x-admin-layout>
