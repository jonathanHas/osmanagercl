<x-admin-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Supplier Payments
            </h2>
            @if(isset($payments) && $payments->count() > 0)
                <a href="{{ route('suppliers.payments.export', ['start_date' => $startDate, 'end_date' => $endDate]) }}"
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
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Select Date Range</h3>
                    <form method="GET" action="{{ route('suppliers.payments') }}" class="space-y-4">
                        <div class="flex flex-wrap items-end gap-4">
                            <div class="flex-1 min-w-[150px] max-w-xs">
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
                            <div class="flex-1 min-w-[150px] max-w-xs">
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
                            <div>
                                <button type="submit"
                                        class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-md text-sm font-medium">
                                    View Payments
                                </button>
                            </div>
                        </div>
                    </form>
                    <p class="mt-2 text-sm text-gray-600">
                        View all supplier payments (bank and cash) within the selected date range.
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
                        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                            <div class="bg-blue-50 p-4 rounded-lg">
                                <div class="text-2xl font-bold text-blue-600">{{ number_format($paymentCount) }}</div>
                                <div class="text-sm text-gray-600">Total Payments</div>
                            </div>
                            <div class="bg-green-50 p-4 rounded-lg">
                                <div class="text-2xl font-bold text-green-600">&euro;{{ number_format($totalPayments, 2) }}</div>
                                <div class="text-sm text-gray-600">Total Amount</div>
                            </div>
                            <div class="bg-purple-50 p-4 rounded-lg">
                                <div class="text-2xl font-bold text-purple-600">&euro;{{ number_format($bankTotal, 2) }}</div>
                                <div class="text-sm text-gray-600">Bank Payments</div>
                            </div>
                            <div class="bg-orange-50 p-4 rounded-lg">
                                <div class="text-2xl font-bold text-orange-600">&euro;{{ number_format($cashTotal, 2) }}</div>
                                <div class="text-sm text-gray-600">Cash Payments</div>
                            </div>
                        </div>
                    </div>
                </div>

                @if($payments->count() > 0)
                    <!-- Payments Table -->
                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <div class="p-6">
                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                Date
                                            </th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                Supplier
                                            </th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                Amount
                                            </th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                Type
                                            </th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                Reference
                                            </th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                Invoice
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-200">
                                        @foreach($payments as $payment)
                                            <tr class="hover:bg-gray-50">
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                    {{ $payment['date']->format('Y-m-d') }}
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                                    {{ $payment['supplier_name'] }}
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                                    &euro;{{ number_format($payment['amount'], 2) }}
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    @if($payment['type'] === 'Bank')
                                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-purple-100 text-purple-800">
                                                            <i class="fas fa-university mr-1"></i> Bank
                                                        </span>
                                                    @else
                                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-orange-100 text-orange-800">
                                                            <i class="fas fa-money-bill-wave mr-1"></i> Cash
                                                        </span>
                                                    @endif
                                                    @if($payment['allocation_type'])
                                                        <div class="text-xs text-gray-500 mt-1">{{ $payment['allocation_type'] }}</div>
                                                    @endif
                                                </td>
                                                <td class="px-6 py-4 text-sm text-gray-700 max-w-xs truncate" title="{{ $payment['reference'] }}">
                                                    {{ Str::limit($payment['reference'], 40) }}
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">
                                                    @if($payment['invoice_number'])
                                                        {{ $payment['invoice_number'] }}
                                                    @else
                                                        <span class="text-gray-400">-</span>
                                                    @endif
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- Overall Total -->
                    <div class="bg-gray-900 text-white overflow-hidden shadow-sm sm:rounded-lg mt-6">
                        <div class="p-6">
                            <div class="flex justify-between items-center">
                                <div>
                                    <h3 class="text-lg font-medium">Total Payments</h3>
                                    <p class="text-sm text-gray-300">
                                        {{ $paymentCount }} payments ({{ $payments->where('type', 'Bank')->count() }} bank, {{ $payments->where('type', 'Cash')->count() }} cash)
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
