@php
    $contextData = $contextData ?? [];
    if (is_string($contextData)) {
        $contextData = json_decode($contextData, true) ?? [];
    }

    $minStockOverride = $minStockOverride ?? ($contextData['min_stock_override'] ?? null);
    $initialValue = $minStockOverride !== null && $minStockOverride !== '' ? (string) $minStockOverride : '';
    $canEditMinStock = auth()->user()->hasAnyRole(['admin', 'manager'])
        && isset($product)
        && ($product?->ID ?? null)
        && isset($orderSession)
        && ($orderSession?->id ?? null);
@endphp

@if($canEditMinStock)
    <div
        x-data="{
            editing: false,
            value: '{{ $initialValue }}',
            displayValue: '{{ $initialValue }}',
            saving: false,
            saved: false,
            error: null,
            itemId: '{{ $item->id }}',
            saveMinStock: function() {
                const component = this;
                component.saving = true;
                component.error = null;
                component.saved = false;

                fetch('{{ route('products.update-min-stock-override', $product->ID) }}', {
                    method: 'PATCH',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({
                        min_stock_override: component.value !== '' ? component.value : null,
                        order_session_id: {{ $orderSession->id }},
                    })
                })
                    .then(response => {
                        if (!response.ok) {
                            throw new Error('HTTP error! status: ' + response.status);
                        }

                        return response.json();
                    })
                    .then(data => {
                        if (data.message) {
                            component.displayValue = component.value;
                            component.editing = false;
                            component.saved = true;
                            setTimeout(() => { component.saved = false; }, 2000);

                            if (window.productCharts && window.productCharts[component.itemId]) {
                                const chart = window.productCharts[component.itemId];
                                const newMinStock = parseFloat(component.value) || null;
                                const minStockIndex = chart.data.datasets.findIndex(ds => ds.label === 'Min Stock Override');

                                if (newMinStock !== null && newMinStock > 0) {
                                    const labels = chart.data.labels.slice(0, -2);
                                    const minStockData = labels.map(() => newMinStock).concat([null, null]);

                                    if (minStockIndex >= 0) {
                                        chart.data.datasets[minStockIndex].data = minStockData;
                                    } else {
                                        chart.data.datasets.splice(1, 0, {
                                            label: 'Min Stock Override',
                                            data: minStockData,
                                            borderColor: 'rgb(249, 115, 22)',
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
                                } else if (minStockIndex >= 0) {
                                    chart.data.datasets.splice(minStockIndex, 1);
                                }

                                chart.update('none');
                            }

                            if (data.recalculated) {
                                const recalc = data.recalculated;
                                const isCaseProduct = {{ $isCaseProduct ? 'true' : 'false' }};
                                const caseUnits = {{ $caseUnits }};

                                const suggestedUnits = recalc.suggested_quantity;
                                const afterStock = recalc.after_order_stock;

                                let suggestedDisplay = suggestedUnits.toFixed(0);
                                let quantityLabel = isCaseProduct ? 'cases' : 'units';
                                if (isCaseProduct && caseUnits > 0) {
                                    const cases = suggestedUnits / caseUnits;
                                    suggestedDisplay = cases.toFixed(1);
                                }

                                const afterStockDisplay = afterStock.toFixed(0);
                                let afterSubtext = afterStockDisplay + ' units';
                                if (isCaseProduct && caseUnits > 0) {
                                    const afterCases = (afterStock / caseUnits).toFixed(1);
                                    afterSubtext = afterCases + ' cases';
                                }

                                const suggestedDisplayEl = document.getElementById('suggested-display-' + component.itemId);
                                if (suggestedDisplayEl) {
                                    suggestedDisplayEl.textContent = suggestedDisplay + ' ' + quantityLabel;
                                }

                                const suggestedUnitsEl = document.getElementById('suggested-units-' + component.itemId);
                                if (suggestedUnitsEl) {
                                    suggestedUnitsEl.textContent = suggestedUnits.toFixed(0) + ' units';
                                }

                                const afterStockValueEl = document.getElementById('after-stock-value-' + component.itemId);
                                if (afterStockValueEl) {
                                    afterStockValueEl.textContent = afterStockDisplay;
                                }

                                const afterStockSubtextEl = document.getElementById('after-stock-subtext-' + component.itemId);
                                if (afterStockSubtextEl) {
                                    afterStockSubtextEl.textContent = afterSubtext;
                                }

                                const qtyInputEl = document.getElementById('qty-input-' + component.itemId);
                                if (qtyInputEl) {
                                    qtyInputEl.value = isCaseProduct && caseUnits > 0
                                        ? (suggestedUnits / caseUnits).toFixed(1)
                                        : suggestedUnits.toFixed(0);
                                }
                            }
                        } else {
                            component.error = 'Failed: ' + (data.error || 'Unknown error');
                        }
                    })
                    .catch(err => {
                        component.error = 'Error: ' + err.message;
                        console.error('Min stock update error:', err);
                    })
                    .finally(() => {
                        component.saving = false;
                    });
            }
        }"
        class="mt-3 text-center text-xs text-gray-600 min-stock-editor"
    >
        <div x-show="!editing" class="inline-flex flex-wrap items-center justify-center gap-2" :class="{ 'text-green-600': saved }">
            <span class="uppercase tracking-wide text-[11px] text-slate-400">Min stock</span>
            <span x-show="displayValue === ''" class="text-slate-400">Not set</span>
            <span x-show="displayValue !== ''" class="font-semibold text-orange-600">
                <span x-text="Math.round(displayValue)"></span>
                <span class="text-[11px] uppercase tracking-wide text-orange-500">units</span>
            </span>
            <button
                type="button"
                @@click="editing = true"
                class="inline-flex items-center gap-1 text-orange-600 hover:text-orange-700"
                title="Edit minimum stock override"
            >
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/>
                </svg>
                <span x-text="displayValue === '' ? 'Set' : 'Edit'"></span>
            </button>
            <span x-show="saved" class="inline-flex items-center gap-1 text-green-600">
                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                </svg>
                Saved
            </span>
        </div>

        <div x-show="editing" class="mt-2 flex flex-wrap items-center justify-center gap-2">
            <input
                type="number"
                x-model="value"
                step="1"
                min="0"
                placeholder="Units"
                class="w-20 rounded border border-gray-300 px-2 py-1 text-xs focus:border-orange-500 focus:ring-orange-500"
                @@keydown.enter="saveMinStock()"
                @@keydown.escape="editing = false; value = displayValue"
            >
            <button
                type="button"
                @@click="saveMinStock()"
                :disabled="saving"
                class="inline-flex items-center gap-1 rounded border border-green-500 px-2 py-1 text-[11px] font-semibold text-green-600 hover:bg-green-50 disabled:opacity-50"
            >
                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                </svg>
                Save
            </button>
            <button
                type="button"
                @@click="editing = false; value = displayValue"
                class="inline-flex items-center gap-1 rounded border border-gray-300 px-2 py-1 text-[11px] text-gray-600 hover:bg-gray-50"
            >
                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
                Cancel
            </button>
        </div>

        <p x-show="error" x-text="error" class="mt-1 text-center text-[11px] text-red-600"></p>
    </div>
@elseif(isset($minStockOverride) && $minStockOverride > 0)
    <div class="mt-3 text-center text-xs text-orange-700">
        Min stock override · <span class="font-semibold text-orange-800">{{ number_format($minStockOverride, 0) }} units</span>
    </div>
@endif
