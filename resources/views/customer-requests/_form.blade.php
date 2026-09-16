{{--
    Shared create / edit form. Expects:
      $customerRequest  CustomerRequest|null
      $seedItems        array  lines to seed the Alpine state with (old() input wins, see controller)
      $inModal          bool   optional; when true the cancel link closes the board modal instead of navigating
--}}
@php
    $isEdit = $customerRequest !== null;
    $inModal = $inModal ?? false;
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
        'statusLabels' => \App\Models\CustomerRequestItem::LABELS,
    ]) }})"
     x-on:product-search:selected="addProduct($event.detail)">

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
                    <input type="text" id="customer_name" name="customer_name" value="{{ $value('customer_name') }}" required maxlength="120" @if(! $inModal) autofocus @endif
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

            {{-- Product picker: x-product-search dispatches product-search:selected, handled on the form root --}}
            <div class="mb-4">
                <p class="block text-sm font-medium text-gray-700 mb-1">Add a stocked product</p>
                <x-product-search mode="picker" :show-stocked-toggle="false" :min-length="2"
                                  placeholder="Scan a barcode or type a product name…" />
                <p class="mt-1 text-xs text-gray-500">No match? Use "Source a new product" to add it as free text.</p>
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
            @if($inModal)
                <button type="button" @click="$dispatch('close-new-request')" class="text-sm text-gray-600 hover:text-gray-900">Cancel</button>
            @else
                <a href="{{ route('customer-requests.index') }}" class="text-sm text-gray-600 hover:text-gray-900">Back to board</a>
            @endif
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
            statusLabels: config.statusLabels ?? {},

            addProduct(p) {
                const existing = this.items.find(it => it.product_code === p.code && (!it.id || it.status === 'pending'));
                if (existing) {
                    existing.quantity = (parseFloat(existing.quantity) || 0) + 1;
                } else {
                    this.items.push(withKey({ product_code: p.code, product_name: p.name, description: p.name, quantity: 1 }));
                }
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
