@php
    $incomingItems = $displayItems ?? null;
    if ($incomingItems && ! $incomingItems instanceof \Illuminate\Support\Collection) {
        $incomingItems = collect($incomingItems);
    }
    $sortMode = request()->input('sort', 'sales');
    $showAll = request()->boolean('show_all', false);
    $baseItems = $orderSession->items;

    $sortedItems = match ($sortMode) {
        'name' => $baseItems->sortBy(function ($item) {
            return strtoupper($item->product->NAME ?? '');
        })->values(),
        'priority' => $baseItems->sortBy(function ($item) {
            return match ($item->review_priority) {
                'review' => 0,
                'standard' => 1,
                default => 2,
            };
        })->values(),
        'value' => $baseItems->sortByDesc(function ($item) {
            return (float) $item->total_cost;
        })->values(),
        default => $baseItems->sortByDesc(function ($item) {
            $context = $item->context_data ?? [];
            // Calculate total sales for the period
            $totalSales = 0;
            if (isset($context['weekly_sales']) && is_array($context['weekly_sales'])) {
                $totalSales = collect($context['weekly_sales'])->sum(function ($week) {
                    return (float) ($week['units'] ?? 0);
                });
            }
            // Sort by total sales (primary), then by suggested quantity (secondary)
            $suggestedQty = (float) ($item->suggested_quantity ?? 0);
            // Return composite sort key: total sales * 1000000 + suggested quantity
            // This ensures items are primarily sorted by total sales, then by suggested quantity
            return ($totalSales * 1000000) + $suggestedQty;
        })->values(),
    };

    $displayItems = $incomingItems
        ? $sortedItems->filter(fn ($item) => $incomingItems->contains('id', $item->id))->values()
        : $sortedItems;

    if (! $showAll) {
        $displayItems = $displayItems->filter(function ($item) {
            $finalUnits = (float) ($item->final_quantity ?? 0);
            if ($finalUnits <= 0) {
                $finalUnits = (float) ($item->suggested_quantity ?? 0);
            }

            return $finalUnits > 0;
        })->values();
    }

    // Split items by category and case/unit type
    // Category IDs: Cheese = "032", Refrigerated = "002"
    $cheeseProducts = $displayItems->filter(function ($item) {
        return $item->product && $item->product->CATEGORY === '032';
    })->values();

    $refrigeratedProducts = $displayItems->filter(function ($item) {
        return $item->product && $item->product->CATEGORY === '002';
    })->values();

    // Non-cheese/refrigerated products split by case/unit
    $otherProducts = $displayItems->filter(function ($item) {
        $category = $item->product ? $item->product->CATEGORY : null;
        return $category !== '032' && $category !== '002';
    });

    $caseProducts = $otherProducts->filter(function ($item) {
        $contextData = $item->context_data ?? [];
        $caseUnits = $contextData['case_units'] ?? 1;
        return $contextData['is_case_product'] ?? ($caseUnits > 1);
    })->values();

    $unitProducts = $otherProducts->filter(function ($item) {
        $contextData = $item->context_data ?? [];
        $caseUnits = $contextData['case_units'] ?? 1;
        return !($contextData['is_case_product'] ?? ($caseUnits > 1));
    })->values();

    $reviewCount = $displayItems->where('review_priority', 'review')->count();
    $standardCount = $displayItems->where('review_priority', 'standard')->count();
    $safeCount = $displayItems->where('review_priority', 'safe')->count();
    $totalItems = $displayItems->count();
    $hiddenCount = $showAll ? 0 : ($sortedItems->count() - $displayItems->count());
    $currentQuery = request()->query();
    $toggleQuery = $currentQuery;
    $toggleQuery['show_all'] = $showAll ? 0 : 1;
    $toggleUrl = request()->url().'?'.http_build_query($toggleQuery);
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

    $categoryGroups = $categoryGroups ?? [];
    $coverageOverrides = $orderSession->coverage_overrides ?? [];
    $categoryCoverageMeta = [];
    $globalCoverageDateRaw = optional($orderSession->coverage_ends_on)?->toDateString();
    $globalCoverageDateFormatted = $globalCoverageDateRaw
        ? \Carbon\Carbon::parse($globalCoverageDateRaw)->format('D j M Y')
        : null;
    $globalCoverageDays = $orderSession->coverage_days;
    $oldCategoryKey = old('category_key');

    foreach ($categoryGroups as $groupKey => $definition) {
        $override = $coverageOverrides[$groupKey] ?? null;
        $effectiveDate = $override['coverage_ends_on'] ?? $globalCoverageDateRaw;
        $effectiveDays = $override['coverage_days'] ?? $globalCoverageDays;

        $categoryCoverageMeta[$groupKey] = [
            'label' => $definition['label'] ?? \Illuminate\Support\Str::headline($groupKey),
            'effective_date' => $effectiveDate,
            'effective_days' => $effectiveDays,
            'formatted_date' => $effectiveDate
                ? \Carbon\Carbon::parse($effectiveDate)->format('D j M Y')
                : null,
            'override_active' => $override !== null,
            'default_hint' => $definition['default_coverage_days'] ?? null,
        ];
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
        <a href="{{ $toggleUrl }}"
           class="px-3 py-2 rounded-md border {{ $showAll ? 'border-indigo-500 text-indigo-600' : 'border-gray-300 text-gray-600' }} text-sm hover:bg-gray-100">
            {{ $showAll ? 'Hide unordered' : 'Show unordered'.($hiddenCount > 0 ? ' ('.$hiddenCount.')' : '') }}
        </a>
        <form method="GET" class="flex items-center space-x-2 text-sm text-gray-600">
            @foreach(request()->except('sort') as $paramKey => $paramValue)
                <input type="hidden" name="{{ $paramKey }}" value="{{ $paramValue }}">
            @endforeach
            <label for="order-sort" class="font-medium text-gray-500">Sort</label>
            <select id="order-sort" name="sort" class="border-gray-300 rounded-md text-sm focus:ring-indigo-500 focus:border-indigo-500" onchange="this.form.submit()">
                <option value="sales" {{ $sortMode === 'sales' ? 'selected' : '' }}>Sales ↓</option>
                <option value="name" {{ $sortMode === 'name' ? 'selected' : '' }}>Name A-Z</option>
                <option value="priority" {{ $sortMode === 'priority' ? 'selected' : '' }}>Priority</option>
                <option value="value" {{ $sortMode === 'value' ? 'selected' : '' }}>Value ↓</option>
            </select>
        </form>
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

<!-- Cheese Products Table -->
@if($cheeseProducts->count() > 0)
@php
    $cheeseCoverage = $categoryCoverageMeta['cheese'] ?? null;
    $cheeseInputValue = $cheeseCoverage
        ? ($oldCategoryKey === 'cheese'
            ? old('coverage_end_date', $cheeseCoverage['override_active'] ? $cheeseCoverage['effective_date'] : '')
            : ($cheeseCoverage['override_active'] ? $cheeseCoverage['effective_date'] : ''))
        : '';
@endphp
<div class="mb-6">
    @if($cheeseCoverage)
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 px-3 py-2 bg-yellow-50 rounded-t-lg border-b-2 border-yellow-400">
            <h3 class="text-lg font-semibold text-gray-900 flex items-center gap-2">
                🧀 Cheese ({{ $cheeseProducts->count() }})
            </h3>
            <div class="flex items-center gap-3 text-sm sm:text-xs text-blue-800">
                <div>
                    {{ $cheeseCoverage['formatted_date'] ?? ($globalCoverageDateFormatted ?? 'Global schedule') }}
                    <span class="block text-xs text-blue-600">
                        {{ $cheeseCoverage['effective_days'] ?? '—' }} day window
                        @if($cheeseCoverage['override_active'])
                            • override active
                        @else
                            • using global target
                        @endif
                    </span>
                </div>
                @if($orderSession->isEditable())
                    <details class="relative">
                        <summary class="text-xs font-medium text-blue-600 hover:text-blue-700 cursor-pointer list-none">
                            Adjust
                        </summary>
                        <div class="absolute right-0 mt-2 w-60 bg-white border border-blue-200 shadow-xl rounded-md p-3 z-30">
                            <form method="POST" action="{{ route('orders.coverage-overrides', $orderSession) }}" class="space-y-3">
                                @csrf
                                @method('PATCH')
                                <input type="hidden" name="category_key" value="cheese">
                                <label for="cheese-coverage-input" class="block text-xs font-semibold text-gray-700">
                                    Cover until
                                </label>
                                <input
                                    type="date"
                                    id="cheese-coverage-input"
                                    name="coverage_end_date"
                                    class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 text-sm"
                                    value="{{ $cheeseInputValue }}"
                                    min="{{ optional($orderSession->order_date)?->format('Y-m-d') ?? now()->format('Y-m-d') }}"
                                >
                                <p class="text-xs text-gray-500">
                                    Leave blank and use “Use global” to fall back to {{ $globalCoverageDateFormatted ?? 'the system default' }}.
                                </p>
                                @if($cheeseCoverage['default_hint'])
                                    <p class="text-xs text-gray-400">
                                        Suggested window: {{ $cheeseCoverage['default_hint'] }} days.
                                    </p>
                                @endif
                                @error('coverage_end_date')
                                    @if($oldCategoryKey === 'cheese')
                                        <p class="text-xs text-red-600">{{ $message }}</p>
                                    @endif
                                @enderror
                                <div class="flex justify-end gap-2">
                                    @if($cheeseCoverage['override_active'])
                                        <button
                                            type="submit"
                                            name="clear"
                                            value="1"
                                            class="px-3 py-1 text-xs font-medium text-blue-600 bg-blue-50 hover:bg-blue-100 rounded"
                                        >
                                            Use global
                                        </button>
                                    @endif
                                    <button
                                        type="submit"
                                        class="px-3 py-1 text-xs font-medium text-white bg-blue-600 hover:bg-blue-700 rounded"
                                    >
                                        Save
                                    </button>
                                </div>
                            </form>
                        </div>
                    </details>
                @endif
            </div>
        </div>
    @else
        <h3 class="text-lg font-semibold text-gray-900 px-2 py-2 bg-yellow-50 rounded-t-lg border-b-2 border-yellow-400">
            🧀 Cheese ({{ $cheeseProducts->count() }})
        </h3>
    @endif
    <div class="bg-white rounded-b-lg shadow overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase w-8"></th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Product</th>
                    <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase w-32">Total Sales</th>
                    <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase" style="width: 320px;">Stock Levels & Sales Trend</th>
                    <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase w-32">Suggested</th>
                    <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase w-40">Order Qty</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase w-32">Cost</th>
                    <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase w-32">Action</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                @foreach($cheeseProducts as $item)
                @php
                    $product = $item->product;
                    $contextData = $item->context_data ?? [];
                    $currentStock = $contextData['current_stock'] ?? 0;
                    $avgWeeklySales = $contextData['avg_weekly_sales'] ?? 0;
                    $weeklySales = $contextData['weekly_sales'] ?? [];
                    $totalPeriodSales = collect($weeklySales)->sum(function ($week) {
                        return (float) ($week['units'] ?? 0);
                    });
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

                    // For visual comparison (bar heights) - use global max across all products
                    $referenceDemand = max($globalMaxWeeklySales, 1);
                    $currentHeightPct = $referenceDemand > 0 ? ($currentStock / $referenceDemand) * 100 : 0;
                    $afterHeightPct = $referenceDemand > 0 ? ($afterStock / $referenceDemand) * 100 : 100;

                    // For percentage labels - use individual product's peak for intuitive display
                    $productPeakDemand = max($peakWeeklySales, 1);
                    $currentPct = $productPeakDemand > 0 ? ($currentStock / $productPeakDemand) * 100 : 0;
                    $afterPct = $productPeakDemand > 0 ? ($afterStock / $productPeakDemand) * 100 : 100;

                    // Bar heights use global scaling for cross-product comparison
                    $currentPositiveHeight = $currentHeightPct > 0 ? min($currentHeightPct, 180) : 0;
                    $currentNegativeHeight = $currentHeightPct < 0 ? min(abs($currentHeightPct), 180) : 0;
                    $afterPositiveHeight = $afterHeightPct > 0 ? min($afterHeightPct, 220) : 0;
                    $afterNegativeHeight = $afterHeightPct < 0 ? min(abs($afterHeightPct), 180) : 0;

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
                            <div class="text-2xl font-bold text-blue-600">{{ number_format($totalPeriodSales, 0) }}</div>
                            <div class="text-xs text-gray-500 mb-1">total sold</div>
                            <div class="w-full bg-gray-200 rounded-full h-2">
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
                        <div class="flex items-end gap-6" style="min-height: 160px; position: relative; padding: 20px 0;">
                            <!-- Reference Grid Lines -->
                            <div class="absolute inset-x-0" style="top: 12px; height: 96px; pointer-events: none;">
                                <div class="absolute inset-x-12 top-0 border-t border-gray-300 border-dashed" title="Visual reference lines for comparing bar heights"></div>
                                <div class="absolute inset-x-12" style="top: 48px; border-top: 1px dashed rgba(148, 163, 184, 0.5);"></div>
                                <div class="absolute inset-x-12 bottom-0 border-t border-gray-200"></div>
                            </div>
                            <!-- Current Stock Bar (Left) -->
                            <div class="flex flex-col items-center" style="width: 60px;">
                                <div class="w-10 bg-gray-200 rounded-b-full relative overflow-visible mx-auto" style="height: 88px;">
                                    <div class="absolute top-0 left-0 right-0 h-0.5 bg-gray-400 z-10" title="Reference line: Bar heights scaled to highest sales across all products"></div>
                                    @if($currentNegativeHeight > 0)
                                        <div class="absolute top-0 left-0 right-0 bg-red-500 rounded-t-full" style="height: {{ $currentNegativeHeight }}%;" title="Short {{ number_format(abs($currentStock), 0) }} units ({{ round(abs($currentPct)) }}% of this product's peak: {{ number_format($peakWeeklySales, 1) }})"></div>
                                    @endif
                                    @if($currentPositiveHeight > 0)
                                        <div class="absolute bottom-0 left-0 right-0 bg-{{ $stockColor }}-500 rounded-b-full" style="height: {{ $currentPositiveHeight }}%;" title="{{ max($currentStock, 0) }} units = {{ round($currentPct) }}% of this product's peak ({{ number_format($peakWeeklySales, 1) }})"></div>
                                    @endif
                                    @if($currentPositiveHeight > 100)
                                        <div class="absolute -top-4 left-1/2 transform -translate-x-1/2 text-[10px] text-{{ $stockColor }}-600 font-semibold">+{{ round($currentPct - 100) }}%</div>
                                    @endif
                                </div>
                            </div>

                            <!-- Chart -->
                            <div style="height: 88px; position: relative; flex: 1;">
                                <canvas id="chart_{{ $item->id }}" style="margin: 0 8px;"></canvas>
                            </div>

                            <!-- After Delivery Bar (Right) -->
                            <div class="flex flex-col items-center" style="width: 60px;">
                                <div class="w-10 bg-gray-200 rounded-b-full relative overflow-visible mx-auto" style="height: 88px;">
                                    <div class="absolute top-0 left-0 right-0 h-0.5 bg-gray-400 z-10" title="Reference line: Bar heights scaled to highest sales across all products"></div>
                                    <div id="after-bar-negative-{{ $item->id }}" class="absolute top-0 left-0 right-0 bg-red-500 rounded-t-full" style="height: {{ $afterNegativeHeight }}%; display: {{ $afterNegativeHeight > 0 ? 'block' : 'none' }};" title="Short {{ number_format(abs($afterStock), 0) }} units ({{ round(abs($afterPct)) }}% of this product's peak: {{ number_format($peakWeeklySales, 1) }})"></div>
                                    <div id="after-bar-positive-{{ $item->id }}" class="absolute bottom-0 left-0 right-0 bg-green-500 rounded-b-full" style="height: {{ $afterPositiveHeight }}%; display: {{ $afterPositiveHeight > 0 ? 'block' : 'none' }};" title="{{ max($afterStock, 0) }} units = {{ round($afterPct) }}% of this product's peak ({{ number_format($peakWeeklySales, 1) }})"></div>
                                    <div id="after-bar-overflow-{{ $item->id }}" class="absolute -top-4 left-1/2 transform -translate-x-1/2 text-[10px] text-green-600 font-semibold" style="display: {{ $afterPositiveHeight > 100 ? 'block' : 'none' }};">+{{ round($afterPct - 100) }}%</div>
                                </div>
                            </div>
                        </div>

                        <!-- Text Labels Row -->
                        <div class="flex items-center justify-between mt-2 text-xs text-gray-600">
                            <div class="text-left">
                                <span class="font-semibold text-{{ $stockColor }}-600">{{ number_format($currentStock, 0) }}</span>
                                <span class="text-[11px] text-gray-500">
                                    ({{ $isCaseProduct ? number_format($currentStock / max($caseUnits, 1), 1).' cs' : 'u' }}, {{ round($currentPct) }}%)
                                </span>
                            </div>
                            <div class="px-2 py-0.5 bg-gray-100 rounded text-gray-700 font-medium">
                                avg {{ number_format($avgWeeklySales, 1) }} · peak {{ number_format($peakWeeklySales, 1) }}
                                @if(isset($contextData['coverage_weeks']))
                                    · cover {{ number_format($contextData['coverage_weeks'], 1) }}→{{ number_format($contextData['target_weeks'] ?? $contextData['coverage_weeks'], 1) }} wk
                                @endif
                            </div>
                            <div class="text-right">
                                <span id="after-stock-value-{{ $item->id }}" class="font-semibold text-green-600">{{ number_format($afterStock, 0) }}</span>
                                <span id="after-stock-label-{{ $item->id }}" class="text-[11px] text-gray-500">
                                    ({{ $isCaseProduct ? number_format($afterStock / max($caseUnits, 1), 1).' cs' : 'u' }}, {{ round($afterPct) }}%)
                                </span>
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
                            <button class="qty-decrease w-8 h-8 bg-red-100 hover:bg-red-200 text-red-700 rounded font-bold"
                                    type="button"
                                    data-item-id="{{ $item->id }}">−</button>
                            <input type="number"
                                   id="qty-input-{{ $item->id }}"
                                   value="{{ number_format($displayOrderQuantity, $quantityPrecision, '.', '') }}"
                                   step="1"
                                   data-item-id="{{ $item->id }}"
                                   data-current-stock="{{ $currentStock }}"
                                   data-case-units="{{ $caseUnits }}"
                                   data-is-case-product="{{ $isCaseProduct ? 1 : 0 }}"
                                   data-product-peak="{{ $peakWeeklySales }}"
                                   data-global-max="{{ $globalMaxWeeklySales }}"
                                   class="qty-input w-20 text-center text-lg font-bold border-2 border-gray-300 rounded py-1">
                            <button class="qty-increase w-8 h-8 bg-green-100 hover:bg-green-200 text-green-700 rounded font-bold"
                                    type="button"
                                    data-item-id="{{ $item->id }}">+</button>
                        </div>
                        <div class="text-center text-xs text-gray-500 mt-1">
                            {{ $quantityLabel }} · <span id="units-label-{{ $item->id }}">{{ number_format($finalUnits, 0) }}</span> units
                        </div>
                    </td>
                    <td class="px-4 py-4 text-right">
                        <div id="total-cost-{{ $item->id }}" class="text-lg font-bold text-gray-900">€{{ number_format($item->total_cost, 2) }}</div>
                        <div class="text-sm text-gray-500">€<span id="unit-cost-{{ $item->id }}">{{ number_format($item->unit_cost, 2) }}</span>/unit</div>
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
</div>
@endif

<!-- Refrigerated Products Table -->
@if($refrigeratedProducts->count() > 0)
@php
    $refrigeratedCoverage = $categoryCoverageMeta['refrigerated'] ?? null;
    $refrigeratedInputValue = $refrigeratedCoverage
        ? ($oldCategoryKey === 'refrigerated'
            ? old('coverage_end_date', $refrigeratedCoverage['override_active'] ? $refrigeratedCoverage['effective_date'] : '')
            : ($refrigeratedCoverage['override_active'] ? $refrigeratedCoverage['effective_date'] : ''))
        : '';
@endphp
<div class="mb-6">
    @if($refrigeratedCoverage)
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 px-3 py-2 bg-cyan-50 rounded-t-lg border-b-2 border-cyan-400">
            <h3 class="text-lg font-semibold text-gray-900 flex items-center gap-2">
                ❄️ Refrigerated ({{ $refrigeratedProducts->count() }})
            </h3>
            <div class="flex items-center gap-3 text-sm sm:text-xs text-blue-800">
                <div>
                    {{ $refrigeratedCoverage['formatted_date'] ?? ($globalCoverageDateFormatted ?? 'Global schedule') }}
                    <span class="block text-xs text-blue-600">
                        {{ $refrigeratedCoverage['effective_days'] ?? '—' }} day window
                        @if($refrigeratedCoverage['override_active'])
                            • override active
                        @else
                            • using global target
                        @endif
                    </span>
                </div>
                @if($orderSession->isEditable())
                    <details class="relative">
                        <summary class="text-xs font-medium text-blue-600 hover:text-blue-700 cursor-pointer list-none">
                            Adjust
                        </summary>
                        <div class="absolute right-0 mt-2 w-60 bg-white border border-blue-200 shadow-xl rounded-md p-3 z-30">
                            <form method="POST" action="{{ route('orders.coverage-overrides', $orderSession) }}" class="space-y-3">
                                @csrf
                                @method('PATCH')
                                <input type="hidden" name="category_key" value="refrigerated">
                                <label for="refrigerated-coverage-input" class="block text-xs font-semibold text-gray-700">
                                    Cover until
                                </label>
                                <input
                                    type="date"
                                    id="refrigerated-coverage-input"
                                    name="coverage_end_date"
                                    class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 text-sm"
                                    value="{{ $refrigeratedInputValue }}"
                                    min="{{ optional($orderSession->order_date)?->format('Y-m-d') ?? now()->format('Y-m-d') }}"
                                >
                                <p class="text-xs text-gray-500">
                                    Leave blank and choose “Use global” to fall back to {{ $globalCoverageDateFormatted ?? 'the system default' }}.
                                </p>
                                @if($refrigeratedCoverage['default_hint'])
                                    <p class="text-xs text-gray-400">
                                        Suggested window: {{ $refrigeratedCoverage['default_hint'] }} days.
                                    </p>
                                @endif
                                @error('coverage_end_date')
                                    @if($oldCategoryKey === 'refrigerated')
                                        <p class="text-xs text-red-600">{{ $message }}</p>
                                    @endif
                                @enderror
                                <div class="flex justify-end gap-2">
                                    @if($refrigeratedCoverage['override_active'])
                                        <button
                                            type="submit"
                                            name="clear"
                                            value="1"
                                            class="px-3 py-1 text-xs font-medium text-blue-600 bg-blue-50 hover:bg-blue-100 rounded"
                                        >
                                            Use global
                                        </button>
                                    @endif
                                    <button
                                        type="submit"
                                        class="px-3 py-1 text-xs font-medium text-white bg-blue-600 hover:bg-blue-700 rounded"
                                    >
                                        Save
                                    </button>
                                </div>
                            </form>
                        </div>
                    </details>
                @endif
            </div>
        </div>
    @else
        <h3 class="text-lg font-semibold text-gray-900 px-2 py-2 bg-cyan-50 rounded-t-lg border-b-2 border-cyan-400">
            ❄️ Refrigerated ({{ $refrigeratedProducts->count() }})
        </h3>
    @endif
    <div class="bg-white rounded-b-lg shadow overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase w-8"></th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Product</th>
                    <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase w-32">Total Sales</th>
                    <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase" style="width: 320px;">Stock Levels & Sales Trend</th>
                    <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase w-32">Suggested</th>
                    <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase w-40">Order Qty</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase w-32">Cost</th>
                    <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase w-32">Action</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                @foreach($refrigeratedProducts as $item)
                @php
                    $product = $item->product;
                    $contextData = $item->context_data ?? [];
                    $currentStock = $contextData['current_stock'] ?? 0;
                    $avgWeeklySales = $contextData['avg_weekly_sales'] ?? 0;
                    $weeklySales = $contextData['weekly_sales'] ?? [];
                    $totalPeriodSales = collect($weeklySales)->sum(function ($week) {
                        return (float) ($week['units'] ?? 0);
                    });
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

                    // For visual comparison (bar heights) - use global max across all products
                    $referenceDemand = max($globalMaxWeeklySales, 1);
                    $currentHeightPct = $referenceDemand > 0 ? ($currentStock / $referenceDemand) * 100 : 0;
                    $afterHeightPct = $referenceDemand > 0 ? ($afterStock / $referenceDemand) * 100 : 100;

                    // For percentage labels - use individual product's peak for intuitive display
                    $productPeakDemand = max($peakWeeklySales, 1);
                    $currentPct = $productPeakDemand > 0 ? ($currentStock / $productPeakDemand) * 100 : 0;
                    $afterPct = $productPeakDemand > 0 ? ($afterStock / $productPeakDemand) * 100 : 100;

                    // Bar heights use global scaling for cross-product comparison
                    $currentPositiveHeight = $currentHeightPct > 0 ? min($currentHeightPct, 180) : 0;
                    $currentNegativeHeight = $currentHeightPct < 0 ? min(abs($currentHeightPct), 180) : 0;
                    $afterPositiveHeight = $afterHeightPct > 0 ? min($afterHeightPct, 220) : 0;
                    $afterNegativeHeight = $afterHeightPct < 0 ? min(abs($afterHeightPct), 180) : 0;

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
                            <div class="text-2xl font-bold text-blue-600">{{ number_format($totalPeriodSales, 0) }}</div>
                            <div class="text-xs text-gray-500 mb-1">total sold</div>
                            <div class="w-full bg-gray-200 rounded-full h-2">
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
                        <div class="flex items-end gap-6" style="min-height: 160px; position: relative; padding: 20px 0;">
                            <!-- Reference Grid Lines -->
                            <div class="absolute inset-x-0" style="top: 12px; height: 96px; pointer-events: none;">
                                <div class="absolute inset-x-12 top-0 border-t border-gray-300 border-dashed" title="Visual reference lines for comparing bar heights"></div>
                                <div class="absolute inset-x-12" style="top: 48px; border-top: 1px dashed rgba(148, 163, 184, 0.5);"></div>
                                <div class="absolute inset-x-12 bottom-0 border-t border-gray-200"></div>
                            </div>
                            <!-- Current Stock Bar (Left) -->
                            <div class="flex flex-col items-center" style="width: 60px;">
                                <div class="w-10 bg-gray-200 rounded-b-full relative overflow-visible mx-auto" style="height: 88px;">
                                    <div class="absolute top-0 left-0 right-0 h-0.5 bg-gray-400 z-10" title="Reference line: Bar heights scaled to highest sales across all products"></div>
                                    @if($currentNegativeHeight > 0)
                                        <div class="absolute top-0 left-0 right-0 bg-red-500 rounded-t-full" style="height: {{ $currentNegativeHeight }}%;" title="Short {{ number_format(abs($currentStock), 0) }} units ({{ round(abs($currentPct)) }}% of this product's peak: {{ number_format($peakWeeklySales, 1) }})"></div>
                                    @endif
                                    @if($currentPositiveHeight > 0)
                                        <div class="absolute bottom-0 left-0 right-0 bg-{{ $stockColor }}-500 rounded-b-full" style="height: {{ $currentPositiveHeight }}%;" title="{{ max($currentStock, 0) }} units = {{ round($currentPct) }}% of this product's peak ({{ number_format($peakWeeklySales, 1) }})"></div>
                                    @endif
                                    @if($currentPositiveHeight > 100)
                                        <div class="absolute -top-4 left-1/2 transform -translate-x-1/2 text-[10px] text-{{ $stockColor }}-600 font-semibold">+{{ round($currentPct - 100) }}%</div>
                                    @endif
                                </div>
                            </div>

                            <!-- Chart -->
                            <div style="height: 88px; position: relative; flex: 1;">
                                <canvas id="chart_{{ $item->id }}" style="margin: 0 8px;"></canvas>
                            </div>

                            <!-- After Delivery Bar (Right) -->
                            <div class="flex flex-col items-center" style="width: 60px;">
                                <div class="w-10 bg-gray-200 rounded-b-full relative overflow-visible mx-auto" style="height: 88px;">
                                    <div class="absolute top-0 left-0 right-0 h-0.5 bg-gray-400 z-10" title="Reference line: Bar heights scaled to highest sales across all products"></div>
                                    <div id="after-bar-negative-{{ $item->id }}" class="absolute top-0 left-0 right-0 bg-red-500 rounded-t-full" style="height: {{ $afterNegativeHeight }}%; display: {{ $afterNegativeHeight > 0 ? 'block' : 'none' }};" title="Short {{ number_format(abs($afterStock), 0) }} units ({{ round(abs($afterPct)) }}% of this product's peak: {{ number_format($peakWeeklySales, 1) }})"></div>
                                    <div id="after-bar-positive-{{ $item->id }}" class="absolute bottom-0 left-0 right-0 bg-green-500 rounded-b-full" style="height: {{ $afterPositiveHeight }}%; display: {{ $afterPositiveHeight > 0 ? 'block' : 'none' }};" title="{{ max($afterStock, 0) }} units = {{ round($afterPct) }}% of this product's peak ({{ number_format($peakWeeklySales, 1) }})"></div>
                                    <div id="after-bar-overflow-{{ $item->id }}" class="absolute -top-4 left-1/2 transform -translate-x-1/2 text-[10px] text-green-600 font-semibold" style="display: {{ $afterPositiveHeight > 100 ? 'block' : 'none' }};">+{{ round($afterPct - 100) }}%</div>
                                </div>
                            </div>
                        </div>

                        <!-- Text Labels Row -->
                        <div class="flex items-center justify-between mt-2 text-xs text-gray-600">
                            <div class="text-left">
                                <span class="font-semibold text-{{ $stockColor }}-600">{{ number_format($currentStock, 0) }}</span>
                                <span class="text-[11px] text-gray-500">
                                    ({{ $isCaseProduct ? number_format($currentStock / max($caseUnits, 1), 1).' cs' : 'u' }}, {{ round($currentPct) }}%)
                                </span>
                            </div>
                            <div class="px-2 py-0.5 bg-gray-100 rounded text-gray-700 font-medium">
                                avg {{ number_format($avgWeeklySales, 1) }} · peak {{ number_format($peakWeeklySales, 1) }}
                                @if(isset($contextData['coverage_weeks']))
                                    · cover {{ number_format($contextData['coverage_weeks'], 1) }}→{{ number_format($contextData['target_weeks'] ?? $contextData['coverage_weeks'], 1) }} wk
                                @endif
                            </div>
                            <div class="text-right">
                                <span id="after-stock-value-{{ $item->id }}" class="font-semibold text-green-600">{{ number_format($afterStock, 0) }}</span>
                                <span id="after-stock-label-{{ $item->id }}" class="text-[11px] text-gray-500">
                                    ({{ $isCaseProduct ? number_format($afterStock / max($caseUnits, 1), 1).' cs' : 'u' }}, {{ round($afterPct) }}%)
                                </span>
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
                            <button class="qty-decrease w-8 h-8 bg-red-100 hover:bg-red-200 text-red-700 rounded font-bold"
                                    type="button"
                                    data-item-id="{{ $item->id }}">−</button>
                            <input type="number"
                                   id="qty-input-{{ $item->id }}"
                                   value="{{ number_format($displayOrderQuantity, $quantityPrecision, '.', '') }}"
                                   step="1"
                                   data-item-id="{{ $item->id }}"
                                   data-current-stock="{{ $currentStock }}"
                                   data-case-units="{{ $caseUnits }}"
                                   data-is-case-product="{{ $isCaseProduct ? 1 : 0 }}"
                                   data-product-peak="{{ $peakWeeklySales }}"
                                   data-global-max="{{ $globalMaxWeeklySales }}"
                                   class="qty-input w-20 text-center text-lg font-bold border-2 border-gray-300 rounded py-1">
                            <button class="qty-increase w-8 h-8 bg-green-100 hover:bg-green-200 text-green-700 rounded font-bold"
                                    type="button"
                                    data-item-id="{{ $item->id }}">+</button>
                        </div>
                        <div class="text-center text-xs text-gray-500 mt-1">
                            {{ $quantityLabel }} · <span id="units-label-{{ $item->id }}">{{ number_format($finalUnits, 0) }}</span> units
                        </div>
                    </td>
                    <td class="px-4 py-4 text-right">
                        <div id="total-cost-{{ $item->id }}" class="text-lg font-bold text-gray-900">€{{ number_format($item->total_cost, 2) }}</div>
                        <div class="text-sm text-gray-500">€<span id="unit-cost-{{ $item->id }}">{{ number_format($item->unit_cost, 2) }}</span>/unit</div>
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
</div>
@endif

<!-- Case Products Table -->
@if($caseProducts->count() > 0)
<div class="mb-6">
    <h3 class="text-lg font-semibold text-gray-900 mb-3 px-2 py-2 bg-blue-50 rounded-t-lg border-b-2 border-blue-400">
        📦 Case Products ({{ $caseProducts->count() }})
    </h3>
    <div class="bg-white rounded-b-lg shadow overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase w-8"></th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Product</th>
                    <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase w-32">Total Sales</th>
                    <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase" style="width: 320px;">Stock Levels & Sales Trend</th>
                    <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase w-32">Suggested</th>
                    <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase w-40">Order Qty</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase w-32">Cost</th>
                    <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase w-32">Action</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                @foreach($caseProducts as $item)
                @php
                    $product = $item->product;
                    $contextData = $item->context_data ?? [];
                    $currentStock = $contextData['current_stock'] ?? 0;
                    $avgWeeklySales = $contextData['avg_weekly_sales'] ?? 0;
                    $weeklySales = $contextData['weekly_sales'] ?? [];
                    $totalPeriodSales = collect($weeklySales)->sum(function ($week) {
                        return (float) ($week['units'] ?? 0);
                    });
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

                    // For visual comparison (bar heights) - use global max across all products
                    $referenceDemand = max($globalMaxWeeklySales, 1);
                    $currentHeightPct = $referenceDemand > 0 ? ($currentStock / $referenceDemand) * 100 : 0;
                    $afterHeightPct = $referenceDemand > 0 ? ($afterStock / $referenceDemand) * 100 : 100;

                    // For percentage labels - use individual product's peak for intuitive display
                    $productPeakDemand = max($peakWeeklySales, 1);
                    $currentPct = $productPeakDemand > 0 ? ($currentStock / $productPeakDemand) * 100 : 0;
                    $afterPct = $productPeakDemand > 0 ? ($afterStock / $productPeakDemand) * 100 : 100;

                    // Bar heights use global scaling for cross-product comparison
                    $currentPositiveHeight = $currentHeightPct > 0 ? min($currentHeightPct, 180) : 0;
                    $currentNegativeHeight = $currentHeightPct < 0 ? min(abs($currentHeightPct), 180) : 0;
                    $afterPositiveHeight = $afterHeightPct > 0 ? min($afterHeightPct, 220) : 0;
                    $afterNegativeHeight = $afterHeightPct < 0 ? min(abs($afterHeightPct), 180) : 0;

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
                            <div class="text-2xl font-bold text-blue-600">{{ number_format($totalPeriodSales, 0) }}</div>
                            <div class="text-xs text-gray-500 mb-1">total sold</div>
                            <div class="w-full bg-gray-200 rounded-full h-2">
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
                        <div class="flex items-end gap-6" style="min-height: 160px; position: relative; padding: 20px 0;">
                            <!-- Reference Grid Lines -->
                            <div class="absolute inset-x-0" style="top: 12px; height: 96px; pointer-events: none;">
                                <div class="absolute inset-x-12 top-0 border-t border-gray-300 border-dashed" title="Visual reference lines for comparing bar heights"></div>
                                <div class="absolute inset-x-12" style="top: 48px; border-top: 1px dashed rgba(148, 163, 184, 0.5);"></div>
                                <div class="absolute inset-x-12 bottom-0 border-t border-gray-200"></div>
                            </div>
                            <!-- Current Stock Bar (Left) -->
                            <div class="flex flex-col items-center" style="width: 60px;">
                                <div class="w-10 bg-gray-200 rounded-b-full relative overflow-visible mx-auto" style="height: 88px;">
                                    <div class="absolute top-0 left-0 right-0 h-0.5 bg-gray-400 z-10" title="Reference line: Bar heights scaled to highest sales across all products"></div>
                                    @if($currentNegativeHeight > 0)
                                        <div class="absolute top-0 left-0 right-0 bg-red-500 rounded-t-full" style="height: {{ $currentNegativeHeight }}%;" title="Short {{ number_format(abs($currentStock), 0) }} units ({{ round(abs($currentPct)) }}% of this product's peak: {{ number_format($peakWeeklySales, 1) }})"></div>
                                    @endif
                                    @if($currentPositiveHeight > 0)
                                        <div class="absolute bottom-0 left-0 right-0 bg-{{ $stockColor }}-500 rounded-b-full" style="height: {{ $currentPositiveHeight }}%;" title="{{ max($currentStock, 0) }} units = {{ round($currentPct) }}% of this product's peak ({{ number_format($peakWeeklySales, 1) }})"></div>
                                    @endif
                                    @if($currentPositiveHeight > 100)
                                        <div class="absolute -top-4 left-1/2 transform -translate-x-1/2 text-[10px] text-{{ $stockColor }}-600 font-semibold">+{{ round($currentPct - 100) }}%</div>
                                    @endif
                                </div>
                            </div>

                            <!-- Chart -->
                            <div style="height: 88px; position: relative; flex: 1;">
                                <canvas id="chart_{{ $item->id }}" style="margin: 0 8px;"></canvas>
                            </div>

                            <!-- After Delivery Bar (Right) -->
                            <div class="flex flex-col items-center" style="width: 60px;">
                                <div class="w-10 bg-gray-200 rounded-b-full relative overflow-visible mx-auto" style="height: 88px;">
                                    <div class="absolute top-0 left-0 right-0 h-0.5 bg-gray-400 z-10" title="Reference line: Bar heights scaled to highest sales across all products"></div>
                                    <div id="after-bar-negative-{{ $item->id }}" class="absolute top-0 left-0 right-0 bg-red-500 rounded-t-full" style="height: {{ $afterNegativeHeight }}%; display: {{ $afterNegativeHeight > 0 ? 'block' : 'none' }};" title="Short {{ number_format(abs($afterStock), 0) }} units ({{ round(abs($afterPct)) }}% of this product's peak: {{ number_format($peakWeeklySales, 1) }})"></div>
                                    <div id="after-bar-positive-{{ $item->id }}" class="absolute bottom-0 left-0 right-0 bg-green-500 rounded-b-full" style="height: {{ $afterPositiveHeight }}%; display: {{ $afterPositiveHeight > 0 ? 'block' : 'none' }};" title="{{ max($afterStock, 0) }} units = {{ round($afterPct) }}% of this product's peak ({{ number_format($peakWeeklySales, 1) }})"></div>
                                    <div id="after-bar-overflow-{{ $item->id }}" class="absolute -top-4 left-1/2 transform -translate-x-1/2 text-[10px] text-green-600 font-semibold" style="display: {{ $afterPositiveHeight > 100 ? 'block' : 'none' }};">+{{ round($afterPct - 100) }}%</div>
                                </div>
                            </div>
                        </div>

                        <!-- Text Labels Row -->
                        <div class="flex items-center justify-between mt-2 text-xs text-gray-600">
                            <div class="text-left">
                                <span class="font-semibold text-{{ $stockColor }}-600">{{ number_format($currentStock, 0) }}</span>
                                <span class="text-[11px] text-gray-500">
                                    ({{ $isCaseProduct ? number_format($currentStock / max($caseUnits, 1), 1).' cs' : 'u' }}, {{ round($currentPct) }}%)
                                </span>
                            </div>
                            <div class="px-2 py-0.5 bg-gray-100 rounded text-gray-700 font-medium">
                                avg {{ number_format($avgWeeklySales, 1) }} · peak {{ number_format($peakWeeklySales, 1) }}
                                @if(isset($contextData['coverage_weeks']))
                                    · cover {{ number_format($contextData['coverage_weeks'], 1) }}→{{ number_format($contextData['target_weeks'] ?? $contextData['coverage_weeks'], 1) }} wk
                                @endif
                            </div>
                            <div class="text-right">
                                <span id="after-stock-value-{{ $item->id }}" class="font-semibold text-green-600">{{ number_format($afterStock, 0) }}</span>
                                <span id="after-stock-label-{{ $item->id }}" class="text-[11px] text-gray-500">
                                    ({{ $isCaseProduct ? number_format($afterStock / max($caseUnits, 1), 1).' cs' : 'u' }}, {{ round($afterPct) }}%)
                                </span>
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
                            <button class="qty-decrease w-8 h-8 bg-red-100 hover:bg-red-200 text-red-700 rounded font-bold"
                                    type="button"
                                    data-item-id="{{ $item->id }}">−</button>
                            <input type="number"
                                   id="qty-input-{{ $item->id }}"
                                   value="{{ number_format($displayOrderQuantity, $quantityPrecision, '.', '') }}"
                                   step="1"
                                   data-item-id="{{ $item->id }}"
                                   data-current-stock="{{ $currentStock }}"
                                   data-case-units="{{ $caseUnits }}"
                                   data-is-case-product="{{ $isCaseProduct ? 1 : 0 }}"
                                   data-product-peak="{{ $peakWeeklySales }}"
                                   data-global-max="{{ $globalMaxWeeklySales }}"
                                   class="qty-input w-20 text-center text-lg font-bold border-2 border-gray-300 rounded py-1">
                            <button class="qty-increase w-8 h-8 bg-green-100 hover:bg-green-200 text-green-700 rounded font-bold"
                                    type="button"
                                    data-item-id="{{ $item->id }}">+</button>
                        </div>
                        <div class="text-center text-xs text-gray-500 mt-1">
                            {{ $quantityLabel }} · <span id="units-label-{{ $item->id }}">{{ number_format($finalUnits, 0) }}</span> units
                        </div>
                    </td>
                    <td class="px-4 py-4 text-right">
                        <div id="total-cost-{{ $item->id }}" class="text-lg font-bold text-gray-900">€{{ number_format($item->total_cost, 2) }}</div>
                        <div class="text-sm text-gray-500">€<span id="unit-cost-{{ $item->id }}">{{ number_format($item->unit_cost, 2) }}</span>/unit</div>
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
</div>
@endif

<!-- Unit Products Table -->
@if($unitProducts->count() > 0)
<div class="mb-6">
    <h3 class="text-lg font-semibold text-gray-900 mb-3 px-2 py-2 bg-green-50 rounded-t-lg border-b-2 border-green-400">
        🔢 Unit Products ({{ $unitProducts->count() }})
    </h3>
    <div class="bg-white rounded-b-lg shadow overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase w-8"></th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Product</th>
                    <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase w-32">Total Sales</th>
                    <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase" style="width: 320px;">Stock Levels & Sales Trend</th>
                    <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase w-32">Suggested</th>
                    <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase w-40">Order Qty</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase w-32">Cost</th>
                    <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase w-32">Action</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                @foreach($unitProducts as $item)
                @php
                    $product = $item->product;
                    $contextData = $item->context_data ?? [];
                    $currentStock = $contextData['current_stock'] ?? 0;
                    $avgWeeklySales = $contextData['avg_weekly_sales'] ?? 0;
                    $weeklySales = $contextData['weekly_sales'] ?? [];
                    $totalPeriodSales = collect($weeklySales)->sum(function ($week) {
                        return (float) ($week['units'] ?? 0);
                    });
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

                    // For visual comparison (bar heights) - use global max across all products
                    $referenceDemand = max($globalMaxWeeklySales, 1);
                    $currentHeightPct = $referenceDemand > 0 ? ($currentStock / $referenceDemand) * 100 : 0;
                    $afterHeightPct = $referenceDemand > 0 ? ($afterStock / $referenceDemand) * 100 : 100;

                    // For percentage labels - use individual product's peak for intuitive display
                    $productPeakDemand = max($peakWeeklySales, 1);
                    $currentPct = $productPeakDemand > 0 ? ($currentStock / $productPeakDemand) * 100 : 0;
                    $afterPct = $productPeakDemand > 0 ? ($afterStock / $productPeakDemand) * 100 : 100;

                    // Bar heights use global scaling for cross-product comparison
                    $currentPositiveHeight = $currentHeightPct > 0 ? min($currentHeightPct, 180) : 0;
                    $currentNegativeHeight = $currentHeightPct < 0 ? min(abs($currentHeightPct), 180) : 0;
                    $afterPositiveHeight = $afterHeightPct > 0 ? min($afterHeightPct, 220) : 0;
                    $afterNegativeHeight = $afterHeightPct < 0 ? min(abs($afterHeightPct), 180) : 0;

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
                            <div class="text-2xl font-bold text-blue-600">{{ number_format($totalPeriodSales, 0) }}</div>
                            <div class="text-xs text-gray-500 mb-1">total sold</div>
                            <div class="w-full bg-gray-200 rounded-full h-2">
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
                        <div class="flex items-end gap-6" style="min-height: 160px; position: relative; padding: 20px 0;">
                            <!-- Reference Grid Lines -->
                            <div class="absolute inset-x-0" style="top: 12px; height: 96px; pointer-events: none;">
                                <div class="absolute inset-x-12 top-0 border-t border-gray-300 border-dashed" title="Visual reference lines for comparing bar heights"></div>
                                <div class="absolute inset-x-12" style="top: 48px; border-top: 1px dashed rgba(148, 163, 184, 0.5);"></div>
                                <div class="absolute inset-x-12 bottom-0 border-t border-gray-200"></div>
                            </div>
                            <!-- Current Stock Bar (Left) -->
                            <div class="flex flex-col items-center" style="width: 60px;">
                                <div class="w-10 bg-gray-200 rounded-b-full relative overflow-visible mx-auto" style="height: 88px;">
                                    <div class="absolute top-0 left-0 right-0 h-0.5 bg-gray-400 z-10" title="Reference line: Bar heights scaled to highest sales across all products"></div>
                                    @if($currentNegativeHeight > 0)
                                        <div class="absolute top-0 left-0 right-0 bg-red-500 rounded-t-full" style="height: {{ $currentNegativeHeight }}%;" title="Short {{ number_format(abs($currentStock), 0) }} units ({{ round(abs($currentPct)) }}% of this product's peak: {{ number_format($peakWeeklySales, 1) }})"></div>
                                    @endif
                                    @if($currentPositiveHeight > 0)
                                        <div class="absolute bottom-0 left-0 right-0 bg-{{ $stockColor }}-500 rounded-b-full" style="height: {{ $currentPositiveHeight }}%;" title="{{ max($currentStock, 0) }} units = {{ round($currentPct) }}% of this product's peak ({{ number_format($peakWeeklySales, 1) }})"></div>
                                    @endif
                                    @if($currentPositiveHeight > 100)
                                        <div class="absolute -top-4 left-1/2 transform -translate-x-1/2 text-[10px] text-{{ $stockColor }}-600 font-semibold">+{{ round($currentPct - 100) }}%</div>
                                    @endif
                                </div>
                            </div>

                            <!-- Chart -->
                            <div style="height: 88px; position: relative; flex: 1;">
                                <canvas id="chart_{{ $item->id }}" style="margin: 0 8px;"></canvas>
                            </div>

                            <!-- After Delivery Bar (Right) -->
                            <div class="flex flex-col items-center" style="width: 60px;">
                                <div class="w-10 bg-gray-200 rounded-b-full relative overflow-visible mx-auto" style="height: 88px;">
                                    <div class="absolute top-0 left-0 right-0 h-0.5 bg-gray-400 z-10" title="Reference line: Bar heights scaled to highest sales across all products"></div>
                                    <div id="after-bar-negative-{{ $item->id }}" class="absolute top-0 left-0 right-0 bg-red-500 rounded-t-full" style="height: {{ $afterNegativeHeight }}%; display: {{ $afterNegativeHeight > 0 ? 'block' : 'none' }};" title="Short {{ number_format(abs($afterStock), 0) }} units ({{ round(abs($afterPct)) }}% of this product's peak: {{ number_format($peakWeeklySales, 1) }})"></div>
                                    <div id="after-bar-positive-{{ $item->id }}" class="absolute bottom-0 left-0 right-0 bg-green-500 rounded-b-full" style="height: {{ $afterPositiveHeight }}%; display: {{ $afterPositiveHeight > 0 ? 'block' : 'none' }};" title="{{ max($afterStock, 0) }} units = {{ round($afterPct) }}% of this product's peak ({{ number_format($peakWeeklySales, 1) }})"></div>
                                    <div id="after-bar-overflow-{{ $item->id }}" class="absolute -top-4 left-1/2 transform -translate-x-1/2 text-[10px] text-green-600 font-semibold" style="display: {{ $afterPositiveHeight > 100 ? 'block' : 'none' }};">+{{ round($afterPct - 100) }}%</div>
                                </div>
                            </div>
                        </div>

                        <!-- Text Labels Row -->
                        <div class="flex items-center justify-between mt-2 text-xs text-gray-600">
                            <div class="text-left">
                                <span class="font-semibold text-{{ $stockColor }}-600">{{ number_format($currentStock, 0) }}</span>
                                <span class="text-[11px] text-gray-500">
                                    ({{ $isCaseProduct ? number_format($currentStock / max($caseUnits, 1), 1).' cs' : 'u' }}, {{ round($currentPct) }}%)
                                </span>
                            </div>
                            <div class="px-2 py-0.5 bg-gray-100 rounded text-gray-700 font-medium">
                                avg {{ number_format($avgWeeklySales, 1) }} · peak {{ number_format($peakWeeklySales, 1) }}
                                @if(isset($contextData['coverage_weeks']))
                                    · cover {{ number_format($contextData['coverage_weeks'], 1) }}→{{ number_format($contextData['target_weeks'] ?? $contextData['coverage_weeks'], 1) }} wk
                                @endif
                            </div>
                            <div class="text-right">
                                <span id="after-stock-value-{{ $item->id }}" class="font-semibold text-green-600">{{ number_format($afterStock, 0) }}</span>
                                <span id="after-stock-label-{{ $item->id }}" class="text-[11px] text-gray-500">
                                    ({{ $isCaseProduct ? number_format($afterStock / max($caseUnits, 1), 1).' cs' : 'u' }}, {{ round($afterPct) }}%)
                                </span>
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
                            <button class="qty-decrease w-8 h-8 bg-red-100 hover:bg-red-200 text-red-700 rounded font-bold"
                                    type="button"
                                    data-item-id="{{ $item->id }}">−</button>
                            <input type="number"
                                   id="qty-input-{{ $item->id }}"
                                   value="{{ number_format($displayOrderQuantity, $quantityPrecision, '.', '') }}"
                                   step="1"
                                   data-item-id="{{ $item->id }}"
                                   data-current-stock="{{ $currentStock }}"
                                   data-case-units="{{ $caseUnits }}"
                                   data-is-case-product="{{ $isCaseProduct ? 1 : 0 }}"
                                   data-product-peak="{{ $peakWeeklySales }}"
                                   data-global-max="{{ $globalMaxWeeklySales }}"
                                   class="qty-input w-20 text-center text-lg font-bold border-2 border-gray-300 rounded py-1">
                            <button class="qty-increase w-8 h-8 bg-green-100 hover:bg-green-200 text-green-700 rounded font-bold"
                                    type="button"
                                    data-item-id="{{ $item->id }}">+</button>
                        </div>
                        <div class="text-center text-xs text-gray-500 mt-1">
                            {{ $quantityLabel }} · <span id="units-label-{{ $item->id }}">{{ number_format($finalUnits, 0) }}</span> units
                        </div>
                    </td>
                    <td class="px-4 py-4 text-right">
                        <div id="total-cost-{{ $item->id }}" class="text-lg font-bold text-gray-900">€{{ number_format($item->total_cost, 2) }}</div>
                        <div class="text-sm text-gray-500">€<span id="unit-cost-{{ $item->id }}">{{ number_format($item->unit_cost, 2) }}</span>/unit</div>
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
</div>
@endif

@if($cheeseProducts->count() === 0 && $refrigeratedProducts->count() === 0 && $caseProducts->count() === 0 && $unitProducts->count() === 0)
    <div class="bg-white rounded-lg shadow p-8 text-center text-gray-500">
        No products have an order quantity greater than zero.
        <a href="{{ $toggleUrl }}" class="text-indigo-600 hover:underline">Show unordered items</a>
    </div>
@endif

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

        @foreach($cheeseProducts->merge($refrigeratedProducts)->merge($caseProducts)->merge($unitProducts) as $item)
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

                // Set point colors - grey for 0 sales, regular color otherwise
                const primaryColor = '{{ $item->review_priority === "review" ? "rgb(239, 68, 68)" : ($item->review_priority === "standard" ? "rgb(234, 179, 8)" : "rgb(34, 197, 94)") }}';
                const greyColor = 'rgb(203, 213, 225)'; // Tailwind slate-300 (lighter grey)
                const pointColors = dataPoints.map(value => value === 0 ? greyColor : primaryColor);
                const pointBorderColors = dataPoints.map(value => value === 0 ? greyColor : primaryColor);
                const pointRadius = dataPoints.map(value => value === 0 ? 2 : 3); // Smaller grey points

                new Chart(chartEl, {
                    type: 'line',
                    data: {
                        labels: labels,
                        datasets: [{
                            label: 'Sales',
                            data: dataPoints,
                            borderColor: primaryColor,
                            backgroundColor: '{{ $item->review_priority === "review" ? "rgba(239, 68, 68, 0.2)" : ($item->review_priority === "standard" ? "rgba(234, 179, 8, 0.2)" : "rgba(34, 197, 94, 0.2)") }}',
                            pointBackgroundColor: pointColors,
                            pointBorderColor: pointBorderColors,
                            pointRadius: pointRadius,
                            pointHoverRadius: dataPoints.map(value => value === 0 ? 4 : 5),
                            // Segment styling - grey lines between zero-sales points
                            segment: {
                                borderColor: ctx => {
                                    // Grey line if both previous and current points are zero
                                    const prevValue = ctx.p0.parsed.y;
                                    const currValue = ctx.p1.parsed.y;
                                    if (prevValue === 0 || currValue === 0) {
                                        return greyColor;
                                    }
                                    return primaryColor;
                                }
                            },
                            tension: 0.4,
                            fill: true,
                            borderWidth: 2
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        layout: {
                            padding: 0
                        },
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
                                        const value = Math.round(context.parsed.y);
                                        if (value === 0) {
                                            return '⚠️ 0 units sold (No sales)';
                                        }
                                        return value + ' units sold';
                                    }
                                }
                            }
                        },
                        scales: {
                            x: { display: false },
                            y: {
                                display: false,
                                beginAtZero: true,
                                max: globalMaxSales > 0 ? globalMaxSales : 10,
                                grace: 0
                            }
                        },
                        elements: {
                            point: {
                                radius: 3,
                                hoverRadius: 5
                            }
                        }
                    }
                });
            })();
        @endforeach
    });

    // Real-time quantity update and bar chart recalculation
    document.addEventListener('DOMContentLoaded', function() {
        const debounceTimers = {};
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';

        // Save quantity to server via AJAX
        function saveQuantityToServer(itemId, quantity) {
            const input = document.getElementById(`qty-input-${itemId}`);
            if (!input) return;

            const isCaseProduct = input.dataset.isCaseProduct === '1';
            const caseUnits = parseFloat(input.dataset.caseUnits) || 1;

            // Show loading state
            input.classList.add('border-blue-400', 'bg-blue-50');
            input.disabled = true;

            // Determine endpoint and parameter based on product type
            const baseUrl = window.location.origin;
            const endpoint = isCaseProduct
                ? `${baseUrl}/order-items/${itemId}/cases`
                : `${baseUrl}/order-items/${itemId}/quantity`;
            const paramName = isCaseProduct ? 'cases' : 'quantity';
            const data = { [paramName]: quantity };

            fetch(endpoint, {
                method: 'PATCH',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'Accept': 'application/json',
                },
                body: JSON.stringify(data),
            })
            .then(response => {
                if (!response.ok) {
                    return response.json().then(err => {
                        throw new Error(err.message || `HTTP ${response.status}: ${response.statusText}`);
                    }).catch(() => {
                        throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                    });
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    // Success - show green flash
                    input.classList.remove('border-blue-400', 'bg-blue-50');
                    input.classList.add('border-green-400', 'bg-green-50');
                    setTimeout(() => {
                        input.classList.remove('border-green-400', 'bg-green-50');
                    }, 1000);

                    // Update cost displays
                    const totalCostEl = document.getElementById(`total-cost-${itemId}`);
                    if (totalCostEl && data.item.total_cost !== undefined) {
                        totalCostEl.textContent = '€' + parseFloat(data.item.total_cost).toFixed(2);
                    }

                    // Update order totals if provided
                    if (data.order_totals) {
                        // You can add order total display elements here if needed
                        console.log('Order totals updated:', data.order_totals);
                    }
                } else {
                    // Error from server
                    input.classList.remove('border-blue-400', 'bg-blue-50');
                    input.classList.add('border-red-400', 'bg-red-50');
                    setTimeout(() => {
                        input.classList.remove('border-red-400', 'bg-red-50');
                    }, 2000);
                    alert(data.error || 'Failed to save quantity');
                }
            })
            .catch(error => {
                // Network or other error
                console.error('Error saving quantity:', error);
                input.classList.remove('border-blue-400', 'bg-blue-50');
                input.classList.add('border-red-400', 'bg-red-50');
                setTimeout(() => {
                    input.classList.remove('border-red-400', 'bg-red-50');
                }, 2000);
                alert('Failed to save quantity:\n' + error.message + '\n\nEndpoint: ' + endpoint);
            })
            .finally(() => {
                input.disabled = false;
            });
        }

        // Debounced save for input changes
        function debouncedSave(itemId, quantity) {
            if (debounceTimers[itemId]) {
                clearTimeout(debounceTimers[itemId]);
            }
            debounceTimers[itemId] = setTimeout(() => {
                saveQuantityToServer(itemId, quantity);
            }, 800);
        }

        function updateAfterStockBar(itemId) {
            const input = document.getElementById(`qty-input-${itemId}`);
            if (!input) return;

            const currentStock = parseFloat(input.dataset.currentStock) || 0;
            const caseUnits = parseFloat(input.dataset.caseUnits) || 1;
            const isCaseProduct = input.dataset.isCaseProduct === '1';
            const productPeak = parseFloat(input.dataset.productPeak) || 1;
            const globalMax = parseFloat(input.dataset.globalMax) || 1;
            const orderQuantity = parseFloat(input.value) || 0;

            // Calculate new units based on whether it's a case product
            const newUnits = isCaseProduct ? (orderQuantity * caseUnits) : orderQuantity;
            const afterStock = currentStock + newUnits;

            // Calculate percentages for display (relative to product peak)
            const afterPct = productPeak > 0 ? (afterStock / productPeak) * 100 : 100;

            // Calculate bar height (relative to global max for visual comparison)
            const referenceDemand = Math.max(globalMax, 1);
            const afterHeightPct = referenceDemand > 0 ? (afterStock / referenceDemand) * 100 : 100;

            // Update bar heights
            const positiveBar = document.getElementById(`after-bar-positive-${itemId}`);
            const negativeBar = document.getElementById(`after-bar-negative-${itemId}`);
            const overflowLabel = document.getElementById(`after-bar-overflow-${itemId}`);

            if (afterStock >= 0) {
                // Positive stock
                const heightPct = Math.min(afterHeightPct, 220);
                if (positiveBar) {
                    positiveBar.style.height = `${heightPct}%`;
                    positiveBar.style.display = heightPct > 0 ? 'block' : 'none';
                    positiveBar.title = `${Math.round(afterStock)} units = ${Math.round(afterPct)}% of this product's peak (${productPeak.toFixed(1)})`;
                }
                if (negativeBar) {
                    negativeBar.style.display = 'none';
                }
                if (overflowLabel) {
                    if (afterHeightPct > 100) {
                        overflowLabel.textContent = `+${Math.round(afterPct - 100)}%`;
                        overflowLabel.style.display = 'block';
                    } else {
                        overflowLabel.style.display = 'none';
                    }
                }
            } else {
                // Negative stock (shortfall)
                const heightPct = Math.min(Math.abs(afterHeightPct), 180);
                if (negativeBar) {
                    negativeBar.style.height = `${heightPct}%`;
                    negativeBar.style.display = 'block';
                    negativeBar.title = `Short ${Math.round(Math.abs(afterStock))} units (${Math.round(Math.abs(afterPct))}% of this product's peak: ${productPeak.toFixed(1)})`;
                }
                if (positiveBar) {
                    positiveBar.style.display = 'none';
                }
                if (overflowLabel) {
                    overflowLabel.style.display = 'none';
                }
            }

            // Update text labels
            const afterStockValue = document.getElementById(`after-stock-value-${itemId}`);
            const afterStockLabel = document.getElementById(`after-stock-label-${itemId}`);
            const unitsLabel = document.getElementById(`units-label-${itemId}`);

            if (afterStockValue) {
                afterStockValue.textContent = Math.round(afterStock).toLocaleString();
            }
            if (afterStockLabel) {
                const displayValue = isCaseProduct
                    ? `${(afterStock / Math.max(caseUnits, 1)).toFixed(1)} cs`
                    : 'u';
                afterStockLabel.textContent = `(${displayValue}, ${Math.round(afterPct)}%)`;
            }
            if (unitsLabel) {
                unitsLabel.textContent = Math.round(newUnits).toLocaleString();
            }
        }

        // Handle input changes (debounced save)
        document.querySelectorAll('.qty-input').forEach(input => {
            input.addEventListener('input', function() {
                const itemId = this.dataset.itemId;
                const quantity = parseFloat(this.value) || 0;

                // Update visual bar immediately
                updateAfterStockBar(itemId);

                // Save to server with debounce
                debouncedSave(itemId, quantity);
            });
        });

        // Handle +/- buttons (immediate save)
        document.querySelectorAll('.qty-decrease').forEach(button => {
            button.addEventListener('click', function() {
                const itemId = this.dataset.itemId;
                const input = document.getElementById(`qty-input-${itemId}`);
                if (input) {
                    const currentValue = parseFloat(input.value) || 0;
                    const newValue = Math.max(0, currentValue - 1);
                    input.value = newValue;

                    // Update visual bar immediately
                    updateAfterStockBar(itemId);

                    // Save immediately (no debounce for buttons)
                    saveQuantityToServer(itemId, newValue);
                }
            });
        });

        document.querySelectorAll('.qty-increase').forEach(button => {
            button.addEventListener('click', function() {
                const itemId = this.dataset.itemId;
                const input = document.getElementById(`qty-input-${itemId}`);
                if (input) {
                    const currentValue = parseFloat(input.value) || 0;
                    const newValue = currentValue + 1;
                    input.value = newValue;

                    // Update visual bar immediately
                    updateAfterStockBar(itemId);

                    // Save immediately (no debounce for buttons)
                    saveQuantityToServer(itemId, newValue);
                }
            });
        });
    });
</script>
