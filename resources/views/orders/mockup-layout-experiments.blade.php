@php
    $sampleItems = [
        [
            'name' => 'Vico Wexford Strawberry Yogurt',
            'code' => 'VWS-001',
            'supplier' => 'Vico Foods',
            'category' => 'Refrigerated • Yogurt',
            'total' => 42,
            'avg' => 5.3,
            'peak' => 9.0,
            'current' => 3,
            'after' => 12,
            'min_stock' => 10,
            'order' => 9,
            'unit_cost' => 6.4,
            'weekly_sales' => [2, 6, 8, 9, 7, 5, 3, 2],
        ],
        [
            'name' => 'Brindisa Manchego Semi Curado',
            'code' => 'BMC-032',
            'supplier' => 'Brindisa',
            'category' => 'Cheese • Hard',
            'total' => 58,
            'avg' => 7.1,
            'peak' => 11.0,
            'current' => 14,
            'after' => 22,
            'min_stock' => 18,
            'order' => 8,
            'unit_cost' => 11.8,
            'weekly_sales' => [4, 5, 9, 11, 7, 8, 6, 8],
        ],
        [
            'name' => 'Union Hand-Roasted Equinox Espresso Beans',
            'code' => 'UNI-222',
            'supplier' => 'Union Roasted',
            'category' => 'Ambient • Coffee',
            'total' => 23,
            'avg' => 2.8,
            'peak' => 5.0,
            'current' => 6,
            'after' => 10,
            'min_stock' => 8,
            'order' => 4,
            'unit_cost' => 7.75,
            'weekly_sales' => [1, 2, 4, 5, 3, 2, 4, 2],
        ],
    ];

    $buildLabels = function (array $sales) {
        $total = count($sales);
        $labels = [];
        foreach (array_keys($sales) as $index) {
            $labels[] = now()->subWeeks($total - 1 - $index)->format('d M');
        }

        return $labels;
    };

    $layout1Weekly = $sampleItems[0]['weekly_sales'];
    $layout1PeakValue = max($layout1Weekly);
    $layout1PeakIndex = array_search($layout1PeakValue, $layout1Weekly, true);
    if ($layout1PeakIndex === false) {
        $layout1PeakIndex = 0;
    }

    $chartConfig = [
        'layout1' => [
            'labels' => $buildLabels($layout1Weekly),
            'sales' => $layout1Weekly,
            'minStock' => $sampleItems[0]['min_stock'],
            'current' => $sampleItems[0]['current'],
            'after' => $sampleItems[0]['after'],
            'totalValue' => $sampleItems[0]['total'],
            'peakValue' => $layout1PeakValue,
            'peakIndex' => $layout1PeakIndex,
        ],
        'layout2' => [
            'labels' => $buildLabels($sampleItems[1]['weekly_sales']),
            'sales' => $sampleItems[1]['weekly_sales'],
            'minStock' => $sampleItems[1]['min_stock'],
            'current' => $sampleItems[1]['current'],
            'after' => $sampleItems[1]['after'],
        ],
        'layout3' => collect($sampleItems)->map(function ($item, $index) use ($buildLabels) {
            return [
                'id' => $index,
                'labels' => $buildLabels($item['weekly_sales']),
                'sales' => $item['weekly_sales'],
                'minStock' => $item['min_stock'],
            ];
        })->values(),
    ];
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-2">
            <h1 class="text-2xl font-semibold text-slate-900">Order Review Layout Experiments</h1>
            <p class="text-sm text-slate-600">
                Sandbox page with hard-coded data for experimenting with different order-review presentations. Use this to iterate
                on visual hierarchy before touching the live table.
            </p>
        </div>
    </x-slot>

    <div class="max-w-6xl mx-auto px-6 py-10 space-y-12">
        <div>
            <p class="text-slate-600 leading-relaxed">
                Each section below rearranges the same sample data to explore readability and decision flow. Duplicate the blocks
                or edit them directly to explore additional directions. When a layout wins, migrate the markup back into
                <code class="font-mono text-xs text-indigo-600">resources/views/orders/partials/review-table.blade.php</code>.
            </p>
            <div class="mt-4 inline-flex items-center gap-2 rounded bg-indigo-50 px-3 py-2 text-sm text-indigo-700">
                <span class="font-semibold uppercase tracking-wide text-xs text-indigo-500">Tip</span>
                <span>
                    Tailwind utility classes are safe to tweak here without affecting production until you copy the winner across.
                </span>
            </div>
        </div>

        {{-- Layout 1 --}}
        <section class="space-y-6">
            <header class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 class="text-xl font-semibold text-slate-900">Layout 1 · Inline Metrics Row</h2>
                    <p class="text-sm text-slate-600">
                        Sales metrics sit directly above the trend chart to emphasise their relationship. Actions remain in a side
                        column. Min stock and coverage metadata live inside the metrics band.
                    </p>
                </div>
                <span class="self-start rounded-full bg-indigo-100 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-indigo-700">
                    Emphasises chart context
                </span>
            </header>

            @php
                $item = $sampleItems[0];
            @endphp

            <article class="rounded-xl border border-slate-200 bg-white shadow-sm">
                <div class="flex flex-col gap-6 p-6 lg:flex-row lg:items-start">
                    <div class="flex-1 space-y-4">
                        <div class="flex flex-wrap items-center gap-4">
                            <div>
                                <h3 class="text-lg font-semibold text-slate-900">{{ $item['name'] }}</h3>
                                <p class="text-sm text-slate-500">{{ $item['code'] }} • {{ $item['supplier'] }} • {{ $item['category'] }}</p>
                            </div>
                            <span class="inline-flex items-center gap-1 rounded-full bg-amber-50 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-amber-600">
                                🟡 Standard priority
                            </span>
                        </div>

                        <div class="rounded-lg border border-slate-200 bg-slate-50/60 p-4">
                            <div class="grid gap-4 text-sm font-semibold text-slate-700 sm:grid-cols-5">
                                <div>
                                    <div class="text-xs uppercase tracking-wide text-slate-400">Total 8w</div>
                                    <div class="text-lg text-slate-900">{{ $item['total'] }}</div>
                                </div>
                                <div>
                                    <div class="text-xs uppercase tracking-wide text-slate-400">Average</div>
                                    <div class="text-lg text-slate-900">{{ number_format($item['avg'], 1) }}</div>
                                </div>
                                <div>
                                    <div class="text-xs uppercase tracking-wide text-slate-400">Peak</div>
                                    <div class="text-lg text-slate-900">{{ number_format($item['peak'], 1) }}</div>
                                </div>
                                <div>
                                    <div class="text-xs uppercase tracking-wide text-slate-400">Current</div>
                                    <div class="text-lg text-rose-600">{{ $item['current'] }}</div>
                                </div>
                                <div>
                                    <div class="text-xs uppercase tracking-wide text-slate-400">After Order</div>
                                    <div class="text-lg text-emerald-600">{{ $item['after'] }}</div>
                                </div>
                            </div>
                            <div class="mt-4 rounded-lg border border-slate-200 bg-white px-4 pt-4 pb-3">
                                <div class="h-48 relative">
                                    <canvas id="layout1_chart" aria-label="Weekly sales trend"></canvas>
                                </div>
                            </div>
                            @if($item['min_stock'] > 0)
                                <div class="mt-2 text-xs font-semibold uppercase tracking-wide text-orange-600">
                                    Min stock target · {{ $item['min_stock'] }} units
                                </div>
                            @endif
                        </div>

                        <div class="flex flex-wrap items-center gap-3 text-sm text-slate-600">
                            <span class="inline-flex items-center gap-1 rounded-lg border border-slate-200 px-3 py-1">
                                Suggested order: <strong class="text-slate-900">{{ $item['order'] }} units</strong>
                            </span>
                            <span class="inline-flex items-center gap-1 rounded-lg border border-slate-200 px-3 py-1">
                                Cost impact: <strong class="text-slate-900">£{{ number_format($item['order'] * $item['unit_cost'], 2) }}</strong>
                            </span>
                            <button class="inline-flex items-center gap-1 rounded border border-slate-300 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-slate-600 hover:bg-slate-100">
                                Adjust order
                            </button>
                            <button class="inline-flex items-center gap-1 rounded border border-indigo-300 bg-white px-3 py-1 text-xs font-semibold uppercase tracking-wide text-indigo-600 hover:bg-indigo-50">
                                Edit min stock
                            </button>
                        </div>
                    </div>

                    <aside class="w-full max-w-xs space-y-4">
                        <div class="rounded-lg border border-slate-200 p-4">
                            <h4 class="text-sm font-semibold uppercase tracking-wide text-slate-500">Actions</h4>
                            <div class="mt-3 space-y-2">
                                <button class="w-full rounded-md bg-emerald-500 py-2 text-sm font-semibold text-white hover:bg-emerald-600">Approve suggestion</button>
                                <button class="w-full rounded-md border border-slate-300 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-100">Flag for review</button>
                                <button class="w-full rounded-md border border-orange-300 bg-orange-50 py-2 text-sm font-semibold text-orange-600 hover:bg-orange-100">Adjust priority</button>
                            </div>
                        </div>

                        <div class="rounded-lg border border-slate-200 p-4 text-sm text-slate-600">
                            <div class="text-xs uppercase tracking-wide text-slate-400">Notes</div>
                            <p class="mt-2">
                                Bringing the critical numbers closer to the graph helps reinforce the trend at a glance. Consider
                                anchoring min stock and coverage meta in this band too.
                            </p>
                        </div>
                    </aside>
                </div>
            </article>
        </section>

        {{-- Layout 2 --}}
        <section class="space-y-6">
            <header class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 class="text-xl font-semibold text-slate-900">Layout 2 · Split Summary Card</h2>
                    <p class="text-sm text-slate-600">
                        Compresses metrics into a left-hand summary stripe and leaves interaction controls on the right. The chart
                        becomes a vertical bar strip, echoing KPI dashboards for quick scanning.
                    </p>
                </div>
                <span class="self-start rounded-full bg-emerald-100 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-emerald-700">
                    Optimised for scan speed
                </span>
            </header>

            @php
                $item = $sampleItems[1];
            @endphp

            <article class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                <div class="grid gap-0 md:grid-cols-[220px,1fr]">
                    <div class="space-y-4 border-b border-slate-200 bg-slate-900/95 p-6 text-white md:border-b-0 md:border-r">
                        <div>
                            <h3 class="text-lg font-semibold">{{ $item['name'] }}</h3>
                            <p class="text-sm text-slate-300">{{ $item['code'] }} • {{ $item['supplier'] }}</p>
                        </div>

                        <dl class="space-y-3 text-sm">
                            <div class="flex items-center justify-between">
                                <dt class="text-slate-300">Current stock</dt>
                                <dd class="font-semibold text-rose-200">{{ $item['current'] }}</dd>
                            </div>
                            <div class="flex items-center justify-between">
                                <dt class="text-slate-300">After order</dt>
                                <dd class="font-semibold text-emerald-200">{{ $item['after'] }}</dd>
                            </div>
                            <div class="flex items-center justify-between">
                                <dt class="text-slate-300">Min stock</dt>
                                <dd class="font-semibold text-orange-200">{{ $item['min_stock'] }}</dd>
                            </div>
                            <div class="flex items-center justify-between">
                                <dt class="text-slate-300">Suggested</dt>
                                <dd class="font-semibold text-sky-200">{{ $item['order'] }} units</dd>
                            </div>
                        </dl>
                    </div>

                    <div class="p-6">
                        <div class="flex flex-wrap items-center gap-4 border-b border-slate-200 pb-4">
                            <div>
                                <div class="text-xs uppercase tracking-wide text-slate-400">Total (8 weeks)</div>
                                <div class="text-xl font-semibold text-slate-900">{{ $item['total'] }}</div>
                            </div>
                            <div>
                                <div class="text-xs uppercase tracking-wide text-slate-400">Average</div>
                                <div class="text-xl font-semibold text-slate-900">{{ number_format($item['avg'], 1) }}</div>
                            </div>
                            <div>
                                <div class="text-xs uppercase tracking-wide text-slate-400">Peak</div>
                                <div class="text-xl font-semibold text-slate-900">{{ number_format($item['peak'], 1) }}</div>
                            </div>
                            <div class="ml-auto inline-flex items-center gap-2 rounded-full bg-slate-100 px-4 py-1 text-xs font-semibold uppercase tracking-wide text-slate-600">
                                Stable demand
                            </div>
                        </div>

                        <div class="mt-5 grid gap-6 lg:grid-cols-[1fr,260px]">
                            <div class="rounded-lg border border-slate-200 bg-slate-50/60 p-4">
                                <div class="h-48">
                                    <canvas id="layout2_chart" aria-label="Weekly sales trend for layout two"></canvas>
                                </div>
                            </div>

                            <div class="space-y-4">
                                <div class="rounded-lg border border-slate-200 bg-white p-4 text-sm text-slate-600">
                                    <div class="text-xs uppercase tracking-wide text-slate-400">Quick adjust</div>
                                    <div class="mt-3 flex flex-wrap gap-2">
                                        <button class="rounded border border-slate-300 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-slate-600 hover:bg-slate-100">
                                            -10%
                                        </button>
                                        <button class="rounded border border-slate-300 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-slate-600 hover:bg-slate-100">
                                            +1 case
                                        </button>
                                        <button class="rounded border border-slate-300 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-slate-600 hover:bg-slate-100">
                                            Match min stock
                                        </button>
                                    </div>
                                </div>

                                <div class="rounded-lg border border-slate-200 bg-white p-4 text-sm text-slate-600">
                                    <div class="text-xs uppercase tracking-wide text-slate-400">Key insight</div>
                                    <p class="mt-2 leading-relaxed">
                                        Mirroring a POS dashboard, this layout pushes the buyer toward decision buttons. Works well when
                                        somebody skims dozens of products quickly.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </article>
        </section>

        {{-- Layout 3 --}}
        <section class="space-y-6">
            <header class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 class="text-xl font-semibold text-slate-900">Layout 3 · Kanban-style Review</h2>
                    <p class="text-sm text-slate-600">
                        A radical variation: each product becomes a standalone card ready for drag-and-drop triage. Trends and key
                        stats stay compact, enabling a kanban board or mobile-first workflow.
                    </p>
                </div>
                <span class="self-start rounded-full bg-rose-100 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-rose-700">
                    Concept exploration
                </span>
            </header>

            <div class="grid gap-5 md:grid-cols-3">
                @foreach($sampleItems as $item)
                    <div class="flex h-full flex-col rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <h3 class="text-base font-semibold text-slate-900">{{ $item['name'] }}</h3>
                                <p class="text-xs text-slate-500">{{ $item['code'] }} • {{ $item['supplier'] }}</p>
                            </div>
                            <span class="rounded-full bg-slate-100 px-3 py-1 text-[10px] font-semibold uppercase tracking-wide text-slate-600">
                                {{ $item['total'] }} sold
                            </span>
                        </div>

                        <dl class="mt-4 grid grid-cols-2 gap-3 text-xs text-slate-600">
                            <div class="rounded-lg bg-slate-50 p-3">
                                <dt class="uppercase tracking-wide text-[10px] text-slate-400">Stock</dt>
                                <dd class="mt-1 text-lg font-semibold text-rose-500">{{ $item['current'] }}</dd>
                            </div>
                            <div class="rounded-lg bg-slate-50 p-3">
                                <dt class="uppercase tracking-wide text-[10px] text-slate-400">After order</dt>
                                <dd class="mt-1 text-lg font-semibold text-emerald-600">{{ $item['after'] }}</dd>
                            </div>
                            <div class="rounded-lg bg-slate-50 p-3">
                                <dt class="uppercase tracking-wide text-[10px] text-slate-400">Avg / Peak</dt>
                                <dd class="mt-1 text-lg font-semibold text-slate-900">{{ number_format($item['avg'], 1) }} / {{ number_format($item['peak'], 1) }}</dd>
                            </div>
                            <div class="rounded-lg bg-slate-50 p-3">
                                <dt class="uppercase tracking-wide text-[10px] text-slate-400">Min stock</dt>
                                <dd class="mt-1 text-lg font-semibold text-orange-500">{{ $item['min_stock'] }}</dd>
                            </div>
                        </dl>

                        <div class="mt-5 rounded-lg border border-slate-100 bg-slate-50/60 p-3">
                            <div class="h-36">
                                <canvas id="layout3_chart_{{ $loop->index }}" aria-label="Mini trend"></canvas>
                            </div>
                        </div>

                        <div class="mt-6 space-y-2 text-xs text-slate-600">
                            <div class="flex justify-between">
                                <span>Suggested order</span>
                                <span class="font-semibold text-slate-900">{{ $item['order'] }} units</span>
                            </div>
                            <div class="flex justify-between">
                                <span>Cost impact</span>
                                <span class="font-semibold text-slate-900">£{{ number_format($item['order'] * $item['unit_cost'], 2) }}</span>
                            </div>
                        </div>

                        <div class="mt-6 space-y-2">
                            <button class="w-full rounded-md bg-indigo-500 py-2 text-sm font-semibold text-white hover:bg-indigo-600">
                                Accept & queue order
                            </button>
                            <button class="w-full rounded-md border border-slate-300 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-100">
                                Open adjustments
                            </button>
                        </div>
                    </div>
                @endforeach
            </div>
        </section>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            if (window.orderLayoutMockChartsInitialised) {
                return;
            }

            window.orderLayoutMockChartsInitialised = true;

            const chartConfig = @json($chartConfig);

            const annotationPlugin = {
                id: 'orderLayoutAnnotationLabels',
                afterDatasetsDraw(chart) {
                    const opts = chart.config.options.layoutAnnotations;
                    if (!opts) {
                        return;
                    }

                    const ctx = chart.ctx;
                    ctx.save();
                    ctx.fillStyle = 'rgb(30, 41, 59)';
                    ctx.font = '600 11px "Figtree", sans-serif';
                    ctx.textAlign = 'center';
                    ctx.textBaseline = 'bottom';

                    const topY = (chart.chartArea?.top ?? 0) - (opts.rowOffset ?? 8);

                    const drawLabel = (element, text, align = 'center', offsetX = 0) => {
                        if (!element || !text) {
                            return;
                        }
                        const { x } = element.tooltipPosition();
                        ctx.textAlign = align;
                        ctx.fillText(text, x + offsetX, topY);
                        ctx.textAlign = 'center';
                    };

                    const barMeta = chart.getDatasetMeta(0);
                    if (opts.total && opts.total.index !== undefined) {
                        drawLabel(barMeta?.data?.[opts.total.index], opts.total.text, 'left', -20);
                    }

                    if (opts.peak && opts.peak.index !== undefined) {
                        drawLabel(barMeta?.data?.[opts.peak.index], opts.peak.text);
                    }

                    if (opts.current) {
                        const currentMeta = chart.getDatasetMeta(opts.current.datasetIndex ?? 2);
                        drawLabel(currentMeta?.data?.[opts.current.index], opts.current.text);
                    }

                    if (opts.after) {
                        const afterMeta = chart.getDatasetMeta(opts.after.datasetIndex ?? 3);
                        drawLabel(afterMeta?.data?.[opts.after.index], opts.after.text, 'right', 20);
                    }

                    ctx.restore();
                },
            };

            const buildBaseOptions = (style = 'default') => {
                const base = {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: style === 'default',
                            position: 'top',
                            labels: {
                                font: { size: style === 'mini' ? 9 : 11 },
                                usePointStyle: true,
                            },
                        },
                        tooltip: {
                            backgroundColor: 'rgba(15, 23, 42, 0.92)',
                            titleFont: { size: 12, weight: '600' },
                            bodyFont: { size: 11 },
                            callbacks: {
                                label: (ctx) => {
                                    const label = ctx.dataset.label ? `${ctx.dataset.label}: ` : '';
                                    return `${label}${ctx.parsed.y ?? 0} units`;
                                },
                            },
                        },
                    },
                    scales: {
                        x: {
                            grid: { display: false },
                            ticks: {
                                font: { size: style === 'mini' ? 9 : 11 },
                                color: 'rgb(100, 116, 139)',
                                maxRotation: 0,
                                autoSkipPadding: 12,
                            },
                        },
                        y: {
                            beginAtZero: true,
                            grid: {
                                color: 'rgba(148, 163, 184, 0.2)',
                            },
                            ticks: {
                                font: { size: style === 'mini' ? 9 : 11 },
                                color: 'rgb(71, 85, 105)',
                                precision: 0,
                            },
                        },
                    },
                };

                if (style === 'mini') {
                    base.plugins.legend.display = false;
                }

                if (style === 'hidden') {
                    base.plugins.legend.display = false;
                }

                return base;
            };

            const renderPrimaryChart = (canvasId, config, palette, annotations, optionsStyle = 'default') => {
                const el = document.getElementById(canvasId);
                if (!el) {
                    return;
                }

                const labels = [...config.labels, 'Current', 'After order'];
                const weekly = [...config.sales, null, null];
                const minStock = config.minStock ? labels.map(() => config.minStock) : labels.map(() => null);

                const currentPoint = Array(labels.length).fill(null);
                currentPoint[labels.length - 2] = config.current;

                const afterPoint = Array(labels.length).fill(null);
                afterPoint[labels.length - 1] = config.after;

                const formatValue = (value) => {
                    if (value === undefined || value === null) {
                        return '';
                    }
                    const numeric = Number(value);
                    if (Number.isNaN(numeric)) {
                        return `${value}`;
                    }
                    const fractionDigits = Number.isInteger(numeric) ? 0 : 1;
                    return numeric.toLocaleString(undefined, { maximumFractionDigits: fractionDigits });
                };

                let annotationOptions = null;
                if (annotations) {
                    annotationOptions = {
                        total: {
                            index: annotations.totalIndex ?? 0,
                            text: `Total ${formatValue(annotations.totalValue)}`,
                        },
                        peak: {
                            index: annotations.peakIndex ?? 0,
                            text: `Peak ${formatValue(annotations.peakValue)}`,
                        },
                        current: {
                            datasetIndex: 2,
                            index: labels.length - 2,
                            text: `Current ${formatValue(config.current)}`,
                        },
                        after: {
                            datasetIndex: 3,
                            index: labels.length - 1,
                            text: `After ${formatValue(config.after)}`,
                        },
                    };
                }

                const options = buildBaseOptions(optionsStyle);
                if (annotationOptions) {
                    options.layoutAnnotations = Object.assign({ rowOffset: 10 }, annotationOptions);
                }

                new Chart(el, {
                    type: 'bar',
                    data: {
                        labels,
                        datasets: [
                            {
                                type: 'bar',
                                label: 'Weekly sales',
                                data: weekly,
                                backgroundColor: palette.bar,
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
                                label: 'Current stock',
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
                                label: 'After order',
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
                    options,
                    plugins: annotationOptions ? [annotationPlugin] : [],
                });
            };

            const renderMiniChart = (canvasId, config) => {
                const el = document.getElementById(canvasId);
                if (!el) {
                    return;
                }

                const labels = config.labels;
                const minStock = config.minStock ? labels.map(() => config.minStock) : labels.map(() => null);

                new Chart(el, {
                    type: 'bar',
                    data: {
                        labels,
                        datasets: [
                            {
                                type: 'bar',
                                label: 'Weekly sales',
                                data: config.sales,
                                backgroundColor: 'rgba(99, 102, 241, 0.45)',
                                borderRadius: 4,
                                borderSkipped: false,
                                order: 2,
                            },
                            {
                                type: 'line',
                                label: 'Min stock target',
                                data: minStock,
                                borderColor: 'rgba(249, 115, 22, 0.85)',
                                borderWidth: 1.5,
                                borderDash: [4, 3],
                                pointRadius: 0,
                                spanGaps: true,
                                fill: false,
                                order: 1,
                            },
                        ],
                    },
                    options: buildBaseOptions('mini'),
                });
            };

            renderPrimaryChart('layout1_chart', chartConfig.layout1, {
                bar: 'rgba(79, 70, 229, 0.45)',
            }, {
                totalValue: chartConfig.layout1.totalValue,
                totalIndex: 0,
                peakValue: chartConfig.layout1.peakValue,
                peakIndex: chartConfig.layout1.peakIndex,
            }, 'hidden');

            renderPrimaryChart('layout2_chart', chartConfig.layout2, {
                bar: 'rgba(16, 185, 129, 0.45)',
            });

            (chartConfig.layout3 || []).forEach((item) => {
                renderMiniChart(`layout3_chart_${item.id}`, item);
            });
        });
    </script>
</x-app-layout>
