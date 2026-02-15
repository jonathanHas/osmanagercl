<x-admin-layout>
    <style>
        @media print {
            nav, header, .no-print, [class*="sidebar"] { display: none !important; }
            body, main { background: white !important; color: black !important; margin: 0 !important; padding: 0 !important; }
            .print-container { max-width: 100% !important; padding: 10px !important; margin: 0 !important; }
            .print-container * { color: black !important; border-color: #999 !important; }
            .print-section { break-inside: avoid; page-break-inside: avoid; margin-bottom: 12px !important; }
            .print-container .bg-gray-800, .print-container .bg-gray-900 { background: white !important; }
            .print-container .bg-teal-950, .print-container .bg-green-950 { background: #f0fdf4 !important; }
            .print-container table { border-collapse: collapse; width: 100%; }
            .print-container th, .print-container td { border: 1px solid #999; padding: 4px 8px; font-size: 11px; }
            .print-container th { background: #f3f4f6 !important; font-weight: bold; }
            .print-container h1 { font-size: 18px; }
            .print-container h2 { font-size: 14px; }
            .ros-box { border: 2px solid black !important; padding: 2px 6px !important; font-weight: bold; }
            .rate-dot { display: none !important; }
            .text-green-400, .text-yellow-400, .text-teal-400, .text-blue-400, .text-purple-400 { color: black !important; }
        }
        .ros-box {
            display: inline-block;
            font-family: ui-monospace, monospace;
            font-size: 0.7rem;
            font-weight: 700;
            padding: 1px 6px;
            border: 1.5px solid;
            border-radius: 3px;
            min-width: 36px;
            text-align: center;
        }
        .ros-box-green  { border-color: #22c55e; color: #4ade80; background: rgba(34,197,94,0.08); }
        .ros-box-yellow { border-color: #eab308; color: #facc15; background: rgba(234,179,8,0.08); }
        .ros-box-blue   { border-color: #3b82f6; color: #60a5fa; background: rgba(59,130,246,0.08); }
        .ros-box-gray   { border-color: #6b7280; color: #9ca3af; background: rgba(107,114,128,0.08); }
        .ros-box-purple { border-color: #a855f7; color: #c084fc; background: rgba(168,85,247,0.08); }
    </style>

    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-6 print-container">
        {{-- Action Buttons --}}
        <div class="flex justify-between items-center mb-6 no-print">
            <a href="{{ route('rtd.submissions.show', $submission) }}"
               class="bg-gray-600 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded inline-flex items-center">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                </svg>
                Back to Submission
            </a>
            <div class="flex items-center gap-3">
                <a href="{{ route('rtd.submissions.export-csv', $submission) }}"
                   class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded inline-flex items-center">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                    Download CSV
                </a>
                <button onclick="window.print()"
                        class="bg-gray-700 hover:bg-gray-600 text-white font-bold py-2 px-4 rounded inline-flex items-center">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
                    </svg>
                    Print
                </button>
            </div>
        </div>

        {{-- ============================================================ --}}
        {{-- REPORT HEADER                                                --}}
        {{-- ============================================================ --}}
        <div class="bg-gray-800 rounded-lg p-6 mb-6 print-section">
            <div class="text-center mb-5">
                <h1 class="text-2xl font-bold text-gray-100">Return of Trading Details (RTD)</h1>
                <p class="text-gray-400 text-sm mt-1">Revenue Online Service — VAT RTD Submission Report</p>
            </div>
            <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-4 text-sm">
                <div>
                    <span class="text-gray-500 text-xs uppercase tracking-wide">Period</span>
                    <p class="text-white font-semibold">{{ $submission->period_start->format('d/m/Y') }} &ndash; {{ $submission->period_end->format('d/m/Y') }}</p>
                </div>
                <div>
                    <span class="text-gray-500 text-xs uppercase tracking-wide">Status</span>
                    <p class="font-semibold">
                        @if($submission->isSubmitted())
                            <span class="px-2 py-0.5 rounded text-xs bg-green-900 text-green-300">Submitted</span>
                        @else
                            <span class="px-2 py-0.5 rounded text-xs bg-yellow-900 text-yellow-300">Draft</span>
                        @endif
                    </p>
                </div>
                <div>
                    <span class="text-gray-500 text-xs uppercase tracking-wide">Date Filed</span>
                    <p class="text-white font-semibold">
                        {{ $submission->submitted_date ? $submission->submitted_date->format('d/m/Y') : '—' }}
                    </p>
                </div>
                <div>
                    <span class="text-gray-500 text-xs uppercase tracking-wide">Revenue Ref.</span>
                    <p class="text-white font-semibold font-mono">{{ $submission->reference_number ?? '—' }}</p>
                </div>
                <div>
                    <span class="text-gray-500 text-xs uppercase tracking-wide">Invoices</span>
                    <p class="text-teal-400 font-semibold text-lg">{{ $submission->invoices->count() }}</p>
                </div>
            </div>
        </div>

        {{-- ============================================================ --}}
        {{-- ROS SECTION 1: GOODS AND/OR SERVICES (SALES)                --}}
        {{-- ============================================================ --}}
        <div class="bg-gray-800 rounded-lg p-6 mb-6 print-section">
            <h2 class="text-lg font-bold text-gray-100 mb-1">
                <span class="text-purple-400">Section 1</span> &mdash; Goods and/or Services
            </h2>
            <p class="text-gray-500 text-xs mb-4">
                &euro; Values Excluding VAT
                @if($vatReturnsCount > 0)
                    &mdash; Aggregated from {{ $vatReturnsCount }} VAT3 return{{ $vatReturnsCount > 1 ? 's' : '' }} for this period
                    @if($salesSource === 'mixed')
                        <span class="text-yellow-500">(some periods used fallback data)</span>
                    @endif
                @else
                    &mdash; <span class="text-yellow-500">No VAT3 returns found for this period</span>
                @endif
            </p>

            <table class="w-full">
                <thead>
                    <tr class="border-b border-gray-700">
                        <th class="text-left text-gray-400 pb-2 py-2 w-16">ROS Box</th>
                        <th class="text-left text-gray-400 pb-2 py-2">Description</th>
                        <th class="text-right text-gray-400 pb-2 py-2 w-40">Net Amount (&euro;)</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-700">
                    <tr>
                        <td class="py-3"><span class="ros-box ros-box-purple">E3</span></td>
                        <td class="py-3 text-gray-200">Exempt</td>
                        <td class="py-3 text-right text-gray-500 font-mono text-lg">0.00</td>
                    </tr>
                    <tr>
                        <td class="py-3"><span class="ros-box ros-box-purple">D4</span></td>
                        <td class="py-3 text-gray-200">0% Exp</td>
                        <td class="py-3 text-right text-gray-500 font-mono text-lg">0.00</td>
                    </tr>
                    <tr>
                        <td class="py-3"><span class="ros-box ros-box-purple">D1</span></td>
                        <td class="py-3 text-gray-200">0% Home</td>
                        <td class="py-3 text-right text-white font-mono text-lg">{{ number_format($sales['0'] ?? 0, 2) }}</td>
                    </tr>
                    <tr>
                        <td class="py-3"><span class="ros-box ros-box-purple">C5</span></td>
                        <td class="py-3 text-gray-200">4.8%</td>
                        <td class="py-3 text-right text-gray-500 font-mono text-lg">0.00</td>
                    </tr>
                    <tr>
                        <td class="py-3"><span class="ros-box ros-box-purple">BC5</span></td>
                        <td class="py-3 text-gray-200">9%</td>
                        <td class="py-3 text-right text-white font-mono text-lg">{{ number_format($sales['9'] ?? 0, 2) }}</td>
                    </tr>
                    <tr>
                        <td class="py-3"><span class="ros-box ros-box-purple">AC5</span></td>
                        <td class="py-3 text-gray-200">13.5%</td>
                        <td class="py-3 text-right text-white font-mono text-lg">{{ number_format($sales['13.5'] ?? 0, 2) }}</td>
                    </tr>
                    <tr>
                        <td class="py-3"><span class="ros-box ros-box-purple">B5</span></td>
                        <td class="py-3 text-gray-200">FlatFarm</td>
                        <td class="py-3 text-right text-gray-500 font-mono text-lg">0.00</td>
                    </tr>
                    <tr>
                        <td class="py-3"><span class="ros-box ros-box-purple">P1</span></td>
                        <td class="py-3 text-gray-200">Std Rate</td>
                        <td class="py-3 text-right text-white font-mono text-lg">{{ number_format($sales['23'] ?? 0, 2) }}</td>
                    </tr>
                    <tr class="border-t-2 border-gray-600">
                        <td class="py-3"><span class="ros-box ros-box-purple">Z1</span></td>
                        <td class="py-3 font-bold text-gray-200">Total</td>
                        <td class="py-3 text-right text-purple-400 font-mono text-xl font-bold">{{ number_format($salesTotal, 2) }}</td>
                    </tr>
                </tbody>
            </table>
        </div>

        {{-- ============================================================ --}}
        {{-- ROS SECTION 2: ACQUISITIONS FROM THE EU AND NON-EU          --}}
        {{-- ============================================================ --}}
        <div class="bg-gray-800 rounded-lg p-6 mb-6 print-section">
            <h2 class="text-lg font-bold text-gray-100 mb-1">
                <span class="text-blue-400">Section 2</span> &mdash; Acquisitions from the EU and Non-EU
            </h2>
            <p class="text-gray-500 text-xs mb-4">&euro; Values Excluding VAT</p>

            <table class="w-full">
                <thead>
                    <tr class="border-b border-gray-700">
                        <th class="text-left text-gray-400 pb-2 py-2 w-16">ROS Box</th>
                        <th class="text-left text-gray-400 pb-2 py-2">Description</th>
                        <th class="text-right text-gray-400 pb-2 py-2 w-40">Net Amount (&euro;)</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-700">
                    <tr>
                        <td class="py-3"><span class="ros-box ros-box-blue">E4</span></td>
                        <td class="py-3 text-gray-200">Exempt</td>
                        <td class="py-3 text-right text-gray-500 font-mono text-lg">0.00</td>
                    </tr>
                    <tr>
                        <td class="py-3"><span class="ros-box ros-box-blue">D2</span></td>
                        <td class="py-3 text-gray-200">0% Home</td>
                        <td class="py-3 text-right text-white font-mono text-lg">{{ number_format($combinedAcquisitions['0'] ?? 0, 2) }}</td>
                    </tr>
                    <tr>
                        <td class="py-3"><span class="ros-box ros-box-blue">C6</span></td>
                        <td class="py-3 text-gray-200">4.8%</td>
                        <td class="py-3 text-right text-gray-500 font-mono text-lg">0.00</td>
                    </tr>
                    <tr>
                        <td class="py-3"><span class="ros-box ros-box-blue">BC6</span></td>
                        <td class="py-3 text-gray-200">9%</td>
                        <td class="py-3 text-right text-white font-mono text-lg">{{ number_format($combinedAcquisitions['9'] ?? 0, 2) }}</td>
                    </tr>
                    <tr>
                        <td class="py-3"><span class="ros-box ros-box-blue">AC6</span></td>
                        <td class="py-3 text-gray-200">13.5%</td>
                        <td class="py-3 text-right text-white font-mono text-lg">{{ number_format($combinedAcquisitions['13.5'] ?? 0, 2) }}</td>
                    </tr>
                    <tr>
                        <td class="py-3"><span class="ros-box ros-box-blue">B6</span></td>
                        <td class="py-3 text-gray-200">FlatFarm</td>
                        <td class="py-3 text-right text-gray-500 font-mono text-lg">0.00</td>
                    </tr>
                    <tr>
                        <td class="py-3"><span class="ros-box ros-box-blue">P2</span></td>
                        <td class="py-3 text-gray-200">Std Rate</td>
                        <td class="py-3 text-right text-white font-mono text-lg">{{ number_format($combinedAcquisitions['23'] ?? 0, 2) }}</td>
                    </tr>
                    <tr class="border-t-2 border-gray-600">
                        <td class="py-3"><span class="ros-box ros-box-blue">Z2</span></td>
                        <td class="py-3 font-bold text-gray-200">Total</td>
                        <td class="py-3 text-right text-blue-400 font-mono text-xl font-bold">{{ number_format($combinedAcquisitionsTotal, 2) }}</td>
                    </tr>
                    <tr class="border-t border-gray-700">
                        <td class="py-3"><span class="ros-box ros-box-blue">PA2</span></td>
                        <td class="py-3 text-gray-200">Postponed Accounting</td>
                        <td class="py-3 text-right text-white font-mono text-lg">{{ number_format($postponedAccounting, 2) }}</td>
                    </tr>
                </tbody>
            </table>
            <p class="text-yellow-500 text-xs mt-3 font-semibold">Figures already included in T1/T2 totals below. Shown for ROS cross-reference only.</p>
        </div>

        {{-- ============================================================ --}}
        {{-- ROS SECTION 3: GOODS OR SERVICES PURCHASED FOR RESALE       --}}
        {{-- ============================================================ --}}
        <div class="bg-gray-800 rounded-lg p-6 mb-6 print-section">
            <h2 class="text-lg font-bold text-gray-100 mb-1">
                <span class="text-green-400">Section 3</span> &mdash; Goods or Services Purchased for Resale
            </h2>
            <p class="text-gray-500 text-xs mb-4">&euro; Values Excluding VAT</p>

            <table class="w-full">
                <thead>
                    <tr class="border-b border-gray-700">
                        <th class="text-left text-gray-400 pb-2 py-2 w-16">ROS Box</th>
                        <th class="text-left text-gray-400 pb-2 py-2">Description</th>
                        <th class="text-right text-gray-400 pb-2 py-2 w-40">Net Amount (&euro;)</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-700">
                    <tr>
                        <td class="py-3"><span class="ros-box ros-box-green">E5</span></td>
                        <td class="py-3 text-gray-200">Exempt</td>
                        <td class="py-3 text-right text-gray-500 font-mono text-lg">0.00</td>
                    </tr>
                    <tr>
                        <td class="py-3"><span class="ros-box ros-box-green">J1</span></td>
                        <td class="py-3 text-gray-200">0% Home</td>
                        <td class="py-3 text-right text-white font-mono text-lg">{{ number_format($goods['0'] ?? 0, 2) }}</td>
                    </tr>
                    <tr>
                        <td class="py-3"><span class="ros-box ros-box-green">H5</span></td>
                        <td class="py-3 text-gray-200">4.8%</td>
                        <td class="py-3 text-right text-gray-500 font-mono text-lg">0.00</td>
                    </tr>
                    <tr>
                        <td class="py-3"><span class="ros-box ros-box-green">BH5</span></td>
                        <td class="py-3 text-gray-200">9%</td>
                        <td class="py-3 text-right text-white font-mono text-lg">{{ number_format($goods['9'] ?? 0, 2) }}</td>
                    </tr>
                    <tr>
                        <td class="py-3"><span class="ros-box ros-box-green">AH5</span></td>
                        <td class="py-3 text-gray-200">13.5%</td>
                        <td class="py-3 text-right text-white font-mono text-lg">{{ number_format($goods['13.5'] ?? 0, 2) }}</td>
                    </tr>
                    <tr>
                        <td class="py-3"><span class="ros-box ros-box-green">G5</span></td>
                        <td class="py-3 text-gray-200">FlatFarm</td>
                        <td class="py-3 text-right text-gray-500 font-mono text-lg">0.00</td>
                    </tr>
                    <tr>
                        <td class="py-3"><span class="ros-box ros-box-green">R1</span></td>
                        <td class="py-3 text-gray-200">Std Rate</td>
                        <td class="py-3 text-right text-white font-mono text-lg">{{ number_format($goods['23'] ?? 0, 2) }}</td>
                    </tr>
                    <tr class="border-t-2 border-gray-600">
                        <td class="py-3"><span class="ros-box ros-box-green">Z3</span></td>
                        <td class="py-3 font-bold text-gray-200">Total</td>
                        <td class="py-3 text-right text-green-400 font-mono text-xl font-bold">{{ number_format($goodsTotal, 2) }}</td>
                    </tr>
                </tbody>
            </table>
        </div>

        {{-- ============================================================ --}}
        {{-- ROS SECTION 4: OTHER DEDUCTIBLE GOODS & SERVICES            --}}
        {{-- ============================================================ --}}
        <div class="bg-gray-800 rounded-lg p-6 mb-6 print-section">
            <h2 class="text-lg font-bold text-gray-100 mb-1">
                <span class="text-yellow-400">Section 4</span> &mdash; Other Deductible Goods &amp; Services (Not for Resale)
            </h2>
            <p class="text-gray-500 text-xs mb-4">&euro; Values Excluding VAT</p>

            <table class="w-full">
                <thead>
                    <tr class="border-b border-gray-700">
                        <th class="text-left text-gray-400 pb-2 py-2 w-16">ROS Box</th>
                        <th class="text-left text-gray-400 pb-2 py-2">Description</th>
                        <th class="text-right text-gray-400 pb-2 py-2 w-40">Net Amount (&euro;)</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-700">
                    <tr>
                        <td class="py-3"><span class="ros-box ros-box-yellow">E6</span></td>
                        <td class="py-3 text-gray-200">Exempt</td>
                        <td class="py-3 text-right text-gray-500 font-mono text-lg">0.00</td>
                    </tr>
                    <tr>
                        <td class="py-3"><span class="ros-box ros-box-yellow">J2</span></td>
                        <td class="py-3 text-gray-200">0% Home</td>
                        <td class="py-3 text-right text-white font-mono text-lg">{{ number_format($service['0'] ?? 0, 2) }}</td>
                    </tr>
                    <tr>
                        <td class="py-3"><span class="ros-box ros-box-yellow">H6</span></td>
                        <td class="py-3 text-gray-200">4.8%</td>
                        <td class="py-3 text-right text-gray-500 font-mono text-lg">0.00</td>
                    </tr>
                    <tr>
                        <td class="py-3"><span class="ros-box ros-box-yellow">BH6</span></td>
                        <td class="py-3 text-gray-200">9%</td>
                        <td class="py-3 text-right text-white font-mono text-lg">{{ number_format($service['9'] ?? 0, 2) }}</td>
                    </tr>
                    <tr>
                        <td class="py-3"><span class="ros-box ros-box-yellow">AH6</span></td>
                        <td class="py-3 text-gray-200">13.5%</td>
                        <td class="py-3 text-right text-white font-mono text-lg">{{ number_format($service['13.5'] ?? 0, 2) }}</td>
                    </tr>
                    <tr>
                        <td class="py-3"><span class="ros-box ros-box-yellow">G6</span></td>
                        <td class="py-3 text-gray-200">FlatFarm</td>
                        <td class="py-3 text-right text-gray-500 font-mono text-lg">0.00</td>
                    </tr>
                    <tr>
                        <td class="py-3"><span class="ros-box ros-box-yellow">R2</span></td>
                        <td class="py-3 text-gray-200">Std Rate</td>
                        <td class="py-3 text-right text-white font-mono text-lg">{{ number_format($service['23'] ?? 0, 2) }}</td>
                    </tr>
                    <tr class="border-t-2 border-gray-600">
                        <td class="py-3"><span class="ros-box ros-box-yellow">Z5</span></td>
                        <td class="py-3 font-bold text-gray-200">Total</td>
                        <td class="py-3 text-right text-yellow-400 font-mono text-xl font-bold">{{ number_format($serviceTotal, 2) }}</td>
                    </tr>
                    <tr class="border-t border-gray-700">
                        <td class="py-3"><span class="ros-box ros-box-yellow">PA4</span></td>
                        <td class="py-3 text-gray-200">Postponed Accounting</td>
                        <td class="py-3 text-right text-white font-mono text-lg">{{ number_format($postponedAccounting, 2) }}</td>
                    </tr>
                </tbody>
            </table>
        </div>

        {{-- ============================================================ --}}
        {{-- SECTION 4: EXCLUDED FROM RTD (Reconciliation Only)           --}}
        {{-- ============================================================ --}}
        <div class="bg-gray-800 rounded-lg p-6 mb-6 print-section">
            <h2 class="text-lg font-bold text-gray-100 mb-1">
                <span class="text-gray-400">Excluded from RTD</span>
            </h2>
            <p class="text-gray-500 text-xs mb-4">These amounts are NOT reported on the RTD. Shown for reconciliation against invoice gross totals only.</p>

            <table class="w-full">
                <thead>
                    <tr class="border-b border-gray-700">
                        <th class="text-left text-gray-400 pb-2 py-2">Category</th>
                        <th class="text-right text-gray-400 pb-2 py-2 w-40">Amount (&euro;)</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-700">
                    <tr>
                        <td class="py-3 text-gray-200">Freight / Delivery charges</td>
                        <td class="py-3 text-right text-white font-mono text-lg">{{ number_format($excluded['freight'] ?? 0, 2) }}</td>
                    </tr>
                    <tr>
                        <td class="py-3 text-gray-200">Deposits (bottle / container)</td>
                        <td class="py-3 text-right text-white font-mono text-lg">{{ number_format($excluded['deposits'] ?? 0, 2) }}</td>
                    </tr>
                    <tr>
                        <td class="py-3 text-gray-200">DRS (Deposit Return Scheme)</td>
                        <td class="py-3 text-right text-white font-mono text-lg">{{ number_format($excluded['drs'] ?? 0, 2) }}</td>
                    </tr>
                    <tr>
                        <td class="py-3 text-gray-200">Non-retail items (service/overhead)</td>
                        <td class="py-3 text-right text-white font-mono text-lg">{{ number_format($excluded['service_overhead'] ?? 0, 2) }}</td>
                    </tr>
                    <tr>
                        <td class="py-3 text-gray-200">VAT charged on invoices</td>
                        <td class="py-3 text-right text-white font-mono text-lg">{{ number_format($excluded['vat'] ?? 0, 2) }}</td>
                    </tr>
                    <tr class="border-t-2 border-gray-600">
                        <td class="py-3 font-bold text-gray-200">Total excluded</td>
                        <td class="py-3 text-right text-gray-300 font-mono text-xl font-bold">{{ number_format($excludedTotal, 2) }}</td>
                    </tr>
                </tbody>
            </table>
        </div>

        {{-- ============================================================ --}}
        {{-- SECTION 5: INVOICE RECONCILIATION (Informational)            --}}
        {{-- ============================================================ --}}
        @php
            $netSubtotal = round($goodsTotal + $serviceTotal + $excludedNonVat, 2);
            $difference = round($invoiceTotalSum - $breakdownSum, 2);
        @endphp
        <div class="bg-gray-800 rounded-lg p-6 mb-6 print-section">
            <h2 class="text-lg font-bold text-gray-100 mb-1">
                Invoice Reconciliation
                <span class="text-gray-500 font-normal text-sm ml-2">(Informational)</span>
            </h2>
            <p class="text-gray-500 text-xs mb-4">
                Cross-check of RTD breakdown against invoice gross totals.
                This section does not affect the RTD submission.
            </p>

            <table class="w-full">
                <tbody class="divide-y divide-gray-700">
                    <tr>
                        <td class="py-2 text-gray-300">T1 Goods for Resale <span class="ros-box ros-box-green text-xs ml-1">Z3</span></td>
                        <td class="py-2 text-right text-green-400 font-mono">{{ number_format($goodsTotal, 2) }}</td>
                    </tr>
                    <tr>
                        <td class="py-2 text-gray-300">T2 Other Deductible <span class="ros-box ros-box-yellow text-xs ml-1">Z5</span></td>
                        <td class="py-2 text-right text-yellow-400 font-mono">{{ number_format($serviceTotal, 2) }}</td>
                    </tr>
                    <tr>
                        <td class="py-2 text-gray-300">Excluded (freight, deposits, DRS)</td>
                        <td class="py-2 text-right text-gray-400 font-mono">{{ number_format($excludedNonVat, 2) }}</td>
                    </tr>
                    <tr class="border-t border-gray-600">
                        <td class="py-2 text-gray-200 font-semibold">Net Subtotal</td>
                        <td class="py-2 text-right text-gray-200 font-mono font-semibold">{{ number_format($netSubtotal, 2) }}</td>
                    </tr>
                    <tr>
                        <td class="py-2 text-gray-300 text-lg">VAT</td>
                        <td class="py-2 text-right text-white font-mono text-lg">{{ number_format($vatTotal, 2) }}</td>
                    </tr>
                    <tr class="border-t-2 border-gray-500">
                        <td class="py-3 font-bold text-gray-100">Total <span class="text-gray-400 font-normal text-sm">(T1 + T2 + Excluded + VAT)</span></td>
                        <td class="py-3 text-right text-teal-400 font-mono text-xl font-bold">{{ number_format($breakdownSum, 2) }}</td>
                    </tr>
                    <tr class="border-t-4 border-gray-600">
                        <td class="py-3 font-bold text-gray-100">Invoice Gross Totals</td>
                        <td class="py-3 text-right text-white font-mono text-xl font-bold">{{ number_format($invoiceTotalSum, 2) }}</td>
                    </tr>
                    <tr class="border-t-2 border-gray-600">
                        <td class="py-3 text-gray-200">
                            Difference
                        </td>
                        <td class="py-3 text-right font-mono text-lg font-bold">
                            @if(abs($difference) < 0.01)
                                <span class="inline-flex items-center gap-2">
                                    <span class="text-green-400">{{ number_format($difference, 2) }}</span>
                                    <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-green-900 text-green-300">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                                        </svg>
                                        Balanced
                                    </span>
                                </span>
                            @else
                                <span class="inline-flex items-center gap-2">
                                    <span class="text-amber-400">{{ number_format($difference, 2) }}</span>
                                    <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-amber-900/50 text-amber-300">
                                        Unmatched
                                    </span>
                                </span>
                            @endif
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        {{-- ============================================================ --}}
        {{-- SECTION 6: INCLUDED INVOICES                                 --}}
        {{-- ============================================================ --}}
        <div class="bg-gray-800 rounded-lg overflow-hidden mb-6 print-section">
            <div class="px-6 py-4 border-b border-gray-700">
                <h2 class="text-lg font-bold text-gray-100">
                    Included Invoices ({{ $submission->invoices->count() }})
                </h2>
            </div>
            <table class="min-w-full divide-y divide-gray-700">
                <thead class="bg-gray-900">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase">Invoice #</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase">Supplier</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase">Date</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-400 uppercase">Invoice Total</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase">Type</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase">Origin</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-400 uppercase">RTD Amount</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-700">
                    @foreach($submission->invoices->sortBy('invoice_date') as $invoice)
                        @php
                            $snapshot = $invoice->rtd_snapshot ?? [];
                            $isService = ($snapshot['breakdown']['stats']['is_service'] ?? false)
                                || ($invoice->supplier && $invoice->supplier->rtd_classification === 'service_overhead');
                            if ($isService) {
                                $breakdown = $snapshot['breakdown']['service_overhead'] ?? $snapshot['breakdown']['goods_for_resale'] ?? [];
                            } else {
                                $breakdown = $snapshot['breakdown']['goods_for_resale'] ?? [];
                            }
                            $rtdTotal = array_sum(array_map('floatval', $breakdown));

                            $vatTreatment = $invoice->supplier->vat_treatment ?? 'irish_vat';
                            $originLabel = match($vatTreatment) {
                                'eu_goods_zero_rated', 'eu_reverse_charge_services' => 'EU',
                                'postponed_import' => 'Non-EU (PA)',
                                'outside_scope_or_exempt' => 'Non-EU',
                                default => 'IE',
                            };
                            $originClass = match($vatTreatment) {
                                'eu_goods_zero_rated', 'eu_reverse_charge_services' => 'bg-blue-900 text-blue-300',
                                'postponed_import', 'outside_scope_or_exempt' => 'bg-gray-700 text-gray-300',
                                default => 'bg-teal-900 text-teal-300',
                            };
                        @endphp
                        <tr class="hover:bg-gray-750">
                            <td class="px-4 py-3 text-gray-200 font-mono">#{{ $invoice->invoice_number }}</td>
                            <td class="px-4 py-3 text-gray-300 text-sm">{{ $invoice->supplier_name }}</td>
                            <td class="px-4 py-3 text-gray-300">{{ $invoice->invoice_date->format('d/m/Y') }}</td>
                            <td class="px-4 py-3 text-right text-gray-300 font-mono">{{ number_format($invoice->total_amount, 2) }}</td>
                            <td class="px-4 py-3">
                                @if($isService)
                                    <span class="px-1.5 py-0.5 text-xs rounded bg-yellow-900 text-yellow-300">T2</span>
                                @else
                                    <span class="px-1.5 py-0.5 text-xs rounded bg-green-900 text-green-300">T1</span>
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                <span class="px-1.5 py-0.5 text-xs rounded {{ $originClass }}">{{ $originLabel }}</span>
                            </td>
                            <td class="px-4 py-3 text-right font-mono font-semibold {{ $isService ? 'text-yellow-400' : 'text-green-400' }}">
                                {{ number_format($rtdTotal, 2) }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</x-admin-layout>
