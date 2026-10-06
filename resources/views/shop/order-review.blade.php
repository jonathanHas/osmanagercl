<x-shop-layout :title="'Order · '.$header['supplier']"
               :subtitle="'Delivery '.($header['delivery_date'] ?? 'not set').' · '.$header['status'].' by '.$header['created_by']"
               :back="route('shop.orders')">
    <main class="shop-page" x-data="shopOrderReview()" data-items-url="{{ route('shop.orders.items', $order) }}" x-on:click.window="maybeUnpin($event)">
        <div class="shop-facts shop-facts--4">
            <div class="shop-fact"><span class="shop-label">Order value</span><span class="shop-fact__value" x-text="eur(totalValue)">€{{ number_format($header['total_value'], 2) }}</span></div>
            <div class="shop-fact"><span class="shop-label">Products ordered</span><span class="shop-fact__value" x-text="orderedCount + ' of ' + allCount">{{ $header['ordered_count'] }} of {{ $header['item_count'] }}</span></div>
            <div class="shop-fact">
                <span class="shop-label">Lasts until</span>
                <span class="shop-fact__value">{{ $header['lasts_until'] ?? '—' }}</span>
                @if ($header['weeks_after_delivery'] !== null)
                    <span class="shop-hint">{{ rtrim(rtrim(number_format($header['weeks_after_delivery'], 1), '0'), '.') }} weeks after delivery</span>
                @endif
            </div>
            <div class="shop-fact"><span class="shop-label">Based on</span><span class="shop-fact__value">{{ $header['history_weeks'] }} weeks</span><span class="shop-hint">of sales</span></div>
        </div>

        <div class="shop-stack shop-ord-controls">
            <div class="shop-between">
                <div class="shop-seg" role="radiogroup" aria-label="Show">
                    <template x-for="f in filters" :key="f.key">
                        <button class="shop-seg__opt" type="button" :aria-pressed="filter === f.key" @click="filter = f.key" x-text="f.label + ' ' + f.count"></button>
                    </template>
                </div>
                <label class="shop-switch"><input type="checkbox" x-model="showUnordered"><span class="shop-switch__track"></span><span class="shop-meta shop-switch__label">Show not ordered</span></label>
            </div>
            <div class="shop-ord-tools">
                <div class="shop-search">
                    <x-shop.icon name="search" />
                    <input class="shop-input" type="search" placeholder="Find in this order" x-model="q">
                </div>
                <select class="shop-input shop-select" aria-label="Sort" x-model="sort">
                    <option value="sales">Best sellers</option>
                    <option value="cover">Lowest cover</option>
                    <option value="name">A–Z</option>
                </select>
            </div>
        </div>

        <template x-for="g in groups" :key="g.key">
            <section class="shop-stack shop-stack--tight">
                <h2 class="shop-group-title"><span x-text="g.title"></span> <small x-text="g.items.length"></small></h2>
                <div class="shop-list">
                    <template x-for="it in shown(g)" :key="it.id">
                        <article class="shop-ord" :class="{ 'is-unordered': isUnordered(it) }">
                            <div class="shop-ord__product shop-ord__product--pic">
                                <x-shop.product-thumb expr="it" x-on:mouseenter="peekAt(it, $el)" x-on:mouseleave="unpeek()" x-on:click="pinPeek(it, $el)" />
                                <div class="shop-ord__text">
                                    <h3 class="shop-row__title" x-text="it.name"></h3>
                                    <span class="shop-row__meta"><span class="shop-code" x-text="it.code"></span> · <span x-text="packLabel(it)"></span></span>
                                    <div class="shop-ord__tags">
                                        <span :class="'shop-pill shop-pill--' + prio(it)[0]" x-text="prio(it)[1]"></span>
                                        <template x-for="t in it.tags" :key="t">
                                            <span class="shop-pill shop-pill--muted" x-text="t"></span>
                                        </template>
                                    </div>
                                </div>
                            </div>
                            <div class="shop-spark">
                                <div class="shop-spark__head"><span><strong x-text="fmt(it.sold)"></strong> sold</span><span class="shop-spark__key" x-text="'avg ' + fmt(it.avg_weekly) + '/wk'"></span><span x-text="'peak ' + fmt(it.peak_weekly)"></span><span class="shop-spark__key shop-spark__key--proj">stock</span></div>
                                <div class="shop-spark__plot" role="img" :aria-label="chartLabel(it)" x-on:pointermove="hover(it, $event)" x-on:pointerleave="unhover(it, $el)" x-on:pointerdown="pinBar(it, $event)" x-on:click.outside="unpinBar(it, $el)">
                                    {{-- Bars come from one x-html per side (sparkPast / sparkFuture in
                                         order-review.js): per-bar x-for bindings made a 1,461-item
                                         Udea order too slow to render (order_clean cycle 1, Revision 2). --}}
                                    <div class="shop-spark__past" x-html="sparkPast(it)"></div>
                                    <div class="shop-spark__future" x-html="sparkFuture(it)"></div>
                                    <span class="shop-spark__tip" x-show="it.hot" :class="'is-' + (it.hot?.align ?? 'mid')" :style="'left:' + (it.hot?.x ?? 0) + 'px'" x-text="it.hot ? tipText(it) : ''"></span>
                                </div>
                                <div class="shop-spark__axis"><span><span x-text="order.history_weeks + ' wk ago'"></span><span>last wk</span></span><span>after delivery</span></div>
                            </div>
                            <div class="shop-ord__stock">
                                <div class="shop-stack"><span class="shop-label">In stock</span><span class="shop-ord__num" :class="{ 'is-zero': it.stock <= 0 }" x-text="fmt(it.stock)"></span></div>
                                <div class="shop-stack"><span class="shop-label">After delivery</span><span class="shop-ord__num" x-text="fmt(after(it))"></span></div>
                                <span :class="'shop-pill shop-pill--' + coverState(it)[0]" x-text="coverState(it)[1]"></span>
                            </div>
                            <div class="shop-ord__order">
                                <div class="shop-ord__stepper">
                                    <button class="shop-iconbtn" type="button" :aria-label="it.group === 'case' ? 'One case less' : 'One less'" :disabled="! order.editable || it.busy || it.final_cases <= 0" @click="dec(it)"><x-shop.icon name="minus" /></button>
                                    <output class="shop-ord__qty" :class="{ 'is-case': it.group === 'case', 'is-unit': it.group !== 'case', 'is-changed': isChanged(it) }"><span x-text="fmt(it.final_cases)"></span><small x-text="caseWord(it)"></small></output>
                                    <button class="shop-iconbtn" type="button" :aria-label="it.group === 'case' ? 'One case more' : 'One more'" :disabled="! order.editable || it.busy" @click="inc(it)"><x-shop.icon name="plus" /></button>
                                </div>
                                <div class="shop-ord__line"><span><span x-text="unitsLabel(it)"></span> · <strong x-text="eur(cost(it))"></strong></span>
                                    <button class="shop-ord__reset" type="button" x-show="isChanged(it) && order.editable" @click="reset(it)"><x-shop.icon name="history" size="sm" /><span x-text="'Suggested ' + fmt(it.suggested_cases)"></span></button>
                                    <span x-show="! (isChanged(it) && order.editable)" x-text="isChanged(it) ? 'Suggested ' + fmt(it.suggested_cases) : 'Suggested'"></span>
                                </div>
                            </div>
                        </article>
                    </template>
                </div>
                <button class="shop-btn shop-btn--secondary shop-btn--block" type="button" x-show="remaining(g) > 0" @click="more(g)" x-text="moreLabel(g)"></button>
            </section>
        </template>

        <div class="shop-card" x-show="! loading && ! groups.length" x-cloak>
            <div class="shop-empty">
                <span class="shop-empty__icon"><x-shop.icon name="search" size="xl" /></span>
                <p class="shop-empty__title">Nothing matches</p>
                <p class="shop-empty__text">Try another filter, or turn on “Show not ordered”.</p>
            </div>
        </div>
        <p class="shop-meta" x-show="loading">Loading order…</p>

        <div class="shop-actions">
            <div class="shop-actions__spacer shop-stack shop-ord-total"><span class="shop-label">Total</span><span class="shop-ord-total__value" x-text="eur(totalValue)">€{{ number_format($header['total_value'], 2) }}</span></div>
            <a class="shop-btn shop-btn--primary shop-btn--lg shop-btn--export" href="{{ route('shop.orders.export', $order) }}">
                <x-shop.icon name="download" />
                Export CSV
            </a>
        </div>

        <div class="shop-toasts" role="status" x-show="toast" x-cloak>
            <div class="shop-toast" :class="toast && 'shop-toast--' + toast.tone">
                <span class="shop-toast__icon" x-show="! toast || toast.tone === 'ok'"><x-shop.icon name="check" size="sm" /></span>
                <span class="shop-toast__icon" x-show="toast && toast.tone !== 'ok'" x-cloak><x-shop.icon name="alert" size="sm" /></span>
                <span class="shop-toast__text" x-text="toast?.text"></span>
            </div>
        </div>

        {{-- The larger picture beside a hovered (or, on touch, tapped) thumbnail: Find product's panel, shared through product-peek.js. --}}
        <div class="shop-peek" :class="{ 'is-open': peek, 'is-pinned': peekPinned }" :style="peek ? 'left:' + peek.x + 'px; top:' + peek.y + 'px' : ''" aria-hidden="true">
            <img :src="peek?.product.image_url" :alt="peek?.product.name || ''" decoding="async">
            <div class="shop-peek__name" x-text="peek?.product.name"></div>
        </div>
    </main>
</x-shop-layout>
