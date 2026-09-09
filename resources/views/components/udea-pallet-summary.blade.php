{{--
    Running pallet-space total for a Udea order, mirroring the calculator on Udea's own
    basket page.

    Udea's maths (js/orders/order.js:1556):
        lineVolume = sve * volume * qty
        linePct    = round(lineVolume / totalPalletVolume * 100, 2)   // rounded PER LINE
        totalPct   = sum(linePct)
        totalPalletVolume = numEuro * euroCapacity + numBlock * blockCapacity

    Each quantity input in the review table carries data-pallet-volume="sve * volume", so the
    running total is just sum(input.value * data-pallet-volume). Rounding per line before
    summing is what reproduces Udea's displayed figure rather than being ~0.02% out.

    Inputs:
      $coverage  – ['withData' => int, 'total' => int] products with/without pallet data
--}}
@props(['coverage' => ['withData' => 0, 'total' => 0]])

@php
    $pallet = config('suppliers.external_links.udea.pallet', ['euro' => 250, 'block' => 360]);
    $missing = max(($coverage['total'] ?? 0) - ($coverage['withData'] ?? 0), 0);
@endphp

<div class="bg-white shadow-sm rounded-lg p-4"
     x-data="udeaPalletCalc({ euro: {{ (float) $pallet['euro'] }}, block: {{ (float) $pallet['block'] }} })"
     x-init="init()">

    <div class="flex flex-wrap items-center justify-between gap-4">

        {{-- Pallet selectors --}}
        <div class="flex items-center gap-4">
            <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">Europallets</label>
                <div class="flex items-center gap-1">
                    <button type="button" @click="euro = Math.max(0, euro - 1); recalc()"
                            class="w-7 h-7 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded font-bold leading-none">−</button>
                    <input type="number" min="0" step="1" x-model.number="euro" @input="recalc()"
                           class="w-14 text-center font-bold border-2 border-gray-300 rounded py-1 text-sm">
                    <button type="button" @click="euro++; recalc()"
                            class="w-7 h-7 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded font-bold leading-none">+</button>
                </div>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">Blockpallets</label>
                <div class="flex items-center gap-1">
                    <button type="button" @click="block = Math.max(0, block - 1); recalc()"
                            class="w-7 h-7 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded font-bold leading-none">−</button>
                    <input type="number" min="0" step="1" x-model.number="block" @input="recalc()"
                           class="w-14 text-center font-bold border-2 border-gray-300 rounded py-1 text-sm">
                    <button type="button" @click="block++; recalc()"
                            class="w-7 h-7 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded font-bold leading-none">+</button>
                </div>
            </div>
        </div>

        {{-- Fill --}}
        <div class="flex items-center gap-4 flex-1 min-w-[260px]">
            <div class="flex-1">
                <div class="flex items-baseline justify-between mb-1">
                    <span class="text-xs font-medium text-gray-500">Pallet fill</span>
                    <span class="text-xs text-gray-400"
                          x-text="volume.toFixed(2) + ' / ' + capacity.toFixed(0) + ' volume units'"></span>
                </div>
                <div class="w-full bg-gray-200 rounded-full h-3 overflow-hidden">
                    <div class="h-3 rounded-full transition-all duration-300"
                         :class="percent > 100 ? 'bg-red-500' : (percent > 90 ? 'bg-amber-500' : 'bg-green-500')"
                         :style="'width: ' + Math.min(percent, 100) + '%'"></div>
                </div>
            </div>
            <div class="text-right">
                <div class="text-2xl font-bold leading-none"
                     :class="percent > 100 ? 'text-red-600' : (percent > 90 ? 'text-amber-600' : 'text-green-600')"
                     x-text="capacity > 0 ? percent.toFixed(2) + '%' : '—'"></div>
                <div class="text-xs text-gray-500 mt-1"
                     x-text="capacity > 0 ? (percent > 100 ? 'Over capacity' : 'of selected pallets') : 'Select pallets'"></div>
            </div>
        </div>
    </div>

    {{-- Caveats --}}
    <div class="mt-3 flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-gray-500">
        <span x-show="counted > 0" x-cloak>
            <span x-text="counted"></span> lines counted
        </span>
        <span x-show="uncounted > 0" x-cloak class="text-amber-600">
            <span x-text="uncounted"></span> line(s) with a quantity have no pallet data &mdash; total is understated
        </span>
        @if($missing > 0)
            <span class="text-amber-600">
                {{ number_format($missing) }} of {{ number_format($coverage['total']) }} products on this order lack pallet data.
                <a href="{{ route('tools.udea-pallet-volumes') }}" class="underline">Sync from the Udea basket</a>
            </span>
        @endif
        <span class="text-gray-400">
            Europallet {{ $pallet['euro'] }} &middot; blockpallet {{ $pallet['block'] }}
        </span>
    </div>
</div>

@once
    @push('scripts')
    <script>
        function udeaPalletCalc(capacities) {
            return {
                euro: 1,
                block: 0,
                volume: 0,
                percent: 0,
                capacity: 0,
                counted: 0,
                uncounted: 0,

                init() {
                    // Restore the operator's pallet selection between visits.
                    try {
                        const saved = JSON.parse(localStorage.getItem('udea_pallet_selection') || 'null');
                        if (saved && Number.isFinite(saved.euro) && Number.isFinite(saved.block)) {
                            this.euro = saved.euro;
                            this.block = saved.block;
                        }
                    } catch (e) { /* ignore - a bad or blocked store just means defaults */ }

                    // Quantity inputs are updated by the review table's own handlers (and by
                    // the case-snap buttons, which set .value directly), so listen broadly.
                    ['input', 'change'].forEach(evt => {
                        document.addEventListener(evt, e => {
                            if (e.target && e.target.classList && e.target.classList.contains('qty-input')) {
                                this.recalc();
                            }
                        }, true);
                    });

                    this.recalc();
                },

                recalc() {
                    let volume = 0, counted = 0, uncounted = 0, pctSum = 0;

                    this.capacity = (this.euro || 0) * capacities.euro + (this.block || 0) * capacities.block;

                    document.querySelectorAll('.qty-input').forEach(input => {
                        const qty = parseFloat(input.value);
                        if (!Number.isFinite(qty) || qty <= 0) return;

                        const perUnit = parseFloat(input.dataset.palletVolume);
                        if (!Number.isFinite(perUnit)) { uncounted++; return; }

                        const lineVolume = perUnit * qty;
                        volume += lineVolume;
                        counted++;

                        // Udea rounds each line to 2dp before summing; summing raw and
                        // rounding once gives a slightly different figure to their page.
                        if (this.capacity > 0) {
                            pctSum += Math.round(lineVolume / this.capacity * 100 * 100) / 100;
                        }
                    });

                    this.volume = volume;
                    this.counted = counted;
                    this.uncounted = uncounted;
                    this.percent = this.capacity > 0 ? pctSum : 0;

                    try {
                        localStorage.setItem('udea_pallet_selection', JSON.stringify({ euro: this.euro, block: this.block }));
                    } catch (e) { /* ignore */ }
                },
            }
        }
    </script>
    @endpush
@endonce
