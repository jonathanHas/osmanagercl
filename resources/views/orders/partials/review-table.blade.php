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

    $formatQuantityDisplay = static function (float $value, int $precision = 3): string {
        $roundedWhole = round($value);
        if (abs($value - $roundedWhole) < 0.0005) {
            return number_format($roundedWhole, 0);
        }

        $formatted = number_format($value, $precision);
        if (str_contains($formatted, '.')) {
            $formatted = rtrim(rtrim($formatted, '0'), '.');
        }

        return $formatted;
    };

    $formatQuantityInput = static function (float $value, int $precision = 3): string {
        $roundedWhole = round($value);
        if (abs($value - $roundedWhole) < 0.0005) {
            return (string) $roundedWhole;
        }

        $formatted = number_format($value, $precision, '.', '');
        $formatted = rtrim(rtrim($formatted, '0'), '.');

        return $formatted === '' ? '0' : $formatted;
    };
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
                                    data-range-group="coverage-cheese-{{ $orderSession->id }}"
                                    data-range-role="end"
                                    data-range-anchor="{{ optional($orderSession->order_date)?->format('Y-m-d') }}"
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
                    <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase w-48">Suggested & Order</th>
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
                    $supplierCode = $contextData['supplier_code'] ?? optional($product->supplierLink)->SupplierCode;
                    if (is_string($supplierCode)) {
                        $supplierCode = trim($supplierCode);
                    }
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
                    $orderInputValue = $formatQuantityInput($displayOrderQuantity, $quantityPrecision);
                    $suggestedDisplayText = $formatQuantityDisplay($suggestedDisplayQuantity, $quantityPrecision);
                    $orderDisplayText = $formatQuantityDisplay($displayOrderQuantity, $quantityPrecision);
                    $currentStockCaseText = $isCaseProduct
                        ? $formatQuantityDisplay($currentStock / max($caseUnits, 1), 3)
                        : null;
                    $afterStockCaseText = $isCaseProduct
                        ? $formatQuantityDisplay($afterStock / max($caseUnits, 1), 3)
                        : null;

                    // For percentage labels - use individual product's peak for intuitive display
                    $productPeakDemand = max($peakWeeklySales, 1);
                    $currentPct = $productPeakDemand > 0 ? ($currentStock / $productPeakDemand) * 100 : 0;
                    $afterPct = $productPeakDemand > 0 ? ($afterStock / $productPeakDemand) * 100 : 100;

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
                            Code: {{ $product->CODE ?? 'N/A' }}@if($caseUnits > 1) • {{ rtrim(rtrim(number_format($caseUnits, 2), '0'), '.') }} units/case @endif
                        </div>
                        @if($supplierCode)
                            <div class="mt-1 flex items-center gap-2 text-xs text-gray-500">
                                <span class="uppercase tracking-wide text-[11px] text-slate-400">Supplier</span>
                                <span class="font-mono text-sm text-slate-600" id="supplier-code-{{ $item->id }}">{{ $supplierCode }}</span>
                                <button type="button"
                                        class="copy-supplier-code text-[11px] font-medium text-blue-600 hover:text-blue-700"
                                        data-supplier-code="{{ $supplierCode }}"
                                        title="Copy supplier code">
                                    Copy
                                </button>
                            </div>
                        @endif
                    </td>
                    <td class="px-4 py-4">
                        <div class="text-center">
                            <div class="text-2xl font-bold text-blue-600">{{ number_format($totalPeriodSales, 0) }}</div>
                            <div class="text-xs text-gray-500 mb-1">total sold</div>
                            <div class="mt-2 flex items-center justify-center gap-6 text-xs text-gray-600">
                                <span>avg {{ number_format($avgWeeklySales, 1) }}</span>
                                <span>peak {{ number_format($peakWeeklySales, 1) }}</span>
                            </div>
                            <div class="w-full bg-gray-200 rounded-full h-2">
                                <div
                                    id="stock-level-bar-{{ $item->id }}"
                                    class="bg-{{ $stockColor }}-500 h-2 rounded-full"
                                    data-stock-color="{{ $stockColor }}"
                                    style="width: {{ max(min($currentPct, 100), 0) }}%"
                                ></div>
                            </div>
                            <div
                                id="stock-level-label-{{ $item->id }}"
                                class="text-xs text-{{ $stockColor }}-600 font-medium mt-1"
                                data-stock-color="{{ $stockColor }}"
                            >
                                @if($currentPct < 30)
                                    🚨 CRITICAL
                                @elseif($currentPct < 50)
                                    ⚠️ Low
                                @elseif($currentPct < 100)
                                    ✓ Moderate
                                @else
                                    ✓ Good
                                @endif
                            </div>
                        </div>
                    </td>
                    <td class="px-4 py-4 align-bottom">
                        <div class="flex flex-col justify-between gap-3" style="min-height: 160px;">
                            <div class="relative" style="height: 110px;">
                                <canvas id="chart_{{ $item->id }}" class="w-full h-full"></canvas>
                            </div>
                            <div class="flex flex-col gap-2 text-xs text-gray-600">
                                <div class="flex items-start justify-between gap-2">
                                    <div>
                                        <div class="text-[11px] uppercase tracking-wide text-slate-500">Current stock</div>
                                        <div class="flex items-center gap-2">
                                            <div id="current-stock-value-{{ $item->id }}" class="text-xl font-semibold text-{{ $stockColor }}-600" data-stock-color="{{ $stockColor }}">{{ number_format($currentStock, 0) }}</div>
                                            @if($product?->ID && $currentStock < 0)
                                                <button
                                                    type="button"
                                                    class="reset-stock-button inline-flex items-center gap-1 rounded border border-green-200 px-2 py-1 text-[11px] font-semibold text-green-600 hover:bg-green-50 focus:outline-none focus:ring-2 focus:ring-green-200"
                                                    data-product-id="{{ $product->ID }}"
                                                    data-item-id="{{ $item->id }}"
                                                    data-case-units="{{ $caseUnits }}"
                                                    data-is-case-product="{{ $isCaseProduct ? 1 : 0 }}"
                                                    data-product-name="{{ e($product->NAME ?? 'Product') }}"
                                                    title="Set stock to zero"
                                                    aria-label="Set stock to zero for {{ e($product->NAME ?? 'product') }}"
                                                >
                                                    <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 10.5 10 5l5.5 5.5M10 5v10.5" />
                                                    </svg>
                                                    <span>0</span>
                                                </button>
                                            @endif
                                        </div>
                                        <div id="current-stock-subtext-{{ $item->id }}" class="text-[11px] text-gray-500">
                                            @if($isCaseProduct)
                                                {{ $currentStockCaseText }} cases
                                            @else
                                                {{ number_format($currentStock, 0) }} units
                                            @endif
                                        </div>
                                    </div>
                                    <div class="text-right">
                                        <div class="text-[11px] uppercase tracking-wide text-slate-500">After order</div>
                                        <div id="after-stock-value-{{ $item->id }}" class="text-2xl font-semibold text-green-600">{{ number_format($afterStock, 0) }}</div>
                                        <div id="after-stock-subtext-{{ $item->id }}" class="text-[11px] text-gray-500">
                                            @if($isCaseProduct)
                                                {{ $afterStockCaseText }} cases
                                            @else
                                                {{ number_format($afterStock, 0) }} units
                                            @endif
                                        </div>
                                    </div>
                                </div>
                                @if(isset($contextData['coverage_weeks']))
                                    <div class="flex justify-end text-[11px] text-gray-500">
                                        Cover {{ number_format($contextData['coverage_weeks'], 1) }}→{{ number_format($contextData['target_weeks'] ?? $contextData['coverage_weeks'], 1) }} wk
                                    </div>
                                @endif
                            </div>
                        </div>
                    </td>
                    <td class="px-4 py-4">
                        <div class="flex flex-col items-center gap-3">
                            <div class="text-center">
                                <div class="text-[11px] font-semibold text-gray-500 uppercase tracking-wide">Suggested</div>
                                <div class="mt-1 text-xl font-bold text-purple-700">
                                    {{ $suggestedDisplayText }} {{ $quantityLabel }}
                                </div>
                                <div class="text-xs text-gray-500">{{ number_format($suggestedUnits, 0) }} units</div>
                            </div>
                            <div class="flex items-center justify-center gap-1">
                                <button class="qty-decrease w-8 h-8 bg-red-100 hover:bg-red-200 text-red-700 rounded font-bold"
                                        type="button"
                                        data-item-id="{{ $item->id }}">−</button>
                                <input type="number"
                                       id="qty-input-{{ $item->id }}"
                                       value="{{ $orderInputValue }}"
                                       step="1"
                                       data-item-id="{{ $item->id }}"
                                       data-current-stock="{{ $currentStock }}"
                                       data-case-units="{{ $caseUnits }}"
                                       data-is-case-product="{{ $isCaseProduct ? 1 : 0 }}"
                                       data-product-peak="{{ $peakWeeklySales }}"
                                       data-quantity-precision="{{ $quantityPrecision }}"
                                       class="qty-input w-20 text-center text-lg font-bold border-2 border-gray-300 rounded py-1">
                                <button class="qty-increase w-8 h-8 bg-green-100 hover:bg-green-200 text-green-700 rounded font-bold"
                                        type="button"
                                        data-item-id="{{ $item->id }}">+</button>
                            </div>
                            <div class="text-xs text-gray-500 text-center">
                                Order: <span id="units-label-{{ $item->id }}">{{ number_format($finalUnits, 0) }}</span> units
                                <span class="text-gray-400">(<span id="order-quantity-display-{{ $item->id }}">{{ $orderDisplayText }}</span> {{ $quantityLabel }})</span>
                            </div>
                            @if(abs($finalUnits - $suggestedUnits) > 0.001)
                                <div class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-orange-50 text-orange-600">
                                    Adjusted from {{ $suggestedDisplayText }} {{ $quantityLabel }}
                                </div>
                            @endif
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
                                    data-range-group="coverage-refrigerated-{{ $orderSession->id }}"
                                    data-range-role="end"
                                    data-range-anchor="{{ optional($orderSession->order_date)?->format('Y-m-d') }}"
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
                    <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase w-48">Suggested & Order</th>
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
                    $supplierCode = $contextData['supplier_code'] ?? optional($product->supplierLink)->SupplierCode;
                    if (is_string($supplierCode)) {
                        $supplierCode = trim($supplierCode);
                    }
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
                    $orderInputValue = $formatQuantityInput($displayOrderQuantity, $quantityPrecision);
                    $suggestedDisplayText = $formatQuantityDisplay($suggestedDisplayQuantity, $quantityPrecision);
                    $orderDisplayText = $formatQuantityDisplay($displayOrderQuantity, $quantityPrecision);
                    $currentStockCaseText = $isCaseProduct
                        ? $formatQuantityDisplay($currentStock / max($caseUnits, 1), 3)
                        : null;
                    $afterStockCaseText = $isCaseProduct
                        ? $formatQuantityDisplay($afterStock / max($caseUnits, 1), 3)
                        : null;

                    // For percentage labels - use individual product's peak for intuitive display
                    $productPeakDemand = max($peakWeeklySales, 1);
                    $currentPct = $productPeakDemand > 0 ? ($currentStock / $productPeakDemand) * 100 : 0;
                    $afterPct = $productPeakDemand > 0 ? ($afterStock / $productPeakDemand) * 100 : 100;

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
                            Code: {{ $product->CODE ?? 'N/A' }}@if($caseUnits > 1) • {{ rtrim(rtrim(number_format($caseUnits, 2), '0'), '.') }} units/case @endif
                        </div>
                        @if($supplierCode)
                            <div class="mt-1 flex items-center gap-2 text-xs text-gray-500">
                                <span class="uppercase tracking-wide text-[11px] text-slate-400">Supplier</span>
                                <span class="font-mono text-sm text-slate-600" id="supplier-code-{{ $item->id }}">{{ $supplierCode }}</span>
                                <button type="button"
                                        class="copy-supplier-code text-[11px] font-medium text-blue-600 hover:text-blue-700"
                                        data-supplier-code="{{ $supplierCode }}"
                                        title="Copy supplier code">
                                    Copy
                                </button>
                            </div>
                        @endif
                    </td>
                    <td class="px-4 py-4">
                        <div class="text-center">
                            <div class="text-2xl font-bold text-blue-600">{{ number_format($totalPeriodSales, 0) }}</div>
                            <div class="text-xs text-gray-500 mb-1">total sold</div>
                            <div class="mt-2 flex items-center justify-center gap-6 text-xs text-gray-600">
                                <span>avg {{ number_format($avgWeeklySales, 1) }}</span>
                                <span>peak {{ number_format($peakWeeklySales, 1) }}</span>
                            </div>
                            <div class="w-full bg-gray-200 rounded-full h-2">
                                <div
                                    id="stock-level-bar-{{ $item->id }}"
                                    class="bg-{{ $stockColor }}-500 h-2 rounded-full"
                                    data-stock-color="{{ $stockColor }}"
                                    style="width: {{ max(min($currentPct, 100), 0) }}%"
                                ></div>
                            </div>
                            <div
                                id="stock-level-label-{{ $item->id }}"
                                class="text-xs text-{{ $stockColor }}-600 font-medium mt-1"
                                data-stock-color="{{ $stockColor }}"
                            >
                                @if($currentPct < 30)
                                    🚨 CRITICAL
                                @elseif($currentPct < 50)
                                    ⚠️ Low
                                @elseif($currentPct < 100)
                                    ✓ Moderate
                                @else
                                    ✓ Good
                                @endif
                            </div>
                        </div>
                    </td>
                    <td class="px-4 py-4 align-bottom">
                        <div class="flex flex-col justify-between gap-3" style="min-height: 160px;">
                            <div class="relative" style="height: 110px;">
                                <canvas id="chart_{{ $item->id }}" class="w-full h-full"></canvas>
                            </div>
                            <div class="flex flex-col gap-2 text-xs text-gray-600">
                                <div class="flex items-start justify-between gap-2">
                                    <div>
                                        <div class="text-[11px] uppercase tracking-wide text-slate-500">Current stock</div>
                                        <div class="flex items-center gap-2">
                                            <div id="current-stock-value-{{ $item->id }}" class="text-xl font-semibold text-{{ $stockColor }}-600" data-stock-color="{{ $stockColor }}">{{ number_format($currentStock, 0) }}</div>
                                            @if($product?->ID && $currentStock < 0)
                                                <button
                                                    type="button"
                                                    class="reset-stock-button inline-flex items-center gap-1 rounded border border-green-200 px-2 py-1 text-[11px] font-semibold text-green-600 hover:bg-green-50 focus:outline-none focus:ring-2 focus:ring-green-200"
                                                    data-product-id="{{ $product->ID }}"
                                                    data-item-id="{{ $item->id }}"
                                                    data-case-units="{{ $caseUnits }}"
                                                    data-is-case-product="{{ $isCaseProduct ? 1 : 0 }}"
                                                    data-product-name="{{ e($product->NAME ?? 'Product') }}"
                                                    title="Set stock to zero"
                                                    aria-label="Set stock to zero for {{ e($product->NAME ?? 'product') }}"
                                                >
                                                    <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 10.5 10 5l5.5 5.5M10 5v10.5" />
                                                    </svg>
                                                    <span>0</span>
                                                </button>
                                            @endif
                                        </div>
                                        <div id="current-stock-subtext-{{ $item->id }}" class="text-[11px] text-gray-500">
                                            @if($isCaseProduct)
                                                {{ $currentStockCaseText }} cases
                                            @else
                                                {{ number_format($currentStock, 0) }} units
                                            @endif
                                        </div>
                                    </div>
                                    <div class="text-right">
                                        <div class="text-[11px] uppercase tracking-wide text-slate-500">After order</div>
                                        <div id="after-stock-value-{{ $item->id }}" class="text-2xl font-semibold text-green-600">{{ number_format($afterStock, 0) }}</div>
                                        <div id="after-stock-subtext-{{ $item->id }}" class="text-[11px] text-gray-500">
                                            @if($isCaseProduct)
                                                {{ $afterStockCaseText }} cases
                                            @else
                                                {{ number_format($afterStock, 0) }} units
                                            @endif
                                        </div>
                                    </div>
                                </div>
                                @if(isset($contextData['coverage_weeks']))
                                    <div class="flex justify-end text-[11px] text-gray-500">
                                        Cover {{ number_format($contextData['coverage_weeks'], 1) }}→{{ number_format($contextData['target_weeks'] ?? $contextData['coverage_weeks'], 1) }} wk
                                    </div>
                                @endif
                            </div>
                        </div>
                    </td>
                    <td class="px-4 py-4">
                        <div class="flex flex-col items-center gap-3">
                            <div class="text-center">
                                <div class="text-[11px] font-semibold text-gray-500 uppercase tracking-wide">Suggested</div>
                                <div class="mt-1 text-xl font-bold text-purple-700">
                                    {{ $suggestedDisplayText }} {{ $quantityLabel }}
                                </div>
                                <div class="text-xs text-gray-500">{{ number_format($suggestedUnits, 0) }} units</div>
                            </div>
                            <div class="flex items-center justify-center gap-1">
                                <button class="qty-decrease w-8 h-8 bg-red-100 hover:bg-red-200 text-red-700 rounded font-bold"
                                        type="button"
                                        data-item-id="{{ $item->id }}">−</button>
                                <input type="number"
                                       id="qty-input-{{ $item->id }}"
                                       value="{{ $orderInputValue }}"
                                       step="1"
                                       data-item-id="{{ $item->id }}"
                                       data-current-stock="{{ $currentStock }}"
                                       data-case-units="{{ $caseUnits }}"
                                       data-is-case-product="{{ $isCaseProduct ? 1 : 0 }}"
                                       data-product-peak="{{ $peakWeeklySales }}"
                                       data-quantity-precision="{{ $quantityPrecision }}"
                                       class="qty-input w-20 text-center text-lg font-bold border-2 border-gray-300 rounded py-1">
                                <button class="qty-increase w-8 h-8 bg-green-100 hover:bg-green-200 text-green-700 rounded font-bold"
                                        type="button"
                                        data-item-id="{{ $item->id }}">+</button>
                            </div>
                            <div class="text-xs text-gray-500 text-center">
                                Order: <span id="units-label-{{ $item->id }}">{{ number_format($finalUnits, 0) }}</span> units
                                <span class="text-gray-400">(<span id="order-quantity-display-{{ $item->id }}">{{ $orderDisplayText }}</span> {{ $quantityLabel }})</span>
                            </div>
                            @if(abs($finalUnits - $suggestedUnits) > 0.001)
                                <div class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-orange-50 text-orange-600">
                                    Adjusted from {{ $suggestedDisplayText }} {{ $quantityLabel }}
                                </div>
                            @endif
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
                    <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase w-48">Suggested & Order</th>
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
                    $supplierCode = $contextData['supplier_code'] ?? optional($product->supplierLink)->SupplierCode;
                    if (is_string($supplierCode)) {
                        $supplierCode = trim($supplierCode);
                    }
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
                    $orderInputValue = $formatQuantityInput($displayOrderQuantity, $quantityPrecision);
                    $suggestedDisplayText = $formatQuantityDisplay($suggestedDisplayQuantity, $quantityPrecision);
                    $orderDisplayText = $formatQuantityDisplay($displayOrderQuantity, $quantityPrecision);
                    $currentStockCaseText = $isCaseProduct
                        ? $formatQuantityDisplay($currentStock / max($caseUnits, 1), 3)
                        : null;
                    $afterStockCaseText = $isCaseProduct
                        ? $formatQuantityDisplay($afterStock / max($caseUnits, 1), 3)
                        : null;

                    // For percentage labels - use individual product's peak for intuitive display
                    $productPeakDemand = max($peakWeeklySales, 1);
                    $currentPct = $productPeakDemand > 0 ? ($currentStock / $productPeakDemand) * 100 : 0;
                    $afterPct = $productPeakDemand > 0 ? ($afterStock / $productPeakDemand) * 100 : 100;

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
                            Code: {{ $product->CODE ?? 'N/A' }}@if($caseUnits > 1) • {{ rtrim(rtrim(number_format($caseUnits, 2), '0'), '.') }} units/case @endif
                        </div>
                        @if($supplierCode)
                            <div class="mt-1 flex items-center gap-2 text-xs text-gray-500">
                                <span class="uppercase tracking-wide text-[11px] text-slate-400">Supplier</span>
                                <span class="font-mono text-sm text-slate-600" id="supplier-code-{{ $item->id }}">{{ $supplierCode }}</span>
                                <button type="button"
                                        class="copy-supplier-code text-[11px] font-medium text-blue-600 hover:text-blue-700"
                                        data-supplier-code="{{ $supplierCode }}"
                                        title="Copy supplier code">
                                    Copy
                                </button>
                            </div>
                        @endif
                    </td>
                    <td class="px-4 py-4">
                        <div class="text-center">
                            <div class="text-2xl font-bold text-blue-600">{{ number_format($totalPeriodSales, 0) }}</div>
                            <div class="text-xs text-gray-500 mb-1">total sold</div>
                            <div class="mt-2 flex items-center justify-center gap-6 text-xs text-gray-600">
                                <span>avg {{ number_format($avgWeeklySales, 1) }}</span>
                                <span>peak {{ number_format($peakWeeklySales, 1) }}</span>
                            </div>
                            <div class="w-full bg-gray-200 rounded-full h-2">
                                <div
                                    id="stock-level-bar-{{ $item->id }}"
                                    class="bg-{{ $stockColor }}-500 h-2 rounded-full"
                                    data-stock-color="{{ $stockColor }}"
                                    style="width: {{ max(min($currentPct, 100), 0) }}%"
                                ></div>
                            </div>
                            <div
                                id="stock-level-label-{{ $item->id }}"
                                class="text-xs text-{{ $stockColor }}-600 font-medium mt-1"
                                data-stock-color="{{ $stockColor }}"
                            >
                                @if($currentPct < 30)
                                    🚨 CRITICAL
                                @elseif($currentPct < 50)
                                    ⚠️ Low
                                @elseif($currentPct < 100)
                                    ✓ Moderate
                                @else
                                    ✓ Good
                                @endif
                            </div>
                        </div>
                    </td>
                    <td class="px-4 py-4 align-bottom">
                        <div class="flex flex-col justify-between gap-3" style="min-height: 160px;">
                            <div class="relative" style="height: 110px;">
                                <canvas id="chart_{{ $item->id }}" class="w-full h-full"></canvas>
                            </div>
                            <div class="flex flex-col gap-2 text-xs text-gray-600">
                                <div class="flex items-start justify-between gap-2">
                                    <div>
                                        <div class="text-[11px] uppercase tracking-wide text-slate-500">Current stock</div>
                                        <div class="flex items-center gap-2">
                                            <div id="current-stock-value-{{ $item->id }}" class="text-xl font-semibold text-{{ $stockColor }}-600" data-stock-color="{{ $stockColor }}">{{ number_format($currentStock, 0) }}</div>
                                            @if($product?->ID && $currentStock < 0)
                                                <button
                                                    type="button"
                                                    class="reset-stock-button inline-flex items-center gap-1 rounded border border-green-200 px-2 py-1 text-[11px] font-semibold text-green-600 hover:bg-green-50 focus:outline-none focus:ring-2 focus:ring-green-200"
                                                    data-product-id="{{ $product->ID }}"
                                                    data-item-id="{{ $item->id }}"
                                                    data-case-units="{{ $caseUnits }}"
                                                    data-is-case-product="{{ $isCaseProduct ? 1 : 0 }}"
                                                    data-product-name="{{ e($product->NAME ?? 'Product') }}"
                                                    title="Set stock to zero"
                                                    aria-label="Set stock to zero for {{ e($product->NAME ?? 'product') }}"
                                                >
                                                    <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 10.5 10 5l5.5 5.5M10 5v10.5" />
                                                    </svg>
                                                    <span>0</span>
                                                </button>
                                            @endif
                                        </div>
                                        <div id="current-stock-subtext-{{ $item->id }}" class="text-[11px] text-gray-500">
                                            @if($isCaseProduct)
                                                {{ $currentStockCaseText }} cases
                                            @else
                                                {{ number_format($currentStock, 0) }} units
                                            @endif
                                        </div>
                                    </div>
                                    <div class="text-right">
                                        <div class="text-[11px] uppercase tracking-wide text-slate-500">After order</div>
                                        <div id="after-stock-value-{{ $item->id }}" class="text-2xl font-semibold text-green-600">{{ number_format($afterStock, 0) }}</div>
                                        <div id="after-stock-subtext-{{ $item->id }}" class="text-[11px] text-gray-500">
                                            @if($isCaseProduct)
                                                {{ $afterStockCaseText }} cases
                                            @else
                                                {{ number_format($afterStock, 0) }} units
                                            @endif
                                        </div>
                                    </div>
                                </div>
                                @if(isset($contextData['coverage_weeks']))
                                    <div class="flex justify-end text-[11px] text-gray-500">
                                        Cover {{ number_format($contextData['coverage_weeks'], 1) }}→{{ number_format($contextData['target_weeks'] ?? $contextData['coverage_weeks'], 1) }} wk
                                    </div>
                                @endif
                            </div>
                        </div>
                    </td>
                    <td class="px-4 py-4">
                        <div class="flex flex-col items-center gap-3">
                            <div class="text-center">
                                <div class="text-[11px] font-semibold text-gray-500 uppercase tracking-wide">Suggested</div>
                                <div class="mt-1 text-xl font-bold text-purple-700">
                                    {{ $suggestedDisplayText }} {{ $quantityLabel }}
                                </div>
                                <div class="text-xs text-gray-500">{{ number_format($suggestedUnits, 0) }} units</div>
                            </div>
                            <div class="flex items-center justify-center gap-1">
                                <button class="qty-decrease w-8 h-8 bg-red-100 hover:bg-red-200 text-red-700 rounded font-bold"
                                        type="button"
                                        data-item-id="{{ $item->id }}">−</button>
                                <input type="number"
                                       id="qty-input-{{ $item->id }}"
                                       value="{{ $orderInputValue }}"
                                       step="1"
                                       data-item-id="{{ $item->id }}"
                                       data-current-stock="{{ $currentStock }}"
                                       data-case-units="{{ $caseUnits }}"
                                       data-is-case-product="{{ $isCaseProduct ? 1 : 0 }}"
                                       data-product-peak="{{ $peakWeeklySales }}"
                                       data-quantity-precision="{{ $quantityPrecision }}"
                                       class="qty-input w-20 text-center text-lg font-bold border-2 border-gray-300 rounded py-1">
                                <button class="qty-increase w-8 h-8 bg-green-100 hover:bg-green-200 text-green-700 rounded font-bold"
                                        type="button"
                                        data-item-id="{{ $item->id }}">+</button>
                            </div>
                            <div class="text-xs text-gray-500 text-center">
                                Order: <span id="units-label-{{ $item->id }}">{{ number_format($finalUnits, 0) }}</span> units
                                <span class="text-gray-400">(<span id="order-quantity-display-{{ $item->id }}">{{ $orderDisplayText }}</span> {{ $quantityLabel }})</span>
                            </div>
                            @if(abs($finalUnits - $suggestedUnits) > 0.001)
                                <div class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-orange-50 text-orange-600">
                                    Adjusted from {{ $suggestedDisplayText }} {{ $quantityLabel }}
                                </div>
                            @endif
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
                    <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase w-48">Suggested & Order</th>
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
                    $supplierCode = $contextData['supplier_code'] ?? optional($product->supplierLink)->SupplierCode;
                    if (is_string($supplierCode)) {
                        $supplierCode = trim($supplierCode);
                    }
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
                    $orderInputValue = $formatQuantityInput($displayOrderQuantity, $quantityPrecision);
                    $suggestedDisplayText = $formatQuantityDisplay($suggestedDisplayQuantity, $quantityPrecision);
                    $orderDisplayText = $formatQuantityDisplay($displayOrderQuantity, $quantityPrecision);
                    $currentStockCaseText = $isCaseProduct
                        ? $formatQuantityDisplay($currentStock / max($caseUnits, 1), 3)
                        : null;
                    $afterStockCaseText = $isCaseProduct
                        ? $formatQuantityDisplay($afterStock / max($caseUnits, 1), 3)
                        : null;

                    // For percentage labels - use individual product's peak for intuitive display
                    $productPeakDemand = max($peakWeeklySales, 1);
                    $currentPct = $productPeakDemand > 0 ? ($currentStock / $productPeakDemand) * 100 : 0;
                    $afterPct = $productPeakDemand > 0 ? ($afterStock / $productPeakDemand) * 100 : 100;

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
                            Code: {{ $product->CODE ?? 'N/A' }}@if($caseUnits > 1) • {{ rtrim(rtrim(number_format($caseUnits, 2), '0'), '.') }} units/case @endif
                        </div>
                        @if($supplierCode)
                            <div class="mt-1 flex items-center gap-2 text-xs text-gray-500">
                                <span class="uppercase tracking-wide text-[11px] text-slate-400">Supplier</span>
                                <span class="font-mono text-sm text-slate-600" id="supplier-code-{{ $item->id }}">{{ $supplierCode }}</span>
                                <button type="button"
                                        class="copy-supplier-code text-[11px] font-medium text-blue-600 hover:text-blue-700"
                                        data-supplier-code="{{ $supplierCode }}"
                                        title="Copy supplier code">
                                    Copy
                                </button>
                            </div>
                        @endif
                    </td>
                    <td class="px-4 py-4">
                        <div class="text-center">
                            <div class="text-2xl font-bold text-blue-600">{{ number_format($totalPeriodSales, 0) }}</div>
                            <div class="text-xs text-gray-500 mb-1">total sold</div>
                            <div class="mt-2 flex items-center justify-center gap-6 text-xs text-gray-600">
                                <span>avg {{ number_format($avgWeeklySales, 1) }}</span>
                                <span>peak {{ number_format($peakWeeklySales, 1) }}</span>
                            </div>
                            <div class="w-full bg-gray-200 rounded-full h-2">
                                <div
                                    id="stock-level-bar-{{ $item->id }}"
                                    class="bg-{{ $stockColor }}-500 h-2 rounded-full"
                                    data-stock-color="{{ $stockColor }}"
                                    style="width: {{ max(min($currentPct, 100), 0) }}%"
                                ></div>
                            </div>
                            <div
                                id="stock-level-label-{{ $item->id }}"
                                class="text-xs text-{{ $stockColor }}-600 font-medium mt-1"
                                data-stock-color="{{ $stockColor }}"
                            >
                                @if($currentPct < 30)
                                    🚨 CRITICAL
                                @elseif($currentPct < 50)
                                    ⚠️ Low
                                @elseif($currentPct < 100)
                                    ✓ Moderate
                                @else
                                    ✓ Good
                                @endif
                            </div>
                        </div>
                    </td>
                    <td class="px-4 py-4 align-bottom">
                        <div class="flex flex-col justify-between gap-3" style="min-height: 160px;">
                            <div class="relative" style="height: 110px;">
                                <canvas id="chart_{{ $item->id }}" class="w-full h-full"></canvas>
                            </div>
                            <div class="flex flex-col gap-2 text-xs text-gray-600">
                                <div class="flex items-start justify-between gap-2">
                                    <div>
                                        <div class="text-[11px] uppercase tracking-wide text-slate-500">Current stock</div>
                                        <div class="flex items-center gap-2">
                                            <div id="current-stock-value-{{ $item->id }}" class="text-xl font-semibold text-{{ $stockColor }}-600" data-stock-color="{{ $stockColor }}">{{ number_format($currentStock, 0) }}</div>
                                            @if($product?->ID && $currentStock < 0)
                                                <button
                                                    type="button"
                                                    class="reset-stock-button inline-flex items-center gap-1 rounded border border-green-200 px-2 py-1 text-[11px] font-semibold text-green-600 hover:bg-green-50 focus:outline-none focus:ring-2 focus:ring-green-200"
                                                    data-product-id="{{ $product->ID }}"
                                                    data-item-id="{{ $item->id }}"
                                                    data-case-units="{{ $caseUnits }}"
                                                    data-is-case-product="{{ $isCaseProduct ? 1 : 0 }}"
                                                    data-product-name="{{ e($product->NAME ?? 'Product') }}"
                                                    title="Set stock to zero"
                                                    aria-label="Set stock to zero for {{ e($product->NAME ?? 'product') }}"
                                                >
                                                    <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 10.5 10 5l5.5 5.5M10 5v10.5" />
                                                    </svg>
                                                    <span>0</span>
                                                </button>
                                            @endif
                                        </div>
                                        <div id="current-stock-subtext-{{ $item->id }}" class="text-[11px] text-gray-500">
                                            @if($isCaseProduct)
                                                {{ $currentStockCaseText }} cases
                                            @else
                                                {{ number_format($currentStock, 0) }} units
                                            @endif
                                        </div>
                                    </div>
                                    <div class="text-right">
                                        <div class="text-[11px] uppercase tracking-wide text-slate-500">After order</div>
                                        <div id="after-stock-value-{{ $item->id }}" class="text-2xl font-semibold text-green-600">{{ number_format($afterStock, 0) }}</div>
                                        <div id="after-stock-subtext-{{ $item->id }}" class="text-[11px] text-gray-500">
                                            @if($isCaseProduct)
                                                {{ $afterStockCaseText }} cases
                                            @else
                                                {{ number_format($afterStock, 0) }} units
                                            @endif
                                        </div>
                                    </div>
                                </div>
                                @if(isset($contextData['coverage_weeks']))
                                    <div class="flex justify-end text-[11px] text-gray-500">
                                        Cover {{ number_format($contextData['coverage_weeks'], 1) }}→{{ number_format($contextData['target_weeks'] ?? $contextData['coverage_weeks'], 1) }} wk
                                    </div>
                                @endif
                            </div>
                        </div>
                    </td>
                    <td class="px-4 py-4">
                        <div class="flex flex-col items-center gap-3">
                            <div class="text-center">
                                <div class="text-[11px] font-semibold text-gray-500 uppercase tracking-wide">Suggested</div>
                                <div class="mt-1 text-xl font-bold text-purple-700">
                                    {{ $suggestedDisplayText }} {{ $quantityLabel }}
                                </div>
                                <div class="text-xs text-gray-500">{{ number_format($suggestedUnits, 0) }} units</div>
                            </div>
                            <div class="flex items-center justify-center gap-1">
                                <button class="qty-decrease w-8 h-8 bg-red-100 hover:bg-red-200 text-red-700 rounded font-bold"
                                        type="button"
                                        data-item-id="{{ $item->id }}">−</button>
                                <input type="number"
                                       id="qty-input-{{ $item->id }}"
                                       value="{{ $orderInputValue }}"
                                       step="1"
                                       data-item-id="{{ $item->id }}"
                                       data-current-stock="{{ $currentStock }}"
                                       data-case-units="{{ $caseUnits }}"
                                       data-is-case-product="{{ $isCaseProduct ? 1 : 0 }}"
                                       data-product-peak="{{ $peakWeeklySales }}"
                                       data-quantity-precision="{{ $quantityPrecision }}"
                                       class="qty-input w-20 text-center text-lg font-bold border-2 border-gray-300 rounded py-1">
                                <button class="qty-increase w-8 h-8 bg-green-100 hover:bg-green-200 text-green-700 rounded font-bold"
                                        type="button"
                                        data-item-id="{{ $item->id }}">+</button>
                            </div>
                            <div class="text-xs text-gray-500 text-center">
                                Order: <span id="units-label-{{ $item->id }}">{{ number_format($finalUnits, 0) }}</span> units
                                <span class="text-gray-400">(<span id="order-quantity-display-{{ $item->id }}">{{ $orderDisplayText }}</span> {{ $quantityLabel }})</span>
                            </div>
                            @if(abs($finalUnits - $suggestedUnits) > 0.001)
                                <div class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-orange-50 text-orange-600">
                                    Adjusted from {{ $suggestedDisplayText }} {{ $quantityLabel }}
                                </div>
                            @endif
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
    const clampPrecision = precision => Math.min(Math.max(precision, 0), 3);

    const formatNumberForDisplay = (value, precision = 3) => {
        if (!Number.isFinite(value)) {
            return '0';
        }

        const safePrecision = clampPrecision(precision);
        const factor = 10 ** safePrecision;
        const rounded = Math.round(value * factor) / factor;
        if (Math.abs(rounded - Math.round(rounded)) < 0.0005) {
            return Math.round(rounded).toLocaleString();
        }

        return rounded.toLocaleString(undefined, {
            minimumFractionDigits: 0,
            maximumFractionDigits: safePrecision
        });
    };

    const stockToneForPercent = (percent) => {
        if (percent < 50) {
            return 'red';
        }
        if (percent < 100) {
            return 'yellow';
        }

        return 'green';
    };

    const stockStatusLabelForPercent = (percent) => {
        if (percent < 30) {
            return '🚨 CRITICAL';
        }
        if (percent < 50) {
            return '⚠️ Low';
        }
        if (percent < 100) {
            return '✓ Moderate';
        }

        return '✓ Good';
    };

    const applyStockToneClass = (element, tone, mode = 'text') => {
        if (!element) {
            return;
        }

        const tones = ['red', 'yellow', 'green'];
        tones.forEach(color => {
            const className = mode === 'bar'
                ? `bg-${color}-500`
                : `text-${color}-600`;
            element.classList.remove(className);
        });

        element.classList.add(mode === 'bar' ? `bg-${tone}-500` : `text-${tone}-600`);
        element.dataset.stockColor = tone;
    };

    document.addEventListener('DOMContentLoaded', function() {
        if (window.chartsInitialized) return;
        window.chartsInitialized = true;

        window.productCharts = window.productCharts || {};

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
                $currentStockValue = (float) ($contextData['current_stock'] ?? 0);
                $caseUnitsValue = (float) ($contextData['case_units'] ?? 1);
                $isCaseProductValue = (bool) ($contextData['is_case_product'] ?? ($caseUnitsValue > 1));
                $suggestedUnitsValue = (float) ($item->suggested_quantity ?? 0);
                $finalUnitsValue = (float) ($item->final_quantity ?? $suggestedUnitsValue);
                $afterStockValue = $currentStockValue + $finalUnitsValue;
                $avgWeeklySalesValue = (float) ($contextData['avg_weekly_sales'] ?? 0);
                $peakWeeklySalesValue = $contextData['peak_weekly_sales'] ?? (count($weekUnits) > 0 ? max($weekUnits) : $avgWeeklySalesValue);
                if ($peakWeeklySalesValue <= 0 && $avgWeeklySalesValue > 0) {
                    $peakWeeklySalesValue = $avgWeeklySalesValue;
                }
                $peakWeeklySalesValue = (float) max($peakWeeklySalesValue, 0);
                $peakReference = $peakWeeklySalesValue > 0 ? $peakWeeklySalesValue : 1;
                $currentPctValue = $peakReference > 0 ? ($currentStockValue / $peakReference) * 100 : 0;
                $afterPctValue = $peakReference > 0 ? ($afterStockValue / $peakReference) * 100 : 0;
            @endphp
            (function() {
                const chartEl = document.getElementById('chart_{{ $item->id }}');
                if (!chartEl) {
                    return;
                }

                const labels = @json($weekLabels);
                const dataPoints = @json($weekUnits);
                const averageUnits = {{ $avgWeeklySalesValue }};
                const currentStock = {{ $currentStockValue }};
                const afterStock = {{ $afterStockValue }};
                const peakWeeklySales = {{ $peakWeeklySalesValue }};
                const currentPct = {{ $currentPctValue }};
                const afterPct = {{ $afterPctValue }};
                const isCaseProduct = {{ $isCaseProductValue ? 'true' : 'false' }};
                const caseUnits = {{ $caseUnitsValue }};

                const extendedLabels = labels.concat(['Current stock', 'After order']);
                const salesData = dataPoints.concat([null, null]);
                const averageLineData = labels.map(() => averageUnits).concat([null, null]);

                // Set point colors - grey for 0 sales, regular color otherwise
                const primaryColor = '{{ $item->review_priority === "review" ? "rgb(239, 68, 68)" : ($item->review_priority === "standard" ? "rgb(234, 179, 8)" : "rgb(34, 197, 94)") }}';
                const greyColor = 'rgb(203, 213, 225)'; // Tailwind slate-300 (lighter grey)
                const pointColors = dataPoints.map(value => value === 0 ? greyColor : primaryColor);
                const pointBorderColors = dataPoints.map(value => value === 0 ? greyColor : primaryColor);
                const pointRadius = dataPoints.map(value => value === 0 ? 2 : 3); // Smaller grey points
                pointColors.push('transparent', 'transparent');
                pointBorderColors.push('transparent', 'transparent');
                pointRadius.push(0, 0);

                const currentStockData = Array(extendedLabels.length).fill(null);
                currentStockData[extendedLabels.length - 2] = currentStock;

                const afterStockData = Array(extendedLabels.length).fill(null);
                afterStockData[extendedLabels.length - 1] = afterStock;

                const salesMax = dataPoints.length ? Math.max(...dataPoints) : 0;
                const chartMax = Math.max(salesMax, currentStock, afterStock, peakWeeklySales, averageUnits, 1);
                const chartMin = Math.min(0, currentStock, afterStock);

                const chart = new Chart(chartEl, {
                    type: 'line',
                    data: {
                        labels: extendedLabels,
                        datasets: [{
                            label: 'Average weekly',
                            data: averageLineData,
                            borderColor: 'rgba(100, 116, 139, 0.7)',
                            borderWidth: 1.5,
                            borderDash: [6, 4],
                            pointRadius: 0,
                            pointHoverRadius: 0,
                            fill: false,
                            tension: 0,
                            spanGaps: true,
                            order: 0
                        },{
                            label: 'Sales',
                            data: salesData,
                            borderColor: primaryColor,
                            backgroundColor: '{{ $item->review_priority === "review" ? "rgba(239, 68, 68, 0.2)" : ($item->review_priority === "standard" ? "rgba(234, 179, 8, 0.2)" : "rgba(34, 197, 94, 0.2)") }}',
                            pointBackgroundColor: pointColors,
                            pointBorderColor: pointBorderColors,
                            pointRadius: pointRadius,
                            pointHoverRadius: pointRadius.map(value => value ? value + 2 : 0),
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
                            fill: {
                                target: 'origin',
                                above: '{{ $item->review_priority === "review" ? "rgba(239, 68, 68, 0.12)" : ($item->review_priority === "standard" ? "rgba(234, 179, 8, 0.12)" : "rgba(34, 197, 94, 0.12)") }}',
                                below: 'transparent'
                            },
                            borderWidth: 2,
                            spanGaps: false,
                            order: 1
                        },{
                            label: 'Current stock',
                            data: currentStockData,
                            showLine: false,
                            pointRadius: 5,
                            pointHoverRadius: 7,
                            pointBackgroundColor: 'rgb(100, 116, 139)',
                            pointBorderColor: 'white',
                            borderColor: 'transparent',
                            order: 2
                        },{
                            label: 'After order',
                            data: afterStockData,
                            showLine: false,
                            pointRadius: 6,
                            pointHoverRadius: 8,
                            pointBackgroundColor: 'rgb(34, 197, 94)',
                            pointBorderColor: 'white',
                            borderColor: 'transparent',
                            order: 3
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
                                        if (context.dataset.label === 'Average weekly') {
                                            if (Number.isNaN(context.parsed.y)) {
                                                return null;
                                            }
                                            return `Avg weekly · ${averageUnits.toFixed(1)} units`;
                                        }
                                        if (context.dataset.label === 'Sales') {
                                            const value = Math.round(context.parsed.y);
                                             if (Number.isNaN(value)) {
                                                return null;
                                             }
                                            if (value === 0) {
                                                return '⚠️ 0 units sold (no sales)';
                                            }
                                            return value + ' units sold';
                                        }

                                        if (context.dataset.label === 'Current stock') {
                                            const units = Math.round(context.parsed.y);
                                            const chartInstance = context.chart;
                                            const caseSize = chartInstance?.$caseUnits ?? caseUnits;
                                            const isCase = chartInstance?.$isCaseProduct ?? isCaseProduct;
                                            const currentPercentage = chartInstance?.$currentPct ?? currentPct;
                                            const displayValue = isCase
                                                ? `${(units / Math.max(caseSize, 1)).toFixed(1)} cases`
                                                : `${units} units`;

                                            return `Current stock · ${displayValue} (${currentPercentage.toFixed(0)}% of peak)`;
                                        }

                                        if (context.dataset.label === 'After order') {
                                            const units = Math.round(context.parsed.y);
                                            const chartInstance = context.chart;
                                            const caseSize = chartInstance?.$caseUnits ?? caseUnits;
                                            const isCase = chartInstance?.$isCaseProduct ?? isCaseProduct;
                                            const updatedPct = chartInstance?.$afterPct ?? afterPct;
                                            const displayValue = isCase
                                                ? `${(units / Math.max(caseSize, 1)).toFixed(1)} cases`
                                                : `${units} units`;

                                            return `After order · ${displayValue} (${updatedPct.toFixed(0)}% of peak)`;
                                        }

                                        return null;
                                    }
                                }
                            }
                        },
                        scales: {
                            x: { display: false },
                            y: {
                                display: false,
                                beginAtZero: chartMin >= 0,
                                min: chartMin < 0 ? chartMin * 1.1 : 0,
                                max: chartMax > 0 ? chartMax * 1.15 : 10,
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

                chart.$currentStock = currentStock;
                chart.$peakValue = peakWeeklySales;
                chart.$caseUnits = caseUnits;
                chart.$isCaseProduct = isCaseProduct;
                chart.$currentPct = currentPct;
                chart.$afterPct = afterPct;
                chart.$currentUnits = currentStock;
                chart.$averageUnits = averageUnits;
                window.productCharts['{{ $item->id }}'] = chart;
            })();
        @endforeach
    });

    // Real-time quantity update and bar chart recalculation
    document.addEventListener('DOMContentLoaded', function() {
        const debounceTimers = {};
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
        const baseUrl = window.location.origin;

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

        function updateStockVisuals(itemId) {
            const input = document.getElementById(`qty-input-${itemId}`);
            if (!input) return;

            const currentStock = parseFloat(input.dataset.currentStock) || 0;
            const caseUnits = parseFloat(input.dataset.caseUnits) || 1;
            const isCaseProduct = input.dataset.isCaseProduct === '1';
            const productPeak = parseFloat(input.dataset.productPeak) || 1;
            const quantityPrecision = parseInt(input.dataset.quantityPrecision || (isCaseProduct ? 3 : 0), 10);
            const orderQuantity = parseFloat(input.value) || 0;

            const currentPct = productPeak > 0 ? (currentStock / productPeak) * 100 : 0;
            const tone = stockToneForPercent(currentPct);
            const statusLabel = stockStatusLabelForPercent(currentPct);

            // Calculate new units based on whether it's a case product
            const newUnits = isCaseProduct ? (orderQuantity * caseUnits) : orderQuantity;
            const afterStock = currentStock + newUnits;

            // Calculate percentages for display (relative to product peak)
            const afterPct = productPeak > 0 ? (afterStock / productPeak) * 100 : 100;

            // Update Chart.js dataset points
            const chart = window.productCharts ? window.productCharts[itemId] : null;
            if (chart) {
                const labelsLength = chart.data.labels.length;

                const afterDataset = chart.data.datasets.find(dataset => dataset.label === 'After order');
                if (afterDataset) {
                    afterDataset.data[labelsLength - 1] = afterStock;
                }

                const currentDataset = chart.data.datasets.find(dataset => dataset.label === 'Current stock');
                if (currentDataset) {
                    currentDataset.data[labelsLength - 2] = currentStock;
                }

                chart.$currentStock = currentStock;
                chart.$currentPct = currentPct;
                chart.$afterPct = afterPct;
                chart.$currentUnits = currentStock;

                const salesDataset = chart.data.datasets.find(dataset => dataset.label === 'Sales');
                const salesData = salesDataset ? salesDataset.data : [];
                const salesMax = salesData.reduce((max, value) => {
                    if (value === null || Number.isNaN(value)) {
                        return max;
                    }
                    return Math.max(max, value);
                }, 0);

                const averageBaseline = chart.$averageUnits || 0;
                const axisMax = Math.max(salesMax, currentStock, afterStock, productPeak, averageBaseline, 1);
                const axisMin = Math.min(0, currentStock, afterStock);

                chart.options.scales.y.min = axisMin < 0 ? axisMin * 1.1 : 0;
                chart.options.scales.y.max = axisMax > 0 ? axisMax * 1.15 : 10;

                chart.update('none');
            }

            // Update current stock displays
            const currentValueEl = document.getElementById(`current-stock-value-${itemId}`);
            if (currentValueEl) {
                currentValueEl.textContent = Math.round(currentStock).toLocaleString();
                applyStockToneClass(currentValueEl, tone, 'text');
            }

            const currentSubtextEl = document.getElementById(`current-stock-subtext-${itemId}`);
            if (currentSubtextEl) {
                const displayValue = isCaseProduct
                    ? `${formatNumberForDisplay(currentStock / Math.max(caseUnits, 1), quantityPrecision || 3)} cases`
                    : `${Math.round(currentStock).toLocaleString()} units`;
                currentSubtextEl.textContent = displayValue;
            }

            const barEl = document.getElementById(`stock-level-bar-${itemId}`);
            if (barEl) {
                barEl.style.width = `${Math.max(0, Math.min(currentPct, 100))}%`;
                applyStockToneClass(barEl, tone, 'bar');
            }

            const statusEl = document.getElementById(`stock-level-label-${itemId}`);
            if (statusEl) {
                statusEl.textContent = statusLabel;
                applyStockToneClass(statusEl, tone, 'text');
            }

            // Update after-order text labels
            const afterStockValue = document.getElementById(`after-stock-value-${itemId}`);
            const afterStockSubtext = document.getElementById(`after-stock-subtext-${itemId}`);
            const unitsLabel = document.getElementById(`units-label-${itemId}`);
            const orderDisplay = document.getElementById(`order-quantity-display-${itemId}`);

            if (afterStockValue) {
                afterStockValue.textContent = Math.round(afterStock).toLocaleString();
            }
            if (afterStockSubtext) {
                const displayValue = isCaseProduct
                    ? `${formatNumberForDisplay(afterStock / Math.max(caseUnits, 1), quantityPrecision || 3)} cases`
                    : `${Math.round(afterStock).toLocaleString()} units`;
                afterStockSubtext.textContent = displayValue;
            }
            if (unitsLabel) {
                unitsLabel.textContent = Math.round(newUnits).toLocaleString();
            }
            if (orderDisplay) {
                const orderValue = Number(orderQuantity);
                orderDisplay.textContent = formatNumberForDisplay(orderValue, quantityPrecision);
            }
        }

        // Handle input changes (debounced save)
        document.querySelectorAll('.qty-input').forEach(input => {
            input.addEventListener('input', function() {
                const itemId = this.dataset.itemId;
                const quantity = parseFloat(this.value) || 0;

                // Update visual chart immediately
                updateStockVisuals(itemId);

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

                    // Update visual chart immediately
                    updateStockVisuals(itemId);

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

                    // Update visual chart immediately
                    updateStockVisuals(itemId);

                    // Save immediately (no debounce for buttons)
                    saveQuantityToServer(itemId, newValue);
                }
            });
        });

        document.querySelectorAll('.reset-stock-button').forEach(button => {
            button.addEventListener('click', function() {
                if (this.dataset.loading === '1') {
                    return;
                }

                const productId = this.dataset.productId;
                const itemId = this.dataset.itemId;
                if (!productId || !itemId) {
                    return;
                }

                const productName = this.dataset.productName || 'this product';
                const confirmationMessage = `Set stock for ${productName} to 0?`;
                if (!window.confirm(confirmationMessage)) {
                    return;
                }

                this.dataset.loading = '1';
                this.disabled = true;
                this.classList.add('opacity-60', 'cursor-not-allowed');

                fetch(`${baseUrl}/products/${encodeURIComponent(productId)}/update-stock`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({ stock_units: 0 }),
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
                    if (!data.success) {
                        throw new Error(data.error || 'Failed to reset stock');
                    }

                    const input = document.getElementById(`qty-input-${itemId}`);
                    if (input) {
                        input.dataset.currentStock = '0';
                    }

                    updateStockVisuals(itemId);

                    this.classList.add('bg-green-100');
                    this.title = 'Stock reset to zero';
                    this.setAttribute('aria-disabled', 'true');

                    const span = this.querySelector('span');
                    if (span) {
                        span.textContent = '0';
                    }

                    setTimeout(() => {
                        this.style.display = 'none';
                    }, 800);
                })
                .catch(error => {
                    console.error('Failed to reset stock', error);
                    alert('Failed to reset stock:\n' + error.message);
                    this.disabled = false;
                    this.classList.remove('opacity-60', 'cursor-not-allowed');
                    this.dataset.loading = '0';
                })
                .finally(() => {
                    this.blur();
                });
            });
        });

        const copyButtons = document.querySelectorAll('.copy-supplier-code');
        const fallbackCopy = (code, button, onSuccess, originalLabel) => {
            const showFailure = () => {
                button.textContent = 'Copy failed';
                setTimeout(() => {
                    button.textContent = originalLabel;
                }, 1600);
            };

            try {
                const temp = document.createElement('textarea');
                temp.value = code;
                temp.setAttribute('readonly', '');
                temp.style.position = 'absolute';
                temp.style.left = '-9999px';
                document.body.appendChild(temp);
                temp.select();
                const succeeded = document.execCommand('copy');
                document.body.removeChild(temp);
                if (succeeded) {
                    onSuccess();
                } else {
                    showFailure();
                }
            } catch (error) {
                console.error('Failed to copy supplier code', error);
                showFailure();
            }
        };

        copyButtons.forEach(button => {
            button.addEventListener('click', function() {
                const code = this.dataset.supplierCode || '';
                if (!code) {
                    return;
                }

                const originalLabel = this.dataset.originalLabel || this.textContent.trim() || 'Copy';
                this.dataset.originalLabel = originalLabel;

                const showCopied = () => {
                    this.textContent = 'Copied';
                    setTimeout(() => {
                        this.textContent = originalLabel;
                    }, 1600);
                };

                if (navigator.clipboard && navigator.clipboard.writeText) {
                    navigator.clipboard.writeText(code)
                        .then(showCopied)
                        .catch(() => fallbackCopy(code, this, showCopied, originalLabel));
                } else {
                    fallbackCopy(code, this, showCopied, originalLabel);
                }
            });
        });
    });
</script>
