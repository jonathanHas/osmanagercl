<x-admin-layout>
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
        {{-- Header --}}
        <div class="flex justify-between items-center mb-6">
            <h2 class="text-2xl font-bold text-gray-100">Invoices</h2>
            <div class="flex space-x-2">
                @if($amazonPendingCount > 0)
                <a href="{{ route('invoices.bulk-upload.amazon-pending') }}" 
                   class="bg-orange-600 hover:bg-orange-700 text-white font-bold py-2 px-4 rounded inline-flex items-center">
                    <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
                    </svg>
                    Amazon Pending ({{ $amazonPendingCount }})
                </a>
                @endif
                
                <a href="{{ route('invoices.bulk-upload.index') }}" 
                   class="bg-purple-600 hover:bg-purple-700 text-white font-bold py-2 px-4 rounded inline-flex items-center">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/>
                    </svg>
                    Bulk Upload
                </a>
                <a href="{{ route('vat-rates.index') }}"
                   class="bg-gray-600 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded inline-flex items-center">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                    </svg>
                    VAT Rates
                </a>
                <a href="{{ route('invoices.export', request()->all()) }}" 
                   class="bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-4 rounded inline-flex items-center">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                    Export CSV
                </a>
                <div class="flex space-x-1">
                    <a href="{{ route('invoices.create-simple') }}" 
                       class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded inline-flex items-center">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                        </svg>
                        New Invoice
                    </a>
                    <a href="{{ route('invoices.create') }}" 
                       class="bg-gray-600 hover:bg-gray-700 text-white font-bold py-1 px-3 rounded text-sm inline-flex items-center">
                        Detailed
                    </a>
                </div>
            </div>
        </div>

        {{-- Flash Messages --}}
        @if(session('success'))
            <div class="bg-green-600 text-white px-4 py-3 rounded mb-6">
                {{ session('success') }}
            </div>
        @endif
        @if(session('error'))
            <div class="bg-red-600 text-white px-4 py-3 rounded mb-6">
                {{ session('error') }}
            </div>
        @endif

        {{-- Statistics Cards --}}
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
            <div class="bg-gray-800 rounded-lg p-4">
                <div class="text-gray-400 text-sm font-medium">Total Unpaid</div>
                <div class="text-2xl font-bold text-yellow-400">€{{ number_format($stats['total_unpaid'], 2) }}</div>
                <div class="text-gray-500 text-xs">{{ $stats['count_unpaid'] }} invoices</div>
            </div>
            <div class="bg-gray-800 rounded-lg p-4">
                <div class="text-gray-400 text-sm font-medium">Overdue</div>
                <div class="text-2xl font-bold text-red-400">€{{ number_format($stats['total_overdue'], 2) }}</div>
                <div class="text-gray-500 text-xs">{{ $stats['count_overdue'] }} invoices</div>
            </div>
            <div class="bg-gray-800 rounded-lg p-4">
                <div class="text-gray-400 text-sm font-medium">This Month</div>
                <div class="text-2xl font-bold text-green-400">
                    €{{ number_format($monthlyTotals['this_month'], 2) }}
                </div>
            </div>
            <div class="bg-gray-800 rounded-lg p-4">
                <div class="text-gray-400 text-sm font-medium">Last Month</div>
                <div class="text-2xl font-bold text-gray-300">
                    €{{ number_format($monthlyTotals['last_month'], 2) }}
                </div>
            </div>
        </div>

        {{-- Filters --}}
        <div class="bg-gray-800 rounded-lg p-4 mb-6">
            <form method="GET" action="{{ route('invoices.index') }}">
                <div class="grid grid-cols-1 md:grid-cols-6 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-400 mb-1">Supplier</label>
                        <select name="supplier_id" class="w-full bg-gray-700 border-gray-600 text-gray-100 rounded-md">
                            <option value="">All Suppliers</option>
                            @foreach($suppliers as $id => $name)
                                <option value="{{ $id }}" {{ request('supplier_id') == $id ? 'selected' : '' }}>
                                    {{ $name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-400 mb-1">Status</label>
                        <select name="payment_status" class="w-full bg-gray-700 border-gray-600 text-gray-100 rounded-md">
                            <option value="">All Status</option>
                            <option value="unpaid" {{ request('payment_status') == 'unpaid' ? 'selected' : '' }}>All Unpaid</option>
                            <option value="pending" {{ request('payment_status') == 'pending' ? 'selected' : '' }}>Pending</option>
                            <option value="overdue" {{ request('payment_status') == 'overdue' ? 'selected' : '' }}>Overdue</option>
                            <option value="paid" {{ request('payment_status') == 'paid' ? 'selected' : '' }}>Paid</option>
                            <option value="partial" {{ request('payment_status') == 'partial' ? 'selected' : '' }}>Partial</option>
                            <option value="cancelled" {{ request('payment_status') == 'cancelled' ? 'selected' : '' }}>Cancelled</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-400 mb-1">From Date</label>
                        <input type="date" name="from_date" value="{{ request('from_date', now()->subMonths(3)->format('Y-m-d')) }}"
                               class="w-full bg-gray-700 border-gray-600 text-gray-100 rounded-md">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-400 mb-1">To Date</label>
                        <input type="date" name="to_date" value="{{ request('to_date') }}"
                               class="w-full bg-gray-700 border-gray-600 text-gray-100 rounded-md">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-400 mb-1">Search</label>
                        <input type="text" name="search" value="{{ request('search') }}"
                               placeholder="Invoice #, supplier..."
                               class="w-full bg-gray-700 border-gray-600 text-gray-100 rounded-md">
                    </div>
                    <div class="flex items-end space-x-2">
                        <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
                            Filter
                        </button>
                        <a href="{{ route('invoices.index') }}" class="bg-gray-600 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded">
                            Clear
                        </a>
                    </div>
                </div>

                {{-- Payment Date Filter (collapsible) --}}
                <div x-data="{ open: {{ request()->hasAny(['paid_from_date', 'paid_to_date']) ? 'true' : 'false' }} }" class="mt-3">
                    <button @click="open = !open" type="button" class="flex items-center text-sm font-medium text-gray-400 hover:text-gray-200 transition-colors">
                        <svg :class="open ? 'rotate-90' : ''" class="w-4 h-4 mr-1 transition-transform" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"/>
                        </svg>
                        Payment Date Filter
                        @if(request()->hasAny(['paid_from_date', 'paid_to_date']))
                            <span class="ml-2 text-xs text-blue-400">(active)</span>
                        @endif
                    </button>
                    <div x-show="open" x-collapse class="mt-2 grid grid-cols-1 md:grid-cols-6 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-400 mb-1">Paid From</label>
                            <input type="date" name="paid_from_date" value="{{ request('paid_from_date') }}"
                                   class="w-full bg-gray-700 border-gray-600 text-gray-100 rounded-md">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-400 mb-1">Paid To</label>
                            <input type="date" name="paid_to_date" value="{{ request('paid_to_date') }}"
                                   class="w-full bg-gray-700 border-gray-600 text-gray-100 rounded-md">
                        </div>
                    </div>
                </div>
            </form>
        </div>

        {{-- Filtered Statistics Cards (show only when filters are applied) --}}
        @if(request()->hasAny(['supplier_id', 'payment_status', 'from_date', 'to_date', 'search', 'paid_from_date', 'paid_to_date']))
        <div class="bg-gray-800 rounded-lg p-4 mb-6">
            <h3 class="text-lg font-semibold text-gray-200 mb-4">Filtered Results Summary</h3>
            
            {{-- Main Summary Cards --}}
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-4">
                <div class="bg-gray-700 rounded p-3">
                    <div class="text-gray-400 text-sm">Total Invoices</div>
                    <div class="text-xl font-bold text-white">{{ $filteredStats['total_count'] }}</div>
                </div>
                <div class="bg-gray-700 rounded p-3">
                    <div class="text-gray-400 text-sm">Total Amount</div>
                    <div class="text-xl font-bold text-green-400">€{{ number_format($filteredStats['total_amount'], 2) }}</div>
                </div>
                <div class="bg-gray-700 rounded p-3">
                    <div class="text-gray-400 text-sm">Total VAT</div>
                    <div class="text-xl font-bold text-blue-400">€{{ number_format($filteredStats['total_vat'], 2) }}</div>
                </div>
                <div class="bg-gray-700 rounded p-3">
                    <div class="text-gray-400 text-sm">Net Amount</div>
                    <div class="text-xl font-bold text-gray-300">€{{ number_format($filteredStats['total_subtotal'], 2) }}</div>
                </div>
            </div>

            {{-- VAT Breakdown --}}
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <h4 class="text-sm font-medium text-gray-400 mb-2">VAT Breakdown</h4>
                    <div class="space-y-2">
                        @if($filteredStats['standard_net'] != 0 || $filteredStats['standard_vat'] != 0)
                        <div class="flex justify-between text-sm">
                            <span class="text-gray-400">Standard Rate (23%)</span>
                            <div class="text-right">
                                <span class="text-gray-300">€{{ number_format($filteredStats['standard_net'], 2) }}</span>
                                <span class="text-gray-500 mx-1">+</span>
                                <span class="text-blue-400">€{{ number_format($filteredStats['standard_vat'], 2) }}</span>
                            </div>
                        </div>
                        @endif
                        @if($filteredStats['reduced_net'] != 0 || $filteredStats['reduced_vat'] != 0)
                        <div class="flex justify-between text-sm">
                            <span class="text-gray-400">Reduced Rate (13.5%)</span>
                            <div class="text-right">
                                <span class="text-gray-300">€{{ number_format($filteredStats['reduced_net'], 2) }}</span>
                                <span class="text-gray-500 mx-1">+</span>
                                <span class="text-blue-400">€{{ number_format($filteredStats['reduced_vat'], 2) }}</span>
                            </div>
                        </div>
                        @endif
                        @if($filteredStats['second_reduced_net'] != 0 || $filteredStats['second_reduced_vat'] != 0)
                        <div class="flex justify-between text-sm">
                            <span class="text-gray-400">Second Reduced (9%)</span>
                            <div class="text-right">
                                <span class="text-gray-300">€{{ number_format($filteredStats['second_reduced_net'], 2) }}</span>
                                <span class="text-gray-500 mx-1">+</span>
                                <span class="text-blue-400">€{{ number_format($filteredStats['second_reduced_vat'], 2) }}</span>
                            </div>
                        </div>
                        @endif
                        @if($filteredStats['zero_net'] != 0)
                        <div class="flex justify-between text-sm">
                            <span class="text-gray-400">Zero Rate (0%)</span>
                            <div class="text-right">
                                <span class="text-gray-300">€{{ number_format($filteredStats['zero_net'], 2) }}</span>
                            </div>
                        </div>
                        @endif
                    </div>
                </div>

                {{-- Payment Status Breakdown --}}
                <div>
                    <h4 class="text-sm font-medium text-gray-400 mb-2">Payment Status</h4>
                    <div class="space-y-2">
                        <div class="flex justify-between text-sm">
                            <span class="text-green-400">Paid</span>
                            <span class="text-gray-300">{{ $filteredStats['paid_count'] }} invoices</span>
                        </div>
                        <div class="flex justify-between text-sm">
                            <span class="text-yellow-400">Unpaid</span>
                            <div class="text-right">
                                <span class="text-gray-300">{{ $filteredStats['unpaid_count'] }} invoices</span>
                                <span class="text-red-400 ml-2">€{{ number_format($filteredStats['unpaid_total'], 2) }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        @endif

        {{-- Bulk Actions Bar --}}
        <div id="bulk-actions-bar" class="hidden bg-blue-900 rounded-lg p-4 mb-4">
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
            
            {{-- Breakdown by supplier --}}
            <div id="supplier-breakdown" class="mt-3 hidden">
                <div class="text-blue-200 text-sm font-medium mb-2">Selected by supplier:</div>
                <div id="supplier-list" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-2">
                    <!-- Supplier breakdowns will be inserted here -->
                </div>
            </div>
        </div>

        {{-- Invoices Table --}}
        <div class="bg-gray-800 rounded-lg overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-700" style="min-width: 1200px;">
                <thead class="bg-gray-900">
                    <tr>
                        <th class="px-3 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider w-8">
                            <input type="checkbox" id="select-all" class="rounded bg-gray-700 border-gray-600 text-blue-600 focus:ring-blue-500 focus:ring-2" autocomplete="off">
                        </th>
                        <th class="px-3 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider" style="width: 100px;">
                            <a href="{{ route('invoices.index', array_merge(request()->all(), ['sort' => 'invoice_number', 'direction' => $sortField === 'invoice_number' && $sortDirection === 'asc' ? 'desc' : 'asc'])) }}" 
                               class="flex items-center space-x-1 hover:text-gray-200">
                                <span>Invoice #</span>
                                @if($sortField === 'invoice_number')
                                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                        @if($sortDirection === 'asc')
                                            <path fill-rule="evenodd" d="M14.707 12.707a1 1 0 01-1.414 0L10 9.414l-3.293 3.293a1 1 0 01-1.414-1.414l4-4a1 1 0 011.414 0l4 4a1 1 0 010 1.414z" clip-rule="evenodd" />
                                        @else
                                            <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                        @endif
                                    </svg>
                                @endif
                            </a>
                        </th>
                        <th class="px-3 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider" style="width: 140px;">
                            <a href="{{ route('invoices.index', array_merge(request()->all(), ['sort' => 'supplier_name', 'direction' => $sortField === 'supplier_name' && $sortDirection === 'asc' ? 'desc' : 'asc'])) }}" 
                               class="flex items-center space-x-1 hover:text-gray-200">
                                <span>Supplier</span>
                                @if($sortField === 'supplier_name')
                                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                        @if($sortDirection === 'asc')
                                            <path fill-rule="evenodd" d="M14.707 12.707a1 1 0 01-1.414 0L10 9.414l-3.293 3.293a1 1 0 01-1.414-1.414l4-4a1 1 0 011.414 0l4 4a1 1 0 010 1.414z" clip-rule="evenodd" />
                                        @else
                                            <path fill-rule="evenodd" d="M5.293 7.293a1 1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                        @endif
                                    </svg>
                                @endif
                            </a>
                        </th>
                        <th class="px-3 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider" style="width: 80px;">
                            <a href="{{ route('invoices.index', array_merge(request()->all(), ['sort' => 'invoice_date', 'direction' => $sortField === 'invoice_date' && $sortDirection === 'asc' ? 'desc' : 'asc'])) }}" 
                               class="flex items-center space-x-1 hover:text-gray-200">
                                <span>Date</span>
                                @if($sortField === 'invoice_date')
                                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                        @if($sortDirection === 'asc')
                                            <path fill-rule="evenodd" d="M14.707 12.707a1 1 0 01-1.414 0L10 9.414l-3.293 3.293a1 1 0 01-1.414-1.414l4-4a1 1 0 011.414 0l4 4a1 1 0 010 1.414z" clip-rule="evenodd" />
                                        @else
                                            <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                        @endif
                                    </svg>
                                @endif
                            </a>
                        </th>
                        <th class="px-3 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider" style="width: 120px;">
                            @php
                                // Smart toggle: if currently sorting by payment_status, switch to payment_date, and vice versa
                                if ($sortField === 'payment_status') {
                                    $nextSort = 'payment_date';
                                    $nextDirection = 'desc'; // Most recent payments first by default
                                } elseif ($sortField === 'payment_date') {
                                    $nextSort = 'payment_date';
                                    $nextDirection = $sortDirection === 'asc' ? 'desc' : 'asc';
                                } else {
                                    $nextSort = 'payment_status';
                                    $nextDirection = 'asc';
                                }
                            @endphp
                            <a href="{{ route('invoices.index', array_merge(request()->all(), ['sort' => $nextSort, 'direction' => $nextDirection])) }}" 
                               class="flex items-center space-x-1 hover:text-gray-200">
                                <span>Status / Paid On</span>
                                @if($sortField === 'payment_status' || $sortField === 'payment_date')
                                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                        @if($sortDirection === 'asc')
                                            <path fill-rule="evenodd" d="M14.707 12.707a1 1 0 01-1.414 0L10 9.414l-3.293 3.293a1 1 0 01-1.414-1.414l4-4a1 1 0 011.414 0l4 4a1 1 0 010 1.414z" clip-rule="evenodd" />
                                        @else
                                            <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                        @endif
                                    </svg>
                                @endif
                            </a>
                        </th>
                        <th class="px-3 py-3 text-right text-xs font-medium text-gray-400 uppercase tracking-wider" style="width: 80px;">
                            <a href="{{ route('invoices.index', array_merge(request()->all(), ['sort' => 'subtotal', 'direction' => $sortField === 'subtotal' && $sortDirection === 'asc' ? 'desc' : 'asc'])) }}" 
                               class="flex items-center justify-end space-x-1 hover:text-gray-200">
                                <span>Net</span>
                                @if($sortField === 'subtotal')
                                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                        @if($sortDirection === 'asc')
                                            <path fill-rule="evenodd" d="M14.707 12.707a1 1 0 01-1.414 0L10 9.414l-3.293 3.293a1 1 0 01-1.414-1.414l4-4a1 1 0 011.414 0l4 4a1 1 0 010 1.414z" clip-rule="evenodd" />
                                        @else
                                            <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                        @endif
                                    </svg>
                                @endif
                            </a>
                        </th>
                        <th class="px-3 py-3 text-right text-xs font-medium text-gray-400 uppercase tracking-wider" style="width: 80px;">
                            <a href="{{ route('invoices.index', array_merge(request()->all(), ['sort' => 'vat_amount', 'direction' => $sortField === 'vat_amount' && $sortDirection === 'asc' ? 'desc' : 'asc'])) }}" 
                               class="flex items-center justify-end space-x-1 hover:text-gray-200">
                                <span>VAT</span>
                                @if($sortField === 'vat_amount')
                                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                        @if($sortDirection === 'asc')
                                            <path fill-rule="evenodd" d="M14.707 12.707a1 1 0 01-1.414 0L10 9.414l-3.293 3.293a1 1 0 01-1.414-1.414l4-4a1 1 0 011.414 0l4 4a1 1 0 010 1.414z" clip-rule="evenodd" />
                                        @else
                                            <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                        @endif
                                    </svg>
                                @endif
                            </a>
                        </th>
                        <th class="px-3 py-3 text-right text-xs font-medium text-gray-400 uppercase tracking-wider" style="width: 90px;">
                            <a href="{{ route('invoices.index', array_merge(request()->all(), ['sort' => 'total_amount', 'direction' => $sortField === 'total_amount' && $sortDirection === 'asc' ? 'desc' : 'asc'])) }}" 
                               class="flex items-center justify-end space-x-1 hover:text-gray-200">
                                <span>Total</span>
                                @if($sortField === 'total_amount')
                                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                        @if($sortDirection === 'asc')
                                            <path fill-rule="evenodd" d="M14.707 12.707a1 1 0 01-1.414 0L10 9.414l-3.293 3.293a1 1 0 01-1.414-1.414l4-4a1 1 0 011.414 0l4 4a1 1 0 010 1.414z" clip-rule="evenodd" />
                                        @else
                                            <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                        @endif
                                    </svg>
                                @endif
                            </a>
                        </th>
                        <th class="px-3 py-3 text-right text-xs font-medium text-gray-400 uppercase tracking-wider" style="width: 70px;">
                            Actions
                        </th>
                    </tr>
                </thead>
                <tbody class="bg-gray-800 divide-y divide-gray-700">
                    @forelse($invoices as $invoice)
                        <tr class="hover:bg-gray-750" data-invoice-id="{{ $invoice->id }}">
                            <td class="px-3 py-3 whitespace-nowrap">
                                <input type="checkbox" 
                                       class="invoice-checkbox rounded bg-gray-700 border-gray-600 text-blue-600 focus:ring-blue-500 focus:ring-2" 
                                       data-invoice-id="{{ $invoice->id }}"
                                       data-supplier-id="{{ $invoice->supplier_id }}"
                                       data-supplier-name="{{ $invoice->supplier_name }}"
                                       data-total-amount="{{ $invoice->total_amount }}"
                                       data-invoice-number="{{ $invoice->invoice_number }}"
                                       autocomplete="off">
                            </td>
                            <td class="px-3 py-3 text-gray-300">
                                <a href="{{ route('invoices.show', $invoice) }}" class="text-blue-400 hover:text-blue-300 break-all text-sm">
                                    @php
                                        $invoiceNum = $invoice->invoice_number;
                                        // Add soft breaks for bulk upload format (BU-2025-000137)
                                        if (preg_match('/^(BU-\d{4}-)(\d+)$/', $invoiceNum, $matches)) {
                                            $displayNum = $matches[1] . '<br>' . $matches[2];
                                        } else {
                                            $displayNum = $invoiceNum;
                                        }
                                    @endphp
                                    {!! $displayNum !!}
                                    @if($invoice->hasAttachments())
                                        @php
                                            $hasMissing = $invoice->hasMissingAttachments();
                                            $missingCount = $hasMissing ? $invoice->missing_attachment_count : 0;
                                        @endphp
                                        @if($hasMissing)
                                            {{-- Files missing - show warning badge (clickable to upload) --}}
                                            <span class="inline-flex items-center ml-1 px-1 py-0.5 rounded-full text-xs bg-red-600 text-red-100 hover:bg-red-500 cursor-pointer transition-colors"
                                                  title="File{{ $missingCount > 1 ? 's' : '' }} missing! Click to upload replacement"
                                                  onclick="event.preventDefault(); event.stopPropagation(); showMissingFilesModal({{ $invoice->id }});">
                                                <svg class="w-2 h-2 mr-0.5" fill="currentColor" viewBox="0 0 24 24">
                                                    <path d="M13,13H11V7H13M13,17H11V15H13M12,2A10,10 0 0,0 2,12A10,10 0 0,0 12,22A10,10 0 0,0 22,12A10,10 0 0,0 12,2Z" />
                                                </svg>
                                                {{ $invoice->attachment_count }}!
                                            </span>
                                        @else
                                            {{-- Files exist - show normal clickable badge --}}
                                            <span class="inline-flex items-center ml-1 px-1 py-0.5 rounded-full text-xs bg-blue-600 text-blue-100 hover:bg-blue-500 cursor-pointer transition-colors"
                                                  title="Click to view attachment ({{ $invoice->attachment_count }} file{{ $invoice->attachment_count > 1 ? 's' : '' }})"
                                                  onclick="event.preventDefault(); event.stopPropagation(); viewInvoiceAttachment({{ $invoice->id }});">
                                                <svg class="w-2 h-2 mr-0.5" fill="currentColor" viewBox="0 0 24 24">
                                                    <path d="M14,2H6A2,2 0 0,0 4,4V20A2,2 0 0,0 6,22H18A2,2 0 0,0 20,20V8L14,2M18,20H6V4H13V9H18V20Z" />
                                                </svg>
                                                {{ $invoice->attachment_count }}
                                            </span>
                                        @endif
                                    @endif
                                </a>
                            </td>
                            <td class="px-3 py-3 text-gray-300 text-sm">
                                <div class="break-words">{{ $invoice->supplier_name }}</div>
                            </td>
                            <td class="px-3 py-3 whitespace-nowrap text-gray-300 text-sm">
                                {{ $invoice->invoice_date->format('d/m/Y') }}
                            </td>
                            <td class="px-3 py-3">
                                @php
                                    $statusColors = [
                                        'pending' => 'bg-yellow-900 text-yellow-300',
                                        'overdue' => 'bg-red-900 text-red-300',
                                        'paid' => 'bg-green-900 text-green-300',
                                        'partial' => 'bg-orange-900 text-orange-300',
                                        'cancelled' => 'bg-gray-700 text-gray-400',
                                    ];
                                    $statusColor = $statusColors[$invoice->payment_status] ?? 'bg-gray-700 text-gray-400';
                                @endphp
                                <div class="space-y-1">
                                    <div>
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full {{ $statusColor }}">
                                            {{ ucfirst($invoice->payment_status) }}
                                        </span>
                                    </div>
                                    @if($invoice->payment_date && $invoice->payment_status === 'paid')
                                        <div class="text-xs text-green-400">
                                            {{ $invoice->payment_date->format('d/m/Y') }}
                                        </div>
                                    @endif
                                    {{-- RTD Status Badge --}}
                                    @if($invoice->hasRtdData())
                                        @if($invoice->rtd_status === 'frozen')
                                            <span class="px-1.5 py-0.5 rounded text-xs bg-green-700 text-green-200" title="RTD Frozen">RTD</span>
                                        @elseif($invoice->isRtdComplete())
                                            <span class="px-1.5 py-0.5 rounded text-xs bg-blue-700 text-blue-200" title="RTD Complete">RTD</span>
                                        @else
                                            <span class="px-1.5 py-0.5 rounded text-xs bg-yellow-700 text-yellow-200" title="{{ $invoice->rtd_breakdown['unresolved']['count'] ?? 0 }} unresolved">RTD!</span>
                                        @endif
                                    @endif
                                </div>
                            </td>
                            <td class="px-3 py-3 whitespace-nowrap text-right text-gray-300 text-sm">
                                €{{ number_format($invoice->subtotal, 2) }}
                            </td>
                            <td class="px-3 py-3 whitespace-nowrap text-right text-gray-300 text-sm">
                                €{{ number_format($invoice->vat_amount, 2) }}
                            </td>
                            <td class="px-3 py-3 whitespace-nowrap text-right font-semibold text-white text-sm">
                                €{{ number_format($invoice->total_amount, 2) }}
                            </td>
                            <td class="px-3 py-3 whitespace-nowrap text-right text-sm font-medium">
                                <div class="flex justify-end space-x-1">
                                    <a href="{{ route('invoices.show', $invoice) }}" 
                                       class="text-gray-400 hover:text-gray-300" title="View">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                        </svg>
                                    </a>
                                    <a href="{{ route('invoices.edit', $invoice) }}"
                                       class="text-blue-400 hover:text-blue-300" title="Edit">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                        </svg>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="px-3 py-12 text-center text-gray-500">
                                No invoices found. <a href="{{ route('invoices.create') }}" class="text-blue-400 hover:text-blue-300">Create your first invoice</a>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

    </div>

    {{-- Bulk Payment Modal --}}
    <div id="payment-modal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
        <div class="bg-gray-800 rounded-lg max-w-2xl w-full max-h-[90vh] overflow-y-auto">
            <div class="p-6">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-xl font-bold text-gray-100">Mark Invoices as Paid</h3>
                    <button id="close-modal" class="text-gray-400 hover:text-gray-200">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>
                
                <form id="bulk-payment-form">
                    @csrf
                    
                    {{-- Selected Invoices Summary --}}
                    <div class="mb-6">
                        <h4 class="text-lg font-semibold text-gray-200 mb-3">Selected Invoices</h4>
                        <div id="modal-supplier-breakdown" class="space-y-3">
                            <!-- Supplier breakdown will be inserted here -->
                        </div>
                    </div>
                    
                    {{-- Payment Details --}}
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                        <div>
                            <label class="block text-sm font-medium text-gray-400 mb-1">Payment Date</label>
                            <input type="date" name="payment_date" id="payment_date" 
                                   value="{{ now()->format('Y-m-d') }}"
                                   class="w-full bg-gray-700 border-gray-600 text-gray-100 rounded-md"
                                   required>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-400 mb-1">Payment Method</label>
                            <select name="payment_method" id="payment_method" 
                                    class="w-full bg-gray-700 border-gray-600 text-gray-100 rounded-md">
                                <option value="bank_transfer">Bank Transfer</option>
                                <option value="cash">Cash</option>
                                <option value="cheque">Cheque</option>
                                <option value="credit_card">Credit Card</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="mb-6">
                        <label class="block text-sm font-medium text-gray-400 mb-1">Payment Reference (Optional)</label>
                        <input type="text" name="payment_reference" id="payment_reference" 
                               placeholder="e.g., Transfer confirmation number, cheque number..."
                               class="w-full bg-gray-700 border-gray-600 text-gray-100 rounded-md">
                    </div>
                    
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

    {{-- Missing Files Upload Modal --}}
    <div id="missing-files-modal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
        <div class="bg-gray-800 rounded-lg max-w-2xl w-full max-h-[90vh] overflow-y-auto">
            <div class="p-6">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-xl font-bold text-gray-100">
                        <svg class="w-6 h-6 inline-block mr-2 text-red-400" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M13,13H11V7H13M13,17H11V15H13M12,2A10,10 0 0,0 2,12A10,10 0 0,0 12,22A10,10 0 0,0 22,12A10,10 0 0,0 12,2Z" />
                        </svg>
                        Missing Files
                    </h3>
                    <button id="close-missing-modal" class="text-gray-400 hover:text-gray-200">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>

                {{-- Invoice Info --}}
                <div class="bg-gray-700 rounded p-3 mb-4">
                    <div class="text-sm text-gray-400">Invoice</div>
                    <div class="text-lg font-semibold text-white" id="missing-invoice-number"></div>
                    <div class="text-sm text-gray-300" id="missing-supplier-name"></div>
                </div>

                {{-- Missing Files List --}}
                <div id="missing-files-list" class="space-y-3 mb-6">
                    <!-- Missing files will be inserted here -->
                </div>

                <div class="flex justify-end">
                    <button type="button" id="close-missing-btn"
                            class="bg-gray-600 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded">
                        Close
                    </button>
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
    <script>
        let selectedInvoices = new Map();

        document.addEventListener('DOMContentLoaded', function() {
            const selectAllCheckbox = document.getElementById('select-all');
            const invoiceCheckboxes = document.querySelectorAll('.invoice-checkbox');
            const bulkActionsBar = document.getElementById('bulk-actions-bar');
            const selectionCount = document.getElementById('selection-count');
            const selectionTotal = document.getElementById('selection-total');
            const supplierBreakdown = document.getElementById('supplier-breakdown');
            const supplierList = document.getElementById('supplier-list');
            const markPaidBtn = document.getElementById('mark-paid-btn');
            const clearSelectionBtn = document.getElementById('clear-selection-btn');
            const paymentModal = document.getElementById('payment-modal');
            const bulkPaymentForm = document.getElementById('bulk-payment-form');
            
            // Force clear all checkboxes on page load to prevent browser persistence
            selectedInvoices.clear();
            invoiceCheckboxes.forEach(checkbox => checkbox.checked = false);
            selectAllCheckbox.checked = false;
            bulkActionsBar.classList.add('hidden');
            
            // Handle select all checkbox
            selectAllCheckbox.addEventListener('change', function() {
                invoiceCheckboxes.forEach(checkbox => {
                    checkbox.checked = this.checked;
                    if (this.checked) {
                        addToSelection(checkbox);
                    } else {
                        removeFromSelection(checkbox);
                    }
                });
                updateSelectionDisplay();
            });
            
            // Handle individual checkboxes
            invoiceCheckboxes.forEach(checkbox => {
                checkbox.addEventListener('change', function() {
                    if (this.checked) {
                        addToSelection(this);
                    } else {
                        removeFromSelection(this);
                    }
                    updateSelectionDisplay();
                    updateSelectAllState();
                });
            });
            
            // Clear selection button
            clearSelectionBtn.addEventListener('click', function() {
                selectedInvoices.clear();
                invoiceCheckboxes.forEach(checkbox => checkbox.checked = false);
                selectAllCheckbox.checked = false;
                updateSelectionDisplay();
            });
            
            // Mark as paid button
            markPaidBtn.addEventListener('click', function() {
                if (selectedInvoices.size === 0) return;
                showPaymentModal();
            });
            
            // Modal controls
            document.getElementById('close-modal').addEventListener('click', hidePaymentModal);
            document.getElementById('cancel-payment').addEventListener('click', hidePaymentModal);
            
            // Form submission
            bulkPaymentForm.addEventListener('submit', function(e) {
                e.preventDefault();
                submitBulkPayment();
            });
            
            function addToSelection(checkbox) {
                const invoiceData = {
                    id: checkbox.dataset.invoiceId,
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
                    bulkActionsBar.classList.add('hidden');
                } else {
                    bulkActionsBar.classList.remove('hidden');
                    selectionCount.textContent = `${count} invoice${count !== 1 ? 's' : ''} selected`;
                    selectionTotal.textContent = `Total: €${total.toFixed(2)}`;
                    
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
                
                if (supplierTotals.size > 1) {
                    supplierBreakdown.classList.remove('hidden');
                    
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
                } else {
                    supplierBreakdown.classList.add('hidden');
                }
            }
            
            function updateSelectAllState() {
                const checkedCount = document.querySelectorAll('.invoice-checkbox:checked').length;
                const totalCount = invoiceCheckboxes.length;
                
                selectAllCheckbox.checked = checkedCount === totalCount && totalCount > 0;
                selectAllCheckbox.indeterminate = checkedCount > 0 && checkedCount < totalCount;
            }
            
            function showPaymentModal() {
                // Update modal with selected invoices
                updateModalSupplierBreakdown();
                paymentModal.classList.remove('hidden');
                document.getElementById('payment_date').focus();
            }
            
            function hidePaymentModal() {
                paymentModal.classList.add('hidden');
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
                modalBreakdown.innerHTML = '';
                
                supplierTotals.forEach(supplier => {
                    const div = document.createElement('div');
                    div.className = 'bg-gray-700 rounded p-3';
                    
                    const invoicesList = supplier.invoices
                        .map(inv => inv.invoiceNumber)
                        .join(', ');
                    
                    div.innerHTML = `
                        <div class="flex justify-between items-start">
                            <div>
                                <div class="text-gray-200 font-medium">${supplier.name}</div>
                                <div class="text-gray-400 text-sm">${invoicesList}</div>
                            </div>
                            <div class="text-right">
                                <div class="text-gray-200 font-medium">€${supplier.total.toFixed(2)}</div>
                                <div class="text-gray-400 text-sm">${supplier.count} invoice${supplier.count !== 1 ? 's' : ''}</div>
                            </div>
                        </div>
                    `;
                    modalBreakdown.appendChild(div);
                });
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
                        selectAllCheckbox.checked = false;
                        updateSelectionDisplay();
                        window.location.href = window.location.href; // Clean refresh to show updated statuses
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

        // Function to copy filename to clipboard
        function copyFilename(filename, button) {
            // Try modern clipboard API first, fallback to execCommand
            function doCopy() {
                if (navigator.clipboard && window.isSecureContext) {
                    return navigator.clipboard.writeText(filename);
                } else {
                    // Fallback for older browsers or non-HTTPS
                    const textArea = document.createElement('textarea');
                    textArea.value = filename;
                    textArea.style.position = 'fixed';
                    textArea.style.left = '-999999px';
                    textArea.style.top = '-999999px';
                    document.body.appendChild(textArea);
                    textArea.focus();
                    textArea.select();
                    return new Promise((resolve, reject) => {
                        document.execCommand('copy') ? resolve() : reject();
                        textArea.remove();
                    });
                }
            }

            doCopy().then(() => {
                // Show success feedback
                const originalHtml = button.innerHTML;
                button.innerHTML = `
                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M21,7L9,19L3.5,13.5L4.91,12.09L9,16.17L19.59,5.59L21,7Z" />
                    </svg>
                `;
                button.classList.remove('text-gray-400');
                button.classList.add('text-green-400');

                // Reset after 1.5 seconds
                setTimeout(() => {
                    button.innerHTML = originalHtml;
                    button.classList.remove('text-green-400');
                    button.classList.add('text-gray-400');
                }, 1500);
            }).catch(err => {
                alert('Failed to copy: ' + filename);
            });
        }

        // Function to view invoice attachment in new window
        function viewInvoiceAttachment(invoiceId) {
            // Fetch attachment info for this invoice
            fetch(`/invoices/${invoiceId}/attachments`)
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.attachments && data.attachments.length > 0) {
                        // Find primary attachment or use first one
                        let attachmentToView = data.attachments.find(att => att.is_primary) || data.attachments[0];

                        // Open attachment viewer in new window
                        const viewerUrl = attachmentToView.viewer_url;
                        const windowName = `invoice_attachment_${invoiceId}_${attachmentToView.id}`;
                        const windowFeatures = 'width=1200,height=800,scrollbars=yes,resizable=yes,toolbar=no,menubar=no,location=no,status=no';

                        window.open(viewerUrl, windowName, windowFeatures);
                    } else {
                        alert('No attachments found for this invoice.');
                    }
                })
                .catch(error => {
                    console.error('Error loading attachments:', error);
                    alert('Failed to load attachments.');
                });
        }

        // Function to show missing files modal
        function showMissingFilesModal(invoiceId) {
            const modal = document.getElementById('missing-files-modal');
            const filesList = document.getElementById('missing-files-list');
            const invoiceNumber = document.getElementById('missing-invoice-number');
            const supplierName = document.getElementById('missing-supplier-name');

            // Show loading state
            filesList.innerHTML = '<div class="text-center text-gray-400 py-4">Loading...</div>';
            modal.classList.remove('hidden');

            // Fetch missing attachment info
            fetch(`/invoices/${invoiceId}/attachments/missing`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        invoiceNumber.textContent = '#' + data.invoice_number;
                        supplierName.textContent = data.supplier_name;

                        if (data.missing_attachments.length === 0) {
                            filesList.innerHTML = '<div class="text-center text-green-400 py-4">All files are now available!</div>';
                            return;
                        }

                        filesList.innerHTML = '';
                        data.missing_attachments.forEach(attachment => {
                            const div = document.createElement('div');
                            div.className = 'bg-gray-700 rounded p-4';
                            div.id = `missing-file-${attachment.id}`;
                            div.innerHTML = `
                                <div class="flex items-start justify-between mb-3">
                                    <div class="flex-1">
                                        <div class="flex items-center">
                                            <svg class="w-5 h-5 text-red-400 mr-2 flex-shrink-0" fill="currentColor" viewBox="0 0 24 24">
                                                <path d="M14,2H6A2,2 0 0,0 4,4V20A2,2 0 0,0 6,22H18A2,2 0 0,0 20,20V8L14,2M18,20H6V4H13V9H18V20Z" />
                                            </svg>
                                            <span class="text-white font-medium break-all">${attachment.original_filename}</span>
                                            <button type="button"
                                                    class="copy-filename-btn ml-2 p-1 text-gray-400 hover:text-blue-400 hover:bg-gray-600 rounded transition-colors flex-shrink-0"
                                                    data-filename="${attachment.original_filename.replace(/"/g, '&quot;')}"
                                                    title="Copy filename">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
                                                </svg>
                                            </button>
                                        </div>
                                        <div class="text-sm text-gray-400 mt-1 ml-7">
                                            ${attachment.attachment_type_label} &bull; ${attachment.formatted_file_size}
                                            ${attachment.is_primary ? '<span class="text-yellow-400 ml-2">(Primary)</span>' : ''}
                                        </div>
                                        <div class="text-xs text-gray-500 mt-1 ml-7">
                                            Uploaded: ${attachment.uploaded_at || 'Unknown'}
                                        </div>
                                    </div>
                                </div>
                                <div class="ml-7">
                                    <label class="block">
                                        <span class="text-sm text-gray-300 mb-1 block">Upload replacement file:</span>
                                        <input type="file"
                                               id="file-input-${attachment.id}"
                                               data-attachment-id="${attachment.id}"
                                               accept=".pdf,.jpg,.jpeg,.png,.gif,.webp,.txt,.doc,.docx,.xls,.xlsx"
                                               class="block w-full text-sm text-gray-400
                                                      file:mr-4 file:py-2 file:px-4
                                                      file:rounded file:border-0
                                                      file:text-sm file:font-semibold
                                                      file:bg-blue-600 file:text-white
                                                      hover:file:bg-blue-700
                                                      cursor-pointer">
                                    </label>
                                    <div id="upload-status-${attachment.id}" class="mt-2 text-sm hidden"></div>
                                </div>
                            `;
                            filesList.appendChild(div);

                            // Add change listener to file input
                            const fileInput = div.querySelector(`#file-input-${attachment.id}`);
                            fileInput.addEventListener('change', function(e) {
                                if (e.target.files.length > 0) {
                                    uploadReplacementFile(attachment.id, e.target.files[0], invoiceId);
                                }
                            });

                            // Add click listener to copy button
                            const copyBtn = div.querySelector('.copy-filename-btn');
                            if (copyBtn) {
                                copyBtn.addEventListener('click', function(e) {
                                    e.preventDefault();
                                    e.stopPropagation();
                                    copyFilename(this.dataset.filename, this);
                                });
                            }
                        });
                    } else {
                        filesList.innerHTML = '<div class="text-center text-red-400 py-4">Failed to load missing files.</div>';
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    filesList.innerHTML = '<div class="text-center text-red-400 py-4">Error loading missing files.</div>';
                });
        }

        // Function to upload replacement file
        function uploadReplacementFile(attachmentId, file, invoiceId, skipValidation = false) {
            const statusDiv = document.getElementById(`upload-status-${attachmentId}`);
            const fileInput = document.getElementById(`file-input-${attachmentId}`);
            const fileRow = document.getElementById(`missing-file-${attachmentId}`);

            // Show uploading/validating status
            statusDiv.classList.remove('hidden', 'text-green-400', 'text-red-400', 'text-yellow-400');
            statusDiv.classList.add('text-blue-400');
            statusDiv.innerHTML = `
                <svg class="w-4 h-4 inline-block animate-spin mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                </svg>
                ${skipValidation ? 'Uploading' : 'Validating'} ${file.name}...
            `;
            fileInput.disabled = true;

            const formData = new FormData();
            formData.append('file', file);
            if (skipValidation) {
                formData.append('skip_validation', '1');
            }

            fetch(`/invoice-attachments/${attachmentId}/replace`, {
                method: 'POST',
                body: formData,
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    statusDiv.classList.remove('text-blue-400');
                    statusDiv.classList.add('text-green-400');

                    // Build success message with validation matches
                    let successHtml = `
                        <svg class="w-4 h-4 inline-block mr-2" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M21,7L9,19L3.5,13.5L4.91,12.09L9,16.17L19.59,5.59L21,7Z" />
                        </svg>
                        File uploaded successfully!
                    `;

                    // Show validation matches if any
                    if (data.validation_matches && data.validation_matches.length > 0) {
                        successHtml += `<div class="mt-2 text-sm">`;
                        data.validation_matches.forEach(m => {
                            successHtml += `<div class="text-green-300">${m.field}: ${m.found} ✓</div>`;
                        });
                        successHtml += `</div>`;
                    }

                    statusDiv.innerHTML = successHtml;

                    // Update the row to show success state
                    setTimeout(() => {
                        fileRow.classList.add('bg-green-900/30', 'border', 'border-green-600');
                        fileRow.classList.remove('bg-gray-700');
                    }, 500);

                    // Refresh page to update the invoice list
                    window.location.reload();
                } else if (data.needs_confirmation) {
                    // Show validation mismatch warning
                    showValidationWarning(attachmentId, file, invoiceId, data.validation, statusDiv, fileInput);
                } else {
                    statusDiv.classList.remove('text-blue-400');
                    statusDiv.classList.add('text-red-400');
                    statusDiv.textContent = data.message || 'Upload failed';
                    fileInput.disabled = false;
                }
            })
            .catch(error => {
                console.error('Error:', error);
                statusDiv.classList.remove('text-blue-400');
                statusDiv.classList.add('text-red-400');
                statusDiv.textContent = 'Upload failed. Please try again.';
                fileInput.disabled = false;
            });
        }

        // Function to show validation warning and confirmation
        function showValidationWarning(attachmentId, file, invoiceId, validation, statusDiv, fileInput) {
            statusDiv.classList.remove('text-blue-400');
            statusDiv.classList.add('text-yellow-400');

            let html = `
                <div class="bg-yellow-900/50 border border-yellow-600 rounded p-3 mt-2">
                    <div class="flex items-center mb-2">
                        <svg class="w-5 h-5 mr-2 text-yellow-400" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M13,13H11V7H13M13,17H11V15H13M12,2A10,10 0 0,0 2,12A10,10 0 0,0 12,22A10,10 0 0,0 22,12A10,10 0 0,0 12,2Z" />
                        </svg>
                        <span class="font-semibold text-yellow-300">File Validation Warning</span>
                    </div>
            `;

            // Show mismatches
            if (validation.mismatches && validation.mismatches.length > 0) {
                html += `<div class="text-sm mb-2">`;
                validation.mismatches.forEach(m => {
                    const severityColor = m.severity === 'high' ? 'text-red-400' : (m.severity === 'medium' ? 'text-yellow-400' : 'text-gray-400');
                    html += `
                        <div class="flex justify-between py-1 border-b border-yellow-700/50">
                            <span class="text-gray-300">${m.field}:</span>
                            <div class="text-right">
                                <span class="text-gray-400">Expected:</span> <span class="text-white">${m.expected}</span><br>
                                <span class="text-gray-400">Found:</span> <span class="${severityColor}">${m.found}</span>
                            </div>
                        </div>
                    `;
                });
                html += `</div>`;
            }

            // Show matches
            if (validation.matches && validation.matches.length > 0) {
                html += `<div class="text-sm text-green-400 mb-2">`;
                validation.matches.forEach(m => {
                    html += `<div class="py-0.5"><span class="text-gray-400">${m.field}:</span> ${m.found} ✓</div>`;
                });
                html += `</div>`;
            }

            html += `
                    <div class="flex space-x-2 mt-3">
                        <button type="button" onclick="confirmUpload(${attachmentId}, '${invoiceId}')"
                                class="bg-yellow-600 hover:bg-yellow-700 text-white text-sm px-3 py-1 rounded">
                            Upload Anyway
                        </button>
                        <button type="button" onclick="cancelUpload(${attachmentId})"
                                class="bg-gray-600 hover:bg-gray-700 text-white text-sm px-3 py-1 rounded">
                            Cancel
                        </button>
                    </div>
                </div>
            `;

            statusDiv.innerHTML = html;

            // Store file reference for later use
            statusDiv.dataset.pendingFile = file.name;
            window.pendingFiles = window.pendingFiles || {};
            window.pendingFiles[attachmentId] = file;
        }

        // Function to confirm upload despite validation warning
        function confirmUpload(attachmentId, invoiceId) {
            const file = window.pendingFiles[attachmentId];
            if (file) {
                uploadReplacementFile(attachmentId, file, invoiceId, true);
            }
        }

        // Function to cancel upload
        function cancelUpload(attachmentId) {
            const statusDiv = document.getElementById(`upload-status-${attachmentId}`);
            const fileInput = document.getElementById(`file-input-${attachmentId}`);

            statusDiv.classList.add('hidden');
            statusDiv.innerHTML = '';
            fileInput.disabled = false;
            fileInput.value = '';

            // Clean up pending file
            if (window.pendingFiles) {
                delete window.pendingFiles[attachmentId];
            }
        }

        // Close missing files modal handlers
        document.getElementById('close-missing-modal').addEventListener('click', function() {
            document.getElementById('missing-files-modal').classList.add('hidden');
        });
        document.getElementById('close-missing-btn').addEventListener('click', function() {
            document.getElementById('missing-files-modal').classList.add('hidden');
        });

        // Close modal on backdrop click
        document.getElementById('missing-files-modal').addEventListener('click', function(e) {
            if (e.target === this) {
                this.classList.add('hidden');
            }
        });
    </script>
    @endpush
</x-admin-layout>