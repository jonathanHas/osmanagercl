<x-admin-layout>
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
        {{-- Header --}}
        <div class="flex justify-between items-center mb-6">
            <h2 class="text-2xl font-bold text-gray-100">RTD Management</h2>
            <div class="flex space-x-2">
                <a href="{{ route('rtd.suppliers') }}"
                   class="bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-4 rounded inline-flex items-center">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                    </svg>
                    Classify Suppliers
                </a>
                <a href="{{ route('rtd.submissions.index') }}"
                   class="bg-teal-600 hover:bg-teal-700 text-white font-bold py-2 px-4 rounded inline-flex items-center">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    Submissions
                </a>
                <a href="{{ route('rtd.year-report') }}"
                   class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded inline-flex items-center">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                    Year Report
                </a>
                <a href="{{ route('rtd.issues') }}"
                   class="bg-yellow-600 hover:bg-yellow-700 text-white font-bold py-2 px-4 rounded inline-flex items-center">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    Issues Queue
                </a>
                <a href="{{ route('rtd-fallbacks.index') }}"
                   class="bg-purple-600 hover:bg-purple-700 text-white font-bold py-2 px-4 rounded inline-flex items-center">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"/>
                    </svg>
                    Fallbacks
                </a>
                <a href="{{ route('rtd-fallbacks.unresolved') }}"
                   class="bg-red-600 hover:bg-red-700 text-white font-bold py-2 px-4 rounded inline-flex items-center">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                    </svg>
                    Unresolved
                </a>
            </div>
        </div>

        {{-- Flash Messages --}}
        @if(session('success'))
            <div class="bg-green-900/50 border border-green-500 text-green-300 px-4 py-3 rounded mb-4">
                {{ session('success') }}
            </div>
        @endif
        @if(session('error'))
            <div class="bg-red-900/50 border border-red-500 text-red-300 px-4 py-3 rounded mb-4">
                {{ session('error') }}
            </div>
        @endif

        {{-- Stats Cards --}}
        @php
            $cardParams = array_filter(['search' => $search, 'supplier_type' => $supplierType !== 'all' ? $supplierType : null]);
            $allStatuses = ['needs_parsing', 'needs_computation', 'has_issues', 'computed', 'frozen'];
            if (($stats['pdf_missing'] ?? 0) > 0) {
                array_splice($allStatuses, 1, 0, ['pdf_missing']);
            }
        @endphp
        <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-7 gap-4 mb-6">
            <a href="{{ route('rtd.index', $cardParams) }}"
               class="bg-gray-800 rounded-lg p-4 hover:bg-gray-700 transition {{ empty($filters) ? 'ring-2 ring-blue-500' : '' }}">
                <div class="text-3xl font-bold text-white">{{ $stats['total'] }}</div>
                <div class="text-sm text-gray-400">Total Invoices</div>
            </a>
            @php
                // Toggle helper: if status is active remove it, otherwise add it
                function toggleFilter($status, $currentFilters, $baseParams) {
                    $newFilters = in_array($status, $currentFilters)
                        ? array_values(array_diff($currentFilters, [$status]))
                        : array_merge($currentFilters, [$status]);
                    return array_merge($baseParams, empty($newFilters) ? [] : ['filter' => $newFilters]);
                }
            @endphp
            <a href="{{ route('rtd.index', toggleFilter('needs_parsing', $filters, $cardParams)) }}"
               class="bg-gray-800 rounded-lg p-4 hover:bg-gray-700 transition {{ in_array('needs_parsing', $filters) ? 'ring-2 ring-red-500' : '' }}">
                <div class="text-3xl font-bold text-red-400">{{ $stats['needs_parsing'] }}</div>
                <div class="text-sm text-gray-400">Needs Parsing</div>
            </a>
            @if(($stats['pdf_missing'] ?? 0) > 0)
            <a href="{{ route('rtd.index', toggleFilter('pdf_missing', $filters, $cardParams)) }}"
               class="bg-gray-800 rounded-lg p-4 hover:bg-gray-700 transition {{ in_array('pdf_missing', $filters) ? 'ring-2 ring-gray-500' : '' }}">
                <div class="text-3xl font-bold text-gray-400">{{ $stats['pdf_missing'] }}</div>
                <div class="text-sm text-gray-400">PDF Missing</div>
            </a>
            @endif
            <a href="{{ route('rtd.index', toggleFilter('needs_computation', $filters, $cardParams)) }}"
               class="bg-gray-800 rounded-lg p-4 hover:bg-gray-700 transition {{ in_array('needs_computation', $filters) ? 'ring-2 ring-orange-500' : '' }}">
                <div class="text-3xl font-bold text-orange-400">{{ $stats['needs_computation'] }}</div>
                <div class="text-sm text-gray-400">Needs Compute</div>
            </a>
            <a href="{{ route('rtd.index', toggleFilter('has_issues', $filters, $cardParams)) }}"
               class="bg-gray-800 rounded-lg p-4 hover:bg-gray-700 transition {{ in_array('has_issues', $filters) ? 'ring-2 ring-yellow-500' : '' }}">
                <div class="text-3xl font-bold text-yellow-400">{{ $stats['has_issues'] }}</div>
                <div class="text-sm text-gray-400">Has Issues</div>
            </a>
            <a href="{{ route('rtd.index', toggleFilter('computed', $filters, $cardParams)) }}"
               class="bg-gray-800 rounded-lg p-4 hover:bg-gray-700 transition {{ in_array('computed', $filters) ? 'ring-2 ring-blue-500' : '' }}">
                <div class="text-3xl font-bold text-blue-400">{{ $stats['computed'] }}</div>
                <div class="text-sm text-gray-400">Computed</div>
            </a>
            <a href="{{ route('rtd.index', toggleFilter('frozen', $filters, $cardParams)) }}"
               class="bg-gray-800 rounded-lg p-4 hover:bg-gray-700 transition {{ in_array('frozen', $filters) ? 'ring-2 ring-green-500' : '' }}">
                <div class="text-3xl font-bold text-green-400">{{ $stats['frozen'] }}</div>
                <div class="text-sm text-gray-400">Frozen</div>
            </a>
        </div>

        {{-- Force Reparse Mode Banner --}}
        <div id="force-reparse-banner" class="hidden bg-orange-900/50 border border-orange-500 text-orange-300 px-4 py-3 rounded mb-4">
            <div class="flex items-center justify-between">
                <div class="flex items-center">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                    </svg>
                    <span class="font-semibold">Force Reparse Mode Active</span>
                    <span class="ml-2 text-sm">- Click "Reparse" on any invoice to re-parse with the latest RTD parser</span>
                </div>
                <button onclick="toggleForceReparseMode()" class="text-orange-300 hover:text-white">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
        </div>

        {{-- Filters and Actions --}}
        <div class="bg-gray-800 rounded-lg p-4 mb-4 flex flex-wrap items-center justify-between gap-4">
            <form action="{{ route('rtd.index') }}" method="GET" class="flex items-center gap-4">
                <div class="relative">
                    <input type="text" name="search" value="{{ $search }}" placeholder="Search invoice #..."
                           class="bg-gray-700 text-white rounded-lg pl-10 pr-4 py-2 w-64 focus:ring-2 focus:ring-blue-500 focus:outline-none">
                    <svg class="w-5 h-5 text-gray-400 absolute left-3 top-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                    </svg>
                </div>
                {{-- Status Filter (multi-select checkboxes) --}}
                <div class="relative" x-data="{ open: false }" @click.away="open = false">
                    <button type="button" @click="open = !open"
                            class="bg-gray-700 text-white rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:outline-none inline-flex items-center gap-2 min-w-[160px]">
                        <span class="truncate">
                            @if(empty($filters))
                                All Statuses
                            @else
                                {{ count($filters) }} status{{ count($filters) > 1 ? 'es' : '' }} selected
                            @endif
                        </span>
                        <svg class="w-4 h-4 flex-shrink-0 transition-transform" :class="open && 'rotate-180'" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </button>
                    <div x-show="open" x-transition
                         class="absolute left-0 mt-1 w-64 rounded-lg bg-gray-700 shadow-lg ring-1 ring-black ring-opacity-5 z-50 py-2">
                        @php
                            $statusOptions = [
                                'needs_parsing' => ['label' => 'Needs Parsing', 'count' => $stats['needs_parsing'], 'color' => 'text-red-400'],
                                'needs_computation' => ['label' => 'Needs Computation', 'count' => $stats['needs_computation'], 'color' => 'text-orange-400'],
                                'has_issues' => ['label' => 'Has Issues', 'count' => $stats['has_issues'], 'color' => 'text-yellow-400'],
                                'computed' => ['label' => 'Computed', 'count' => $stats['computed'], 'color' => 'text-blue-400'],
                                'frozen' => ['label' => 'Frozen', 'count' => $stats['frozen'], 'color' => 'text-green-400'],
                            ];
                            if (($stats['pdf_missing'] ?? 0) > 0) {
                                $statusOptions = array_merge(
                                    array_slice($statusOptions, 0, 1),
                                    ['pdf_missing' => ['label' => 'PDF Missing', 'count' => $stats['pdf_missing'], 'color' => 'text-gray-400']],
                                    array_slice($statusOptions, 1)
                                );
                            }
                        @endphp
                        @foreach($statusOptions as $value => $opt)
                            <label class="flex items-center px-3 py-1.5 hover:bg-gray-600 cursor-pointer">
                                <input type="checkbox" name="filter[]" value="{{ $value }}"
                                       {{ in_array($value, $filters) ? 'checked' : '' }}
                                       class="form-checkbox h-4 w-4 rounded border-gray-500 bg-gray-600 text-blue-500 focus:ring-blue-500 focus:ring-offset-0">
                                <span class="ml-2 text-sm text-white">{{ $opt['label'] }}</span>
                                <span class="ml-auto text-xs {{ $opt['color'] }}">{{ $opt['count'] }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>
                {{-- Supplier Type Filter --}}
                <select name="supplier_type" class="bg-gray-700 text-white rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:outline-none">
                    <option value="all" {{ ($supplierType ?? 'all') === 'all' ? 'selected' : '' }}>All Types</option>
                    <option value="parser" {{ ($supplierType ?? '') === 'parser' ? 'selected' : '' }}>Parser (Udea, etc)</option>
                    <option value="simple" {{ ($supplierType ?? '') === 'simple' ? 'selected' : '' }}>Simple VAT</option>
                    <option value="service" {{ ($supplierType ?? '') === 'service' ? 'selected' : '' }}>Service/Overhead</option>
                </select>
                <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg">
                    Filter
                </button>
                @if($search || ($supplierType ?? 'all') !== 'all' || !empty($filters))
                    <a href="{{ route('rtd.index') }}" class="text-gray-400 hover:text-white">
                        Clear
                    </a>
                @endif
            </form>

            <div class="flex items-center gap-2">
                {{-- Settings Dropdown --}}
                <div class="relative" x-data="{ open: false }">
                    <button @click="open = !open" type="button"
                            class="bg-gray-700 hover:bg-gray-600 text-white px-3 py-2 rounded-lg inline-flex items-center"
                            title="Settings">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                        </svg>
                    </button>
                    <div x-show="open" @click.away="open = false"
                         x-transition:enter="transition ease-out duration-100"
                         x-transition:enter-start="transform opacity-0 scale-95"
                         x-transition:enter-end="transform opacity-100 scale-100"
                         x-transition:leave="transition ease-in duration-75"
                         x-transition:leave-start="transform opacity-100 scale-100"
                         x-transition:leave-end="transform opacity-0 scale-95"
                         class="absolute right-0 mt-2 w-64 rounded-lg bg-gray-700 shadow-lg ring-1 ring-black ring-opacity-5 z-50">
                        <div class="p-4">
                            <label class="flex items-center cursor-pointer">
                                <input type="checkbox" id="force-reparse-toggle"
                                       onchange="toggleForceReparseMode()"
                                       class="form-checkbox h-5 w-5 text-orange-500 rounded border-gray-500 bg-gray-600 focus:ring-orange-500">
                                <span class="ml-3 text-white text-sm">Force Reparse Mode</span>
                            </label>
                            <p class="mt-2 text-xs text-gray-400">
                                When enabled, shows a "Reparse" button on all invoices to re-parse with the latest RTD parser.
                            </p>
                        </div>
                    </div>
                </div>

                <button type="button" id="freeze-all-btn" onclick="freezeAllBalanced()"
                        class="bg-green-600 hover:bg-green-700 text-white text-xs px-3 py-1.5 rounded inline-flex items-center hidden">
                    <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                    </svg>
                    <span id="freeze-all-text">Freeze All (<span id="freeze-all-count">0</span>)</span>
                </button>
                <button type="button" id="compute-all-btn" onclick="computeAllPending()"
                        class="bg-orange-600 hover:bg-orange-700 text-white text-xs px-3 py-1.5 rounded inline-flex items-center hidden">
                    <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                    </svg>
                    <span id="compute-all-text">Compute All (<span id="compute-all-count">0</span>)</span>
                </button>
                <form action="{{ route('rtd.recompute-all') }}" method="POST">
                    @csrf
                    <button type="submit" class="bg-purple-600 hover:bg-purple-700 text-white text-xs px-3 py-1.5 rounded inline-flex items-center">
                        <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                        </svg>
                        Recompute All
                    </button>
                </form>
            </div>
        </div>

        {{-- Invoice Table --}}
        <div class="bg-gray-800 rounded-lg overflow-hidden">
            @if($invoices->isEmpty())
                <div class="p-8 text-center text-gray-400">
                    <svg class="w-12 h-12 mx-auto mb-4 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                    <p>No invoices found{{ !empty($filters) ? ' matching this filter' : '' }}.</p>
                </div>
            @else
                <table class="min-w-full divide-y divide-gray-700">
                    <thead class="bg-gray-900">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Invoice</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Date</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-400 uppercase tracking-wider">Total</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Breakdown</th>
                            <th class="px-4 py-3 text-center text-xs font-medium text-gray-400 uppercase tracking-wider">Status</th>
                            <th class="px-4 py-3 text-center text-xs font-medium text-gray-400 uppercase tracking-wider">Issues</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-400 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-700">
                        @foreach($invoices as $invoice)
                            @php
                                // Pre-compute balance for row data attribute and display
                                $isBalancedRow = false;
                                if ($invoice->hasRtdData()) {
                                    $gfrRow = $invoice->rtd_breakdown['goods_for_resale'] ?? [];
                                    $exclRow = $invoice->rtd_breakdown['excluded'] ?? [];
                                    $unresRow = $invoice->rtd_breakdown['unresolved'] ?? [];
                                    $statsRow = $invoice->rtd_breakdown['stats'] ?? [];
                                    $isServiceRow = ($statsRow['is_service'] ?? false) || ($invoice->supplier && $invoice->supplier->rtd_classification === 'service_overhead');
                                    $serviceOhRow = (float) ($exclRow['service_overhead'] ?? 0);
                                    $rtdTotalRow = $isServiceRow ? 0 : $invoice->getRtdTotal();
                                    $exclTotalRow = ($exclRow['freight'] ?? 0) + ($exclRow['deposits'] ?? 0) + ($exclRow['drs'] ?? 0) + ($exclRow['vat'] ?? 0) + $serviceOhRow;
                                    $unrTotalRow = $unresRow['net_total'] ?? 0;
                                    $calcTotalRow = $rtdTotalRow + $exclTotalRow + $unrTotalRow;
                                    $diffRow = abs($calcTotalRow - $invoice->total_amount);
                                    $isBalancedRow = $diffRow < 0.50;
                                }
                            @endphp
                            <tr class="hover:bg-gray-750 cursor-pointer"
                                onclick="toggleExpand({{ $invoice->id }})"
                                id="row-{{ $invoice->id }}"
                                data-status="{{ $invoice->rtd_display_status }}"
                                data-balanced="{{ $isBalancedRow ? 'true' : 'false' }}">
                                <td class="px-4 py-3">
                                    <div class="flex items-center">
                                        <svg class="w-4 h-4 mr-2 text-gray-500 transition-transform" id="chevron-{{ $invoice->id }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                        </svg>
                                        <div>
                                            <div class="text-white font-medium flex items-center gap-2">
                                                #{{ $invoice->invoice_number }}
                                                @if($invoice->supplier && $invoice->supplier->rtd_classification === 'service_overhead')
                                                    <span class="px-1.5 py-0.5 text-xs font-semibold rounded bg-yellow-900 text-yellow-300">Service</span>
                                                @elseif($invoice->supplier && $invoice->supplier->rtd_classification === 'goods_simple')
                                                    <span class="px-1.5 py-0.5 text-xs font-semibold rounded bg-purple-900 text-purple-300">Simple</span>
                                                @endif
                                            </div>
                                            <div class="text-xs text-gray-400">{{ $invoice->supplier_name }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-gray-300">
                                    {{ $invoice->invoice_date->format('d/m/Y') }}
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <div class="flex items-center justify-end gap-1.5">
                                        <span class="text-gray-300">{{ number_format($invoice->total_amount, 2) }}</span>
                                        @if($invoice->hasRtdData())
                                            {{-- $isBalancedRow pre-computed above the <tr> --}}
                                            @if($isBalancedRow)
                                                <svg class="w-4 h-4 text-green-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                                                </svg>
                                            @else
                                                <span class="text-red-400 text-xs font-mono flex-shrink-0">{{ number_format($diffRow, 2) }}</span>
                                            @endif
                                        @else
                                            <span class="text-gray-600 text-xs">-</span>
                                        @endif
                                    </div>
                                </td>
                                {{-- Breakdown column: G / E / U --}}
                                <td class="px-4 py-3">
                                    @if($invoice->hasRtdData())
                                        <div class="text-xs font-mono space-y-0.5 whitespace-nowrap">
                                            <div class="flex justify-between gap-2">
                                                <span class="text-green-400">G:</span>
                                                <span class="text-green-400">{{ number_format($rtdTotalRow, 2) }}</span>
                                            </div>
                                            <div class="flex justify-between gap-2">
                                                <span class="text-blue-400">E:</span>
                                                <span class="text-blue-400">{{ number_format($exclTotalRow, 2) }}</span>
                                            </div>
                                            <div class="flex justify-between gap-2">
                                                <span class="{{ $unrTotalRow > 0 ? 'text-red-400' : 'text-gray-500' }}">U:</span>
                                                <span class="{{ $unrTotalRow > 0 ? 'text-red-400' : 'text-gray-500' }}">{{ number_format($unrTotalRow, 2) }}</span>
                                            </div>
                                        </div>
                                    @else
                                        <span class="text-gray-600 text-xs">-</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-center">
                                    @switch($invoice->rtd_display_status)
                                        @case('frozen')
                                            <span class="px-2 py-1 text-xs font-semibold rounded-full bg-green-900 text-green-300">Frozen</span>
                                            @break
                                        @case('computed')
                                            <span class="px-2 py-1 text-xs font-semibold rounded-full bg-blue-900 text-blue-300">Computed</span>
                                            @break
                                        @case('has_issues')
                                            <span class="px-2 py-1 text-xs font-semibold rounded-full bg-yellow-900 text-yellow-300">Has Issues</span>
                                            @break
                                        @case('needs_computation')
                                            <span class="px-2 py-1 text-xs font-semibold rounded-full bg-orange-900 text-orange-300">Needs Compute</span>
                                            @break
                                        @case('needs_parsing')
                                            <span class="px-2 py-1 text-xs font-semibold rounded-full bg-red-900 text-red-300">Needs Parsing</span>
                                            @break
                                        @case('pdf_missing')
                                            <span class="px-2 py-1 text-xs font-semibold rounded-full bg-gray-700 text-gray-300">PDF Missing</span>
                                            @break
                                    @endswitch
                                </td>
                                <td class="px-4 py-3 text-center" onclick="event.stopPropagation()">
                                    @if($invoice->hasRtdData())
                                        @php $unresolvedCount = $invoice->rtd_breakdown['unresolved']['count'] ?? 0; @endphp
                                        @if($unresolvedCount > 0)
                                            <a href="{{ route('rtd-fallbacks.unresolved', ['invoice_id' => $invoice->id]) }}"
                                               class="px-2 py-1 text-xs font-semibold rounded-full bg-red-900 text-red-300 hover:bg-red-800 transition"
                                               title="Resolve unresolved items">{{ $unresolvedCount }}</a>
                                        @else
                                            <span class="text-green-400">0</span>
                                        @endif
                                    @else
                                        <span class="text-gray-500">-</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-right" onclick="event.stopPropagation()">
                                    <div class="flex justify-end space-x-2 items-center" id="actions-{{ $invoice->id }}" data-has-pdf="{{ $invoice->hasPdfOnDisk() ? 'true' : 'false' }}" data-is-frozen="{{ $invoice->rtd_status === 'frozen' ? 'true' : 'false' }}">
                                        {{-- Loading indicator --}}
                                        <span id="loading-{{ $invoice->id }}" class="hidden items-center text-blue-400 text-xs">
                                            <svg class="animate-spin h-4 w-4 mr-1" fill="none" viewBox="0 0 24 24">
                                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                            </svg>
                                            <span id="loading-text-{{ $invoice->id }}">Processing...</span>
                                        </span>
                                        {{-- Force Reparse button (hidden by default, shown in force mode) --}}
                                        @if($invoice->hasPdfOnDisk())
                                            <button type="button" onclick="rtdAction({{ $invoice->id }}, 'force-parse', 'Reparsing')"
                                                    class="force-reparse-btn hidden bg-orange-600 hover:bg-orange-700 text-white text-xs px-3 py-1 rounded"
                                                    title="Force reparse with latest RTD parser">
                                                Reparse
                                            </button>
                                        @endif
                                        {{-- Action buttons --}}
                                        <span id="buttons-{{ $invoice->id }}">
                                        @if($invoice->rtd_display_status === 'needs_parsing')
                                            <button type="button" onclick="rtdAction({{ $invoice->id }}, 'parse', 'Parsing')"
                                                    class="bg-red-600 hover:bg-red-700 text-white text-xs px-3 py-1 rounded">
                                                Parse
                                            </button>
                                            <button type="button" onclick="openManualEdit({{ $invoice->id }}, {{ json_encode($invoice->total_amount) }})"
                                                    class="bg-yellow-600 hover:bg-yellow-700 text-white text-xs px-3 py-1 rounded"
                                                    title="Manually enter RTD breakdown">
                                                Manual
                                            </button>
                                        @elseif($invoice->rtd_display_status === 'needs_computation')
                                            <button type="button" onclick="rtdAction({{ $invoice->id }}, 'compute', 'Computing')"
                                                    class="bg-orange-600 hover:bg-orange-700 text-white text-xs px-3 py-1 rounded">
                                                Compute
                                            </button>
                                        @elseif($invoice->rtd_display_status === 'has_issues')
                                            <button type="button" onclick="rtdAction({{ $invoice->id }}, 'compute', 'Recomputing')"
                                                    class="bg-blue-600 hover:bg-blue-700 text-white text-xs px-3 py-1 rounded">
                                                Recompute
                                            </button>
                                            <button type="button" onclick="openManualEdit({{ $invoice->id }}, {{ json_encode($invoice->total_amount) }})"
                                                    class="bg-yellow-600 hover:bg-yellow-700 text-white text-xs px-3 py-1 rounded"
                                                    title="Manually edit RTD breakdown">
                                                Edit
                                            </button>
                                        @elseif($invoice->rtd_display_status === 'computed')
                                            <button type="button" onclick="rtdAction({{ $invoice->id }}, 'compute', 'Recomputing')"
                                                    class="bg-blue-600 hover:bg-blue-700 text-white text-xs px-3 py-1 rounded">
                                                Recompute
                                            </button>
                                            <button type="button" onclick="openManualEdit({{ $invoice->id }}, {{ json_encode($invoice->total_amount) }})"
                                                    class="bg-yellow-600 hover:bg-yellow-700 text-white text-xs px-3 py-1 rounded"
                                                    title="Manually edit RTD breakdown">
                                                Edit
                                            </button>
                                            <button type="button" onclick="rtdAction({{ $invoice->id }}, 'accept', 'Freezing')"
                                                    class="bg-green-600 hover:bg-green-700 text-white text-xs px-3 py-1 rounded">
                                                Freeze
                                            </button>
                                        @elseif($invoice->rtd_display_status === 'pdf_missing')
                                            <span class="text-gray-400 text-xs">No PDF file</span>
                                        @endif
                                        </span>
                                        @if($invoice->hasPdfOnDisk())
                                            <button type="button"
                                                    onclick="viewInvoicePdf({{ $invoice->id }})"
                                                    class="bg-purple-600 hover:bg-purple-700 text-white text-xs px-3 py-1 rounded inline-flex items-center"
                                                    title="View PDF">
                                                <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 24 24">
                                                    <path d="M14,2H6A2,2 0 0,0 4,4V20A2,2 0 0,0 6,22H18A2,2 0 0,0 20,20V8L14,2M18,20H6V4H13V9H18V20Z" />
                                                </svg>
                                                PDF
                                            </button>
                                        @endif
                                        <a href="{{ route('invoices.show', $invoice) }}"
                                           class="bg-gray-600 hover:bg-gray-500 text-white text-xs px-3 py-1 rounded">
                                            View
                                        </a>
                                    </div>
                                </td>
                            </tr>
                            {{-- Expandable Detail Row --}}
                            <tr id="detail-{{ $invoice->id }}" class="hidden bg-gray-850"
                                data-breakdown="{{ json_encode($invoice->rtd_breakdown ?? []) }}"
                                data-invoice-total="{{ $invoice->total_amount }}"
                            >
                                <td colspan="7" class="px-4 py-4">
                                    <div class="bg-gray-900 rounded-lg p-4">
                                        @if($invoice->hasRtdData())
                                            @php
                                                $gfr = $invoice->rtd_breakdown['goods_for_resale'] ?? [];
                                                $excluded = $invoice->rtd_breakdown['excluded'] ?? [];
                                                $unresolved = $invoice->rtd_breakdown['unresolved'] ?? [];
                                                $stats = $invoice->rtd_breakdown['stats'] ?? [];
                                                $isService = ($stats['is_service'] ?? false) || ($invoice->supplier && $invoice->supplier->rtd_classification === 'service_overhead');
                                                $rtdMethod = $stats['method'] ?? 'parser';

                                                // For service suppliers, VAT breakdown is in stats.service_by_vat
                                                $serviceByVat = $stats['service_by_vat'] ?? [];
                                                $serviceOverheadAmount = (float) ($excluded['service_overhead'] ?? 0);

                                                // T1 total (goods for resale) - always 0 for service suppliers
                                                $rtdTotal = $isService ? 0 : $invoice->getRtdTotal();

                                                // Excluded total includes service_overhead for service suppliers
                                                $excludedTotal = ($excluded['freight'] ?? 0) + ($excluded['deposits'] ?? 0) + ($excluded['drs'] ?? 0) + ($excluded['vat'] ?? 0) + $serviceOverheadAmount;
                                                $unresolvedTotal = $unresolved['net_total'] ?? 0;
                                                $calculatedTotal = $rtdTotal + $excludedTotal + $unresolvedTotal;
                                                $invoiceTotal = $invoice->total_amount;
                                                $difference = abs($calculatedTotal - $invoiceTotal);
                                                $isBalanced = $difference < 0.50;
                                            @endphp

                                            {{-- Method/Type Badge --}}
                                            @if($rtdMethod !== 'parser' || $isService)
                                                <div class="mb-3 flex items-center gap-2">
                                                    @if($isService)
                                                        <span class="px-2 py-1 text-xs font-semibold rounded bg-yellow-900 text-yellow-300">T2 - Service/Overhead</span>
                                                    @elseif($rtdMethod === 'simple_vat')
                                                        <span class="px-2 py-1 text-xs font-semibold rounded bg-purple-900 text-purple-300">Simple VAT Breakdown</span>
                                                    @endif
                                                </div>
                                            @endif

                                            <div class="grid grid-cols-1 lg:grid-cols-4 gap-4">
                                                {{-- Goods for Resale or Service/Overhead --}}
                                                <div class="bg-gray-800 rounded-lg p-3">
                                                    <h4 class="text-sm font-semibold {{ $isService ? 'text-yellow-400' : 'text-green-400' }} mb-2 flex items-center">
                                                        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                                        </svg>
                                                        {{ $isService ? 'Service/Overhead (T2)' : 'Goods for Resale (T1)' }}
                                                    </h4>
                                                    @php $vatSource = $isService ? $serviceByVat : $gfr; @endphp
                                                    <div class="space-y-1 text-sm">
                                                        <div class="flex justify-between">
                                                            <span class="text-gray-400">0% Rate:</span>
                                                            <span class="text-white font-mono">{{ number_format($vatSource['0'] ?? 0, 2) }}</span>
                                                        </div>
                                                        <div class="flex justify-between">
                                                            <span class="text-gray-400">9% Rate:</span>
                                                            <span class="text-white font-mono">{{ number_format($vatSource['9'] ?? 0, 2) }}</span>
                                                        </div>
                                                        <div class="flex justify-between">
                                                            <span class="text-gray-400">13.5% Rate:</span>
                                                            <span class="text-white font-mono">{{ number_format($vatSource['13.5'] ?? 0, 2) }}</span>
                                                        </div>
                                                        <div class="flex justify-between">
                                                            <span class="text-gray-400">23% Rate:</span>
                                                            <span class="text-white font-mono">{{ number_format($vatSource['23'] ?? 0, 2) }}</span>
                                                        </div>
                                                        <div class="flex justify-between pt-2 border-t border-gray-600 font-semibold">
                                                            <span class="text-gray-300">Subtotal:</span>
                                                            <span class="{{ $isService ? 'text-yellow-400' : 'text-green-400' }} font-mono">{{ number_format($rtdTotal, 2) }}</span>
                                                        </div>
                                                    </div>
                                                </div>

                                                {{-- Excluded --}}
                                                <div class="bg-gray-800 rounded-lg p-3">
                                                    <h4 class="text-sm font-semibold text-blue-400 mb-2 flex items-center">
                                                        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/>
                                                        </svg>
                                                        Excluded
                                                    </h4>
                                                    <div class="space-y-1 text-sm">
                                                        <div class="flex justify-between">
                                                            <span class="text-gray-400">Freight:</span>
                                                            <span class="text-white font-mono">{{ number_format($excluded['freight'] ?? 0, 2) }}</span>
                                                        </div>
                                                        <div class="flex justify-between">
                                                            <span class="text-gray-400">Deposits:</span>
                                                            <span class="text-white font-mono">{{ number_format($excluded['deposits'] ?? 0, 2) }}</span>
                                                        </div>
                                                        @if(($excluded['drs'] ?? 0) > 0)
                                                        <div class="flex justify-between">
                                                            <span class="text-gray-400">DRS:</span>
                                                            <span class="text-white font-mono">{{ number_format($excluded['drs'] ?? 0, 2) }}</span>
                                                        </div>
                                                        @endif
                                                        @if(($excluded['vat'] ?? 0) > 0)
                                                        <div class="flex justify-between">
                                                            <span class="text-gray-400">VAT:</span>
                                                            <span class="text-white font-mono">{{ number_format($excluded['vat'] ?? 0, 2) }}</span>
                                                        </div>
                                                        @endif
                                                        @if($serviceOverheadAmount > 0)
                                                        <div class="flex justify-between text-yellow-400">
                                                            <span>{{ $isService ? 'Service/Overhead:' : 'Non-retail:' }}</span>
                                                            <span class="font-mono">{{ number_format($serviceOverheadAmount, 2) }}</span>
                                                        </div>
                                                        @endif
                                                        <div class="flex justify-between pt-2 border-t border-gray-600 font-semibold">
                                                            <span class="text-gray-300">Subtotal:</span>
                                                            <span class="text-blue-400 font-mono">{{ number_format($excludedTotal, 2) }}</span>
                                                        </div>
                                                    </div>
                                                </div>

                                                {{-- Unresolved --}}
                                                <div class="bg-gray-800 rounded-lg p-3">
                                                    <h4 class="text-sm font-semibold {{ ($unresolved['count'] ?? 0) > 0 ? 'text-red-400' : 'text-gray-400' }} mb-2 flex items-center">
                                                        @if(($unresolved['count'] ?? 0) > 0)
                                                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                                                            </svg>
                                                        @else
                                                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                                            </svg>
                                                        @endif
                                                        Unresolved
                                                    </h4>
                                                    @if(($unresolved['count'] ?? 0) > 0)
                                                        <div class="space-y-1 text-sm">
                                                            <div class="flex justify-between text-red-400">
                                                                <span>{{ $unresolved['count'] }} items</span>
                                                                <span class="font-mono">{{ number_format($unresolvedTotal, 2) }}</span>
                                                            </div>
                                                            @if(!empty($invoice->rtd_resolution_issues))
                                                                <ul class="mt-1 space-y-0.5 text-xs text-gray-400 max-h-16 overflow-y-auto">
                                                                    @foreach(array_slice($invoice->rtd_resolution_issues, 0, 3) as $issue)
                                                                        <li class="flex justify-between">
                                                                            <span class="font-mono truncate mr-2">{{ $issue['article_code'] }}</span>
                                                                            <span class="font-mono">{{ number_format($issue['line_total'], 2) }}</span>
                                                                        </li>
                                                                    @endforeach
                                                                    @if(count($invoice->rtd_resolution_issues) > 3)
                                                                        <li class="text-gray-500">+{{ count($invoice->rtd_resolution_issues) - 3 }} more</li>
                                                                    @endif
                                                                </ul>
                                                            @endif
                                                            <a href="{{ route('rtd-fallbacks.unresolved', ['invoice_id' => $invoice->id]) }}"
                                                               class="inline-block mt-1 text-xs text-blue-400 hover:text-blue-300">
                                                                Resolve →
                                                            </a>
                                                        </div>
                                                    @else
                                                        <div class="text-sm">
                                                            <p class="text-green-400 text-xs mb-1">All items resolved</p>
                                                            <div class="flex justify-between pt-2 border-t border-gray-600 font-semibold">
                                                                <span class="text-gray-300">Subtotal:</span>
                                                                <span class="text-gray-400 font-mono">0.00</span>
                                                            </div>
                                                        </div>
                                                    @endif
                                                </div>

                                                {{-- Reconciliation Summary --}}
                                                <div class="bg-gray-800 rounded-lg p-3 border-2 {{ $isBalanced ? 'border-green-600' : 'border-yellow-600' }}">
                                                    <h4 class="text-sm font-semibold {{ $isBalanced ? 'text-green-400' : 'text-yellow-400' }} mb-2 flex items-center">
                                                        @if($isBalanced)
                                                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                                            </svg>
                                                        @else
                                                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                                            </svg>
                                                        @endif
                                                        Reconciliation
                                                    </h4>
                                                    <div class="space-y-1 text-sm">
                                                        @if($isService)
                                                        {{-- Service suppliers: show simplified reconciliation --}}
                                                        <div class="flex justify-between">
                                                            <span class="text-yellow-400">+ Service (T2):</span>
                                                            <span class="text-white font-mono">{{ number_format($serviceOverheadAmount, 2) }}</span>
                                                        </div>
                                                        <div class="flex justify-between">
                                                            <span class="text-blue-400">+ VAT:</span>
                                                            <span class="text-white font-mono">{{ number_format($excluded['vat'] ?? 0, 2) }}</span>
                                                        </div>
                                                        @else
                                                        {{-- Goods suppliers: show full breakdown --}}
                                                        <div class="flex justify-between">
                                                            <span class="text-green-400">+ Goods (T1):</span>
                                                            <span class="text-white font-mono">{{ number_format($rtdTotal, 2) }}</span>
                                                        </div>
                                                        <div class="flex justify-between">
                                                            <span class="text-blue-400">+ Excluded:</span>
                                                            <span class="text-white font-mono">{{ number_format($excludedTotal, 2) }}</span>
                                                        </div>
                                                        <div class="flex justify-between">
                                                            <span class="text-red-400">+ Unresolved:</span>
                                                            <span class="text-white font-mono">{{ number_format($unresolvedTotal, 2) }}</span>
                                                        </div>
                                                        @endif
                                                        <div class="flex justify-between pt-1 border-t border-gray-600">
                                                            <span class="text-gray-300 font-semibold">= Calculated:</span>
                                                            <span class="text-white font-mono font-semibold">{{ number_format($calculatedTotal, 2) }}</span>
                                                        </div>
                                                        <div class="flex justify-between">
                                                            <span class="text-gray-300 font-semibold">Invoice Total:</span>
                                                            <span class="text-white font-mono font-semibold">{{ number_format($invoiceTotal, 2) }}</span>
                                                        </div>
                                                        @if(!$isBalanced)
                                                            <div class="flex justify-between pt-1 border-t border-yellow-600 text-yellow-400">
                                                                <span class="font-semibold">Difference:</span>
                                                                <span class="font-mono font-semibold">{{ number_format($difference, 2) }}</span>
                                                            </div>
                                                        @else
                                                            <div class="pt-1 text-center">
                                                                <span class="text-green-400 text-xs">✓ Balanced</span>
                                                            </div>
                                                        @endif
                                                    </div>
                                                </div>
                                            </div>

                                            {{-- Frozen Info --}}
                                            @if($invoice->rtd_status === 'frozen')
                                                <div class="mt-3 pt-3 border-t border-gray-700 text-xs text-gray-500">
                                                    Frozen {{ $invoice->rtd_accepted_at?->format('d M Y H:i') }}
                                                    @if($invoice->rtdAcceptedByUser) by {{ $invoice->rtdAcceptedByUser->name }} @endif
                                                </div>
                                            @endif
                                        @elseif($invoice->canComputeRtd())
                                            <div class="text-center py-4">
                                                <p class="text-gray-400 mb-4">This invoice has parsed line data. Click Compute to generate RTD breakdown.</p>
                                                <form action="{{ route('rtd.compute', $invoice) }}" method="POST" class="inline">
                                                    @csrf
                                                    <button type="submit" class="bg-orange-600 hover:bg-orange-700 text-white px-4 py-2 rounded">
                                                        Compute RTD
                                                    </button>
                                                </form>
                                            </div>
                                        @elseif($invoice->canReparseForRtd())
                                            <div class="text-center py-4">
                                                <p class="text-gray-400 mb-4">This invoice has a PDF attachment. Click Parse to extract line items.</p>
                                                <form action="{{ route('rtd.parse', $invoice) }}" method="POST" class="inline">
                                                    @csrf
                                                    <button type="submit" class="bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded">
                                                        Parse PDF
                                                    </button>
                                                </form>
                                            </div>
                                        @else
                                            <div class="text-center py-4 text-gray-500">
                                                <p>No PDF attachment found for this invoice.</p>
                                                <a href="{{ route('invoices.edit', $invoice) }}" class="text-blue-400 hover:text-blue-300">
                                                    Upload attachment
                                                </a>
                                            </div>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>

                {{-- Pagination --}}
                @if($invoices->hasPages())
                    <div class="bg-gray-900 px-4 py-3 flex items-center justify-between border-t border-gray-700">
                        <div class="text-sm text-gray-400">
                            Showing {{ $invoices->firstItem() }} to {{ $invoices->lastItem() }} of {{ $invoices->total() }} invoices
                        </div>
                        <div class="flex space-x-2">
                            @if($invoices->onFirstPage())
                            @else
                                <a href="{{ $invoices->appends(request()->query())->previousPageUrl() }}"
                                   class="bg-gray-700 hover:bg-gray-600 text-white px-3 py-1 rounded">
                                    Previous
                                </a>
                            @endif
                            @if($invoices->hasMorePages())
                                <a href="{{ $invoices->appends(request()->query())->nextPageUrl() }}"
                                   class="bg-gray-700 hover:bg-gray-600 text-white px-3 py-1 rounded">
                                    Next
                                </a>
                            @endif
                        </div>
                    </div>
                @endif
            @endif
        </div>
    </div>

    <script>
        // Force Reparse Mode state
        let forceReparseMode = localStorage.getItem('rtd_force_reparse_mode') === 'true';

        // Initialize on page load
        document.addEventListener('DOMContentLoaded', function() {
            const toggle = document.getElementById('force-reparse-toggle');
            if (toggle) {
                toggle.checked = forceReparseMode;
            }
            updateForceReparseUI();
            updateFreezeAllButton();
            updateComputeAllButton();
        });

        function toggleForceReparseMode() {
            forceReparseMode = !forceReparseMode;
            localStorage.setItem('rtd_force_reparse_mode', forceReparseMode);
            const toggle = document.getElementById('force-reparse-toggle');
            if (toggle) {
                toggle.checked = forceReparseMode;
            }
            updateForceReparseUI();
        }

        function updateForceReparseUI() {
            const banner = document.getElementById('force-reparse-banner');
            const buttons = document.querySelectorAll('.force-reparse-btn');

            if (forceReparseMode) {
                banner.classList.remove('hidden');
                buttons.forEach(btn => btn.classList.remove('hidden'));
            } else {
                banner.classList.add('hidden');
                buttons.forEach(btn => btn.classList.add('hidden'));
            }
        }

        function toggleExpand(invoiceId) {
            const detailRow = document.getElementById('detail-' + invoiceId);
            const chevron = document.getElementById('chevron-' + invoiceId);

            if (detailRow.classList.contains('hidden')) {
                detailRow.classList.remove('hidden');
                chevron.style.transform = 'rotate(90deg)';
            } else {
                detailRow.classList.add('hidden');
                chevron.style.transform = 'rotate(0deg)';
            }
        }

        function viewInvoicePdf(invoiceId) {
            // Fetch attachment info for this invoice
            fetch(`/invoices/${invoiceId}/attachments`)
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.attachments && data.attachments.length > 0) {
                        // Find PDF attachment (primary first, then any PDF)
                        let pdfAttachment = data.attachments.find(att => att.is_primary) || data.attachments[0];

                        // Open in minimal viewer in new window
                        const viewerUrl = pdfAttachment.viewer_minimal_url;
                        const windowName = `rtd_pdf_${invoiceId}_${pdfAttachment.id}`;
                        const windowFeatures = 'width=900,height=1000,scrollbars=yes,resizable=yes,toolbar=no,menubar=no,location=no,status=no';

                        window.open(viewerUrl, windowName, windowFeatures);
                    } else {
                        alert('No PDF attachment found for this invoice.');
                    }
                })
                .catch(error => {
                    console.error('Error loading attachments:', error);
                    alert('Failed to load PDF attachment.');
                });
        }

        // AJAX action handler for RTD operations (parse, compute, accept)
        function rtdAction(invoiceId, action, loadingText) {
            const loading = document.getElementById('loading-' + invoiceId);
            const loadingTextEl = document.getElementById('loading-text-' + invoiceId);
            const buttons = document.getElementById('buttons-' + invoiceId);

            // Show loading state
            loading.classList.remove('hidden');
            loading.classList.add('inline-flex');
            loadingTextEl.textContent = loadingText + '...';
            buttons.classList.add('hidden');

            // Determine the URL based on action
            let url;
            switch(action) {
                case 'parse':
                    url = `/rtd/${invoiceId}/parse`;
                    break;
                case 'force-parse':
                    url = `/rtd/${invoiceId}/force-parse`;
                    break;
                case 'compute':
                    url = `/rtd/${invoiceId}/compute`;
                    break;
                case 'accept':
                    url = `/rtd/${invoiceId}/accept`;
                    break;
                default:
                    console.error('Unknown action:', action);
                    return;
            }

            fetch(url, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'Accept': 'application/json',
                    'Content-Type': 'application/json'
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showFlashMessage(data.message, 'success');
                    updateRowStatus(invoiceId, data);
                } else {
                    showFlashMessage(data.message || 'Action failed', 'error');
                    // Restore buttons on error
                    loading.classList.add('hidden');
                    loading.classList.remove('inline-flex');
                    buttons.classList.remove('hidden');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showFlashMessage('An error occurred. Please try again.', 'error');
                // Restore buttons on error
                loading.classList.add('hidden');
                loading.classList.remove('inline-flex');
                buttons.classList.remove('hidden');
            });
        }

        // Open manual RTD edit form in the detail row
        function openManualEdit(invoiceId, invoiceTotal) {
            const detailRow = document.getElementById('detail-' + invoiceId);
            const chevron = document.getElementById('chevron-' + invoiceId);

            // Show detail row
            detailRow.classList.remove('hidden');
            if (chevron) chevron.style.transform = 'rotate(90deg)';

            // Try to read existing breakdown from the row's current data
            const contentDiv = detailRow.querySelector('.bg-gray-900');
            let gfr = { '0': 0, '9': 0, '13.5': 0, '23': 0 };
            let excl = { freight: 0, deposits: 0, drs: 0, vat: 0, service_overhead: 0 };

            // Read existing breakdown from data attribute
            try {
                const bd = JSON.parse(detailRow.dataset.breakdown || '{}');
                if (bd.goods_for_resale) gfr = bd.goods_for_resale;
                if (bd.excluded) excl = { ...excl, ...bd.excluded };
            } catch(e) {}

            const inputClass = 'bg-gray-700 text-white text-sm font-mono text-right rounded px-2 py-1 w-24 border border-gray-600 focus:border-yellow-500 focus:outline-none';

            const html = `
                <div class="grid grid-cols-1 lg:grid-cols-4 gap-4" id="manual-form-${invoiceId}">
                    <div class="bg-gray-800 rounded-lg p-3">
                        <h4 class="text-sm font-semibold text-green-400 mb-2">Goods for Resale (T1)</h4>
                        <div class="space-y-2 text-sm">
                            <div class="flex justify-between items-center">
                                <span class="text-gray-400">0% Rate:</span>
                                <input type="text" inputmode="decimal" id="me-g0-${invoiceId}" value="${parseFloat(gfr['0'] || 0).toFixed(2)}" class="${inputClass}" oninput="updateManualReconciliation(${invoiceId}, ${invoiceTotal})">
                            </div>
                            <div class="flex justify-between items-center">
                                <span class="text-gray-400">9% Rate:</span>
                                <input type="text" inputmode="decimal" id="me-g9-${invoiceId}" value="${parseFloat(gfr['9'] || 0).toFixed(2)}" class="${inputClass}" oninput="updateManualReconciliation(${invoiceId}, ${invoiceTotal})">
                            </div>
                            <div class="flex justify-between items-center">
                                <span class="text-gray-400">13.5% Rate:</span>
                                <input type="text" inputmode="decimal" id="me-g135-${invoiceId}" value="${parseFloat(gfr['13.5'] || 0).toFixed(2)}" class="${inputClass}" oninput="updateManualReconciliation(${invoiceId}, ${invoiceTotal})">
                            </div>
                            <div class="flex justify-between items-center">
                                <span class="text-gray-400">23% Rate:</span>
                                <input type="text" inputmode="decimal" id="me-g23-${invoiceId}" value="${parseFloat(gfr['23'] || 0).toFixed(2)}" class="${inputClass}" oninput="updateManualReconciliation(${invoiceId}, ${invoiceTotal})">
                            </div>
                            <div class="flex justify-between pt-2 border-t border-gray-600 font-semibold">
                                <span class="text-gray-300">Subtotal:</span>
                                <span class="text-green-400 font-mono" id="me-goods-total-${invoiceId}">0.00</span>
                            </div>
                        </div>
                    </div>
                    <div class="bg-gray-800 rounded-lg p-3">
                        <h4 class="text-sm font-semibold text-blue-400 mb-2">Excluded</h4>
                        <div class="space-y-2 text-sm">
                            <div class="flex justify-between items-center">
                                <span class="text-gray-400">Freight:</span>
                                <input type="text" inputmode="decimal" id="me-freight-${invoiceId}" value="${parseFloat(excl.freight || 0).toFixed(2)}" class="${inputClass}" oninput="updateManualReconciliation(${invoiceId}, ${invoiceTotal})">
                            </div>
                            <div class="flex justify-between items-center">
                                <span class="text-gray-400">Deposits:</span>
                                <input type="text" inputmode="decimal" id="me-deposits-${invoiceId}" value="${parseFloat(excl.deposits || 0).toFixed(2)}" class="${inputClass}" oninput="updateManualReconciliation(${invoiceId}, ${invoiceTotal})">
                            </div>
                            <div class="flex justify-between items-center">
                                <span class="text-gray-400">DRS:</span>
                                <input type="text" inputmode="decimal" id="me-drs-${invoiceId}" value="${parseFloat(excl.drs || 0).toFixed(2)}" class="${inputClass}" oninput="updateManualReconciliation(${invoiceId}, ${invoiceTotal})">
                            </div>
                            <div class="flex justify-between items-center">
                                <span class="text-gray-400">VAT:</span>
                                <input type="text" inputmode="decimal" id="me-vat-${invoiceId}" value="${parseFloat(excl.vat || 0).toFixed(2)}" class="${inputClass}" oninput="updateManualReconciliation(${invoiceId}, ${invoiceTotal})">
                            </div>
                            <div class="flex justify-between items-center">
                                <span class="text-gray-400">Service/OH:</span>
                                <input type="text" inputmode="decimal" id="me-soh-${invoiceId}" value="${parseFloat(excl.service_overhead || 0).toFixed(2)}" class="${inputClass}" oninput="updateManualReconciliation(${invoiceId}, ${invoiceTotal})">
                            </div>
                            <div class="flex justify-between pt-2 border-t border-gray-600 font-semibold">
                                <span class="text-gray-300">Subtotal:</span>
                                <span class="text-blue-400 font-mono" id="me-excl-total-${invoiceId}">0.00</span>
                            </div>
                        </div>
                    </div>
                    <div class="bg-gray-800 rounded-lg p-3" id="me-recon-${invoiceId}">
                        <h4 class="text-sm font-semibold text-gray-400 mb-2">Reconciliation</h4>
                        <div class="space-y-1 text-sm">
                            <div class="flex justify-between">
                                <span class="text-green-400">+ Goods (T1):</span>
                                <span class="text-white font-mono" id="me-recon-goods-${invoiceId}">0.00</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-blue-400">+ Excluded:</span>
                                <span class="text-white font-mono" id="me-recon-excl-${invoiceId}">0.00</span>
                            </div>
                            <div class="flex justify-between pt-1 border-t border-gray-600">
                                <span class="text-gray-300 font-semibold">= Calculated:</span>
                                <span class="text-white font-mono font-semibold" id="me-recon-calc-${invoiceId}">0.00</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-300 font-semibold">Invoice Total:</span>
                                <span class="text-white font-mono font-semibold">${formatNumber(invoiceTotal)}</span>
                            </div>
                            <div id="me-recon-status-${invoiceId}" class="pt-1 text-center"></div>
                        </div>
                    </div>
                    <div class="bg-gray-800 rounded-lg p-3 flex flex-col justify-between">
                        <div>
                            <h4 class="text-sm font-semibold text-yellow-400 mb-2">Manual Entry</h4>
                            <p class="text-xs text-gray-400 mb-3">Enter the RTD breakdown values manually. The reconciliation updates live as you type.</p>
                        </div>
                        <div class="flex gap-2">
                            <button type="button" onclick="saveManualBreakdown(${invoiceId})"
                                    class="flex-1 bg-yellow-600 hover:bg-yellow-700 text-white text-sm px-4 py-2 rounded font-semibold">
                                Save
                            </button>
                            <button type="button" onclick="toggleExpand(${invoiceId})"
                                    class="flex-1 bg-gray-600 hover:bg-gray-500 text-white text-sm px-4 py-2 rounded">
                                Cancel
                            </button>
                        </div>
                    </div>
                </div>
            `;

            if (contentDiv) {
                contentDiv.innerHTML = html;
            }

            // Trigger initial reconciliation calculation
            updateManualReconciliation(invoiceId, invoiceTotal);
        }

        // Live update reconciliation as user types
        function updateManualReconciliation(invoiceId, invoiceTotal) {
            const g = (id) => parseFloat(document.getElementById(id)?.value || 0);

            const goodsTotal = g(`me-g0-${invoiceId}`) + g(`me-g9-${invoiceId}`) + g(`me-g135-${invoiceId}`) + g(`me-g23-${invoiceId}`);
            const exclTotal = g(`me-freight-${invoiceId}`) + g(`me-deposits-${invoiceId}`) + g(`me-drs-${invoiceId}`) + g(`me-vat-${invoiceId}`) + g(`me-soh-${invoiceId}`);
            const calcTotal = goodsTotal + exclTotal;
            const diff = Math.abs(calcTotal - invoiceTotal);
            const balanced = diff < 0.50;

            document.getElementById(`me-goods-total-${invoiceId}`).textContent = formatNumber(goodsTotal);
            document.getElementById(`me-excl-total-${invoiceId}`).textContent = formatNumber(exclTotal);
            document.getElementById(`me-recon-goods-${invoiceId}`).textContent = formatNumber(goodsTotal);
            document.getElementById(`me-recon-excl-${invoiceId}`).textContent = formatNumber(exclTotal);
            document.getElementById(`me-recon-calc-${invoiceId}`).textContent = formatNumber(calcTotal);

            const reconDiv = document.getElementById(`me-recon-${invoiceId}`);
            reconDiv.className = `bg-gray-800 rounded-lg p-3 border-2 ${balanced ? 'border-green-600' : 'border-yellow-600'}`;

            const statusDiv = document.getElementById(`me-recon-status-${invoiceId}`);
            if (balanced) {
                statusDiv.innerHTML = '<span class="text-green-400 text-xs">&#10003; Balanced</span>';
            } else {
                statusDiv.innerHTML = `<span class="text-yellow-400 text-xs font-mono">Difference: ${formatNumber(diff)}</span>`;
            }
        }

        // Save manual breakdown via AJAX
        function saveManualBreakdown(invoiceId) {
            const g = (id) => parseFloat(document.getElementById(id)?.value || 0);

            const payload = {
                goods_0: g(`me-g0-${invoiceId}`),
                goods_9: g(`me-g9-${invoiceId}`),
                goods_13_5: g(`me-g135-${invoiceId}`),
                goods_23: g(`me-g23-${invoiceId}`),
                freight: g(`me-freight-${invoiceId}`),
                deposits: g(`me-deposits-${invoiceId}`),
                drs: g(`me-drs-${invoiceId}`),
                vat: g(`me-vat-${invoiceId}`),
                service_overhead: g(`me-soh-${invoiceId}`),
            };

            const loading = document.getElementById('loading-' + invoiceId);
            const loadingTextEl = document.getElementById('loading-text-' + invoiceId);
            const buttons = document.getElementById('buttons-' + invoiceId);

            loading.classList.remove('hidden');
            loading.classList.add('inline-flex');
            loadingTextEl.textContent = 'Saving...';
            buttons.classList.add('hidden');

            fetch(`/rtd/${invoiceId}/manual-assign`, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'Accept': 'application/json',
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(payload)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showFlashMessage(data.message, 'success');
                    updateRowStatus(invoiceId, data);
                } else {
                    showFlashMessage(data.message || 'Save failed', 'error');
                    loading.classList.add('hidden');
                    loading.classList.remove('inline-flex');
                    buttons.classList.remove('hidden');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showFlashMessage('An error occurred. Please try again.', 'error');
                loading.classList.add('hidden');
                loading.classList.remove('inline-flex');
                buttons.classList.remove('hidden');
            });
        }

        // Update row status and buttons after successful AJAX action
        function updateRowStatus(invoiceId, data) {
            const loading = document.getElementById('loading-' + invoiceId);
            const buttons = document.getElementById('buttons-' + invoiceId);
            const row = document.getElementById('row-' + invoiceId);
            const actionsDiv = document.getElementById('actions-' + invoiceId);

            // Hide loading, show buttons
            loading.classList.add('hidden');
            loading.classList.remove('inline-flex');
            buttons.classList.remove('hidden');

            // Update data attributes for force reparse logic
            if (actionsDiv && data.is_frozen !== undefined) {
                actionsDiv.dataset.isFrozen = data.is_frozen ? 'true' : 'false';
            }

            // Update breakdown data attribute so manual edit pre-fills correctly
            if (data.rtd_breakdown) {
                const detailRow = document.getElementById('detail-' + invoiceId);
                if (detailRow) {
                    detailRow.dataset.breakdown = JSON.stringify(data.rtd_breakdown);
                    detailRow.dataset.invoiceTotal = data.invoice_total || 0;
                }
            }

            // Update balance indicator on total cell (3rd column)
            const totalCell = row.querySelector('td:nth-child(3)');
            if (totalCell && data.has_rtd_data && data.rtd_breakdown) {
                const gfr = data.rtd_breakdown.goods_for_resale || {};
                const excl = data.rtd_breakdown.excluded || {};
                const unres = data.rtd_breakdown.unresolved || {};
                const rtdT = parseFloat(data.rtd_total) || 0;
                const serviceOh = parseFloat(excl.service_overhead) || 0;
                const exclT = (parseFloat(excl.freight) || 0) + (parseFloat(excl.deposits) || 0) + (parseFloat(excl.drs) || 0) + (parseFloat(excl.vat) || 0) + serviceOh;
                const unresT = parseFloat(unres.net_total) || 0;
                const calcT = rtdT + exclT + unresT;
                const invT = parseFloat(data.invoice_total) || 0;
                const diff = Math.abs(calcT - invT);
                const balanced = diff < 0.50;

                const balanceIndicator = balanced
                    ? `<svg class="w-4 h-4 text-green-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>`
                    : `<span class="text-red-400 text-xs font-mono flex-shrink-0">${formatNumber(diff)}</span>`;

                totalCell.innerHTML = `<div class="flex items-center justify-end gap-1.5"><span class="text-gray-300">${formatNumber(invT)}</span>${balanceIndicator}</div>`;

                // Update breakdown column (4th column)
                const breakdownCell = row.querySelector('td:nth-child(4)');
                if (breakdownCell) {
                    const unresClass = unresT > 0 ? 'text-red-400' : 'text-gray-500';
                    breakdownCell.innerHTML = `
                        <div class="text-xs font-mono space-y-0.5 whitespace-nowrap">
                            <div class="flex justify-between gap-2"><span class="text-green-400">G:</span><span class="text-green-400">${formatNumber(rtdT)}</span></div>
                            <div class="flex justify-between gap-2"><span class="text-blue-400">E:</span><span class="text-blue-400">${formatNumber(exclT)}</span></div>
                            <div class="flex justify-between gap-2"><span class="${unresClass}">U:</span><span class="${unresClass}">${formatNumber(unresT)}</span></div>
                        </div>`;
                }
            }

            // Update status badge (5th column)
            const statusCell = row.querySelector('td:nth-child(5)');
            if (statusCell) {
                statusCell.innerHTML = getStatusBadgeHtml(data.new_status);
            }

            // Update issues count (6th column)
            const issuesCell = row.querySelector('td:nth-child(6)');
            if (issuesCell && data.unresolved_count !== undefined) {
                if (data.unresolved_count > 0) {
                    issuesCell.innerHTML = `<a href="/rtd-fallbacks/unresolved?invoice_id=${invoiceId}" class="px-2 py-1 text-xs font-semibold rounded-full bg-red-900 text-red-300 hover:bg-red-800 transition" title="Resolve unresolved items">${data.unresolved_count}</a>`;
                } else if (data.has_rtd_data) {
                    issuesCell.innerHTML = '<span class="text-green-400">0</span>';
                } else {
                    issuesCell.innerHTML = '<span class="text-gray-500">-</span>';
                }
            }

            // Update action buttons based on new status
            buttons.innerHTML = getActionButtonsHtml(invoiceId, data);

            // Update data-status and data-balanced attributes on the row
            if (row && data.new_status) {
                row.dataset.status = data.new_status;
                // Recompute balanced state from response data
                if (data.has_rtd_data && data.rtd_breakdown) {
                    const excl = data.rtd_breakdown.excluded || {};
                    const unres = data.rtd_breakdown.unresolved || {};
                    const rtdT = parseFloat(data.rtd_total) || 0;
                    const svcOh = parseFloat(excl.service_overhead) || 0;
                    const exclT = (parseFloat(excl.freight) || 0) + (parseFloat(excl.deposits) || 0) + (parseFloat(excl.drs) || 0) + (parseFloat(excl.vat) || 0) + svcOh;
                    const unresT = parseFloat(unres.net_total) || 0;
                    const calcT = rtdT + exclT + unresT;
                    const invT = parseFloat(data.invoice_total) || 0;
                    row.dataset.balanced = Math.abs(calcT - invT) < 0.50 ? 'true' : 'false';
                } else {
                    row.dataset.balanced = 'false';
                }
            }

            // Update the detail row content
            updateDetailRow(invoiceId, data);

            // Update batch button counts
            updateFreezeAllButton();
            updateComputeAllButton();
            updateForceReparseUI();
        }

        // Update the expandable detail row content
        function updateDetailRow(invoiceId, data) {
            const detailRow = document.getElementById('detail-' + invoiceId);
            if (!detailRow) return;

            const contentDiv = detailRow.querySelector('.bg-gray-900.rounded-lg.p-4');
            if (!contentDiv) return;

            let html = '';

            if (data.has_rtd_data && data.rtd_breakdown) {
                const gfr = data.rtd_breakdown.goods_for_resale || {};
                const excluded = data.rtd_breakdown.excluded || {};
                const unresolved = data.rtd_breakdown.unresolved || {};
                const issues = data.rtd_resolution_issues || [];

                const rtdTotal = parseFloat(data.rtd_total) || 0;
                const drsAmount = parseFloat(excluded.drs) || 0;
                const vatAmount = parseFloat(excluded.vat) || 0;
                const nonRetailAmount = parseFloat(excluded.service_overhead) || 0;
                const excludedTotal = (parseFloat(excluded.freight) || 0) + (parseFloat(excluded.deposits) || 0) + drsAmount + vatAmount + nonRetailAmount;
                const unresolvedTotal = parseFloat(unresolved.net_total) || 0;
                const calculatedTotal = rtdTotal + excludedTotal + unresolvedTotal;
                const invoiceTotal = parseFloat(data.invoice_total) || 0;
                const difference = Math.abs(calculatedTotal - invoiceTotal);
                const isBalanced = difference < 0.50;

                html = `
                    <div class="grid grid-cols-1 lg:grid-cols-4 gap-4">
                        <!-- Goods for Resale -->
                        <div class="bg-gray-800 rounded-lg p-3">
                            <h4 class="text-sm font-semibold text-green-400 mb-2 flex items-center">
                                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                                Goods for Resale
                            </h4>
                            <div class="space-y-1 text-sm">
                                <div class="flex justify-between">
                                    <span class="text-gray-400">0% Rate:</span>
                                    <span class="text-white font-mono">${formatNumber(gfr['0'] || 0)}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-400">9% Rate:</span>
                                    <span class="text-white font-mono">${formatNumber(gfr['9'] || 0)}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-400">13.5% Rate:</span>
                                    <span class="text-white font-mono">${formatNumber(gfr['13.5'] || 0)}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-400">23% Rate:</span>
                                    <span class="text-white font-mono">${formatNumber(gfr['23'] || 0)}</span>
                                </div>
                                <div class="flex justify-between pt-2 border-t border-gray-600 font-semibold">
                                    <span class="text-gray-300">Subtotal:</span>
                                    <span class="text-green-400 font-mono">${formatNumber(rtdTotal)}</span>
                                </div>
                            </div>
                        </div>

                        <!-- Excluded -->
                        <div class="bg-gray-800 rounded-lg p-3">
                            <h4 class="text-sm font-semibold text-blue-400 mb-2 flex items-center">
                                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/>
                                </svg>
                                Excluded
                            </h4>
                            <div class="space-y-1 text-sm">
                                <div class="flex justify-between">
                                    <span class="text-gray-400">Freight:</span>
                                    <span class="text-white font-mono">${formatNumber(excluded.freight || 0)}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-400">Deposits:</span>
                                    <span class="text-white font-mono">${formatNumber(excluded.deposits || 0)}</span>
                                </div>
                                ${drsAmount > 0 ? `
                                <div class="flex justify-between">
                                    <span class="text-gray-400">DRS:</span>
                                    <span class="text-white font-mono">${formatNumber(drsAmount)}</span>
                                </div>
                                ` : ''}
                                ${vatAmount > 0 ? `
                                <div class="flex justify-between">
                                    <span class="text-gray-400">VAT:</span>
                                    <span class="text-white font-mono">${formatNumber(vatAmount)}</span>
                                </div>
                                ` : ''}
                                ${nonRetailAmount > 0 ? `
                                <div class="flex justify-between text-yellow-400">
                                    <span>Non-retail:</span>
                                    <span class="font-mono">${formatNumber(nonRetailAmount)}</span>
                                </div>
                                ` : ''}
                                <div class="flex justify-between pt-2 border-t border-gray-600 font-semibold">
                                    <span class="text-gray-300">Subtotal:</span>
                                    <span class="text-blue-400 font-mono">${formatNumber(excludedTotal)}</span>
                                </div>
                            </div>
                        </div>

                        <!-- Unresolved -->
                        <div class="bg-gray-800 rounded-lg p-3">
                            <h4 class="text-sm font-semibold ${(unresolved.count || 0) > 0 ? 'text-red-400' : 'text-gray-400'} mb-2 flex items-center">
                                ${(unresolved.count || 0) > 0 ? `
                                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                                    </svg>
                                ` : `
                                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                    </svg>
                                `}
                                Unresolved
                            </h4>
                            ${(unresolved.count || 0) > 0 ? `
                                <div class="space-y-1 text-sm">
                                    <div class="flex justify-between text-red-400">
                                        <span>${unresolved.count} items</span>
                                        <span class="font-mono">${formatNumber(unresolvedTotal)}</span>
                                    </div>
                                    ${issues.length > 0 ? `
                                        <ul class="mt-1 space-y-0.5 text-xs text-gray-400 max-h-16 overflow-y-auto">
                                            ${issues.slice(0, 3).map(issue => `
                                                <li class="flex justify-between">
                                                    <span class="font-mono truncate mr-2">${escapeHtml(issue.article_code)}</span>
                                                    <span class="font-mono">${formatNumber(issue.line_total)}</span>
                                                </li>
                                            `).join('')}
                                            ${issues.length > 3 ? `<li class="text-gray-500">+${issues.length - 3} more</li>` : ''}
                                        </ul>
                                    ` : ''}
                                    <a href="/rtd-fallbacks/unresolved?invoice_id=${invoiceId}"
                                       class="inline-block mt-1 text-xs text-blue-400 hover:text-blue-300">
                                        Resolve →
                                    </a>
                                </div>
                            ` : `
                                <div class="text-sm">
                                    <p class="text-green-400 text-xs mb-1">All items resolved</p>
                                    <div class="flex justify-between pt-2 border-t border-gray-600 font-semibold">
                                        <span class="text-gray-300">Subtotal:</span>
                                        <span class="text-gray-400 font-mono">0.00</span>
                                    </div>
                                </div>
                            `}
                        </div>

                        <!-- Reconciliation Summary -->
                        <div class="bg-gray-800 rounded-lg p-3 border-2 ${isBalanced ? 'border-green-600' : 'border-yellow-600'}">
                            <h4 class="text-sm font-semibold ${isBalanced ? 'text-green-400' : 'text-yellow-400'} mb-2 flex items-center">
                                ${isBalanced ? `
                                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                    </svg>
                                ` : `
                                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                    </svg>
                                `}
                                Reconciliation
                            </h4>
                            <div class="space-y-1 text-sm">
                                <div class="flex justify-between">
                                    <span class="text-green-400">+ Goods:</span>
                                    <span class="text-white font-mono">${formatNumber(rtdTotal)}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-blue-400">+ Excluded:</span>
                                    <span class="text-white font-mono">${formatNumber(excludedTotal)}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-red-400">+ Unresolved:</span>
                                    <span class="text-white font-mono">${formatNumber(unresolvedTotal)}</span>
                                </div>
                                <div class="flex justify-between pt-1 border-t border-gray-600">
                                    <span class="text-gray-300 font-semibold">= Calculated:</span>
                                    <span class="text-white font-mono font-semibold">${formatNumber(calculatedTotal)}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-300 font-semibold">Invoice Total:</span>
                                    <span class="text-white font-mono font-semibold">${formatNumber(invoiceTotal)}</span>
                                </div>
                                ${!isBalanced ? `
                                    <div class="flex justify-between pt-1 border-t border-yellow-600 text-yellow-400">
                                        <span class="font-semibold">Difference:</span>
                                        <span class="font-mono font-semibold">${formatNumber(difference)}</span>
                                    </div>
                                ` : `
                                    <div class="pt-1 text-center">
                                        <span class="text-green-400 text-xs">✓ Balanced</span>
                                    </div>
                                `}
                            </div>
                        </div>
                    </div>
                `;

                // Add frozen info if applicable
                if (data.is_frozen && data.rtd_accepted_at) {
                    html += `
                        <div class="mt-3 pt-3 border-t border-gray-700 text-xs text-gray-500">
                            Frozen ${data.rtd_accepted_at}${data.rtd_accepted_by ? ` by ${escapeHtml(data.rtd_accepted_by)}` : ''}
                        </div>
                    `;
                }
            } else if (data.can_compute) {
                html = `
                    <div class="text-center py-4">
                        <p class="text-gray-400 mb-4">This invoice has parsed line data. Click Compute to generate RTD breakdown.</p>
                        <button type="button" onclick="rtdAction(${invoiceId}, 'compute', 'Computing')"
                                class="bg-orange-600 hover:bg-orange-700 text-white px-4 py-2 rounded">
                            Compute RTD
                        </button>
                    </div>
                `;
            } else if (data.can_parse) {
                html = `
                    <div class="text-center py-4">
                        <p class="text-gray-400 mb-4">This invoice has a PDF attachment. Click Parse to extract line items.</p>
                        <button type="button" onclick="rtdAction(${invoiceId}, 'parse', 'Parsing')"
                                class="bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded">
                            Parse PDF
                        </button>
                    </div>
                `;
            } else {
                html = `
                    <div class="text-center py-4 text-gray-500">
                        <p>No PDF attachment found for this invoice.</p>
                    </div>
                `;
            }

            contentDiv.innerHTML = html;
        }

        // Helper to format numbers
        function formatNumber(num) {
            return parseFloat(num || 0).toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
        }

        // Helper to escape HTML
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text || '';
            return div.innerHTML;
        }

        // Generate status badge HTML
        function getStatusBadgeHtml(status) {
            const badges = {
                'frozen': '<span class="px-2 py-1 text-xs font-semibold rounded-full bg-green-900 text-green-300">Frozen</span>',
                'computed': '<span class="px-2 py-1 text-xs font-semibold rounded-full bg-blue-900 text-blue-300">Computed</span>',
                'has_issues': '<span class="px-2 py-1 text-xs font-semibold rounded-full bg-yellow-900 text-yellow-300">Has Issues</span>',
                'needs_computation': '<span class="px-2 py-1 text-xs font-semibold rounded-full bg-orange-900 text-orange-300">Needs Compute</span>',
                'needs_parsing': '<span class="px-2 py-1 text-xs font-semibold rounded-full bg-red-900 text-red-300">Needs Parsing</span>',
                'pdf_missing': '<span class="px-2 py-1 text-xs font-semibold rounded-full bg-gray-700 text-gray-300">PDF Missing</span>'
            };
            return badges[status] || '<span class="text-gray-500">Unknown</span>';
        }

        // Generate action buttons HTML based on new status
        // Note: Force reparse button is rendered in Blade and controlled by updateForceReparseUI()
        function getActionButtonsHtml(invoiceId, data) {
            let html = '';

            if (data.is_frozen) {
                // Frozen - no action buttons (use Force Reparse to unfreeze + reparse)
                html = '';
            } else if (data.new_status === 'needs_parsing' && data.can_parse) {
                html = `<button type="button" onclick="rtdAction(${invoiceId}, 'parse', 'Parsing')" class="bg-red-600 hover:bg-red-700 text-white text-xs px-3 py-1 rounded">Parse</button>`;
                html += `<button type="button" onclick="openManualEdit(${invoiceId}, ${data.invoice_total || 0})" class="bg-yellow-600 hover:bg-yellow-700 text-white text-xs px-3 py-1 rounded">Manual</button>`;
            } else if (data.new_status === 'needs_computation' && data.can_compute) {
                html = `<button type="button" onclick="rtdAction(${invoiceId}, 'compute', 'Computing')" class="bg-orange-600 hover:bg-orange-700 text-white text-xs px-3 py-1 rounded">Compute</button>`;
            } else if (data.new_status === 'has_issues') {
                html = `<button type="button" onclick="rtdAction(${invoiceId}, 'compute', 'Recomputing')" class="bg-blue-600 hover:bg-blue-700 text-white text-xs px-3 py-1 rounded">Recompute</button>`;
                html += `<button type="button" onclick="openManualEdit(${invoiceId}, ${data.invoice_total || 0})" class="bg-yellow-600 hover:bg-yellow-700 text-white text-xs px-3 py-1 rounded">Edit</button>`;
            } else if (data.new_status === 'computed') {
                html = `<button type="button" onclick="rtdAction(${invoiceId}, 'compute', 'Recomputing')" class="bg-blue-600 hover:bg-blue-700 text-white text-xs px-3 py-1 rounded">Recompute</button>`;
                html += `<button type="button" onclick="openManualEdit(${invoiceId}, ${data.invoice_total || 0})" class="bg-yellow-600 hover:bg-yellow-700 text-white text-xs px-3 py-1 rounded">Edit</button>`;
                html += `<button type="button" onclick="rtdAction(${invoiceId}, 'accept', 'Freezing')" class="bg-green-600 hover:bg-green-700 text-white text-xs px-3 py-1 rounded ml-2">Freeze</button>`;
            } else if (data.new_status === 'pdf_missing') {
                html = '<span class="text-gray-400 text-xs">No PDF file</span>';
            }

            return html;
        }

        // Update the "Freeze All Balanced" button visibility and count
        function updateFreezeAllButton() {
            const rows = document.querySelectorAll('tr[data-status="computed"][data-balanced="true"]');
            const btn = document.getElementById('freeze-all-btn');
            const countEl = document.getElementById('freeze-all-count');
            if (btn) {
                if (rows.length > 0) {
                    btn.classList.remove('hidden');
                    countEl.textContent = rows.length;
                } else {
                    btn.classList.add('hidden');
                }
            }
        }

        // Update the "Compute All" button visibility and count
        function updateComputeAllButton() {
            const rows = document.querySelectorAll('tr[data-status="needs_computation"]');
            const btn = document.getElementById('compute-all-btn');
            const countEl = document.getElementById('compute-all-count');
            if (btn) {
                if (rows.length > 0) {
                    btn.classList.remove('hidden');
                    countEl.textContent = rows.length;
                } else {
                    btn.classList.add('hidden');
                }
            }
        }

        // Freeze all computed + balanced invoices sequentially
        async function freezeAllBalanced() {
            const rows = document.querySelectorAll('tr[data-status="computed"][data-balanced="true"]');
            if (rows.length === 0) return;

            if (!confirm(`Freeze ${rows.length} balanced invoice(s)?`)) return;

            const btn = document.getElementById('freeze-all-btn');
            const textEl = document.getElementById('freeze-all-text');
            btn.disabled = true;
            btn.classList.add('opacity-50', 'cursor-not-allowed');

            const ids = Array.from(rows).map(r => r.id.replace('row-', ''));
            let frozen = 0, failed = 0;

            for (let i = 0; i < ids.length; i++) {
                textEl.textContent = `Freezing ${i + 1}/${ids.length}...`;
                try {
                    const resp = await fetch(`/rtd/${ids[i]}/accept`, {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                            'Accept': 'application/json',
                            'Content-Type': 'application/json'
                        }
                    });
                    const data = await resp.json();
                    if (data.success) {
                        frozen++;
                        updateRowStatus(ids[i], data);
                    } else {
                        failed++;
                    }
                } catch (e) {
                    failed++;
                }
            }

            btn.disabled = false;
            btn.classList.remove('opacity-50', 'cursor-not-allowed');

            let msg = `Frozen ${frozen} invoice(s) successfully.`;
            if (failed > 0) msg += ` ${failed} failed.`;
            showFlashMessage(msg, failed > 0 ? 'warning' : 'success');

            updateFreezeAllButton();
        }

        // Compute all needs_computation invoices sequentially
        async function computeAllPending() {
            const rows = document.querySelectorAll('tr[data-status="needs_computation"]');
            if (rows.length === 0) return;

            if (!confirm(`Compute RTD for ${rows.length} invoice(s)?`)) return;

            const btn = document.getElementById('compute-all-btn');
            const textEl = document.getElementById('compute-all-text');
            btn.disabled = true;
            btn.classList.add('opacity-50', 'cursor-not-allowed');

            const ids = Array.from(rows).map(r => r.id.replace('row-', ''));
            let computed = 0, failed = 0;

            for (let i = 0; i < ids.length; i++) {
                textEl.textContent = `Computing ${i + 1}/${ids.length}...`;
                try {
                    const resp = await fetch(`/rtd/${ids[i]}/compute`, {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                            'Accept': 'application/json',
                            'Content-Type': 'application/json'
                        }
                    });
                    if (!resp.ok) {
                        console.error(`Compute failed for invoice ${ids[i]}: HTTP ${resp.status}`);
                        failed++;
                        continue;
                    }
                    const data = await resp.json();
                    if (data.success) {
                        computed++;
                        try { updateRowStatus(ids[i], data); } catch (e) { /* DOM update non-critical */ }
                    } else {
                        console.error(`Compute returned failure for invoice ${ids[i]}:`, data.message);
                        failed++;
                    }
                } catch (e) {
                    console.error(`Compute error for invoice ${ids[i]}:`, e);
                    failed++;
                }
            }

            btn.disabled = false;
            btn.classList.remove('opacity-50', 'cursor-not-allowed');

            let msg = `Computed ${computed} invoice(s) successfully.`;
            if (failed > 0) msg += ` ${failed} failed.`;
            showFlashMessage(msg, failed > 0 ? 'warning' : 'success');

            updateComputeAllButton();
            updateFreezeAllButton();
        }

        // Show toast-style flash message (fixed position, no layout shift)
        function showFlashMessage(message, type) {
            // Remove existing flash messages
            const existingFlash = document.querySelectorAll('.ajax-flash-message');
            existingFlash.forEach(el => el.remove());

            // Create toast-style flash message (fixed position, top-right)
            const flashDiv = document.createElement('div');
            const typeClasses = {
                success: 'bg-green-900 border border-green-500 text-green-300',
                warning: 'bg-yellow-900 border border-yellow-500 text-yellow-300',
                error: 'bg-red-900 border border-red-500 text-red-300'
            };
            flashDiv.className = 'ajax-flash-message fixed top-4 right-4 z-50 px-4 py-3 rounded-lg shadow-lg max-w-md ' +
                (typeClasses[type] || typeClasses.error);
            flashDiv.innerHTML = `
                <div class="flex justify-between items-center">
                    <span>${message}</span>
                    <button onclick="this.parentElement.parentElement.remove()" class="ml-4 text-lg leading-none">&times;</button>
                </div>
            `;

            document.body.appendChild(flashDiv);

            // Auto-remove after 5 seconds
            setTimeout(() => {
                if (flashDiv.parentElement) {
                    flashDiv.remove();
                }
            }, 5000);
        }
    </script>
</x-admin-layout>
