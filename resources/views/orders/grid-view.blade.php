<x-admin-layout>
    <x-slot name="header">
        <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-2">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    Order Review (Grid View) – {{ $order->supplier->Supplier ?? 'Supplier' }}
                </h2>
                <p class="text-sm text-gray-600 mt-1">
                    Delivery: {{ optional($order->order_date)->format('l, F j, Y') ?? 'TBC' }}
                    • Created by {{ $order->user->name ?? 'System' }}
                    • Status: <span class="font-semibold">{{ ucfirst($order->status) }}</span>
                </p>
            </div>
            <div class="flex gap-2">
                <a href="{{ route('orders.show', $order) }}" class="px-4 py-2 bg-gray-600 hover:bg-gray-700 text-white rounded-lg font-medium text-sm">
                    Table View
                </a>
                @if ($order->isEditable())
                    <form method="POST" action="{{ route('orders.complete', $order) }}" class="inline-block">
                        @csrf
                        <button type="submit" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg font-medium text-sm">
                            Complete Order
                        </button>
                    </form>
                @endif
                <a href="{{ route('orders.export', $order) }}" class="px-4 py-2 bg-gray-600 hover:bg-gray-700 text-white rounded-lg font-medium text-sm">
                    Export CSV
                </a>
            </div>
        </div>
    </x-slot>

    {{-- CSS Grid Layout Style --}}
    <style>
        @media (min-width: 768px) {
            .grid-chart-controls {
                display: grid !important;
                grid-template-columns: 1fr 16rem !important;
            }
        }
    </style>

    @php
        $totalUnits = $order->items->sum('quantity');
        $avgOrderQuantity = $order->items->count() > 0 ? $totalUnits / $order->items->count() : 0;
    @endphp

    <div class="py-6">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
            {{-- Summary Statistics --}}
            <div class="grid grid-cols-1 md:grid-cols-5 gap-4">
                <div class="bg-white shadow-sm rounded-lg p-4">
                    <div class="text-sm font-medium text-gray-500">Total Items</div>
                    <div class="mt-1 text-2xl font-semibold text-gray-900">{{ $statistics['total_items'] }}</div>
                </div>
                <div class="bg-blue-50 shadow-sm rounded-lg p-4">
                    <div class="text-sm font-medium text-blue-600">Total Order Value</div>
                    <div class="mt-1 text-2xl font-semibold text-blue-700">£{{ number_format($statistics['total_value'], 2) }}</div>
                </div>
                <div class="bg-green-50 shadow-sm rounded-lg p-4">
                    <div class="text-sm font-medium text-green-600">Total Units</div>
                    <div class="mt-1 text-2xl font-semibold text-green-700">{{ $totalUnits }}</div>
                </div>
                <div class="bg-purple-50 shadow-sm rounded-lg p-4">
                    <div class="text-sm font-medium text-purple-600">Avg Order</div>
                    <div class="mt-1 text-2xl font-semibold text-purple-700">{{ number_format($avgOrderQuantity, 1) }}</div>
                </div>
                <div class="bg-orange-50 shadow-sm rounded-lg p-4">
                    <div class="text-sm font-medium text-orange-600">Coverage</div>
                    <div class="mt-1 text-2xl font-semibold text-orange-700">{{ $order->coverage_days ?? 'N/A' }} days</div>
                </div>
            </div>

            {{-- Product Cards --}}
            @foreach ($order->items as $item)
                @php
                    $product = $item->product;

                    // Skip if product doesn't exist
                    if (!$product) {
                        continue;
                    }

                    // Decode context_data if it's a string
                    $contextData = $item->context_data ?? [];
                    if (is_string($contextData)) {
                        $contextData = json_decode($contextData, true) ?? [];
                    }

                    // Extract weekly sales - it's an array of ['label' => 'date', 'units' => number]
                    $weeklySalesRaw = $contextData['weekly_sales'] ?? [];
                    $weekLabels = array_map(static fn ($week) => $week['label'] ?? '', $weeklySalesRaw);
                    $weeklySalesUnits = array_map(static fn ($week) => (float) ($week['units'] ?? 0), $weeklySalesRaw);

                    $totalSold = array_sum($weeklySalesUnits);
                    $currentStock = $contextData['current_stock'] ?? 0;
                    $avgSales = $contextData['average_sales'] ?? 0;
                    $peakSales = $contextData['peak_sales'] ?? 0;
                    $afterOrderStock = $currentStock + ($item->final_quantity ?? $item->suggested_quantity ?? 0);

                    // Get min stock from context data
                    $minStock = $contextData['min_stock'] ?? 0;

                    $unitCost = $item->unit_cost ?? $product->cost_price ?? 0;
                    $costImpact = ($item->final_quantity ?? $item->suggested_quantity ?? 0) * $unitCost;

                    // Get display values with fallbacks
                    $productName = strip_tags(html_entity_decode($product->NAME ?? $contextData['product_name'] ?? 'Unknown Product'));
                    $productCode = $product->CODE ?? $product->REFERENCE ?? $contextData['product_code'] ?? 'N/A';
                    $supplierName = $product->supplier->Supplier ?? $order->supplier->Supplier ?? $contextData['supplier_name'] ?? 'Supplier';
                @endphp

                <article class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                    <div class="md:flex md:items-stretch">
                        {{-- Dark Sidebar: Product Info --}}
                        <aside class="hidden bg-slate-900/95 p-6 text-slate-200 md:block md:w-60 md:flex-shrink-0">
                            <div class="flex h-full flex-col">
                                <div class="pb-5">
                                    <p class="text-[11px] uppercase tracking-[0.2em] text-slate-500">Product</p>
                                    <h3 class="mt-2 text-base font-semibold text-white leading-snug">{{ $productName }}</h3>
                                    <p class="mt-1 text-xs text-slate-400 leading-relaxed">
                                        {{ $productCode }} • {{ $supplierName }}
                                    </p>
                                </div>

                                <dl class="space-y-4 border-t border-slate-800/60 pt-5 text-xs text-slate-300">
                                    <div>
                                        <dt class="text-slate-500 uppercase tracking-wide text-[10px]">Min stock</dt>
                                        <dd class="mt-1 text-sm font-semibold text-orange-200">{{ $minStock }}</dd>
                                    </div>
                                    <div>
                                        <dt class="text-slate-500 uppercase tracking-wide text-[10px]">Unit cost</dt>
                                        <dd class="mt-1 text-sm font-semibold text-emerald-200">£{{ number_format($unitCost, 2) }}</dd>
                                    </div>
                                    <div>
                                        <dt class="text-slate-500 uppercase tracking-wide text-[10px]">Total sold (8w)</dt>
                                        <dd class="mt-1 text-sm font-semibold text-slate-100">{{ $totalSold }}</dd>
                                    </div>
                                </dl>

                                <div class="mt-auto space-y-2 border-t border-slate-800/60 pt-5 text-xs">
                                    <p class="text-slate-500 uppercase tracking-wide text-[10px]">Admin actions</p>
                                    <a href="{{ route('products.show', $product) }}" class="block w-full rounded-lg border border-slate-700/40 bg-slate-800/60 px-3 py-2 font-semibold text-center text-slate-100 transition hover:bg-slate-700/80">
                                        View Product
                                    </a>
                                </div>
                            </div>
                        </aside>

                        {{-- Main Content: Chart + Controls --}}
                        <div class="p-6 md:pl-8 md:flex-1">
                            <div class="flex flex-col gap-6 grid-chart-controls">
                                {{-- Chart Container --}}
                                <div class="rounded-lg border border-slate-200 bg-slate-50/60 p-4">
                                    <div class="grid gap-4 text-xs font-semibold uppercase tracking-wide text-slate-500 sm:grid-cols-5">
                                        <div>
                                            <div>Total 8w</div>
                                            <div class="text-lg font-semibold text-slate-800">{{ $totalSold }}</div>
                                        </div>
                                        <div>
                                            <div>Average</div>
                                            <div class="text-lg font-semibold text-slate-800">{{ number_format($avgSales, 1) }}</div>
                                        </div>
                                        <div>
                                            <div>Peak</div>
                                            <div class="text-lg font-semibold text-slate-800">{{ number_format($peakSales, 1) }}</div>
                                        </div>
                                        <div>
                                            <div>Current</div>
                                            <div class="text-lg font-semibold text-rose-600">{{ $currentStock }}</div>
                                        </div>
                                        <div>
                                            <div>After order</div>
                                            <div class="text-lg font-semibold text-emerald-600">{{ $afterOrderStock }}</div>
                                        </div>
                                    </div>
                                    <div class="mt-4 h-64">
                                        <canvas id="chart_item_{{ $item->id }}" aria-label="Weekly sales trend" data-item-id="{{ $item->id }}"></canvas>
                                    </div>
                                    <div class="mt-3 text-xs font-semibold uppercase tracking-wide text-orange-600">
                                        Min stock target · {{ $minStock }} units
                                    </div>
                                </div>

                                {{-- Controls Column --}}
                                <div class="flex flex-col gap-3">
                                    <div>
                                        <div class="mb-1 flex items-baseline justify-between text-xs uppercase tracking-wide text-slate-400">
                                            <span>Order units</span>
                                            <span class="text-xs font-medium text-slate-500">
                                                Suggested <span class="font-normal text-slate-500">{{ $item->suggested_quantity ?? 0 }}</span>
                                            </span>
                                        </div>
                                        <input
                                            type="number"
                                            value="{{ $item->final_quantity ?? $item->suggested_quantity ?? 0 }}"
                                            data-item-id="{{ $item->id }}"
                                            class="order-quantity-input w-full rounded-md border border-slate-300 px-2.5 py-1.5 text-base font-semibold text-slate-800 focus:border-indigo-500 focus:ring-indigo-500"
                                            @if(!$order->isEditable()) disabled @endif
                                        >
                                    </div>
                                    <div>
                                        <p class="mb-1 text-xs uppercase tracking-wide text-slate-400">Min stock override</p>
                                        <input
                                            type="number"
                                            value="{{ $minStock }}"
                                            class="w-full rounded-md border border-slate-300 px-2.5 py-1.5 text-sm font-semibold text-slate-800 focus:border-indigo-500 focus:ring-indigo-500"
                                            disabled
                                        >
                                    </div>
                                    <div class="space-y-2 text-sm text-slate-600">
                                        <div class="rounded border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-500">
                                            Cost impact: <span class="text-slate-700 cost-impact-value" data-item-id="{{ $item->id }}">£{{ number_format($costImpact, 2) }}</span>
                                        </div>
                                        @if($order->isEditable())
                                            <button
                                                class="reset-quantity-btn w-full rounded border border-slate-300 px-3 py-2 text-xs font-semibold uppercase tracking-wide text-slate-600 hover:bg-slate-100"
                                                data-item-id="{{ $item->id }}"
                                                data-suggested="{{ $item->suggested_quantity ?? 0 }}"
                                            >
                                                Reset to suggested
                                            </button>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </article>
            @endforeach
        </div>
    </div>

    {{-- Prepare chart data --}}
    @php
        $chartData = $order->items->map(function($item) {
            // Decode context_data if it's a string
            $contextData = $item->context_data ?? [];
            if (is_string($contextData)) {
                $contextData = json_decode($contextData, true) ?? [];
            }

            // Extract weekly sales - it's an array of ['label' => 'date', 'units' => number]
            $weeklySalesRaw = $contextData['weekly_sales'] ?? [];
            $weekLabels = array_map(static fn ($week) => $week['label'] ?? '', $weeklySalesRaw);
            $weeklySalesUnits = array_map(static fn ($week) => (float) ($week['units'] ?? 0), $weeklySalesRaw);

            $currentStock = $contextData['current_stock'] ?? 0;
            $minStock = $contextData['min_stock'] ?? 0;

            return [
                'id' => $item->id,
                'labels' => $weekLabels,
                'sales' => $weeklySalesUnits,
                'current' => $currentStock,
                'after' => $currentStock + ($item->final_quantity ?? $item->suggested_quantity ?? 0),
                'minStock' => $minStock,
            ];
        });
    @endphp

    {{-- Chart.js Library --}}
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            // Chart data from server
            const chartData = @json($chartData);

            // Base chart options
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

            // Render charts
            chartData.forEach(config => {
                const canvas = document.getElementById(`chart_item_${config.id}`);
                if (!canvas) return;

                const labels = [...config.labels, 'Current', 'After'];
                const sales = [...config.sales, null, null];
                const minStock = labels.map(() => config.minStock);

                const currentPoint = Array(labels.length).fill(null);
                currentPoint[labels.length - 2] = config.current;

                const afterPoint = Array(labels.length).fill(null);
                afterPoint[labels.length - 1] = config.after;

                new Chart(canvas, {
                    type: 'bar',
                    data: {
                        labels,
                        datasets: [
                            {
                                type: 'bar',
                                label: 'Weekly sales',
                                data: sales,
                                backgroundColor: 'rgba(79, 70, 229, 0.45)',
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
            });

            // Quantity update functionality
            document.querySelectorAll('.order-quantity-input').forEach(input => {
                input.addEventListener('change', async function() {
                    const itemId = this.dataset.itemId;
                    const newQuantity = parseInt(this.value);
                    const unitCost = parseFloat(this.closest('article').querySelector('.cost-impact-value').textContent.replace(/[£,]/g, '')) / parseInt(this.value.split('.')[0] || 1);

                    try {
                        const response = await fetch(`/order-items/${itemId}/quantity`, {
                            method: 'PATCH',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                            },
                            body: JSON.stringify({ quantity: newQuantity }),
                        });

                        if (response.ok) {
                            // Update cost impact
                            const costImpactEl = document.querySelector(`.cost-impact-value[data-item-id="${itemId}"]`);
                            if (costImpactEl) {
                                costImpactEl.textContent = `£${(newQuantity * unitCost).toFixed(2)}`;
                            }
                        } else {
                            alert('Failed to update quantity');
                            location.reload();
                        }
                    } catch (error) {
                        console.error('Error updating quantity:', error);
                        alert('Error updating quantity');
                    }
                });
            });

            // Reset button functionality
            document.querySelectorAll('.reset-quantity-btn').forEach(btn => {
                btn.addEventListener('click', function() {
                    const itemId = this.dataset.itemId;
                    const suggested = parseInt(this.dataset.suggested);
                    const input = document.querySelector(`.order-quantity-input[data-item-id="${itemId}"]`);

                    if (input) {
                        input.value = suggested;
                        input.dispatchEvent(new Event('change'));
                    }
                });
            });
        });
    </script>
</x-admin-layout>
