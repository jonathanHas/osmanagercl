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

            <!-- VAT Summary -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-6">
                <div class="p-6 bg-white border-b border-gray-200">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">VAT Summary</h3>
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
</x-admin-layout>