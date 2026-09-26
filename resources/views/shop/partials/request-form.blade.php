{{--
    The New request card's form: one line per request, either a pre-order (a
    stocked product found by typing or scanning) or a sourcing request (free
    text). Posts to the existing endpoint as a plain form, so the redirect and
    flash contract is unchanged; the only JavaScript is the typeahead.
--}}
@php($seed = $seedItems[0] ?? null)
<form id="new-request-form" method="POST" action="{{ route('customer-requests.store') }}"
      x-data="shopRequestForm(@js($seed))"
      data-search-url="{{ $searchUrl }}"
      class="shop-stack">
    @csrf

    <div class="shop-seg shop-seg--block" role="radiogroup" aria-label="Request type">
        <label class="shop-seg__opt">
            <input type="radio" name="kind" value="preorder" x-model="kind">
            Pre-order
        </label>
        <label class="shop-seg__opt">
            <input type="radio" name="kind" value="sourcing" x-model="kind">
            Sourcing
        </label>
    </div>

    <div class="shop-field" x-show="kind === 'preorder'" x-cloak>
        <label class="shop-field__label" for="product_query">Item</label>

        <div class="shop-row is-latest" x-show="picked" x-cloak>
            <x-shop.product-thumb expr="picked" />
            <div class="shop-row__main">
                <span class="shop-row__title" x-text="picked?.name"></span>
                <span class="shop-row__meta shop-code" x-text="picked?.code"></span>
            </div>
            <button class="shop-iconbtn shop-iconbtn--ghost" type="button" aria-label="Choose a different product" @click="unpick()">
                <x-shop.icon name="x" />
            </button>
        </div>

        <div class="shop-search" x-show="! picked" x-cloak>
            <x-shop.icon name="search" />
            <input class="shop-input" id="product_query" type="search" x-model="query"
                   @input.debounce.250ms="search()" @keydown.enter.prevent="pickFirst()"
                   placeholder="Type a name, or scan a barcode" autocomplete="off" enterkeyhint="search">
        </div>

        <div class="shop-list" x-show="! picked && results.length" x-cloak>
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
    </div>

    <div class="shop-field" x-show="kind === 'sourcing'" x-cloak>
        <label class="shop-field__label" for="description">What are they after?</label>
        <input class="shop-input" id="description" type="text" x-model="description"
               maxlength="255" placeholder="Describe the item" autocomplete="off">
    </div>

    {{-- The endpoint takes one line; these carry whichever branch is active. --}}
    <input type="hidden" name="items[0][product_code]" :value="productCode">
    <input type="hidden" name="items[0][product_name]" :value="productName">
    <input type="hidden" name="items[0][description]" :value="descriptionValue">

    @error('items.0.description')
        <span class="shop-pill shop-pill--bad">{{ $message }}</span>
    @enderror

    <div class="shop-field">
        <label class="shop-field__label" for="customer_name">Customer</label>
        <input class="shop-input" id="customer_name" name="customer_name" type="text"
               value="{{ old('customer_name') }}" maxlength="120" required autocomplete="off">
        @error('customer_name')
            <span class="shop-pill shop-pill--bad">{{ $message }}</span>
        @enderror
    </div>


    <div class="shop-facts shop-facts--2">
        <div class="shop-field">
            <label class="shop-field__label" for="quantity">Quantity</label>
            <input class="shop-input" id="quantity" name="items[0][quantity]" type="text"
                   inputmode="decimal" value="{{ old('items.0.quantity', 1) }}" required>
            @error('items.0.quantity')
                <span class="shop-pill shop-pill--bad">{{ $message }}</span>
            @enderror
        </div>

        <div class="shop-field">
            <label class="shop-field__label" for="wanted_on">Due</label>
            <input class="shop-input" id="wanted_on" name="wanted_on" type="date" value="{{ old('wanted_on') }}">
            @error('wanted_on')
                <span class="shop-pill shop-pill--bad">{{ $message }}</span>
            @enderror
        </div>
    </div>

    <div class="shop-field">
        <label class="shop-field__label" for="customer_phone">Phone &middot; not shown on the public board</label>
        <input class="shop-input" id="customer_phone" name="customer_phone" type="tel"
               inputmode="tel" value="{{ old('customer_phone') }}" maxlength="40" autocomplete="off">
        @error('customer_phone')
            <span class="shop-pill shop-pill--bad">{{ $message }}</span>
        @enderror
    </div>

</form>
