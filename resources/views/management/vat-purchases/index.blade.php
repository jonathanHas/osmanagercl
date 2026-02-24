<x-admin-layout>
    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <!-- Header -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-6">
                <div class="p-6 text-gray-900">
                    <div class="flex justify-between items-start">
                        <div>
                            <h1 class="text-2xl font-semibold text-gray-900">VAT on Purchases</h1>
                            <p class="text-gray-600 mt-1">Purchase invoice VAT breakdown by rate, split into Retail (T1) and Non-Retail (T2)</p>
                        </div>
                        <a href="{{ route('management.vat-purchases.export', ['start_date' => $startDate->format('Y-m-d'), 'end_date' => $endDate->format('Y-m-d')]) }}"
                           class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                            <svg class="-ml-1 mr-2 h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                            </svg>
                            Export CSV
                        </a>
                    </div>
                </div>
            </div>

            <!-- Date Range Form -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-6">
                <div class="p-6 text-gray-900">
                    <form method="GET" action="{{ route('management.vat-purchases.index') }}" class="flex items-end space-x-4">
                        <div class="flex-1">
                            <label for="start_date" class="block text-sm font-medium text-gray-700 mb-1">Start Date</label>
                            <input type="date" name="start_date" id="start_date"
                                   value="{{ $startDate->format('Y-m-d') }}"
                                   class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        </div>
                        <div class="flex-1">
                            <label for="end_date" class="block text-sm font-medium text-gray-700 mb-1">End Date</label>
                            <input type="date" name="end_date" id="end_date"
                                   value="{{ $endDate->format('Y-m-d') }}"
                                   class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        </div>
                        <div>
                            <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-lg font-medium">
                                Generate Report
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Summary Cards -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
                <!-- Retail (T1) -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg border-l-4 border-green-500">
                    <div class="p-6">
                        <div class="flex items-center justify-between">
                            <h3 class="text-sm font-medium text-gray-500 uppercase tracking-wider">Retail (T1)</h3>
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                {{ $retail['invoice_count'] }} invoices
                            </span>
                        </div>
                        <div class="mt-3">
                            <p class="text-2xl font-bold text-gray-900">&euro;{{ number_format($retail['total_net'], 2) }}</p>
                            <p class="text-sm text-gray-500 mt-1">VAT: &euro;{{ number_format($retail['total_vat'], 2) }}</p>
                        </div>
                    </div>
                </div>

                <!-- Non-Retail (T2) -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg border-l-4 border-yellow-500">
                    <div class="p-6">
                        <div class="flex items-center justify-between">
                            <h3 class="text-sm font-medium text-gray-500 uppercase tracking-wider">Non-Retail (T2)</h3>
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">
                                {{ $nonRetail['invoice_count'] }} invoices
                            </span>
                        </div>
                        <div class="mt-3">
                            <p class="text-2xl font-bold text-gray-900">&euro;{{ number_format($nonRetail['total_net'], 2) }}</p>
                            <p class="text-sm text-gray-500 mt-1">VAT: &euro;{{ number_format($nonRetail['total_vat'], 2) }}</p>
                        </div>
                    </div>
                </div>

                <!-- Unclassified -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg border-l-4 border-gray-400">
                    <div class="p-6">
                        <div class="flex items-center justify-between">
                            <h3 class="text-sm font-medium text-gray-500 uppercase tracking-wider">Unclassified</h3>
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
                                {{ $unclassified['invoice_count'] }} invoices
                            </span>
                        </div>
                        <div class="mt-3">
                            <p class="text-2xl font-bold text-gray-900">&euro;{{ number_format($unclassified['total_net'], 2) }}</p>
                            <p class="text-sm text-gray-500 mt-1">VAT: &euro;{{ number_format($unclassified['total_vat'], 2) }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- VAT Rate Breakdown Table -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-6">
                <div class="p-6">
                    <h2 class="text-lg font-semibold text-gray-900 mb-4">VAT Rate Breakdown</h2>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">VAT Rate</th>
                                    <th class="px-4 py-3 text-right text-xs font-medium text-green-600 uppercase tracking-wider">Retail Net</th>
                                    <th class="px-4 py-3 text-right text-xs font-medium text-green-600 uppercase tracking-wider">Retail VAT</th>
                                    <th class="px-4 py-3 text-right text-xs font-medium text-yellow-600 uppercase tracking-wider">Non-Retail Net</th>
                                    <th class="px-4 py-3 text-right text-xs font-medium text-yellow-600 uppercase tracking-wider">Non-Retail VAT</th>
                                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Unclass. Net</th>
                                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Unclass. VAT</th>
                                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-700 uppercase tracking-wider bg-gray-100">Total Net</th>
                                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-700 uppercase tracking-wider bg-gray-100">Total VAT</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                @php
                                    $rates = [
                                        ['label' => '0%', 'net' => 'zero_net', 'vat' => 'zero_vat'],
                                        ['label' => '9%', 'net' => 'second_reduced_net', 'vat' => 'second_reduced_vat'],
                                        ['label' => '13.5%', 'net' => 'reduced_net', 'vat' => 'reduced_vat'],
                                        ['label' => '23%', 'net' => 'standard_net', 'vat' => 'standard_vat'],
                                    ];
                                @endphp
                                @foreach($rates as $rate)
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-4 py-3 text-sm font-medium text-gray-900">{{ $rate['label'] }}</td>
                                        <td class="px-4 py-3 text-sm text-right text-gray-700">&euro;{{ number_format($retail[$rate['net']], 2) }}</td>
                                        <td class="px-4 py-3 text-sm text-right text-gray-700">&euro;{{ number_format($retail[$rate['vat']], 2) }}</td>
                                        <td class="px-4 py-3 text-sm text-right text-gray-700">&euro;{{ number_format($nonRetail[$rate['net']], 2) }}</td>
                                        <td class="px-4 py-3 text-sm text-right text-gray-700">&euro;{{ number_format($nonRetail[$rate['vat']], 2) }}</td>
                                        <td class="px-4 py-3 text-sm text-right text-gray-500">&euro;{{ number_format($unclassified[$rate['net']], 2) }}</td>
                                        <td class="px-4 py-3 text-sm text-right text-gray-500">&euro;{{ number_format($unclassified[$rate['vat']], 2) }}</td>
                                        <td class="px-4 py-3 text-sm text-right font-medium text-gray-900 bg-gray-50">&euro;{{ number_format($totals[$rate['net']], 2) }}</td>
                                        <td class="px-4 py-3 text-sm text-right font-medium text-gray-900 bg-gray-50">&euro;{{ number_format($totals[$rate['vat']], 2) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot class="bg-gray-100">
                                <tr class="font-bold">
                                    <td class="px-4 py-3 text-sm text-gray-900">Total</td>
                                    <td class="px-4 py-3 text-sm text-right text-green-700">&euro;{{ number_format($retail['total_net'], 2) }}</td>
                                    <td class="px-4 py-3 text-sm text-right text-green-700">&euro;{{ number_format($retail['total_vat'], 2) }}</td>
                                    <td class="px-4 py-3 text-sm text-right text-yellow-700">&euro;{{ number_format($nonRetail['total_net'], 2) }}</td>
                                    <td class="px-4 py-3 text-sm text-right text-yellow-700">&euro;{{ number_format($nonRetail['total_vat'], 2) }}</td>
                                    <td class="px-4 py-3 text-sm text-right text-gray-500">&euro;{{ number_format($unclassified['total_net'], 2) }}</td>
                                    <td class="px-4 py-3 text-sm text-right text-gray-500">&euro;{{ number_format($unclassified['total_vat'], 2) }}</td>
                                    <td class="px-4 py-3 text-sm text-right text-gray-900 bg-gray-200">&euro;{{ number_format($totals['total_net'], 2) }}</td>
                                    <td class="px-4 py-3 text-sm text-right text-gray-900 bg-gray-200">&euro;{{ number_format($totals['total_vat'], 2) }}</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Invoice Detail Table -->
            @if($invoices->count() > 0)
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <div x-data="{ open: false }">
                            <button @click="open = !open" class="flex items-center text-lg font-semibold text-gray-900 mb-4 hover:text-blue-600">
                                <svg class="w-5 h-5 mr-2 transition-transform" :class="open ? 'rotate-90' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                                </svg>
                                Invoice Detail ({{ $invoices->count() }} invoices)
                            </button>
                            <div x-show="open" x-transition>
                                <div class="overflow-x-auto">
                                    <table class="min-w-full divide-y divide-gray-200">
                                        <thead class="bg-gray-50">
                                            <tr>
                                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Invoice #</th>
                                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Supplier</th>
                                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">RTD Class</th>
                                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Net</th>
                                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">VAT</th>
                                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Gross</th>
                                            </tr>
                                        </thead>
                                        <tbody class="bg-white divide-y divide-gray-200">
                                            @foreach($invoices as $invoice)
                                                @php
                                                    $classification = $invoice->supplier->rtd_classification ?? 'not_applicable';
                                                @endphp
                                                <tr class="hover:bg-gray-50">
                                                    <td class="px-4 py-2 text-sm text-gray-700">{{ $invoice->invoice_date->format('d M Y') }}</td>
                                                    <td class="px-4 py-2 text-sm text-gray-700">{{ $invoice->invoice_number }}</td>
                                                    <td class="px-4 py-2 text-sm text-gray-700">{{ $invoice->supplier_name ?? $invoice->supplier->name ?? '-' }}</td>
                                                    <td class="px-4 py-2 text-sm">
                                                        @if(in_array($classification, ['goods_simple', 'goods_parser']))
                                                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">Retail</span>
                                                        @elseif($classification === 'service_overhead')
                                                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">Non-Retail</span>
                                                        @else
                                                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-600">Unclassified</span>
                                                        @endif
                                                    </td>
                                                    <td class="px-4 py-2 text-sm text-right text-gray-700">&euro;{{ number_format($invoice->subtotal ?? 0, 2) }}</td>
                                                    <td class="px-4 py-2 text-sm text-right text-gray-700">&euro;{{ number_format($invoice->vat_amount ?? 0, 2) }}</td>
                                                    <td class="px-4 py-2 text-sm text-right font-medium text-gray-900">&euro;{{ number_format($invoice->total_amount ?? 0, 2) }}</td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </div>
</x-admin-layout>
