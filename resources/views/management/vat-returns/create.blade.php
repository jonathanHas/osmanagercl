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
                            <label for="end_date" class="block text-sm font-medium text-gray-700">Select End Date</label>
                            <input type="date" 
                                   name="end_date" 
                                   id="end_date" 
                                   value="{{ $endDate ? $endDate->format('Y-m-d') : '' }}"
                                   class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                        </div>
                        <button type="submit" 
                                class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                            Filter Invoices
                        </button>
                    </form>
                </div>
            </div>

            @if($invoices->count() > 0)
                <!-- Summary Totals -->
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
                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">0%</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right">€{{ number_format($grandTotals['zero_net'], 2) }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right">-</td>
                                    </tr>
                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">9%</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right">€{{ number_format($grandTotals['second_reduced_net'], 2) }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right">€{{ number_format($grandTotals['second_reduced_vat'], 2) }}</td>
                                    </tr>
                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">13.5%</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right">€{{ number_format($grandTotals['reduced_net'], 2) }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right">€{{ number_format($grandTotals['reduced_vat'], 2) }}</td>
                                    </tr>
                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">23%</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right">€{{ number_format($grandTotals['standard_net'], 2) }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right">€{{ number_format($grandTotals['standard_vat'], 2) }}</td>
                                    </tr>
                                    <tr class="bg-gray-50 font-bold">
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">TOTAL</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right">€{{ number_format($grandTotals['total_net'], 2) }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right">€{{ number_format($grandTotals['total_vat'], 2) }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <div class="mt-4 text-right">
                            <span class="text-lg font-bold">Total Amount: €{{ number_format($grandTotals['total_gross'], 2) }}</span>
                        </div>
                    </div>
                </div>

                <!-- Assignment Form -->
                <form method="POST" action="{{ route('management.vat-returns.store') }}" id="assignForm">
                    @csrf
                    
                    <!-- Form Fields -->
                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-6">
                        <div class="p-6 bg-white border-b border-gray-200">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <label for="return_period" class="block text-sm font-medium text-gray-700">Return Period</label>
                                    <input type="text" 
                                           name="return_period" 
                                           id="return_period" 
                                           value="{{ $endDate ? $endDate->format('Y-m') : date('Y-m') }}"
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
                                           value="{{ $endDate ? $endDate->format('Y-m-d') : '' }}"
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
                                <h3 class="text-lg font-medium text-gray-900 mb-4">{{ $supplierName }}</h3>
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
                                </div>
                                <button type="submit" 
                                        class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                                    Assign to VAT Return
                                </button>
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
                            <h3 class="mt-2 text-sm font-medium text-gray-900">No unassigned invoices</h3>
                            <p class="mt-1 text-sm text-gray-500">All invoices have been assigned to VAT returns or select a different date range.</p>
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </div>

    @push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Select all button
            document.getElementById('selectAllBtn')?.addEventListener('click', function() {
                document.querySelectorAll('.invoice-checkbox').forEach(checkbox => {
                    checkbox.checked = true;
                });
                document.querySelectorAll('.supplier-checkbox').forEach(checkbox => {
                    checkbox.checked = true;
                });
            });

            // Deselect all button
            document.getElementById('deselectAllBtn')?.addEventListener('click', function() {
                document.querySelectorAll('.invoice-checkbox').forEach(checkbox => {
                    checkbox.checked = false;
                });
                document.querySelectorAll('.supplier-checkbox').forEach(checkbox => {
                    checkbox.checked = false;
                });
            });

            // Supplier checkbox toggle
            document.querySelectorAll('.supplier-checkbox').forEach(checkbox => {
                checkbox.addEventListener('change', function() {
                    const supplier = this.dataset.supplier;
                    const isChecked = this.checked;
                    document.querySelectorAll(`.invoice-checkbox[data-supplier="${supplier}"]`).forEach(invoiceCheckbox => {
                        invoiceCheckbox.checked = isChecked;
                    });
                });
            });

            // Form validation
            document.getElementById('assignForm')?.addEventListener('submit', function(e) {
                const checkedBoxes = document.querySelectorAll('.invoice-checkbox:checked');
                if (checkedBoxes.length === 0) {
                    e.preventDefault();
                    alert('Please select at least one invoice to assign.');
                }
            });
        });
    </script>
    @endpush
</x-admin-layout>