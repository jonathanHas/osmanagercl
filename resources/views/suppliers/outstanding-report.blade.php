<x-admin-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Outstanding Invoices Report
            </h2>
            @if(isset($supplierGroups))
                <a href="{{ route('suppliers.outstanding-report.export', ['report_date' => $reportDate, 'show_previous_payments' => $showPreviousPayments ? '1' : '0']) }}"
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
                    <form method="GET" action="{{ route('suppliers.outstanding-report') }}" class="space-y-4">
                        <div class="flex items-center space-x-4">
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
                        </div>

                        <div class="flex items-center">
                            <input type="checkbox"
                                   id="show_previous_payments"
                                   name="show_previous_payments"
                                   value="1"
                                   {{ $showPreviousPayments ? 'checked' : '' }}
                                   class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                            <label for="show_previous_payments" class="ml-2 block text-sm text-gray-700">
                                Show last 2 payments for each supplier
                                <span class="text-gray-500">(helps identify skipped invoices)</span>
                            </label>
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
                    <!-- Expand/Collapse All Controls and Bulk Actions -->
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

                    <!-- Bulk Actions Bar (Sticky) -->
                    <div id="bulk-actions-bar" class="hidden bg-blue-900 rounded-lg p-4 mb-4 sticky top-0 z-40 shadow-lg">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center space-x-4">
                                <span id="selection-count" class="text-blue-100 font-medium">0 invoices selected</span>
                                <span id="selection-total" class="text-blue-200 text-sm">Total: €0.00</span>
                            </div>
                            <div class="flex space-x-2">
                                <button id="mark-paid-btn" 
                                        class="bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-4 rounded">
                                    Mark as Paid
                                </button>
                                <button id="clear-selection-btn" 
                                        class="bg-gray-600 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded">
                                    Clear Selection
                                </button>
                            </div>
                        </div>
                        
                        <!-- Breakdown by supplier -->
                        <div id="supplier-breakdown" class="mt-3 hidden">
                            <div class="text-blue-200 text-sm font-medium mb-2">Selected by supplier:</div>
                            <div id="supplier-list" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-2">
                                <!-- Supplier breakdowns will be inserted here -->
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
                                <div class="flex justify-between items-center">
                                    <div class="flex items-center space-x-3">
                                        <!-- Select All for Supplier -->
                                        <input type="checkbox" 
                                               class="supplier-select-all rounded border-gray-300 text-blue-600 focus:ring-blue-500 focus:ring-2" 
                                               data-supplier-index="{{ $supplierIndex }}"
                                               autocomplete="off"
                                               title="Select all invoices for {{ $supplierGroup['supplier_name'] }}">
                                        <!-- Expand/Collapse Icon -->
                                        <div class="cursor-pointer" @click="suppliers['{{ $supplierIndex }}'] = !suppliers['{{ $supplierIndex }}']">
                                            <svg class="w-5 h-5 text-gray-500 transition-transform duration-200" 
                                                 :class="suppliers['{{ $supplierIndex }}'] ? 'rotate-90' : ''"
                                                 fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                                            </svg>
                                        </div>
                                        <h3 class="text-lg font-medium text-gray-900 cursor-pointer" 
                                            @click="suppliers['{{ $supplierIndex }}'] = !suppliers['{{ $supplierIndex }}']">
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
                                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-8">
                                                    <input type="checkbox" 
                                                           class="supplier-select-all-table rounded border-gray-300 text-blue-600 focus:ring-blue-500 focus:ring-2" 
                                                           data-supplier-index="{{ $supplierIndex }}"
                                                           autocomplete="off">
                                                </th>
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
                                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                    Notes
                                                </th>
                                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                    Actions
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody class="bg-white divide-y divide-gray-200">
                                            @foreach($supplierGroup['all_invoices'] as $invoice)
                                                @php
                                                    // Determine if this is an outstanding invoice
                                                    $isOutstanding = in_array($invoice->payment_status, ['pending', 'overdue', 'partial']) ||
                                                                    ($invoice->payment_status === 'paid' && $invoice->payment_date && $invoice->payment_date > Carbon\Carbon::parse($reportDate));

                                                    $statusColors = [
                                                        'pending' => 'bg-yellow-100 text-yellow-800',
                                                        'overdue' => 'bg-red-100 text-red-800',
                                                        'paid' => 'bg-green-100 text-green-800',
                                                        'partial' => 'bg-orange-100 text-orange-800',
                                                    ];
                                                    $statusColor = $statusColors[$invoice->payment_status] ?? 'bg-gray-100 text-gray-800';

                                                    // Use different row background for paid vs outstanding
                                                    $rowBgClass = $isOutstanding ? 'bg-white hover:bg-gray-50' : 'bg-green-50 hover:bg-green-100';
                                                @endphp
                                                <tr class="{{ $rowBgClass }}">
                                                    <td class="px-6 py-4 whitespace-nowrap">
                                                        @if($isOutstanding)
                                                            <input type="checkbox"
                                                                   class="invoice-checkbox rounded border-gray-300 text-blue-600 focus:ring-blue-500 focus:ring-2"
                                                                   data-invoice-id="{{ $invoice->id }}"
                                                                   data-supplier-index="{{ $supplierIndex }}"
                                                                   data-supplier-id="{{ $supplierGroup['supplier']->id ?? 'unknown' }}"
                                                                   data-supplier-name="{{ $supplierGroup['supplier_name'] }}"
                                                                   data-total-amount="{{ $invoice->total_amount }}"
                                                                   data-invoice-number="{{ $invoice->invoice_number }}"
                                                                   autocomplete="off">
                                                        @else
                                                            <span class="text-green-600" title="Already paid before report date">
                                                                <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                                                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                                                                </svg>
                                                            </span>
                                                        @endif
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium {{ $isOutstanding ? 'text-gray-900' : 'text-green-900' }}">
                                                        {{ $invoice->invoice_number }}
                                                        @if(!$isOutstanding)
                                                            <span class="ml-1 text-xs text-green-600 font-normal">(ref)</span>
                                                        @endif
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm {{ $isOutstanding ? 'text-gray-700' : 'text-green-700' }}">
                                                        {{ $invoice->invoice_date->format('Y-m-d') }}
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium {{ $isOutstanding ? 'text-gray-900' : 'text-green-800' }}">
                                                        €{{ number_format($invoice->total_amount, 2) }}
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap">
                                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $statusColor }}">
                                                            {{ ucfirst($invoice->payment_status) }}
                                                        </span>
                                                        @if($invoice->payment_status === 'paid' && $invoice->payment_date)
                                                            <div class="text-xs {{ $isOutstanding ? 'text-gray-600' : 'text-green-600' }} mt-1">
                                                                Paid: {{ $invoice->payment_date->format('Y-m-d') }}
                                                                @if($invoice->payment_method)
                                                                    <br>{{ ucfirst(str_replace('_', ' ', $invoice->payment_method)) }}
                                                                @endif
                                                            </div>
                                                        @endif
                                                    </td>
                                                    <td class="px-4 py-3" x-data="{ editing: false, notes: {{ json_encode($invoice->notes ?? '') }}, newNote: '' }">
                                                        <div x-show="!editing" @click="editing = true" class="cursor-pointer min-w-[60px] min-h-[20px]">
                                                            <span x-text="notes || '—'" class="text-xs text-gray-500 whitespace-pre-line"></span>
                                                        </div>
                                                        <div x-show="editing" x-cloak>
                                                            <div x-show="notes" class="text-xs text-gray-400 mb-1 whitespace-pre-line" x-text="notes"></div>
                                                            <textarea x-model="newNote" placeholder="Add note..." @click.away="if(newNote.trim()){fetch('/invoices/{{ $invoice->id }}/notes', { method: 'PATCH', headers: {'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content}, body: JSON.stringify({notes: newNote.trim()}) }).then(r => r.json()).then(d => { notes = d.notes; newNote = ''; editing = false; })} else { editing = false; }" @keydown.escape="newNote = ''; editing = false" rows="2" class="text-xs w-full rounded border-gray-300 focus:border-blue-500 focus:ring-blue-500"></textarea>
                                                        </div>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                                        @php
                                                            $viewAttachment = $invoice->attachments->firstWhere('is_primary', true) ?? $invoice->attachments->first();
                                                            $viewUrl = $viewAttachment
                                                                ? route('invoices.attachments.viewer-minimal', $viewAttachment)
                                                                : route('invoices.show', $invoice);
                                                        @endphp
                                                        <a href="{{ $viewUrl }}"
                                                           @if($viewAttachment) onclick="return openInvoicePopup(event, this.href)" @endif
                                                           class="{{ $isOutstanding ? 'text-blue-600 hover:text-blue-900' : 'text-green-700 hover:text-green-900' }}">
                                                            View Invoice
                                                        </a>
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                        <tfoot class="bg-gray-50">
                                            <tr>
                                                <td colspan="3" class="px-6 py-3 text-right text-sm font-medium text-gray-900">
                                                    Supplier Total:
                                                </td>
                                                <td class="px-6 py-3 text-sm font-bold text-gray-900">
                                                    €{{ number_format($supplierGroup['total_amount'], 2) }}
                                                </td>
                                                <td colspan="3"></td>
                                            </tr>
                                        </tfoot>
                                    </table>
                                    </div>

                                    @if($showPreviousPayments)
                                        <div class="mt-4 bg-blue-50 rounded-lg p-3 border border-blue-200">
                                            <p class="text-xs text-blue-800 flex items-center">
                                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                                </svg>
                                                <strong class="mr-1">Legend:</strong>
                                                <span class="bg-white px-2 py-0.5 rounded mr-2">White rows</span> = Outstanding invoices
                                                <span class="bg-green-50 px-2 py-0.5 rounded border border-green-200 ml-2">Green rows (ref)</span> = Previous payments for reference
                                            </p>
                                        </div>
                                    @endif
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

    {{-- Bulk Payment Modal --}}
    <div id="payment-modal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
        <div class="bg-white rounded-lg max-w-2xl w-full max-h-[90vh] overflow-y-auto">
            <div class="p-6">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-xl font-bold text-gray-900">Mark Outstanding Invoices as Paid</h3>
                    <button id="close-modal" class="text-gray-400 hover:text-gray-600">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>
                
                <form id="bulk-payment-form">
                    @csrf
                    
                    {{-- Selected Invoices Summary --}}
                    <div class="mb-6">
                        <h4 class="text-lg font-semibold text-gray-800 mb-3">Selected Invoices</h4>
                        <div id="modal-supplier-breakdown" class="space-y-3">
                            <!-- Supplier breakdown will be inserted here -->
                        </div>
                    </div>
                    
                    {{-- Payment Details --}}
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Payment Date</label>
                            <input type="date" name="payment_date" id="payment_date" 
                                   value="{{ now()->format('Y-m-d') }}"
                                   class="w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500"
                                   required>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Payment Method</label>
                            <select name="payment_method" id="payment_method" 
                                    class="w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                                <option value="bank_transfer">Bank Transfer</option>
                                <option value="cash">Cash</option>
                                <option value="cheque">Cheque</option>
                                <option value="credit_card">Credit Card</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="mb-6">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Payment Reference (Optional)</label>
                        <input type="text" name="payment_reference" id="payment_reference" 
                               placeholder="e.g., Transfer confirmation number, cheque number..."
                               class="w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                    </div>
                    
                    <details class="mb-6">
                        <summary class="text-sm font-medium text-gray-700 cursor-pointer hover:text-gray-900">Add Notes (Optional)</summary>
                        <textarea name="notes" id="payment_notes" rows="3"
                                  placeholder="Any additional notes about this payment..."
                                  class="mt-2 w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500"></textarea>
                    </details>

                    <div class="flex justify-end space-x-3">
                        <button type="button" id="cancel-payment"
                                class="bg-gray-600 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded">
                            Cancel
                        </button>
                        <button type="submit" 
                                class="bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-4 rounded">
                            Mark as Paid
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    @push('scripts')
    <script>
        function openInvoicePopup(event, url) {
            event.preventDefault();
            const width = Math.min(1100, window.screen.availWidth - 100);
            const height = Math.min(900, window.screen.availHeight - 100);
            const left = Math.max(0, (window.screen.availWidth - width) / 2);
            const top = Math.max(0, (window.screen.availHeight - height) / 2);
            const features = `popup=yes,width=${width},height=${height},left=${left},top=${top},resizable=yes,scrollbars=yes`;
            window.open(url, 'invoiceViewer_' + Date.now(), features);
            return false;
        }

        let selectedInvoices = new Map();
        
        document.addEventListener('DOMContentLoaded', function() {
            const invoiceCheckboxes = document.querySelectorAll('.invoice-checkbox');
            const supplierSelectAllCheckboxes = document.querySelectorAll('.supplier-select-all');
            const supplierSelectAllTableCheckboxes = document.querySelectorAll('.supplier-select-all-table');
            const bulkActionsBar = document.getElementById('bulk-actions-bar');
            const selectionCount = document.getElementById('selection-count');
            const selectionTotal = document.getElementById('selection-total');
            const supplierBreakdown = document.getElementById('supplier-breakdown');
            const supplierList = document.getElementById('supplier-list');
            const markPaidBtn = document.getElementById('mark-paid-btn');
            const clearSelectionBtn = document.getElementById('clear-selection-btn');
            const paymentModal = document.getElementById('payment-modal');
            const bulkPaymentForm = document.getElementById('bulk-payment-form');
            
            // Force clear all checkboxes on page load
            selectedInvoices.clear();
            invoiceCheckboxes.forEach(checkbox => checkbox.checked = false);
            supplierSelectAllCheckboxes.forEach(checkbox => checkbox.checked = false);
            supplierSelectAllTableCheckboxes.forEach(checkbox => checkbox.checked = false);
            if (bulkActionsBar) bulkActionsBar.classList.add('hidden');
            
            // Handle individual invoice checkboxes
            invoiceCheckboxes.forEach(checkbox => {
                checkbox.addEventListener('change', function() {
                    if (this.checked) {
                        addToSelection(this);
                    } else {
                        removeFromSelection(this);
                    }
                    updateSelectionDisplay();
                    updateSupplierSelectAllStates(this.dataset.supplierIndex);
                });
            });
            
            // Handle supplier select all checkboxes (both header and table)
            [...supplierSelectAllCheckboxes, ...supplierSelectAllTableCheckboxes].forEach(checkbox => {
                checkbox.addEventListener('change', function() {
                    const supplierIndex = this.dataset.supplierIndex;
                    const supplierInvoices = document.querySelectorAll(`.invoice-checkbox[data-supplier-index="${supplierIndex}"]`);
                    
                    supplierInvoices.forEach(invoiceCheckbox => {
                        invoiceCheckbox.checked = this.checked;
                        if (this.checked) {
                            addToSelection(invoiceCheckbox);
                        } else {
                            removeFromSelection(invoiceCheckbox);
                        }
                    });
                    
                    // Sync both supplier checkboxes
                    const otherSupplierCheckboxes = document.querySelectorAll(`[data-supplier-index="${supplierIndex}"]`);
                    otherSupplierCheckboxes.forEach(cb => {
                        if (cb !== this) cb.checked = this.checked;
                    });
                    
                    updateSelectionDisplay();
                });
            });
            
            // Clear selection button
            if (clearSelectionBtn) {
                clearSelectionBtn.addEventListener('click', function() {
                    selectedInvoices.clear();
                    invoiceCheckboxes.forEach(checkbox => checkbox.checked = false);
                    supplierSelectAllCheckboxes.forEach(checkbox => checkbox.checked = false);
                    supplierSelectAllTableCheckboxes.forEach(checkbox => checkbox.checked = false);
                    updateSelectionDisplay();
                });
            }
            
            // Mark as paid button
            if (markPaidBtn) {
                markPaidBtn.addEventListener('click', function() {
                    if (selectedInvoices.size === 0) return;
                    showPaymentModal();
                });
            }
            
            // Modal controls
            if (document.getElementById('close-modal')) {
                document.getElementById('close-modal').addEventListener('click', hidePaymentModal);
            }
            if (document.getElementById('cancel-payment')) {
                document.getElementById('cancel-payment').addEventListener('click', hidePaymentModal);
            }
            
            // Form submission
            if (bulkPaymentForm) {
                bulkPaymentForm.addEventListener('submit', function(e) {
                    e.preventDefault();
                    submitBulkPayment();
                });
            }
            
            function addToSelection(checkbox) {
                const invoiceData = {
                    id: checkbox.dataset.invoiceId,
                    supplierIndex: checkbox.dataset.supplierIndex,
                    supplierId: checkbox.dataset.supplierId,
                    supplierName: checkbox.dataset.supplierName,
                    totalAmount: parseFloat(checkbox.dataset.totalAmount),
                    invoiceNumber: checkbox.dataset.invoiceNumber
                };
                selectedInvoices.set(invoiceData.id, invoiceData);
            }
            
            function removeFromSelection(checkbox) {
                selectedInvoices.delete(checkbox.dataset.invoiceId);
            }
            
            function updateSelectionDisplay() {
                const count = selectedInvoices.size;
                const total = Array.from(selectedInvoices.values())
                    .reduce((sum, invoice) => sum + invoice.totalAmount, 0);
                
                if (count === 0) {
                    if (bulkActionsBar) bulkActionsBar.classList.add('hidden');
                } else {
                    if (bulkActionsBar) bulkActionsBar.classList.remove('hidden');
                    if (selectionCount) selectionCount.textContent = `${count} invoice${count !== 1 ? 's' : ''} selected`;
                    if (selectionTotal) selectionTotal.textContent = `Total: €${total.toFixed(2)}`;
                    
                    // Update supplier breakdown
                    updateSupplierBreakdown();
                }
            }
            
            function updateSupplierBreakdown() {
                const supplierTotals = new Map();
                
                selectedInvoices.forEach(invoice => {
                    if (!supplierTotals.has(invoice.supplierId)) {
                        supplierTotals.set(invoice.supplierId, {
                            name: invoice.supplierName,
                            count: 0,
                            total: 0,
                            invoices: []
                        });
                    }
                    
                    const supplier = supplierTotals.get(invoice.supplierId);
                    supplier.count++;
                    supplier.total += invoice.totalAmount;
                    supplier.invoices.push(invoice);
                });
                
                if (supplierTotals.size > 1 && supplierBreakdown) {
                    supplierBreakdown.classList.remove('hidden');
                    
                    if (supplierList) {
                        supplierList.innerHTML = '';
                        supplierTotals.forEach(supplier => {
                            const div = document.createElement('div');
                            div.className = 'bg-blue-800 rounded p-2';
                            div.innerHTML = `
                                <div class="text-blue-100 font-medium">${supplier.name}</div>
                                <div class="text-blue-200 text-sm">${supplier.count} invoice${supplier.count !== 1 ? 's' : ''} - €${supplier.total.toFixed(2)}</div>
                            `;
                            supplierList.appendChild(div);
                        });
                    }
                } else {
                    if (supplierBreakdown) supplierBreakdown.classList.add('hidden');
                }
            }
            
            function updateSupplierSelectAllStates(supplierIndex) {
                const supplierInvoices = document.querySelectorAll(`.invoice-checkbox[data-supplier-index="${supplierIndex}"]`);
                const checkedCount = document.querySelectorAll(`.invoice-checkbox[data-supplier-index="${supplierIndex}"]:checked`).length;
                const totalCount = supplierInvoices.length;
                
                const supplierCheckboxes = document.querySelectorAll(`[data-supplier-index="${supplierIndex}"]`);
                supplierCheckboxes.forEach(checkbox => {
                    if (checkbox.classList.contains('supplier-select-all') || checkbox.classList.contains('supplier-select-all-table')) {
                        checkbox.checked = checkedCount === totalCount && totalCount > 0;
                        checkbox.indeterminate = checkedCount > 0 && checkedCount < totalCount;
                    }
                });
            }
            
            function showPaymentModal() {
                updateModalSupplierBreakdown();
                if (paymentModal) {
                    paymentModal.classList.remove('hidden');
                    const paymentDateInput = document.getElementById('payment_date');
                    if (paymentDateInput) paymentDateInput.focus();
                }
            }
            
            function hidePaymentModal() {
                if (paymentModal) {
                    paymentModal.classList.add('hidden');
                    const notesField = document.getElementById('payment_notes');
                    if (notesField) notesField.value = '';
                    const notesDetails = notesField?.closest('details');
                    if (notesDetails) notesDetails.removeAttribute('open');
                }
            }
            
            function updateModalSupplierBreakdown() {
                const supplierTotals = new Map();
                
                selectedInvoices.forEach(invoice => {
                    if (!supplierTotals.has(invoice.supplierId)) {
                        supplierTotals.set(invoice.supplierId, {
                            name: invoice.supplierName,
                            count: 0,
                            total: 0,
                            invoices: []
                        });
                    }
                    
                    const supplier = supplierTotals.get(invoice.supplierId);
                    supplier.count++;
                    supplier.total += invoice.totalAmount;
                    supplier.invoices.push(invoice);
                });
                
                const modalBreakdown = document.getElementById('modal-supplier-breakdown');
                if (modalBreakdown) {
                    modalBreakdown.innerHTML = '';
                    
                    supplierTotals.forEach(supplier => {
                        const div = document.createElement('div');
                        div.className = 'bg-gray-100 rounded p-3';
                        
                        const invoicesList = supplier.invoices
                            .map(inv => inv.invoiceNumber)
                            .join(', ');
                        
                        div.innerHTML = `
                            <div class="flex justify-between items-start">
                                <div>
                                    <div class="text-gray-800 font-medium">${supplier.name}</div>
                                    <div class="text-gray-600 text-sm">${invoicesList}</div>
                                </div>
                                <div class="text-right">
                                    <div class="text-gray-800 font-medium">€${supplier.total.toFixed(2)}</div>
                                    <div class="text-gray-600 text-sm">${supplier.count} invoice${supplier.count !== 1 ? 's' : ''}</div>
                                </div>
                            </div>
                        `;
                        modalBreakdown.appendChild(div);
                    });
                }
            }
            
            function submitBulkPayment() {
                const formData = new FormData(bulkPaymentForm);
                const invoiceIds = Array.from(selectedInvoices.keys());
                
                // Add invoice IDs to form data
                invoiceIds.forEach(id => {
                    formData.append('invoice_ids[]', id);
                });
                
                fetch('{{ route("invoices.bulk-mark-paid") }}', {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                    }
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        hidePaymentModal();
                        // Clear checkboxes and selection state before refresh
                        selectedInvoices.clear();
                        invoiceCheckboxes.forEach(checkbox => checkbox.checked = false);
                        supplierSelectAllCheckboxes.forEach(checkbox => checkbox.checked = false);
                        supplierSelectAllTableCheckboxes.forEach(checkbox => checkbox.checked = false);
                        updateSelectionDisplay();
                        // Show success message
                        alert(`Success: ${data.message}`);
                        // Refresh page to show updated payment statuses
                        window.location.reload();
                    } else {
                        alert(data.error || 'Failed to mark invoices as paid');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred while processing payment');
                });
            }
        });
    </script>
    @endpush
</x-admin-layout>