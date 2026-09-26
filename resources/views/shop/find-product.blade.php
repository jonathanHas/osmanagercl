<x-shop-layout title="Find product" :back="route('shop.home')">
    <main class="shop-page shop-page--narrow" x-data="shopFindProduct()"
          data-search-url="{{ route('api.products.search') }}">
        <div class="shop-search">
            <x-shop.icon name="search" />
            <input class="shop-input" type="search" x-ref="input" x-model="q"
                   @input="onInput()" @keydown.enter.prevent="onEnter()"
                   placeholder="Search by name, or scan a barcode"
                   autocomplete="off" enterkeyhint="search" aria-label="Search products">
        </div>

        <div class="shop-seg shop-seg--block" role="radiogroup" aria-label="Range">
            <label class="shop-seg__opt">
                <input type="radio" name="range" value="1" :checked="stocked" @change="setStocked(true)">
                Our range
            </label>
            <label class="shop-seg__opt">
                <input type="radio" name="range" value="0" :checked="! stocked" @change="setStocked(false)">
                All products
            </label>
        </div>

        <p class="shop-meta" x-show="correctedQuery" x-cloak>
            Showing results for <strong x-text="correctedQuery"></strong>
        </p>

        <section class="shop-card" x-show="selected" x-cloak x-ref="card">
            <div class="shop-between">
                <span class="shop-label">Shelf price</span>
                <button class="shop-iconbtn shop-iconbtn--ghost" type="button" aria-label="Close" @click="close()">
                    <x-shop.icon name="x" />
                </button>
            </div>

            <div class="shop-inline">
                <button class="shop-thumb-btn" type="button" x-show="selected && hasImage(selected)" :class="{ 'is-open': enlarged }" :aria-pressed="enlarged" :aria-label="enlarged ? 'Shrink image' : 'Show full image'" @click="toggleImage()">
                    <img class="shop-thumb shop-thumb--lg" :src="selected?.image_url" :alt="selected?.name" decoding="async" x-on:error="selected && imageFailed(selected)">
                </button>

                <div class="shop-stack shop-stack--tight">
                    <h2 class="shop-subtitle" x-text="selected?.name"></h2>
                    <span class="shop-bignum">
                        <span class="shop-bignum__cur">€</span>
                        <span x-text="selected ? Number(selected.price_with_vat).toFixed(2) : ''"></span>
                    </span>
                    <div class="shop-inline">
                        <span class="shop-pill" :class="selected && stockTone(selected)" x-text="selected && stockLabel(selected)"></span>
                        <span class="shop-pill shop-pill--muted" x-show="selected && ! selected.is_stocked" x-cloak>Not in our range</span>
                    </div>
                </div>
            </div>

            <div class="shop-facts shop-facts--2">
                <div class="shop-fact">
                    <span class="shop-label">Stock</span>
                    <span class="shop-fact__value" x-text="selected ? units(selected) + ' units' : ''"></span>
                </div>
                <div class="shop-fact">
                    <span class="shop-label">Category</span>
                    <span class="shop-fact__value" x-text="selected?.category_name || '—'"></span>
                </div>
                <div class="shop-fact">
                    <span class="shop-label">Supplier</span>
                    <span class="shop-fact__value" x-text="selected && supplierLabel(selected)"></span>
                </div>
                <div class="shop-fact">
                    <span class="shop-label">Barcode</span>
                    <span class="shop-fact__value shop-code" x-text="selected?.code"></span>
                </div>
                <div class="shop-fact">
                    <span class="shop-label">VAT</span>
                    <span class="shop-fact__value" x-text="selected?.vat_label || '—'"></span>
                </div>
            </div>
        </section>

        <section class="shop-stack shop-stack--tight" x-show="results.length" x-cloak>
            <div class="shop-between">
                <h2 class="shop-label">Results</h2>
                <span class="shop-meta" x-text="meta ? meta.total + ' found' : ''"></span>
            </div>

            <div class="shop-list">
                <template x-for="p in results" :key="p.id">
                    <button class="shop-row" type="button" :class="{ 'is-off': units(p) === 0 }" @click="select(p)">
                        <x-shop.product-thumb x-on:mouseenter="peekAt(p, $el)" x-on:mouseleave="unpeek()" />
                        <div class="shop-row__main">
                            <span class="shop-row__title" x-text="p.name"></span>
                            <span class="shop-row__meta shop-code" x-text="rowMeta(p)"></span>
                        </div>
                        <div class="shop-row__aside">
                            <span class="shop-row__qty" x-text="price(p)"></span>
                            <span class="shop-pill" :class="stockTone(p)" x-text="stockLabel(p)"></span>
                        </div>
                        <x-shop.icon name="chevron-right" class="shop-row__chev" />
                    </button>
                </template>
            </div>

            <button class="shop-btn shop-btn--secondary shop-btn--block" type="button"
                    x-show="hasMore" x-cloak :disabled="loading" @click="more()">Show more</button>
        </section>

        <section class="shop-empty" x-show="q.trim().length < 2" x-cloak>
            <div class="shop-empty__icon"><x-shop.icon name="search" size="xl" /></div>
            <p class="shop-empty__title">Find a product</p>
            <p class="shop-empty__text">Type part of the name, or scan a barcode.</p>
        </section>

        <section class="shop-empty" x-show="! loading && meta && results.length === 0" x-cloak>
            <div class="shop-empty__icon"><x-shop.icon name="package" size="xl" /></div>
            <p class="shop-empty__title">Nothing matches</p>
            <p class="shop-empty__text">Try fewer words, or switch to All products.</p>
        </section>

        <section class="shop-empty" x-show="error" x-cloak>
            <div class="shop-empty__icon"><x-shop.icon name="alert" size="xl" /></div>
            <p class="shop-empty__title">Something went wrong</p>
            <p class="shop-empty__text" x-text="error"></p>
        </section>

        <div class="shop-peek" :class="{ 'is-open': peek }" :style="peek ? 'left:' + peek.x + 'px; top:' + peek.y + 'px' : ''" aria-hidden="true">
            <img :src="peek?.product.image_url" :alt="peek?.product.name || ''" decoding="async">
            <div class="shop-peek__name" x-text="peek?.product.name"></div>
        </div>
    </main>
</x-shop-layout>
