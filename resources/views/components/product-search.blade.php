{{--
    Reusable product search bar backed by GET /api/products/search.
    See docs/features/product-search.md for props, events and the JSON shape.

    Usage:
        <x-product-search mode="picker" name="product_id" />
        <x-product-search mode="list" :initial="$initial" :camera="true">
            <x-slot:row-actions>
                <a :href="product.edit_url">Edit</a>
            </x-slot:row-actions>
        </x-product-search>

    Events (bubble from the root element):
        product-search:selected  detail = product item (picker mode)
        product-search:cleared
        product-search:results   detail = { data, meta } (every successful search)
--}}
@props([
    'mode' => 'picker',
    'url' => null,
    'stocked' => true,
    'showStockedToggle' => true,
    'supplierId' => null,
    'categoryId' => null,
    'excludeIds' => [],
    'perPage' => null,
    'placeholder' => 'Search by name, barcode, reference or supplier code…',
    'minLength' => 1,
    'debounce' => 250,
    'autofocus' => false,
    'camera' => false,
    'initial' => null,
    'syncUrl' => true,
    'name' => null,
])

@php
    $mode = $mode === 'list' ? 'list' : 'picker';
    $uid = 'ps-'.\Illuminate\Support\Str::random(6);
    $config = [
        'mode' => $mode,
        'url' => $url ?? route('api.products.search'),
        'stocked' => (bool) $stocked,
        'showStockedToggle' => (bool) $showStockedToggle,
        'supplierId' => $supplierId ?: null,
        'categoryId' => $categoryId ?: null,
        'excludeIds' => array_values($excludeIds ?? []),
        'perPage' => (int) ($perPage ?? ($mode === 'list' ? 20 : 10)),
        'minLength' => (int) $minLength,
        'debounce' => (int) $debounce,
        'autofocus' => (bool) $autofocus,
        'camera' => (bool) $camera,
        'initial' => $initial,
        'syncUrl' => $mode === 'list' && (bool) $syncUrl,
        'name' => $name,
        'uid' => $uid,
    ];
@endphp

<div {{ $attributes->merge(['class' => $mode === 'list' ? 'product-search product-search--list' : 'product-search product-search--picker relative']) }}
     x-data="productSearch(@js($config))"
     x-on:click.outside="closeDropdown()">

    @if($name)
        <input type="hidden" name="{{ $name }}" x-model="selectedId">
    @endif

    {{-- Search bar --}}
    <div class="flex flex-wrap items-center gap-2">
        <div class="relative flex-1 min-w-[16rem]">
            <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400 dark:text-gray-500">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35M17 11A6 6 0 115 11a6 6 0 0112 0z"/></svg>
            </span>
            <input type="text"
                   x-ref="input"
                   x-model="q"
                   id="{{ $uid }}-input"
                   autocomplete="off"
                   spellcheck="false"
                   placeholder="{{ $placeholder }}"
                   @if($autofocus) autofocus @endif
                   x-on:input="onInput()"
                   x-on:focus="onFocus()"
                   x-on:keydown.down.prevent="move(1)"
                   x-on:keydown.up.prevent="move(-1)"
                   x-on:keydown.enter.prevent="onEnter()"
                   x-on:keydown.escape="onEscape()"
                   class="block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm pl-10 pr-16">
            <div class="absolute inset-y-0 right-0 flex items-center pr-2 gap-1">
                <svg x-show="loading" x-cloak class="animate-spin h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path></svg>
                <button type="button" x-show="q.length > 0" x-cloak x-on:click="clear()" title="Clear"
                        class="p-1 rounded text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
        </div>

        @if($camera)
            <button type="button" x-on:click="openScanner()" title="Scan barcode with camera"
                    class="inline-flex items-center justify-center px-3 py-2 bg-green-100 border-2 border-green-500 text-green-700 dark:bg-green-900/30 dark:border-green-500 dark:text-green-400 rounded-md hover:bg-green-200 dark:hover:bg-green-900/50 transition touch-manipulation">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"/>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"/>
                </svg>
            </button>
        @endif

        @if($showStockedToggle)
            <label class="inline-flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300 select-none whitespace-nowrap">
                <input type="checkbox" x-model="includeUnstocked" x-on:change="onStockedToggle()"
                       class="rounded border-gray-300 dark:border-gray-600 dark:bg-gray-900 text-indigo-600 shadow-sm focus:ring-indigo-500">
                Include unstocked
            </label>
        @endif

        @if($mode === 'list')
            <span x-show="meta && meta.took_ms !== undefined" x-cloak
                  class="ml-auto inline-flex items-center px-2 py-0.5 rounded text-xs font-mono bg-gray-100 text-gray-500 dark:bg-gray-700 dark:text-gray-400"
                  title="Server time for the last search"
                  x-text="meta ? meta.took_ms + ' ms' : ''"></span>
        @endif
    </div>

    <p x-show="error" x-cloak class="mt-2 text-sm text-red-600 dark:text-red-400" x-text="error"></p>

    @if($mode === 'picker')
        {{-- Picker dropdown --}}
        <div x-show="open" x-cloak
             class="absolute z-30 mt-1 w-full bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-md shadow-lg max-h-96 overflow-auto">
            <p x-show="meta && meta.corrected_query" x-cloak class="px-3 py-1.5 text-xs text-gray-500 dark:text-gray-400 border-b border-gray-100 dark:border-gray-700">
                Showing results for <strong class="text-gray-700 dark:text-gray-200" x-text="meta && meta.corrected_query"></strong>
            </p>
            <template x-for="(product, i) in results" :key="product.id">
                <button type="button"
                        x-on:click="select(product)"
                        x-on:mouseenter="activeIndex = i"
                        :class="activeIndex === i ? 'bg-indigo-50 dark:bg-gray-700' : ''"
                        class="w-full text-left px-3 py-2 text-sm flex items-center gap-3 hover:bg-indigo-50 dark:hover:bg-gray-700">
                    @include('components.product-search.thumb')
                    <span class="min-w-0 flex-1">
                        <span class="block truncate text-gray-900 dark:text-gray-100" x-text="product.name"></span>
                        <span class="block text-xs text-gray-500 dark:text-gray-400 truncate">
                            <span class="font-mono" x-text="product.code"></span>
                            <template x-if="product.supplier">
                                <span> · <span x-text="product.supplier.name"></span><template x-if="product.supplier.code"><span> <span class="font-mono" x-text="product.supplier.code"></span></span></template></span>
                            </template>
                            <span x-show="!product.is_stocked" class="ml-1 text-amber-600 dark:text-amber-400">unstocked</span>
                        </span>
                    </span>
                    <span class="text-right flex-shrink-0">
                        <span class="block text-sm text-gray-900 dark:text-gray-100" x-text="'€' + Number(product.price_with_vat).toFixed(2)"></span>
                        <span class="block text-xs" :class="product.stock_units > 0 ? 'text-green-600 dark:text-green-400' : 'text-gray-400 dark:text-gray-500'"
                              x-text="product.is_service ? 'N/A' : Number(product.stock_units).toFixed(2) + ' in stock'"></span>
                    </span>
                </button>
            </template>
            <p x-show="results.length === 0 && !loading" x-cloak class="px-3 py-2 text-sm text-gray-500 dark:text-gray-400">
                @isset($empty) {{ $empty }} @else No products found. @endisset
            </p>
            <p x-show="meta && meta.total > results.length" x-cloak class="px-3 py-1.5 text-xs text-gray-400 dark:text-gray-500 border-t border-gray-100 dark:border-gray-700"
               x-text="meta ? 'Showing ' + results.length + ' of ' + meta.total + ' — keep typing to narrow' : ''"></p>
        </div>
    @else
        {{-- Results list --}}
        <div class="mt-4 bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
            <p x-show="meta && meta.corrected_query" x-cloak class="px-4 py-2 text-sm text-gray-600 dark:text-gray-300 bg-yellow-50 dark:bg-yellow-900/20 border-b border-yellow-100 dark:border-yellow-900/40">
                Showing results for <strong x-text="meta && meta.corrected_query"></strong>
                (<a href="#" class="underline" x-on:click.prevent="searchInstead()">search instead for <em x-text="meta && meta.query"></em></a>)
            </p>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-700">
                        <tr>
                            <th class="px-2 py-3 w-12"></th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Product</th>
                            <th class="hidden lg:table-cell px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Category</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Supplier</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider whitespace-nowrap">Price (incl. VAT)</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">VAT</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Stock</th>
                            @isset($rowActions)
                                <th class="sticky right-0 bg-gray-50 dark:bg-gray-700 shadow-[-4px_0_6px_-4px_rgba(0,0,0,0.1)] px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Actions</th>
                            @endisset
                        </tr>
                    </thead>
                    <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700" :class="loading ? 'opacity-60' : ''">
                        <template x-for="product in results" :key="product.id">
                            <tr>
                                <td class="px-2 py-2 whitespace-nowrap">
                                    @include('components.product-search.thumb', ['tap' => true])
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-900 dark:text-gray-100">
                                    <div x-text="product.name"></div>
                                    <div class="text-xs text-gray-500 dark:text-gray-400">
                                        <span class="font-mono" x-text="product.code"></span>
                                        <span x-show="!product.is_stocked" class="ml-1 text-amber-600 dark:text-amber-400">unstocked</span>
                                    </div>
                                </td>
                                <td class="hidden lg:table-cell px-4 py-3 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                    <span x-show="product.category_name" class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200" x-text="product.category_name"></span>
                                    <span x-show="!product.category_name" class="text-gray-400 dark:text-gray-500 text-xs">Uncategorized</span>
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">
                                    <template x-if="product.supplier">
                                        <div class="flex flex-col">
                                            <span class="font-medium" x-text="product.supplier.name"></span>
                                            <span x-show="product.supplier.code" class="text-xs font-mono text-gray-500 dark:text-gray-400" x-text="product.supplier.code"></span>
                                            <a x-show="product.supplier.website_url" :href="product.supplier.website_url" target="_blank" rel="noopener noreferrer"
                                               class="text-xs text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300">View on supplier site →</a>
                                        </div>
                                    </template>
                                    <span x-show="!product.supplier" class="text-gray-400 dark:text-gray-500 text-xs">No supplier</span>
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100" x-text="'€' + Number(product.price_with_vat).toFixed(2)"></td>
                                <td class="px-4 py-3 whitespace-nowrap text-sm">
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full" :class="product.vat_badge_class" x-text="product.vat_label"></span>
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">
                                    <span x-show="product.is_service" class="text-gray-500 dark:text-gray-400">N/A</span>
                                    <span x-show="!product.is_service">
                                        <span :class="product.stock_units > 0 ? 'text-green-600 dark:text-green-400 font-semibold' : 'text-gray-400 dark:text-gray-500'" x-text="Number(product.stock_units).toFixed(2)"></span>
                                        <small x-show="product.stock_location" class="text-gray-400 dark:text-gray-500 block" x-text="product.stock_location"></small>
                                        <span x-show="!product.has_stock_record" class="text-xs text-red-400 dark:text-red-500 block">No stock record</span>
                                    </span>
                                </td>
                                @isset($rowActions)
                                    <td class="sticky right-0 bg-white dark:bg-gray-800 shadow-[-4px_0_6px_-4px_rgba(0,0,0,0.1)] px-4 py-3 whitespace-nowrap text-sm font-medium">
                                        {{ $rowActions }}
                                    </td>
                                @endisset
                            </tr>
                        </template>
                        <tr x-show="results.length === 0 && !loading" x-cloak>
                            <td colspan="8" class="px-6 py-6 text-center text-gray-500 dark:text-gray-400">
                                @isset($empty) {{ $empty }} @else No products found. @endisset
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div x-show="meta && meta.total > 0" x-cloak class="flex items-center justify-between px-4 py-3 border-t border-gray-200 dark:border-gray-700 text-sm text-gray-600 dark:text-gray-300">
                <span x-text="rangeLabel()"></span>
                <div class="flex items-center gap-2">
                    <button type="button" x-on:click="prevPage()" :disabled="!meta || meta.page <= 1"
                            class="px-3 py-1 rounded-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-700 disabled:opacity-40 disabled:cursor-not-allowed">‹ Prev</button>
                    <span class="text-xs text-gray-500 dark:text-gray-400" x-text="meta ? 'Page ' + meta.page + ' of ' + meta.last_page : ''"></span>
                    <button type="button" x-on:click="nextPage()" :disabled="!meta || meta.page >= meta.last_page"
                            class="px-3 py-1 rounded-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-700 disabled:opacity-40 disabled:cursor-not-allowed">Next ›</button>
                </div>
            </div>
        </div>
    @endif

    @if($camera)
        {{-- Camera scanner modal --}}
        <template x-teleport="body">
            <div x-show="scanner.open" x-cloak class="fixed inset-0 bg-black bg-opacity-50 z-50" x-on:keydown.escape.window="closeScanner()">
                <div class="flex items-center justify-center min-h-screen p-4">
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-xl max-w-md w-full" x-on:click.outside="closeScanner()">
                        <div class="p-6">
                            <div class="flex justify-between items-center mb-4">
                                <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Scan Product Barcode</h3>
                                <button type="button" x-on:click="closeScanner()" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                </button>
                            </div>
                            <div id="{{ $uid }}-camera" class="rounded-lg overflow-hidden bg-gray-900" style="min-height: 260px;"></div>
                            <p class="text-gray-500 dark:text-gray-400 text-sm text-center mt-2" x-text="scanner.status"></p>
                            <div class="flex justify-end mt-4">
                                <button type="button" x-on:click="closeScanner()"
                                        class="px-4 py-2 bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-md hover:bg-gray-200 dark:hover:bg-gray-600 transition">Cancel</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </template>
    @endif
</div>

@if($camera)
    @once('product-search-scanner')
        @push('scripts')
            @vite(['resources/js/barcode-scanner.js'])
        @endpush
    @endonce
@endif

@once('product-search-script')
@push('scripts')
<script>
    // Hover/tap preview for a result-row thumbnail. Mirrors the x-product-image
    // component's hover mode (see components/product-search/thumb.blade.php).
    window.productSearchThumb = function () {
        const PREVIEW = 256;   // w-64
        const CAPTION = 64;    // rough height of the name strip below the image

        return {
            open: false,
            pinned: false,
            failed: false,
            pos: { x: 0, y: 0 },

            place(el) {
                const rect = el.getBoundingClientRect();
                const below = window.innerHeight - rect.bottom;
                const height = Math.min(320, window.innerHeight - 16) + CAPTION;

                // Prefer below the thumbnail, flip above when there is more room there.
                this.pos.y = (below >= height || below > rect.top) ? rect.bottom + 8 : Math.max(8, rect.top - height - 8);
                this.pos.x = Math.max(8, Math.min(rect.left, window.innerWidth - PREVIEW - 8));
            },

            preview(el) {
                if (this.failed || this.pinned) return;
                this.place(el);
                this.open = true;
            },

            hide() {
                this.open = false;
            },

            pin(el) {
                if (this.failed) return;
                this.open = false;
                this.pinned = true;
            },
        };
    };

    // Plain window function (not Alpine.data): app.js starts Alpine before this stack runs.
    window.productSearch = function (config) {
        return {
            mode: config.mode,
            q: '',
            includeUnstocked: !config.stocked,
            results: [],
            meta: null,
            selectedId: null,
            loading: false,
            open: false,
            activeIndex: 0,
            error: null,
            lastUrl: null,
            requestSeq: 0,
            debounceTimer: null,
            scanner: { open: false, status: '', lastCode: '', lastTime: 0 },

            init() {
                if (config.initial && config.initial.meta) {
                    this.results = config.initial.data || [];
                    this.meta = config.initial.meta;
                    this.q = this.meta.query || '';
                    this.includeUnstocked = !this.meta.stocked;
                } else if (this.mode === 'list') {
                    this.run();
                }
                if (config.autofocus) {
                    this.$nextTick(() => this.$refs.input?.focus());
                }
            },

            // ----- input handling -----
            onInput() {
                clearTimeout(this.debounceTimer);
                this.debounceTimer = setTimeout(() => this.run(), config.debounce);
            },
            onFocus() {
                if (this.mode === 'picker' && this.results.length > 0 && this.q.trim() !== '') this.open = true;
            },
            async onEnter() {
                clearTimeout(this.debounceTimer);
                if (this.mode === 'list') { await this.run(); return; }
                // Picker: commit the highlighted match; a barcode scanner sends the code
                // followed by Enter, so a scan selects the product in one go.
                if (!this.open || this.results.length === 0) {
                    await this.run();
                }
                if (this.results.length > 0) {
                    this.select(this.results[this.activeIndex] ?? this.results[0]);
                }
            },
            onEscape() {
                if (this.mode === 'picker') { this.closeDropdown(); } else { this.clear(); }
            },
            onStockedToggle() {
                if (this.mode === 'list' || this.q.trim() !== '') this.run();
            },
            move(delta) {
                if (this.mode !== 'picker' || this.results.length === 0) return;
                this.open = true;
                this.activeIndex = (this.activeIndex + delta + this.results.length) % this.results.length;
            },
            closeDropdown() { this.open = false; },

            // ----- searching -----
            buildUrl(page) {
                const url = new URL(config.url, window.location.origin);
                url.searchParams.set('q', this.q.trim());
                url.searchParams.set('stocked', this.includeUnstocked ? '0' : '1');
                if (config.supplierId) url.searchParams.set('supplier_id', config.supplierId);
                if (config.categoryId) url.searchParams.set('category_id', config.categoryId);
                if (config.excludeIds.length) url.searchParams.set('exclude', config.excludeIds.join(','));
                url.searchParams.set('page', String(page));
                url.searchParams.set('per_page', String(config.perPage));
                return url.toString();
            },
            async run(page = 1) {
                const term = this.q.trim();
                if (this.mode === 'picker' && term.length < config.minLength) {
                    this.results = []; this.meta = null; this.open = false; return;
                }
                if (this.mode === 'list' && term.length > 0 && term.length < config.minLength) return;

                const seq = ++this.requestSeq;
                this.loading = true;
                this.error = null;
                const url = this.buildUrl(page);
                this.lastUrl = url;
                try {
                    const response = await fetch(url, {
                        headers: { 'Accept': 'application/json' },
                        credentials: 'same-origin',
                    });
                    if (seq !== this.requestSeq) return; // stale response, a newer search is in flight
                    if (!response.ok) throw new Error('Search failed (' + response.status + ')');
                    const json = await response.json();
                    if (seq !== this.requestSeq) return;
                    this.results = json.data || [];
                    this.meta = json.meta || null;
                    this.activeIndex = 0;
                    if (this.mode === 'picker') this.open = true;
                    this.syncUrl();
                    this.$dispatch('product-search:results', { data: this.results, meta: this.meta, url });
                } catch (e) {
                    if (seq !== this.requestSeq) return;
                    this.error = e.message || 'Search failed';
                    this.results = [];
                    this.meta = null;
                } finally {
                    if (seq === this.requestSeq) this.loading = false;
                }
            },
            searchInstead() {
                // The original query returned nothing (that is why it was corrected), so show that honestly.
                if (!this.meta) return;
                this.results = [];
                this.meta = { ...this.meta, corrected_query: null, total: 0, page: 1, last_page: 1 };
            },
            nextPage() { if (this.meta && this.meta.page < this.meta.last_page) this.run(this.meta.page + 1); },
            prevPage() { if (this.meta && this.meta.page > 1) this.run(this.meta.page - 1); },
            rangeLabel() {
                if (!this.meta || !this.meta.total) return '';
                const from = (this.meta.page - 1) * this.meta.per_page + 1;
                const to = Math.min(this.meta.page * this.meta.per_page, this.meta.total);
                return 'Showing ' + from + '–' + to + ' of ' + this.meta.total;
            },
            syncUrl() {
                if (!config.syncUrl || !this.meta || !window.history?.replaceState) return;
                const url = new URL(window.location.href);
                const term = this.q.trim();
                term ? url.searchParams.set('q', term) : url.searchParams.delete('q');
                url.searchParams.delete('search');
                this.includeUnstocked ? url.searchParams.set('stocked', '0') : url.searchParams.delete('stocked');
                this.meta.page > 1 ? url.searchParams.set('page', String(this.meta.page)) : url.searchParams.delete('page');
                window.history.replaceState(window.history.state, '', url.toString());
            },

            // ----- selection (picker) -----
            select(product) {
                this.selectedId = product.id;
                this.q = '';
                this.results = [];
                this.meta = null;
                this.open = false;
                this.$dispatch('product-search:selected', product);
                this.$nextTick(() => this.$refs.input?.focus());
            },
            clear() {
                clearTimeout(this.debounceTimer);
                this.q = '';
                this.selectedId = null;
                this.error = null;
                if (this.mode === 'list') {
                    this.run();
                } else {
                    this.results = []; this.meta = null; this.open = false;
                }
                this.$dispatch('product-search:cleared');
                this.$refs.input?.focus();
            },

            // ----- camera scanner -----
            openScanner() {
                this.scanner.open = true;
                this.$nextTick(() => this.startScanner());
            },
            closeScanner() {
                if (!this.scanner.open) return;
                this.scanner.open = false;
                this.stopScanner();
            },
            startScanner() {
                if (!window.BarcodeScanner) {
                    this.scanner.status = 'Scanner module not loaded. Ensure HTTPS is enabled.';
                    return;
                }
                this.scanner.status = 'Starting camera…';
                window.BarcodeScanner.startScanner(
                    config.uid + '-camera',
                    (text) => this.onScanned(text),
                    () => {}
                ).then(() => {
                    this.scanner.status = 'Point camera at barcode';
                }).catch((err) => {
                    this.scanner.status = 'Camera error: ' + (err.message || err);
                });
            },
            stopScanner() {
                if (window.BarcodeScanner && window.BarcodeScanner.isRunning()) {
                    window.BarcodeScanner.stopScanner().catch(() => {});
                }
                this.scanner.status = '';
            },
            parseBarcode(raw) {
                let text = raw.trim();
                for (const prefix of [']C1', ']d2', ']e0']) {
                    if (text.startsWith(prefix)) text = text.substring(3);
                }
                text = text.replace(/[\x1D]/g, '|');
                const gtin14 = text.match(/(?:^|\|)01(\d{14})/);
                return gtin14 ? gtin14[1] : text;
            },
            async onScanned(text) {
                const now = Date.now();
                if (text === this.scanner.lastCode && now - this.scanner.lastTime < 2000) return;
                this.scanner.lastCode = text;
                this.scanner.lastTime = now;
                try {
                    const ctx = new (window.AudioContext || window.webkitAudioContext)();
                    const osc = ctx.createOscillator();
                    osc.type = 'square'; osc.frequency.value = 1000;
                    osc.connect(ctx.destination); osc.start(); osc.stop(ctx.currentTime + 0.1);
                } catch (e) {}
                if (navigator.vibrate) navigator.vibrate(100);

                this.closeScanner();
                this.q = this.parseBarcode(text);
                await this.run();
                if (this.mode === 'picker' && this.results.length > 0) {
                    this.select(this.results[0]);
                }
            },
        };
    };
</script>
@endpush
@endonce
