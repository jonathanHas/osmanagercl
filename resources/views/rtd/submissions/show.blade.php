<x-admin-layout>
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
        {{-- Header --}}
        <div class="flex justify-between items-center mb-6">
            <div>
                <h2 class="text-2xl font-bold text-gray-100">
                    RTD Submission
                    @if($submission->isSubmitted())
                        <span class="px-2 py-1 rounded text-sm bg-green-900 text-green-300 ml-2">Submitted</span>
                    @else
                        <span class="px-2 py-1 rounded text-sm bg-yellow-900 text-yellow-300 ml-2">Draft</span>
                    @endif
                </h2>
                <p class="text-gray-400 text-sm mt-1">
                    Period: {{ $submission->period_start->format('d/m/Y') }} - {{ $submission->period_end->format('d/m/Y') }}
                    | Created {{ $submission->created_at->format('d/m/Y H:i') }}
                    @if($submission->creator) by {{ $submission->creator->name }} @endif
                </p>
            </div>
            <div class="flex items-center gap-3">
                <a href="{{ route('rtd.submissions.report', $submission) }}"
                   class="bg-teal-600 hover:bg-teal-700 text-white font-bold py-2 px-4 rounded inline-flex items-center">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                    View Report
                </a>
                <a href="{{ route('rtd.submissions.export-csv', $submission) }}"
                   class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded inline-flex items-center">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                    Download CSV
                </a>
                <a href="{{ route('rtd.submissions.index') }}"
                   class="bg-gray-600 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded inline-flex items-center">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                    </svg>
                    Back to Submissions
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

        @php
            $totals = $submission->totals_snapshot ?? [];
            $goods = $totals['goods'] ?? ['0' => 0, '9' => 0, '13.5' => 0, '23' => 0];
            $service = $totals['service'] ?? ['0' => 0, '9' => 0, '13.5' => 0, '23' => 0];
            $excluded = $totals['excluded'] ?? ['freight' => 0, 'deposits' => 0, 'drs' => 0, 'vat' => 0];
            $goodsTotal = $totals['goods_total'] ?? 0;
            $serviceTotal = $totals['service_total'] ?? 0;
        @endphp

        {{-- Mark as Submitted Form (draft only) --}}
        @if($submission->isDraft())
            <div class="bg-gray-800 rounded-lg p-6 mb-6">
                <h3 class="text-lg font-semibold text-gray-100 mb-4">Mark as Submitted to Revenue</h3>
                <form action="{{ route('rtd.submissions.submit', $submission) }}" method="POST" class="flex flex-wrap items-end gap-4">
                    @csrf
                    <div>
                        <label class="block text-sm text-gray-400 mb-1">Date Filed</label>
                        <input type="date" name="submitted_date" value="{{ old('submitted_date', now()->format('Y-m-d')) }}" required
                               class="bg-gray-700 text-white rounded-lg px-4 py-2 focus:ring-2 focus:ring-green-500 focus:outline-none">
                    </div>
                    <div>
                        <label class="block text-sm text-gray-400 mb-1">Revenue Reference (optional)</label>
                        <input type="text" name="reference_number" value="{{ old('reference_number') }}"
                               placeholder="e.g. RTD-2025-001"
                               class="bg-gray-700 text-white rounded-lg px-4 py-2 focus:ring-2 focus:ring-green-500 focus:outline-none">
                    </div>
                    <button type="submit"
                            class="bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-4 rounded inline-flex items-center">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        Mark as Submitted
                    </button>
                </form>
                <div class="mt-4 pt-4 border-t border-gray-700">
                    <form action="{{ route('rtd.submissions.recalculate', $submission) }}" method="POST" class="flex items-center justify-between">
                        @csrf
                        <p class="text-gray-500 text-sm">
                            If supplier details have changed since this draft was created, recalculate to pick up the latest data.
                        </p>
                        <button type="submit"
                                class="bg-gray-600 hover:bg-gray-500 text-white font-bold py-2 px-4 rounded inline-flex items-center whitespace-nowrap">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                            </svg>
                            Recalculate Totals
                        </button>
                    </form>
                </div>
            </div>
        @else
            {{-- Submitted Info --}}
            <div class="bg-green-950 border border-green-700 rounded-lg p-4 mb-6">
                <div class="flex items-center">
                    <svg class="w-5 h-5 text-green-400 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    <span class="text-green-300">
                        Filed with Revenue on <strong>{{ $submission->submitted_date->format('d/m/Y') }}</strong>
                        @if($submission->reference_number)
                            | Reference: <strong class="font-mono">{{ $submission->reference_number }}</strong>
                        @endif
                    </span>
                </div>
            </div>
        @endif

        {{-- Notes --}}
        @if($submission->notes)
            <div class="bg-gray-800 rounded-lg p-4 mb-6">
                <h4 class="text-sm text-gray-400 mb-1">Notes</h4>
                <p class="text-gray-200">{{ $submission->notes }}</p>
            </div>
        @endif

        {{-- VAT Breakdown Summary --}}
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
            {{-- T1 & T2 Breakdown --}}
            <div class="lg:col-span-2 bg-gray-800 rounded-lg p-6">
                <h3 class="text-lg font-semibold text-gray-100 mb-4">
                    <span class="text-green-400">T1</span> - Goods for Resale by VAT Rate
                </h3>
                <table class="w-full">
                    <thead>
                        <tr class="border-b border-gray-700">
                            <th class="text-left text-gray-400 pb-2">VAT Rate</th>
                            <th class="text-right text-gray-400 pb-2">Net Amount</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-700">
                        <tr>
                            <td class="py-3">
                                <span class="inline-flex items-center">
                                    <span class="w-3 h-3 rounded-full bg-purple-500 mr-2"></span>
                                    0% (Zero Rated)
                                </span>
                            </td>
                            <td class="py-3 text-right text-white font-mono text-lg">{{ number_format($goods['0'], 2) }}</td>
                        </tr>
                        <tr>
                            <td class="py-3">
                                <span class="inline-flex items-center">
                                    <span class="w-3 h-3 rounded-full bg-blue-500 mr-2"></span>
                                    9% (Reduced)
                                </span>
                            </td>
                            <td class="py-3 text-right text-white font-mono text-lg">{{ number_format($goods['9'], 2) }}</td>
                        </tr>
                        <tr>
                            <td class="py-3">
                                <span class="inline-flex items-center">
                                    <span class="w-3 h-3 rounded-full bg-yellow-500 mr-2"></span>
                                    13.5% (Second Reduced)
                                </span>
                            </td>
                            <td class="py-3 text-right text-white font-mono text-lg">{{ number_format($goods['13.5'], 2) }}</td>
                        </tr>
                        <tr>
                            <td class="py-3">
                                <span class="inline-flex items-center">
                                    <span class="w-3 h-3 rounded-full bg-green-500 mr-2"></span>
                                    23% (Standard)
                                </span>
                            </td>
                            <td class="py-3 text-right text-white font-mono text-lg">{{ number_format($goods['23'], 2) }}</td>
                        </tr>
                        <tr class="border-t-2 border-gray-600">
                            <td class="py-3 font-semibold text-gray-200">Total T1 - Goods for Resale</td>
                            <td class="py-3 text-right text-green-400 font-mono text-xl font-bold">{{ number_format($goodsTotal, 2) }}</td>
                        </tr>
                    </tbody>
                </table>

                {{-- T2 Section --}}
                @if($serviceTotal > 0)
                <div class="mt-6 pt-6 border-t border-gray-700">
                    <h3 class="text-lg font-semibold text-gray-100 mb-4">
                        <span class="text-yellow-400">T2</span> - Service/Overhead by VAT Rate
                    </h3>
                    <table class="w-full">
                        <thead>
                            <tr class="border-b border-gray-700">
                                <th class="text-left text-gray-400 pb-2">VAT Rate</th>
                                <th class="text-right text-gray-400 pb-2">Net Amount</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-700">
                            <tr>
                                <td class="py-3"><span class="inline-flex items-center"><span class="w-3 h-3 rounded-full bg-purple-500 mr-2"></span>0%</span></td>
                                <td class="py-3 text-right text-white font-mono text-lg">{{ number_format($service['0'], 2) }}</td>
                            </tr>
                            <tr>
                                <td class="py-3"><span class="inline-flex items-center"><span class="w-3 h-3 rounded-full bg-blue-500 mr-2"></span>9%</span></td>
                                <td class="py-3 text-right text-white font-mono text-lg">{{ number_format($service['9'], 2) }}</td>
                            </tr>
                            <tr>
                                <td class="py-3"><span class="inline-flex items-center"><span class="w-3 h-3 rounded-full bg-yellow-500 mr-2"></span>13.5%</span></td>
                                <td class="py-3 text-right text-white font-mono text-lg">{{ number_format($service['13.5'], 2) }}</td>
                            </tr>
                            <tr>
                                <td class="py-3"><span class="inline-flex items-center"><span class="w-3 h-3 rounded-full bg-green-500 mr-2"></span>23%</span></td>
                                <td class="py-3 text-right text-white font-mono text-lg">{{ number_format($service['23'], 2) }}</td>
                            </tr>
                            <tr class="border-t-2 border-gray-600">
                                <td class="py-3 font-semibold text-gray-200">Total T2 - Service/Overhead</td>
                                <td class="py-3 text-right text-yellow-400 font-mono text-xl font-bold">{{ number_format($serviceTotal, 2) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                @endif
            </div>

            {{-- Summary Card --}}
            <div class="bg-gray-800 rounded-lg p-6">
                <h3 class="text-lg font-semibold text-gray-100 mb-4">Submission Summary</h3>
                <dl class="space-y-4">
                    <div>
                        <dt class="text-gray-400 text-sm">Period</dt>
                        <dd class="text-white font-semibold">{{ $submission->period_start->format('d M Y') }} - {{ $submission->period_end->format('d M Y') }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-400 text-sm">Total Invoices</dt>
                        <dd class="text-teal-400 font-semibold text-2xl">{{ $submission->invoices->count() }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-400 text-sm">T1 Goods Total</dt>
                        <dd class="text-green-400 font-semibold text-xl font-mono">{{ number_format($goodsTotal, 2) }}</dd>
                    </div>
                    @if($serviceTotal > 0)
                    <div>
                        <dt class="text-gray-400 text-sm">T2 Service Total</dt>
                        <dd class="text-yellow-400 font-semibold text-xl font-mono">{{ number_format($serviceTotal, 2) }}</dd>
                    </div>
                    @endif
                    <div class="pt-4 border-t border-gray-700">
                        <dt class="text-gray-400 text-sm">Excluded from RTD</dt>
                        <dd class="text-gray-300 text-sm mt-1">
                            Freight: {{ number_format($excluded['freight'], 2) }}<br>
                            Deposits: {{ number_format($excluded['deposits'], 2) }}
                            @if(($excluded['drs'] ?? 0) > 0)
                            <br>DRS: {{ number_format($excluded['drs'], 2) }}
                            @endif
                            @if(($excluded['vat'] ?? 0) > 0)
                            <br>VAT: {{ number_format($excluded['vat'], 2) }}
                            @endif
                        </dd>
                    </div>
                </dl>
            </div>
        </div>

        {{-- Pre-compute per-invoice reconciliation data --}}
        @php
            $colCount = $submission->isDraft() ? 10 : 9;
            $invoiceReconData = [];
            $reconIssueCount = 0;
            $reconTotalDiff = 0;

            foreach ($submission->invoices->sortBy('invoice_date') as $inv) {
                $snap = $inv->rtd_snapshot ?? [];
                $bd = $snap['breakdown'] ?? [];
                $exc = $bd['excluded'] ?? [];

                $isService = ($bd['stats']['is_service'] ?? false)
                    || ($inv->supplier && $inv->supplier->rtd_classification === 'service_overhead');

                if ($isService) {
                    $rateBuckets = $bd['service_overhead'] ?? $bd['goods_for_resale'] ?? [];
                } else {
                    $rateBuckets = $bd['goods_for_resale'] ?? [];
                }

                $rtdTotal = array_sum(array_map('floatval', $rateBuckets));
                $excFreight = (float) ($exc['freight'] ?? 0);
                $excDeposits = (float) ($exc['deposits'] ?? 0);
                $excDrs = (float) ($exc['drs'] ?? 0);
                $excServiceOh = (float) ($exc['service_overhead'] ?? 0);
                $excNonVat = round($excFreight + $excDeposits + $excDrs + $excServiceOh, 2);
                $vatAmt = (float) ($exc['vat'] ?? 0);
                $reconDelta = round($inv->total_amount - ($rtdTotal + $excNonVat + $vatAmt), 2);

                if (abs($reconDelta) >= 0.01) {
                    $reconIssueCount++;
                    $reconTotalDiff += $reconDelta;
                }

                $invoiceReconData[$inv->id] = [
                    'isService' => $isService,
                    'rateBuckets' => $rateBuckets,
                    'rtdTotal' => $rtdTotal,
                    'excFreight' => $excFreight,
                    'excDeposits' => $excDeposits,
                    'excDrs' => $excDrs,
                    'excServiceOh' => $excServiceOh,
                    'excNonVat' => $excNonVat,
                    'vatAmt' => $vatAmt,
                    'reconDelta' => $reconDelta,
                ];
            }
            $reconTotalDiff = round($reconTotalDiff, 2);
        @endphp

        {{-- Reconciliation Summary Panel --}}
        <div class="mb-4 rounded-lg px-5 py-3 {{ $reconIssueCount === 0 ? 'bg-green-950 border border-green-700' : 'bg-amber-950 border border-amber-700' }}">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-2">
                    @if($reconIssueCount === 0)
                        <svg class="w-5 h-5 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        <span class="text-green-300 font-semibold">All Reconciled</span>
                        <span class="text-green-400 text-sm">&mdash; every invoice breakdown matches its gross total</span>
                    @else
                        <svg class="w-5 h-5 text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z"/>
                        </svg>
                        <span class="text-amber-300 font-semibold">{{ $reconIssueCount }} invoice{{ $reconIssueCount > 1 ? 's' : '' }} with reconciliation differences</span>
                    @endif
                </div>
                @if($reconIssueCount > 0)
                    <span class="text-amber-400 font-mono font-semibold">&euro;{{ number_format(abs($reconTotalDiff), 2) }} total</span>
                @endif
            </div>
        </div>

        {{-- Invoice List --}}
        <div class="bg-gray-800 rounded-lg overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-700">
                <h3 class="text-lg font-semibold text-gray-100">Included Invoices ({{ $submission->invoices->count() }})</h3>
            </div>
            <table class="min-w-full divide-y divide-gray-700">
                <thead class="bg-gray-900">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase w-8"></th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase">Invoice #</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase">Supplier</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase">Date</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-400 uppercase">Gross</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-400 uppercase">RTD Net</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-400 uppercase">Excluded</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-400 uppercase">VAT</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-400 uppercase">Recon &Delta;</th>
                        @if($submission->isDraft())
                        <th class="px-4 py-3 text-center text-xs font-medium text-gray-400 uppercase">Actions</th>
                        @endif
                    </tr>
                </thead>
                @foreach($submission->invoices->sortBy('invoice_date') as $invoice)
                    @php $rd = $invoiceReconData[$invoice->id]; @endphp
                    <tbody x-data="{ open: false }" class="divide-y divide-gray-700 border-b border-gray-700">
                        <tr class="{{ abs($rd['reconDelta']) >= 0.01 ? 'bg-amber-900/20 border-l-2 border-amber-500' : 'hover:bg-gray-750' }}"
                            id="invoice-row-{{ $invoice->id }}">
                            <td class="px-2 py-3 text-center">
                                <button @click="open = !open" class="text-gray-400 hover:text-gray-200 transition-transform" :class="{ 'rotate-90': open }">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                    </svg>
                                </button>
                            </td>
                            <td class="px-4 py-3">
                                <a href="{{ route('invoices.show', $invoice) }}" class="text-blue-400 hover:text-blue-300 font-mono">
                                    #{{ $invoice->invoice_number }}
                                </a>
                            </td>
                            <td class="px-4 py-3 text-gray-300 text-sm">{{ $invoice->supplier_name }}</td>
                            <td class="px-4 py-3 text-gray-300">{{ $invoice->invoice_date->format('d/m/Y') }}</td>
                            <td class="px-4 py-3 text-right text-gray-300 font-mono">{{ number_format($invoice->total_amount, 2) }}</td>
                            <td class="px-4 py-3 text-right font-mono font-semibold {{ $rd['isService'] ? 'text-yellow-400' : 'text-green-400' }}">
                                {{ number_format($rd['rtdTotal'], 2) }}
                            </td>
                            <td class="px-4 py-3 text-right text-gray-400 font-mono">{{ number_format($rd['excNonVat'], 2) }}</td>
                            <td class="px-4 py-3 text-right text-gray-400 font-mono">{{ number_format($rd['vatAmt'], 2) }}</td>
                            <td class="px-4 py-3 text-right font-mono font-semibold {{ abs($rd['reconDelta']) < 0.01 ? 'text-green-400' : 'text-amber-400' }}">
                                {{ number_format($rd['reconDelta'], 2) }}
                                @if(abs($rd['reconDelta']) < 0.01)
                                    <svg class="w-3.5 h-3.5 inline ml-0.5 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                                    </svg>
                                @endif
                            </td>
                            @if($submission->isDraft())
                            <td class="px-4 py-3 text-center">
                                <button onclick="removeInvoice({{ $submission->id }}, {{ $invoice->id }})"
                                        class="text-red-400 hover:text-red-300 text-sm">
                                    Remove
                                </button>
                            </td>
                            @endif
                        </tr>
                        {{-- Expandable detail row --}}
                        <tr x-show="open"
                            x-transition:enter="transition ease-out duration-150"
                            x-transition:enter-start="opacity-0"
                            x-transition:enter-end="opacity-100"
                            x-cloak
                            class="bg-gray-900/50">
                            <td colspan="{{ $colCount }}" class="px-6 py-4">
                                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 text-sm">
                                    {{-- RTD Breakdown by VAT Rate --}}
                                    <div>
                                        <h4 class="text-xs uppercase tracking-wide text-gray-500 mb-2 font-semibold">
                                            {{ $rd['isService'] ? 'T2 Service/Overhead' : 'T1 Goods for Resale' }}
                                        </h4>
                                        <table class="w-full">
                                            <tbody>
                                                @foreach(['0' => '0%', '9' => '9%', '13.5' => '13.5%', '23' => '23%'] as $rate => $label)
                                                    @if(($rd['rateBuckets'][$rate] ?? 0) != 0)
                                                    <tr>
                                                        <td class="py-0.5 text-gray-400">{{ $label }}</td>
                                                        <td class="py-0.5 text-right text-gray-200 font-mono">{{ number_format($rd['rateBuckets'][$rate] ?? 0, 2) }}</td>
                                                    </tr>
                                                    @endif
                                                @endforeach
                                                <tr class="border-t border-gray-700">
                                                    <td class="py-1 text-gray-300 font-semibold">RTD Net</td>
                                                    <td class="py-1 text-right font-mono font-semibold {{ $rd['isService'] ? 'text-yellow-400' : 'text-green-400' }}">{{ number_format($rd['rtdTotal'], 2) }}</td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>

                                    {{-- Excluded Breakdown --}}
                                    <div>
                                        <h4 class="text-xs uppercase tracking-wide text-gray-500 mb-2 font-semibold">Excluded &amp; VAT</h4>
                                        <table class="w-full">
                                            <tbody>
                                                @if($rd['excFreight'] != 0)
                                                <tr>
                                                    <td class="py-0.5 text-gray-400">Freight</td>
                                                    <td class="py-0.5 text-right text-gray-200 font-mono">{{ number_format($rd['excFreight'], 2) }}</td>
                                                </tr>
                                                @endif
                                                @if($rd['excDeposits'] != 0)
                                                <tr>
                                                    <td class="py-0.5 text-gray-400">Deposits</td>
                                                    <td class="py-0.5 text-right text-gray-200 font-mono">{{ number_format($rd['excDeposits'], 2) }}</td>
                                                </tr>
                                                @endif
                                                @if($rd['excDrs'] != 0)
                                                <tr>
                                                    <td class="py-0.5 text-gray-400">DRS</td>
                                                    <td class="py-0.5 text-right text-gray-200 font-mono">{{ number_format($rd['excDrs'], 2) }}</td>
                                                </tr>
                                                @endif
                                                @if($rd['excServiceOh'] != 0)
                                                <tr>
                                                    <td class="py-0.5 text-gray-400">Non-retail</td>
                                                    <td class="py-0.5 text-right text-gray-200 font-mono">{{ number_format($rd['excServiceOh'], 2) }}</td>
                                                </tr>
                                                @endif
                                                @if($rd['excNonVat'] == 0 && $rd['vatAmt'] == 0)
                                                <tr>
                                                    <td class="py-0.5 text-gray-500 italic" colspan="2">None</td>
                                                </tr>
                                                @endif
                                                @if($rd['excNonVat'] != 0)
                                                <tr class="border-t border-gray-700">
                                                    <td class="py-1 text-gray-300 font-semibold">Excl. Subtotal</td>
                                                    <td class="py-1 text-right text-gray-300 font-mono font-semibold">{{ number_format($rd['excNonVat'], 2) }}</td>
                                                </tr>
                                                @endif
                                                <tr class="{{ $rd['excNonVat'] != 0 ? '' : 'border-t border-gray-700' }}">
                                                    <td class="py-1 text-gray-300 font-semibold">VAT</td>
                                                    <td class="py-1 text-right text-gray-300 font-mono font-semibold">{{ number_format($rd['vatAmt'], 2) }}</td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>

                                    {{-- Reconciliation Equation --}}
                                    <div>
                                        <h4 class="text-xs uppercase tracking-wide text-gray-500 mb-2 font-semibold">Reconciliation</h4>
                                        <div class="space-y-1 font-mono text-sm">
                                            <div class="flex justify-between">
                                                <span class="text-gray-400">Invoice Gross</span>
                                                <span class="text-white">{{ number_format($invoice->total_amount, 2) }}</span>
                                            </div>
                                            <div class="flex justify-between">
                                                <span class="text-gray-400">&minus; RTD Net</span>
                                                <span class="text-gray-300">{{ number_format($rd['rtdTotal'], 2) }}</span>
                                            </div>
                                            <div class="flex justify-between">
                                                <span class="text-gray-400">&minus; Excluded</span>
                                                <span class="text-gray-300">{{ number_format($rd['excNonVat'], 2) }}</span>
                                            </div>
                                            <div class="flex justify-between">
                                                <span class="text-gray-400">&minus; VAT</span>
                                                <span class="text-gray-300">{{ number_format($rd['vatAmt'], 2) }}</span>
                                            </div>
                                            <div class="flex justify-between border-t border-gray-600 pt-1 mt-1">
                                                <span class="text-gray-200 font-semibold">= Difference</span>
                                                <span class="font-semibold {{ abs($rd['reconDelta']) < 0.01 ? 'text-green-400' : 'text-amber-400' }}">
                                                    {{ number_format($rd['reconDelta'], 2) }}
                                                    @if(abs($rd['reconDelta']) < 0.01)
                                                        <svg class="w-3.5 h-3.5 inline ml-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                                                        </svg>
                                                    @endif
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                @endforeach
            </table>
        </div>
    </div>

    @if($submission->isDraft())
    <script>
        function removeInvoice(submissionId, invoiceId) {
            if (!confirm('Remove this invoice from the submission?')) return;

            fetch(`/rtd/submissions/${submissionId}/invoices/${invoiceId}`, {
                method: 'DELETE',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'Accept': 'application/json',
                },
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    document.getElementById('invoice-row-' + invoiceId).remove();
                    location.reload();
                } else {
                    alert(data.message || 'Failed to remove invoice.');
                }
            })
            .catch(() => alert('Failed to remove invoice.'));
        }
    </script>
    @endif
</x-admin-layout>
