<x-admin-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-lg text-gray-800 leading-tight py-1">
            Supplier Code Lookup
        </h2>
    </x-slot>

    <div class="py-4">
        <div class="max-w-full mx-auto px-2 sm:px-4 lg:px-6">
            <!-- Navigation -->
            <div class="flex items-center justify-between mb-4">
                <a href="{{ route('destock-review.index') }}"
                   class="inline-flex items-center text-sm text-indigo-600 hover:text-indigo-800 font-medium">
                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                    </svg>
                    Destock Review
                </a>
            </div>

            <!-- Input Form -->
            <div class="bg-white shadow-sm sm:rounded-lg p-4 mb-4">
                <form method="POST" action="{{ route('supplier-code-lookup.lookup') }}">
                    @csrf
                    <div class="grid grid-cols-1 lg:grid-cols-4 gap-4 items-end">
                        <div class="lg:col-span-2">
                            <label for="supplier_codes" class="block text-xs font-medium text-gray-500 uppercase">Supplier Codes</label>
                            <textarea name="supplier_codes" id="supplier_codes" rows="3"
                                      placeholder="Paste supplier codes separated by commas, spaces, or new lines...&#10;e.g. 88801, 88910, 6000677, 6001737"
                                      class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500 font-mono">{{ old('supplier_codes', $supplierCodes ?? '') }}</textarea>
                        </div>
                        <div>
                            <label for="supplier_id" class="block text-xs font-medium text-gray-500 uppercase">Supplier</label>
                            <select name="supplier_id" id="supplier_id"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <option value="">All Suppliers</option>
                                @foreach($suppliers as $id => $name)
                                    <option value="{{ $id }}" {{ ($supplierId ?? '') == $id ? 'selected' : '' }}>{{ $name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <button type="submit"
                                    class="inline-flex items-center px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-md hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                                <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                                </svg>
                                Look Up Products
                            </button>
                        </div>
                    </div>
                </form>
            </div>

            @if($errors->any())
                <div class="bg-red-50 border border-red-200 rounded-lg p-3 mb-4">
                    <p class="text-sm text-red-800">{{ $errors->first() }}</p>
                </div>
            @endif

            <!-- Not Found Codes Warning -->
            @if(!empty($notFoundCodes))
                <div class="bg-amber-50 border border-amber-200 rounded-lg p-3 mb-4">
                    <p class="text-sm text-amber-800">
                        <span class="font-medium">{{ count($notFoundCodes) }} code(s) not found{{ $supplierId ? ' for selected supplier' : '' }}:</span>
                        {{ implode(', ', $notFoundCodes) }}
                    </p>
                </div>
            @endif

            <!-- Results -->
            @if(count($inputCodes ?? []) > 0)
                @if($supplierLinks->isEmpty())
                    <div class="bg-white shadow-sm sm:rounded-lg p-8 text-center text-gray-500">
                        <p class="text-lg font-medium">No products found</p>
                        <p class="text-sm mt-1">None of the entered supplier codes matched any products{{ $supplierId ? ' for the selected supplier' : '' }}.</p>
                    </div>
                @else
                    <!-- Results Summary -->
                    <div class="bg-blue-50 border border-blue-200 rounded-lg p-3 mb-4">
                        <p class="text-sm text-blue-800">
                            <span class="font-medium">Found {{ $supplierLinks->count() }} product(s)</span> from {{ count($inputCodes) }} supplier code(s).
                            Sales data shown for the last 3 months (12 weeks).
                        </p>
                    </div>

                    <div class="bg-white shadow-sm sm:rounded-lg overflow-hidden">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase w-12"></th>
                                    <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase">Product</th>
                                    <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase">Supplier Code</th>
                                    <th class="px-3 py-3 text-center text-xs font-medium text-gray-500 uppercase">Total Sales</th>
                                    <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase" style="min-width: 280px;">Stock Levels & Sales Trend</th>
                                    <th class="px-3 py-3 text-center text-xs font-medium text-gray-500 uppercase">Status</th>
                                    <th class="px-3 py-3 text-center text-xs font-medium text-gray-500 uppercase">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                @foreach($supplierLinks as $linkIndex => $link)
                                    @php
                                        $productModel = $products[$link->Barcode] ?? null;
                                        $sales = $salesData[$link->Barcode] ?? null;
                                        $isStocked = $productModel && $productModel->stocking;
                                        $supplierName = null;
                                        $supplierWebLink = null;
                                        if ($productModel) {
                                            $supplierName = $productModel->supplier?->Supplier;
                                            if ($supplierService) {
                                                $supplierWebLink = $supplierService->getSupplierWebsiteLink($productModel);
                                            }
                                        }

                                        // Weekly sales data for chart
                                        $weeklyData = $weeklySalesData[$link->Barcode] ?? [];
                                        $weekLabels = array_map(fn($w) => $w['label'] ?? '', $weeklyData);
                                        $weekUnits = array_map(fn($w) => round((float)($w['units'] ?? 0), 2), $weeklyData);
                                        $totalPeriodSales = array_sum($weekUnits);
                                        $avgWeeklySales = count($weekUnits) > 0 ? $totalPeriodSales / count($weekUnits) : 0;
                                        $peakWeeklySales = count($weekUnits) > 0 ? max($weekUnits) : 0;
                                        $currentStock = $productModel ? (float)$productModel->STOCKUNITS : 0;
                                    @endphp
                                    <tr class="hover:bg-gray-50 {{ !$isStocked ? 'bg-red-50/30' : '' }}" id="row-{{ $link->Barcode }}" style="height: 160px;">
                                        {{-- Image --}}
                                        <td class="px-3 py-2">
                                            @if($productModel)
                                                <x-product-image :product="$productModel" :supplier-service="$supplierService" size="sm" :hover="true" />
                                            @else
                                                <div class="w-8 h-8 bg-gray-100 rounded border border-gray-200 flex items-center justify-center">
                                                    <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                                    </svg>
                                                </div>
                                            @endif
                                        </td>
                                        {{-- Product name + barcode + supplier --}}
                                        <td class="px-3 py-3">
                                            @if($productModel)
                                                <div class="text-sm font-medium text-gray-900">{{ $productModel->NAME }}</div>
                                                <div class="text-xs text-gray-500 font-mono">{{ $link->Barcode }}</div>
                                            @else
                                                <div class="text-sm text-gray-400 italic">Product not found</div>
                                                <div class="text-xs text-gray-500 font-mono">{{ $link->Barcode }}</div>
                                            @endif
                                            <div class="text-xs text-gray-500 mt-1">
                                                @if($supplierWebLink)
                                                    <a href="{{ $supplierWebLink }}" target="_blank" rel="noopener"
                                                       class="text-blue-600 hover:text-blue-800 hover:underline" title="View on supplier website">
                                                        {{ $supplierName }}
                                                        <svg class="inline w-3 h-3 ml-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                                                    </a>
                                                @else
                                                    {{ $supplierName ?? '' }}
                                                @endif
                                            </div>
                                        </td>
                                        {{-- Supplier Code --}}
                                        <td class="px-3 py-3 text-sm text-gray-700 font-mono">{{ $link->SupplierCode }}</td>
                                        {{-- Total Sales --}}
                                        <td class="px-4 py-4">
                                            <div class="text-center">
                                                <div class="text-2xl font-bold text-blue-600">{{ number_format($totalPeriodSales, 0) }}</div>
                                                <div class="text-xs text-gray-500 mb-1">total sold</div>
                                                <div class="mt-2 flex items-center justify-center gap-6 text-xs text-gray-600">
                                                    <span>avg {{ number_format($avgWeeklySales, 1) }}</span>
                                                    <span>peak {{ number_format($peakWeeklySales, 1) }}</span>
                                                </div>
                                            </div>
                                        </td>
                                        {{-- Stock Levels & Sales Trend Chart --}}
                                        <td class="px-4 py-4 align-bottom">
                                            <div class="flex flex-col justify-between gap-2" style="min-height: 130px;">
                                                <div class="relative" style="height: 90px;">
                                                    @if($productModel && !empty($weekLabels))
                                                        <canvas
                                                            id="chart_{{ $linkIndex }}"
                                                            class="w-full h-full cursor-pointer hover:opacity-80 transition-opacity"
                                                            data-product-id="{{ $productModel->ID }}"
                                                            data-product-name="{{ e($productModel->NAME) }}"
                                                            data-sales-weeks="12"
                                                            title="Click to view extended sales history"
                                                        ></canvas>
                                                    @else
                                                        <div class="w-full h-full flex items-center justify-center text-xs text-gray-400">No data</div>
                                                    @endif
                                                </div>
                                                <div class="flex items-center justify-between text-xs text-gray-600">
                                                    <div>
                                                        <div class="text-[11px] uppercase tracking-wide text-slate-500">Current stock</div>
                                                        @php
                                                            $stockColor = 'green';
                                                            if ($peakWeeklySales > 0) {
                                                                $stockPct = ($currentStock / $peakWeeklySales) * 100;
                                                                if ($stockPct <= 25) $stockColor = 'red';
                                                                elseif ($stockPct <= 60) $stockColor = 'yellow';
                                                            } elseif ($currentStock <= 0) {
                                                                $stockColor = 'red';
                                                            }
                                                        @endphp
                                                        <div class="text-lg font-semibold text-{{ $stockColor }}-600">{{ number_format($currentStock, 0) }}</div>
                                                        <div class="text-[11px] text-gray-500">{{ number_format($currentStock, 0) }} units</div>
                                                    </div>
                                                    @if($sales)
                                                        <div class="text-right">
                                                            <div class="text-[11px] uppercase tracking-wide text-slate-500">Revenue</div>
                                                            <div class="text-lg font-semibold text-gray-700">&euro;{{ number_format($sales->total_revenue, 0) }}</div>
                                                            <div class="text-[11px] text-gray-500">{{ $sales->days_with_sales }} days with sales</div>
                                                        </div>
                                                    @endif
                                                </div>
                                            </div>
                                        </td>
                                        {{-- Stocked/Destocked badge --}}
                                        <td class="px-3 py-3 text-center" id="status-{{ $link->Barcode }}">
                                            @if($isStocked)
                                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">Stocked</span>
                                            @else
                                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">Destocked</span>
                                            @endif
                                        </td>
                                        {{-- Actions --}}
                                        <td class="px-3 py-3 text-center">
                                            @if($productModel)
                                                <div class="flex flex-col items-center gap-1.5">
                                                    <div class="flex items-center gap-1.5">
                                                        <button onclick="showSalesChartModal('{{ $productModel->ID }}', '{{ addslashes($productModel->NAME) }}')"
                                                                class="inline-flex items-center p-1.5 text-indigo-600 hover:text-indigo-900 hover:bg-indigo-50 rounded transition-colors"
                                                                title="View Sales History">
                                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                                                            </svg>
                                                        </button>
                                                        <a href="{{ route('products.edit', $productModel->ID) }}"
                                                           class="inline-flex items-center p-1.5 text-blue-600 hover:text-blue-900 hover:bg-blue-50 rounded transition-colors"
                                                           title="View Product">
                                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                                            </svg>
                                                        </a>
                                                    </div>
                                                    <button
                                                        type="button"
                                                        class="toggle-stock-btn inline-flex items-center px-2.5 py-1 text-xs font-medium rounded-md focus:outline-none focus:ring-2 transition-colors {{ $isStocked ? 'bg-red-600 text-white hover:bg-red-700 focus:ring-red-500' : 'bg-green-600 text-white hover:bg-green-700 focus:ring-green-500' }}"
                                                        data-product-id="{{ $productModel->ID }}"
                                                        data-product-name="{{ $productModel->NAME }}"
                                                        data-barcode="{{ $link->Barcode }}"
                                                        data-is-stocked="{{ $isStocked ? '1' : '0' }}"
                                                        onclick="toggleStocking(this)"
                                                    >
                                                        @if($isStocked)
                                                            <svg class="w-3.5 h-3.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 12H4"/>
                                                            </svg>
                                                            Destock
                                                        @else
                                                            <svg class="w-3.5 h-3.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                                                            </svg>
                                                            Restock
                                                        @endif
                                                    </button>
                                                </div>
                                            @else
                                                <span class="text-xs text-gray-400">N/A</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            @endif
        </div>
    </div>

    {{-- Sales Chart Modal --}}
    <x-sales-chart-modal />

    @push('scripts')
    @once
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    @endonce
    <script>
        // Toggle stocking handler
        async function toggleStocking(button) {
            const productId = button.dataset.productId;
            const productName = button.dataset.productName;
            const barcode = button.dataset.barcode;
            const isCurrentlyStocked = button.dataset.isStocked === '1';
            const newState = !isCurrentlyStocked;
            const actionLabel = newState ? 'restock' : 'destock';

            const message = isCurrentlyStocked
                ? `Destock "${productName}"?\n\nThis will remove it from stock management and future orders.`
                : `Restock "${productName}"?\n\nThis will add it back to stock management.`;

            if (!confirm(message)) return;

            button.disabled = true;
            const originalHtml = button.innerHTML;
            button.innerHTML = '<svg class="w-3.5 h-3.5 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>';

            try {
                const response = await fetch(`/products/${productId}/toggle-stocking`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({
                        include_in_stocking: newState,
                        source: 'supplier_code_lookup'
                    })
                });

                const data = await response.json();

                if (data.success) {
                    button.dataset.isStocked = newState ? '1' : '0';

                    if (newState) {
                        button.className = 'toggle-stock-btn inline-flex items-center px-2.5 py-1 text-xs font-medium rounded-md focus:outline-none focus:ring-2 transition-colors bg-red-600 text-white hover:bg-red-700 focus:ring-red-500';
                        button.innerHTML = '<svg class="w-3.5 h-3.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 12H4"/></svg> Destock';
                    } else {
                        button.className = 'toggle-stock-btn inline-flex items-center px-2.5 py-1 text-xs font-medium rounded-md focus:outline-none focus:ring-2 transition-colors bg-green-600 text-white hover:bg-green-700 focus:ring-green-500';
                        button.innerHTML = '<svg class="w-3.5 h-3.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg> Restock';
                    }

                    const statusCell = document.getElementById('status-' + barcode);
                    if (statusCell) {
                        if (newState) {
                            statusCell.innerHTML = '<span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">Stocked</span>';
                        } else {
                            statusCell.innerHTML = '<span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">Destocked</span>';
                        }
                    }

                    button.disabled = false;
                } else {
                    alert(`Failed to ${actionLabel}: ` + (data.error || 'Unknown error'));
                    button.disabled = false;
                    button.innerHTML = originalHtml;
                }
            } catch (error) {
                alert(`Error: ` + error.message);
                button.disabled = false;
                button.innerHTML = originalHtml;
            }
        }

        // Render inline sales charts
        document.addEventListener('DOMContentLoaded', function() {
            @if($supplierLinks->isNotEmpty())
                @foreach($supplierLinks as $linkIndex => $link)
                    @php
                        $productModel = $products[$link->Barcode] ?? null;
                        $weeklyData = $weeklySalesData[$link->Barcode] ?? [];
                        $weekLabelsChart = array_map(fn($w) => $w['label'] ?? '', $weeklyData);
                        $weekUnitsChart = array_map(fn($w) => round((float)($w['units'] ?? 0), 2), $weeklyData);
                        $avgWeeklyChart = count($weekUnitsChart) > 0 ? array_sum($weekUnitsChart) / count($weekUnitsChart) : 0;
                        $currentStockChart = $productModel ? (float)$productModel->STOCKUNITS : 0;
                    @endphp
                    @if($productModel && !empty($weekLabelsChart))
                    (function() {
                        const chartEl = document.getElementById('chart_{{ $linkIndex }}');
                        if (!chartEl) return;

                        const labels = @json($weekLabelsChart);
                        const dataPoints = @json($weekUnitsChart);
                        const averageUnits = {{ round($avgWeeklyChart, 2) }};
                        const currentStock = {{ $currentStockChart }};

                        const extendedLabels = labels.concat(['Current stock']);
                        const salesData = dataPoints.concat([null]);
                        const averageLineData = labels.map(() => averageUnits).concat([null]);

                        const primaryColor = 'rgb(234, 179, 8)'; // Amber/yellow
                        const greyColor = 'rgb(203, 213, 225)';
                        const pointColors = dataPoints.map(value => value === 0 ? greyColor : primaryColor);
                        const pointBorderColors = dataPoints.map(value => value === 0 ? greyColor : primaryColor);
                        const pointRadius = dataPoints.map(value => value === 0 ? 2 : 3);
                        pointColors.push('transparent');
                        pointBorderColors.push('transparent');
                        pointRadius.push(0);

                        const currentStockData = Array(extendedLabels.length).fill(null);
                        currentStockData[extendedLabels.length - 1] = currentStock;

                        const salesMax = dataPoints.length ? Math.max(...dataPoints) : 0;
                        const chartMax = Math.max(salesMax, currentStock, averageUnits, 1);
                        const chartMin = Math.min(0, currentStock);

                        const datasets = [{
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
                            backgroundColor: 'rgba(234, 179, 8, 0.2)',
                            pointBackgroundColor: pointColors,
                            pointBorderColor: pointBorderColors,
                            pointRadius: pointRadius,
                            pointHoverRadius: pointRadius.map(v => v ? v + 2 : 0),
                            segment: {
                                borderColor: ctx => {
                                    if (ctx.p0.parsed.y === 0 || ctx.p1.parsed.y === 0) return greyColor;
                                    return primaryColor;
                                }
                            },
                            tension: 0.4,
                            fill: {
                                target: 'origin',
                                above: 'rgba(234, 179, 8, 0.12)',
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
                            pointBackgroundColor: currentStock > 0 ? 'rgb(34, 197, 94)' : 'rgb(239, 68, 68)',
                            pointBorderColor: 'white',
                            borderColor: 'transparent',
                            order: 2
                        }];

                        new Chart(chartEl, {
                            type: 'line',
                            data: { labels: extendedLabels, datasets: datasets },
                            options: {
                                responsive: true,
                                maintainAspectRatio: false,
                                layout: { padding: 0 },
                                interaction: { mode: 'index', intersect: false },
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
                                                    if (Number.isNaN(context.parsed.y)) return null;
                                                    return `Avg weekly: ${averageUnits.toFixed(1)} units`;
                                                }
                                                if (context.dataset.label === 'Sales') {
                                                    const value = Math.round(context.parsed.y);
                                                    if (Number.isNaN(value)) return null;
                                                    if (value === 0) return '0 units sold';
                                                    return value + ' units sold';
                                                }
                                                if (context.dataset.label === 'Current stock') {
                                                    const units = Math.round(context.parsed.y);
                                                    return `Current stock: ${units} units`;
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
                                elements: { point: { radius: 3, hoverRadius: 5 } }
                            }
                        });
                    })();
                    @endif
                @endforeach
            @endif

            // Open sales-history modal when an inline chart is clicked
            document.querySelectorAll('canvas[data-product-id]').forEach(function(canvas) {
                canvas.addEventListener('click', function(e) {
                    e.stopPropagation();
                    const productId = this.dataset.productId;
                    const productName = this.dataset.productName || 'Product';
                    const salesWeeks = parseInt(this.dataset.salesWeeks, 10) || 12;
                    if (!productId || typeof window.showSalesChartModal !== 'function') return;
                    window.showSalesChartModal(productId, productName, salesWeeks);
                });
            });
        });
    </script>
    @endpush
</x-admin-layout>
