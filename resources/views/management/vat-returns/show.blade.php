<x-admin-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                VAT Return: {{ $vatReturn->return_period }}
            </h2>
            <div class="flex space-x-2">
                <a href="{{ route('management.vat-returns.export', $vatReturn) }}" 
                   class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                    <svg class="-ml-1 mr-2 h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                    </svg>
                    Export CSV
                </a>
                @if($vatReturn->canBeModified())
                    <form action="{{ route('management.vat-returns.finalize', $vatReturn) }}" method="POST" class="inline">
                        @csrf
                        @method('PATCH')
                        <button type="submit" 
                                onclick="return confirm('Are you sure you want to finalize this VAT return? This action cannot be undone.')"
                                class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500">
                            Finalize Return
                        </button>
                    </form>
                @endif
                <a href="{{ route('management.vat-returns.index') }}" 
                   class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                    Back to List
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            @if(session('success'))
                <div class="mb-4 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative" role="alert">
                    <span class="block sm:inline">{{ session('success') }}</span>
                </div>
            @endif

            @if(session('error'))
                <div class="mb-4 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative" role="alert">
                    <span class="block sm:inline">{{ session('error') }}</span>
                </div>
            @endif

            <!-- Return Details -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-6">
                <div class="p-6 bg-white border-b border-gray-200">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Return Details</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <p class="text-sm font-medium text-gray-500">Period</p>
                            <p class="mt-1 text-sm text-gray-900">{{ $vatReturn->return_period }}</p>
                        </div>
                        <div>
                            <p class="text-sm font-medium text-gray-500">Date Range</p>
                            <p class="mt-1 text-sm text-gray-900">
                                {{ $vatReturn->period_start->format('d M Y') }} - {{ $vatReturn->period_end->format('d M Y') }}
                            </p>
                        </div>
                        <div>
                            <p class="text-sm font-medium text-gray-500">Status</p>
                            <p class="mt-1">
                                @if($vatReturn->status === 'draft')
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-yellow-100 text-yellow-800">
                                        Draft
                                    </span>
                                @elseif($vatReturn->status === 'finalized')
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-blue-100 text-blue-800">
                                        Finalized
                                    </span>
                                @elseif($vatReturn->status === 'submitted')
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                                        Submitted
                                    </span>
                                @endif
                            </p>
                        </div>
                        <div>
                            <p class="text-sm font-medium text-gray-500">Created By</p>
                            <p class="mt-1 text-sm text-gray-900">
                                {{ $vatReturn->creator->name ?? 'System' }} on {{ $vatReturn->created_at->format('d M Y H:i') }}
                            </p>
                        </div>
                        @if($vatReturn->finalized_by)
                            <div>
                                <p class="text-sm font-medium text-gray-500">Finalized By</p>
                                <p class="mt-1 text-sm text-gray-900">
                                    {{ $vatReturn->finalizer->name ?? 'System' }}
                                </p>
                            </div>
                        @endif
                        @if($vatReturn->notes)
                            <div class="md:col-span-2">
                                <p class="text-sm font-medium text-gray-500">Notes</p>
                                <p class="mt-1 text-sm text-gray-900">{{ $vatReturn->notes }}</p>
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            <!-- ROS VAT Return Summary -->
            @if($rosFields)
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
                                @if($euInvoices->count() > 0)
                                <div class="mt-2 text-xs text-gray-500">
                                    EU Suppliers:
                                    @foreach($euInvoices->groupBy('supplier_name') as $supplierName => $supplierEuInvoices)
                                        {{ $supplierName }} (€{{ number_format($supplierEuInvoices->sum('subtotal'), 2) }})@if(!$loop->last), @endif
                                    @endforeach
                                </div>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            @endif

            <!-- Sales VAT Breakdown -->
            @if($salesData && isset($salesData['by_rate']))
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-6">
                <div class="p-6 bg-white border-b border-gray-200">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">
                        Sales VAT Breakdown
                        @if(($salesData['data_source'] ?? '') === 'optimized')
                            <span class="text-xs bg-green-100 text-green-800 px-2 py-1 rounded ml-2">Optimized Data</span>
                        @elseif(($salesData['data_source'] ?? '') === 'real-time')
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
                                @foreach($salesData['by_rate'] as $rateData)
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                        {{ number_format(($rateData['vat_rate'] ?? 0) * 100, 1) }}%
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right">
                                        €{{ number_format($rateData['total_net'] ?? 0, 2) }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right">
                                        €{{ number_format($rateData['total_vat'] ?? 0, 2) }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right">
                                        €{{ number_format(($rateData['total_net'] ?? 0) + ($rateData['total_vat'] ?? 0), 2) }}
                                    </td>
                                </tr>
                                @endforeach
                                <tr class="bg-gray-50 font-bold">
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">TOTAL</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right">
                                        €{{ number_format($salesData['total_net'] ?? 0, 2) }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right">
                                        €{{ number_format($salesData['total_vat'] ?? 0, 2) }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right">
                                        €{{ number_format($salesData['total_gross'] ?? 0, 2) }}
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
                                @foreach($vatBreakdown as $rate => $amounts)
                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">{{ $rate }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right">€{{ number_format($amounts['net'], 2) }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right">€{{ number_format($amounts['vat'], 2) }}</td>
                                    </tr>
                                @endforeach
                                <tr class="bg-gray-50 font-bold">
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">TOTAL</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right">€{{ number_format($vatReturn->total_net, 2) }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right">€{{ number_format($vatReturn->total_vat, 2) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="mt-4 text-right">
                        <span class="text-lg font-bold">Total Amount: €{{ number_format($vatReturn->total_gross, 2) }}</span>
                    </div>
                </div>
            </div>

            <!-- Goods from Other EU Countries -->
            @if($euInvoices->count() > 0)
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-6">
                    <div class="p-6 bg-white border-b border-gray-200">
                        <h3 class="text-lg font-medium text-gray-900 mb-4">Goods from Other EU Countries (E2)</h3>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Supplier</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Country</th>
                                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Net Amount</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    @foreach($euInvoices->groupBy('supplier_name') as $supplierName => $supplierEuInvoices)
                                        <tr>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">{{ $supplierName }}</td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ $supplierEuInvoices->first()->supplier->country_code ?? 'EU' }}</td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right">€{{ number_format($supplierEuInvoices->sum('subtotal'), 2) }}</td>
                                        </tr>
                                    @endforeach
                                    <tr class="bg-gray-50 font-bold">
                                        <td colspan="2" class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">Total EU Goods</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right">€{{ number_format($euTotalAmount, 2) }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            @endif

            <!-- Invoices by Supplier -->
            @foreach($invoicesBySupplier as $supplierName => $supplierInvoices)
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-6">
                    <div class="p-6 bg-white border-b border-gray-200">
                        <h3 class="text-lg font-medium text-gray-900 mb-4">{{ $supplierName }}</h3>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Invoice</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">0%</th>
                                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">9%</th>
                                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">13.5%</th>
                                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">23%</th>
                                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Total</th>
                                        @if($vatReturn->canBeModified())
                                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Action</th>
                                        @endif
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    @foreach($supplierInvoices as $invoice)
                                        <tr class="hover:bg-gray-50">
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
                                            @if($vatReturn->canBeModified())
                                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                                    <form action="{{ route('management.vat-returns.remove-invoice', [$vatReturn, $invoice]) }}" 
                                                          method="POST" 
                                                          class="inline"
                                                          onsubmit="return confirm('Remove this invoice from the VAT return?');">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit" class="text-red-600 hover:text-red-900">Remove</button>
                                                    </form>
                                                </td>
                                            @endif
                                        </tr>
                                    @endforeach
                                    <!-- Supplier Totals -->
                                    <tr class="bg-gray-100 font-medium">
                                        <td colspan="2" class="px-6 py-3 text-right text-sm">Subtotal:</td>
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
                                        @if($vatReturn->canBeModified())
                                            <td></td>
                                        @endif
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
        </div>
    </div>
    
    @if(session('download_csv'))
    @push('scripts')
    <script>
        // Automatically download CSV when VAT return is created
        document.addEventListener('DOMContentLoaded', function() {
            setTimeout(function() {
                window.location.href = '{{ route("management.vat-returns.export", $vatReturn) }}';
            }, 1000); // Wait 1 second for user to see success message
        });
    </script>
    @endpush
    @endif
</x-admin-layout>