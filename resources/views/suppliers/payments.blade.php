<x-admin-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Supplier Payments
            </h2>
            @if(isset($payments) && $payments->count() > 0)
                <a href="{{ route('suppliers.payments.export', ['start_date' => $startDate, 'end_date' => $endDate, 'sort' => $sort ?? 'date_desc', 'group_by' => $groupBy ?? 'none']) }}"
                   class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm font-medium">
                    <i class="fas fa-download mr-2"></i>Export CSV
                </a>
            @endif
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <!-- Back Navigation -->
            <div class="mb-4">
                <a href="{{ route('suppliers.index') }}" class="text-blue-600 hover:text-blue-800 text-sm">
                    <i class="fas fa-arrow-left mr-1"></i> Back to Suppliers
                </a>
            </div>

            <!-- Date Range Form -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-6">
                <div class="p-6">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Filter & Sort Options</h3>
                    <form method="GET" action="{{ route('suppliers.payments') }}" class="space-y-4">
                        <div class="flex flex-wrap items-end gap-4">
                            <div class="flex-1 min-w-[140px] max-w-[180px]">
                                <label for="start_date" class="block text-sm font-medium text-gray-700 mb-1">
                                    Start Date
                                </label>
                                <input type="date"
                                       id="start_date"
                                       name="start_date"
                                       value="{{ $startDate }}"
                                       max="{{ now()->format('Y-m-d') }}"
                                       class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                            </div>
                            <div class="flex-1 min-w-[140px] max-w-[180px]">
                                <label for="end_date" class="block text-sm font-medium text-gray-700 mb-1">
                                    End Date
                                </label>
                                <input type="date"
                                       id="end_date"
                                       name="end_date"
                                       value="{{ $endDate }}"
                                       max="{{ now()->format('Y-m-d') }}"
                                       class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                            </div>
                            <div class="flex-1 min-w-[140px] max-w-[180px]">
                                <label for="sort" class="block text-sm font-medium text-gray-700 mb-1">
                                    Sort By
                                </label>
                                <select id="sort"
                                        name="sort"
                                        class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                                    <option value="date_desc" {{ ($sort ?? 'date_desc') === 'date_desc' ? 'selected' : '' }}>Date (Newest)</option>
                                    <option value="date_asc" {{ ($sort ?? '') === 'date_asc' ? 'selected' : '' }}>Date (Oldest)</option>
                                    <option value="supplier_asc" {{ ($sort ?? '') === 'supplier_asc' ? 'selected' : '' }}>Supplier (A-Z)</option>
                                    <option value="supplier_desc" {{ ($sort ?? '') === 'supplier_desc' ? 'selected' : '' }}>Supplier (Z-A)</option>
                                </select>
                            </div>
                            <div class="flex items-center pt-6">
                                <input type="checkbox"
                                       id="group_by_supplier"
                                       name="group_by"
                                       value="supplier"
                                       {{ ($groupBy ?? 'none') === 'supplier' ? 'checked' : '' }}
                                       class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                                <label for="group_by_supplier" class="ml-2 block text-sm text-gray-700">
                                    Group by Supplier
                                </label>
                            </div>
                            <div>
                                <button type="submit"
                                        class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-md text-sm font-medium">
                                    View Payments
                                </button>
                            </div>
                        </div>
                    </form>
                    <p class="mt-2 text-sm text-gray-600">
                        View all supplier invoice payments within the selected date range.
                    </p>
                </div>
            </div>

            @if(isset($payments))
                <!-- Summary Stats -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-6">
                    <div class="p-6">
                        <h3 class="text-lg font-medium text-gray-900 mb-4">
                            Payments from {{ Carbon\Carbon::parse($startDate)->format('F j, Y') }} to {{ Carbon\Carbon::parse($endDate)->format('F j, Y') }}
                        </h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                            <div class="bg-blue-50 p-4 rounded-lg">
                                <div class="text-2xl font-bold text-blue-600">{{ number_format($paymentCount) }}</div>
                                <div class="text-sm text-gray-600">Total Payments</div>
                            </div>
                            <div class="bg-green-50 p-4 rounded-lg">
                                <div class="text-2xl font-bold text-green-600">&euro;{{ number_format($totalPayments, 2) }}</div>
                                <div class="text-sm text-gray-600">Total Amount</div>
                            </div>
                            @foreach($methodTotals as $method => $total)
                                <div class="bg-gray-50 p-4 rounded-lg">
                                    <div class="text-2xl font-bold text-gray-700">&euro;{{ number_format($total, 2) }}</div>
                                    <div class="text-sm text-gray-600">{{ ucfirst(str_replace('_', ' ', $method ?: 'Unknown')) }}</div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>

                @if($payments->count() > 0)
                    @if(isset($groupedPayments) && $groupedPayments)
                        <!-- Grouped View -->
                        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-4" x-data="{ expandAll: false }">
                            <div class="p-4">
                                <div class="flex justify-between items-center">
                                    <h3 class="text-md font-medium text-gray-900">Payments by Supplier</h3>
                                    <button @click="expandAll = !expandAll; $dispatch('toggle-all-payments', { expand: expandAll })"
                                            class="text-blue-600 hover:text-blue-800 text-sm font-medium">
                                        <span x-text="expandAll ? 'Collapse All' : 'Expand All'"></span>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div x-data="{ suppliers: {} }" @toggle-all-payments.window="Object.keys(suppliers).forEach(key => suppliers[key] = $event.detail.expand)">
                            @foreach($groupedPayments as $supplierName => $group)
                                @php $supplierKey = Str::slug($supplierName); @endphp
                                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-4"
                                     x-init="suppliers['{{ $supplierKey }}'] = false">
                                    <div class="p-4">
                                        <!-- Supplier Header -->
                                        <div class="flex justify-between items-center cursor-pointer"
                                             @click="suppliers['{{ $supplierKey }}'] = !suppliers['{{ $supplierKey }}']">
                                            <div class="flex items-center space-x-3">
                                                <svg class="w-5 h-5 text-gray-500 transition-transform duration-200"
                                                     :class="suppliers['{{ $supplierKey }}'] ? 'rotate-90' : ''"
                                                     fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                                                </svg>
                                                <h3 class="text-lg font-medium text-gray-900">{{ $supplierName }}</h3>
                                            </div>
                                            <div class="flex items-center space-x-4">
                                                <span class="text-sm text-gray-600">{{ $group['count'] }} payment{{ $group['count'] !== 1 ? 's' : '' }}</span>
                                                <span class="text-lg font-bold text-gray-900">&euro;{{ number_format($group['total'], 2) }}</span>
                                            </div>
                                        </div>

                                        <!-- Payments Table (Collapsible) -->
                                        <div x-show="suppliers['{{ $supplierKey }}']"
                                             x-transition:enter="transition ease-out duration-200"
                                             x-transition:enter-start="opacity-0 transform scale-95"
                                             x-transition:enter-end="opacity-100 transform scale-100"
                                             class="mt-4">
                                            <div class="overflow-x-auto">
                                                <table class="min-w-full divide-y divide-gray-200">
                                                    <thead class="bg-gray-50">
                                                        <tr>
                                                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Payment Date</th>
                                                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Amount</th>
                                                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Method</th>
                                                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Invoice #</th>
                                                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Invoice Date</th>
                                                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Reference</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody class="bg-white divide-y divide-gray-200">
                                                        @foreach($group['payments'] as $payment)
                                                            <tr class="hover:bg-gray-50">
                                                                <td class="px-4 py-2 whitespace-nowrap text-sm text-gray-900">
                                                                    {{ $payment['date']->format('d/m/Y') }}
                                                                </td>
                                                                <td class="px-4 py-2 whitespace-nowrap text-sm font-medium text-gray-900">
                                                                    &euro;{{ number_format($payment['amount'], 2) }}
                                                                </td>
                                                                <td class="px-4 py-2 whitespace-nowrap">
                                                                    @php
                                                                        $methodColors = [
                                                                            'Bank Transfer' => 'bg-purple-100 text-purple-800',
                                                                            'BACS' => 'bg-purple-100 text-purple-800',
                                                                            'Cash' => 'bg-orange-100 text-orange-800',
                                                                            'Cheque' => 'bg-blue-100 text-blue-800',
                                                                            'Card' => 'bg-green-100 text-green-800',
                                                                            'Credit Card' => 'bg-green-100 text-green-800',
                                                                        ];
                                                                        $colorClass = $methodColors[$payment['type']] ?? 'bg-gray-100 text-gray-800';
                                                                    @endphp
                                                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $colorClass }}">
                                                                        {{ $payment['type'] }}
                                                                    </span>
                                                                </td>
                                                                <td class="px-4 py-2 whitespace-nowrap text-sm text-gray-700">
                                                                    {{ $payment['invoice_number'] ?: '-' }}
                                                                </td>
                                                                <td class="px-4 py-2 whitespace-nowrap text-sm text-gray-700">
                                                                    {{ $payment['invoice_date'] ? $payment['invoice_date']->format('d/m/Y') : '-' }}
                                                                </td>
                                                                <td class="px-4 py-2 text-sm text-gray-700 max-w-xs truncate" title="{{ $payment['reference'] }}">
                                                                    {{ Str::limit($payment['reference'], 30) }}
                                                                </td>
                                                            </tr>
                                                        @endforeach
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <!-- Flat Table View -->
                        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                            <div class="p-6">
                                <div class="overflow-x-auto">
                                    <table class="min-w-full divide-y divide-gray-200">
                                        <thead class="bg-gray-50">
                                            <tr>
                                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                    Payment Date
                                                </th>
                                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                    Supplier
                                                </th>
                                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                    Amount
                                                </th>
                                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                    Payment Method
                                                </th>
                                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                    Invoice #
                                                </th>
                                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                    Invoice Date
                                                </th>
                                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                    Reference
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody class="bg-white divide-y divide-gray-200">
                                            @foreach($payments as $payment)
                                                <tr class="hover:bg-gray-50">
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                        {{ $payment['date']->format('d/m/Y') }}
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                                        {{ $payment['supplier_name'] }}
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                                        &euro;{{ number_format($payment['amount'], 2) }}
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap">
                                                        @php
                                                            $methodColors = [
                                                                'Bank Transfer' => 'bg-purple-100 text-purple-800',
                                                                'BACS' => 'bg-purple-100 text-purple-800',
                                                                'Cash' => 'bg-orange-100 text-orange-800',
                                                                'Cheque' => 'bg-blue-100 text-blue-800',
                                                                'Card' => 'bg-green-100 text-green-800',
                                                                'Credit Card' => 'bg-green-100 text-green-800',
                                                            ];
                                                            $colorClass = $methodColors[$payment['type']] ?? 'bg-gray-100 text-gray-800';
                                                        @endphp
                                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $colorClass }}">
                                                            {{ $payment['type'] }}
                                                        </span>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">
                                                        {{ $payment['invoice_number'] ?: '-' }}
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">
                                                        {{ $payment['invoice_date'] ? $payment['invoice_date']->format('d/m/Y') : '-' }}
                                                    </td>
                                                    <td class="px-6 py-4 text-sm text-gray-700 max-w-xs truncate" title="{{ $payment['reference'] }}">
                                                        {{ Str::limit($payment['reference'], 40) }}
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    @endif

                    <!-- Overall Total -->
                    <div class="bg-gray-900 text-white overflow-hidden shadow-sm sm:rounded-lg mt-6">
                        <div class="p-6">
                            <div class="flex justify-between items-center">
                                <div>
                                    <h3 class="text-lg font-medium">Total Payments</h3>
                                    <p class="text-sm text-gray-300">
                                        {{ $paymentCount }} payments across {{ $methodTotals->count() }} payment method(s)
                                    </p>
                                </div>
                                <div class="text-3xl font-bold">
                                    &euro;{{ number_format($totalPayments, 2) }}
                                </div>
                            </div>
                        </div>
                    </div>
                @else
                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <div class="p-6 text-center">
                            <div class="text-gray-500">
                                <i class="fas fa-search text-4xl mb-4"></i>
                                <h3 class="text-lg font-medium mb-2">No Payments Found</h3>
                                <p>No supplier payments were recorded between {{ Carbon\Carbon::parse($startDate)->format('F j, Y') }} and {{ Carbon\Carbon::parse($endDate)->format('F j, Y') }}.</p>
                            </div>
                        </div>
                    </div>
                @endif
            @endif
        </div>
    </div>
</x-admin-layout>
