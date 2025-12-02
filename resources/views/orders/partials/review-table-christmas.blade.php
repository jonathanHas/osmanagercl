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

    // Load all min_stock_override values at once to avoid N+1 queries
    $productIds = $displayItems->pluck('product_id')->unique()->filter()->toArray();
    $minStockOverrides = \App\Models\ProductOrderSetting::whereIn('product_id', $productIds)
        ->whereNotNull('min_stock_override')
        ->pluck('min_stock_override', 'product_id')
        ->toArray();
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
        <button
            type="button"
            class="priority-filter-button px-4 py-2 rounded-lg font-medium text-sm bg-gray-100 text-gray-700 hover:bg-gray-200 transition"
            data-priority-filter="review"
            data-active-classes="bg-red-600 text-white shadow-sm hover:bg-red-700"
            data-inactive-classes="bg-gray-100 text-gray-700 hover:bg-gray-200"
            aria-pressed="false"
        >
            🔴 Review (<span data-priority-count="review">{{ $reviewCount }}</span>)
        </button>
        <button
            type="button"
            class="priority-filter-button px-4 py-2 rounded-lg font-medium text-sm bg-gray-100 text-gray-700 hover:bg-gray-200 transition"
            data-priority-filter="standard"
            data-active-classes="bg-amber-500 text-white shadow-sm hover:bg-amber-600"
            data-inactive-classes="bg-gray-100 text-gray-700 hover:bg-gray-200"
            aria-pressed="false"
        >
            🟡 Standard (<span data-priority-count="standard">{{ $standardCount }}</span>)
        </button>
        <button
            type="button"
            class="priority-filter-button px-4 py-2 rounded-lg font-medium text-sm bg-gray-100 text-gray-700 hover:bg-gray-200 transition"
            data-priority-filter="safe"
            data-active-classes="bg-green-500 text-white shadow-sm hover:bg-green-600"
            data-inactive-classes="bg-gray-100 text-gray-700 hover:bg-gray-200"
            aria-pressed="false"
        >
            🟢 Safe (<span data-priority-count="safe">{{ $safeCount }}</span>)
        </button>
        <button
            type="button"
            class="priority-filter-button px-4 py-2 rounded-lg font-medium text-sm bg-indigo-600 text-white shadow-sm hover:bg-indigo-700 transition"
            data-priority-filter="all"
            data-active-classes="bg-indigo-600 text-white shadow-sm hover:bg-indigo-700"
            data-inactive-classes="bg-gray-100 text-gray-700 hover:bg-gray-200"
            aria-pressed="true"
        >
            All (<span data-priority-count="all">{{ $totalItems }}</span>)
        </button>
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
<div class="mb-6" data-priority-section="cheese">
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
                    <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase" style="width: 640px;">Stock Levels & Sales Trend</th>
                    <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase w-48">Suggested & Order</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase w-32">Cost</th>
                    <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase w-32">Action</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                @foreach($cheeseProducts as $item)
                @php
                    $product = $item->product;
                    // Decode context_data if it's a string (should be auto-cast to array, but be defensive)
                    $contextData = $item->context_data ?? [];
                    if (is_string($contextData)) {
                        $contextData = json_decode($contextData, true) ?? [];
                    }

                    // Merge current min_stock_override from pre-loaded array (may have been updated after order session created)
                    if (isset($minStockOverrides[$product->ID])) {
                        $contextData['min_stock_override'] = $minStockOverrides[$product->ID];
                    }

                    $safeProductName = strip_tags(html_entity_decode($product->NAME ?? 'Unknown Product'));
                    $currentStock = $contextData['current_stock'] ?? 0;
                    $avgWeeklySales = (float) ($contextData['avg_weekly_sales'] ?? 0);
                    $safetyFactorWeeks = isset($contextData['safety_factor'])
                        ? (float) $contextData['safety_factor']
                        : 1.5;
                    $safetyStockUnits = $avgWeeklySales * $safetyFactorWeeks;
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

                <tr data-order-item-id="{{ $item->id }}" data-priority="{{ $item->review_priority }}" class="hover:bg-{{ $stockColor }}-50 border-l-4 {{ $borderColor }}" style="height: 360px;">
                    <td class="px-4 py-4">
                        <div data-priority-indicator="{{ $item->id }}" class="w-8 h-8 {{ $iconBg }} rounded-full flex items-center justify-center">
                            <span data-priority-symbol="{{ $item->id }}" class="font-bold text-sm">
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
                        <div class="font-medium text-gray-900">
                            @if(($product->ID ?? null) !== null)
                                <a href="{{ route('products.edit', $product->ID) }}"
                                   class="text-indigo-600 hover:text-indigo-800"
                                   target="_blank"
                                   rel="noopener"
                                   title="Edit {{ $safeProductName }}">
                                    {!! $safeProductName !!}
                                </a>
                            @else
                                {!! $safeProductName !!}
                            @endif
                        </div>
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
                        <div class="mt-2 flex items-center gap-2 text-xs">
                            <label for="priority-select-{{ $item->id }}" class="uppercase tracking-wide text-[11px] text-slate-400">
                                Priority
                            </label>
                            <select
                                id="priority-select-{{ $item->id }}"
                                class="priority-selector border-gray-200 rounded-md text-xs focus:ring-indigo-500 focus:border-indigo-500"
                                data-item-id="{{ $item->id }}"
                                data-product-id="{{ $product->ID ?? '' }}"
                                data-current-priority="{{ $item->review_priority }}"
                                aria-label="Adjust priority for {{ $safeProductName }}"
                            >
                                <option value="review" {{ $item->review_priority === 'review' ? 'selected' : '' }}>🔴 Requires review</option>
                                <option value="standard" {{ $item->review_priority === 'standard' ? 'selected' : '' }}>🟡 Standard</option>
                                <option value="safe" {{ $item->review_priority === 'safe' ? 'selected' : '' }}>🟢 Safe to over-order</option>
                            </select>
                            <span class="hidden text-[11px] text-green-600" data-priority-feedback="{{ $item->id }}">
                                Saved
                            </span>
                        </div>
                        <div class="mt-2 text-xs text-slate-500 leading-tight">
                            <span class="uppercase tracking-wide text-[10px] text-slate-400">Safety stock floor</span>
                            <div class="flex flex-wrap gap-2 text-[11px] text-slate-600">
                                <span>{{ number_format($safetyFactorWeeks, 1) }} wk minimum</span>
                                <span>≈ {{ number_format($safetyStockUnits, 0) }} units</span>
                            </div>
                        </div>
                    </td>
                    <td class="px-4 py-4">
                        <div class="text-center">
                            <div class="text-2xl font-bold text-blue-600">{{ number_format($totalPeriodSales, 0) }}</div>
                            <div class="text-xs text-gray-500 mb-1">total sold</div>
                            <div class="mt-2 flex items-center justify-center gap-6 text-xs text-gray-600">
                                <span>avg {{ number_format($avgWeeklySales, 1) }}</span>
                                <span>peak {{ number_format($peakWeeklySales, 1) }}</span>
                            </div>
                            @include('orders.partials.min-stock-editor', [
                                'item' => $item,
                                'product' => $product,
                                'contextData' => $contextData,
                                'orderSession' => $orderSession,
                                'isCaseProduct' => $isCaseProduct,
                                'caseUnits' => $caseUnits,
                            ])
                            <div class="w-full bg-gray-200 rounded-full h-2">
                                <div
                                    id="stock-level-bar-{{ $item->id }}"
                                    class="bg-{{ $stockColor }}-500 h-2 rounded-full"
                                    data-stock-color="{{ $stockColor }}"
                                    style="width: {{ max(min($currentPct, 100), 0) }}%"
                                ></div>
                            </div>
                            <!-- Current Stock moved here from graph column -->
                            <div class="mt-3 pt-3 border-t border-gray-200">
                                <div class="flex items-center justify-between gap-2">
                                    <div>
                                        <div class="text-[10px] uppercase tracking-wide text-slate-400">Current</div>
                                        <div class="flex items-center gap-1">
                                            <div id="current-stock-value-{{ $item->id }}" class="text-lg font-semibold text-{{ $stockColor }}-600" data-stock-color="{{ $stockColor }}">{{ number_format($currentStock, 0) }}</div>
                                            @if($product?->ID && $currentStock < 0)
                                                <button
                                                    type="button"
                                                    class="reset-stock-button inline-flex items-center gap-0.5 rounded border border-green-200 px-1.5 py-0.5 text-[10px] font-semibold text-green-600 hover:bg-green-50 focus:outline-none focus:ring-2 focus:ring-green-200"
                                                    data-product-id="{{ $product->ID }}"
                                                    data-item-id="{{ $item->id }}"
                                                    data-case-units="{{ $caseUnits }}"
                                                    data-is-case-product="{{ $isCaseProduct ? 1 : 0 }}"
                                                    data-product-name="{{ e($product->NAME ?? 'Product') }}"
                                                    title="Set stock to zero"
                                                    aria-label="Set stock to zero for {{ e($product->NAME ?? 'product') }}"
                                                >
                                                    <svg class="h-3 w-3" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 10.5 10 5l5.5 5.5M10 5v10.5" />
                                                    </svg>
                                                    <span>0</span>
                                                </button>
                                            @endif
                                        </div>
                                        <div id="current-stock-subtext-{{ $item->id }}" class="text-[10px] text-gray-500">
                                            @if($isCaseProduct)
                                                {{ $currentStockCaseText }} cases
                                            @else
                                                units
                                            @endif
                                        </div>
                                    </div>
                                    <div class="text-right">
                                        <div class="text-[10px] uppercase tracking-wide text-slate-400">After</div>
                                        <div id="after-stock-value-{{ $item->id }}" class="text-lg font-semibold text-green-600">{{ number_format($afterStock, 0) }}</div>
                                        <div id="after-stock-subtext-{{ $item->id }}" class="text-[10px] text-gray-500">
                                            @if($isCaseProduct)
                                                {{ $afterStockCaseText }} cases
                                            @else
                                                units
                                            @endif
                                        </div>
                                    </div>
                                </div>
                                @if(isset($contextData['coverage_weeks']))
                                    <div class="text-[10px] text-gray-400 mt-1">
                                        Cover {{ number_format($contextData['coverage_weeks'], 1) }}→{{ number_format($contextData['target_weeks'] ?? $contextData['coverage_weeks'], 1) }} wk
                                    </div>
                                @endif
                            </div>
                        </div>
                    </td>
                    <td class="px-4 py-4 align-bottom">
                        <div class="flex flex-col justify-between gap-2" style="min-height: 280px;">
                            <div class="relative overflow-hidden" style="height: 220px;">
                                <canvas id="chart_{{ $item->id }}" class="w-full h-full"></canvas>
                            </div>
                            <!-- Period totals under the graph -->
                            @php
                                $christmasWindows = $contextData['christmas_comparison']['christmas_windows'] ?? [];
                                // Sort by year ascending for display
                                usort($christmasWindows, fn($a, $b) => ($a['year'] ?? 0) <=> ($b['year'] ?? 0));
                            @endphp
                            <div class="flex items-center justify-between gap-2 text-xs border-t border-gray-100 pt-2">
                                @foreach($christmasWindows as $window)
                                    <div class="text-center flex-1">
                                        <div class="text-[10px] uppercase tracking-wide" style="color: {{ $loop->index === 0 ? 'rgb(168, 85, 247)' : ($loop->index === 1 ? 'rgb(236, 72, 153)' : 'rgb(59, 130, 246)') }};">
                                            Xmas {{ $window['year'] ?? '' }}
                                        </div>
                                        <div class="text-sm font-bold" style="color: {{ $loop->index === 0 ? 'rgb(168, 85, 247)' : ($loop->index === 1 ? 'rgb(236, 72, 153)' : 'rgb(59, 130, 246)') }};">
                                            {{ number_format($window['total_units'] ?? 0, 0) }}
                                        </div>
                                    </div>
                                @endforeach
                                <div class="text-center flex-1">
                                    <div class="text-[10px] uppercase tracking-wide text-slate-500">Recent</div>
                                    <div class="text-sm font-bold text-blue-600">{{ number_format($totalPeriodSales, 0) }}</div>
                                </div>
                                <div class="text-center flex-1">
                                    <div class="text-[10px] uppercase tracking-wide text-slate-400">Current</div>
                                    <div class="text-sm font-semibold text-slate-500">{{ number_format($currentStock, 0) }}</div>
                                </div>
                                <div class="text-center flex-1">
                                    <div class="text-[10px] uppercase tracking-wide text-green-600">After</div>
                                    <div class="text-sm font-semibold text-green-600">{{ number_format($afterStock, 0) }}</div>
                                </div>
                            </div>
                        </div>
                    </td>
                    <td class="px-4 py-4">
                        <div class="flex flex-col items-center gap-3">
                            @php
                                $christmasData = $contextData['christmas_comparison'] ?? null;
                                $stats = $christmasData['stats'] ?? null;
                                $isChristmasHigher = ($stats['selected_mode'] ?? '') === 'christmas';
                            @endphp

                            @if($christmasData && $stats)
                                <!-- Christmas Comparison View -->
                                <div class="w-full grid grid-cols-2 gap-2 p-2 bg-slate-50 rounded-lg border text-xs">
                                    <!-- Regular Column -->
                                    <div class="border-r pr-2">
                                        <div class="text-[10px] font-semibold uppercase text-slate-500 mb-1">Recent ({{ $orderSession->sales_history_weeks }}w)</div>
                                        <div class="mb-1">
                                            <div class="text-[9px] text-slate-600">Weekly avg</div>
                                            <div class="text-sm font-bold text-blue-600">{{ number_format($stats['regular_weekly_avg'], 1) }}</div>
                                        </div>
                                        <div>
                                            <div class="text-[9px] text-slate-600">Suggested</div>
                                            <div class="text-base font-semibold {{ !$isChristmasHigher ? 'text-green-600' : 'text-slate-600' }}">
                                                {{ number_format($stats['regular_suggested'], 0) }}
                                                @if(!$isChristmasHigher) <span class="text-xs">✓</span> @endif
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Christmas Column -->
                                    <div class="pl-2">
                                        <div class="text-[10px] font-semibold uppercase text-slate-500 mb-1">Christmas ({{ implode(', ', $christmasData['years']) }})</div>
                                        <div class="mb-1">
                                            <div class="text-[9px] text-slate-600">Weekly avg</div>
                                            <div class="text-sm font-bold text-purple-600">{{ number_format($stats['christmas_weekly_avg'], 1) }}</div>
                                        </div>
                                        <div>
                                            <div class="text-[9px] text-slate-600">Suggested</div>
                                            <div class="text-base font-semibold {{ $isChristmasHigher ? 'text-green-600' : 'text-slate-600' }}">
                                                {{ number_format($stats['christmas_suggested'], 0) }}
                                                @if($isChristmasHigher) <span class="text-xs">✓</span> @endif
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                @if(abs($stats['delta']) > 5)
                                    <div class="w-full p-2 rounded-lg text-[10px] {{ $stats['delta'] > 0 ? 'bg-orange-50 text-orange-800 border border-orange-200' : 'bg-green-50 text-green-800 border border-green-200' }}">
                                        <span class="text-sm">{{ $stats['delta'] > 0 ? '⬆️' : '⬇️' }}</span>
                                        <strong>{{ abs($stats['delta']) }} units difference</strong>
                                        <br><span class="text-[9px]">Using {{ $isChristmasHigher ? 'Christmas' : 'regular' }} (higher)</span>
                                    </div>
                                @endif
                            @endif

                            <div class="text-center">
                                <div class="text-[11px] font-semibold text-gray-500 uppercase tracking-wide">Suggested</div>
                                <div id="suggested-display-{{ $item->id }}" class="mt-1 text-xl font-bold text-purple-700">
                                    {{ $suggestedDisplayText }} {{ $quantityLabel }}
                                </div>
                                <div id="suggested-units-{{ $item->id }}" class="text-xs text-gray-500">{{ number_format($suggestedUnits, 0) }} units</div>
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
                            <button
                                type="button"
                                data-approval-button="{{ $item->id }}"
                                class="px-4 py-2 bg-green-600 text-white rounded-lg font-medium text-sm">
                                ✓ Approved
                            </button>
                        @else
                            <button
                                type="button"
                                data-approval-button="{{ $item->id }}"
                                class="px-4 py-2 bg-blue-600 text-white rounded-lg font-medium hover:bg-blue-700 text-sm">
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
<div class="mb-6" data-priority-section="refrigerated">
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
                    <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase" style="width: 640px;">Stock Levels & Sales Trend</th>
                    <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase w-48">Suggested & Order</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase w-32">Cost</th>
                    <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase w-32">Action</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                @foreach($refrigeratedProducts as $item)
                @php
                    $product = $item->product;
                    // Decode context_data if it's a string (should be auto-cast to array, but be defensive)
                    $contextData = $item->context_data ?? [];
                    if (is_string($contextData)) {
                        $contextData = json_decode($contextData, true) ?? [];
                    }

                    // Merge current min_stock_override from pre-loaded array (may have been updated after order session created)
                    if (isset($minStockOverrides[$product->ID])) {
                        $contextData['min_stock_override'] = $minStockOverrides[$product->ID];
                    }

                    $safeProductName = strip_tags(html_entity_decode($product->NAME ?? 'Unknown Product'));
                    $currentStock = $contextData['current_stock'] ?? 0;
                    $avgWeeklySales = (float) ($contextData['avg_weekly_sales'] ?? 0);
                    $safetyFactorWeeks = isset($contextData['safety_factor'])
                        ? (float) $contextData['safety_factor']
                        : 1.5;
                    $safetyStockUnits = $avgWeeklySales * $safetyFactorWeeks;
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

                <tr data-order-item-id="{{ $item->id }}" data-priority="{{ $item->review_priority }}" class="hover:bg-{{ $stockColor }}-50 border-l-4 {{ $borderColor }}" style="height: 360px;">
                    <td class="px-4 py-4">
                        <div data-priority-indicator="{{ $item->id }}" class="w-8 h-8 {{ $iconBg }} rounded-full flex items-center justify-center">
                            <span data-priority-symbol="{{ $item->id }}" class="font-bold text-sm">
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
                        <div class="font-medium text-gray-900">
                            @if(($product->ID ?? null) !== null)
                                <a href="{{ route('products.edit', $product->ID) }}"
                                   class="text-indigo-600 hover:text-indigo-800"
                                   target="_blank"
                                   rel="noopener"
                                   title="Edit {{ $safeProductName }}">
                                    {!! $safeProductName !!}
                                </a>
                            @else
                                {!! $safeProductName !!}
                            @endif
                        </div>
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
                        <div class="mt-2 flex items-center gap-2 text-xs">
                            <label for="priority-select-{{ $item->id }}" class="uppercase tracking-wide text-[11px] text-slate-400">
                                Priority
                            </label>
                            <select
                                id="priority-select-{{ $item->id }}"
                                class="priority-selector border-gray-200 rounded-md text-xs focus:ring-indigo-500 focus:border-indigo-500"
                                data-item-id="{{ $item->id }}"
                                data-product-id="{{ $product->ID ?? '' }}"
                                data-current-priority="{{ $item->review_priority }}"
                                aria-label="Adjust priority for {{ $safeProductName }}"
                            >
                                <option value="review" {{ $item->review_priority === 'review' ? 'selected' : '' }}>🔴 Requires review</option>
                                <option value="standard" {{ $item->review_priority === 'standard' ? 'selected' : '' }}>🟡 Standard</option>
                                <option value="safe" {{ $item->review_priority === 'safe' ? 'selected' : '' }}>🟢 Safe to over-order</option>
                            </select>
                            <span class="hidden text-[11px] text-green-600" data-priority-feedback="{{ $item->id }}">
                                Saved
                            </span>
                        </div>
                        <div class="mt-2 text-xs text-slate-500 leading-tight">
                            <span class="uppercase tracking-wide text-[10px] text-slate-400">Safety stock floor</span>
                            <div class="flex flex-wrap gap-2 text-[11px] text-slate-600">
                                <span>{{ number_format($safetyFactorWeeks, 1) }} wk minimum</span>
                                <span>≈ {{ number_format($safetyStockUnits, 0) }} units</span>
                            </div>
                        </div>
                    </td>
                    <td class="px-4 py-4">
                        <div class="text-center">
                            <div class="text-2xl font-bold text-blue-600">{{ number_format($totalPeriodSales, 0) }}</div>
                            <div class="text-xs text-gray-500 mb-1">total sold</div>
                            <div class="mt-2 flex items-center justify-center gap-6 text-xs text-gray-600">
                                <span>avg {{ number_format($avgWeeklySales, 1) }}</span>
                                <span>peak {{ number_format($peakWeeklySales, 1) }}</span>
                            </div>
                            @include('orders.partials.min-stock-editor', [
                                'item' => $item,
                                'product' => $product,
                                'contextData' => $contextData,
                                'orderSession' => $orderSession,
                                'isCaseProduct' => $isCaseProduct,
                                'caseUnits' => $caseUnits,
                            ])
                            <div class="w-full bg-gray-200 rounded-full h-2">
                                <div
                                    id="stock-level-bar-{{ $item->id }}"
                                    class="bg-{{ $stockColor }}-500 h-2 rounded-full"
                                    data-stock-color="{{ $stockColor }}"
                                    style="width: {{ max(min($currentPct, 100), 0) }}%"
                                ></div>
                            </div>
                            <!-- Current Stock moved here from graph column -->
                            <div class="mt-3 pt-3 border-t border-gray-200">
                                <div class="flex items-center justify-between gap-2">
                                    <div>
                                        <div class="text-[10px] uppercase tracking-wide text-slate-400">Current</div>
                                        <div class="flex items-center gap-1">
                                            <div id="current-stock-value-{{ $item->id }}" class="text-lg font-semibold text-{{ $stockColor }}-600" data-stock-color="{{ $stockColor }}">{{ number_format($currentStock, 0) }}</div>
                                            @if($product?->ID && $currentStock < 0)
                                                <button
                                                    type="button"
                                                    class="reset-stock-button inline-flex items-center gap-0.5 rounded border border-green-200 px-1.5 py-0.5 text-[10px] font-semibold text-green-600 hover:bg-green-50 focus:outline-none focus:ring-2 focus:ring-green-200"
                                                    data-product-id="{{ $product->ID }}"
                                                    data-item-id="{{ $item->id }}"
                                                    data-case-units="{{ $caseUnits }}"
                                                    data-is-case-product="{{ $isCaseProduct ? 1 : 0 }}"
                                                    data-product-name="{{ e($product->NAME ?? 'Product') }}"
                                                    title="Set stock to zero"
                                                    aria-label="Set stock to zero for {{ e($product->NAME ?? 'product') }}"
                                                >
                                                    <svg class="h-3 w-3" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 10.5 10 5l5.5 5.5M10 5v10.5" />
                                                    </svg>
                                                    <span>0</span>
                                                </button>
                                            @endif
                                        </div>
                                        <div id="current-stock-subtext-{{ $item->id }}" class="text-[10px] text-gray-500">
                                            @if($isCaseProduct)
                                                {{ $currentStockCaseText }} cases
                                            @else
                                                units
                                            @endif
                                        </div>
                                    </div>
                                    <div class="text-right">
                                        <div class="text-[10px] uppercase tracking-wide text-slate-400">After</div>
                                        <div id="after-stock-value-{{ $item->id }}" class="text-lg font-semibold text-green-600">{{ number_format($afterStock, 0) }}</div>
                                        <div id="after-stock-subtext-{{ $item->id }}" class="text-[10px] text-gray-500">
                                            @if($isCaseProduct)
                                                {{ $afterStockCaseText }} cases
                                            @else
                                                units
                                            @endif
                                        </div>
                                    </div>
                                </div>
                                @if(isset($contextData['coverage_weeks']))
                                    <div class="text-[10px] text-gray-400 mt-1">
                                        Cover {{ number_format($contextData['coverage_weeks'], 1) }}→{{ number_format($contextData['target_weeks'] ?? $contextData['coverage_weeks'], 1) }} wk
                                    </div>
                                @endif
                            </div>
                        </div>
                    </td>
                    <td class="px-4 py-4 align-bottom">
                        <div class="flex flex-col justify-between gap-2" style="min-height: 280px;">
                            <div class="relative overflow-hidden" style="height: 220px;">
                                <canvas id="chart_{{ $item->id }}" class="w-full h-full"></canvas>
                            </div>
                            <!-- Period totals under the graph -->
                            @php
                                $christmasWindows = $contextData['christmas_comparison']['christmas_windows'] ?? [];
                                // Sort by year ascending for display
                                usort($christmasWindows, fn($a, $b) => ($a['year'] ?? 0) <=> ($b['year'] ?? 0));
                            @endphp
                            <div class="flex items-center justify-between gap-2 text-xs border-t border-gray-100 pt-2">
                                @foreach($christmasWindows as $window)
                                    <div class="text-center flex-1">
                                        <div class="text-[10px] uppercase tracking-wide" style="color: {{ $loop->index === 0 ? 'rgb(168, 85, 247)' : ($loop->index === 1 ? 'rgb(236, 72, 153)' : 'rgb(59, 130, 246)') }};">
                                            Xmas {{ $window['year'] ?? '' }}
                                        </div>
                                        <div class="text-sm font-bold" style="color: {{ $loop->index === 0 ? 'rgb(168, 85, 247)' : ($loop->index === 1 ? 'rgb(236, 72, 153)' : 'rgb(59, 130, 246)') }};">
                                            {{ number_format($window['total_units'] ?? 0, 0) }}
                                        </div>
                                    </div>
                                @endforeach
                                <div class="text-center flex-1">
                                    <div class="text-[10px] uppercase tracking-wide text-slate-500">Recent</div>
                                    <div class="text-sm font-bold text-blue-600">{{ number_format($totalPeriodSales, 0) }}</div>
                                </div>
                                <div class="text-center flex-1">
                                    <div class="text-[10px] uppercase tracking-wide text-slate-400">Current</div>
                                    <div class="text-sm font-semibold text-slate-500">{{ number_format($currentStock, 0) }}</div>
                                </div>
                                <div class="text-center flex-1">
                                    <div class="text-[10px] uppercase tracking-wide text-green-600">After</div>
                                    <div class="text-sm font-semibold text-green-600">{{ number_format($afterStock, 0) }}</div>
                                </div>
                            </div>
                        </div>
                    </td>
                    <td class="px-4 py-4">
                        <div class="flex flex-col items-center gap-3">
                            @php
                                $christmasData = $contextData['christmas_comparison'] ?? null;
                                $stats = $christmasData['stats'] ?? null;
                                $isChristmasHigher = ($stats['selected_mode'] ?? '') === 'christmas';
                            @endphp

                            @if($christmasData && $stats)
                                <!-- Christmas Comparison View -->
                                <div class="w-full grid grid-cols-2 gap-2 p-2 bg-slate-50 rounded-lg border text-xs">
                                    <!-- Regular Column -->
                                    <div class="border-r pr-2">
                                        <div class="text-[10px] font-semibold uppercase text-slate-500 mb-1">Recent ({{ $orderSession->sales_history_weeks }}w)</div>
                                        <div class="mb-1">
                                            <div class="text-[9px] text-slate-600">Weekly avg</div>
                                            <div class="text-sm font-bold text-blue-600">{{ number_format($stats['regular_weekly_avg'], 1) }}</div>
                                        </div>
                                        <div>
                                            <div class="text-[9px] text-slate-600">Suggested</div>
                                            <div class="text-base font-semibold {{ !$isChristmasHigher ? 'text-green-600' : 'text-slate-600' }}">
                                                {{ number_format($stats['regular_suggested'], 0) }}
                                                @if(!$isChristmasHigher) <span class="text-xs">✓</span> @endif
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Christmas Column -->
                                    <div class="pl-2">
                                        <div class="text-[10px] font-semibold uppercase text-slate-500 mb-1">Christmas ({{ implode(', ', $christmasData['years']) }})</div>
                                        <div class="mb-1">
                                            <div class="text-[9px] text-slate-600">Weekly avg</div>
                                            <div class="text-sm font-bold text-purple-600">{{ number_format($stats['christmas_weekly_avg'], 1) }}</div>
                                        </div>
                                        <div>
                                            <div class="text-[9px] text-slate-600">Suggested</div>
                                            <div class="text-base font-semibold {{ $isChristmasHigher ? 'text-green-600' : 'text-slate-600' }}">
                                                {{ number_format($stats['christmas_suggested'], 0) }}
                                                @if($isChristmasHigher) <span class="text-xs">✓</span> @endif
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                @if(abs($stats['delta']) > 5)
                                    <div class="w-full p-2 rounded-lg text-[10px] {{ $stats['delta'] > 0 ? 'bg-orange-50 text-orange-800 border border-orange-200' : 'bg-green-50 text-green-800 border border-green-200' }}">
                                        <span class="text-sm">{{ $stats['delta'] > 0 ? '⬆️' : '⬇️' }}</span>
                                        <strong>{{ abs($stats['delta']) }} units difference</strong>
                                        <br><span class="text-[9px]">Using {{ $isChristmasHigher ? 'Christmas' : 'regular' }} (higher)</span>
                                    </div>
                                @endif
                            @endif

                            <div class="text-center">
                                <div class="text-[11px] font-semibold text-gray-500 uppercase tracking-wide">Suggested</div>
                                <div id="suggested-display-{{ $item->id }}" class="mt-1 text-xl font-bold text-purple-700">
                                    {{ $suggestedDisplayText }} {{ $quantityLabel }}
                                </div>
                                <div id="suggested-units-{{ $item->id }}" class="text-xs text-gray-500">{{ number_format($suggestedUnits, 0) }} units</div>
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
                            <button
                                type="button"
                                data-approval-button="{{ $item->id }}"
                                class="px-4 py-2 bg-green-600 text-white rounded-lg font-medium text-sm">
                                ✓ Approved
                            </button>
                        @else
                            <button
                                type="button"
                                data-approval-button="{{ $item->id }}"
                                class="px-4 py-2 bg-blue-600 text-white rounded-lg font-medium hover:bg-blue-700 text-sm">
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
<div class="mb-6" data-priority-section="case">
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
                    <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase" style="width: 640px;">Stock Levels & Sales Trend</th>
                    <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase w-48">Suggested & Order</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase w-32">Cost</th>
                    <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase w-32">Action</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                @foreach($caseProducts as $item)
                @php
                    $product = $item->product;
                    // Decode context_data if it's a string (should be auto-cast to array, but be defensive)
                    $contextData = $item->context_data ?? [];
                    if (is_string($contextData)) {
                        $contextData = json_decode($contextData, true) ?? [];
                    }

                    // Merge current min_stock_override from pre-loaded array (may have been updated after order session created)
                    if (isset($minStockOverrides[$product->ID])) {
                        $contextData['min_stock_override'] = $minStockOverrides[$product->ID];
                    }

                    $safeProductName = strip_tags(html_entity_decode($product->NAME ?? 'Unknown Product'));
                    $currentStock = $contextData['current_stock'] ?? 0;
                    $avgWeeklySales = (float) ($contextData['avg_weekly_sales'] ?? 0);
                    $safetyFactorWeeks = isset($contextData['safety_factor'])
                        ? (float) $contextData['safety_factor']
                        : 1.5;
                    $safetyStockUnits = $avgWeeklySales * $safetyFactorWeeks;
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

                <tr data-order-item-id="{{ $item->id }}" data-priority="{{ $item->review_priority }}" class="hover:bg-{{ $stockColor }}-50 border-l-4 {{ $borderColor }}" style="height: 360px;">
                    <td class="px-4 py-4">
                        <div data-priority-indicator="{{ $item->id }}" class="w-8 h-8 {{ $iconBg }} rounded-full flex items-center justify-center">
                            <span data-priority-symbol="{{ $item->id }}" class="font-bold text-sm">
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
                        <div class="font-medium text-gray-900">
                            @if(($product->ID ?? null) !== null)
                                <a href="{{ route('products.edit', $product->ID) }}"
                                   class="text-indigo-600 hover:text-indigo-800"
                                   target="_blank"
                                   rel="noopener"
                                   title="Edit {{ $safeProductName }}">
                                    {!! $safeProductName !!}
                                </a>
                            @else
                                {!! $safeProductName !!}
                            @endif
                        </div>
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
                        <div class="mt-2 flex items-center gap-2 text-xs">
                            <label for="priority-select-{{ $item->id }}" class="uppercase tracking-wide text-[11px] text-slate-400">
                                Priority
                            </label>
                            <select
                                id="priority-select-{{ $item->id }}"
                                class="priority-selector border-gray-200 rounded-md text-xs focus:ring-indigo-500 focus:border-indigo-500"
                                data-item-id="{{ $item->id }}"
                                data-product-id="{{ $product->ID ?? '' }}"
                                data-current-priority="{{ $item->review_priority }}"
                                aria-label="Adjust priority for {{ $safeProductName }}"
                            >
                                <option value="review" {{ $item->review_priority === 'review' ? 'selected' : '' }}>🔴 Requires review</option>
                                <option value="standard" {{ $item->review_priority === 'standard' ? 'selected' : '' }}>🟡 Standard</option>
                                <option value="safe" {{ $item->review_priority === 'safe' ? 'selected' : '' }}>🟢 Safe to over-order</option>
                            </select>
                            <span class="hidden text-[11px] text-green-600" data-priority-feedback="{{ $item->id }}">
                                Saved
                            </span>
                        </div>
                        <div class="mt-2 text-xs text-slate-500 leading-tight">
                            <span class="uppercase tracking-wide text-[10px] text-slate-400">Safety stock floor</span>
                            <div class="flex flex-wrap gap-2 text-[11px] text-slate-600">
                                <span>{{ number_format($safetyFactorWeeks, 1) }} wk minimum</span>
                                <span>≈ {{ number_format($safetyStockUnits, 0) }} units</span>
                            </div>
                        </div>
                    </td>
                    <td class="px-4 py-4">
                        <div class="text-center">
                            <div class="text-2xl font-bold text-blue-600">{{ number_format($totalPeriodSales, 0) }}</div>
                            <div class="text-xs text-gray-500 mb-1">total sold</div>
                            <div class="mt-2 flex items-center justify-center gap-6 text-xs text-gray-600">
                                <span>avg {{ number_format($avgWeeklySales, 1) }}</span>
                                <span>peak {{ number_format($peakWeeklySales, 1) }}</span>
                            </div>
                            @include('orders.partials.min-stock-editor', [
                                'item' => $item,
                                'product' => $product,
                                'contextData' => $contextData,
                                'orderSession' => $orderSession,
                                'isCaseProduct' => $isCaseProduct,
                                'caseUnits' => $caseUnits,
                            ])
                            <div class="w-full bg-gray-200 rounded-full h-2">
                                <div
                                    id="stock-level-bar-{{ $item->id }}"
                                    class="bg-{{ $stockColor }}-500 h-2 rounded-full"
                                    data-stock-color="{{ $stockColor }}"
                                    style="width: {{ max(min($currentPct, 100), 0) }}%"
                                ></div>
                            </div>
                            <!-- Current Stock moved here from graph column -->
                            <div class="mt-3 pt-3 border-t border-gray-200">
                                <div class="flex items-center justify-between gap-2">
                                    <div>
                                        <div class="text-[10px] uppercase tracking-wide text-slate-400">Current</div>
                                        <div class="flex items-center gap-1">
                                            <div id="current-stock-value-{{ $item->id }}" class="text-lg font-semibold text-{{ $stockColor }}-600" data-stock-color="{{ $stockColor }}">{{ number_format($currentStock, 0) }}</div>
                                            @if($product?->ID && $currentStock < 0)
                                                <button
                                                    type="button"
                                                    class="reset-stock-button inline-flex items-center gap-0.5 rounded border border-green-200 px-1.5 py-0.5 text-[10px] font-semibold text-green-600 hover:bg-green-50 focus:outline-none focus:ring-2 focus:ring-green-200"
                                                    data-product-id="{{ $product->ID }}"
                                                    data-item-id="{{ $item->id }}"
                                                    data-case-units="{{ $caseUnits }}"
                                                    data-is-case-product="{{ $isCaseProduct ? 1 : 0 }}"
                                                    data-product-name="{{ e($product->NAME ?? 'Product') }}"
                                                    title="Set stock to zero"
                                                    aria-label="Set stock to zero for {{ e($product->NAME ?? 'product') }}"
                                                >
                                                    <svg class="h-3 w-3" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 10.5 10 5l5.5 5.5M10 5v10.5" />
                                                    </svg>
                                                    <span>0</span>
                                                </button>
                                            @endif
                                        </div>
                                        <div id="current-stock-subtext-{{ $item->id }}" class="text-[10px] text-gray-500">
                                            @if($isCaseProduct)
                                                {{ $currentStockCaseText }} cases
                                            @else
                                                units
                                            @endif
                                        </div>
                                    </div>
                                    <div class="text-right">
                                        <div class="text-[10px] uppercase tracking-wide text-slate-400">After</div>
                                        <div id="after-stock-value-{{ $item->id }}" class="text-lg font-semibold text-green-600">{{ number_format($afterStock, 0) }}</div>
                                        <div id="after-stock-subtext-{{ $item->id }}" class="text-[10px] text-gray-500">
                                            @if($isCaseProduct)
                                                {{ $afterStockCaseText }} cases
                                            @else
                                                units
                                            @endif
                                        </div>
                                    </div>
                                </div>
                                @if(isset($contextData['coverage_weeks']))
                                    <div class="text-[10px] text-gray-400 mt-1">
                                        Cover {{ number_format($contextData['coverage_weeks'], 1) }}→{{ number_format($contextData['target_weeks'] ?? $contextData['coverage_weeks'], 1) }} wk
                                    </div>
                                @endif
                            </div>
                        </div>
                    </td>
                    <td class="px-4 py-4 align-bottom">
                        <div class="flex flex-col justify-between gap-2" style="min-height: 280px;">
                            <div class="relative overflow-hidden" style="height: 220px;">
                                <canvas id="chart_{{ $item->id }}" class="w-full h-full"></canvas>
                            </div>
                            <!-- Period totals under the graph -->
                            @php
                                $christmasWindows = $contextData['christmas_comparison']['christmas_windows'] ?? [];
                                // Sort by year ascending for display
                                usort($christmasWindows, fn($a, $b) => ($a['year'] ?? 0) <=> ($b['year'] ?? 0));
                            @endphp
                            <div class="flex items-center justify-between gap-2 text-xs border-t border-gray-100 pt-2">
                                @foreach($christmasWindows as $window)
                                    <div class="text-center flex-1">
                                        <div class="text-[10px] uppercase tracking-wide" style="color: {{ $loop->index === 0 ? 'rgb(168, 85, 247)' : ($loop->index === 1 ? 'rgb(236, 72, 153)' : 'rgb(59, 130, 246)') }};">
                                            Xmas {{ $window['year'] ?? '' }}
                                        </div>
                                        <div class="text-sm font-bold" style="color: {{ $loop->index === 0 ? 'rgb(168, 85, 247)' : ($loop->index === 1 ? 'rgb(236, 72, 153)' : 'rgb(59, 130, 246)') }};">
                                            {{ number_format($window['total_units'] ?? 0, 0) }}
                                        </div>
                                    </div>
                                @endforeach
                                <div class="text-center flex-1">
                                    <div class="text-[10px] uppercase tracking-wide text-slate-500">Recent</div>
                                    <div class="text-sm font-bold text-blue-600">{{ number_format($totalPeriodSales, 0) }}</div>
                                </div>
                                <div class="text-center flex-1">
                                    <div class="text-[10px] uppercase tracking-wide text-slate-400">Current</div>
                                    <div class="text-sm font-semibold text-slate-500">{{ number_format($currentStock, 0) }}</div>
                                </div>
                                <div class="text-center flex-1">
                                    <div class="text-[10px] uppercase tracking-wide text-green-600">After</div>
                                    <div class="text-sm font-semibold text-green-600">{{ number_format($afterStock, 0) }}</div>
                                </div>
                            </div>
                        </div>
                    </td>
                    <td class="px-4 py-4">
                        <div class="flex flex-col items-center gap-3">
                            @php
                                $christmasData = $contextData['christmas_comparison'] ?? null;
                                $stats = $christmasData['stats'] ?? null;
                                $isChristmasHigher = ($stats['selected_mode'] ?? '') === 'christmas';
                            @endphp

                            @if($christmasData && $stats)
                                <!-- Christmas Comparison View -->
                                <div class="w-full grid grid-cols-2 gap-2 p-2 bg-slate-50 rounded-lg border text-xs">
                                    <!-- Regular Column -->
                                    <div class="border-r pr-2">
                                        <div class="text-[10px] font-semibold uppercase text-slate-500 mb-1">Recent ({{ $orderSession->sales_history_weeks }}w)</div>
                                        <div class="mb-1">
                                            <div class="text-[9px] text-slate-600">Weekly avg</div>
                                            <div class="text-sm font-bold text-blue-600">{{ number_format($stats['regular_weekly_avg'], 1) }}</div>
                                        </div>
                                        <div>
                                            <div class="text-[9px] text-slate-600">Suggested</div>
                                            <div class="text-base font-semibold {{ !$isChristmasHigher ? 'text-green-600' : 'text-slate-600' }}">
                                                {{ number_format($stats['regular_suggested'], 0) }}
                                                @if(!$isChristmasHigher) <span class="text-xs">✓</span> @endif
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Christmas Column -->
                                    <div class="pl-2">
                                        <div class="text-[10px] font-semibold uppercase text-slate-500 mb-1">Christmas ({{ implode(', ', $christmasData['years']) }})</div>
                                        <div class="mb-1">
                                            <div class="text-[9px] text-slate-600">Weekly avg</div>
                                            <div class="text-sm font-bold text-purple-600">{{ number_format($stats['christmas_weekly_avg'], 1) }}</div>
                                        </div>
                                        <div>
                                            <div class="text-[9px] text-slate-600">Suggested</div>
                                            <div class="text-base font-semibold {{ $isChristmasHigher ? 'text-green-600' : 'text-slate-600' }}">
                                                {{ number_format($stats['christmas_suggested'], 0) }}
                                                @if($isChristmasHigher) <span class="text-xs">✓</span> @endif
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                @if(abs($stats['delta']) > 5)
                                    <div class="w-full p-2 rounded-lg text-[10px] {{ $stats['delta'] > 0 ? 'bg-orange-50 text-orange-800 border border-orange-200' : 'bg-green-50 text-green-800 border border-green-200' }}">
                                        <span class="text-sm">{{ $stats['delta'] > 0 ? '⬆️' : '⬇️' }}</span>
                                        <strong>{{ abs($stats['delta']) }} units difference</strong>
                                        <br><span class="text-[9px]">Using {{ $isChristmasHigher ? 'Christmas' : 'regular' }} (higher)</span>
                                    </div>
                                @endif
                            @endif

                            <div class="text-center">
                                <div class="text-[11px] font-semibold text-gray-500 uppercase tracking-wide">Suggested</div>
                                <div id="suggested-display-{{ $item->id }}" class="mt-1 text-xl font-bold text-purple-700">
                                    {{ $suggestedDisplayText }} {{ $quantityLabel }}
                                </div>
                                <div id="suggested-units-{{ $item->id }}" class="text-xs text-gray-500">{{ number_format($suggestedUnits, 0) }} units</div>
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
                            <button
                                type="button"
                                data-approval-button="{{ $item->id }}"
                                class="px-4 py-2 bg-green-600 text-white rounded-lg font-medium text-sm">
                                ✓ Approved
                            </button>
                        @else
                            <button
                                type="button"
                                data-approval-button="{{ $item->id }}"
                                class="px-4 py-2 bg-blue-600 text-white rounded-lg font-medium hover:bg-blue-700 text-sm">
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
<div class="mb-6" data-priority-section="unit">
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
                    <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase" style="width: 640px;">Stock Levels & Sales Trend</th>
                    <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase w-48">Suggested & Order</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase w-32">Cost</th>
                    <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase w-32">Action</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                @foreach($unitProducts as $item)
                @php
                    $product = $item->product;
                    // Decode context_data if it's a string (should be auto-cast to array, but be defensive)
                    $contextData = $item->context_data ?? [];
                    if (is_string($contextData)) {
                        $contextData = json_decode($contextData, true) ?? [];
                    }

                    // Merge current min_stock_override from pre-loaded array (may have been updated after order session created)
                    if (isset($minStockOverrides[$product->ID])) {
                        $contextData['min_stock_override'] = $minStockOverrides[$product->ID];
                    }

                    $safeProductName = strip_tags(html_entity_decode($product->NAME ?? 'Unknown Product'));
                    $currentStock = $contextData['current_stock'] ?? 0;
                    $avgWeeklySales = (float) ($contextData['avg_weekly_sales'] ?? 0);
                    $safetyFactorWeeks = isset($contextData['safety_factor'])
                        ? (float) $contextData['safety_factor']
                        : 1.5;
                    $safetyStockUnits = $avgWeeklySales * $safetyFactorWeeks;
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

                <tr data-order-item-id="{{ $item->id }}" data-priority="{{ $item->review_priority }}" class="hover:bg-{{ $stockColor }}-50 border-l-4 {{ $borderColor }}" style="height: 360px;">
                    <td class="px-4 py-4">
                        <div data-priority-indicator="{{ $item->id }}" class="w-8 h-8 {{ $iconBg }} rounded-full flex items-center justify-center">
                            <span data-priority-symbol="{{ $item->id }}" class="font-bold text-sm">
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
                        <div class="font-medium text-gray-900">
                            @if(($product->ID ?? null) !== null)
                                <a href="{{ route('products.edit', $product->ID) }}"
                                   class="text-indigo-600 hover:text-indigo-800"
                                   target="_blank"
                                   rel="noopener"
                                   title="Edit {{ $safeProductName }}">
                                    {!! $safeProductName !!}
                                </a>
                            @else
                                {!! $safeProductName !!}
                            @endif
                        </div>
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
                        <div class="mt-2 flex items-center gap-2 text-xs">
                            <label for="priority-select-{{ $item->id }}" class="uppercase tracking-wide text-[11px] text-slate-400">
                                Priority
                            </label>
                            <select
                                id="priority-select-{{ $item->id }}"
                                class="priority-selector border-gray-200 rounded-md text-xs focus:ring-indigo-500 focus:border-indigo-500"
                                data-item-id="{{ $item->id }}"
                                data-product-id="{{ $product->ID ?? '' }}"
                                data-current-priority="{{ $item->review_priority }}"
                                aria-label="Adjust priority for {{ $safeProductName }}"
                            >
                                <option value="review" {{ $item->review_priority === 'review' ? 'selected' : '' }}>🔴 Requires review</option>
                                <option value="standard" {{ $item->review_priority === 'standard' ? 'selected' : '' }}>🟡 Standard</option>
                                <option value="safe" {{ $item->review_priority === 'safe' ? 'selected' : '' }}>🟢 Safe to over-order</option>
                            </select>
                            <span class="hidden text-[11px] text-green-600" data-priority-feedback="{{ $item->id }}">
                                Saved
                            </span>
                        </div>
                        <div class="mt-2 text-xs text-slate-500 leading-tight">
                            <span class="uppercase tracking-wide text-[10px] text-slate-400">Safety stock floor</span>
                            <div class="flex flex-wrap gap-2 text-[11px] text-slate-600">
                                <span>{{ number_format($safetyFactorWeeks, 1) }} wk minimum</span>
                                <span>≈ {{ number_format($safetyStockUnits, 0) }} units</span>
                            </div>
                        </div>
                    </td>
                    <td class="px-4 py-4">
                        <div class="text-center">
                            <div class="text-2xl font-bold text-blue-600">{{ number_format($totalPeriodSales, 0) }}</div>
                            <div class="text-xs text-gray-500 mb-1">total sold</div>
                            <div class="mt-2 flex items-center justify-center gap-6 text-xs text-gray-600">
                                <span>avg {{ number_format($avgWeeklySales, 1) }}</span>
                                <span>peak {{ number_format($peakWeeklySales, 1) }}</span>
                            </div>
                            @include('orders.partials.min-stock-editor', [
                                'item' => $item,
                                'product' => $product,
                                'contextData' => $contextData,
                                'orderSession' => $orderSession,
                                'isCaseProduct' => $isCaseProduct,
                                'caseUnits' => $caseUnits,
                            ])
                            <div class="w-full bg-gray-200 rounded-full h-2">
                                <div
                                    id="stock-level-bar-{{ $item->id }}"
                                    class="bg-{{ $stockColor }}-500 h-2 rounded-full"
                                    data-stock-color="{{ $stockColor }}"
                                    style="width: {{ max(min($currentPct, 100), 0) }}%"
                                ></div>
                            </div>
                            <!-- Current Stock moved here from graph column -->
                            <div class="mt-3 pt-3 border-t border-gray-200">
                                <div class="flex items-center justify-between gap-2">
                                    <div>
                                        <div class="text-[10px] uppercase tracking-wide text-slate-400">Current</div>
                                        <div class="flex items-center gap-1">
                                            <div id="current-stock-value-{{ $item->id }}" class="text-lg font-semibold text-{{ $stockColor }}-600" data-stock-color="{{ $stockColor }}">{{ number_format($currentStock, 0) }}</div>
                                            @if($product?->ID && $currentStock < 0)
                                                <button
                                                    type="button"
                                                    class="reset-stock-button inline-flex items-center gap-0.5 rounded border border-green-200 px-1.5 py-0.5 text-[10px] font-semibold text-green-600 hover:bg-green-50 focus:outline-none focus:ring-2 focus:ring-green-200"
                                                    data-product-id="{{ $product->ID }}"
                                                    data-item-id="{{ $item->id }}"
                                                    data-case-units="{{ $caseUnits }}"
                                                    data-is-case-product="{{ $isCaseProduct ? 1 : 0 }}"
                                                    data-product-name="{{ e($product->NAME ?? 'Product') }}"
                                                    title="Set stock to zero"
                                                    aria-label="Set stock to zero for {{ e($product->NAME ?? 'product') }}"
                                                >
                                                    <svg class="h-3 w-3" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 10.5 10 5l5.5 5.5M10 5v10.5" />
                                                    </svg>
                                                    <span>0</span>
                                                </button>
                                            @endif
                                        </div>
                                        <div id="current-stock-subtext-{{ $item->id }}" class="text-[10px] text-gray-500">
                                            @if($isCaseProduct)
                                                {{ $currentStockCaseText }} cases
                                            @else
                                                units
                                            @endif
                                        </div>
                                    </div>
                                    <div class="text-right">
                                        <div class="text-[10px] uppercase tracking-wide text-slate-400">After</div>
                                        <div id="after-stock-value-{{ $item->id }}" class="text-lg font-semibold text-green-600">{{ number_format($afterStock, 0) }}</div>
                                        <div id="after-stock-subtext-{{ $item->id }}" class="text-[10px] text-gray-500">
                                            @if($isCaseProduct)
                                                {{ $afterStockCaseText }} cases
                                            @else
                                                units
                                            @endif
                                        </div>
                                    </div>
                                </div>
                                @if(isset($contextData['coverage_weeks']))
                                    <div class="text-[10px] text-gray-400 mt-1">
                                        Cover {{ number_format($contextData['coverage_weeks'], 1) }}→{{ number_format($contextData['target_weeks'] ?? $contextData['coverage_weeks'], 1) }} wk
                                    </div>
                                @endif
                            </div>
                        </div>
                    </td>
                    <td class="px-4 py-4 align-bottom">
                        <div class="flex flex-col justify-between gap-2" style="min-height: 280px;">
                            <div class="relative overflow-hidden" style="height: 220px;">
                                <canvas id="chart_{{ $item->id }}" class="w-full h-full"></canvas>
                            </div>
                            <!-- Period totals under the graph -->
                            @php
                                $christmasWindows = $contextData['christmas_comparison']['christmas_windows'] ?? [];
                                // Sort by year ascending for display
                                usort($christmasWindows, fn($a, $b) => ($a['year'] ?? 0) <=> ($b['year'] ?? 0));
                            @endphp
                            <div class="flex items-center justify-between gap-2 text-xs border-t border-gray-100 pt-2">
                                @foreach($christmasWindows as $window)
                                    <div class="text-center flex-1">
                                        <div class="text-[10px] uppercase tracking-wide" style="color: {{ $loop->index === 0 ? 'rgb(168, 85, 247)' : ($loop->index === 1 ? 'rgb(236, 72, 153)' : 'rgb(59, 130, 246)') }};">
                                            Xmas {{ $window['year'] ?? '' }}
                                        </div>
                                        <div class="text-sm font-bold" style="color: {{ $loop->index === 0 ? 'rgb(168, 85, 247)' : ($loop->index === 1 ? 'rgb(236, 72, 153)' : 'rgb(59, 130, 246)') }};">
                                            {{ number_format($window['total_units'] ?? 0, 0) }}
                                        </div>
                                    </div>
                                @endforeach
                                <div class="text-center flex-1">
                                    <div class="text-[10px] uppercase tracking-wide text-slate-500">Recent</div>
                                    <div class="text-sm font-bold text-blue-600">{{ number_format($totalPeriodSales, 0) }}</div>
                                </div>
                                <div class="text-center flex-1">
                                    <div class="text-[10px] uppercase tracking-wide text-slate-400">Current</div>
                                    <div class="text-sm font-semibold text-slate-500">{{ number_format($currentStock, 0) }}</div>
                                </div>
                                <div class="text-center flex-1">
                                    <div class="text-[10px] uppercase tracking-wide text-green-600">After</div>
                                    <div class="text-sm font-semibold text-green-600">{{ number_format($afterStock, 0) }}</div>
                                </div>
                            </div>
                        </div>
                    </td>
                    <td class="px-4 py-4">
                        <div class="flex flex-col items-center gap-3">
                            @php
                                $christmasData = $contextData['christmas_comparison'] ?? null;
                                $stats = $christmasData['stats'] ?? null;
                                $isChristmasHigher = ($stats['selected_mode'] ?? '') === 'christmas';
                            @endphp

                            @if($christmasData && $stats)
                                <!-- Christmas Comparison View -->
                                <div class="w-full grid grid-cols-2 gap-2 p-2 bg-slate-50 rounded-lg border text-xs">
                                    <!-- Regular Column -->
                                    <div class="border-r pr-2">
                                        <div class="text-[10px] font-semibold uppercase text-slate-500 mb-1">Recent ({{ $orderSession->sales_history_weeks }}w)</div>
                                        <div class="mb-1">
                                            <div class="text-[9px] text-slate-600">Weekly avg</div>
                                            <div class="text-sm font-bold text-blue-600">{{ number_format($stats['regular_weekly_avg'], 1) }}</div>
                                        </div>
                                        <div>
                                            <div class="text-[9px] text-slate-600">Suggested</div>
                                            <div class="text-base font-semibold {{ !$isChristmasHigher ? 'text-green-600' : 'text-slate-600' }}">
                                                {{ number_format($stats['regular_suggested'], 0) }}
                                                @if(!$isChristmasHigher) <span class="text-xs">✓</span> @endif
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Christmas Column -->
                                    <div class="pl-2">
                                        <div class="text-[10px] font-semibold uppercase text-slate-500 mb-1">Christmas ({{ implode(', ', $christmasData['years']) }})</div>
                                        <div class="mb-1">
                                            <div class="text-[9px] text-slate-600">Weekly avg</div>
                                            <div class="text-sm font-bold text-purple-600">{{ number_format($stats['christmas_weekly_avg'], 1) }}</div>
                                        </div>
                                        <div>
                                            <div class="text-[9px] text-slate-600">Suggested</div>
                                            <div class="text-base font-semibold {{ $isChristmasHigher ? 'text-green-600' : 'text-slate-600' }}">
                                                {{ number_format($stats['christmas_suggested'], 0) }}
                                                @if($isChristmasHigher) <span class="text-xs">✓</span> @endif
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                @if(abs($stats['delta']) > 5)
                                    <div class="w-full p-2 rounded-lg text-[10px] {{ $stats['delta'] > 0 ? 'bg-orange-50 text-orange-800 border border-orange-200' : 'bg-green-50 text-green-800 border border-green-200' }}">
                                        <span class="text-sm">{{ $stats['delta'] > 0 ? '⬆️' : '⬇️' }}</span>
                                        <strong>{{ abs($stats['delta']) }} units difference</strong>
                                        <br><span class="text-[9px]">Using {{ $isChristmasHigher ? 'Christmas' : 'regular' }} (higher)</span>
                                    </div>
                                @endif
                            @endif

                            <div class="text-center">
                                <div class="text-[11px] font-semibold text-gray-500 uppercase tracking-wide">Suggested</div>
                                <div id="suggested-display-{{ $item->id }}" class="mt-1 text-xl font-bold text-purple-700">
                                    {{ $suggestedDisplayText }} {{ $quantityLabel }}
                                </div>
                                <div id="suggested-units-{{ $item->id }}" class="text-xs text-gray-500">{{ number_format($suggestedUnits, 0) }} units</div>
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
                            <button
                                type="button"
                                data-approval-button="{{ $item->id }}"
                                class="px-4 py-2 bg-green-600 text-white rounded-lg font-medium text-sm">
                                ✓ Approved
                            </button>
                        @else
                            <button
                                type="button"
                                data-approval-button="{{ $item->id }}"
                                class="px-4 py-2 bg-blue-600 text-white rounded-lg font-medium hover:bg-blue-700 text-sm">
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

<div data-priority-empty-state class="hidden bg-white rounded-lg shadow p-8 text-center text-gray-500">
    No items match the selected priority filter.
</div>

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
                const minStockOverride = {{ isset($contextData['min_stock_override']) && $contextData['min_stock_override'] > 0 ? $contextData['min_stock_override'] : 'null' }};

                // Christmas comparison data
                const christmasData = @json($contextData['christmas_comparison'] ?? null);

                // Build chronological timeline: Christmas years (oldest first) → Recent → Current → After
                let extendedLabels = [];
                let salesData = [];
                let christmasDatasetConfigs = []; // Store Christmas dataset info for later
                let recentStartIndex = 0; // Track where recent sales data begins

                const primaryColor = '{{ $item->review_priority === "review" ? "rgb(239, 68, 68)" : ($item->review_priority === "standard" ? "rgb(234, 179, 8)" : "rgb(34, 197, 94)") }}';
                const greyColor = 'rgb(203, 213, 225)'; // Tailwind slate-300 (lighter grey)
                const christmasColors = ['rgb(168, 85, 247)', 'rgb(236, 72, 153)', 'rgb(59, 130, 246)'];

                // 1. Add Christmas data FIRST (sorted by year ascending - oldest first)
                if (christmasData && christmasData.christmas_windows && christmasData.christmas_windows.length > 0) {
                    // Sort windows by year ascending (2023 before 2024)
                    const sortedWindows = [...christmasData.christmas_windows].sort((a, b) => a.year - b.year);

                    sortedWindows.forEach((window, index) => {
                        const startIdx = extendedLabels.length;

                        // Add Christmas labels for this year
                        const christmasLabels = window.weekly_breakdown.map(w => `'${String(window.year).slice(-2)} ${w.label}`);
                        extendedLabels.push(...christmasLabels);

                        // Add nulls to salesData (recent sales don't exist in Christmas section)
                        salesData.push(...Array(christmasLabels.length).fill(null));

                        // Store config for building dataset later
                        christmasDatasetConfigs.push({
                            year: window.year,
                            startIdx: startIdx,
                            data: window.weekly_breakdown.map(w => w.units),
                            colorIndex: index
                        });

                        // Add separator between Christmas years
                        if (index < sortedWindows.length - 1) {
                            extendedLabels.push('—');
                            salesData.push(null);
                        }
                    });

                    // Add separator before recent sales
                    extendedLabels.push('—');
                    salesData.push(null);
                }

                // 2. Track where recent sales start, then add recent labels
                recentStartIndex = extendedLabels.length;
                extendedLabels.push(...labels);
                salesData.push(...dataPoints);

                // 3. Add Current and After markers
                extendedLabels.push('Current', 'After');
                salesData.push(null, null);

                // Build point colors for recent sales section only
                const pointColors = [];
                const pointBorderColors = [];
                const pointRadius = [];

                // Fill with transparent for Christmas section
                for (let i = 0; i < recentStartIndex; i++) {
                    pointColors.push('transparent');
                    pointBorderColors.push('transparent');
                    pointRadius.push(0);
                }

                // Add colors for recent sales
                dataPoints.forEach(value => {
                    pointColors.push(value === 0 ? greyColor : primaryColor);
                    pointBorderColors.push(value === 0 ? greyColor : primaryColor);
                    pointRadius.push(value === 0 ? 2 : 3);
                });

                // Add transparent for Current/After
                pointColors.push('transparent', 'transparent');
                pointBorderColors.push('transparent', 'transparent');
                pointRadius.push(0, 0);

                // Build average line (only spans recent sales section)
                const averageLineData = Array(extendedLabels.length).fill(null);
                for (let i = recentStartIndex; i < recentStartIndex + labels.length; i++) {
                    averageLineData[i] = averageUnits;
                }

                // Build min stock override line (only spans recent sales section)
                let minStockLineData = null;
                if (minStockOverride !== null) {
                    minStockLineData = Array(extendedLabels.length).fill(null);
                    for (let i = recentStartIndex; i < recentStartIndex + labels.length; i++) {
                        minStockLineData[i] = minStockOverride;
                    }
                }

                // Current stock and After order data points (at end)
                const currentStockData = Array(extendedLabels.length).fill(null);
                currentStockData[extendedLabels.length - 2] = currentStock;

                const afterStockData = Array(extendedLabels.length).fill(null);
                afterStockData[extendedLabels.length - 1] = afterStock;

                const salesMax = dataPoints.length ? Math.max(...dataPoints) : 0;
                // Include Christmas data in max calculation so chart scales to fit all data
                const christmasMax = christmasDatasetConfigs.length > 0
                    ? Math.max(...christmasDatasetConfigs.flatMap(c => c.data))
                    : 0;
                const chartMax = Math.max(salesMax, christmasMax, currentStock, afterStock, peakWeeklySales, averageUnits, minStockOverride || 0, 1);
                const chartMin = Math.min(0, currentStock, afterStock);

                // Build datasets array
                const datasets = [];

                // Add Christmas datasets FIRST (so they appear behind recent sales)
                christmasDatasetConfigs.forEach((config, index) => {
                    const christmasDataPoints = Array(extendedLabels.length).fill(null);
                    config.data.forEach((value, dataIdx) => {
                        christmasDataPoints[config.startIdx + dataIdx] = value;
                    });

                    datasets.push({
                        label: `Christmas ${config.year}`,
                        data: christmasDataPoints,
                        borderColor: christmasColors[config.colorIndex % christmasColors.length],
                        backgroundColor: christmasColors[config.colorIndex % christmasColors.length].replace(')', ', 0.1)'),
                        borderWidth: 2,
                        tension: 0.35,
                        pointRadius: 4,
                        pointBorderWidth: 2,
                        spanGaps: true,
                        fill: false,
                        order: 4 + index
                    });
                });

                // Add average line
                datasets.push({
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
                });

                // Add min stock override line if set
                if (minStockOverride !== null && minStockLineData !== null) {
                    datasets.push({
                        label: 'Min Stock Override',
                        data: minStockLineData,
                        borderColor: 'rgb(249, 115, 22)', // Orange
                        borderWidth: 2,
                        borderDash: [8, 4],
                        pointRadius: 0,
                        pointHoverRadius: 0,
                        fill: false,
                        tension: 0,
                        spanGaps: true,
                        order: 0
                    });
                }

                // Add recent sales dataset
                datasets.push({
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
                });

                // Add Current stock and After order points
                datasets.push({
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
                });

                const chart = new Chart(chartEl, {
                    type: 'line',
                    data: {
                        labels: extendedLabels,
                        datasets: datasets
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
                            legend: {
                                display: christmasData && christmasData.christmas_windows ? true : false,
                                position: 'top',
                                labels: {
                                    usePointStyle: true,
                                    padding: 8,
                                    font: { size: 10 },
                                    boxWidth: 10,
                                    boxHeight: 10
                                }
                            },
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
                                        if (context.dataset.label === 'Min Stock Override') {
                                            if (Number.isNaN(context.parsed.y)) {
                                                return null;
                                            }
                                            return `Min Stock Override · ${minStockOverride.toFixed(0)} units`;
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

                                        if (context.dataset.label.startsWith('Christmas ')) {
                                            const value = Math.round(context.parsed.y);
                                            if (Number.isNaN(value)) {
                                                return null;
                                            }
                                            return `${context.dataset.label} · ${value} units sold`;
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
        const priorityStates = {
            review: {
                border: 'border-red-500',
                icon: ['bg-red-100', 'text-red-600'],
                symbol: '!',
            },
            standard: {
                border: 'border-yellow-500',
                icon: ['bg-yellow-100', 'text-yellow-600'],
                symbol: '●',
            },
            safe: {
                border: 'border-green-500',
                icon: ['bg-green-100', 'text-green-600'],
                symbol: '✓',
            },
        };
        const priorityBorderClasses = ['border-red-500', 'border-yellow-500', 'border-green-500'];
        const priorityIconClasses = ['bg-red-100', 'text-red-600', 'bg-yellow-100', 'text-yellow-600', 'bg-green-100', 'text-green-600'];
        const approvalButtonBlueClasses = ['bg-blue-600', 'hover:bg-blue-700'];
        const approvalButtonGreenClasses = ['bg-green-600'];
        const priorityFeedbackTimers = {};
        const priorityFilterButtons = document.querySelectorAll('[data-priority-filter]');
        const priorityCountElements = document.querySelectorAll('[data-priority-count]');
        const priorityEmptyState = document.querySelector('[data-priority-empty-state]');
        let currentPriorityFilter = 'all';

        const applyClassList = (element, classes, action) => {
            if (!element || !classes) {
                return;
            }

            classes.split(/\s+/).filter(Boolean).forEach(className => {
                element.classList[action](className);
            });
        };

        const updatePriorityButtons = (activeFilter) => {
            priorityFilterButtons.forEach(button => {
                const activeClasses = button.dataset.activeClasses || '';
                const inactiveClasses = button.dataset.inactiveClasses || '';
                applyClassList(button, activeClasses, 'remove');
                applyClassList(button, inactiveClasses, 'remove');

                if ((button.dataset.priorityFilter || 'all') === activeFilter) {
                    applyClassList(button, activeClasses, 'add');
                    button.setAttribute('aria-pressed', 'true');
                } else {
                    applyClassList(button, inactiveClasses, 'add');
                    button.setAttribute('aria-pressed', 'false');
                }
            });
        };

        const applyPriorityFilter = (filter) => {
            const rows = document.querySelectorAll('tr[data-priority]');

            if (rows.length === 0) {
                if (priorityEmptyState) {
                    priorityEmptyState.classList.add('hidden');
                }
                priorityCountElements.forEach(element => {
                    const key = element.dataset.priorityCount;
                    if (key) {
                        element.textContent = '0';
                    }
                });
                return;
            }

            const counts = {
                review: 0,
                standard: 0,
                safe: 0,
                all: rows.length,
            };

            let visibleCount = 0;

            rows.forEach(row => {
                const rowPriority = row.dataset.priority || 'standard';
                counts[rowPriority] = (counts[rowPriority] ?? 0) + 1;

                const matches = filter === 'all' || rowPriority === filter;
                row.classList.toggle('hidden', !matches);

                if (matches) {
                    visibleCount++;
                }
            });

            document.querySelectorAll('[data-priority-section]').forEach(section => {
                const sectionRows = section.querySelectorAll('tbody tr[data-priority]');
                const sectionVisible = Array.from(sectionRows).some(row => !row.classList.contains('hidden'));
                section.classList.toggle('hidden', !sectionVisible);
            });

            priorityCountElements.forEach(element => {
                const key = element.dataset.priorityCount;
                if (key && counts[key] !== undefined) {
                    element.textContent = counts[key];
                }
            });

            if (priorityEmptyState) {
                priorityEmptyState.classList.toggle('hidden', visibleCount > 0);
            }
        };

        function showPriorityFeedback(itemId, message, isError = false) {
            const feedback = document.querySelector(`[data-priority-feedback="${itemId}"]`);
            if (!feedback) {
                return;
            }

            feedback.textContent = message;
            feedback.classList.remove('hidden', 'text-green-600', 'text-red-600');
            feedback.classList.add(isError ? 'text-red-600' : 'text-green-600');

            if (priorityFeedbackTimers[itemId]) {
                clearTimeout(priorityFeedbackTimers[itemId]);
            }

            priorityFeedbackTimers[itemId] = setTimeout(() => {
                feedback.classList.add('hidden');
            }, isError ? 4000 : 1500);
        }

        function applyPriorityStyles(itemId, priority, autoApproved) {
            const state = priorityStates[priority];
            if (!state) {
                return;
            }

            const row = document.querySelector(`tr[data-order-item-id="${itemId}"]`);
            if (row) {
                row.dataset.priority = priority;
                priorityBorderClasses.forEach(cls => row.classList.remove(cls));
                row.classList.add(state.border);
            }

            const indicator = document.querySelector(`[data-priority-indicator="${itemId}"]`);
            if (indicator) {
                priorityIconClasses.forEach(cls => indicator.classList.remove(cls));
                state.icon.forEach(cls => indicator.classList.add(cls));
            }

            const symbol = document.querySelector(`[data-priority-symbol="${itemId}"]`);
            if (symbol) {
                symbol.textContent = state.symbol;
            }

            const approvalButton = document.querySelector(`[data-approval-button="${itemId}"]`);
            if (approvalButton) {
                approvalButton.classList.remove(...approvalButtonBlueClasses);
                approvalButton.classList.remove(...approvalButtonGreenClasses);

                if (autoApproved) {
                    approvalButton.classList.add(...approvalButtonGreenClasses);
                    approvalButton.textContent = '✓ Approved';
                } else {
                    approvalButton.classList.add(...approvalButtonBlueClasses);
                    approvalButton.textContent = 'Approve';
                }
            }
        }

        function persistPriority(itemId, priority, previousPriority, selectEl) {
            if (!priorityStates[priority]) {
                return;
            }

            const endpoint = `${baseUrl}/order-items/${itemId}/priority`;
            const payload = {
                priority,
                apply_to_product: true,
            };

            selectEl.disabled = true;
            selectEl.classList.remove('border-red-400', 'bg-red-50', 'border-green-400', 'bg-green-50');
            selectEl.classList.add('border-blue-400', 'bg-blue-50');

            fetch(endpoint, {
                method: 'PATCH',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'Accept': 'application/json',
                },
                body: JSON.stringify(payload),
            })
                .then(response => {
                    if (!response.ok) {
                        return response.json()
                            .catch(() => ({}))
                            .then(err => {
                                throw new Error(err.message || `HTTP ${response.status}: ${response.statusText}`);
                            });
                    }

                    return response.json();
                })
                .then(data => {
                    if (!data.success) {
                        throw new Error(data.error || 'Failed to update priority');
                    }

                    const updatedPriority = data.item?.review_priority || priority;
                    const autoApproved = Boolean(data.item?.auto_approved);

                    selectEl.dataset.currentPriority = updatedPriority;
                    selectEl.dataset.previousPriority = updatedPriority;
                    selectEl.classList.remove('border-blue-400', 'bg-blue-50');
                    selectEl.classList.add('border-green-400', 'bg-green-50');
                    setTimeout(() => {
                        selectEl.classList.remove('border-green-400', 'bg-green-50');
                    }, 1000);

                    applyPriorityStyles(itemId, updatedPriority, autoApproved);
                    applyPriorityFilter(currentPriorityFilter);
                    showPriorityFeedback(itemId, data.message || 'Saved');
                })
                .catch(error => {
                    console.error('Priority update error:', error);
                    selectEl.classList.remove('border-blue-400', 'bg-blue-50');
                    selectEl.classList.add('border-red-400', 'bg-red-50');
                    setTimeout(() => {
                        selectEl.classList.remove('border-red-400', 'bg-red-50');
                    }, 2000);
                    selectEl.value = previousPriority;

                    showPriorityFeedback(itemId, error.message || 'Failed to save priority', true);
                })
                .finally(() => {
                    selectEl.disabled = false;
                });
        }

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

        priorityFilterButtons.forEach(button => {
            button.addEventListener('click', function() {
                currentPriorityFilter = this.dataset.priorityFilter || 'all';
                updatePriorityButtons(currentPriorityFilter);
                applyPriorityFilter(currentPriorityFilter);
            });
        });

        updatePriorityButtons(currentPriorityFilter);
        applyPriorityFilter(currentPriorityFilter);

        // Handle input changes (debounced save)
        document.querySelectorAll('.priority-selector').forEach(select => {
            select.addEventListener('focus', function() {
                this.dataset.previousPriority = this.value;
            });

            select.addEventListener('change', function() {
                const itemId = this.dataset.itemId;
                if (!itemId) {
                    return;
                }

                const newPriority = this.value;
                const currentPriority = this.dataset.currentPriority || this.dataset.previousPriority || newPriority;

                if (newPriority === currentPriority) {
                    return;
                }

                persistPriority(itemId, newPriority, currentPriority, this);
            });
        });

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
