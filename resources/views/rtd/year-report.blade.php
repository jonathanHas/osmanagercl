<x-admin-layout>
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
        {{-- Header --}}
        <div class="flex justify-between items-center mb-6">
            <div>
                <h2 class="text-2xl font-bold text-gray-100">RTD Accounting Year Report</h2>
                <p class="text-gray-400 text-sm mt-1">Aggregated goods for resale by VAT rate from frozen invoices</p>
            </div>
            <div class="flex space-x-2">
                <a href="{{ route('rtd.index') }}"
                   class="bg-gray-600 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded inline-flex items-center">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                    </svg>
                    Back to RTD
                </a>
            </div>
        </div>

        {{-- Date Range Filter --}}
        <div class="bg-gray-800 rounded-lg p-4 mb-6">
            <form action="{{ route('rtd.year-report') }}" method="GET" class="flex flex-wrap items-end gap-4">
                <div>
                    <label class="block text-sm text-gray-400 mb-1">Quick Select</label>
                    <select name="year" onchange="updateDateRange(this.value)"
                            class="bg-gray-700 text-white rounded-lg px-4 py-2 focus:ring-2 focus:ring-blue-500 focus:outline-none">
                        @foreach($availableYears as $y)
                            <option value="{{ $y }}" {{ $year == $y ? 'selected' : '' }}>{{ $y }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm text-gray-400 mb-1">Start Date</label>
                    <input type="date" name="start_date" value="{{ $startDate }}" id="start_date"
                           class="bg-gray-700 text-white rounded-lg px-4 py-2 focus:ring-2 focus:ring-blue-500 focus:outline-none">
                </div>
                <div>
                    <label class="block text-sm text-gray-400 mb-1">End Date</label>
                    <input type="date" name="end_date" value="{{ $endDate }}" id="end_date"
                           class="bg-gray-700 text-white rounded-lg px-4 py-2 focus:ring-2 focus:ring-blue-500 focus:outline-none">
                </div>
                <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg">
                    Update Report
                </button>
            </form>
        </div>

        {{-- Warning: Non-Frozen Invoices --}}
        @if($nonFrozenInvoices->count() > 0)
        <div class="bg-orange-950 border border-orange-500 rounded-lg p-4 mb-6">
            <div class="flex items-start">
                <svg class="w-6 h-6 text-orange-400 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                </svg>
                <div class="flex-1">
                    <h3 class="text-orange-300 font-semibold text-lg">{{ $nonFrozenInvoices->count() }} Invoice(s) Not Frozen</h3>
                    <p class="text-gray-300 text-sm mt-1">
                        The following invoices in this period are not included in the totals because they haven't been frozen yet.
                    </p>
                    <div class="mt-3 max-h-40 overflow-y-auto bg-gray-900/50 rounded p-2">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="text-orange-300 text-left border-b border-gray-700">
                                    <th class="pr-4 pb-2">Invoice #</th>
                                    <th class="pr-4 pb-2">Date</th>
                                    <th class="pr-4 pb-2">Total</th>
                                    <th class="pr-4 pb-2">Status</th>
                                    <th class="pb-2"></th>
                                </tr>
                            </thead>
                            <tbody class="text-gray-200">
                                @foreach($nonFrozenInvoices as $invoice)
                                <tr class="border-b border-gray-800 last:border-0">
                                    <td class="pr-4 py-2 font-mono">#{{ $invoice->invoice_number }}</td>
                                    <td class="pr-4 py-2">{{ $invoice->invoice_date->format('d/m/Y') }}</td>
                                    <td class="pr-4 py-2 font-mono">{{ number_format($invoice->total_amount, 2) }}</td>
                                    <td class="pr-4 py-2">
                                        @if($invoice->rtd_status === 'computed')
                                            <span class="px-2 py-0.5 rounded text-xs bg-blue-900 text-blue-300">Computed</span>
                                        @else
                                            <span class="px-2 py-0.5 rounded text-xs bg-gray-700 text-gray-300">Pending</span>
                                        @endif
                                    </td>
                                    <td class="py-2">
                                        <a href="{{ route('rtd.index', ['search' => $invoice->invoice_number]) }}"
                                           class="text-blue-400 hover:text-blue-300 text-xs whitespace-nowrap">
                                            View in RTD →
                                        </a>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        @endif

        {{-- Summary Totals --}}
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
            {{-- VAT Rate Breakdown --}}
            <div class="lg:col-span-2 bg-gray-800 rounded-lg p-6">
                <h3 class="text-lg font-semibold text-gray-100 mb-4">Goods for Resale by VAT Rate</h3>
                <table class="w-full">
                    <thead>
                        <tr class="border-b border-gray-700">
                            <th class="text-left text-gray-400 pb-2">VAT Rate</th>
                            <th class="text-right text-gray-400 pb-2">Net Amount</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-700">
                        <tr>
                            <td class="py-3">
                                <span class="inline-flex items-center">
                                    <span class="w-3 h-3 rounded-full bg-purple-500 mr-2"></span>
                                    0% (Zero Rated)
                                </span>
                            </td>
                            <td class="py-3 text-right text-white font-mono text-lg">{{ number_format($totals['0'], 2) }}</td>
                        </tr>
                        <tr>
                            <td class="py-3">
                                <span class="inline-flex items-center">
                                    <span class="w-3 h-3 rounded-full bg-blue-500 mr-2"></span>
                                    9% (Reduced)
                                </span>
                            </td>
                            <td class="py-3 text-right text-white font-mono text-lg">{{ number_format($totals['9'], 2) }}</td>
                        </tr>
                        <tr>
                            <td class="py-3">
                                <span class="inline-flex items-center">
                                    <span class="w-3 h-3 rounded-full bg-yellow-500 mr-2"></span>
                                    13.5% (Second Reduced)
                                </span>
                            </td>
                            <td class="py-3 text-right text-white font-mono text-lg">{{ number_format($totals['13.5'], 2) }}</td>
                        </tr>
                        <tr>
                            <td class="py-3">
                                <span class="inline-flex items-center">
                                    <span class="w-3 h-3 rounded-full bg-green-500 mr-2"></span>
                                    23% (Standard)
                                </span>
                            </td>
                            <td class="py-3 text-right text-white font-mono text-lg">{{ number_format($totals['23'], 2) }}</td>
                        </tr>
                        <tr class="border-t-2 border-gray-600">
                            <td class="py-3 font-semibold text-gray-200">Total Goods for Resale</td>
                            <td class="py-3 text-right text-green-400 font-mono text-xl font-bold">{{ number_format($grandTotal, 2) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            {{-- Stats Card --}}
            <div class="bg-gray-800 rounded-lg p-6">
                <h3 class="text-lg font-semibold text-gray-100 mb-4">Report Summary</h3>
                <dl class="space-y-4">
                    <div>
                        <dt class="text-gray-400 text-sm">Period</dt>
                        <dd class="text-white font-semibold">{{ date('d M Y', strtotime($startDate)) }} - {{ date('d M Y', strtotime($endDate)) }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-400 text-sm">Frozen Invoices Included</dt>
                        <dd class="text-green-400 font-semibold text-2xl">{{ $frozenInvoices->count() }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-400 text-sm">Non-Frozen (Not Included)</dt>
                        <dd class="text-yellow-400 font-semibold text-2xl">{{ $nonFrozenInvoices->count() }}</dd>
                    </div>
                    <div class="pt-4 border-t border-gray-700">
                        <dt class="text-gray-400 text-sm">Excluded from RTD</dt>
                        <dd class="text-gray-300 text-sm mt-1">
                            Freight: {{ number_format($excludedTotals['freight'], 2) }}<br>
                            Deposits: {{ number_format($excludedTotals['deposits'], 2) }}
                            @if(($excludedTotals['drs'] ?? 0) > 0)
                            <br>DRS: {{ number_format($excludedTotals['drs'], 2) }}
                            @endif
                        </dd>
                    </div>
                </dl>
            </div>
        </div>

        {{-- Included Invoices List --}}
        <div class="bg-gray-800 rounded-lg overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-700">
                <h3 class="text-lg font-semibold text-gray-100">Frozen Invoices Included ({{ $frozenInvoices->count() }})</h3>
            </div>
            @if($frozenInvoices->isEmpty())
                <div class="p-8 text-center text-gray-400">
                    <p>No frozen invoices found for this period.</p>
                </div>
            @else
                <table class="min-w-full divide-y divide-gray-700">
                    <thead class="bg-gray-900">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase">Invoice #</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase">Date</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-400 uppercase">0%</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-400 uppercase">9%</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-400 uppercase">13.5%</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-400 uppercase">23%</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-400 uppercase">RTD Total</th>
                            <th class="px-4 py-3 text-center text-xs font-medium text-gray-400 uppercase">Frozen</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-700">
                        @foreach($frozenInvoices as $invoice)
                            @php
                                $gfr = $invoice->rtd_snapshot['breakdown']['goods_for_resale'] ?? [];
                                $invoiceRtdTotal = array_sum(array_map('floatval', $gfr));
                            @endphp
                            <tr class="hover:bg-gray-750">
                                <td class="px-4 py-3">
                                    <a href="{{ route('invoices.show', $invoice) }}" class="text-blue-400 hover:text-blue-300">
                                        #{{ $invoice->invoice_number }}
                                    </a>
                                </td>
                                <td class="px-4 py-3 text-gray-300">{{ $invoice->invoice_date->format('d/m/Y') }}</td>
                                <td class="px-4 py-3 text-right text-gray-300 font-mono">{{ number_format($gfr['0'] ?? 0, 2) }}</td>
                                <td class="px-4 py-3 text-right text-gray-300 font-mono">{{ number_format($gfr['9'] ?? 0, 2) }}</td>
                                <td class="px-4 py-3 text-right text-gray-300 font-mono">{{ number_format($gfr['13.5'] ?? 0, 2) }}</td>
                                <td class="px-4 py-3 text-right text-gray-300 font-mono">{{ number_format($gfr['23'] ?? 0, 2) }}</td>
                                <td class="px-4 py-3 text-right text-white font-mono font-semibold">{{ number_format($invoiceRtdTotal, 2) }}</td>
                                <td class="px-4 py-3 text-center text-xs text-gray-400">
                                    {{ $invoice->rtd_accepted_at?->format('d/m/Y') }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot class="bg-gray-900 border-t-2 border-gray-600">
                        <tr>
                            <td class="px-4 py-3 font-semibold text-gray-200" colspan="2">Totals</td>
                            <td class="px-4 py-3 text-right text-white font-mono font-bold">{{ number_format($totals['0'], 2) }}</td>
                            <td class="px-4 py-3 text-right text-white font-mono font-bold">{{ number_format($totals['9'], 2) }}</td>
                            <td class="px-4 py-3 text-right text-white font-mono font-bold">{{ number_format($totals['13.5'], 2) }}</td>
                            <td class="px-4 py-3 text-right text-white font-mono font-bold">{{ number_format($totals['23'], 2) }}</td>
                            <td class="px-4 py-3 text-right text-green-400 font-mono font-bold text-lg">{{ number_format($grandTotal, 2) }}</td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
            @endif
        </div>
    </div>

    <script>
        function updateDateRange(year) {
            document.getElementById('start_date').value = year + '-01-01';
            document.getElementById('end_date').value = year + '-12-31';
        }
    </script>
</x-admin-layout>
