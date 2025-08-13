<x-admin-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('Create VAT Return') }}
            </h2>
            <a href="{{ route('management.vat-returns.index') }}" 
               class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                Back to List
            </a>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <!-- Date Filter -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-6">
                <div class="p-6 bg-white border-b border-gray-200">
                    <form method="GET" action="{{ route('management.vat-returns.create') }}" class="flex items-end space-x-4">
                        <div>
                            <label for="end_date" class="block text-sm font-medium text-gray-700">Select Date in VAT Period</label>
                            <input type="date" 
                                   name="end_date" 
                                   id="end_date" 
                                   value="{{ $endDate ? $endDate->format('Y-m-d') : '' }}"
                                   class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                            <p class="mt-1 text-xs text-gray-500">VAT periods: Jan-Feb, Mar-Apr, May-Jun, Jul-Aug, Sep-Oct, Nov-Dec</p>
                        </div>
                        <button type="submit" 
                                class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                            Load VAT Data
                        </button>
                    </form>
                    @if(isset($periodEnd) && $periodEnd)
                    <div class="mt-2 text-sm text-gray-600">
                        VAT Period: <strong>{{ $startDate->format('M') }}-{{ $periodEnd->format('M Y') }}</strong>
                        <span class="ml-2">({{ $startDate->format('M j') }} to {{ $periodEnd->format('M j, Y') }})</span>
                        <span class="text-xs text-gray-500 ml-2">Due by {{ $periodEnd->copy()->addDays(19)->format('M j, Y') }}</span>
                    </div>
                    @endif
                </div>
            </div>

            @if($invoices->count() > 0 || isset($salesData))
                <!-- ROS VAT Return Summary -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-6">
                    <div class="p-6 bg-white border-b border-gray-200">
                        <h3 class="text-lg font-medium text-gray-900 mb-4">VAT Return Summary (ROS Format)</h3>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <!-- Left Column - VAT Calculations -->
                            <div>
                                <h4 class="text-sm font-semibold text-gray-700 mb-3">VAT Calculations</h4>
                                <div class="space-y-3">
                                    <div class="flex justify-between items-center py-2 border-b">
                                        <span class="text-sm font-medium text-gray-600">T1 - VAT on Sales</span>
                                        <span class="text-sm font-bold text-gray-900">€{{ number_format($rosFields['T1'], 2) }}</span>
                                    </div>
                                    <div class="flex justify-between items-center py-2 border-b">
                                        <span class="text-sm font-medium text-gray-600">T2 - VAT on Purchases</span>
                                        <span class="text-sm font-bold text-gray-900">€{{ number_format($rosFields['T2'], 2) }}</span>
                                    </div>
                                    @if($rosFields['T3'] > 0)
                                    <div class="flex justify-between items-center py-2 bg-red-50 px-2 rounded">
                                        <span class="text-sm font-medium text-red-700">T3 - Net Payable</span>
                                        <span class="text-sm font-bold text-red-700">€{{ number_format($rosFields['T3'], 2) }}</span>
                                    </div>
                                    @else
                                    <div class="flex justify-between items-center py-2 bg-green-50 px-2 rounded">
                                        <span class="text-sm font-medium text-green-700">T4 - Net Repayable</span>
                                        <span class="text-sm font-bold text-green-700">€{{ number_format($rosFields['T4'], 2) }}</span>
                                    </div>
                                    @endif
                                </div>
                            </div>
                            
                            <!-- Right Column - Intra-EU Trade -->
                            <div>
                                <h4 class="text-sm font-semibold text-gray-700 mb-3">Intra-EU Trade (INTRASTAT)</h4>
                                <div class="space-y-3">
                                    <div class="flex justify-between items-center py-2 border-b">
                                        <span class="text-sm font-medium text-gray-600">E1 - Goods to EU</span>
                                        <span class="text-sm font-bold text-gray-900">€{{ number_format($rosFields['E1'], 2) }}</span>
                                    </div>
                                    <div class="flex justify-between items-center py-2 border-b">
                                        <span class="text-sm font-medium text-gray-600">E2 - Goods from EU</span>
                                        <span class="text-sm font-bold text-gray-900">€{{ number_format($rosFields['E2'], 2) }}</span>
                                    </div>
                                    @if($euInvoices && $euInvoices->count() > 0)
                                    <div class="mt-2 text-xs text-gray-500">
                                        EU Suppliers: 
                                        @foreach($euInvoices->groupBy('supplier_name') as $supplierName => $supplierInvoices)
                                            {{ $supplierName }} (€{{ number_format($supplierInvoices->sum('subtotal'), 2) }})@if(!$loop->last), @endif
                                        @endforeach
                                    </div>
                                    @endif
                                </div>
                            </div>
                        </div>
                        
                        <!-- Alert for unusual expenditure -->
                        @if($rosFields['T2'] > 5000)
                        <div class="mt-4 p-3 bg-yellow-50 border border-yellow-200 rounded">
                            <p class="text-sm text-yellow-800">
                                <strong>Note:</strong> This return includes VAT on purchases over €5,000. 
                                Consider whether any exceptional business purchases should be documented 
                                (e.g., vehicles, equipment, ICT software, property improvements).
                            </p>
                        </div>
                        @endif
                    </div>
                </div>

                <!-- Sales VAT Breakdown -->
                @if(isset($salesData))
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-6">
                    <div class="p-6 bg-white border-b border-gray-200">
                        <h3 class="text-lg font-medium text-gray-900 mb-4">
                            Sales VAT Breakdown
                            @if($salesData['data_source'] === 'optimized')
                                <span class="text-xs bg-green-100 text-green-800 px-2 py-1 rounded ml-2">Optimized Data</span>
                            @else
                                <span class="text-xs bg-yellow-100 text-yellow-800 px-2 py-1 rounded ml-2">Real-time Data</span>
                            @endif
                        </h3>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">VAT Rate</th>
                                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Net Sales</th>
                                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">VAT Amount</th>
                                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Gross Sales</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    @foreach($salesData['by_rate'] as $rate => $data)
                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                            {{ number_format($rate * 100, 1) }}%
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right">
                                            €{{ number_format($data->total_net ?? 0, 2) }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right">
                                            €{{ number_format($data->total_vat ?? 0, 2) }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right">
                                            €{{ number_format(($data->total_net ?? 0) + ($data->total_vat ?? 0), 2) }}
                                        </td>
                                    </tr>
                                    @endforeach
                                    <tr class="bg-gray-50 font-bold">
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">TOTAL</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right">
                                            €{{ number_format($salesData['total_net'], 2) }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right">
                                            €{{ number_format($salesData['total_vat'], 2) }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right">
                                            €{{ number_format($salesData['total_gross'], 2) }}
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                @endif

                <!-- Purchase VAT Summary -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-6">
                    <div class="p-6 bg-white border-b border-gray-200">
                        <h3 class="text-lg font-medium text-gray-900 mb-4">Purchase VAT Summary</h3>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">VAT Rate</th>
                                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Net Amount</th>
                                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">VAT Amount</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">0%</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right">€{{ number_format($purchaseTotals['zero_net'], 2) }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right">-</td>
                                    </tr>
                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">9%</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right">€{{ number_format($purchaseTotals['second_reduced_net'], 2) }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right">€{{ number_format($purchaseTotals['second_reduced_vat'], 2) }}</td>
                                    </tr>
                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">13.5%</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right">€{{ number_format($purchaseTotals['reduced_net'], 2) }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right">€{{ number_format($purchaseTotals['reduced_vat'], 2) }}</td>
                                    </tr>
                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">23%</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right">€{{ number_format($purchaseTotals['standard_net'], 2) }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right">€{{ number_format($purchaseTotals['standard_vat'], 2) }}</td>
                                    </tr>
                                    <tr class="bg-gray-50 font-bold">
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">TOTAL</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right">€{{ number_format($purchaseTotals['total_net'], 2) }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right">€{{ number_format($purchaseTotals['total_vat'], 2) }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <div class="mt-4 text-right">
                            <span class="text-lg font-bold">Total Purchases: €{{ number_format($purchaseTotals['total_gross'], 2) }}</span>
                        </div>
                    </div>
                </div>

                <!-- Assignment Form -->
                <form method="POST" action="{{ route('management.vat-returns.store') }}" id="assignForm">
                    @csrf
                    
                    <!-- Form Fields -->
                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-6">
                        <div class="p-6 bg-white border-b border-gray-200">
                            <h3 class="text-lg font-medium text-gray-900 mb-4">VAT Return Details</h3>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <label for="return_period" class="block text-sm font-medium text-gray-700">Return Period</label>
                                    <input type="text" 
                                           name="return_period" 
                                           id="return_period" 
                                           value="{{ isset($periodEnd) && $periodEnd && isset($startDate) ? $startDate->format('M').'-'.$periodEnd->format('M Y') : '' }}"
                                           required
                                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                    @error('return_period')
                                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>
                                
                                <div>
                                    <label for="period_end" class="block text-sm font-medium text-gray-700">Period End Date</label>
                                    <input type="date" 
                                           name="period_end" 
                                           id="period_end" 
                                           value="{{ isset($periodEnd) && $periodEnd ? $periodEnd->format('Y-m-d') : '' }}"
                                           required
                                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                    @error('period_end')
                                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>
                            </div>
                            
                            <div class="mt-4">
                                <label for="notes" class="block text-sm font-medium text-gray-700">Notes</label>
                                <textarea name="notes" 
                                          id="notes" 
                                          rows="3"
                                          class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"></textarea>
                            </div>
                        </div>
                    </div>

                    <!-- Invoices by Supplier -->
                    @foreach($invoicesBySupplier as $supplierName => $supplierInvoices)
                        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-6">
                            <div class="p-6 bg-white border-b border-gray-200">
                                <h3 class="text-lg font-medium text-gray-900 mb-4">
                                    {{ $supplierName }}
                                    @php
                                        $supplierId = $supplierInvoices->first()->supplier_id;
                                        $isEuSupplier = $euSupplierIds->contains($supplierId);
                                    @endphp
                                    @if($isEuSupplier)
                                        <span class="text-xs bg-blue-100 text-blue-800 px-2 py-1 rounded ml-2">EU Supplier</span>
                                    @endif
                                </h3>
                                <div class="overflow-x-auto">
                                    <table class="min-w-full divide-y divide-gray-200">
                                        <thead class="bg-gray-50">
                                            <tr>
                                                <th class="px-3 py-3 text-left">
                                                    <input type="checkbox" 
                                                           class="supplier-checkbox rounded border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                                           data-supplier="{{ str_replace(' ', '_', $supplierName) }}">
                                                </th>
                                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Invoice</th>
                                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">0%</th>
                                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">9%</th>
                                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">13.5%</th>
                                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">23%</th>
                                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Total</th>
                                            </tr>
                                        </thead>
                                        <tbody class="bg-white divide-y divide-gray-200">
                                            @foreach($supplierInvoices as $invoice)
                                                <tr class="hover:bg-gray-50">
                                                    <td class="px-3 py-4">
                                                        <input type="checkbox" 
                                                               name="invoice_ids[]" 
                                                               value="{{ $invoice->id }}"
                                                               class="invoice-checkbox rounded border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                                               data-supplier="{{ str_replace(' ', '_', $supplierName) }}">
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                                        <a href="{{ route('invoices.show', $invoice) }}" 
                                                           target="_blank"
                                                           class="text-indigo-600 hover:text-indigo-900">
                                                            {{ $invoice->invoice_number }}
                                                        </a>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                        {{ $invoice->invoice_date->format('d-M-y') }}
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right">
                                                        @if($invoice->zero_net > 0)
                                                            {{ number_format($invoice->zero_net, 2) }}
                                                        @endif
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right">
                                                        @if($invoice->second_reduced_net > 0)
                                                            {{ number_format($invoice->second_reduced_net, 2) }}
                                                        @endif
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right">
                                                        @if($invoice->reduced_net > 0)
                                                            {{ number_format($invoice->reduced_net, 2) }}
                                                        @endif
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right">
                                                        @if($invoice->standard_net > 0)
                                                            {{ number_format($invoice->standard_net, 2) }}
                                                        @endif
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right font-medium">
                                                        €{{ number_format($invoice->total_amount, 2) }}
                                                    </td>
                                                </tr>
                                            @endforeach
                                            <!-- Supplier Totals -->
                                            <tr class="bg-gray-100 font-medium">
                                                <td colspan="3" class="px-6 py-3 text-right text-sm">Subtotal:</td>
                                                <td class="px-6 py-3 text-right text-sm">
                                                    @if($supplierTotals[$supplierName]['zero_net'] > 0)
                                                        {{ number_format($supplierTotals[$supplierName]['zero_net'], 2) }}
                                                    @endif
                                                </td>
                                                <td class="px-6 py-3 text-right text-sm">
                                                    @if($supplierTotals[$supplierName]['second_reduced_net'] > 0)
                                                        {{ number_format($supplierTotals[$supplierName]['second_reduced_net'], 2) }}
                                                    @endif
                                                </td>
                                                <td class="px-6 py-3 text-right text-sm">
                                                    @if($supplierTotals[$supplierName]['reduced_net'] > 0)
                                                        {{ number_format($supplierTotals[$supplierName]['reduced_net'], 2) }}
                                                    @endif
                                                </td>
                                                <td class="px-6 py-3 text-right text-sm">
                                                    @if($supplierTotals[$supplierName]['standard_net'] > 0)
                                                        {{ number_format($supplierTotals[$supplierName]['standard_net'], 2) }}
                                                    @endif
                                                </td>
                                                <td class="px-6 py-3 text-right text-sm">
                                                    €{{ number_format($supplierTotals[$supplierName]['total'], 2) }}
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                                <div class="mt-2 text-sm text-gray-600">
                                    VAT - 
                                    @if($supplierTotals[$supplierName]['second_reduced_vat'] > 0)
                                        9%: €{{ number_format($supplierTotals[$supplierName]['second_reduced_vat'], 2) }}
                                    @endif
                                    @if($supplierTotals[$supplierName]['reduced_vat'] > 0)
                                        13.5%: €{{ number_format($supplierTotals[$supplierName]['reduced_vat'], 2) }}
                                    @endif
                                    @if($supplierTotals[$supplierName]['standard_vat'] > 0)
                                        23%: €{{ number_format($supplierTotals[$supplierName]['standard_vat'], 2) }}
                                    @endif
                                </div>
                            </div>
                        </div>
                    @endforeach

                    <!-- Action Buttons -->
                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <div class="p-6 bg-white border-b border-gray-200">
                            <!-- Information Banner -->
                            <div class="mb-4 p-3 bg-blue-50 border border-blue-200 rounded-lg">
                                <div class="flex">
                                    <svg class="h-5 w-5 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                    </svg>
                                    <div class="ml-3">
                                        <h3 class="text-sm font-medium text-blue-800">Invoice Selection</h3>
                                        <p class="mt-1 text-sm text-blue-700">
                                            All period invoices are selected by default as they're included in the VAT calculations above. 
                                            You can deselect specific invoices if needed, but this will affect your VAT liability.
                                        </p>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="flex justify-between items-center">
                                <div>
                                    <button type="button" 
                                            id="selectAllBtn"
                                            class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                                        Select All
                                    </button>
                                    <button type="button" 
                                            id="deselectAllBtn"
                                            class="ml-2 inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                                        Deselect All
                                    </button>
                                    <span id="selectionCount" class="ml-4 text-sm text-gray-600"></span>
                                </div>
                                <div class="flex space-x-3">
                                    @if(isset($periodEnd) && $periodEnd)
                                    <button type="button" 
                                            id="downloadCsvBtn"
                                            class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                                        <svg class="h-4 w-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                        </svg>
                                        Download CSV Preview
                                    </button>
                                    @endif
                                    
                                    <button type="submit" 
                                            class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                                        Create VAT Return
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            @else
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 bg-white border-b border-gray-200">
                        <div class="text-center py-8">
                            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                            <h3 class="mt-2 text-sm font-medium text-gray-900">No data available</h3>
                            <p class="mt-1 text-sm text-gray-500">Please select a period end date to load VAT return data.</p>
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </div>

    @push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Auto-select all invoices when page loads with VAT data
            @if($invoices && $invoices->count() > 0)
            setTimeout(function() {
                selectAllInvoices();
                updateSelectionCount();
            }, 100);
            @endif
            
            function selectAllInvoices() {
                document.querySelectorAll('.invoice-checkbox').forEach(checkbox => {
                    checkbox.checked = true;
                });
                document.querySelectorAll('.supplier-checkbox').forEach(checkbox => {
                    checkbox.checked = true;
                });
            }
            
            function updateSelectionCount() {
                const checkedBoxes = document.querySelectorAll('.invoice-checkbox:checked');
                const totalBoxes = document.querySelectorAll('.invoice-checkbox');
                const countElement = document.getElementById('selectionCount');
                if (countElement) {
                    countElement.textContent = `${checkedBoxes.length} of ${totalBoxes.length} invoices selected`;
                }
            }

            // Select all button
            document.getElementById('selectAllBtn')?.addEventListener('click', function() {
                selectAllInvoices();
                updateSelectionCount();
            });

            // Deselect all button
            document.getElementById('deselectAllBtn')?.addEventListener('click', function() {
                document.querySelectorAll('.invoice-checkbox').forEach(checkbox => {
                    checkbox.checked = false;
                });
                document.querySelectorAll('.supplier-checkbox').forEach(checkbox => {
                    checkbox.checked = false;
                });
                updateSelectionCount();
            });

            // Supplier checkbox toggle
            document.querySelectorAll('.supplier-checkbox').forEach(checkbox => {
                checkbox.addEventListener('change', function() {
                    const supplier = this.dataset.supplier;
                    const isChecked = this.checked;
                    document.querySelectorAll(`.invoice-checkbox[data-supplier="${supplier}"]`).forEach(invoiceCheckbox => {
                        invoiceCheckbox.checked = isChecked;
                    });
                    updateSelectionCount();
                });
            });
            
            // Individual invoice checkbox change
            document.querySelectorAll('.invoice-checkbox').forEach(checkbox => {
                checkbox.addEventListener('change', function() {
                    updateSelectionCount();
                    
                    // Update supplier checkbox state based on individual invoices
                    const supplier = this.dataset.supplier;
                    const supplierInvoices = document.querySelectorAll(`.invoice-checkbox[data-supplier="${supplier}"]`);
                    const checkedSupplierInvoices = document.querySelectorAll(`.invoice-checkbox[data-supplier="${supplier}"]:checked`);
                    const supplierCheckbox = document.querySelector(`.supplier-checkbox[data-supplier="${supplier}"]`);
                    
                    if (supplierCheckbox) {
                        supplierCheckbox.checked = supplierInvoices.length === checkedSupplierInvoices.length;
                    }
                });
            });

            // Form validation
            document.getElementById('assignForm')?.addEventListener('submit', function(e) {
                const checkedBoxes = document.querySelectorAll('.invoice-checkbox:checked');
                if (checkedBoxes.length === 0) {
                    e.preventDefault();
                    alert('Please select at least one invoice to assign to the VAT return.');
                }
            });
            
            // CSV Download button
            document.getElementById('downloadCsvBtn')?.addEventListener('click', function() {
                const checkedBoxes = document.querySelectorAll('.invoice-checkbox:checked');
                if (checkedBoxes.length === 0) {
                    alert('Please select at least one invoice to include in the CSV export.');
                    return;
                }
                
                // Get selected invoice IDs
                const invoiceIds = Array.from(checkedBoxes).map(checkbox => checkbox.value);
                
                // Create form data
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = '{{ route("management.vat-returns.export-preview") }}';
                form.style.display = 'none';
                
                // Add CSRF token
                const csrfField = document.createElement('input');
                csrfField.type = 'hidden';
                csrfField.name = '_token';
                csrfField.value = '{{ csrf_token() }}';
                form.appendChild(csrfField);
                
                // Add period data
                const startDateField = document.createElement('input');
                startDateField.type = 'hidden';
                startDateField.name = 'start_date';
                startDateField.value = '{{ isset($startDate) ? $startDate->format("Y-m-d") : "" }}';
                form.appendChild(startDateField);
                
                const endDateField = document.createElement('input');
                endDateField.type = 'hidden';
                endDateField.name = 'end_date';
                endDateField.value = '{{ isset($periodEnd) ? $periodEnd->format("Y-m-d") : "" }}';
                form.appendChild(endDateField);
                
                const periodField = document.createElement('input');
                periodField.type = 'hidden';
                periodField.name = 'period';
                periodField.value = document.getElementById('return_period').value || '';
                form.appendChild(periodField);
                
                // Add selected invoice IDs
                invoiceIds.forEach(id => {
                    const idField = document.createElement('input');
                    idField.type = 'hidden';
                    idField.name = 'invoice_ids[]';
                    idField.value = id;
                    form.appendChild(idField);
                });
                
                document.body.appendChild(form);
                form.submit();
                document.body.removeChild(form);
            });
        });
    </script>
    @endpush
</x-admin-layout>