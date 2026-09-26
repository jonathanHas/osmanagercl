@php
    // Old input wins wholesale on a validation bounce so nothing typed is lost —
    // the same rule the office form used.
    $hasOld = session()->hasOldInput();
    $value = fn (string $field, $default = '') => $hasOld ? old($field, '') : ($customerRequest->{$field} ?? $default);
    $wantedOn = $hasOld ? old('wanted_on', '') : optional($customerRequest->wanted_on)->toDateString();
@endphp

<x-shop-layout title="Edit request" :back="route('customer-requests.show', $customerRequest)">
    <main class="shop-page shop-page--narrow">
        @if ($errors->any())
            <section class="shop-card shop-card--flat">
                <div class="shop-inline">
                    <x-shop.icon name="alert" />
                    <h2 class="shop-subtitle">Please fix these before saving</h2>
                </div>
                @foreach ($errors->all() as $error)
                    <p class="shop-meta">{{ $error }}</p>
                @endforeach
            </section>
        @endif

        <form id="edit-request-form" method="POST" action="{{ route('customer-requests.update', $customerRequest) }}"
              class="shop-stack"
              x-data="shopRequestEdit(@js($seedItems), @js(\App\Models\CustomerRequestItem::LABELS))"
              data-search-url="{{ route('api.products.search') }}"
              @submit="submitGuard($event)">
            @csrf
            @method('PUT')

            <section class="shop-card">
                <h2 class="shop-subtitle">Customer</h2>

                <div class="shop-field">
                    <label class="shop-field__label" for="customer_name">Customer</label>
                    <input class="shop-input" id="customer_name" name="customer_name" type="text"
                           value="{{ $value('customer_name') }}" maxlength="120" required autocomplete="off">
                    @error('customer_name')
                        <span class="shop-pill shop-pill--bad">{{ $message }}</span>
                    @enderror
                </div>

                <div class="shop-field">
                    <label class="shop-field__label" for="customer_phone">Phone &middot; not shown on the public board</label>
                    <input class="shop-input" id="customer_phone" name="customer_phone" type="tel"
                           inputmode="tel" value="{{ $value('customer_phone') }}" maxlength="40" autocomplete="off">
                    @error('customer_phone')
                        <span class="shop-pill shop-pill--bad">{{ $message }}</span>
                    @enderror
                </div>

                <div class="shop-field">
                    <label class="shop-field__label" for="wanted_on">Due</label>
                    <input class="shop-input" id="wanted_on" name="wanted_on" type="date" value="{{ $wantedOn }}">
                    @error('wanted_on')
                        <span class="shop-pill shop-pill--bad">{{ $message }}</span>
                    @enderror
                </div>

                <div class="shop-field">
                    <label class="shop-field__label" for="notes">Notes</label>
                    <textarea class="shop-input" id="notes" name="notes" rows="2" maxlength="2000">{{ $value('notes') }}</textarea>
                    @error('notes')
                        <span class="shop-pill shop-pill--bad">{{ $message }}</span>
                    @enderror
                </div>
            </section>

            <section class="shop-card">
                <h2 class="shop-subtitle">Items</h2>
                <p class="shop-meta">Line statuses are changed from the board; editing here only changes the details.</p>

                <div class="shop-list">
                    <template x-for="(item, idx) in items" :key="item._key">
                        <div class="shop-row">
                            <input type="hidden" :name="`items[${idx}][id]`" :value="item.id ?? ''">
                            <input type="hidden" :name="`items[${idx}][product_code]`" :value="item.product_code">
                            <input type="hidden" :name="`items[${idx}][product_name]`" :value="item.product_name">

                            <template x-if="item.product">
                                <span class="shop-inline"><x-shop.product-thumb expr="item.product" /></span>
                            </template>
                            {{-- A stocked line seeded from the request has no image to show,
                                 but it is still a product, not something to source. --}}
                            <span class="shop-row__lead" x-show="! item.product && item.product_code"><x-shop.icon name="package" /></span>
                            <span class="shop-row__lead" x-show="! item.product && ! item.product_code"><x-shop.icon name="sprout" /></span>

                            <div class="shop-row__main">
                                <div class="shop-field">
                                    <label class="shop-field__label" :for="`item-${idx}-description`">Description</label>
                                    <input class="shop-input" type="text" :id="`item-${idx}-description`"
                                           :name="`items[${idx}][description]`" x-model="item.description"
                                           :data-line-idx="idx" maxlength="255" required autocomplete="off">
                                </div>

                                <div class="shop-facts shop-facts--2">
                                    <div class="shop-field">
                                        <label class="shop-field__label" :for="`item-${idx}-quantity`">Quantity</label>
                                        <input class="shop-input" type="text" inputmode="decimal" :id="`item-${idx}-quantity`"
                                               :name="`items[${idx}][quantity]`" x-model="item.quantity" required>
                                    </div>
                                    <div class="shop-field">
                                        <label class="shop-field__label" :for="`item-${idx}-notes`">Line note</label>
                                        <input class="shop-input" type="text" :id="`item-${idx}-notes`"
                                               :name="`items[${idx}][notes]`" x-model="item.notes" maxlength="500" autocomplete="off">
                                    </div>
                                </div>

                                <div class="shop-inline">
                                    <span class="shop-code" x-show="item.product_code" x-text="item.product_code"></span>
                                    <button class="shop-btn shop-btn--secondary" type="button" x-show="item.product_code" @click="unlink(idx)">Unlink product</button>
                                    <span class="shop-pill shop-pill--muted"
                                          x-show="item.id && item.status && item.status !== 'pending'"
                                          x-text="statusLabel(item)"></span>
                                </div>
                            </div>

                            <button class="shop-iconbtn shop-iconbtn--ghost" type="button" x-show="canRemove(item)"
                                    :aria-label="`Remove ${item.description || 'line'}`" @click="remove(idx)">
                                <x-shop.icon name="x" />
                            </button>
                        </div>
                    </template>
                </div>

                <div class="shop-search">
                    <x-shop.icon name="scan" />
                    <input class="shop-input" type="search" x-model="query"
                           @input.debounce.250ms="search()" @keydown.enter.prevent="pickFirst()"
                           placeholder="Type a name or scan a barcode" autocomplete="off" enterkeyhint="search"
                           aria-label="Add a stocked product">
                </div>

                <div class="shop-list" x-show="results.length" x-cloak>
                    <template x-for="p in results" :key="p.id">
                        <button class="shop-row" type="button" @click="pickResult(p)">
                            <x-shop.product-thumb />
                            <div class="shop-row__main">
                                <span class="shop-row__title" x-text="p.name"></span>
                                <span class="shop-row__meta shop-code" x-text="p.code"></span>
                            </div>
                        </button>
                    </template>
                </div>

                <button class="shop-btn shop-btn--secondary" type="button" @click="addBlank()">
                    <x-shop.icon name="plus" />
                    Add a product to source
                </button>

                <span class="shop-pill shop-pill--bad" x-show="tooFew" x-cloak>Add at least one item before saving.</span>
            </section>

            <div class="shop-actions">
                <a class="shop-btn shop-btn--secondary shop-btn--lg" href="{{ route('customer-requests.show', $customerRequest) }}">Cancel</a>
                <button class="shop-btn shop-btn--primary shop-btn--lg" type="submit">
                    <x-shop.icon name="check" />
                    Save changes
                </button>
            </div>
        </form>
    </main>
</x-shop-layout>
