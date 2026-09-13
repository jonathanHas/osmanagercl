{{--
    Shared create / edit form. Expects:
      $customerRequest  CustomerRequest|null
      $seedItems        array  lines to seed the Alpine state with (old() input wins, see controller)
--}}
@php
    $isEdit = $customerRequest !== null;
    $action = $isEdit ? route('customer-requests.update', $customerRequest) : route('customer-requests.store');

    // Old input wins wholesale on a validation bounce so nothing the user typed is lost.
    $hasOld = session()->hasOldInput();
    $value = fn (string $field, $default = '') => $hasOld ? old($field, '') : ($isEdit ? ($customerRequest->{$field} ?? $default) : $default);
    $wantedOn = $hasOld
        ? old('wanted_on', '')
        : ($isEdit ? optional($customerRequest->wanted_on)->toDateString() : '');
@endphp

<div x-data="customerRequestForm({{ \Illuminate\Support\Js::from([
        'items' => $seedItems,
        'urls' => ['productSearch' => route('customer-requests.api.products.search')],
        'statusLabels' => \App\Models\CustomerRequestItem::LABELS,
    ]) }})">

    @if($errors->any())
        <div class="mb-4 rounded bg-red-100 border border-red-300 text-red-800 px-4 py-3 text-sm">
            <p class="font-semibold mb-1">Please fix the following before saving:</p>
            <ul class="list-disc list-inside space-y-0.5">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ $action }}" class="space-y-6" @submit="if (items.length === 0) { $event.preventDefault(); alert('Add at least one item.'); }">
        @csrf
        @if($isEdit)
            @method('PUT')
        @endif

        {{-- Customer --}}
        <div class="bg-white shadow rounded-lg p-4 sm:p-6">
            <h3 class="text-base font-semibold text-gray-900 mb-4">Customer</h3>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label for="customer_name" class="block text-sm font-medium text-gray-700">Name <span class="text-red-500">*</span></label>
                    <input type="text" id="customer_name" name="customer_name" value="{{ $value('customer_name') }}" required maxlength="120" autofocus
                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                </div>
                <div>
                    <label for="customer_phone" class="block text-sm font-medium text-gray-700">Phone</label>
                    <input type="tel" id="customer_phone" name="customer_phone" value="{{ $value('customer_phone') }}" maxlength="40"
                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                </div>
                <div>
                    <label for="wanted_on" class="block text-sm font-medium text-gray-700">Wanted by</label>
                    <input type="date" id="wanted_on" name="wanted_on" value="{{ $wantedOn }}"
                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                    <p class="mt-1 text-xs text-gray-500">The request shows under "Due today" from this date.</p>
                </div>
            </div>
            <div class="mt-4">
                <label for="notes" class="block text-sm font-medium text-gray-700">Notes</label>
                <textarea id="notes" name="notes" rows="2" maxlength="2000"
                          class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">{{ $value('notes') }}</textarea>
            </div>
        </div>

        {{-- Items --}}
        <div class="bg-white shadow rounded-lg p-4 sm:p-6">
            <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
                <h3 class="text-base font-semibold text-gray-900">Items</h3>
                <button type="button" @click="addBlankItem()"
                        class="inline-flex items-center px-3 py-1.5 rounded-md border border-purple-300 bg-purple-50 text-purple-800 text-sm font-medium hover:bg-purple-100">
                    + Source a new product
                </button>
            </div>

            {{-- Product typeahead --}}
            <div class="relative mb-4" @click.outside="searchOpen = false">
                <label for="product-search" class="block text-sm font-medium text-gray-700">Add a stocked product</label>
                <div class="mt-1 relative">
                    <input type="text" id="product-search" x-model="searchTerm" x-ref="search"
                           @input.debounce.250ms="runSearch()"
                           @focus="searchOpen = searchResults.length > 0"
                           @keydown.enter.prevent="commitTopMatch()"
                           @keydown.escape="searchOpen = false"
                           @keydown.down.prevent="moveActive(1)"
                           @keydown.up.prevent="moveActive(-1)"
                           placeholder="Scan a barcode or type a product name…" autocomplete="off"
                           class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm pr-10">
                    <div x-show="searching" x-cloak class="absolute inset-y-0 right-3 flex items-center">
                        <svg class="animate-spin h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path></svg>
                    </div>
                </div>
                <div x-show="searchOpen && searchResults.length > 0" x-cloak
                     class="absolute z-20 mt-1 w-full bg-white border border-gray-200 rounded-md shadow-lg max-h-72 overflow-auto">
                    <template x-for="(p, i) in searchResults" :key="p.code">
                        <button type="button" @click="addProduct(p)" @mouseenter="activeIndex = i"
                                :class="activeIndex === i ? 'bg-indigo-50' : ''"
                                class="w-full text-left px-3 py-2 text-sm flex items-center justify-between gap-3 hover:bg-indigo-50">
                            <span class="min-w-0">
                                <span class="block truncate text-gray-900" x-text="p.name"></span>
                                <span class="block text-xs font-mono text-gray-500" x-text="p.code"></span>
                            </span>
                            <span class="text-xs text-gray-500 flex-shrink-0" x-show="p.price !== null" x-text="'€' + Number(p.price).toFixed(2)"></span>
                        </button>
                    </template>
                </div>
                <p x-show="searchOpen && searchTerm.trim().length >= 2 && searchResults.length === 0 && !searching" x-cloak class="mt-1 text-xs text-gray-500">
                    No stocked product matches — use "Source a new product" to add it as free text.
                </p>
            </div>

            <p x-show="items.length === 0" x-cloak class="text-sm text-gray-500 border border-dashed border-gray-300 rounded-md px-4 py-6 text-center">
                No items yet. Search for a stocked product above or add a product to source.
            </p>

            <div class="space-y-3">
                <template x-for="(item, idx) in items" :key="item._key">
                    <div class="border rounded-md p-3"
                         :class="item.product_code ? 'border-gray-200 bg-gray-50' : 'border-purple-200 bg-purple-50/40'">
                        <input type="hidden" :name="`items[${idx}][id]`" :value="item.id ?? ''">
                        <input type="hidden" :name="`items[${idx}][product_code]`" :value="item.product_code ?? ''">
                        <input type="hidden" :name="`items[${idx}][product_name]`" :value="item.product_name ?? ''">

                        <div class="grid grid-cols-1 md:grid-cols-12 gap-3 items-start">
                            <div class="md:col-span-6">
                                <label class="block text-xs font-medium text-gray-600">
                                    <span x-text="item.product_code ? 'Product' : 'Product to source'"></span>
                                    <span class="ml-1 inline-flex items-center px-1.5 py-0.5 rounded text-[11px] font-medium"
                                          :class="item.product_code ? 'bg-green-100 text-green-800' : 'bg-purple-100 text-purple-700'"
                                          x-text="item.product_code ? 'Stocked' : 'To source'"></span>
                                </label>
                                <input type="text" :name="`items[${idx}][description]`" x-model="item.description" required maxlength="255"
                                       :data-item-idx="idx" data-field="description"
                                       :placeholder="item.product_code ? '' : 'Describe the product, brand, size…'"
                                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                                <div class="mt-1 flex items-center gap-3 text-xs text-gray-500">
                                    <span class="font-mono" x-show="item.product_code" x-text="item.product_code"></span>
                                    <button type="button" x-show="item.product_code" @click="unlink(idx)" class="text-gray-500 hover:text-gray-800 underline">Unlink product</button>
                                </div>
                            </div>
                            <div class="md:col-span-2">
                                <label class="block text-xs font-medium text-gray-600">Qty</label>
                                <input type="number" :name="`items[${idx}][quantity]`" x-model="item.quantity" min="0.01" step="0.01" required
                                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                            </div>
                            <div class="md:col-span-3">
                                <label class="block text-xs font-medium text-gray-600">Line note</label>
                                <input type="text" :name="`items[${idx}][notes]`" x-model="item.notes" maxlength="500"
                                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                            </div>
                            <div class="md:col-span-1 flex md:justify-end items-end h-full pt-5">
                                <template x-if="item.id && item.status && item.status !== 'pending'">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold uppercase bg-gray-200 text-gray-700" x-text="statusLabels[item.status] ?? item.status"></span>
                                </template>
                                <template x-if="!item.id || !item.status || item.status === 'pending'">
                                    <button type="button" @click="removeItem(idx)" class="text-gray-400 hover:text-red-600 p-1" title="Remove line">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                    </button>
                                </template>
                            </div>
                        </div>
                    </div>
                </template>
            </div>
        </div>

        <div class="flex items-center justify-between">
            <a href="{{ route('customer-requests.index') }}" class="text-sm text-gray-600 hover:text-gray-900">Back to board</a>
            <button type="submit" class="inline-flex items-center px-4 py-2 rounded-md bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold">
                {{ $isEdit ? 'Save changes' : 'Save request' }}
            </button>
        </div>
    </form>
</div>

@once
@push('scripts')
<script>
    function customerRequestForm(config) {
        let keySeq = 0;
        const withKey = (item) => ({
            id: item.id ?? null,
            product_code: item.product_code ?? null,
            product_name: item.product_name ?? null,
            description: item.description ?? '',
            quantity: item.quantity ?? 1,
            notes: item.notes ?? '',
            status: item.status ?? null,
            _key: ++keySeq,
        });

        return {
            items: (config.items ?? []).map(withKey),
            urls: config.urls,
            statusLabels: config.statusLabels ?? {},
            searchTerm: '',
            searchResults: [],
            searchOpen: false,
            searching: false,
            activeIndex: 0,

            async runSearch() {
                const term = this.searchTerm.trim();
                if (term.length < 2) { this.searchResults = []; this.searchOpen = false; return; }
                this.searching = true;
                try {
                    const r = await fetch(`${this.urls.productSearch}?q=${encodeURIComponent(term)}`, { headers: { Accept: 'application/json' } });
                    const j = await r.json();
                    this.searchResults = j.data ?? [];
                    this.activeIndex = 0;
                    this.searchOpen = true;
                } catch (e) {
                    this.searchResults = [];
                } finally {
                    this.searching = false;
                }
            },

            // Enter commits the highlighted match — a barcode scanner sends the code
            // followed by Enter, so a scan adds the product in one go.
            async commitTopMatch() {
                if (this.searchResults.length === 0 && this.searchTerm.trim() !== '') {
                    await this.runSearch();
                }
                if (this.searchResults.length > 0) {
                    this.addProduct(this.searchResults[this.activeIndex] ?? this.searchResults[0]);
                }
            },

            moveActive(delta) {
                if (this.searchResults.length === 0) return;
                this.activeIndex = (this.activeIndex + delta + this.searchResults.length) % this.searchResults.length;
            },

            addProduct(p) {
                const existing = this.items.find(it => it.product_code === p.code && (!it.id || it.status === 'pending'));
                if (existing) {
                    existing.quantity = (parseFloat(existing.quantity) || 0) + 1;
                } else {
                    this.items.push(withKey({ product_code: p.code, product_name: p.name, description: p.name, quantity: 1 }));
                }
                this.searchResults = [];
                this.searchTerm = '';
                this.searchOpen = false;
                this.$nextTick(() => this.$refs.search?.focus());
            },

            addBlankItem() {
                this.items.push(withKey({ description: '', quantity: 1 }));
                const idx = this.items.length - 1;
                this.$nextTick(() => {
                    document.querySelector(`[data-item-idx="${idx}"][data-field="description"]`)?.focus();
                });
            },

            unlink(idx) {
                this.items[idx].product_code = null;
                this.items[idx].product_name = null;
            },

            removeItem(idx) { this.items.splice(idx, 1); },
        };
    }
</script>
@endpush
@endonce
