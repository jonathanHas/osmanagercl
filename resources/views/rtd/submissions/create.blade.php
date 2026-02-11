<x-admin-layout>
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
        {{-- Header --}}
        <div class="flex justify-between items-center mb-6">
            <div>
                <h2 class="text-2xl font-bold text-gray-100">New RTD Submission</h2>
                <p class="text-gray-400 text-sm mt-1">Select frozen invoices to include in this Revenue submission</p>
            </div>
            <a href="{{ route('rtd.submissions.index') }}"
               class="bg-gray-600 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded inline-flex items-center">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                </svg>
                Back to Submissions
            </a>
        </div>

        {{-- Validation Errors --}}
        @if($errors->any())
            <div class="bg-red-900/50 border border-red-500 text-red-300 px-4 py-3 rounded mb-4">
                <ul class="list-disc pl-4">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        {{-- Date Range Filter --}}
        <div class="bg-gray-800 rounded-lg p-4 mb-6">
            <form action="{{ route('rtd.submissions.create') }}" method="GET" class="flex flex-wrap items-end gap-4">
                <div>
                    <label class="block text-sm text-gray-400 mb-1">Start Date</label>
                    <input type="date" name="start_date" value="{{ $startDate }}"
                           class="bg-gray-700 text-white rounded-lg px-4 py-2 focus:ring-2 focus:ring-teal-500 focus:outline-none">
                </div>
                <div>
                    <label class="block text-sm text-gray-400 mb-1">End Date</label>
                    <input type="date" name="end_date" value="{{ $endDate }}"
                           class="bg-gray-700 text-white rounded-lg px-4 py-2 focus:ring-2 focus:ring-teal-500 focus:outline-none">
                </div>
                <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg">
                    Filter Invoices
                </button>
            </form>
        </div>

        {{-- Invoice Selection Form --}}
        <form action="{{ route('rtd.submissions.store') }}" method="POST" x-data="submissionForm()" x-init="selectAllVisible()">
            @csrf
            <input type="hidden" name="period_start" value="{{ $startDate }}">
            <input type="hidden" name="period_end" value="{{ $endDate }}">

            {{-- Selection Summary Panel --}}
            <div class="bg-teal-950 border border-teal-500 rounded-lg p-4 mb-6">
                <div class="flex items-center justify-between">
                    <div class="flex items-center">
                        <svg class="w-5 h-5 text-teal-400 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        <span class="text-teal-300">
                            <strong>{{ $invoices->count() }}</strong> eligible frozen invoice(s) found
                            @if($startDate && $endDate)
                                for {{ date('d/m/Y', strtotime($startDate)) }} - {{ date('d/m/Y', strtotime($endDate)) }}
                            @endif
                        </span>
                    </div>
                </div>
                {{-- Live Totals Breakdown --}}
                <div class="mt-3 pt-3 border-t border-teal-800 grid grid-cols-2 md:grid-cols-4 gap-4 text-sm" x-show="selectedCount > 0" x-cloak>
                    <div>
                        <span class="text-teal-400">Selected</span>
                        <p class="text-white font-semibold text-lg"><span x-text="selectedCount">0</span> invoices</p>
                    </div>
                    <div>
                        <span class="text-green-400">T1 Goods for Resale</span>
                        <p class="text-green-400 font-mono font-semibold text-lg">&euro;<span x-text="formatNumber(selectedGoodsTotal)">0.00</span></p>
                    </div>
                    <div>
                        <span class="text-yellow-400">T2 Service/Overhead</span>
                        <p class="text-yellow-400 font-mono font-semibold text-lg">&euro;<span x-text="formatNumber(selectedServiceTotal)">0.00</span></p>
                    </div>
                    <div>
                        <span class="text-gray-400">Excluded</span>
                        <p class="text-gray-300 font-mono font-semibold text-lg">&euro;<span x-text="formatNumber(selectedExcludedTotal)">0.00</span></p>
                    </div>
                </div>
                {{-- Warnings --}}
                <div class="mt-3 pt-3 border-t border-teal-800" x-show="warningCount > 0" x-cloak>
                    <div class="flex items-center text-amber-400 text-sm">
                        <svg class="w-4 h-4 mr-2 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4.5c-.77-.833-2.694-.833-3.464 0L3.34 16.5c-.77.833.192 2.5 1.732 2.5z"/>
                        </svg>
                        <span>
                            <strong x-text="warningCount">0</strong> selected invoice(s) have warnings
                            <span class="text-amber-500/70">&mdash; unresolved RTD items or reconciliation differences. Review flagged rows below.</span>
                        </span>
                    </div>
                </div>
            </div>

            @if($invoices->isEmpty())
                <div class="bg-gray-800 rounded-lg p-12 text-center mb-6">
                    <svg class="w-12 h-12 text-gray-600 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    <p class="text-gray-400 text-lg">No eligible invoices found</p>
                    <p class="text-gray-500 text-sm mt-1">All frozen invoices in this date range have already been assigned to a submission.</p>
                </div>
            @else
                {{-- Quick Type Filters --}}
                <div class="flex items-center gap-2 mb-4">
                    <span class="text-gray-400 text-sm mr-2">Filter:</span>
                    <button type="button" @click="filterType('all')"
                            :class="activeFilter === 'all' ? 'bg-teal-600 text-white' : 'bg-gray-700 text-gray-300 hover:bg-gray-600'"
                            class="px-3 py-1 rounded text-sm font-medium transition-colors">
                        All
                    </button>
                    <button type="button" @click="filterType('goods')"
                            :class="activeFilter === 'goods' ? 'bg-green-600 text-white' : 'bg-gray-700 text-gray-300 hover:bg-gray-600'"
                            class="px-3 py-1 rounded text-sm font-medium transition-colors">
                        T1 Goods
                    </button>
                    <button type="button" @click="filterType('service')"
                            :class="activeFilter === 'service' ? 'bg-yellow-600 text-white' : 'bg-gray-700 text-gray-300 hover:bg-gray-600'"
                            class="px-3 py-1 rounded text-sm font-medium transition-colors">
                        T2 Service
                    </button>
                </div>

                {{-- Invoice Table --}}
                <div class="bg-gray-800 rounded-lg overflow-hidden mb-6">
                    <table class="min-w-full divide-y divide-gray-700">
                        <thead class="bg-gray-900">
                            <tr>
                                <th class="px-4 py-3 text-left">
                                    <input type="checkbox" x-ref="selectAllCheckbox"
                                           x-on:change="toggleAll($event.target.checked)"
                                           class="rounded bg-gray-700 border-gray-600 text-teal-500 focus:ring-teal-500">
                                </th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase">Invoice #</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase">Supplier</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase">Date</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-400 uppercase">Invoice Total</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-400 uppercase">RTD Amount</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase">Type</th>
                                <th class="px-4 py-3 text-center text-xs font-medium text-gray-400 uppercase w-10"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-700">
                            @foreach($invoices as $invoice)
                                @php
                                    $snapshot = $invoice->rtd_snapshot;
                                    $isService = ($snapshot['breakdown']['stats']['is_service'] ?? false)
                                        || ($invoice->supplier && $invoice->supplier->rtd_classification === 'service_overhead');
                                    if ($isService) {
                                        $breakdown = $snapshot['breakdown']['service_overhead'] ?? $snapshot['breakdown']['goods_for_resale'] ?? [];
                                    } else {
                                        $breakdown = $snapshot['breakdown']['goods_for_resale'] ?? [];
                                    }
                                    $rtdTotal = array_sum(array_map('floatval', $breakdown));
                                    $excl = $snapshot['breakdown']['excluded'] ?? [];
                                    $exclFreight = (float)($excl['freight'] ?? 0);
                                    $exclDeposits = (float)($excl['deposits'] ?? 0);
                                    $exclDrs = (float)($excl['drs'] ?? 0);
                                    $exclVat = (float)($excl['vat'] ?? 0);

                                    $unresolvedCount = $snapshot['breakdown']['unresolved']['count'] ?? 0;
                                    $integrity = $snapshot['breakdown']['integrity'] ?? [];
                                    $isReconciled = $integrity['reconciled'] ?? true;
                                    $hasWarning = $unresolvedCount > 0 || !$isReconciled;
                                @endphp
                                <tr class="hover:bg-gray-750 invoice-row" data-type="{{ $isService ? 'service' : 'goods' }}">
                                    <td class="px-4 py-3">
                                        <input type="checkbox" name="invoice_ids[]" value="{{ $invoice->id }}"
                                               x-on:change="updateTotals()"
                                               data-type="{{ $isService ? 'service' : 'goods' }}"
                                               data-goods-total="{{ $isService ? 0 : $rtdTotal }}"
                                               data-service-total="{{ $isService ? $rtdTotal : 0 }}"
                                               data-excluded-freight="{{ $exclFreight }}"
                                               data-excluded-deposits="{{ $exclDeposits }}"
                                               data-excluded-drs="{{ $exclDrs }}"
                                               data-excluded-vat="{{ $exclVat }}"
                                               data-has-warning="{{ $hasWarning ? '1' : '0' }}"
                                               class="invoice-checkbox rounded bg-gray-700 border-gray-600 text-teal-500 focus:ring-teal-500">
                                    </td>
                                    <td class="px-4 py-3">
                                        <span class="text-gray-200 font-mono">#{{ $invoice->invoice_number }}</span>
                                    </td>
                                    <td class="px-4 py-3 text-gray-300 text-sm">{{ $invoice->supplier_name }}</td>
                                    <td class="px-4 py-3 text-gray-300">{{ $invoice->invoice_date->format('d/m/Y') }}</td>
                                    <td class="px-4 py-3 text-right text-gray-300 font-mono">{{ number_format($invoice->total_amount, 2) }}</td>
                                    <td class="px-4 py-3 text-right font-mono font-semibold {{ $isService ? 'text-yellow-400' : 'text-green-400' }}">
                                        {{ number_format($rtdTotal, 2) }}
                                    </td>
                                    <td class="px-4 py-3">
                                        @if($isService)
                                            <span class="px-1.5 py-0.5 text-xs rounded bg-yellow-900 text-yellow-300">T2 Service</span>
                                        @else
                                            <span class="px-1.5 py-0.5 text-xs rounded bg-green-900 text-green-300">T1 Goods</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-center">
                                        @if($hasWarning)
                                            <span title="{{ $unresolvedCount > 0 ? $unresolvedCount . ' unresolved RTD item(s)' : '' }}{{ $unresolvedCount > 0 && !$isReconciled ? ' + ' : '' }}{{ !$isReconciled ? 'Reconciliation difference: ' . number_format($integrity['difference'] ?? 0, 2) : '' }}"
                                                  class="text-amber-400 cursor-help">
                                                <svg class="w-4 h-4 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4.5c-.77-.833-2.694-.833-3.464 0L3.34 16.5c-.77.833.192 2.5 1.732 2.5z"/>
                                                </svg>
                                            </span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                {{-- Notes and Submit --}}
                <div class="bg-gray-800 rounded-lg p-6">
                    <div class="mb-4">
                        <label class="block text-sm text-gray-400 mb-1">Notes (optional)</label>
                        <textarea name="notes" rows="2"
                                  class="w-full bg-gray-700 text-white rounded-lg px-4 py-2 focus:ring-2 focus:ring-teal-500 focus:outline-none"
                                  placeholder="Any notes about this submission...">{{ old('notes') }}</textarea>
                    </div>
                    <div class="flex items-center justify-between">
                        <p class="text-gray-400 text-sm">
                            You can mark this submission as filed with Revenue after creating it.
                        </p>
                        <button type="submit"
                                x-bind:disabled="selectedCount === 0"
                                class="bg-teal-600 hover:bg-teal-700 disabled:bg-gray-600 disabled:cursor-not-allowed text-white font-bold py-2 px-6 rounded inline-flex items-center">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            Create Submission (<span x-text="selectedCount">0</span> invoices)
                        </button>
                    </div>
                </div>
            @endif
        </form>
    </div>

    <script>
        function submissionForm() {
            return {
                selectedCount: 0,
                selectedGoodsTotal: 0,
                selectedServiceTotal: 0,
                selectedExcludedTotal: 0,
                warningCount: 0,
                activeFilter: 'all',

                selectAllVisible() {
                    document.querySelectorAll('.invoice-checkbox').forEach(cb => {
                        const row = cb.closest('tr');
                        if (row && row.style.display !== 'none') {
                            cb.checked = true;
                        }
                    });
                    this.updateTotals();
                },

                toggleAll(checked) {
                    document.querySelectorAll('.invoice-checkbox').forEach(cb => {
                        const row = cb.closest('tr');
                        if (row && row.style.display !== 'none') {
                            cb.checked = checked;
                        }
                    });
                    this.updateTotals();
                },

                updateSelectAll() {
                    const all = document.querySelectorAll('.invoice-row:not([style*="display: none"]) .invoice-checkbox');
                    const checked = document.querySelectorAll('.invoice-row:not([style*="display: none"]) .invoice-checkbox:checked');
                    const headerCb = this.$refs.selectAllCheckbox;
                    if (!headerCb) return;
                    if (checked.length === 0) {
                        headerCb.indeterminate = false;
                        headerCb.checked = false;
                    } else if (checked.length === all.length) {
                        headerCb.indeterminate = false;
                        headerCb.checked = true;
                    } else {
                        headerCb.indeterminate = true;
                    }
                },

                updateTotals() {
                    const checkboxes = document.querySelectorAll('.invoice-checkbox:checked');
                    this.selectedCount = checkboxes.length;
                    this.selectedGoodsTotal = 0;
                    this.selectedServiceTotal = 0;
                    this.selectedExcludedTotal = 0;
                    this.warningCount = 0;
                    checkboxes.forEach(cb => {
                        this.selectedGoodsTotal += parseFloat(cb.dataset.goodsTotal || 0);
                        this.selectedServiceTotal += parseFloat(cb.dataset.serviceTotal || 0);
                        this.selectedExcludedTotal += parseFloat(cb.dataset.excludedFreight || 0)
                            + parseFloat(cb.dataset.excludedDeposits || 0)
                            + parseFloat(cb.dataset.excludedDrs || 0)
                            + parseFloat(cb.dataset.excludedVat || 0);
                        if (cb.dataset.hasWarning === '1') {
                            this.warningCount++;
                        }
                    });
                    this.updateSelectAll();
                },

                filterType(type) {
                    this.activeFilter = type;
                    document.querySelectorAll('.invoice-row').forEach(row => {
                        if (type === 'all' || row.dataset.type === type) {
                            row.style.display = '';
                        } else {
                            row.style.display = 'none';
                            const cb = row.querySelector('.invoice-checkbox');
                            if (cb) cb.checked = false;
                        }
                    });
                    this.updateTotals();
                },

                formatNumber(n) {
                    return n.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
                }
            };
        }
    </script>
</x-admin-layout>
