<x-shop-layout title="Fruit & veg" :back="route('shop.home')">
    <main class="shop-page" x-data="shopFvHarvest()"
          data-rows-url="{{ route('fruit-veg.harvest.rows') }}"
          data-save-url="{{ route('fruit-veg.harvest.save-row') }}">
        @include('shop.partials.fv-nav', ['active' => 'harvest'])

        <div class="shop-split">
            <div class="shop-stack">
                <section class="shop-stack">
                    <h2 class="shop-subtitle">What did you pick?</h2>
                    {{-- The list is short by design, so say what it is; otherwise
                         "recent picks" and "everything" look like the same list. --}}
                    <p class="shop-meta" x-text="searching ? 'All of your produce matching the search' : 'Recent picks · search to add anything else'"></p>

                    <div class="shop-search">
                        <x-shop.icon name="search" />
                        <input class="shop-input" type="search" placeholder="Search all your produce" x-model="query">
                    </div>

                    <div class="shop-choices">
                        <template x-for="p in choices" :key="p.code">
                            <label class="shop-choice shop-choice--pic">
                                <input type="radio" name="h" :value="p.code" :checked="selected && selected.code === p.code" @change="select(p)">
                                <x-shop.product-thumb placeholder="carrot" />
                                <span class="shop-choice__text">
                                    <span x-text="p.name"></span>
                                    <small x-text="(p.unit === 'kg' ? 'per kg' : 'units') + (p.logged > 0 ? ' · ' + p.logged + ' ' + p.unit + ' today' : '')"></small>
                                </span>
                            </label>
                        </template>
                    </div>

                    {{-- Two different nothings: no history yet, and a search that
                         missed. The first is the normal state of a fresh season. --}}
                    <div class="shop-empty" x-show="! loading && ! searching && ! hasRecent" x-cloak>
                        <div class="shop-empty__icon"><x-shop.icon name="carrot" size="xl" /></div>
                        <p class="shop-empty__title">Nothing harvested recently</p>
                        <p class="shop-empty__text">Search your produce above to log the first pick.</p>
                    </div>

                    <div class="shop-empty" x-show="! loading && searching && ! choices.length" x-cloak>
                        <div class="shop-empty__icon"><x-shop.icon name="search" size="xl" /></div>
                        <p class="shop-empty__title">No produce matches</p>
                        <p class="shop-empty__text">Try a shorter search.</p>
                    </div>
                </section>

                <section class="shop-stack shop-stack--tight" x-show="today.length" x-cloak>
                    <h2 class="shop-label">Today</h2>
                    <div class="shop-list">
                        <template x-for="r in today" :key="'t-' + r.code">
                            <div class="shop-row">
                                <x-shop.product-thumb expr="r" placeholder="carrot" />
                                <div class="shop-row__main">
                                    <span class="shop-row__title" x-text="r.name"></span>
                                    <span class="shop-row__meta" x-text="when(r.updated_at) + (r.by ? ' · ' + r.by : '')"></span>
                                </div>
                                <div class="shop-row__aside">
                                    <span class="shop-row__qty" x-text="r.logged + ' ' + r.unit"></span>
                                </div>
                            </div>
                        </template>
                    </div>
                </section>
            </div>

            <section class="shop-card" x-show="selected" x-cloak>
                <div class="shop-seg shop-seg--block" role="radiogroup" aria-label="Unit">
                    <label class="shop-seg__opt">
                        <input type="radio" name="hunit" value="kg" :checked="unit === 'kg'" @change="setUnit('kg')">
                        kg
                    </label>
                    <label class="shop-seg__opt">
                        <input type="radio" name="hunit" value="unit" :checked="unit === 'unit'" @change="setUnit('unit')">
                        units
                    </label>
                </div>

                <div class="shop-numpad">
                    <div class="shop-numpad__display">
                        <span class="shop-label" x-text="'Quantity (' + (unit === 'kg' ? 'kg' : 'units') + ')'"></span>
                        <span x-text="typed || '0'"></span>
                    </div>
                    @foreach (range(1, 9) as $digit)
                        <button class="shop-key" type="button" @click="key('{{ $digit }}')">{{ $digit }}</button>
                    @endforeach
                    <button class="shop-key shop-key--fn" type="button" aria-label="Decimal point" @click="key('.')">.</button>
                    <button class="shop-key" type="button" @click="key('0')">0</button>
                    <button class="shop-key shop-key--fn" type="button" aria-label="Delete" @click="key('backspace')"><x-shop.icon name="backspace" size="lg" /></button>
                </div>
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
