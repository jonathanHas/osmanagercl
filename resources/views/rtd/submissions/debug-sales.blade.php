<x-admin-layout>
    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
        {{-- Header --}}
        <div class="flex justify-between items-center mb-6">
            <div>
                <h1 class="text-2xl font-bold text-white">Section 1 Sales Debug</h1>
                <p class="text-gray-400 mt-1">
                    Submission #{{ $submission->id }} &mdash;
                    {{ $periodStart->format('d M Y') }} to {{ $periodEnd->format('d M Y') }}
                </p>
            </div>
            <a href="{{ route('rtd.submissions.report', $submission) }}"
               class="bg-gray-600 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded inline-flex items-center">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                </svg>
                Back to Report
            </a>
        </div>

        {{-- Summary Banner --}}
        @if($hasGaps)
            <div class="bg-red-900/50 border border-red-500 rounded-lg p-4 mb-6">
                <h2 class="text-red-400 font-bold text-lg">Coverage Gap Detected</h2>
                <p class="text-red-300 mt-1">
                    {{ count($gaps) }} date range(s) within the RTD period are NOT covered by any VAT return.
                    These dates are silently dropped from the Section 1 calculation.
                </p>
            </div>
        @else
            <div class="bg-green-900/50 border border-green-500 rounded-lg p-4 mb-6">
                <h2 class="text-green-400 font-bold text-lg">Full Coverage</h2>
                <p class="text-green-300 mt-1">All dates within the RTD period are covered by VAT returns.</p>
            </div>
        @endif

        {{-- 1. VAT Returns Found --}}
        <div class="bg-gray-800 rounded-lg p-6 mb-6">
            <h2 class="text-lg font-bold text-white mb-3">
                VAT Returns Found: {{ $vatReturnCount }}
            </h2>

            @if($vatReturnCount === 0)
                <p class="text-yellow-400">No VAT returns found for this period. Tier 3 (direct sales_accounting_daily) would be used.</p>
            @else
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-gray-400 border-b border-gray-600">
                            <th class="text-left py-2 px-3">ID</th>
                            <th class="text-left py-2 px-3">Period</th>
                            <th class="text-left py-2 px-3">Return Period</th>
                            <th class="text-left py-2 px-3">Status</th>
                            <th class="text-center py-2 px-3">Has sales_vat_data?</th>
                            <th class="text-center py-2 px-3">Has by_rate?</th>
                            <th class="text-center py-2 px-3">Tier Used</th>
                            <th class="text-right py-2 px-3">0%</th>
                            <th class="text-right py-2 px-3">9%</th>
                            <th class="text-right py-2 px-3">13.5%</th>
                            <th class="text-right py-2 px-3">23%</th>
                            <th class="text-right py-2 px-3">Total</th>
                            <th class="text-right py-2 px-3">Paperin</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($vatReturns as $vr)
                            <tr class="border-b border-gray-700 {{ $vr['tier_used'] === 2 ? 'bg-yellow-900/20' : '' }}">
                                <td class="py-2 px-3 text-gray-300">#{{ $vr['id'] }}</td>
                                <td class="py-2 px-3 text-white">{{ $vr['period_start'] }} &rarr; {{ $vr['period_end'] }}</td>
                                <td class="py-2 px-3 text-gray-300">{{ $vr['return_period'] }}</td>
                                <td class="py-2 px-3">
                                    <span class="px-2 py-0.5 rounded text-xs {{ $vr['status'] === 'draft' ? 'bg-yellow-800 text-yellow-300' : 'bg-green-800 text-green-300' }}">
                                        {{ $vr['status'] }}
                                    </span>
                                </td>
                                <td class="py-2 px-3 text-center">
                                    @if($vr['has_sales_vat_data'])
                                        <span class="text-green-400">Yes</span>
                                    @else
                                        <span class="text-red-400">No</span>
                                    @endif
                                </td>
                                <td class="py-2 px-3 text-center">
                                    @if($vr['has_by_rate'])
                                        <span class="text-green-400">Yes</span>
                                    @else
                                        <span class="text-red-400">No</span>
                                    @endif
                                </td>
                                <td class="py-2 px-3 text-center">
                                    <span class="px-2 py-0.5 rounded text-xs font-bold {{ $vr['tier_used'] === 1 ? 'bg-blue-800 text-blue-300' : 'bg-yellow-800 text-yellow-300' }}">
                                        Tier {{ $vr['tier_used'] }}
                                    </span>
                                </td>
                                <td class="py-2 px-3 text-right text-white">{{ number_format($vr['sales_by_rate']['0'], 2) }}</td>
                                <td class="py-2 px-3 text-right text-white">{{ number_format($vr['sales_by_rate']['9'], 2) }}</td>
                                <td class="py-2 px-3 text-right text-white">{{ number_format($vr['sales_by_rate']['13.5'], 2) }}</td>
                                <td class="py-2 px-3 text-right text-white">{{ number_format($vr['sales_by_rate']['23'], 2) }}</td>
                                <td class="py-2 px-3 text-right text-white font-bold">{{ number_format(array_sum($vr['sales_by_rate']), 2) }}</td>
                                <td class="py-2 px-3 text-right {{ $vr['paperin_gross'] > 0 ? 'text-orange-400' : 'text-gray-500' }}">
                                    {{ number_format($vr['paperin_gross'], 2) }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>

        {{-- 2. Coverage Gaps --}}
        <div class="bg-gray-800 rounded-lg p-6 mb-6">
            <h2 class="text-lg font-bold text-white mb-3">
                Coverage Gaps
                @if($hasGaps)
                    <span class="text-red-400 text-sm font-normal ml-2">({{ count($gaps) }} gap(s) found)</span>
                @else
                    <span class="text-green-400 text-sm font-normal ml-2">(none)</span>
                @endif
            </h2>

            @if($hasGaps)
                <p class="text-gray-400 text-sm mb-3">
                    These date ranges are within the RTD period but not covered by any VAT return.
                    Their sales data is currently <strong class="text-red-400">missing</strong> from Section 1.
                </p>
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-gray-400 border-b border-gray-600">
                            <th class="text-left py-2 px-3">Gap Period</th>
                            <th class="text-right py-2 px-3">0%</th>
                            <th class="text-right py-2 px-3">9%</th>
                            <th class="text-right py-2 px-3">13.5%</th>
                            <th class="text-right py-2 px-3">23%</th>
                            <th class="text-right py-2 px-3">Total</th>
                            <th class="text-right py-2 px-3">Paperin</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($gaps as $gap)
                            <tr class="border-b border-gray-700 bg-red-900/20">
                                <td class="py-2 px-3 text-red-300 font-medium">{{ $gap['start'] }} &rarr; {{ $gap['end'] }}</td>
                                <td class="py-2 px-3 text-right text-red-300">{{ number_format($gap['sales_by_rate']['0'], 2) }}</td>
                                <td class="py-2 px-3 text-right text-red-300">{{ number_format($gap['sales_by_rate']['9'], 2) }}</td>
                                <td class="py-2 px-3 text-right text-red-300">{{ number_format($gap['sales_by_rate']['13.5'], 2) }}</td>
                                <td class="py-2 px-3 text-right text-red-300">{{ number_format($gap['sales_by_rate']['23'], 2) }}</td>
                                <td class="py-2 px-3 text-right text-red-300 font-bold">{{ number_format($gap['total'], 2) }}</td>
                                <td class="py-2 px-3 text-right {{ $gap['paperin_gross'] > 0 ? 'text-orange-400' : 'text-gray-500' }}">
                                    {{ number_format($gap['paperin_gross'], 2) }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @else
                <p class="text-green-300 text-sm">All dates in the RTD period are covered by VAT returns. No gaps detected.</p>
            @endif
        </div>

        {{-- 3. Comparison Table --}}
        <div class="bg-gray-800 rounded-lg p-6 mb-6">
            <h2 class="text-lg font-bold text-white mb-3">Comparison</h2>
            <p class="text-gray-400 text-sm mb-3">
                Side-by-side: current snapshot, current aggregation (without gaps), fixed aggregation (with gaps), and sales accounting reference.
            </p>
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-gray-400 border-b border-gray-600">
                        <th class="text-left py-2 px-3">Source</th>
                        <th class="text-right py-2 px-3">0%</th>
                        <th class="text-right py-2 px-3">9%</th>
                        <th class="text-right py-2 px-3">13.5%</th>
                        <th class="text-right py-2 px-3">23%</th>
                        <th class="text-right py-2 px-3">Total</th>
                    </tr>
                </thead>
                <tbody>
                    {{-- Current snapshot --}}
                    <tr class="border-b border-gray-700">
                        <td class="py-2 px-3 text-gray-300">
                            Current Snapshot
                            <span class="text-xs text-gray-500 ml-1">({{ $snapshotSource }}, {{ $snapshotVatCount }} VR)</span>
                        </td>
                        @foreach(['0', '9', '13.5', '23'] as $rate)
                            @php
                                $snVal = $snapshotSales[$rate] ?? 0;
                                $refVal = $referenceSales[$rate] ?? 0;
                                $matches = abs($snVal - $refVal) < 0.01;
                            @endphp
                            <td class="py-2 px-3 text-right {{ $matches ? 'text-white' : 'text-red-400 font-bold' }}">
                                {{ number_format($snVal, 2) }}
                            </td>
                        @endforeach
                        <td class="py-2 px-3 text-right {{ abs(array_sum($snapshotSales) - array_sum($referenceSales)) < 0.01 ? 'text-white' : 'text-red-400 font-bold' }}">
                            {{ number_format(array_sum($snapshotSales), 2) }}
                        </td>
                    </tr>

                    {{-- Aggregated (without gaps) --}}
                    <tr class="border-b border-gray-700 bg-yellow-900/10">
                        <td class="py-2 px-3 text-yellow-300">
                            Aggregated (without gaps)
                            <span class="text-xs text-gray-500 ml-1">(live recalc)</span>
                        </td>
                        @foreach(['0', '9', '13.5', '23'] as $rate)
                            <td class="py-2 px-3 text-right text-yellow-300">{{ number_format($aggregatedSales[$rate], 2) }}</td>
                        @endforeach
                        <td class="py-2 px-3 text-right text-yellow-300 font-bold">{{ number_format(array_sum($aggregatedSales), 2) }}</td>
                    </tr>

                    {{-- Fixed (with gaps filled) --}}
                    <tr class="border-b border-gray-700 bg-green-900/10">
                        <td class="py-2 px-3 text-green-300">
                            Fixed (gaps filled)
                            <span class="text-xs text-gray-500 ml-1">(proposed fix)</span>
                        </td>
                        @foreach(['0', '9', '13.5', '23'] as $rate)
                            @php
                                $fixVal = $fixedSales[$rate] ?? 0;
                                $refVal = $referenceSales[$rate] ?? 0;
                                $matches = abs($fixVal - $refVal) < 0.01;
                            @endphp
                            <td class="py-2 px-3 text-right {{ $matches ? 'text-green-400' : 'text-yellow-400' }}">
                                {{ number_format($fixVal, 2) }}
                            </td>
                        @endforeach
                        <td class="py-2 px-3 text-right text-green-400 font-bold">{{ number_format(array_sum($fixedSales), 2) }}</td>
                    </tr>

                    {{-- Reference: full sales_accounting_daily --}}
                    <tr class="border-b border-gray-700 bg-blue-900/10">
                        <td class="py-2 px-3 text-blue-300">
                            Sales Accounting Reference
                            <span class="text-xs text-gray-500 ml-1">(sales_accounting_daily, full period)</span>
                        </td>
                        @foreach(['0', '9', '13.5', '23'] as $rate)
                            <td class="py-2 px-3 text-right text-blue-300">{{ number_format($referenceSales[$rate], 2) }}</td>
                        @endforeach
                        <td class="py-2 px-3 text-right text-blue-300 font-bold">{{ number_format(array_sum($referenceSales), 2) }}</td>
                    </tr>
                </tbody>
            </table>

            {{-- Difference rows --}}
            <div class="mt-4 text-sm">
                @php
                    $snapshotDiff = round(array_sum($snapshotSales) - array_sum($referenceSales), 2);
                    $fixedDiff = round(array_sum($fixedSales) - array_sum($referenceSales), 2);
                @endphp
                <p class="{{ abs($snapshotDiff) < 0.01 ? 'text-green-400' : 'text-red-400' }}">
                    Snapshot vs Reference difference: <strong>{{ number_format($snapshotDiff, 2) }}</strong>
                </p>
                <p class="{{ abs($fixedDiff) < 0.01 ? 'text-green-400' : 'text-yellow-400' }}">
                    Fixed vs Reference difference: <strong>{{ number_format($fixedDiff, 2) }}</strong>
                    @if(abs($fixedDiff) >= 0.01)
                        <span class="text-gray-500">(remaining diff may be from Tier 1 stale data on a VAT return)</span>
                    @endif
                </p>
            </div>
        </div>

        {{-- 4. Per-VAT-Return raw sales_vat_data --}}
        @foreach($vatReturns as $vr)
            @if($vr['has_sales_vat_data'])
                <div class="bg-gray-800 rounded-lg p-6 mb-6">
                    <h2 class="text-lg font-bold text-white mb-3">
                        VAT Return #{{ $vr['id'] }} &mdash; Raw sales_vat_data
                    </h2>
                    <pre class="bg-gray-900 rounded p-4 text-xs text-gray-300 overflow-x-auto">{{ json_encode($vr['raw_sales_vat_data'], JSON_PRETTY_PRINT) }}</pre>
                </div>
            @endif
        @endforeach

        {{-- Paperin Summary --}}
        <div class="bg-gray-800 rounded-lg p-6 mb-6">
            <h2 class="text-lg font-bold text-white mb-3">Paperin (Gift Voucher) Summary</h2>
            <p class="text-gray-400 text-sm mb-3">
                Full-period paperin gross from sales_accounting_daily: <strong class="text-orange-400">{{ number_format($referencePaperin, 2) }}</strong>
            </p>
            <p class="text-gray-400 text-sm">
                This amount is deducted from 0% net to prevent double-counting gift voucher revenue.
            </p>
        </div>
    </div>
</x-admin-layout>
