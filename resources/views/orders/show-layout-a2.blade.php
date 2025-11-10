<x-admin-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-2 lg:flex-row lg:items-center lg:justify-between">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.3em] text-slate-400">Orders · Layout A2</p>
                <h2 class="font-semibold text-2xl text-slate-900 leading-tight">
                    Supplier Review – {{ $order->supplier->Supplier ?? 'Supplier' }}
                </h2>
                <p class="text-sm text-slate-600 mt-1">
                    Delivery {{ optional($order->order_date)->format('l, F j, Y') ?? 'TBC' }}
                    • Created by {{ $order->user->name ?? 'System' }}
                    • Status <span class="font-semibold text-slate-900">{{ ucfirst($order->status) }}</span>
                </p>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('orders.show', $order) }}" class="inline-flex items-center gap-2 rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
                    Table View
                </a>
                <a href="{{ route('orders.grid-view', $order) }}" class="inline-flex items-center gap-2 rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
                    Grid View
                </a>
                <a href="{{ route('orders.index') }}" class="inline-flex items-center gap-2 rounded-lg border border-slate-200 px-4 py-2 text-sm font-medium text-slate-500 hover:bg-slate-50">
                    Back to Orders
                </a>
            </div>
        </div>
    </x-slot>

    @php
        $coverageDays = $order->coverage_days ?? null;
        $coverageWeeks = $coverageDays ? $coverageDays / 7 : null;
        $coverageEnds = optional($order->coverage_ends_on)?->format('l, F j, Y');
        $salesHistoryWeeks = $order->sales_history_weeks ?? 8;

        $currencySymbol = '€';

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
            return rtrim(rtrim($formatted, '0'), '.') ?: '0';
        };

        $priorityOrder = ['review' => 0, 'standard' => 1, 'safe' => 2];

        $itemsCollection = $order->items
            ->map(function ($item) use ($formatQuantityDisplay, $formatQuantityInput, $priorityOrder, $order) {
                $product = $item->product;
                $contextData = $item->context_data ?? [];
                if (is_string($contextData)) {
                    $contextData = json_decode($contextData, true) ?? [];
                }

                $weeklySales = $contextData['weekly_sales'] ?? [];
                $weekLabels = array_map(static fn ($week) => $week['label'] ?? '', $weeklySales);
                $weekUnitsRaw = array_map(static fn ($week) => (float) ($week['units'] ?? 0), $weeklySales);
                if (empty($weekLabels)) {
                    $historyWeeks = max((int) ($contextData['sales_history_weeks'] ?? 8), 1);
                    $weekLabels = array_map(static fn ($index) => 'W'.($index + 1), range(0, $historyWeeks - 1));
                }
                if (count($weekUnitsRaw) !== count($weekLabels)) {
                    $weekUnitsRaw = array_pad($weekUnitsRaw, count($weekLabels), 0.0);
                }
                $weekUnits = array_map(static fn ($value) => round($value, 2), $weekUnitsRaw);
                $totalSales = array_sum($weekUnits);
                $avgWeeklySales = $contextData['avg_weekly_sales'] ?? ($totalSales > 0 && count($weekUnits) > 0 ? $totalSales / max(count($weekUnits), 1) : 0);
                $peakWeeklySales = $contextData['peak_weekly_sales'] ?? (count($weekUnits) > 0 ? max($weekUnits) : $avgWeeklySales);
                if ($peakWeeklySales <= 0 && $avgWeeklySales > 0) {
                    $peakWeeklySales = $avgWeeklySales;
                }
                $peakWeeklySales = (float) max($peakWeeklySales, 0);
                $peakIndex = 0;
                if (count($weekUnits) > 0) {
                    $peakIndex = array_keys($weekUnits, max($weekUnits), true)[0] ?? 0;
                }

                $caseUnits = (float) ($contextData['case_units'] ?? $item->case_units ?? 1);
                if ($caseUnits <= 0) {
                    $caseUnits = 1;
                }
                $isCaseProduct = (bool) ($contextData['is_case_product'] ?? ($caseUnits > 1));
                $suggestedUnits = (float) ($item->suggested_quantity ?? 0);
                $finalUnits = (float) ($item->final_quantity ?? $suggestedUnits);
                $suggestedCases = $isCaseProduct
                    ? (float) ($item->suggested_cases ?? ($caseUnits > 0 ? $suggestedUnits / $caseUnits : 0))
                    : null;
                $finalCases = $isCaseProduct
                    ? (float) ($item->final_cases ?? ($caseUnits > 0 ? $finalUnits / $caseUnits : 0))
                    : null;

                $displayQuantity = $isCaseProduct ? $finalCases : $finalUnits;
                $suggestedDisplayQuantity = $isCaseProduct ? ($suggestedCases ?? 0) : $suggestedUnits;
                $quantityLabel = $isCaseProduct ? 'cases' : 'units';
                $quantityPrecision = $isCaseProduct ? 3 : 0;

                $currentStock = (float) ($contextData['current_stock'] ?? 0);
                $afterStock = $currentStock + $finalUnits;
                $minStock = $contextData['min_stock_override']
                    ?? $contextData['min_stock']
                    ?? ($contextData['calculated_min_stock'] ?? null);
                $unitCost = (float) ($item->unit_cost ?? 0);
                $costImpact = $finalUnits * $unitCost;

                $productName = strip_tags(html_entity_decode($product->NAME ?? $contextData['product_name'] ?? 'Unknown product'));
                $productCode = $product->CODE ?? $product->REFERENCE ?? $contextData['product_code'] ?? 'N/A';
                $supplierLink = $product?->supplierLinks?->first();
                $supplierName = $contextData['supplier_name']
                    ?? optional($supplierLink?->supplier)->Supplier
                    ?? ($order->supplier->Supplier ?? 'Supplier');
                $categoryLabel = $contextData['category_path'] ?? ($product->CATEGORY ?? 'General');
                $supplierCode = $contextData['supplier_code']
                    ?? ($supplierLink->SupplierCode ?? null);

                $priority = $item->review_priority ?? 'standard';
                $priorityLabel = match ($priority) {
                    'review' => 'Requires review',
                    'safe' => 'Safe',
                    default => 'Standard',
                };

                $chartColor = match ($priority) {
                    'review' => 'rgb(248, 113, 113)',
                    'standard' => 'rgb(251, 191, 36)',
                    default => 'rgb(34, 197, 94)',
                };

                return [
                    'id' => $item->id,
                    'model' => $item,
                    'product_model' => $product,
                    'priority' => $priority,
                    'priority_label' => $priorityLabel,
                    'auto_approved' => (bool) $item->auto_approved,
                    'product' => [
                        'name' => $productName,
                        'code' => $productCode,
                        'supplier' => $supplierName,
                        'category' => $categoryLabel,
                        'supplier_code' => $supplierCode,
                    ],
                    'stats' => [
                        'total_sales' => $totalSales,
                        'average' => $avgWeeklySales,
                        'peak' => $peakWeeklySales,
                        'current_stock' => $currentStock,
                        'after_stock' => $afterStock,
                        'min_stock' => $minStock,
                    ],
                    'order' => [
                        'display_quantity' => $formatQuantityDisplay((float) $displayQuantity, $quantityPrecision),
                        'suggested_display' => $formatQuantityDisplay((float) $suggestedDisplayQuantity, $quantityPrecision),
                        'input_value' => $formatQuantityInput((float) $displayQuantity, $quantityPrecision),
                        'reset_value' => $formatQuantityInput((float) $suggestedDisplayQuantity, $quantityPrecision),
                        'quantity_label' => $quantityLabel,
                        'quantity_precision' => $quantityPrecision,
                        'suggested_units' => $suggestedUnits,
                        'final_units' => $finalUnits,
                        'case_units' => $caseUnits,
                        'is_case_product' => $isCaseProduct,
                    ],
                    'unit_cost' => $unitCost,
                    'cost_impact' => $costImpact,
                    'context_data' => $contextData,
                    'chart' => [
                        'labels' => $weekLabels,
                        'sales' => $weekUnits,
                        'min_stock' => $minStock,
                        'peak_index' => $peakIndex,
                        'color' => $chartColor,
                    ],
                ];
            })
            ->sortBy(static function ($item) use ($priorityOrder) {
                $priorityRank = $priorityOrder[$item['priority']] ?? 3;
                return ($priorityRank * 1_000_000) - ($item['stats']['total_sales'] ?? 0);
            })
            ->values();

        $priorityCounts = [
            'review' => $itemsCollection->where('priority', 'review')->count(),
            'standard' => $itemsCollection->where('priority', 'standard')->count(),
            'safe' => $itemsCollection->where('priority', 'safe')->count(),
        ];
        $priorityCounts['all'] = $itemsCollection->count();

        $chartPayloads = $itemsCollection->map(static function ($item) {
            return [
                'id' => $item['id'],
                'labels' => $item['chart']['labels'],
                'sales' => $item['chart']['sales'],
                'minStock' => $item['chart']['min_stock'],
                'current' => $item['stats']['current_stock'],
                'after' => $item['stats']['after_stock'],
                'peakIndex' => $item['chart']['peak_index'],
                'color' => $item['chart']['color'],
                'total' => $item['stats']['total_sales'],
                'peak' => $item['stats']['peak'],
                'caseUnits' => $item['order']['case_units'],
                'isCaseProduct' => $item['order']['is_case_product'],
            ];
        });

        $reviewCount = $priorityCounts['review'];
        $standardCount = $priorityCounts['standard'];
        $safeCount = $priorityCounts['safe'];
    @endphp

    <style>
        @media (min-width: 1024px) {
            .order-a2-controls {
                flex: 0 0 clamp(10rem, 14vw, 12rem);
            }
        }
        .order-a2-canvas {
            width: 100% !important;
            height: 100% !important;
        }
    </style>

    <div class="py-6">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div class="bg-white shadow-sm rounded-lg p-4">
                    <div class="text-sm font-medium text-slate-500">Total Items</div>
                    <div class="mt-1 text-2xl font-semibold text-slate-900">{{ $statistics['total_items'] }}</div>
                </div>
                <div class="bg-rose-50 shadow-sm rounded-lg p-4">
                    <div class="text-sm font-medium text-rose-600">Requires Review</div>
                    <div class="mt-1 text-2xl font-semibold text-rose-700">{{ $reviewCount }}</div>
                </div>
                <div class="bg-amber-50 shadow-sm rounded-lg p-4">
                    <div class="text-sm font-medium text-amber-600">Standard</div>
                    <div class="mt-1 text-2xl font-semibold text-amber-700">{{ $standardCount }}</div>
                </div>
                <div class="bg-emerald-50 shadow-sm rounded-lg p-4">
                    <div class="text-sm font-medium text-emerald-600">Safe Items</div>
                    <div class="mt-1 text-2xl font-semibold text-emerald-700">{{ $safeCount }}</div>
                </div>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div class="bg-blue-50 shadow-sm rounded-lg p-4 md:col-span-2">
                    <div class="text-sm font-medium text-blue-700">Coverage Window</div>
                    <div class="mt-1 text-base text-blue-900">
                        {{ $coverageEnds ? "Through {$coverageEnds}" : 'Not specified' }}
                        @if($coverageWeeks)
                            • {{ number_format($coverageWeeks, 1) }} week target
                        @endif
                    </div>
                    <div class="text-xs text-blue-700 mt-1">
                        Sales history window {{ $salesHistoryWeeks }} weeks
                    </div>
                </div>
                <div class="bg-slate-50 shadow-sm rounded-lg p-4">
                    <div class="text-sm font-medium text-slate-600">Order value</div>
                    <div class="mt-1 text-2xl font-semibold text-slate-900">{{ $currencySymbol }}{{ number_format($order->total_value, 2) }}</div>
                    <div class="text-xs text-slate-500 mt-1">Avg per item {{ $currencySymbol }}{{ number_format($statistics['avg_item_value'], 2) }}</div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow-sm p-4 flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                <div class="flex flex-wrap gap-2">
                    <button type="button" class="priority-filter-button px-4 py-2 rounded-full text-sm font-semibold bg-white border border-rose-200 text-rose-600 hover:bg-rose-50" data-priority-filter="review" data-active-classes="bg-rose-600 text-white border-rose-600" data-inactive-classes="bg-white border border-rose-200 text-rose-600">
                        🔴 Review (<span data-priority-count="review">{{ $priorityCounts['review'] }}</span>)
                    </button>
                    <button type="button" class="priority-filter-button px-4 py-2 rounded-full text-sm font-semibold bg-white border border-amber-200 text-amber-600 hover:bg-amber-50" data-priority-filter="standard" data-active-classes="bg-amber-500 text-white border-amber-500" data-inactive-classes="bg-white border border-amber-200 text-amber-600">
                        🟡 Standard (<span data-priority-count="standard">{{ $priorityCounts['standard'] }}</span>)
                    </button>
                    <button type="button" class="priority-filter-button px-4 py-2 rounded-full text-sm font-semibold bg-white border border-emerald-200 text-emerald-600 hover:bg-emerald-50" data-priority-filter="safe" data-active-classes="bg-emerald-500 text-white border-emerald-500" data-inactive-classes="bg-white border border-emerald-200 text-emerald-600">
                        🟢 Safe (<span data-priority-count="safe">{{ $priorityCounts['safe'] }}</span>)
                    </button>
                    <button type="button" class="priority-filter-button px-4 py-2 rounded-full text-sm font-semibold bg-slate-900 text-white" data-priority-filter="all" data-active-classes="bg-slate-900 text-white" data-inactive-classes="bg-white border border-slate-200 text-slate-700" aria-pressed="true">
                        All (<span data-priority-count="all">{{ $priorityCounts['all'] }}</span>)
                    </button>
                </div>
                <div class="flex flex-wrap gap-2">
                    @if($order->isEditable())
                        <form method="POST" action="{{ route('orders.auto-approve-safe', $order) }}" class="inline-flex">
                            @csrf
                            <button type="submit" class="inline-flex items-center gap-2 rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-emerald-700">
                                Auto-approve safe
                            </button>
                        </form>
                        <form method="POST" action="{{ route('orders.complete', $order) }}" class="inline-flex">
                            @csrf
                            <button type="submit" class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-700">
                                Complete order
                            </button>
                        </form>
                    @endif
                    <a href="{{ route('orders.export', $order) }}" class="inline-flex items-center gap-2 rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                        Export CSV
                    </a>
                </div>
            </div>

            <div class="space-y-6" id="order-a2-card-container">
                @forelse($itemsCollection as $card)
                    @php
                        $itemModel = $card['model'];
                        $productModel = $card['product_model'];
                        $contextData = $card['context_data'];
                        $minStockForDisplay = $card['stats']['min_stock'];
                        $isCaseProduct = $card['order']['is_case_product'];
                        $caseUnits = $card['order']['case_units'];
                    @endphp
                    <article
                        class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm transition"
                        data-order-item-card="true"
                        data-order-item-id="{{ $card['id'] }}"
                        data-priority="{{ $card['priority'] }}"
                    >
                        <div class="md:flex md:items-stretch">
                            <aside class="hidden bg-slate-900/95 p-6 text-slate-200 md:block md:w-60 md:flex-shrink-0">
                                <div class="flex h-full flex-col">
                                    <div class="pb-5">
                                        <p class="text-[11px] uppercase tracking-[0.2em] text-slate-500">Product</p>
                                        <h3 class="mt-2 text-base font-semibold text-white leading-snug">
                                            @if(($productModel->ID ?? null))
                                                <a href="{{ route('products.edit', $productModel->ID) }}" target="_blank" class="hover:underline">
                                                    {{ $card['product']['name'] }}
                                                </a>
                                            @else
                                                {{ $card['product']['name'] }}
                                            @endif
                                        </h3>
                                        <p class="mt-1 text-xs text-slate-400 leading-relaxed">
                                            {{ $card['product']['code'] }} • {{ $card['product']['supplier'] }}
                                        </p>
                                        <p class="text-[11px] uppercase tracking-[0.2em] text-slate-500 mt-2">{{ $card['product']['category'] }}</p>
                                    </div>

                                    <dl class="space-y-4 border-t border-slate-800/60 pt-5 text-xs text-slate-300">
                                        <div>
                                            <dt class="text-slate-500 uppercase tracking-wide text-[10px]">Min stock</dt>
                                            <dd class="mt-1 text-sm font-semibold text-orange-200">
                                                {{ $minStockForDisplay !== null ? number_format($minStockForDisplay, 0) : '—' }}
                                            </dd>
                                        </div>
                                        <div>
                                            <dt class="text-slate-500 uppercase tracking-wide text-[10px]">Unit cost</dt>
                                            <dd class="mt-1 text-sm font-semibold text-emerald-200">{{ $currencySymbol }}{{ number_format($card['unit_cost'], 2) }}</dd>
                                        </div>
                                        <div>
                                            <dt class="text-slate-500 uppercase tracking-wide text-[10px]">Total sold (8w)</dt>
                                            <dd class="mt-1 text-sm font-semibold text-slate-100">{{ number_format($card['stats']['total_sales']) }}</dd>
                                        </div>
                                    </dl>

                                    <div class="mt-auto space-y-3 border-t border-slate-800/60 pt-5 text-xs">
                                        @if($card['product']['supplier_code'])
                                            <div class="flex items-center justify-between text-[11px] uppercase tracking-widest text-slate-500">
                                                <span>Supplier code</span>
                                                <span class="font-mono text-sm text-slate-100">{{ $card['product']['supplier_code'] }}</span>
                                            </div>
                                        @endif
                                        <label class="text-slate-500 uppercase tracking-wide text-[10px]" for="priority-select-{{ $card['id'] }}">Priority</label>
                                        <select
                                            id="priority-select-{{ $card['id'] }}"
                                            class="priority-selector w-full rounded-md border border-slate-600 bg-slate-900/60 px-2 py-1 text-xs text-white focus:border-indigo-400 focus:ring-indigo-400"
                                            data-item-id="{{ $card['id'] }}"
                                            data-current-priority="{{ $card['priority'] }}"
                                        >
                                            <option value="review" {{ $card['priority'] === 'review' ? 'selected' : '' }}>Requires review</option>
                                            <option value="standard" {{ $card['priority'] === 'standard' ? 'selected' : '' }}>Standard</option>
                                            <option value="safe" {{ $card['priority'] === 'safe' ? 'selected' : '' }}>Safe</option>
                                        </select>
                                        <div class="flex items-center gap-2 text-[11px] text-slate-400">
                                            <span data-priority-feedback="{{ $card['id'] }}" class="hidden"></span>
                                        </div>
                                        <div class="flex gap-2 text-xs">
                                            <button class="flex-1 rounded-md border border-slate-600 px-3 py-2 font-semibold text-slate-100">
                                                Flag
                                            </button>
                                            <button class="flex-1 rounded-md border border-slate-600 px-3 py-2 font-semibold text-slate-100">
                                                Adjust
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </aside>

                            <div class="flex-1 p-6 md:pl-8">
                                <div class="flex flex-col gap-6 lg:flex-row lg:items-start">
                                    <div class="rounded-xl border border-slate-200 bg-slate-50/60 p-4 lg:flex-1">
                                        <div class="flex items-center justify-between">
                                            <div class="text-[11px] font-semibold uppercase tracking-[0.3em] text-slate-500">Sales + Stock</div>
                                            <span data-priority-pill="{{ $card['id'] }}" class="rounded-full border px-2 py-0.5 text-xs font-semibold text-slate-600">
                                                {{ $card['priority_label'] }}
                                            </span>
                                        </div>
                                        <div class="mt-4 grid gap-4 text-xs font-semibold uppercase tracking-wide text-slate-500 sm:grid-cols-5">
                                            <div>
                                                <div>Total 8w</div>
                                                <div class="text-lg font-semibold text-slate-900">{{ number_format($card['stats']['total_sales']) }}</div>
                                            </div>
                                            <div>
                                                <div>Average</div>
                                                <div class="text-lg font-semibold text-slate-900">{{ number_format($card['stats']['average'], 1) }}</div>
                                            </div>
                                            <div>
                                                <div>Peak</div>
                                                <div class="text-lg font-semibold text-slate-900">{{ number_format($card['stats']['peak'], 1) }}</div>
                                            </div>
                                            <div>
                                                <div>Current</div>
                                                <div class="text-lg font-semibold text-rose-600">{{ number_format($card['stats']['current_stock']) }}</div>
                                            </div>
                                            <div>
                                                <div>After order</div>
                                                <div class="text-lg font-semibold text-emerald-600" id="after-stock-value-{{ $card['id'] }}">{{ number_format($card['stats']['after_stock']) }}</div>
                                            </div>
                                        </div>
                                        <div class="mt-4 h-64">
                                            <canvas id="order-card-chart-{{ $card['id'] }}" class="order-a2-canvas" aria-label="Sales trend"></canvas>
                                        </div>
                                        <div class="mt-3 text-xs font-semibold uppercase tracking-wide text-orange-600">
                                            Min stock target · {{ $minStockForDisplay !== null ? number_format($minStockForDisplay, 0) : 'Not set' }}
                                        </div>
                                    </div>

                                    <div class="order-a2-controls flex w-full flex-col gap-3">
                                        <div>
                                            <div class="mb-1 flex items-baseline justify-between text-xs uppercase tracking-wide text-slate-400">
                                                <span>Order {{ $card['order']['quantity_label'] }}</span>
                                                <span class="text-xs font-medium text-slate-500">Suggested <span class="font-semibold">{{ $card['order']['suggested_display'] }}</span></span>
                                            </div>
                                            <div class="flex items-center gap-2">
                                                <button type="button" class="qty-decrease inline-flex h-9 w-9 items-center justify-center rounded-md border border-slate-300 text-lg font-semibold text-slate-600" data-item-id="{{ $card['id'] }}">−</button>
                                                <input
                                                    id="qty-input-{{ $card['id'] }}"
                                                    class="qty-input w-full rounded-md border border-slate-300 px-2.5 py-1.5 text-base font-semibold text-slate-800 focus:border-indigo-500 focus:ring-indigo-500"
                                                    type="number"
                                                    value="{{ $card['order']['input_value'] }}"
                                                    step="{{ $isCaseProduct ? '0.001' : '1' }}"
                                                    min="0"
                                                    data-item-id="{{ $card['id'] }}"
                                                    data-is-case-product="{{ $isCaseProduct ? '1' : '0' }}"
                                                    data-case-units="{{ $caseUnits }}"
                                                    data-suggested-value="{{ $card['order']['reset_value'] }}"
                                                    data-current-stock="{{ $card['stats']['current_stock'] }}"
                                                    data-product-peak="{{ $card['stats']['peak'] ?: 1 }}"
                                                    data-quantity-precision="{{ $card['order']['quantity_precision'] }}"
                                                    data-unit-cost="{{ $card['unit_cost'] }}"
                                                    @if(!$order->isEditable()) disabled @endif
                                                >
                                                <button type="button" class="qty-increase inline-flex h-9 w-9 items-center justify-center rounded-md border border-slate-300 text-lg font-semibold text-slate-600" data-item-id="{{ $card['id'] }}">+</button>
                                            </div>
                                        </div>
                                        <div>
                                            <p class="mb-1 text-xs uppercase tracking-wide text-slate-400">Cost impact</p>
                                            <div class="rounded border border-slate-200 px-3 py-2 text-sm font-semibold text-slate-700">
                                                <span id="cost-impact-{{ $card['id'] }}">{{ $currencySymbol }}{{ number_format($card['cost_impact'], 2) }}</span>
                                            </div>
                                        </div>
                                        <div class="flex flex-col gap-2">
                                            <button type="button" class="qty-reset rounded-md border border-slate-300 px-3 py-2 text-xs font-semibold uppercase tracking-wide text-slate-600 hover:bg-slate-100" data-item-id="{{ $card['id'] }}">
                                                Reset to suggested
                                            </button>
                                            <button type="button" class="rounded-md border border-emerald-200 bg-emerald-50 px-3 py-2 text-xs font-semibold uppercase tracking-wide text-emerald-600" data-approval-button="{{ $card['id'] }}">
                                                {{ $card['auto_approved'] ? '✓ Approved' : 'Approve' }}
                                            </button>
                                        </div>
                                        @include('orders.partials.min-stock-editor', [
                                            'contextData' => $contextData,
                                            'product' => $productModel,
                                            'item' => $itemModel,
                                            'orderSession' => $order,
                                            'isCaseProduct' => $isCaseProduct,
                                            'caseUnits' => $caseUnits,
                                        ])
                                    </div>
                                </div>
                            </div>
                        </div>
                    </article>
                @empty
                    <div class="rounded-xl border border-dashed border-slate-300 bg-white p-12 text-center text-slate-500">
                        No items ready for ordering.
                    </div>
                @endforelse
            </div>

            <div data-priority-empty-state class="hidden rounded-xl border border-slate-200 bg-white p-12 text-center text-slate-500">
                No items match the selected priority.
            </div>
        </div>
    </div>

    @once
        <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    @endonce
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const chartPayloads = @json($chartPayloads);
            const cardsContainer = document.getElementById('order-a2-card-container');
            const priorityButtons = document.querySelectorAll('.priority-filter-button');
            const emptyState = document.querySelector('[data-priority-empty-state]');
            const priorityCounts = {
                review: {{ $priorityCounts['review'] }},
                standard: {{ $priorityCounts['standard'] }},
                safe: {{ $priorityCounts['safe'] }},
                all: {{ $priorityCounts['all'] }},
            };
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
            const debounceTimers = {};
            let currentFilter = 'all';

            window.productCharts = window.productCharts || {};

            const renderChart = (payload) => {
                const ctx = document.getElementById(`order-card-chart-${payload.id}`);
                if (!ctx) {
                    return;
                }

                const labels = [...payload.labels, 'Current', 'After'];
                const sales = [...payload.sales, null, null];
                const minStock = labels.map(() => payload.minStock ?? null);

                const currentPoints = Array(labels.length).fill(null);
                currentPoints[labels.length - 2] = payload.current;
                const afterPoints = Array(labels.length).fill(null);
                afterPoints[labels.length - 1] = payload.after;

                const chart = new Chart(ctx, {
                    type: 'line',
                    data: {
                        labels,
                        datasets: [
                            {
                                type: 'line',
                                label: 'Weekly sales',
                                data: sales,
                                borderColor: payload.color,
                                borderWidth: 2,
                                tension: 0.35,
                                pointRadius: (context) => (context.dataIndex === payload.peakIndex ? 6 : 4),
                                pointBackgroundColor: (context) => (context.dataIndex === payload.peakIndex ? payload.color : '#fff'),
                                pointBorderColor: payload.color,
                                pointBorderWidth: 2,
                                spanGaps: true,
                                fill: false,
                                order: 2,
                            },
                            {
                                type: 'line',
                                label: 'Min stock target',
                                data: minStock,
                                borderColor: 'rgb(249, 115, 22)',
                                borderWidth: 2,
                                borderDash: [6, 4],
                                pointRadius: 0,
                                spanGaps: true,
                                order: 1,
                            },
                            {
                                type: 'line',
                                label: 'Current',
                                data: currentPoints,
                                borderColor: 'transparent',
                                pointBackgroundColor: 'rgb(251, 191, 36)',
                                pointBorderColor: '#fff',
                                pointBorderWidth: 2,
                                pointRadius: 5,
                                spanGaps: true,
                                order: 3,
                            },
                            {
                                type: 'line',
                                label: 'After',
                                data: afterPoints,
                                borderColor: 'transparent',
                                pointBackgroundColor: 'rgb(16, 185, 129)',
                                pointBorderColor: '#fff',
                                pointBorderWidth: 2,
                                pointRadius: 5,
                                spanGaps: true,
                                order: 3,
                            },
                        ],
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { display: false },
                            tooltip: {
                                backgroundColor: 'rgba(15, 23, 42, 0.92)',
                                titleFont: { size: 12, weight: '600' },
                                bodyFont: { size: 11 },
                                callbacks: {
                                    label: (context) => `${context.parsed.y ?? 0} units`,
                                },
                            },
                        },
                        scales: {
                            x: {
                                grid: { display: false },
                                ticks: { font: { size: 10 }, color: 'rgb(100, 116, 139)', maxRotation: 0 },
                            },
                            y: {
                                beginAtZero: true,
                                grid: { color: 'rgba(148, 163, 184, 0.2)' },
                                ticks: { font: { size: 10 }, color: 'rgb(71, 85, 105)', precision: 0 },
                            },
                        },
                    },
                });

                chart.$caseUnits = payload.caseUnits;
                chart.$isCaseProduct = payload.isCaseProduct;
                window.productCharts[payload.id] = chart;
            };

            chartPayloads.forEach(renderChart);

            const applyFilter = (filter) => {
                currentFilter = filter;
                const cards = cardsContainer?.querySelectorAll('[data-order-item-card]') ?? [];
                let visibleCount = 0;

                cards.forEach((card) => {
                    const priority = card.dataset.priority || 'standard';
                    const isVisible = filter === 'all' || priority === filter;
                    card.classList.toggle('hidden', !isVisible);
                    if (isVisible) {
                        visibleCount += 1;
                    }
                });

                if (emptyState) {
                    emptyState.classList.toggle('hidden', visibleCount !== 0);
                }

                priorityButtons.forEach((button) => {
                    const buttonFilter = button.dataset.priorityFilter || 'all';
                    const activeClasses = (button.dataset.activeClasses || '').split(' ').filter(Boolean);
                    const inactiveClasses = (button.dataset.inactiveClasses || '').split(' ').filter(Boolean);

                    button.classList.remove(...activeClasses, ...inactiveClasses);
                    if (buttonFilter === filter) {
                        button.classList.add(...activeClasses);
                        button.setAttribute('aria-pressed', 'true');
                    } else {
                        button.classList.add(...inactiveClasses);
                        button.setAttribute('aria-pressed', 'false');
                    }
                });
            };

            priorityButtons.forEach((button) => {
                button.addEventListener('click', () => {
                    applyFilter(button.dataset.priorityFilter || 'all');
                });
            });
            applyFilter('all');

            const updatePriorityCounts = () => {
                const cards = cardsContainer?.querySelectorAll('[data-order-item-card]') ?? [];
                const counts = { review: 0, standard: 0, safe: 0 };
                cards.forEach((card) => {
                    const priority = card.dataset.priority || 'standard';
                    if (counts[priority] !== undefined) {
                        counts[priority] += 1;
                    }
                });
                counts.all = cards.length;

                document.querySelectorAll('[data-priority-count="review"]').forEach((node) => { node.textContent = counts.review; });
                document.querySelectorAll('[data-priority-count="standard"]').forEach((node) => { node.textContent = counts.standard; });
                document.querySelectorAll('[data-priority-count="safe"]').forEach((node) => { node.textContent = counts.safe; });
                document.querySelectorAll('[data-priority-count="all"]').forEach((node) => { node.textContent = counts.all; });
            };

            const priorityLabels = {
                review: 'Requires review',
                standard: 'Standard',
                safe: 'Safe',
            };

            const priorityStyles = {
                review: {
                    card: ['border-rose-200', 'bg-white'],
                    pill: ['border-rose-200', 'text-rose-700', 'bg-rose-50'],
                    approval: ['bg-rose-600', 'text-white'],
                },
                standard: {
                    card: ['border-amber-200', 'bg-white'],
                    pill: ['border-amber-200', 'text-amber-700', 'bg-amber-50'],
                    approval: ['bg-amber-500', 'text-white'],
                },
                safe: {
                    card: ['border-emerald-200', 'bg-white'],
                    pill: ['border-emerald-200', 'text-emerald-700', 'bg-emerald-50'],
                    approval: ['bg-emerald-600', 'text-white'],
                },
            };

            const basePillClasses = ['rounded-full', 'border', 'px-2', 'py-0.5', 'text-xs', 'font-semibold'];
            const approvalBaseClasses = ['rounded-md', 'border', 'px-3', 'py-2', 'text-xs', 'font-semibold', 'uppercase', 'tracking-wide'];

            const showPriorityFeedback = (itemId, message, isError = false) => {
                const target = document.querySelector(`[data-priority-feedback="${itemId}"]`);
                if (!target) {
                    return;
                }
                target.textContent = message;
                target.classList.remove('hidden', 'text-rose-500', 'text-emerald-500');
                target.classList.add(isError ? 'text-rose-500' : 'text-emerald-500');
                setTimeout(() => target.classList.add('hidden'), isError ? 4000 : 1500);
            };

            const applyPriorityVisuals = (itemId, priority, autoApproved = false) => {
                const styles = priorityStyles[priority] || priorityStyles.standard;
                const card = document.querySelector(`[data-order-item-id="${itemId}"]`);
                if (card) {
                    card.dataset.priority = priority;
                    card.classList.remove('border-rose-200', 'border-amber-200', 'border-emerald-200');
                    card.classList.add(...styles.card);
                }

                const pill = document.querySelector(`[data-priority-pill="${itemId}"]`);
                if (pill) {
                    pill.className = '';
                    pill.classList.add(...basePillClasses, ...styles.pill);
                    pill.textContent = priorityLabels[priority] || priorityLabels.standard;
                }

                const approvalButton = document.querySelector(`[data-approval-button="${itemId}"]`);
                if (approvalButton) {
                    approvalButton.className = '';
                    approvalButton.classList.add(...approvalBaseClasses, ...styles.approval);
                    approvalButton.textContent = autoApproved ? '✓ Approved' : 'Approve';
                }
            };

            const persistPriority = (itemId, priority, selectEl) => {
                selectEl.disabled = true;
                fetch(`/order-items/${itemId}/priority`, {
                    method: 'PATCH',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({ priority, apply_to_product: true }),
                })
                    .then((response) => {
                        if (!response.ok) {
                            return response.json().catch(() => ({})).then((error) => {
                                throw new Error(error.message || 'Failed to update priority');
                            });
                        }

                        return response.json();
                    })
                    .then((data) => {
                        if (!data.success) {
                            throw new Error(data.error || 'Failed to update priority');
                        }

                        const updatedPriority = data.item?.review_priority || priority;
                        const autoApproved = Boolean(data.item?.auto_approved);
                        selectEl.dataset.currentPriority = updatedPriority;
                        selectEl.value = updatedPriority;
                        selectEl.classList.add('border-green-500');
                        setTimeout(() => selectEl.classList.remove('border-green-500'), 800);

                        applyPriorityVisuals(itemId, updatedPriority, autoApproved);
                        updatePriorityCounts();
                        applyFilter(currentFilter);
                        showPriorityFeedback(itemId, data.message || 'Priority saved');
                    })
                    .catch((error) => {
                        console.error(error);
                        selectEl.classList.add('border-rose-500');
                        showPriorityFeedback(itemId, error.message, true);
                        setTimeout(() => selectEl.classList.remove('border-rose-500'), 1200);
                        selectEl.value = selectEl.dataset.currentPriority || selectEl.value;
                    })
                    .finally(() => {
                        selectEl.disabled = false;
                    });
            };

            const currencySymbol = @json($currencySymbol);

            const updateStockVisuals = (itemId) => {
                const input = document.getElementById(`qty-input-${itemId}`);
                if (!input) {
                    return;
                }
                const isCaseProduct = input.dataset.isCaseProduct === '1';
                const caseUnits = parseFloat(input.dataset.caseUnits) || 1;
                const currentStock = parseFloat(input.dataset.currentStock) || 0;
                const quantityPrecision = parseInt(input.dataset.quantityPrecision || (isCaseProduct ? 3 : 0), 10);
                const unitCost = parseFloat(input.dataset.unitCost) || 0;
                const rawValue = parseFloat(input.value) || 0;
                const orderedUnits = isCaseProduct ? rawValue * caseUnits : rawValue;
                const afterStock = currentStock + orderedUnits;

                const afterStockLabel = document.getElementById(`after-stock-value-${itemId}`);
                if (afterStockLabel) {
                    afterStockLabel.textContent = Math.round(afterStock).toLocaleString();
                }

                const costImpactLabel = document.getElementById(`cost-impact-${itemId}`);
                if (costImpactLabel) {
                    costImpactLabel.textContent = `${currencySymbol}${(orderedUnits * unitCost).toFixed(2)}`;
                }

                const chart = window.productCharts ? window.productCharts[itemId] : null;
                if (chart) {
                    const labelsLength = chart.data.labels.length;
                    const afterDataset = chart.data.datasets.find((dataset) => dataset.label === 'After');
                    const currentDataset = chart.data.datasets.find((dataset) => dataset.label === 'Current');
                    if (afterDataset) {
                        afterDataset.data[labelsLength - 1] = afterStock;
                    }
                    if (currentDataset) {
                        currentDataset.data[labelsLength - 2] = currentStock;
                    }
                    chart.update('none');
                }
            };

            const saveQuantityToServer = (itemId) => {
                const input = document.getElementById(`qty-input-${itemId}`);
                if (!input) {
                    return;
                }
                const isCaseProduct = input.dataset.isCaseProduct === '1';
                const payloadKey = isCaseProduct ? 'cases' : 'quantity';
                const payloadValue = parseFloat(input.value) || 0;
                const endpoint = isCaseProduct
                    ? `/order-items/${itemId}/cases`
                    : `/order-items/${itemId}/quantity`;

                input.classList.add('border-blue-400');
                input.disabled = true;

                fetch(endpoint, {
                    method: 'PATCH',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({ [payloadKey]: payloadValue }),
                })
                    .then((response) => {
                        if (!response.ok) {
                            return response.json().catch(() => ({})).then((error) => {
                                throw new Error(error.message || 'Failed to update quantity');
                            });
                        }

                        return response.json();
                    })
                    .then((data) => {
                        if (!data.success) {
                            throw new Error(data.error || 'Failed to update quantity');
                        }
                        input.classList.remove('border-blue-400');
                        input.classList.add('border-emerald-400');
                        setTimeout(() => input.classList.remove('border-emerald-400'), 600);
                    })
                    .catch((error) => {
                        console.error(error);
                        input.classList.remove('border-blue-400');
                        input.classList.add('border-rose-500');
                        setTimeout(() => input.classList.remove('border-rose-500'), 1200);
                    })
                    .finally(() => {
                        input.disabled = false;
                    });
            };

            const debouncedSave = (itemId) => {
                if (debounceTimers[itemId]) {
                    clearTimeout(debounceTimers[itemId]);
                }
                debounceTimers[itemId] = setTimeout(() => {
                    saveQuantityToServer(itemId);
                }, 700);
            };

            document.querySelectorAll('.priority-selector').forEach((select) => {
                applyPriorityVisuals(select.dataset.itemId, select.dataset.currentPriority);
                select.addEventListener('change', () => {
                    const itemId = select.dataset.itemId;
                    const currentValue = select.dataset.currentPriority || select.value;
                    if (itemId && select.value !== currentValue) {
                        persistPriority(itemId, select.value, select);
                    }
                });
            });

            document.querySelectorAll('.qty-input').forEach((input) => {
                const itemId = input.dataset.itemId;
                updateStockVisuals(itemId);
                input.addEventListener('input', () => {
                    updateStockVisuals(itemId);
                    debouncedSave(itemId);
                });
            });

            document.querySelectorAll('.qty-increase').forEach((button) => {
                button.addEventListener('click', () => {
                    const itemId = button.dataset.itemId;
                    const input = document.getElementById(`qty-input-${itemId}`);
                    if (!input || input.disabled) {
                        return;
                    }
                    const step = parseFloat(input.step || '1');
                    input.value = (parseFloat(input.value) || 0) + step;
                    updateStockVisuals(itemId);
                    saveQuantityToServer(itemId);
                });
            });

            document.querySelectorAll('.qty-decrease').forEach((button) => {
                button.addEventListener('click', () => {
                    const itemId = button.dataset.itemId;
                    const input = document.getElementById(`qty-input-${itemId}`);
                    if (!input || input.disabled) {
                        return;
                    }
                    const step = parseFloat(input.step || '1');
                    input.value = Math.max(0, (parseFloat(input.value) || 0) - step);
                    updateStockVisuals(itemId);
                    saveQuantityToServer(itemId);
                });
            });

            document.querySelectorAll('.qty-reset').forEach((button) => {
                button.addEventListener('click', () => {
                    const itemId = button.dataset.itemId;
                    const input = document.getElementById(`qty-input-${itemId}`);
                    if (!input || input.disabled) {
                        return;
                    }
                    input.value = input.dataset.suggestedValue || '0';
                    updateStockVisuals(itemId);
                    saveQuantityToServer(itemId);
                });
            });
        });
    </script>
</x-admin-layout>
