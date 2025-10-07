@php
    $displayItems = $displayItems ?? $orderSession->items;
    $reviewCount = $orderSession->items->where('review_priority', 'review')->count();
    $standardCount = $orderSession->items->where('review_priority', 'standard')->count();
    $safeCount = $orderSession->items->where('review_priority', 'safe')->count();
    $totalItems = $orderSession->items->count();
    $globalMaxWeeklySales = $displayItems->map(function ($item) {
        $context = $item->context_data ?? [];
        $weekly = $context['weekly_sales'] ?? [];
        if (empty($weekly)) {
            return 0;
        }

        return collect($weekly)->map(static fn ($week) => (float) ($week['units'] ?? 0))->max() ?? 0;
    })->max() ?? 0;

    if ($globalMaxWeeklySales <= 0) {
        $globalMaxWeeklySales = 1;
    }
@endphp

<!-- Filter Bar -->
<div class="bg-white rounded-lg shadow mb-6 p-4 flex items-center justify-between">
    <div class="flex space-x-2">
        <button class="px-4 py-2 bg-red-600 text-white rounded-lg font-medium">🔴 Review ({{ $reviewCount }})</button>
        <button class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg font-medium hover:bg-gray-200">🟡 Standard ({{ $standardCount }})</button>
        <button class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg font-medium hover:bg-gray-200">🟢 Safe ({{ $safeCount }})</button>
        <button class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg font-medium hover:bg-gray-200">All ({{ $totalItems }})</button>
    </div>
    <div class="flex items-center space-x-3">
        @isset($backLink)
            <a href="{{ $backLink }}" class="px-4 py-2 bg-gray-600 text-white rounded-lg font-medium hover:bg-gray-700">
                ← Back
            </a>
        @endisset
        @isset($primaryActions)
            @foreach($primaryActions as $action)
                {!! $action !!}
            @endforeach
        @elseif(isset($primaryAction))
            {!! $primaryAction !!}
        @endisset
    </div>
</div>

<!-- Product Table -->
<div class="bg-white rounded-lg shadow overflow-hidden">
    <table class="min-w-full divide-y divide-gray-200">
        <thead class="bg-gray-50">
            <tr>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase w-8"></th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Product</th>
                <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase w-32">Stock Status</th>
                <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase" style="width: 320px;">Stock Levels & Sales Trend</th>
                <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase w-32">Suggested</th>
                <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase w-40">Order Qty</th>
                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase w-32">Cost</th>
                <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase w-32">Action</th>
            </tr>
        </thead>
        <tbody class="bg-white divide-y divide-gray-200">
            @foreach($displayItems as $item)
                @php
                    $product = $item->product;
                    $contextData = $item->context_data ?? [];
                    $currentStock = $contextData['current_stock'] ?? 0;
                    $avgWeeklySales = $contextData['avg_weekly_sales'] ?? 0;
                    $weeklySales = $contextData['weekly_sales'] ?? [];
                    $weekLabels = array_map(static fn ($week) => $week['label'] ?? '', $weeklySales);
                    $weekUnitsRaw = array_map(static fn ($week) => (float) ($week['units'] ?? 0), $weeklySales);
                    if (empty($weekLabels)) {
                        $weekLabels = array_map(static fn ($index) => 'W'.($index + 1), range(0, max(($contextData['sales_history_weeks'] ?? 8) - 1, 0)));
                    }
                    if (count($weekUnitsRaw) !== count($weekLabels)) {
                        $weekUnitsRaw = array_pad($weekUnitsRaw, count($weekLabels), 0.0);
                    }
                    $weekUnits = array_map(static fn ($value) => round($value, 2), $weekUnitsRaw);
                    $peakWeeklySales = $contextData['peak_weekly_sales'] ?? (count($weekUnits) > 0 ? max($weekUnits) : $avgWeeklySales);
                    if ($peakWeeklySales <= 0 && $avgWeeklySales > 0) {
                        $peakWeeklySales = $avgWeeklySales;
                    }
                    $caseUnits = $contextData['case_units'] ?? 1;
                    $isCaseProduct = $contextData['is_case_product'] ?? ($caseUnits > 1);
                    $suggestedUnits = (float) ($item->suggested_quantity ?? 0);
                    $suggestedCases = $caseUnits > 1
                        ? (float) ($item->suggested_cases ?? ($caseUnits > 0 ? $suggestedUnits / $caseUnits : 0))
                        : $suggestedUnits;
                    $finalUnits = (float) ($item->final_quantity ?? $suggestedUnits);
                    $finalCases = $caseUnits > 1
                        ? (float) ($item->final_cases ?? ($caseUnits > 0 ? $finalUnits / $caseUnits : 0))
                        : $finalUnits;
                    $displayOrderQuantity = $isCaseProduct ? $finalCases : $finalUnits;
                    $suggestedDisplayQuantity = $isCaseProduct ? $suggestedCases : $suggestedUnits;
                    $quantityLabel = $isCaseProduct ? 'cases' : 'units';
                    $quantityPrecision = $isCaseProduct ? 3 : 0;
                    $afterStock = $currentStock + $finalUnits;
                    $referenceDemand = max($globalMaxWeeklySales, 1);

                    // Calculate percentages relative to global max
                    $currentPct = $referenceDemand > 0 ? ($currentStock / $referenceDemand) * 100 : 0;
                    $afterPct = $referenceDemand > 0 ? ($afterStock / $referenceDemand) * 100 : 100;
                    $currentPositiveHeight = $currentPct > 0 ? min($currentPct, 180) : 0;
                    $currentNegativeHeight = $currentPct < 0 ? min(abs($currentPct), 180) : 0;
                    $afterPositiveHeight = $afterPct > 0 ? min($afterPct, 220) : 0;
                    $afterNegativeHeight = $afterPct < 0 ? min(abs($afterPct), 180) : 0;

                    // Priority colors
                    $borderColor = match($item->review_priority) {
                        'review' => 'border-red-500',
                        'standard' => 'border-yellow-500',
                        default => 'border-green-500'
                    };
                    $iconBg = match($item->review_priority) {
                        'review' => 'bg-red-100 text-red-600',
                        'standard' => 'bg-yellow-100 text-yellow-600',
                        default => 'bg-green-100 text-green-600'
                    };
                    $stockColor = $currentPct < 50 ? 'red' : ($currentPct < 100 ? 'yellow' : 'green');
                @endphp

                <tr class="hover:bg-{{ $stockColor }}-50 border-l-4 {{ $borderColor }}" style="height: 180px;">
                    <td class="px-4 py-4">
                        <div class="w-8 h-8 {{ $iconBg }} rounded-full flex items-center justify-center">
                            <span class="font-bold text-sm">
                                @switch($item->review_priority)
                                    @case('review')
                                        !
                                        @break
                                    @case('standard')
                                        ●
                                        @break
                                    @case('safe')
                                        ✓
                                        @break
                                @endswitch
                            </span>
                        </div>
                    </td>
                    <td class="px-4 py-4">
                        <div class="font-medium text-gray-900">{!! strip_tags(html_entity_decode($product->NAME ?? 'Unknown Product')) !!}</div>
                        <div class="text-sm text-gray-500">
                            Code: {{ $product->CODE ?? 'N/A' }} • {{ $avgWeeklySales > 0 ? number_format($avgWeeklySales, 1) : '0' }}/week avg
                        </div>
                    </td>
                    <td class="px-4 py-4">
                        <div class="text-center">
                            <div class="text-2xl font-bold text-{{ $stockColor }}-600">{{ number_format($currentStock, 0) }}</div>
                            <div class="mt-1 w-full bg-gray-200 rounded-full h-2">
                                <div class="bg-{{ $stockColor }}-500 h-2 rounded-full" style="width: {{ max(min($currentPct, 100), 0) }}%"></div>
                            </div>
                            <div class="text-xs text-{{ $stockColor }}-600 font-medium mt-1">
                                @if($currentPct < 30) 🚨 CRITICAL
                                @elseif($currentPct < 50) ⚠️ Low
                                @elseif($currentPct < 100) ✓ Moderate
                                @else ✓ Good
                                @endif
                            </div>
                        </div>
                    </td>
                    <td class="px-4 py-4 align-bottom">
                        <!-- Bars and Chart Container -->
                        <div class="flex items-end gap-3" style="min-height: 140px; position: relative; padding: 16px 0;">
                            <!-- Reference Grid Lines -->
                            <div class="absolute inset-x-0" style="top: 16px; height: 72px; pointer-events: none;">
                                <div class="absolute inset-x-0 top-0 border-t border-gray-300 border-dashed"></div>
                                <div class="absolute inset-x-0" style="top: 36px; border-top: 1px dashed rgba(148, 163, 184, 0.5);"></div>
                                <div class="absolute inset-x-0 bottom-0 border-t border-gray-200"></div>
                                <div class="absolute left-2 -top-2 text-[10px] text-gray-500 font-medium">100%</div>
                                <div class="absolute left-2" style="top: 34px;">
                                    <span class="text-[10px] text-gray-400 font-medium">50%</span>
                                </div>
                                <div class="absolute left-2 -bottom-3 text-[10px] text-gray-400 font-medium">0%</div>
                            </div>
                            <!-- Current Stock Bar (Left) -->
                            <div style="width: 46px;">
                                <div class="text-xs font-medium text-gray-600 mb-1 text-center">Now</div>
                                <div class="w-10 bg-gray-200 rounded-full relative overflow-visible mx-auto" style="height: 72px;">
                                    <div class="absolute top-0 left-0 right-0 h-0.5 bg-gray-400 z-10" title="100% = Highest weekly sales in list"></div>
                                    @if($currentNegativeHeight > 0)
                                        <div class="absolute top-0 left-0 right-0 bg-red-500 rounded-full" style="height: {{ $currentNegativeHeight }}%;" title="Short {{ number_format(abs($currentStock), 0) }} units ({{ round(abs($currentPct)) }}% of max week)"></div>
                                    @endif
                                    @if($currentPositiveHeight > 0)
                                        <div class="absolute bottom-0 left-0 right-0 bg-{{ $stockColor }}-500 rounded-full" style="height: {{ $currentPositiveHeight }}%;" title="{{ max($currentStock, 0) }} units = {{ round($currentPct) }}% of max week"></div>
                                    @endif
                                    @if($currentPositiveHeight > 100)
                                        <div class="absolute -top-4 left-1/2 transform -translate-x-1/2 text-[10px] text-{{ $stockColor }}-600 font-semibold">+{{ round($currentPct - 100) }}%</div>
                                    @endif
                                </div>
                            </div>

                            <!-- Chart -->
                            <div style="height: 60px; position: relative; flex: 1;">
                                <canvas id="chart_{{ $item->id }}"></canvas>
                            </div>

                            <!-- After Delivery Bar (Right) -->
                            <div style="width: 46px;">
                                <div class="text-xs font-medium text-gray-600 mb-1 text-center">After</div>
                                <div class="w-10 bg-gray-200 rounded-full relative overflow-visible mx-auto" style="height: 72px;">
                                    <div class="absolute top-0 left-0 right-0 h-0.5 bg-gray-400 z-10" title="100% = Highest weekly sales in list"></div>
                                    @if($afterNegativeHeight > 0)
                                        <div class="absolute top-0 left-0 right-0 bg-red-500 rounded-full" style="height: {{ $afterNegativeHeight }}%;" title="Short {{ number_format(abs($afterStock), 0) }} units ({{ round(abs($afterPct)) }}% of max week)"></div>
                                    @endif
                                    @if($afterPositiveHeight > 0)
                                        <div class="absolute bottom-0 left-0 right-0 bg-green-500 rounded-full" style="height: {{ $afterPositiveHeight }}%;" title="{{ max($afterStock, 0) }} units = {{ round($afterPct) }}% of max week"></div>
                                    @endif
                                    @if($afterPositiveHeight > 100)
                                        <div class="absolute -top-4 left-1/2 transform -translate-x-1/2 text-[10px] text-green-600 font-semibold">+{{ round($afterPct - 100) }}%</div>
                                    @endif
                                </div>
                            </div>
                        </div>

                        <!-- Text Labels Row -->
                        <div class="flex gap-3 mt-1">
                            <div class="text-center" style="width: 40px;">
                                <div class="text-xs font-bold text-{{ $stockColor }}-600">
                                    {{ number_format($currentStock, 0) }}
                                    @if($isCaseProduct)
                                        <span class="block text-[10px] text-gray-500">{{ number_format($currentStock / max($caseUnits, 1), 1) }} cases</span>
                                    @else
                                        <span class="block text-[10px] text-gray-500">units</span>
                                    @endif
                                </div>
                                <div class="text-xs text-gray-500">{{ round($currentPct) }}% max</div>
                            </div>
                            <div class="flex-1 text-center">
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-700">
                                    Weekly avg: {{ number_format($avgWeeklySales, 1) }} • Peak: {{ number_format($peakWeeklySales, 1) }}
                                </span>
                                @if(isset($contextData['coverage_weeks']))
                                    <div class="text-[11px] text-gray-500 mt-0.5">
                                        Covering {{ number_format($contextData['coverage_weeks'], 1) }} wk (target {{ number_format($contextData['target_weeks'] ?? $contextData['coverage_weeks'], 1) }} wk)
                                    </div>
                                @endif
                            </div>
                            <div class="text-center" style="width: 40px;">
                                <div class="text-xs font-bold text-green-600">
                                    {{ number_format($afterStock, 0) }}
                                    @if($isCaseProduct)
                                        <span class="block text-[10px] text-gray-500">{{ number_format($afterStock / max($caseUnits, 1), 1) }} cases</span>
                                    @else
                                        <span class="block text-[10px] text-gray-500">units</span>
                                    @endif
                                </div>
                                <div class="text-xs text-green-600 font-medium">{{ round($afterPct) }}% max{{ $afterPct > 100 ? '↑' : '' }}</div>
                            </div>
                        </div>
                    </td>
                    <td class="px-4 py-4 text-center">
                        <div class="text-xl font-bold text-purple-700">
                            {{ number_format($suggestedDisplayQuantity, $quantityPrecision) }} {{ $quantityLabel }}
                        </div>
                        <div class="text-sm text-gray-600">{{ number_format($suggestedUnits, 0) }} units</div>
                        @if(abs($finalUnits - $suggestedUnits) > 0.001)
                            <div class="mt-1 inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-orange-50 text-orange-600">
                                Final: {{ number_format($displayOrderQuantity, $quantityPrecision) }} {{ $quantityLabel }}
                            </div>
                        @endif
                    </td>
                    <td class="px-4 py-4">
                        <div class="flex items-center justify-center space-x-1">
                            <button class="w-8 h-8 bg-red-100 hover:bg-red-200 text-red-700 rounded font-bold" type="button">−</button>
                            <input type="number"
                                   value="{{ number_format($displayOrderQuantity, $quantityPrecision, '.', '') }}"
                                   step="1"
                                   class="w-20 text-center text-lg font-bold border-2 border-gray-300 rounded py-1">
                            <button class="w-8 h-8 bg-green-100 hover:bg-green-200 text-green-700 rounded font-bold" type="button">+</button>
                        </div>
                        <div class="text-center text-xs text-gray-500 mt-1">
                            {{ $quantityLabel }} · {{ number_format($finalUnits, 0) }} units
                        </div>
                    </td>
                    <td class="px-4 py-4 text-right">
                        <div class="text-lg font-bold text-gray-900">€{{ number_format($item->total_cost, 2) }}</div>
                        <div class="text-sm text-gray-500">€{{ number_format($item->unit_cost, 2) }}/unit</div>
                    </td>
                    <td class="px-4 py-4 text-center">
                        @if($item->auto_approved)
                            <button class="px-4 py-2 bg-green-600 text-white rounded-lg font-medium text-sm">
                                ✓ Approved
                            </button>
                        @else
                            <button class="px-4 py-2 bg-blue-600 text-white rounded-lg font-medium hover:bg-blue-700 text-sm">
                                Approve
                            </button>
                        @endif
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>

@if(isset($limitNotice) && $limitNotice && $totalItems > $displayItems->count())
    <div class="mt-4 text-center text-gray-600">
        Showing first {{ $displayItems->count() }} of {{ $totalItems }} items
    </div>
@endif

@once
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
@endonce
<script>
    document.addEventListener('DOMContentLoaded', function() {
        if (window.chartsInitialized) return;
        window.chartsInitialized = true;

        const globalMaxSales = @json($globalMaxWeeklySales);

        @foreach($displayItems as $item)
            @php
                $contextData = $item->context_data ?? [];
                $weeklySales = $contextData['weekly_sales'] ?? [];
                $weekLabels = array_map(static fn ($week) => $week['label'] ?? '', $weeklySales);
                $weekUnitsRaw = array_map(static fn ($week) => (float) ($week['units'] ?? 0), $weeklySales);
                if (empty($weekLabels)) {
                    $weekLabels = array_map(static fn ($index) => 'W'.($index + 1), range(0, max(($contextData['sales_history_weeks'] ?? 8) - 1, 0)));
                }
                if (count($weekUnitsRaw) !== count($weekLabels)) {
                    $weekUnitsRaw = array_pad($weekUnitsRaw, count($weekLabels), 0.0);
                }
                $weekUnits = array_map(static fn ($value) => round($value, 2), $weekUnitsRaw);
            @endphp
            (function() {
                const chartEl = document.getElementById('chart_{{ $item->id }}');
                if (!chartEl) {
                    return;
                }

                const labels = @json($weekLabels);
                const dataPoints = @json($weekUnits);
                const maxDataPoint = dataPoints.length ? Math.max(...dataPoints) : 0;

                new Chart(chartEl, {
                    type: 'line',
                    data: {
                        labels: labels,
                        datasets: [{
                            label: 'Sales',
                            data: dataPoints,
                            borderColor: '{{ $item->review_priority === "review" ? "rgb(239, 68, 68)" : ($item->review_priority === "standard" ? "rgb(234, 179, 8)" : "rgb(34, 197, 94)") }}',
                            backgroundColor: '{{ $item->review_priority === "review" ? "rgba(239, 68, 68, 0.2)" : ($item->review_priority === "standard" ? "rgba(234, 179, 8, 0.2)" : "rgba(34, 197, 94, 0.2)") }}',
                            tension: 0.4,
                            fill: true,
                            borderWidth: 2
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        interaction: {
                            mode: 'index',
                            intersect: false
                        },
                        plugins: {
                            legend: { display: false },
                            tooltip: {
                                enabled: true,
                                backgroundColor: 'rgba(0, 0, 0, 0.8)',
                                padding: 10,
                                cornerRadius: 4,
                                titleFont: { size: 12, weight: 'bold' },
                                bodyFont: { size: 12 },
                                displayColors: false,
                                callbacks: {
                                    label: function(context) {
                                        return Math.round(context.parsed.y) + ' units sold';
                                    }
                                }
                            }
                        },
                        scales: {
                            x: { display: false },
                            y: {
                                display: false,
                                beginAtZero: true,
                                suggestedMax: globalMaxSales > 0 ? globalMaxSales * 1.1 : (maxDataPoint > 0 ? maxDataPoint * 1.2 : 10)
                            }
                        },
                        elements: {
                            point: { radius: 3, hoverRadius: 5 }
                        }
                    }
                });
            })();
        @endforeach
    });
</script>
