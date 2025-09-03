<x-admin-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Outstanding Invoices Report
            </h2>
            @if(isset($supplierGroups))
                <a href="{{ route('suppliers.outstanding-report.export', ['report_date' => $reportDate]) }}" 
                   class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm font-medium">
                    <i class="fas fa-download mr-2"></i>Export CSV
                </a>
            @endif
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <!-- Date Selection Form -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-6">
                <div class="p-6">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Select Report Date</h3>
                    <form method="GET" action="{{ route('suppliers.outstanding-report') }}" class="flex items-center space-x-4">
                        <div class="flex-1 max-w-xs">
                            <label for="report_date" class="block text-sm font-medium text-gray-700 mb-1">
                                Report Date
                            </label>
                            <input type="date" 
                                   id="report_date" 
                                   name="report_date" 
                                   value="{{ $reportDate }}"
                                   max="{{ now()->format('Y-m-d') }}"
                                   class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                        </div>
                        <div class="pt-6">
                            <button type="submit" 
                                    class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-md text-sm font-medium">
                                Generate Report
                            </button>
                        </div>
                    </form>
                    <p class="mt-2 text-sm text-gray-600">
                        This report shows all invoices that were still outstanding on the selected date.
                    </p>
                </div>
            </div>

            @if(isset($supplierGroups))
                <!-- Report Summary -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-6">
                    <div class="p-6">
                        <h3 class="text-lg font-medium text-gray-900 mb-4">
                            Outstanding Invoices as of {{ Carbon\Carbon::parse($reportDate)->format('F j, Y') }}
                        </h3>
                        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                            <div class="bg-blue-50 p-4 rounded-lg">
                                <div class="text-2xl font-bold text-blue-600">{{ $supplierGroups->count() }}</div>
                                <div class="text-sm text-gray-600">Suppliers with Outstanding</div>
                            </div>
                            <div class="bg-green-50 p-4 rounded-lg">
                                <div class="text-2xl font-bold text-green-600">{{ $totalInvoiceCount }}</div>
                                <div class="text-sm text-gray-600">Total Outstanding Invoices</div>
                            </div>
                            <div class="bg-red-50 p-4 rounded-lg">
                                <div class="text-2xl font-bold text-red-600">€{{ number_format($overallTotal, 2) }}</div>
                                <div class="text-sm text-gray-600">Total Outstanding Amount</div>
                            </div>
                            <div class="bg-yellow-50 p-4 rounded-lg">
                                <div class="text-2xl font-bold text-yellow-600">{{ $unpaidInvoices->count() }}</div>
                                <div class="text-sm text-gray-600">Invoices Still Unpaid</div>
                            </div>
                        </div>
                    </div>
                </div>

                @if($supplierGroups->count() > 0)
                    <!-- Expand/Collapse All Controls -->
                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-4" x-data="{ expandAll: false }">
                        <div class="p-4">
                            <div class="flex justify-between items-center">
                                <h3 class="text-md font-medium text-gray-900">Supplier Details</h3>
                                <button @click="expandAll = !expandAll; $dispatch('toggle-all', { expand: expandAll })" 
                                        class="text-blue-600 hover:text-blue-800 text-sm font-medium">
                                    <span x-text="expandAll ? 'Collapse All' : 'Expand All'"></span>
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Supplier Groups -->
                    <div x-data="{ suppliers: {} }" @toggle-all.window="Object.keys(suppliers).forEach(key => suppliers[key] = $event.detail.expand)">
                        @foreach($supplierGroups as $supplierIndex => $supplierGroup)
                        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-6" 
                             x-init="suppliers['{{ $supplierIndex }}'] = false">
                            <div class="p-6">
                                <!-- Supplier Header - Always Visible -->
                                <div class="flex justify-between items-center cursor-pointer" 
                                     @click="suppliers['{{ $supplierIndex }}'] = !suppliers['{{ $supplierIndex }}']">
                                    <div class="flex items-center space-x-3">
                                        <!-- Expand/Collapse Icon -->
                                        <svg class="w-5 h-5 text-gray-500 transition-transform duration-200" 
                                             :class="suppliers['{{ $supplierIndex }}'] ? 'rotate-90' : ''"
                                             fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                                        </svg>
                                        <h3 class="text-lg font-medium text-gray-900">
                                            {{ $supplierGroup['supplier_name'] ?: 'Unknown Supplier' }}
                                        </h3>
                                    </div>
                                    <div class="text-right">
                                        <div class="text-lg font-bold text-gray-900">€{{ number_format($supplierGroup['total_amount'], 2) }}</div>
                                        <div class="text-sm text-gray-600">{{ $supplierGroup['invoice_count'] }} invoices</div>
                                    </div>
                                </div>

                                <!-- Invoice Details Table - Collapsible -->
                                <div x-show="suppliers['{{ $supplierIndex }}']" 
                                     x-transition:enter="transition ease-out duration-200"
                                     x-transition:enter-start="opacity-0 transform scale-95"
                                     x-transition:enter-end="opacity-100 transform scale-100"
                                     x-transition:leave="transition ease-in duration-150"
                                     x-transition:leave-start="opacity-100 transform scale-100"
                                     x-transition:leave-end="opacity-0 transform scale-95"
                                     class="mt-6">
                                    <div class="overflow-x-auto">
                                    <table class="min-w-full divide-y divide-gray-200">
                                        <thead class="bg-gray-50">
                                            <tr>
                                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                    Invoice Number
                                                </th>
                                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                    Invoice Date
                                                </th>
                                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                    Amount
                                                </th>
                                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                    Payment Status
                                                </th>
                                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                    Actions
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody class="bg-white divide-y divide-gray-200">
                                            @foreach($supplierGroup['invoices'] as $invoice)
                                                <tr class="hover:bg-gray-50">
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                                        {{ $invoice->invoice_number }}
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">
                                                        {{ $invoice->invoice_date->format('Y-m-d') }}
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                                        €{{ number_format($invoice->total_amount, 2) }}
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap">
                                                        @php
                                                            $statusColors = [
                                                                'pending' => 'bg-yellow-100 text-yellow-800',
                                                                'overdue' => 'bg-red-100 text-red-800',
                                                                'paid' => 'bg-green-100 text-green-800',
                                                                'partial' => 'bg-orange-100 text-orange-800',
                                                            ];
                                                            $statusColor = $statusColors[$invoice->payment_status] ?? 'bg-gray-100 text-gray-800';
                                                        @endphp
                                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $statusColor }}">
                                                            {{ ucfirst($invoice->payment_status) }}
                                                        </span>
                                                        @if($invoice->payment_status === 'paid' && $invoice->payment_date)
                                                            <div class="text-xs text-gray-600 mt-1">
                                                                Paid: {{ $invoice->payment_date->format('Y-m-d') }}
                                                            </div>
                                                        @endif
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                                        <a href="{{ route('invoices.show', $invoice) }}" 
                                                           class="text-blue-600 hover:text-blue-900">
                                                            View Invoice
                                                        </a>
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                        <tfoot class="bg-gray-50">
                                            <tr>
                                                <td colspan="2" class="px-6 py-3 text-right text-sm font-medium text-gray-900">
                                                    Supplier Total:
                                                </td>
                                                <td class="px-6 py-3 text-sm font-bold text-gray-900">
                                                    €{{ number_format($supplierGroup['total_amount'], 2) }}
                                                </td>
                                                <td colspan="2"></td>
                                            </tr>
                                        </tfoot>
                                    </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                        @endforeach
                    </div>

                    <!-- Overall Total -->
                    <div class="bg-gray-900 text-white overflow-hidden shadow-sm sm:rounded-lg">
                        <div class="p-6">
                            <div class="flex justify-between items-center">
                                <div>
                                    <h3 class="text-lg font-medium">Overall Total</h3>
                                    <p class="text-sm text-gray-300">
                                        {{ $supplierGroups->count() }} suppliers, {{ $totalInvoiceCount }} invoices
                                    </p>
                                </div>
                                <div class="text-3xl font-bold">
                                    €{{ number_format($overallTotal, 2) }}
                                </div>
                            </div>
                        </div>
                    </div>

                    @if($unpaidInvoices->count() > 0)
                        <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4 mt-6">
                            <div class="flex">
                                <div class="flex-shrink-0">
                                    <svg class="h-5 w-5 text-yellow-400" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                                    </svg>
                                </div>
                                <div class="ml-3">
                                    <h3 class="text-sm font-medium text-yellow-800">
                                        Currently Unpaid Invoices
                                    </h3>
                                    <div class="mt-2 text-sm text-yellow-700">
                                        <p>{{ $unpaidInvoices->count() }} invoices are still marked as unpaid ({{ $unpaidInvoices->where('payment_status', 'pending')->count() }} pending, {{ $unpaidInvoices->where('payment_status', 'overdue')->count() }} overdue, {{ $unpaidInvoices->where('payment_status', 'partial')->count() }} partial). These were outstanding on the report date and remain unpaid.</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endif

                @else
                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <div class="p-6 text-center">
                            <div class="text-gray-500">
                                <i class="fas fa-check-circle text-4xl mb-4"></i>
                                <h3 class="text-lg font-medium mb-2">No Outstanding Invoices</h3>
                                <p>There were no outstanding invoices as of {{ Carbon\Carbon::parse($reportDate)->format('F j, Y') }}.</p>
                            </div>
                        </div>
                    </div>
                @endif
            @endif
        </div>
    </div>
</x-admin-layout>