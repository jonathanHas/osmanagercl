<x-admin-layout>
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
        <div class="flex flex-wrap justify-between items-center gap-2 mb-6">
            <div>
                <h2 class="text-2xl font-bold text-gray-100">Aged Debtors</h2>
                <p class="text-gray-400 text-sm mt-1">Outstanding customer balances as at {{ $as_of->format('d M Y') }}</p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <a href="{{ route('customers.debtors.export', array_filter(['as_of' => $as_of->toDateString(), 'include_credits' => $include_credits ? 1 : null])) }}"
                   class="bg-gray-700 hover:bg-gray-600 text-white px-4 py-2 rounded text-sm">Export CSV</a>
                <form method="POST" action="{{ route('customers.debtors.send', ['as_of' => $as_of->toDateString()]) }}" class="inline"
                      onsubmit="return confirm('Queue statement emails for all opted-in customers with a balance?');">
                    @csrf
                    <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded text-sm">
                        Email all statements
                    </button>
                </form>
            </div>
        </div>

        @if (session('status'))
            <div class="mb-4 rounded bg-green-700 text-white px-4 py-2">{{ session('status') }}</div>
        @endif
        @if (session('error'))
            <div class="mb-4 rounded bg-red-700 text-white px-4 py-2">{{ session('error') }}</div>
        @endif

        @if (config('mail.default') === 'log')
            <div class="mb-4 rounded bg-yellow-900/40 border border-yellow-700 text-yellow-200 px-4 py-2 text-sm">
                Mail is set to <code class="font-mono">log</code> — statement emails are written to the application log and
                are <strong>not</strong> delivered to customers. Set <code class="font-mono">MAIL_MAILER</code> to enable real sending.
            </div>
        @endif

        <form method="GET" class="bg-gray-800 p-4 rounded mb-4 grid grid-cols-1 md:grid-cols-3 gap-3">
            <div>
                <label class="block text-xs text-gray-400 mb-1">As at</label>
                <input type="date" name="as_of" value="{{ $as_of->toDateString() }}"
                       class="w-full bg-gray-900 border border-gray-700 rounded px-2 py-1 text-gray-100">
            </div>
            <div class="flex items-end">
                <label class="inline-flex items-center text-sm text-gray-300">
                    <input type="checkbox" name="include_credits" value="1" @checked($include_credits)
                           class="bg-gray-900 border-gray-700 rounded mr-2">
                    Include accounts in credit
                </label>
            </div>
            <div class="flex items-end gap-2">
                <button type="submit" class="bg-gray-700 hover:bg-gray-600 text-white px-4 py-1 rounded">Filter</button>
                <a href="{{ route('customers.debtors') }}" class="text-gray-400 hover:text-gray-200 px-2 py-1">Reset</a>
            </div>
        </form>

        <div class="grid grid-cols-2 md:grid-cols-6 gap-3 mb-4 text-sm">
            @foreach ($bucket_labels as $key => $label)
                <div class="rounded p-3 {{ $key !== 'current' && $totals[$key] > 0.005 ? 'bg-red-900/30 border border-red-800/50' : 'bg-gray-800' }}">
                    <div class="text-xs {{ $key !== 'current' && $totals[$key] > 0.005 ? 'text-red-300' : 'text-gray-500' }} uppercase">{{ $label }}</div>
                    <div class="font-mono {{ $key !== 'current' && $totals[$key] > 0.005 ? 'text-red-300' : 'text-gray-100' }}">
                        €{{ number_format($totals[$key], 2) }}
                    </div>
                </div>
            @endforeach
            <div class="rounded p-3 bg-gray-800 border border-gray-600">
                <div class="text-xs text-gray-400 uppercase">Total due</div>
                <div class="font-mono font-bold text-gray-100">€{{ number_format($totals['total'], 2) }}</div>
            </div>
        </div>

        <div class="bg-gray-800 rounded shadow overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-700 text-sm">
                <thead class="bg-gray-900 text-gray-400">
                    <tr>
                        <th class="px-4 py-2 text-left">Customer</th>
                        <th class="px-4 py-2 text-right">Balance</th>
                        @foreach ($bucket_labels as $label)
                            <th class="px-3 py-2 text-right whitespace-nowrap">{{ $label }}</th>
                        @endforeach
                        <th class="px-3 py-2 text-right">Open</th>
                        <th class="px-4 py-2"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-700 text-gray-200">
                    @forelse ($rows as $row)
                        <tr class="hover:bg-gray-700/40">
                            <td class="px-4 py-2">
                                <a href="{{ route('customers.show', $row['customer']) }}" class="text-blue-400 hover:text-blue-300 font-medium">
                                    {{ $row['customer']->name }}
                                </a>
                                <div class="text-xs text-gray-500">
                                    {{ $row['customer']->email ?: 'no email' }}
                                    @if (! $row['customer']->send_statements)
                                        <span class="ml-1 text-gray-600" title="Not opted in to emailed statements">· not opted in</span>
                                    @endif
                                </div>
                            </td>
                            <td class="px-4 py-2 text-right font-mono {{ $row['balance'] < -0.005 ? 'text-green-400' : 'text-gray-100' }}">
                                @if ($row['balance'] < -0.005)
                                    €{{ number_format(abs($row['balance']), 2) }} cr
                                @else
                                    €{{ number_format($row['balance'], 2) }}
                                @endif
                            </td>
                            @foreach ($bucket_labels as $key => $label)
                                <td class="px-3 py-2 text-right font-mono {{ $key !== 'current' && $row['aging'][$key] > 0.005 ? 'text-red-400' : '' }}">
                                    @if ($row['aging'][$key] > 0.005)€{{ number_format($row['aging'][$key], 2) }}@else<span class="text-gray-600">—</span>@endif
                                </td>
                            @endforeach
                            <td class="px-3 py-2 text-right text-gray-400">{{ $row['open_count'] }}</td>
                            <td class="px-4 py-2 text-right whitespace-nowrap space-x-2">
                                <a href="{{ route('customers.statement', $row['customer']) }}" class="text-blue-400 hover:text-blue-300">Statement</a>
                                <a href="{{ route('customers.statement.print', $row['customer']) }}" target="_blank" class="text-gray-400 hover:text-gray-200">Print</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="{{ 4 + count($bucket_labels) }}" class="px-4 py-8 text-center text-gray-500">
                            No outstanding balances. 🎉
                        </td></tr>
                    @endforelse
                </tbody>
                @if (! empty($rows))
                    <tfoot class="bg-gray-900 text-gray-200 font-semibold">
                        <tr>
                            <td class="px-4 py-2">Total ({{ count($rows) }})</td>
                            <td class="px-4 py-2 text-right font-mono">€{{ number_format($totals['balance'], 2) }}</td>
                            @foreach ($bucket_labels as $key => $label)
                                <td class="px-3 py-2 text-right font-mono">€{{ number_format($totals[$key], 2) }}</td>
                            @endforeach
                            <td colspan="2"></td>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>
    </div>
</x-admin-layout>
