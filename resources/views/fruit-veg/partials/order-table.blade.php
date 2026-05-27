@php
    $categoryColor = $categoryColor ?? 'gray';
@endphp

<table class="min-w-full divide-y divide-gray-200">
    <thead class="bg-gray-50">
        <tr>
            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase w-12">Image</th>
            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Product</th>
            <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase w-32">Total Sales</th>
            <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase" style="width: 320px;">Weekly Sales Trend</th>
            <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase w-48">Suggested Order</th>
        </tr>
    </thead>
    <tbody class="bg-white divide-y divide-gray-200">
        @foreach($products as $index => $item)
        @php
            $product = $item['product'];
            $salesData = $item['sales_data'];
            $safeProductName = strip_tags(html_entity_decode($product->NAME ?? 'Unknown Product'));
            $totalSales = $salesData['total_units'] ?? 0;
            $avgWeekly = $salesData['avg_weekly'] ?? 0;
            $peakWeekly = $salesData['peak_weekly'] ?? 0;
            $suggestedQty = $salesData['suggested_qty'] ?? 0;
            $weekLabels = $salesData['week_labels'] ?? [];
            $weekUnits = $salesData['week_units'] ?? [];
            $productCode = $product->CODE ?? 'N/A';
            $chartId = $categoryColor . '_' . $index;
        @endphp
        <tr class="hover:bg-{{ $categoryColor }}-50 border-l-4 border-{{ $categoryColor }}-300" style="height: 160px;">
            <!-- Image -->
            <td class="px-4 py-3">
                <div class="w-10 h-10 rounded overflow-hidden bg-gray-100">
                    <img src="{{ route('fruit-veg.product-image', $productCode) }}"
                         alt="{{ $safeProductName }}"
                         class="w-full h-full object-cover"
                         onerror="this.style.display='none'">
                </div>
            </td>

            <!-- Product Info -->
            <td class="px-4 py-3">
                <div class="font-medium text-gray-900">
                    <a href="{{ route('fruit-veg.product.edit', $productCode) }}"
                       class="text-indigo-600 hover:text-indigo-800"
                       target="_blank"
                       rel="noopener"
                       title="Edit {{ $safeProductName }}">
                        {!! $safeProductName !!}
                    </a>
                </div>
                <div class="text-sm text-gray-500">
                    Code: {{ $productCode }}
                </div>
                @if($product->vegDetails && $product->vegDetails->country)
                    <div class="text-xs text-gray-400 mt-1">
                        Origin: {{ $product->vegDetails->country->name ?? 'N/A' }}
                    </div>
                @endif
            </td>

            <!-- Total Sales -->
            <td class="px-4 py-3">
                <div class="text-center">
                    <div class="text-2xl font-bold text-blue-600">{{ rtrim(rtrim(number_format($totalSales, 2), '0'), '.') }}</div>
                    <div class="text-xs text-gray-500 mb-1">total sold</div>
                    <div class="mt-2 flex items-center justify-center gap-4 text-xs text-gray-600">
                        <span>avg {{ number_format($avgWeekly, 1) }}/wk</span>
                        <span>peak {{ number_format($peakWeekly, 1) }}</span>
                    </div>
                </div>
            </td>

            <!-- Weekly Sales Chart -->
            <td class="px-4 py-3">
                <div style="height: 110px;">
                    <canvas
                        id="chart_{{ $chartId }}"
                        data-week-labels="{{ json_encode($weekLabels) }}"
                        data-week-units="{{ json_encode($weekUnits) }}"
                        data-avg-weekly="{{ $avgWeekly }}"
                        data-peak-weekly="{{ $peakWeekly }}"
                        class="w-full h-full"
                    ></canvas>
                </div>
            </td>

            <!-- Suggested Order -->
            <td class="px-4 py-3">
                <div class="flex flex-col items-center gap-2">
                    <div class="text-center">
                        <div class="text-[11px] font-semibold text-gray-500 uppercase tracking-wide">Suggested</div>
                        <div class="mt-1 text-xl font-bold text-purple-700">
                            {{ number_format($suggestedQty, 0) }}
                        </div>
                        <div class="text-xs text-gray-500">units</div>
                    </div>
                    <div class="flex items-center justify-center gap-1">
                        <button class="qty-decrease w-8 h-8 bg-red-100 hover:bg-red-200 text-red-700 rounded font-bold"
                                type="button"
                                data-product-code="{{ $productCode }}">−</button>
                        <input type="number"
                               id="qty-{{ $productCode }}"
                               value="{{ number_format($suggestedQty, 0) }}"
                               step="1"
                               min="0"
                               data-product-code="{{ $productCode }}"
                               class="qty-input w-20 text-center text-lg font-bold border-2 border-gray-300 rounded py-1">
                        <button class="qty-increase w-8 h-8 bg-green-100 hover:bg-green-200 text-green-700 rounded font-bold"
                                type="button"
                                data-product-code="{{ $productCode }}">+</button>
                    </div>
                </div>
            </td>
        </tr>
        @endforeach
    </tbody>
</table>

@pushOnce('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Quantity adjustment buttons
        document.querySelectorAll('.qty-decrease').forEach(btn => {
            btn.addEventListener('click', function() {
                const code = this.dataset.productCode;
                const input = document.getElementById('qty-' + code);
                if (input) {
                    const currentVal = parseInt(input.value) || 0;
                    input.value = Math.max(0, currentVal - 1);
                }
            });
        });

        document.querySelectorAll('.qty-increase').forEach(btn => {
            btn.addEventListener('click', function() {
                const code = this.dataset.productCode;
                const input = document.getElementById('qty-' + code);
                if (input) {
                    const currentVal = parseInt(input.value) || 0;
                    input.value = currentVal + 1;
                }
            });
        });
    });
</script>
@endPushOnce
