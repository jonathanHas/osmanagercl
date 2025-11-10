@php
    $sampleItems = [
        [
            'name' => 'Vico Wexford Strawberry Yogurt',
            'code' => 'VWS-001',
            'supplier' => 'Vico Foods',
            'category' => 'Refrigerated • Yogurt',
            'order' => 9,
            'current' => 3,
            'after' => 12,
            'avg' => 5.3,
            'peak' => 9.0,
            'total' => 42,
            'min_stock' => 10,
            'unit_cost' => 6.40,
            'weekly_sales' => [2, 6, 8, 9, 7, 5, 3, 2],
        ],
        [
            'name' => 'Brindisa Manchego Semi Curado',
            'code' => 'BMC-032',
            'supplier' => 'Brindisa',
            'category' => 'Cheese • Hard',
            'order' => 8,
            'current' => 14,
            'after' => 22,
            'avg' => 7.1,
            'peak' => 11.0,
            'total' => 58,
            'min_stock' => 18,
            'unit_cost' => 11.80,
            'weekly_sales' => [4, 5, 9, 11, 7, 8, 6, 8],
        ],
    ];

    $labelsFor = function (array $weeks) {
        $total = count($weeks);
        $labels = [];
        foreach (array_keys($weeks) as $i) {
            $labels[] = now()->subWeeks($total - 1 - $i)->format('d M');
        }

        return $labels;
    };

    $chartPayload = collect($sampleItems)->map(function ($item) use ($labelsFor) {
        $maxWeekly = max($item['weekly_sales']);
        $peakIndex = array_search($maxWeekly, $item['weekly_sales'], true);
        if ($peakIndex === false) {
            $peakIndex = 0;
        }

        return [
            'labels' => $labelsFor($item['weekly_sales']),
            'sales' => $item['weekly_sales'],
            'minStock' => $item['min_stock'],
            'current' => $item['current'],
            'after' => $item['after'],
            'total' => $item['total'],
            'peak' => $maxWeekly,
            'peakIndex' => $peakIndex,
        ];
    });
@endphp

<style>
    @media (min-width: 768px) {
        .layout-balanced .order-controls-col {
            flex: 0 0 10rem;
            max-width: 10rem;
        }
    }
    @media (min-width: 1024px) {
        .layout-balanced .order-controls-col {
            flex: 0 0 11rem;
            max-width: 11rem;
        }
    }
    .layout-balanced .chart-canvas-full {
        width: 100% !important;
        height: 100% !important;
    }
</style>

<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-2">
            <h1 class="text-2xl font-semibold text-slate-900">Order Layout Experiments · Round 2</h1>
            <p class="text-sm text-slate-600">Two new variations that keep context information stable while bringing the order controls forward.</p>
        </div>
    </x-slot>

    <div class="max-w-6xl mx-auto px-6 pb-12 space-y-12">
        {{-- Variation A --}}
        <section class="space-y-4">
            <header class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 class="text-xl font-semibold text-slate-900">Variation A · Split Shell with Order Focus</h2>
                    <p class="text-sm text-slate-600">Fixed meta data sits in the dark column; order input and trend stay in the light workspace.</p>
                </div>
                <span class="self-start rounded-full bg-indigo-100 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-indigo-700">Concept</span>
            </header>

            @php
                $primaryItem = $sampleItems[0];
                $primaryChart = $chartPayload[0];
            @endphp

            <article class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                <div class="md:flex md:items-stretch">
                    <aside class="hidden bg-slate-900/95 p-6 text-slate-200 md:block md:w-60 md:flex-shrink-0">
                        <div class="flex h-full flex-col">
                            <div class="pb-5">
                                <p class="text-[11px] uppercase tracking-[0.2em] text-slate-500">Product</p>
                                <h3 class="mt-2 text-base font-semibold text-white leading-snug">{{ $primaryItem['name'] }}</h3>
                                <p class="mt-1 text-xs text-slate-400 leading-relaxed">{{ $primaryItem['code'] }} • {{ $primaryItem['supplier'] }} • {{ $primaryItem['category'] }}</p>
                            </div>

                            <dl class="space-y-4 border-t border-slate-800/60 pt-5 text-xs text-slate-300">
                                <div>
                                    <dt class="text-slate-500 uppercase tracking-wide text-[10px]">Min stock</dt>
                                    <dd class="mt-1 text-sm font-semibold text-orange-200">{{ $primaryItem['min_stock'] }}</dd>
                                </div>
                                <div>
                                    <dt class="text-slate-500 uppercase tracking-wide text-[10px]">Unit cost</dt>
                                    <dd class="mt-1 text-sm font-semibold text-emerald-200">£{{ number_format($primaryItem['unit_cost'], 2) }}</dd>
                                </div>
                                <div>
                                    <dt class="text-slate-500 uppercase tracking-wide text-[10px]">Total sold (8w)</dt>
                                    <dd class="mt-1 text-sm font-semibold text-slate-100">{{ $primaryItem['total'] }}</dd>
                                </div>
                            </dl>

                            <div class="mt-auto space-y-2 border-t border-slate-800/60 pt-5 text-xs">
                                <p class="text-slate-500 uppercase tracking-wide text-[10px]">Admin actions</p>
                                <button class="w-full rounded-lg border border-slate-700/40 bg-slate-800/60 px-3 py-2 font-semibold text-slate-100 transition hover:bg-slate-700/80">Flag for review</button>
                                <button class="w-full rounded-lg border border-slate-700/40 bg-slate-800/60 px-3 py-2 font-semibold text-slate-100 transition hover:bg-slate-700/80">Adjust priority</button>
                            </div>
                        </div>
                    </aside>

                    <div class="p-6 md:flex-1 md:pl-8">
                        <div class="mt-5 flex flex-col gap-6 md:flex md:flex-row md:items-start md:gap-6">
                            <div class="rounded-lg border border-slate-200 bg-slate-50/60 p-4 md:flex-1">
                                <div class="grid gap-4 text-xs font-semibold uppercase tracking-wide text-slate-500 sm:grid-cols-5">
                                    <div>
                                        <div>Total 8w</div>
                                        <div class="text-lg font-semibold text-slate-800">{{ $primaryItem['total'] }}</div>
                                    </div>
                                    <div>
                                        <div>Average</div>
                                        <div class="text-lg font-semibold text-slate-800">{{ number_format($primaryItem['avg'], 1) }}</div>
                                    </div>
                                    <div>
                                        <div>Peak</div>
                                        <div class="text-lg font-semibold text-slate-800">{{ number_format($primaryItem['peak'], 1) }}</div>
                                    </div>
                                    <div>
                                        <div>Current</div>
                                        <div class="text-lg font-semibold text-rose-600">{{ $primaryItem['current'] }}</div>
                                    </div>
                                    <div>
                                        <div>After order</div>
                                        <div class="text-lg font-semibold text-emerald-600">{{ $primaryItem['after'] }}</div>
                                    </div>
                                </div>
                                <div class="mt-4 h-64">
                                    <canvas id="layout2_v1_chart" aria-label="Weekly sales trend"></canvas>
                                </div>
                                <div class="mt-3 text-xs font-semibold uppercase tracking-wide text-orange-600">
                                    Min stock target · {{ $primaryItem['min_stock'] }} units
                                </div>
                            </div>
                            <div class="flex w-full flex-col gap-3 md:w-full order-controls-col">
                                <div>
                                    <div class="mb-1 flex items-baseline justify-between text-xs uppercase tracking-wide text-slate-400">
                                        <span>Order units</span>
                                        <span class="text-xs font-medium text-slate-500">Suggested <span class="font-normal text-slate-500">{{ $primaryItem['order'] }}</span></span>
                                    </div>
                                    <input id="mock-order-input" type="number" value="{{ $primaryItem['order'] }}" class="w-full rounded-md border border-slate-300 px-2.5 py-1.5 text-base font-semibold text-slate-800 focus:border-indigo-500 focus:ring-indigo-500">
                                </div>
                                <div>
                                    <p class="mb-1 text-xs uppercase tracking-wide text-slate-400">Min stock override</p>
                                    <input type="number" value="{{ $primaryItem['min_stock'] }}" class="w-full rounded-md border border-slate-300 px-2.5 py-1.5 text-sm font-semibold text-slate-800 focus:border-indigo-500 focus:ring-indigo-500">
                                </div>
                                <div class="space-y-2 text-sm text-slate-600">
                                    <div class="rounded border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-500">
                                        Cost impact: <span class="text-slate-700">£{{ number_format($primaryItem['order'] * $primaryItem['unit_cost'], 2) }}</span>
                                    </div>
                                    <button class="w-full rounded border border-slate-300 px-3 py-2 text-xs font-semibold uppercase tracking-wide text-slate-600 hover:bg-slate-100">
                                        Reset to suggested
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                        <div class="md:hidden border-t border-slate-200 bg-slate-900/95 p-5 text-slate-200">
                    <div>
                        <p class="text-xs uppercase tracking-wide text-slate-400">Product</p>
                        <h3 class="mt-1 text-lg font-semibold text-white">{{ $primaryItem['name'] }}</h3>
                        <p class="text-sm text-slate-400">{{ $primaryItem['code'] }} • {{ $primaryItem['supplier'] }} • {{ $primaryItem['category'] }}</p>
                    </div>
                    <dl class="mt-4 grid grid-cols-3 gap-3 text-xs text-slate-300">
                        <div>
                            <dt>Min stock</dt>
                            <dd class="font-semibold text-orange-200">{{ $primaryItem['min_stock'] }}</dd>
                        </div>
                        <div>
                            <dt>Unit cost</dt>
                            <dd class="font-semibold text-emerald-200">£{{ number_format($primaryItem['unit_cost'], 2) }}</dd>
                        </div>
                        <div>
                            <dt>Total sold</dt>
                            <dd class="font-semibold text-slate-100">{{ $primaryItem['total'] }}</dd>
                        </div>
                    </dl>
                    <div class="mt-4 grid grid-cols-2 gap-2 text-xs">
                        <button class="rounded-md border border-slate-700/70 px-3 py-2 font-semibold text-slate-200 hover:bg-slate-800">Flag for review</button>
                        <button class="rounded-md border border-slate-700/70 px-3 py-2 font-semibold text-slate-200 hover:bg-slate-800">Adjust priority</button>
                    </div>
                </div>
            </article>
        </section>

{{-- Variation B --}}
        <section class="space-y-4">
            <header class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 class="text-xl font-semibold text-slate-900">Variation B · Compact Header with Inline Controls</h2>
                    <p class="text-sm text-slate-600">Metrics in a single strip, order adjustments beneath, chart provides the trend context.</p>
                </div>
                <span class="self-start rounded-full bg-emerald-100 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-emerald-700">Concept</span>
            </header>

            @php
                $secondaryItem = $sampleItems[1];
                $secondaryChart = $chartPayload[1];
            @endphp

            <article class="rounded-xl border border-slate-200 bg-white shadow-sm layout-balanced">
                <div class="p-6 space-y-5">
                    <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                        <div>
                            <h3 class="text-lg font-semibold text-slate-900">{{ $secondaryItem['name'] }}</h3>
                            <p class="text-sm text-slate-500">{{ $secondaryItem['code'] }} • {{ $secondaryItem['supplier'] }} • {{ $secondaryItem['category'] }}</p>
                        </div>
                        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4 text-xs font-semibold uppercase tracking-wide text-slate-500">
                            <div class="flex items-center gap-2">
                                <span>Total</span>
                                <span class="text-slate-700 text-sm font-bold">{{ $secondaryItem['total'] }}</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <span>Avg / Peak</span>
                                <span class="text-slate-700 text-sm font-bold">{{ number_format($secondaryItem['avg'], 1) }} / {{ number_format($secondaryItem['peak'], 1) }}</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <span>Current</span>
                                <span class="text-rose-600 text-sm font-bold">{{ $secondaryItem['current'] }}</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <span>After Order</span>
                                <span class="text-emerald-600 text-sm font-bold">{{ $secondaryItem['after'] }}</span>
                            </div>
                        </div>
                    </div>

                    <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                        <div class="flex items-center gap-3">
                            <label for="variation-b-input" class="text-xs uppercase tracking-wide text-slate-400">Order units</label>
                            <input id="variation-b-input" type="number" value="{{ $secondaryItem['order'] }}" class="w-24 rounded-md border border-slate-300 px-3 py-1.5 text-sm font-semibold text-slate-800 focus:border-indigo-500 focus:ring-indigo-500">
                            <button class="rounded-md bg-indigo-500 px-4 py-1.5 text-sm font-semibold text-white hover:bg-indigo-600">Save</button>
                        </div>
                        <div class="flex gap-2 text-xs font-semibold uppercase tracking-wide">
                            <button class="rounded border border-slate-300 px-3 py-1 text-slate-600 hover:bg-slate-100">-10%</button>
                            <button class="rounded border border-slate-300 px-3 py-1 text-slate-600 hover:bg-slate-100">+1 case</button>
                            <button class="rounded border border-slate-300 px-3 py-1 text-slate-600 hover:bg-slate-100">Zero out</button>
                        </div>
                    </div>

                    <div class="grid gap-5 lg:grid-cols-[2fr,1fr]">
                        <div class="rounded-lg border border-slate-200 bg-slate-50/60 p-4">
                            <div class="h-48">
                                <canvas id="layout2_v2_chart" aria-label="Weekly sales trend"></canvas>
                            </div>
                        </div>

                        <div class="space-y-3 text-sm text-slate-600">
                            <div class="rounded-lg border border-slate-200 bg-white p-4 space-y-2">
                                <p class="text-xs uppercase tracking-wide text-slate-400">Actions</p>
                                <button class="w-full rounded-md bg-emerald-500 py-2 text-sm font-semibold text-white hover:bg-emerald-600">Approve suggestion</button>
                                <button class="w-full rounded-md border border-slate-300 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-100">Request review</button>
                            </div>

                            <div class="rounded-lg border border-slate-200 bg-white p-4 space-y-1">
                                <p class="text-xs uppercase tracking-wide text-slate-400">Summary</p>
                                <p>Total order value: £{{ number_format($secondaryItem['order'] * $secondaryItem['unit_cost'], 2) }}</p>
                                <p>Min stock: {{ $secondaryItem['min_stock'] }} units</p>
                            </div>
                        </div>
                    </div>
                </div>
            </article>
        </section>

        {{-- Variation A1 --}}
        <section class="space-y-4">
            <header class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 class="text-xl font-semibold text-slate-900">Variation A1 · Fixed Controls, A-style Metrics</h2>
                    <p class="text-sm text-slate-600">Variation A visuals with a fixed-width control stack to prevent initial stretching.</p>
                </div>
                <span class="self-start rounded-full bg-indigo-100 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-indigo-700">Prototype</span>
            </header>

            @php
                $a1Item = $sampleItems[0];
                $a1Chart = $chartPayload[0];
            @endphp

            <article class="rounded-xl border border-slate-200 bg-white shadow-sm layout-balanced">
                <div class="p-6 md:flex md:items-start md:gap-8">
                    <div class="rounded-lg border border-slate-200 bg-slate-50/60 p-4 md:flex-1 md:min-w-0">
                        <div class="grid gap-4 text-xs font-semibold uppercase tracking-wide text-slate-500 sm:grid-cols-5">
                            <div>
                                <div>Total 8w</div>
                                <div class="text-lg font-semibold text-slate-800">{{ $a1Item['total'] }}</div>
                            </div>
                            <div>
                                <div>Average</div>
                                <div class="text-lg font-semibold text-slate-800">{{ number_format($a1Item['avg'], 1) }}</div>
                            </div>
                            <div>
                                <div>Peak</div>
                                <div class="text-lg font-semibold text-slate-800">{{ number_format($a1Item['peak'], 1) }}</div>
                            </div>
                            <div>
                                <div>Current</div>
                                <div class="text-lg font-semibold text-rose-600">{{ $a1Item['current'] }}</div>
                            </div>
                            <div>
                                <div>After order</div>
                                <div class="text-lg font-semibold text-emerald-600">{{ $a1Item['after'] }}</div>
                            </div>
                        </div>
                        <div class="mt-4 h-64">
                            <canvas id="layout2_v1a_chart" aria-label="Weekly sales trend"></canvas>
                        </div>
                        <div class="mt-3 text-xs font-semibold uppercase tracking-wide text-orange-600">
                            Min stock target · {{ $a1Item['min_stock'] }} units
                        </div>
                    </div>

                    <div class="order-controls-col flex w-full flex-col gap-3" style="flex: 0 0 clamp(9rem, 11vw, 11.5rem);">
                        <div>
                            <div class="mb-1 flex items-baseline justify-between text-xs uppercase tracking-wide text-slate-400">
                                <span>Order units</span>
                                <span class="text-xs font-medium text-slate-500">Suggested <span class="font-normal text-slate-500">{{ $a1Item['order'] }}</span></span>
                            </div>
                            <input type="number" value="{{ $a1Item['order'] }}" class="w-full rounded-md border border-slate-300 px-2.5 py-1.5 text-base font-semibold text-slate-800 focus:border-indigo-500 focus:ring-indigo-500">
                        </div>
                        <div>
                            <p class="mb-1 text-xs uppercase tracking-wide text-slate-400">Min stock override</p>
                            <input type="number" value="{{ $a1Item['min_stock'] }}" class="w-full rounded-md border border-slate-300 px-2.5 py-1.5 text-sm font-semibold text-slate-800 focus:border-indigo-500 focus:ring-indigo-500">
                        </div>
                        <div class="space-y-2 text-xs font-semibold uppercase tracking-wide text-slate-500">
                            <div class="rounded border border-slate-200 px-3 py-2">
                                Cost impact <span class="font-normal normal-case text-slate-700">£{{ number_format($a1Item['order'] * $a1Item['unit_cost'], 2) }}</span>
                            </div>
                            <button class="w-full rounded border border-slate-300 px-3 py-2 text-[11px] font-semibold uppercase tracking-wide text-slate-600 hover:bg-slate-100">
                                Reset to suggested
                            </button>
                        </div>
                    </div>
                </div>
            </article>
        </section>

        {{-- Variation C --}}
        <section class="space-y-4">
            <header class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 class="text-xl font-semibold text-slate-900">Variation C · Balanced Controls</h2>
                    <p class="text-sm text-slate-600">Tighter right column with stacked controls while keeping the chart dominant.</p>
                </div>
                <span class="self-start rounded-full bg-blue-100 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-blue-700">Concept</span>
            </header>

            @php
                $balancedItem = $sampleItems[0];
            @endphp

            <article class="rounded-xl border border-slate-200 bg-white shadow-sm">
                <div class="p-6 space-y-6 md:flex md:items-start md:gap-8">
                    <div class="rounded-lg border border-slate-200 bg-slate-50/60 p-4 md:flex-1 md:min-w-0">
                        <div class="grid gap-4 text-xs font-semibold uppercase tracking-wide text-slate-500 sm:grid-cols-5">
                            <div>
                                <div>Total 8w</div>
                                <div class="text-lg font-semibold text-slate-800">{{ $balancedItem['total'] }}</div>
                            </div>
                            <div>
                                <div>Average</div>
                                <div class="text-lg font-semibold text-slate-800">{{ number_format($balancedItem['avg'], 1) }}</div>
                            </div>
                            <div>
                                <div>Peak</div>
                                <div class="text-lg font-semibold text-slate-800">{{ number_format($balancedItem['peak'], 1) }}</div>
                            </div>
                            <div>
                                <div>Current</div>
                                <div class="text-lg font-semibold text-rose-600">{{ $balancedItem['current'] }}</div>
                            </div>
                            <div>
                                <div>After order</div>
                                <div class="text-lg font-semibold text-emerald-600">{{ $balancedItem['after'] }}</div>
                            </div>
                        </div>
                        <div class="mt-4 h-64">
                            <canvas id="layout2_v3_chart" aria-label="Weekly sales trend"></canvas>
                        </div>
                        <div class="mt-3 text-xs font-semibold uppercase tracking-wide text-orange-600">
                            Min stock target · {{ $balancedItem['min_stock'] }} units
                        </div>
                    </div>

                    <div class="flex w-full flex-col gap-3 order-controls-col">
                        <div>
                            <div class="mb-1 flex items-baseline justify-between text-xs uppercase tracking-wide text-slate-400">
                                <span>Order units</span>
                                <span class="text-xs font-medium text-slate-500">Suggested <span class="font-normal text-slate-500">{{ $balancedItem['order'] }}</span></span>
                            </div>
                            <input type="number" value="{{ $balancedItem['order'] }}" class="w-full rounded-md border border-slate-300 px-2.5 py-1.5 text-base font-semibold text-slate-800 focus:border-indigo-500 focus:ring-indigo-500">
                        </div>
                        <div>
                            <p class="mb-1 text-xs uppercase tracking-wide text-slate-400">Min stock override</p>
                            <input type="number" value="{{ $balancedItem['min_stock'] }}" class="w-full rounded-md border border-slate-300 px-2.5 py-1.5 text-sm font-semibold text-slate-800 focus:border-indigo-500 focus:ring-indigo-500">
                        </div>
                        <div class="space-y-2 text-xs font-semibold uppercase tracking-wide text-slate-500">
                            <div class="rounded border border-slate-200 px-3 py-2">
                                Cost impact <span class="font-normal normal-case text-slate-700">£{{ number_format($balancedItem['order'] * $balancedItem['unit_cost'], 2) }}</span>
                            </div>
                            <button class="w-full rounded border border-slate-300 px-3 py-2 text-[11px] font-semibold uppercase tracking-wide text-slate-600 hover:bg-slate-100">
                                Reset to suggested
                            </button>
                        </div>
                    </div>
                </div>
            </article>
        </section>

        {{-- Variation D --}}
        <section class="space-y-4">
            <header class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 class="text-xl font-semibold text-slate-900">Variation D · Fixed Width Controls</h2>
                    <p class="text-sm text-slate-600">Controls clamped to 12rem while the chart canvas stretches to full width on load.</p>
                </div>
                <span class="self-start rounded-full bg-violet-100 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-violet-700">Prototype</span>
            </header>

            @php
                $fixedItem = $sampleItems[1];
            @endphp

            <article class="rounded-xl border border-slate-200 bg-white shadow-sm layout-balanced">
                <div class="p-6 md:flex md:items-start md:gap-8">
                    <div class="rounded-lg border border-slate-200 bg-slate-50/60 p-4 md:flex-1 md:min-w-0">
                        <div class="grid gap-4 text-xs font-semibold uppercase tracking-wide text-slate-500 sm:grid-cols-5">
                            <div>
                                <div>Total 8w</div>
                                <div class="text-lg font-semibold text-slate-800">{{ $fixedItem['total'] }}</div>
                            </div>
                            <div>
                                <div>Average</div>
                                <div class="text-lg font-semibold text-slate-800">{{ number_format($fixedItem['avg'], 1) }}</div>
                            </div>
                            <div>
                                <div>Peak</div>
                                <div class="text-lg font-semibold text-slate-800">{{ number_format($fixedItem['peak'], 1) }}</div>
                            </div>
                            <div>
                                <div>Current</div>
                                <div class="text-lg font-semibold text-rose-600">{{ $fixedItem['current'] }}</div>
                            </div>
                            <div>
                                <div>After order</div>
                                <div class="text-lg font-semibold text-emerald-600">{{ $fixedItem['after'] }}</div>
                            </div>
                        </div>
                        <div class="mt-4 h-64">
                            <canvas id="layout2_v4_chart" class="chart-canvas-full" aria-label="Weekly sales trend"></canvas>
                        </div>
                        <div class="mt-3 text-xs font-semibold uppercase tracking-wide text-orange-600">
                            Min stock target · {{ $fixedItem['min_stock'] }} units
                        </div>
                    </div>

                    <div class="order-controls-col flex w-full flex-col gap-3" style="flex: 0 0 clamp(9rem, 12vw, 12rem);">
                        <div>
                            <div class="mb-1 flex items-baseline justify-between text-xs uppercase tracking-wide text-slate-400">
                                <span>Order units</span>
                                <span class="text-xs font-medium text-slate-500">Suggested <span class="font-normal text-slate-500">{{ $fixedItem['order'] }}</span></span>
                            </div>
                            <input type="number" value="{{ $fixedItem['order'] }}" class="w-full rounded-md border border-slate-300 px-2 py-1.5 text-base font-semibold text-slate-800 focus:border-indigo-500 focus:ring-indigo-500">
                        </div>
                        <div>
                            <p class="mb-1 text-xs uppercase tracking-wide text-slate-400">Min stock override</p>
                            <input type="number" value="{{ $fixedItem['min_stock'] }}" class="w-full rounded-md border border-slate-300 px-2 py-1.5 text-sm font-semibold text-slate-800 focus:border-indigo-500 focus:ring-indigo-500">
                        </div>
                        <div class="space-y-2 text-xs font-semibold uppercase tracking-wide text-slate-500">
                            <div class="rounded border border-slate-200 px-3 py-2">
                                Cost impact <span class="font-normal normal-case text-slate-700">£{{ number_format($fixedItem['order'] * $fixedItem['unit_cost'], 2) }}</span>
                            </div>
                            <button class="w-full rounded border border-slate-300 px-3 py-2 text-[11px] font-semibold uppercase tracking-wide text-slate-600 hover:bg-slate-100">
                                Reset to suggested
                            </button>
                        </div>
                    </div>
                </div>
            </article>
        </section>

        {{-- Variation E --}}
        <section class="space-y-4">
            <header class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 class="text-xl font-semibold text-slate-900">Variation E · CSS Mini Chart</h2>
                    <p class="text-sm text-slate-600">Uses pure CSS bars so the layout is independent of Chart.js sizing quirks.</p>
                </div>
                <span class="self-start rounded-full bg-cyan-100 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-cyan-700">Exploration</span>
            </header>

            @php
                $cssItem = $sampleItems[0];
                $maxSales = max($cssItem['weekly_sales']);
            @endphp

            <article class="rounded-xl border border-slate-200 bg-white shadow-sm">
                <div class="p-6 md:grid md:grid-cols-[minmax(0,1fr)_clamp(9rem,11vw,11rem)] md:gap-6">
                    <div class="rounded-lg border border-slate-200 bg-slate-50/60 p-4 md:flex-1 md:min-w-0">
                        <div class="grid gap-4 text-xs font-semibold uppercase tracking-wide text-slate-500 sm:grid-cols-5">
                            <div>
                                <div>Total 8w</div>
                                <div class="text-lg font-semibold text-slate-800">{{ $cssItem['total'] }}</div>
                            </div>
                            <div>
                                <div>Average</div>
                                <div class="text-lg font-semibold text-slate-800">{{ number_format($cssItem['avg'], 1) }}</div>
                            </div>
                            <div>
                                <div>Peak</div>
                                <div class="text-lg font-semibold text-slate-800">{{ number_format($cssItem['peak'], 1) }}</div>
                            </div>
                            <div>
                                <div>Current</div>
                                <div class="text-lg font-semibold text-rose-600">{{ $cssItem['current'] }}</div>
                            </div>
                            <div>
                                <div>After order</div>
                                <div class="text-lg font-semibold text-emerald-600">{{ $cssItem['after'] }}</div>
                            </div>
                        </div>
                        <div class="mt-4">
                            <div class="relative rounded-xl border border-slate-200 bg-white p-4">
                                <div class="flex items-end gap-2" style="height: 220px;">
                                    @foreach($cssItem['weekly_sales'] as $index => $units)
                                        @php
                                            $height = $maxSales > 0 ? ($units / $maxSales) * 100 : 0;
                                            $label = now()->subWeeks(count($cssItem['weekly_sales']) - 1 - $index)->format('d M');
                                        @endphp
                                        <div class="flex h-full w-6 flex-col items-center justify-end">
                                            <div class="w-full rounded-t bg-indigo-300" style="height: {{ $height }}%;"></div>
                                            <span class="mt-2 text-[10px] text-slate-500">{{ $label }}</span>
                                        </div>
                                    @endforeach
                                </div>
                                <div class="absolute left-4 right-4 top-[30%] border-t border-dashed border-orange-400"></div>
                                <div class="mt-4 text-xs font-semibold uppercase tracking-wide text-orange-600">Min stock target · {{ $cssItem['min_stock'] }} units</div>
                            </div>
                        </div>
                    </div>

                    <div class="order-controls-col flex w-full flex-col gap-3">
                        <div>
                            <div class="mb-1 flex items-baseline justify-between text-xs uppercase tracking-wide text-slate-400">
                                <span>Order units</span>
                                <span class="text-xs font-medium text-slate-500">Suggested <span class="font-normal text-slate-500">{{ $cssItem['order'] }}</span></span>
                            </div>
                            <input type="number" value="{{ $cssItem['order'] }}" class="w-full rounded-md border border-slate-300 px-2 py-1.5 text-base font-semibold text-slate-800 focus:border-indigo-500 focus:ring-indigo-500">
                        </div>
                        <div>
                            <p class="mb-1 text-xs uppercase tracking-wide text-slate-400">Min stock override</p>
                            <input type="number" value="{{ $cssItem['min_stock'] }}" class="w-full rounded-md border border-slate-300 px-2 py-1.5 text-sm font-semibold text-slate-800 focus:border-indigo-500 focus:ring-indigo-500">
                        </div>
                        <div class="space-y-2 text-xs font-semibold uppercase tracking-wide text-slate-500">
                            <div class="rounded border border-slate-200 px-3 py-2">
                                Cost impact <span class="font-normal normal-case text-slate-700">£{{ number_format($cssItem['order'] * $cssItem['unit_cost'], 2) }}</span>
                            </div>
                            <button class="w-full rounded border border-slate-300 px-3 py-2 text-[11px] font-semibold uppercase tracking-wide text-slate-600 hover:bg-slate-100">
                                Reset to suggested
                            </button>
                        </div>
                    </div>
                </div>
            </article>
        </section>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            if (window.orderLayoutMockChartsV2Initialised) {
                return;
            }

            window.orderLayoutMockChartsV2Initialised = true;

            const charts = @json($chartPayload);
            const chartInstances = [];

            const makeBaseOptions = () => ({
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: 'rgba(15, 23, 42, 0.92)',
                        titleFont: { size: 12, weight: '600' },
                        bodyFont: { size: 11 },
                        callbacks: {
                            label: (ctx) => `${ctx.parsed.y ?? 0} units`,
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
            });

            const renderChart = (canvasId, config, palette) => {
                const el = document.getElementById(canvasId);
                if (!el) return;

                const labels = [...config.labels, 'Current', 'After'];
                const sales = [...config.sales, null, null];
                const minStock = config.minStock ? labels.map(() => config.minStock) : labels.map(() => null);

                const currentPoint = Array(labels.length).fill(null);
                currentPoint[labels.length - 2] = config.current;
                const afterPoint = Array(labels.length).fill(null);
                afterPoint[labels.length - 1] = config.after;

                const instance = new Chart(el, {
                    type: 'bar',
                    data: {
                        labels,
                        datasets: [
                            {
                                type: 'bar',
                                label: 'Weekly sales',
                                data: sales,
                                backgroundColor: palette,
                                borderRadius: 6,
                                borderSkipped: false,
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
                                fill: false,
                                order: 1,
                            },
                            {
                                type: 'line',
                                label: 'Current',
                                data: currentPoint,
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
                                data: afterPoint,
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
                    options: makeBaseOptions(),
                });

                chartInstances.push(instance);
            };

            renderChart('layout2_v1_chart', charts[0], 'rgba(79, 70, 229, 0.45)');
            renderChart('layout2_v2_chart', charts[1], 'rgba(16, 185, 129, 0.45)');
            renderChart('layout2_v3_chart', charts[0], 'rgba(79, 70, 229, 0.45)');
            renderChart('layout2_v4_chart', charts[1], 'rgba(16, 185, 129, 0.45)');
            renderChart('layout2_v1a_chart', charts[0], 'rgba(79, 70, 229, 0.45)');

            requestAnimationFrame(() => {
                chartInstances.forEach((chart) => chart.resize());
            });
        });
    </script>
</x-app-layout>
