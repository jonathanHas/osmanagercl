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
                            <div class="order-controls-col flex w-full flex-col gap-3" style="flex: 0 0 clamp(9rem, 11vw, 11.5rem);">
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

{{-- Variation A2 --}}
        <section class="space-y-4">
            <header class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 class="text-xl font-semibold text-slate-900">Variation A2 · Split Shell with Point Trend</h2>
                    <p class="text-sm text-slate-600">Keeps the split shell framing from A but trades bars for a point-based trend to emphasise rate-of-change.</p>
                </div>
                <span class="self-start rounded-full bg-indigo-100 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-indigo-700">Concept</span>
            </header>

            @php
                $a2Item = $sampleItems[0];
                $a2Chart = $chartPayload[0];
            @endphp

            <article class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                <div class="md:flex md:items-stretch">
                    <aside class="hidden bg-slate-900/95 p-6 text-slate-200 md:block md:w-60 md:flex-shrink-0">
                        <div class="flex h-full flex-col">
                            <div class="pb-5">
                                <p class="text-[11px] uppercase tracking-[0.2em] text-slate-500">Product</p>
                                <h3 class="mt-2 text-base font-semibold text-white leading-snug">{{ $a2Item['name'] }}</h3>
                                <p class="mt-1 text-xs text-slate-400 leading-relaxed">{{ $a2Item['code'] }} • {{ $a2Item['supplier'] }} • {{ $a2Item['category'] }}</p>
                            </div>

                            <dl class="space-y-4 border-t border-slate-800/60 pt-5 text-xs text-slate-300">
                                <div>
                                    <dt class="text-slate-500 uppercase tracking-wide text-[10px]">Min stock</dt>
                                    <dd class="mt-1 text-sm font-semibold text-orange-200">{{ $a2Item['min_stock'] }}</dd>
                                </div>
                                <div>
                                    <dt class="text-slate-500 uppercase tracking-wide text-[10px]">Unit cost</dt>
                                    <dd class="mt-1 text-sm font-semibold text-emerald-200">£{{ number_format($a2Item['unit_cost'], 2) }}</dd>
                                </div>
                                <div>
                                    <dt class="text-slate-500 uppercase tracking-wide text-[10px]">Total sold (8w)</dt>
                                    <dd class="mt-1 text-sm font-semibold text-slate-100">{{ $a2Item['total'] }}</dd>
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
                                        <div class="text-lg font-semibold text-slate-800">{{ $a2Item['total'] }}</div>
                                    </div>
                                    <div>
                                        <div>Average</div>
                                        <div class="text-lg font-semibold text-slate-800">{{ number_format($a2Item['avg'], 1) }}</div>
                                    </div>
                                    <div>
                                        <div>Peak</div>
                                        <div class="text-lg font-semibold text-slate-800">{{ number_format($a2Item['peak'], 1) }}</div>
                                    </div>
                                    <div>
                                        <div>Current</div>
                                        <div class="text-lg font-semibold text-rose-600">{{ $a2Item['current'] }}</div>
                                    </div>
                                    <div>
                                        <div>After order</div>
                                        <div class="text-lg font-semibold text-emerald-600">{{ $a2Item['after'] }}</div>
                                    </div>
                                </div>
                                <div class="mt-4 h-64">
                                    <canvas id="layout2_v1a2_chart" aria-label="Weekly sales trend"></canvas>
                                </div>
                                <div class="mt-3 text-xs font-semibold uppercase tracking-wide text-orange-600">
                                    Min stock target · {{ $a2Item['min_stock'] }} units
                                </div>
                            </div>
                            <div class="order-controls-col flex w-full flex-col gap-3" style="flex: 0 0 clamp(9rem, 11vw, 11.5rem);">
                                <div>
                                    <div class="mb-1 flex items-baseline justify-between text-xs uppercase tracking-wide text-slate-400">
                                        <span>Order units</span>
                                        <span class="text-xs font-medium text-slate-500">Suggested <span class="font-normal text-slate-500">{{ $a2Item['order'] }}</span></span>
                                    </div>
                                    <input id="mock-order-input" type="number" value="{{ $a2Item['order'] }}" class="w-full rounded-md border border-slate-300 px-2.5 py-1.5 text-base font-semibold text-slate-800 focus:border-indigo-500 focus:ring-indigo-500">
                                </div>
                                <div>
                                    <p class="mb-1 text-xs uppercase tracking-wide text-slate-400">Min stock override</p>
                                    <input type="number" value="{{ $a2Item['min_stock'] }}" class="w-full rounded-md border border-slate-300 px-2.5 py-1.5 text-sm font-semibold text-slate-800 focus:border-indigo-500 focus:ring-indigo-500">
                                </div>
                                <div class="space-y-2 text-sm text-slate-600">
                                    <div class="rounded border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-500">
                                        Cost impact: <span class="text-slate-700">£{{ number_format($a2Item['order'] * $a2Item['unit_cost'], 2) }}</span>
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
                        <h3 class="mt-1 text-lg font-semibold text-white">{{ $a2Item['name'] }}</h3>
                        <p class="text-sm text-slate-400">{{ $a2Item['code'] }} • {{ $a2Item['supplier'] }} • {{ $a2Item['category'] }}</p>
                    </div>
                    <dl class="mt-4 grid grid-cols-3 gap-3 text-xs text-slate-300">
                        <div>
                            <dt>Min stock</dt>
                            <dd class="font-semibold text-orange-200">{{ $a2Item['min_stock'] }}</dd>
                        </div>
                        <div>
                            <dt>Unit cost</dt>
                            <dd class="font-semibold text-emerald-200">£{{ number_format($a2Item['unit_cost'], 2) }}</dd>
                        </div>
                        <div>
                            <dt>Total sold</dt>
                            <dd class="font-semibold text-slate-100">{{ $a2Item['total'] }}</dd>
                        </div>
                    </dl>
                    <div class="mt-4 grid grid-cols-2 gap-2 text-xs">
                        <button class="rounded-md border border-slate-700/70 px-3 py-2 font-semibold text-slate-200 hover:bg-slate-800">Flag for review</button>
                        <button class="rounded-md border border-slate-700/70 px-3 py-2 font-semibold text-slate-200 hover:bg-slate-800">Adjust priority</button>
                    </div>
                </div>
            </article>
        </section>

{{-- Variation A1 --}}
        <section class="space-y-4">
            <header class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 class="text-xl font-semibold text-slate-900">Variation A1 · Fixed Controls, A-style Metrics</h2>
                    <p class="text-sm text-slate-600">Same visual hierarchy as Variation A, but the control stack is clamped so it never balloons on load.</p>
                </div>
                <span class="self-start rounded-full bg-indigo-100 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-indigo-700">Concept</span>
            </header>

            @php
                $a1Item = $sampleItems[0];
                $a1Chart = $chartPayload[0];
            @endphp

            <article class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                <div class="md:flex md:items-stretch">
                    <aside class="hidden bg-slate-900/95 p-6 text-slate-200 md:block md:w-60 md:flex-shrink-0">
                        <div class="flex h-full flex-col">
                            <div class="pb-5">
                                <p class="text-[11px] uppercase tracking-[0.2em] text-slate-500">Product</p>
                                <h3 class="mt-2 text-base font-semibold text-white leading-snug">{{ $a1Item['name'] }}</h3>
                                <p class="mt-1 text-xs text-slate-400 leading-relaxed">{{ $a1Item['code'] }} • {{ $a1Item['supplier'] }} • {{ $a1Item['category'] }}</p>
                            </div>

                            <dl class="space-y-4 border-t border-slate-800/60 pt-5 text-xs text-slate-300">
                                <div>
                                    <dt class="text-slate-500 uppercase tracking-wide text-[10px]">Min stock</dt>
                                    <dd class="mt-1 text-sm font-semibold text-orange-200">{{ $a1Item['min_stock'] }}</dd>
                                </div>
                                <div>
                                    <dt class="text-slate-500 uppercase tracking-wide text-[10px]">Unit cost</dt>
                                    <dd class="mt-1 text-sm font-semibold text-emerald-200">£{{ number_format($a1Item['unit_cost'], 2) }}</dd>
                                </div>
                                <div>
                                    <dt class="text-slate-500 uppercase tracking-wide text-[10px]">Total sold (8w)</dt>
                                    <dd class="mt-1 text-sm font-semibold text-slate-100">{{ $a1Item['total'] }}</dd>
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
                            <div class="flex w-full flex-col gap-3 md:w-full order-controls-col">
                                <div>
                                    <div class="mb-1 flex items-baseline justify-between text-xs uppercase tracking-wide text-slate-400">
                                        <span>Order units</span>
                                        <span class="text-xs font-medium text-slate-500">Suggested <span class="font-normal text-slate-500">{{ $a1Item['order'] }}</span></span>
                                    </div>
                                    <input id="mock-order-input" type="number" value="{{ $a1Item['order'] }}" class="w-full rounded-md border border-slate-300 px-2.5 py-1.5 text-base font-semibold text-slate-800 focus:border-indigo-500 focus:ring-indigo-500">
                                </div>
                                <div>
                                    <p class="mb-1 text-xs uppercase tracking-wide text-slate-400">Min stock override</p>
                                    <input type="number" value="{{ $a1Item['min_stock'] }}" class="w-full rounded-md border border-slate-300 px-2.5 py-1.5 text-sm font-semibold text-slate-800 focus:border-indigo-500 focus:ring-indigo-500">
                                </div>
                                <div class="space-y-2 text-sm text-slate-600">
                                    <div class="rounded border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-500">
                                        Cost impact: <span class="text-slate-700">£{{ number_format($a1Item['order'] * $a1Item['unit_cost'], 2) }}</span>
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
                        <h3 class="mt-1 text-lg font-semibold text-white">{{ $a1Item['name'] }}</h3>
                        <p class="text-sm text-slate-400">{{ $a1Item['code'] }} • {{ $a1Item['supplier'] }} • {{ $a1Item['category'] }}</p>
                    </div>
                    <dl class="mt-4 grid grid-cols-3 gap-3 text-xs text-slate-300">
                        <div>
                            <dt>Min stock</dt>
                            <dd class="font-semibold text-orange-200">{{ $a1Item['min_stock'] }}</dd>
                        </div>
                        <div>
                            <dt>Unit cost</dt>
                            <dd class="font-semibold text-emerald-200">£{{ number_format($a1Item['unit_cost'], 2) }}</dd>
                        </div>
                        <div>
                            <dt>Total sold</dt>
                            <dd class="font-semibold text-slate-100">{{ $a1Item['total'] }}</dd>
                        </div>
                    </dl>
                    <div class="mt-4 grid grid-cols-2 gap-2 text-xs">
                        <button class="rounded-md border border-slate-700/70 px-3 py-2 font-semibold text-slate-200 hover:bg-slate-800">Flag for review</button>
                        <button class="rounded-md border border-slate-700/70 px-3 py-2 font-semibold text-slate-200 hover:bg-slate-800">Adjust priority</button>
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
                    <aside class="hidden bg-slate-900/95 p-6 text-slate-200 md:block md:w-60 md:flex-shrink-0">
                        <div class="flex h-full flex-col">
                            <div class="pb-5">
                                <p class="text-[11px] uppercase tracking-[0.2em] text-slate-500">Product</p>
                                <h3 class="mt-2 text-base font-semibold text-white leading-snug">{{ $fixedItem['name'] }}</h3>
                                <p class="mt-1 text-xs text-slate-400 leading-relaxed">{{ $fixedItem['code'] }} • {{ $fixedItem['supplier'] }} • {{ $fixedItem['category'] }}</p>
                            </div>

                            <dl class="space-y-4 border-t border-slate-800/60 pt-5 text-xs text-slate-300">
                                <div>
                                    <dt class="text-slate-500 uppercase tracking-wide text-[10px]">Min stock</dt>
                                    <dd class="mt-1 text-sm font-semibold text-orange-200">{{ $fixedItem['min_stock'] }}</dd>
                                </div>
                                <div>
                                    <dt class="text-slate-500 uppercase tracking-wide text-[10px]">Unit cost</dt>
                                    <dd class="mt-1 text-sm font-semibold text-emerald-200">£{{ number_format($fixedItem['unit_cost'], 2) }}</dd>
                                </div>
                                <div>
                                    <dt class="text-slate-500 uppercase tracking-wide text-[10px]">Total sold (8w)</dt>
                                    <dd class="mt-1 text-sm font-semibold text-slate-100">{{ $fixedItem['total'] }}</dd>
                                </div>
                            </dl>

                            <div class="mt-auto space-y-2 border-t border-slate-800/60 pt-5 text-xs">
                                <p class="text-slate-500 uppercase tracking-wide text-[10px]">Admin actions</p>
                                <button class="w-full rounded-lg border border-slate-700/40 bg-slate-800/60 px-3 py-2 font-semibold text-slate-100 transition hover:bg-slate-700/80">Flag for review</button>
                                <button class="w-full rounded-lg border border-slate-700/40 bg-slate-800/60 px-3 py-2 font-semibold text-slate-100 transition hover:bg-slate-700/80">Adjust priority</button>
                            </div>
                        </div>
                    </aside>

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
                            <div class="rounded border border-slate-200 px-3 py-2">Cost impact <span class="font-normal normal-case text-slate-700">£{{ number_format($fixedItem['order'] * $fixedItem['unit_cost'], 2) }}</span></div>
                            <button class="w-full rounded border border-slate-300 px-3 py-2 text-[11px] font-semibold uppercase tracking-wide text-slate-600 hover:bg-slate-100">Reset to suggested</button>
                        </div>
                    </div>
                </div>
                <div class="md:hidden border-t border-slate-200 bg-slate-900/95 p-5 text-slate-200">
                    <div>
                        <p class="text-xs uppercase tracking-wide text-slate-400">Product</p>
                        <h3 class="mt-1 text-lg font-semibold text-white">{{ $fixedItem['name'] }}</h3>
                        <p class="text-sm text-slate-400">{{ $fixedItem['code'] }} • {{ $fixedItem['supplier'] }} • {{ $fixedItem['category'] }}</p>
                    </div>
                    <dl class="mt-4 grid grid-cols-3 gap-3 text-xs text-slate-300">
                        <div>
                            <dt>Min stock</dt>
                            <dd class="font-semibold text-orange-200">{{ $fixedItem['min_stock'] }}</dd>
                        </div>
                        <div>
                            <dt>Unit cost</dt>
                            <dd class="font-semibold text-emerald-200">£{{ number_format($fixedItem['unit_cost'], 2) }}</dd>
                        </div>
                        <div>
                            <dt>Total sold</dt>
                            <dd class="font-semibold text-slate-100">{{ $fixedItem['total'] }}</dd>
                        </div>
                    </dl>
                    <div class="mt-4 grid grid-cols-2 gap-2 text-xs">
                        <button class="rounded-md border border-slate-700/70 px-3 py-2 font-semibold text-slate-200 hover:bg-slate-800">Flag for review</button>
                        <button class="rounded-md border border-slate-700/70 px-3 py-2 font-semibold text-slate-200 hover:bg-slate-800">Adjust priority</button>
                    </div>
                </div>

             </article>
        </section>
        <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            if (window.orderLayoutMockChartsV4Initialised) {
                return;
            }

            window.orderLayoutMockChartsV4Initialised = true;

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

            const renderChart = (canvasId, config, palette, mode = 'bar') => {
                const el = document.getElementById(canvasId);
                if (!el) return;

                const labels = [...config.labels, 'Current', 'After'];
                const sales = [...config.sales, null, null];
                const minStock = config.minStock ? labels.map(() => config.minStock) : labels.map(() => null);

                const currentPoint = Array(labels.length).fill(null);
                currentPoint[labels.length - 2] = config.current;
                const afterPoint = Array(labels.length).fill(null);
                afterPoint[labels.length - 1] = config.after;

                const salesDataset =
                    mode === 'points'
                        ? {
                              type: 'line',
                              label: 'Weekly sales',
                              data: sales,
                              borderColor: palette,
                              borderWidth: 2,
                              tension: 0.35,
                              pointRadius: (ctx) => (ctx.dataIndex === config.peakIndex ? 6 : 4),
                              pointBackgroundColor: (ctx) => (ctx.dataIndex === config.peakIndex ? palette : '#fff'),
                              pointBorderColor: palette,
                              pointBorderWidth: 2,
                              spanGaps: true,
                              fill: false,
                              order: 2,
                          }
                        : {
                              type: 'bar',
                              label: 'Weekly sales',
                              data: sales,
                              backgroundColor: palette,
                              borderRadius: 6,
                              borderSkipped: false,
                              order: 2,
                          };

                const instance = new Chart(el, {
                    type: mode === 'points' ? 'line' : 'bar',
                    data: {
                        labels,
                        datasets: [
                            salesDataset,
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
            renderChart('layout2_v1a2_chart', charts[0], 'rgb(79, 70, 229)', 'points');
            renderChart('layout2_v1a_chart', charts[0], 'rgba(79, 70, 229, 0.45)');
            renderChart('layout2_v4_chart', charts[1], 'rgba(16, 185, 129, 0.45)');

            requestAnimationFrame(() => {
                chartInstances.forEach((chart) => chart.resize());
            });
        });
    </script>
</x-app-layout>
