<x-shop-layout title="Fruit & veg" :back="route('shop.home')">
    <main class="shop-page" x-data="shopFvWaste()"
          data-rows-url="{{ route('fruit-veg.waste.rows') }}"
          data-search-url="{{ route('fruit-veg.waste.search') }}"
          data-entry-url="{{ route('fruit-veg.waste.entry') }}">
        @include('shop.partials.fv-nav', ['active' => 'waste'])

        <div class="shop-split">
            <section class="shop-stack">
                <h2 class="shop-subtitle">1 · Product</h2>

                <div class="shop-search">
                    <x-shop.icon name="search" />
                    <input class="shop-input" type="search" placeholder="Search products"
                           x-model="query" @input.debounce.300ms="search()">
                </div>

                <div class="shop-choices">
                    <template x-for="p in shown" :key="p.code">
                        <label class="shop-choice shop-choice--pic">
                            <input type="radio" name="p" :value="p.code" :checked="selected && selected.code === p.code" @change="select(p)">
                            <x-shop.product-thumb placeholder="carrot" />
                            <span class="shop-choice__text">
                                <span x-text="p.name"></span>
                                <small x-text="(p.priced_unit === 'kg' ? 'per kg' : 'each') + (p.quantity > 0 ? ' · ' + p.quantity + ' ' + p.unit + ' today' : '')"></small>
                            </span>
                        </label>
                    </template>
                </div>

                <div class="shop-empty" x-show="! loading && ! shown.length" x-cloak>
                    <div class="shop-empty__icon"><x-shop.icon name="search" size="xl" /></div>
                    <p class="shop-empty__title">No products match</p>
                    <p class="shop-empty__text">Try a shorter search.</p>
                </div>

                <section class="shop-stack shop-stack--tight" x-show="today.length" x-cloak>
                    <h2 class="shop-label">Today</h2>
                    <div class="shop-list">
                        <template x-for="p in today" :key="'t-' + p.code">
                            <div class="shop-row">
                                <x-shop.product-thumb placeholder="carrot" />
                                <div class="shop-row__main">
                                    <span class="shop-row__title" x-text="p.name"></span>
                                    <span class="shop-row__meta" x-text="p.value !== null ? '€' + p.value.toFixed(2) : ''"></span>
                                </div>
                                <div class="shop-row__aside">
                                    <span class="shop-row__qty" x-text="p.quantity + ' ' + p.unit"></span>
                                    <button class="shop-iconbtn" type="button" aria-label="Remove" :disabled="busy" @click="remove(p)"><x-shop.icon name="x" /></button>
                                </div>
                            </div>
                        </template>
                    </div>
                </section>
            </section>

            <section class="shop-card" x-show="selected" x-cloak>
                <h2 class="shop-subtitle">2 · How much</h2>

                <div class="shop-seg shop-seg--block" role="radiogroup" aria-label="Unit">
                    <label class="shop-seg__opt">
                        <input type="radio" name="unit" value="kg" :checked="unit === 'kg'" @change="setUnit('kg')">
                        kg
                    </label>
                    <label class="shop-seg__opt">
                        <input type="radio" name="unit" value="unit" :checked="unit === 'unit'" @change="setUnit('unit')">
                        units
                    </label>
                </div>

                <div class="shop-stepper">
                    <button class="shop-iconbtn shop-iconbtn--lg" type="button" aria-label="Less" @click="bump(-1)"><x-shop.icon name="minus" size="lg" /></button>
                    <output class="shop-stepper__value" x-text="amountText"></output>
                    <button class="shop-iconbtn shop-iconbtn--lg" type="button" aria-label="More" @click="bump(1)"><x-shop.icon name="plus" size="lg" /></button>
                </div>

                {{-- One row per product per day, so a unit change is a replacement,
                     not a second entry. Say so before they tap Log, not after. --}}
                <p class="shop-meta" x-show="unitClash" x-cloak>Logging in a different unit replaces today's entry.</p>
            </section>
        </div>

        <div class="shop-actions" x-show="selected" x-cloak>
            <button class="shop-btn shop-btn--primary shop-btn--lg" type="button" :disabled="! canLog || busy" @click="log()">
                <x-shop.icon name="check" /><span x-text="logLabel"></span>
            </button>
        </div>

        <div class="shop-toasts" role="status" x-show="toast" x-cloak>
            <div class="shop-toast" :class="toast && 'shop-toast--' + toast.tone">
                <span class="shop-toast__icon" x-show="! toast || toast.tone === 'ok'"><x-shop.icon name="check" size="sm" /></span>
                <span class="shop-toast__icon" x-show="toast && toast.tone !== 'ok'" x-cloak><x-shop.icon name="alert" size="sm" /></span>
                <span class="shop-toast__text" x-text="toast?.text"></span>
            </div>
        </div>
    </main>
</x-shop-layout>
