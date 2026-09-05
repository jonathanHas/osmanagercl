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
            @php
                $rangeParams = array_filter(['customer' => $customer->id, 'from' => $from?->toDateString(), 'to' => $to->toDateString()]);
            @endphp
            <div class="flex flex-wrap items-center gap-2">
                <a href="{{ route('customers.show', $customer) }}" class="text-gray-400 hover:text-gray-200 mr-2">← Customer</a>
                <a href="{{ route('customers.statement.print', $rangeParams) }}" target="_blank"
                   class="bg-gray-700 hover:bg-gray-600 text-white px-4 py-2 rounded">Print</a>
                <a href="{{ route('customers.statement.pdf', $rangeParams) }}"
                   class="bg-purple-600 hover:bg-purple-700 text-white px-4 py-2 rounded">Download PDF</a>
                @if ($customer->email)
                    <form method="POST" action="{{ route('customers.statement.email', $rangeParams) }}" class="inline"
                          onsubmit="return confirm('Email this statement to {{ $customer->email }}?');">
                        @csrf
                        <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded">Email</button>
                    </form>
                @else
                    <span class="bg-gray-800 text-gray-500 px-4 py-2 rounded cursor-not-allowed border border-gray-700"
                          title="This customer has no email address">Email</span>
                @endif
            </div>
        </div>

        @if (session('status'))
            <div class="mb-4 rounded bg-green-700 text-white px-4 py-2">{{ session('status') }}</div>
        @endif
        @if (session('error'))
            <div class="mb-4 rounded bg-red-700 text-white px-4 py-2">{{ session('error') }}</div>
        @endif

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

        @if (! empty($open_invoices))
            <div class="bg-gray-800 rounded p-4 mb-4">
                <h3 class="text-gray-400 text-xs uppercase tracking-wide mb-3">Aged balance</h3>
                <div class="grid grid-cols-2 md:grid-cols-6 gap-3 text-sm">
                    @foreach ($bucket_labels as $key => $label)
                        <div class="rounded p-3 {{ $key !== 'current' && $aging[$key] > 0.005 ? 'bg-red-900/30 border border-red-800/50' : 'bg-gray-900' }}">
                            <div class="text-xs {{ $key !== 'current' && $aging[$key] > 0.005 ? 'text-red-300' : 'text-gray-500' }}">{{ $label }}</div>
                            <div class="font-mono {{ $key !== 'current' && $aging[$key] > 0.005 ? 'text-red-300' : 'text-gray-200' }}">
                                @if ($aging[$key] > 0.005)€{{ number_format($aging[$key], 2) }}@else<span class="text-gray-600">—</span>@endif
                            </div>
                        </div>
                    @endforeach
                    <div class="rounded p-3 bg-gray-900 border border-gray-700">
                        <div class="text-xs text-gray-500">Total due</div>
                        <div class="font-mono font-bold text-gray-100">€{{ number_format($aging['total'], 2) }}</div>
                    </div>
                </div>
            </div>

            <div class="bg-gray-800 rounded shadow overflow-x-auto mb-4">
                <div class="px-4 py-3 border-b border-gray-700">
                    <h3 class="font-semibold text-gray-200">Outstanding invoices as at {{ $to->format('d M Y') }}</h3>
                </div>
                <table class="min-w-full divide-y divide-gray-700 text-sm">
                    <thead class="bg-gray-900 text-gray-400">
                        <tr>
                            <th class="px-4 py-2 text-left">Invoice</th>
                            <th class="px-4 py-2 text-left">Issued</th>
                            <th class="px-4 py-2 text-left">Due</th>
                            <th class="px-4 py-2 text-right">Total</th>
                            <th class="px-4 py-2 text-right">Paid</th>
                            <th class="px-4 py-2 text-right">Outstanding</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-700 text-gray-200">
                        @foreach ($open_invoices as $row)
                            <tr>
                                <td class="px-4 py-2 font-mono">
                                    <a href="{{ route('customer-invoices.show', $row['invoice']) }}"
                                       class="text-blue-400 hover:text-blue-300">{{ $row['invoice_number'] }}</a>
                                    @if ($row['status'] === 'partial')
                                        <span class="ml-1 text-[10px] uppercase tracking-wide bg-yellow-800 text-yellow-100 px-1.5 py-0.5 rounded font-sans">part-paid</span>
                                    @endif
                                    @if ($row['paid'] > 0.005 && ! empty($row['payments']))
                                        <div class="text-xs text-gray-400 mt-0.5 font-sans">
                                            @foreach ($row['payments'] as $p)
                                                <a href="{{ route('customer-payments.show', $p['payment']) }}"
                                                   class="text-gray-400 hover:text-gray-200">{{ $p['date']?->format('d M') }}
                                                    €{{ number_format($p['amount'], 2) }}</a>@if (! $loop->last)<span class="text-gray-600"> · </span>@endif
                                            @endforeach
                                        </div>
                                    @endif
                                </td>
                                <td class="px-4 py-2">{{ $row['issue_date']->format('Y-m-d') }}</td>
                                <td class="px-4 py-2 {{ $row['days_overdue'] > 0 ? 'text-red-400' : '' }}">
                                    {{ $row['due_date']->format('Y-m-d') }}
                                    @if ($row['due_date_is_derived'])
                                        <span class="text-xs text-gray-500" title="Derived from {{ $customer->terms_days }}-day payment terms — no due date set on the invoice">*</span>
                                    @endif
                                    @if ($row['days_overdue'] > 0)
                                        <div class="text-xs text-red-400">{{ $row['days_overdue'] }} {{ \Illuminate\Support\Str::plural('day', $row['days_overdue']) }} overdue</div>
                                    @endif
                                </td>
                                <td class="px-4 py-2 text-right font-mono">€{{ number_format($row['total'], 2) }}</td>
                                <td class="px-4 py-2 text-right font-mono">@if ($row['paid'] > 0)€{{ number_format($row['paid'], 2) }}@endif</td>
                                <td class="px-4 py-2 text-right font-mono font-semibold">
                                    €{{ number_format($row['outstanding'], 2) }}
                                    @if (($unallocated_credit ?? 0) > 0.005 && $row['invoice']->isIssued())
                                        <a href="{{ route('customer-invoices.apply-credit', $row['invoice']) }}"
                                           class="block text-xs font-normal text-blue-400 hover:text-blue-300"
                                           title="Apply this customer's unapplied credit to this invoice">apply credit →</a>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                @if (collect($open_invoices)->contains('due_date_is_derived', true))
                    <div class="px-4 py-2 text-xs text-gray-500 border-t border-gray-700">
                        * Due date derived from {{ $customer->terms_days }}-day payment terms — no due date was set on the invoice.
                    </div>
                @endif
            </div>
        @endif

        {{-- Outside the open-invoices guard above: a customer can hold credit with
             no open invoices at all, and that is exactly when they most need to
             see it. Unallocated credit also spans every payment up to $to, while
             the ledger only covers [$from, $to]. --}}
        @if (! empty($credit_payments))
            <div class="bg-gray-800 rounded shadow overflow-x-auto mb-4">
                <div class="px-4 py-3 border-b border-gray-700">
                    <h3 class="font-semibold text-gray-200">Payments on account</h3>
                    <p class="text-xs text-gray-500 mt-0.5">Received but not yet applied to a specific invoice.</p>
                </div>
                <table class="min-w-full divide-y divide-gray-700 text-sm">
                    <thead class="bg-gray-900 text-gray-400">
                        <tr>
                            <th class="px-4 py-2 text-left">Date</th>
                            <th class="px-4 py-2 text-left">Method</th>
                            <th class="px-4 py-2 text-left">Reference</th>
                            <th class="px-4 py-2 text-right">Payment</th>
                            <th class="px-4 py-2 text-right">Not yet applied</th>
                            <th class="px-4 py-2"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-700 text-gray-200">
                        @foreach ($credit_payments as $p)
                            <tr>
                                <td class="px-4 py-2">
                                    <a href="{{ route('customer-payments.show', $p['payment']) }}"
                                       class="text-blue-400 hover:text-blue-300">{{ $p['date']->format('Y-m-d') }}</a>
                                </td>
                                <td class="px-4 py-2">{{ $p['method'] }}</td>
                                <td class="px-4 py-2 text-gray-400">{{ $p['reference'] ?: '—' }}</td>
                                <td class="px-4 py-2 text-right font-mono">€{{ number_format($p['amount'], 2) }}</td>
                                <td class="px-4 py-2 text-right font-mono text-teal-300">€{{ number_format($p['unapplied'], 2) }}</td>
                                <td class="px-4 py-2 text-right">
                                    <a href="{{ route('customer-payments.allocations.edit', $p['payment']) }}"
                                       class="text-blue-400 hover:text-blue-300 text-xs">match to invoices →</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot class="bg-gray-900 text-gray-200 font-semibold">
                        <tr>
                            <td colspan="4" class="px-4 py-2 text-right">Total on account</td>
                            <td class="px-4 py-2 text-right font-mono text-teal-300">€{{ number_format($unallocated_credit, 2) }}</td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        @endif

        @if (($unallocated_credit ?? 0) > 0.005)
            {{-- The statement showing its own proof: the documented identity
                 aged total - unallocated credit == closing balance, in words. --}}
            <div class="bg-gray-800 rounded p-4 mb-4 text-sm text-green-300">
                €{{ number_format($aging['total'], 2) }} outstanding on invoices, less
                €{{ number_format($unallocated_credit, 2) }} received on account, leaves
                @if ($closing_balance < -0.005)
                    €{{ number_format(abs($closing_balance), 2) }} in your favour.
                @else
                    a balance of €{{ number_format($closing_balance, 2) }}.
                @endif
            </div>
        @endif

        <div class="bg-gray-800 rounded shadow overflow-x-auto">
            <div class="px-4 py-3 border-b border-gray-700">
                <h3 class="font-semibold text-gray-200">Account activity</h3>
            </div>
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
                            {{-- copy(): this Carbon is shared with the rest of the context. --}}
                            <td class="px-4 py-2 text-gray-400">{{ $from->copy()->subDay()->format('Y-m-d') }}</td>
                            <td class="px-4 py-2 text-gray-400">Opening balance</td>
                            <td></td><td></td>
                            <td class="px-4 py-2 text-right font-mono">€{{ number_format($opening_balance, 2) }}</td>
                        </tr>
                    @endif
                    @forelse ($events as $e)
                        <tr>
                            <td class="px-4 py-2">{{ $e['date']->format('Y-m-d') }}</td>
                            <td class="px-4 py-2">
                                {{ $e['description'] }}
                                @if (! empty($e['allocations']))
                                    <div class="text-xs text-gray-400 mt-0.5">
                                        @foreach ($e['allocations'] as $a)
                                            <span class="whitespace-nowrap">@if ($a['invoice'])<a href="{{ route('customer-invoices.show', $a['invoice']) }}" class="text-blue-400 hover:text-blue-300">{{ $a['invoice_number'] }}</a>@else{{ $a['invoice_number'] }}@endif
                                                €{{ number_format($a['amount'], 2) }}</span>@if (! $loop->last)<span class="text-gray-600"> · </span>@endif
                                        @endforeach
                                    </div>
                                @endif
                                @if ($e['unapplied'] > 0.005)
                                    <div class="text-xs text-teal-300 mt-0.5">
                                        €{{ number_format($e['unapplied'], 2) }} on account
                                        <a href="{{ route('customer-payments.allocations.edit', $e['payment']) }}"
                                           class="text-blue-400 hover:text-blue-300">— match →</a>
                                    </div>
                                @elseif ($e['unapplied'] < -0.005)
                                    {{-- Legacy data only: assertAllocationsValid() blocks new
                                         over-allocation, but silence is what let it hide before. --}}
                                    <div class="text-xs text-red-400 mt-0.5">Over-applied by €{{ number_format(abs($e['unapplied']), 2) }}</div>
                                @endif
                            </td>
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
