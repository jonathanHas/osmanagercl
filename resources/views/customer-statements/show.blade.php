<x-admin-layout>
    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
        <div class="flex flex-wrap justify-between items-center gap-2 mb-6">
            <div>
                <h2 class="text-2xl font-bold text-gray-100">Statement — {{ $customer->name }}</h2>
                <p class="text-gray-400 text-sm mt-1">
                    @if ($from)
                        From {{ $from->format('d M Y') }} to {{ $to->format('d M Y') }}
                    @else
                        All activity up to {{ $to->format('d M Y') }}
                    @endif
                </p>
            </div>
            <div class="space-x-2">
                <a href="{{ route('customers.show', $customer) }}" class="text-gray-400 hover:text-gray-200">← Customer</a>
                <a href="{{ route('customers.statement.pdf', array_filter(['customer' => $customer->id, 'from' => $from?->toDateString(), 'to' => $to->toDateString()])) }}"
                   class="bg-purple-600 hover:bg-purple-700 text-white px-4 py-2 rounded">Download PDF</a>
            </div>
        </div>

        <form method="GET" class="bg-gray-800 p-4 rounded mb-4 grid grid-cols-1 md:grid-cols-3 gap-3">
            <div>
                <label class="block text-xs text-gray-400 mb-1">From</label>
                <input type="date" name="from" value="{{ $from?->toDateString() }}"
                       class="w-full bg-gray-900 border border-gray-700 rounded px-2 py-1 text-gray-100">
            </div>
            <div>
                <label class="block text-xs text-gray-400 mb-1">To</label>
                <input type="date" name="to" value="{{ $to->toDateString() }}"
                       class="w-full bg-gray-900 border border-gray-700 rounded px-2 py-1 text-gray-100">
            </div>
            <div class="flex items-end gap-2">
                <button type="submit" class="bg-gray-700 hover:bg-gray-600 text-white px-4 py-1 rounded">Filter</button>
                <a href="{{ route('customers.statement', $customer) }}" class="text-gray-400 hover:text-gray-200 px-2 py-1">Reset</a>
            </div>
        </form>

        <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-4 text-sm">
            <div class="bg-gray-800 rounded p-3">
                <div class="text-xs text-gray-500 uppercase">Opening</div>
                <div class="font-mono text-gray-100">€{{ number_format($opening_balance, 2) }}</div>
            </div>
            <div class="bg-gray-800 rounded p-3">
                <div class="text-xs text-gray-500 uppercase">Invoiced</div>
                <div class="font-mono text-gray-100">€{{ number_format($range_invoiced, 2) }}</div>
            </div>
            <div class="bg-gray-800 rounded p-3">
                <div class="text-xs text-gray-500 uppercase">Paid</div>
                <div class="font-mono text-gray-100">€{{ number_format($range_paid, 2) }}</div>
            </div>
            <div class="bg-gray-800 rounded p-3">
                <div class="text-xs text-gray-500 uppercase">Closing</div>
                <div class="font-mono {{ $closing_balance > 0.005 ? 'text-red-400' : ($closing_balance < -0.005 ? 'text-green-400' : 'text-gray-100') }}">
                    €{{ number_format(abs($closing_balance), 2) }}
                    @if ($closing_balance < -0.005) <span class="text-xs">credit</span> @endif
                </div>
            </div>
        </div>

        <div class="bg-gray-800 rounded shadow overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-700 text-sm">
                <thead class="bg-gray-900 text-gray-400">
                    <tr>
                        <th class="px-4 py-2 text-left">Date</th>
                        <th class="px-4 py-2 text-left">Description</th>
                        <th class="px-4 py-2 text-right">Debit</th>
                        <th class="px-4 py-2 text-right">Credit</th>
                        <th class="px-4 py-2 text-right">Balance</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-700 text-gray-200">
                    @if ($from && abs($opening_balance) > 0.005)
                        <tr class="bg-gray-700/30 italic">
                            <td class="px-4 py-2 text-gray-400">{{ $from->subDay()->format('Y-m-d') }}</td>
                            <td class="px-4 py-2 text-gray-400">Opening balance</td>
                            <td></td><td></td>
                            <td class="px-4 py-2 text-right font-mono">€{{ number_format($opening_balance, 2) }}</td>
                        </tr>
                    @endif
                    @forelse ($events as $e)
                        <tr>
                            <td class="px-4 py-2">{{ $e['date']->format('Y-m-d') }}</td>
                            <td class="px-4 py-2">{{ $e['description'] }}</td>
                            <td class="px-4 py-2 text-right font-mono">@if ($e['debit'] > 0)€{{ number_format($e['debit'], 2) }}@endif</td>
                            <td class="px-4 py-2 text-right font-mono">@if ($e['credit'] > 0)€{{ number_format($e['credit'], 2) }}@endif</td>
                            <td class="px-4 py-2 text-right font-mono">€{{ number_format($e['balance'], 2) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-4 py-6 text-center text-gray-500">No activity in this period.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-admin-layout>
