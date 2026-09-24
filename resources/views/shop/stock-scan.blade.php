<x-shop-layout title="Stock scan" :back="route('shop.home')">
    <main class="shop-page" x-data="shopStockScan()"
          data-lookup-url="{{ route('stocking.lookup') }}"
          data-update-url="{{ route('stocking.update-stock') }}"
          @scan="lookup($event.detail.code)">
        <div class="shop-split">
            <div class="shop-stack">
                <x-shop.scan-input />

                <section class="shop-card" x-show="product" x-cloak>
                    <div class="shop-between">
                        <div class="shop-stack shop-stack--tight">
                            <span class="shop-label">Product</span>
                            <h2 class="shop-subtitle" x-text="product?.name"></h2>
                            <span class="shop-row__meta shop-code" x-text="product ? product.code + ' · ' + product.category : ''"></span>
                        </div>
                        <span class="shop-pill" :class="stock > 0 ? 'shop-pill--ok' : 'shop-pill--bad'" x-text="stock > 0 ? 'In stock' : 'Out of stock'"></span>
                    </div>

                    <div class="shop-stack shop-stack--tight">
                        <span class="shop-label">Current stock</span>
                        <span class="shop-bignum"><span x-text="stock"></span><small>units</small></span>
                    </div>

                    <div class="shop-stack shop-stack--tight">
                        <span class="shop-label">Adjust by</span>
                        <div class="shop-stepper">
                            <button class="shop-iconbtn shop-iconbtn--lg" type="button" aria-label="Minus one" @click="step(-1)"><x-shop.icon name="minus" size="lg" /></button>
                            <output class="shop-stepper__value" :class="deltaTone" x-text="deltaLabel"></output>
                            <button class="shop-iconbtn shop-iconbtn--lg" type="button" aria-label="Plus one" @click="step(1)"><x-shop.icon name="plus" size="lg" /></button>
                        </div>
                        <p class="shop-meta">New stock will be <strong x-text="newStock + ' units'"></strong></p>
                    </div>
                </section>

                <section class="shop-empty" x-show="! product" x-cloak>
                    <div class="shop-empty__icon"><x-shop.icon name="package" size="xl" /></div>
                    <p class="shop-empty__title">Scan a product</p>
                    <p class="shop-empty__text">Point the scanner at a barcode, or type it and press Enter.</p>
                </section>
            </div>

            <div class="shop-stack">
                <section class="shop-card">
                    <div class="shop-numpad">
                        <div class="shop-numpad__display">
                            <span class="shop-label">Or type amount</span>
                            <span x-text="typed || '0'"></span>
                        </div>
                        @foreach (range(1, 9) as $digit)
                            <button class="shop-key" type="button" @click="key('{{ $digit }}')">{{ $digit }}</button>
                        @endforeach
                        <button class="shop-key shop-key--fn" type="button" aria-label="Flip sign" @click="key('sign')">±</button>
                        <button class="shop-key" type="button" @click="key('0')">0</button>
                        <button class="shop-key shop-key--fn" type="button" aria-label="Delete" @click="key('backspace')"><x-shop.icon name="backspace" size="lg" /></button>
                    </div>
                </section>

                <section class="shop-stack shop-stack--tight">
                    <h2 class="shop-label">Last scans</h2>
                    <div class="shop-list" x-show="history.length" x-cloak>
                        <template x-for="row in history" :key="row.code">
                            <div class="shop-row" :class="{ 'is-latest': product && row.code === product.code }">
                                <div class="shop-row__main">
                                    <span class="shop-row__title" x-text="row.name"></span>
                                    <span class="shop-row__meta" x-text="rowMeta(row)"></span>
                                </div>
                                <div class="shop-row__aside"><span class="shop-row__qty" x-text="row.stock"></span></div>
                            </div>
                        </template>
                    </div>
                    <div class="shop-empty" x-show="! history.length" x-cloak>
                        <p class="shop-empty__text">No scans yet</p>
                    </div>
                </section>
            </div>
        </div>

        <div class="shop-actions">
            <button class="shop-btn shop-btn--secondary shop-btn--lg" type="button" @click="cancel()" :disabled="delta === 0">Cancel</button>
            <button class="shop-btn shop-btn--primary shop-btn--lg" type="button" @click="save()" :disabled="! product || delta === 0 || busy">
                <x-shop.icon name="check" />
                <span x-text="'Save · ' + newStock"></span>
            </button>
        </div>

        <div class="shop-toasts" role="status" x-show="toast" x-cloak>
            <div class="shop-toast" :class="toast && 'shop-toast--' + toast.tone">
                <span class="shop-toast__icon"><x-shop.icon name="check" size="sm" /></span>
                <span class="shop-toast__text" x-text="toast?.text"></span>
            </div>
        </div>
    </main>
</x-shop-layout>
