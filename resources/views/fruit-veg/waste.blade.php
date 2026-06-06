<x-admin-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    {{ __('Log Fruit & Veg Waste') }}
                </h2>
                <p class="text-sm text-gray-500">Record spoiled or discarded stock across the full range</p>
            </div>
            <div class="flex items-center gap-3">
                <a href="{{ route('fruit-veg.waste.history') }}"
                   class="text-sm text-indigo-600 hover:text-indigo-800">Waste report &rarr;</a>
                <a href="{{ route('fruit-veg.index') }}"
                   class="text-sm text-gray-500 hover:text-gray-700">&larr; Back to F&amp;V</a>
            </div>
        </div>
    </x-slot>

    <div class="py-6" x-data="wasteLog({
            rows: {{ Js::from($rows) }},
            date: '{{ $selectedDate }}',
            entryUrl: '{{ route('fruit-veg.waste.entry') }}',
            searchUrl: '{{ route('fruit-veg.waste.search') }}',
            csrf: '{{ csrf_token() }}',
        })">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">

            @if (session('success'))
                <div class="mb-6 rounded-lg bg-green-50 border border-green-200 px-4 py-3 text-sm text-green-800">
                    {{ session('success') }}
                </div>
            @endif

            <!-- Date selector + search -->
            <div class="flex flex-col sm:flex-row gap-4 mb-5">
                <form method="GET" action="{{ route('fruit-veg.waste') }}" class="flex items-center gap-2">
                    <div class="flex items-center gap-2 bg-white border border-gray-300 rounded-lg px-3 py-2 shadow-sm">
                        <svg class="h-4 w-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                        </svg>
                        <input type="date" name="date" value="{{ $selectedDate }}" max="{{ now()->toDateString() }}"
                               class="border-0 p-0 text-sm font-medium text-gray-700 focus:ring-0">
                    </div>
                    <button type="submit"
                            class="px-3 py-2 bg-gray-700 text-white text-sm rounded-lg hover:bg-gray-800 transition">
                        Load
                    </button>
                </form>

                <!-- Search-all bar -->
                <div class="relative flex-1">
                    <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                        <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                        </svg>
                    </div>
                    <input type="text" x-model="search" @input.debounce.400ms="runSearch()"
                           placeholder="Search all fruit &amp; veg — name, code or category…"
                           class="block w-full pl-11 pr-24 py-3 bg-white border border-gray-300 rounded-xl shadow-sm placeholder-gray-400
                                  focus:outline-none focus:border-[#c2410c] focus:ring-2 focus:ring-[#c2410c]/20 text-[15px]">
                    <button x-show="search.length > 0" x-cloak @click="clearSearch()"
                            class="absolute inset-y-0 right-3 my-auto h-7 w-7 flex items-center justify-center rounded-full bg-gray-100 text-gray-500 hover:bg-gray-200"
                            title="Clear search">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                    <span x-show="search.length === 0"
                          class="absolute inset-y-0 right-4 flex items-center text-xs text-gray-400 pointer-events-none">
                        Full range
                    </span>
                </div>
            </div>

            <!-- Product table -->
            <div class="bg-white rounded-lg shadow overflow-hidden">
                <div class="max-h-[60vh] overflow-y-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead>
                            <tr>
                                <th class="sticky top-0 z-10 bg-gray-50 px-2 sm:px-4 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wider" colspan="2">Item</th>
                                <th class="sticky top-0 z-10 bg-gray-50 px-2 sm:px-4 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wider w-24 hidden md:table-cell">Price</th>
                                <th class="sticky top-0 z-10 bg-gray-50 px-2 sm:px-4 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wider md:w-72">Amount to waste</th>
                                <th class="sticky top-0 z-10 bg-gray-50 px-2 sm:px-4 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wider w-24 hidden lg:table-cell">Value</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <template x-for="row in visibleRows" :key="row.code">
                                <tr :class="row.quantity > 0
                                        ? 'bg-orange-50 shadow-[inset_3px_0_0_#c2410c]'
                                        : 'bg-white'"
                                    class="transition-colors">
                                    <!-- Thumbnail -->
                                    <td class="pl-3 sm:pl-5 pr-1 py-3 w-12 sm:w-14">
                                        <div class="w-9 h-9 sm:w-11 sm:h-11 rounded-lg overflow-hidden bg-gray-100 flex items-center justify-center">
                                            <img :src="'/fruit-veg/product-image/' + row.code"
                                                 :alt="row.name"
                                                 class="w-full h-full object-cover"
                                                 loading="lazy"
                                                 @@error="$el.style.display='none'; $el.nextElementSibling.style.display='flex'"
                                                 @@load="if($el.naturalWidth === 1 && $el.naturalHeight === 1) { $el.style.display='none'; $el.nextElementSibling.style.display='flex'; }">
                                            <div class="hidden w-full h-full items-center justify-center text-gray-400">
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                          d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                                                </svg>
                                            </div>
                                        </div>
                                    </td>
                                    <!-- Product -->
                                    <td class="px-2 sm:px-4 py-3 min-w-0">
                                        <div class="flex flex-wrap items-center gap-x-2 gap-y-0.5">
                                            <span class="text-sm font-semibold text-blue-600" x-text="row.name"></span>
                                            <span x-show="row.on_till"
                                                  class="inline-block px-1.5 py-0.5 rounded text-[10px] font-bold uppercase tracking-wide bg-green-100 text-green-800">On till</span>
                                            <span x-show="!row.on_till"
                                                  class="inline-block px-1.5 py-0.5 rounded text-[10px] font-bold uppercase tracking-wide bg-orange-100 text-orange-800">Full range</span>
                                        </div>
                                        <div class="text-xs text-gray-400 truncate hidden sm:block" x-text="meta(row)"></div>
                                        <!-- Mobile-only: price (and code) shown under the name since the columns are hidden -->
                                        <div class="text-xs text-gray-500 md:hidden">
                                            <span class="font-semibold text-gray-700" x-text="'€' + row.current_price.toFixed(2) + '/' + (row.priced_unit === 'kg' ? 'kg' : 'unit')"></span>
                                            <span class="text-gray-400 sm:hidden" x-text="' · ' + row.code"></span>
                                        </div>
                                    </td>
                                    <!-- Price -->
                                    <td class="px-2 sm:px-4 py-3 whitespace-nowrap hidden md:table-cell">
                                        <span class="text-sm font-semibold text-gray-900" x-text="'€' + row.current_price.toFixed(2)"></span><span class="text-xs text-gray-400" x-text="'/' + (row.priced_unit === 'kg' ? 'kg' : 'unit')"></span>
                                    </td>
                                    <!-- Amount -->
                                    <td class="px-2 sm:px-4 py-3">
                                        <div class="flex flex-wrap items-center gap-1.5 sm:gap-2">
                                            <div class="flex items-center h-9 rounded-lg border overflow-hidden bg-white"
                                                 :class="row.quantity > 0 ? 'border-[#c2410c] bg-orange-50' : 'border-gray-300'">
                                                <button type="button" x-show="row.unit === 'unit'" tabindex="-1"
                                                        @click="step(row, -1)" :disabled="!(row.quantity > 0)"
                                                        class="w-8 self-stretch flex items-center justify-center bg-gray-100 text-gray-500 hover:bg-gray-200 hover:text-gray-700 disabled:opacity-40">
                                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" d="M5 12h14"/></svg>
                                                </button>
                                                <input type="number" inputmode="decimal" min="0" :step="row.unit === 'unit' ? 1 : 0.1"
                                                       placeholder="0"
                                                       :value="row.quantity > 0 ? row.quantity : ''"
                                                       @input="onType(row, $event.target.value)"
                                                       class="w-14 sm:w-16 h-full border-0 text-center text-[15px] font-bold text-gray-900 focus:ring-0 p-0 [appearance:textfield] [&::-webkit-outer-spin-button]:appearance-none [&::-webkit-inner-spin-button]:appearance-none"
                                                       :class="row.quantity > 0 ? 'bg-orange-50' : 'bg-white'">
                                                <button type="button" x-show="row.unit === 'unit'" tabindex="-1"
                                                        @click="step(row, 1)"
                                                        class="w-8 self-stretch flex items-center justify-center bg-gray-100 text-gray-500 hover:bg-gray-200 hover:text-gray-700">
                                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" d="M12 5v14M5 12h14"/></svg>
                                                </button>
                                                <span class="px-2 text-xs font-semibold border-l self-stretch flex items-center"
                                                      :class="row.quantity > 0 ? 'text-[#c2410c] border-orange-200' : 'text-gray-400 border-gray-200'"
                                                      x-text="row.unit === 'kg' ? 'kg' : 'units'"></span>
                                            </div>
                                            <!-- kg / units toggle -->
                                            <div class="flex h-9 rounded-lg border border-gray-300 overflow-hidden" role="group" title="Log by weight or by units">
                                                <button type="button" @click="setUnit(row, 'kg')"
                                                        class="px-2.5 text-xs font-semibold flex items-center gap-1"
                                                        :class="row.unit === 'kg' ? 'bg-[#c2410c] text-white' : 'bg-white text-gray-400 hover:text-gray-600'">
                                                    kg
                                                </button>
                                                <button type="button" @click="setUnit(row, 'unit')"
                                                        class="px-2.5 text-xs font-semibold flex items-center gap-1 border-l border-gray-300"
                                                        :class="row.unit === 'unit' ? 'bg-[#c2410c] text-white' : 'bg-white text-gray-400 hover:text-gray-600'">
                                                    units
                                                </button>
                                            </div>
                                            <!-- Save state -->
                                            <span x-show="saveState[row.code] === 'saving'" x-cloak class="text-xs text-gray-400">saving…</span>
                                            <span x-show="saveState[row.code] === 'saved'" x-cloak class="text-green-600">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M20 6L9 17l-5-5"/></svg>
                                            </span>
                                            <span x-show="saveState[row.code] === 'error'" x-cloak class="text-xs text-red-600 font-medium" title="Could not save — retry">failed</span>
                                        </div>
                                    </td>
                                    <!-- Value -->
                                    <td class="px-4 py-3 whitespace-nowrap hidden lg:table-cell">
                                        <span class="text-sm font-bold" :class="estValueRaw(row) > 0 ? 'text-[#c2410c]' : 'text-gray-300'"
                                              x-text="estValue(row)"></span>
                                    </td>
                                </tr>
                            </template>
                            <tr x-show="visibleRows.length === 0 && !searching">
                                <td colspan="5" class="px-6 py-12 text-center text-sm text-gray-400">
                                    <span x-show="search.length > 0">No products match "<span x-text="search"></span>".</span>
                                    <span x-show="search.length === 0">No till-visible fruit &amp; veg products found.</span>
                                </td>
                            </tr>
                            <tr x-show="searching" x-cloak>
                                <td colspan="5" class="px-6 py-6 text-center text-sm text-gray-400">Searching full range…</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Totals bar -->
            <div class="sticky bottom-0 mt-4 bg-white border border-gray-200 rounded-lg shadow-lg px-4 sm:px-6 py-3 sm:py-4 flex flex-wrap items-center gap-x-5 sm:gap-x-8 gap-y-2 sm:gap-y-3">
                <div>
                    <div class="text-xl font-extrabold tabular-nums text-gray-900" x-text="totalItems"></div>
                    <div class="text-[11px] font-semibold uppercase tracking-wide text-gray-400">items</div>
                </div>
                <div>
                    <div class="text-xl font-extrabold tabular-nums text-gray-900">
                        <span x-text="fmtQty(totalKg)"></span><span class="text-sm font-bold text-gray-400">kg</span>
                        <span x-show="totalUnits > 0" class="text-xs font-semibold text-gray-400">+ <span x-text="fmtQty(totalUnits)"></span> units</span>
                    </div>
                    <div class="text-[11px] font-semibold uppercase tracking-wide text-gray-400">logged as waste</div>
                </div>
                <div>
                    <div class="text-xl font-extrabold tabular-nums text-[#c2410c]" x-text="'€' + totalValue.toFixed(2)"></div>
                    <div class="text-[11px] font-semibold uppercase tracking-wide text-gray-400">est. value lost</div>
                </div>
                <div class="flex-1"></div>
                <span class="text-xs text-gray-400 hidden sm:inline">Entries save automatically as you type</span>
                <a href="{{ route('fruit-veg.waste.history') }}"
                   class="inline-flex items-center gap-2 px-4 py-2.5 bg-[#c2410c] text-white text-sm font-semibold rounded-lg hover:bg-[#9a3412] transition shadow-sm">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24">
                        <path stroke-linecap="round" d="M4 20V10M10 20V4M16 20v-7M22 20H2"/>
                    </svg>
                    Waste report
                </a>
            </div>
        </div>
    </div>

    @push('scripts')
    <script>
        function wasteLog({ rows, date, entryUrl, searchUrl, csrf }) {
            return {
                rows,                 // [{ code, name, category, origin, class, on_till, current_price, priced_unit, quantity, unit, value }]
                date,
                search: '',
                searching: false,
                searchCodes: null,    // codes matching the current search (null = no active search)
                saveState: {},        // code -> 'saving' | 'saved' | 'error'
                saveTimers: {},
                stateTimers: {},

                get visibleRows() {
                    const q = this.search.trim().toLowerCase();
                    if (!q) return this.rows;
                    // Local match is instant; searchCodes adds server matches
                    // (e.g. on DISPLAY name) once the full-range search returns.
                    return this.rows.filter(r =>
                        [r.name, r.code, r.category, r.origin].filter(Boolean).join(' ').toLowerCase().includes(q)
                        || (this.searchCodes !== null && this.searchCodes.has(r.code)));
                },
                get totalItems() {
                    return this.rows.filter(r => r.quantity > 0).length;
                },
                get totalKg() {
                    return this.rows.filter(r => r.quantity > 0 && r.unit === 'kg')
                        .reduce((s, r) => s + r.quantity, 0);
                },
                get totalUnits() {
                    return this.rows.filter(r => r.quantity > 0 && r.unit === 'unit')
                        .reduce((s, r) => s + r.quantity, 0);
                },
                get totalValue() {
                    return this.rows.reduce((s, r) => s + this.estValueRaw(r), 0);
                },

                meta(row) {
                    return [row.code, row.category, row.origin, row.class ? 'Class ' + row.class : null]
                        .filter(Boolean).join(' · ');
                },
                estValueRaw(row) {
                    return row.quantity > 0 && row.unit === row.priced_unit
                        ? row.quantity * row.current_price : 0;
                },
                estValue(row) {
                    const v = this.estValueRaw(row);
                    return v > 0 ? '€' + v.toFixed(2) : '—';
                },
                fmtQty(n) {
                    return Number.isInteger(n) ? String(n) : n.toFixed(2).replace(/\.?0+$/, '');
                },

                onType(row, value) {
                    const n = parseFloat(value);
                    row.quantity = (!isNaN(n) && n > 0) ? Math.round(n * 100) / 100 : 0;
                    this.queueSave(row);
                },
                step(row, delta) {
                    row.quantity = Math.max(0, Math.round(((row.quantity || 0) + delta) * 100) / 100);
                    this.queueSave(row);
                },
                setUnit(row, unit) {
                    if (row.unit === unit) return;
                    row.unit = unit;
                    if (row.quantity > 0) this.queueSave(row);
                },
                queueSave(row) {
                    clearTimeout(this.saveTimers[row.code]);
                    this.saveTimers[row.code] = setTimeout(() => this.save(row), 500);
                },
                async save(row) {
                    this.setState(row.code, 'saving');
                    try {
                        const response = await fetch(entryUrl, {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
                            body: JSON.stringify({
                                date: this.date,
                                product_code: row.code,
                                quantity: row.quantity,
                                unit: row.unit,
                            }),
                        });
                        if (!response.ok) throw new Error('save failed');
                        const data = await response.json();
                        row.value = data.deleted ? null : data.value;
                        this.setState(row.code, 'saved');
                    } catch (e) {
                        console.error('Waste entry save failed', e);
                        this.setState(row.code, 'error', 5000);
                    }
                },
                setState(code, state, clearAfter = 1500) {
                    this.saveState[code] = state;
                    if (state === 'saving') return;
                    clearTimeout(this.stateTimers[code]);
                    this.stateTimers[code] = setTimeout(() => { delete this.saveState[code]; }, clearAfter);
                },

                clearSearch() {
                    this.search = '';
                    this.searchCodes = null;
                },
                async runSearch() {
                    const q = this.search.trim();
                    if (q.length === 0) { this.searchCodes = null; return; }
                    this.searching = true;
                    try {
                        const response = await fetch(`${searchUrl}?q=${encodeURIComponent(q)}&date=${this.date}`, {
                            headers: { 'Accept': 'application/json' },
                        });
                        if (!response.ok) throw new Error('search failed');
                        const data = await response.json();
                        if (this.search.trim() !== q) return; // stale response
                        const known = new Set(this.rows.map(r => r.code));
                        data.products.forEach(p => {
                            if (!known.has(p.code)) this.rows.push(p);
                        });
                        this.searchCodes = new Set(data.products.map(p => p.code));
                    } catch (e) {
                        console.error('Waste search failed', e);
                    } finally {
                        this.searching = false;
                    }
                },
            };
        }
    </script>
    @endpush
</x-admin-layout>
