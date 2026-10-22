<x-shop-layout title="Print labels" :back="route('shop.home')">
    <main class="shop-page shop-page--narrow" x-data="shopLabels()"
          data-queue-url="{{ route('labels.queue') }}"
          data-scan-url="{{ route('labels.scan') }}"
          data-dismiss-url="{{ route('labels.dismiss') }}"
          data-clear-url="{{ $canClear ? route('labels.dismiss-all') : '' }}"
          data-printed-url="{{ route('labels.printed-today') }}"
          data-requeue-url="{{ route('labels.requeue') }}"
          data-restore-url="{{ $canClear ? route('labels.restore-batch') : '' }}"
          @scan="onScan($event.detail.code)">
        @include('shop.partials.labels-nav', ['active' => 'shelf'])

        <x-shop.scan-input placeholder="Scan a product to add a label" hint="Ready — scan to add to the queue" />

        <div class="shop-between">
            <h2 class="shop-subtitle">Print queue</h2>
            <span class="shop-meta" x-text="total ? total + (total === 1 ? ' label · ' : ' labels · ') + sheets + ' A4 sheet' + (sheets === 1 ? '' : 's') : ''"></span>
        </div>

        <div class="shop-list" x-show="total > 0" x-cloak>
            <template x-for="row in rows" :key="row.id">
                <div class="shop-row">
                    <div class="shop-row__main">
                        <span class="shop-row__title" x-text="row.name"></span>
                        <span class="shop-row__meta" x-text="reasonLabel(row) + ' · ' + row.price"></span>
                    </div>
                    <div class="shop-qty">
                        <span class="shop-qty__value">&times;1</span>
                        <button class="shop-iconbtn shop-iconbtn--ghost" type="button" :aria-label="'Remove ' + row.name" :disabled="busy" @click="dismiss(row)">
                            <x-shop.icon name="x" />
                        </button>
                    </div>
                </div>
            </template>
        </div>

        <div class="shop-empty" x-show="! loading && ! error && total === 0" x-cloak>
            <div class="shop-empty__icon"><x-shop.icon name="check" size="xl" /></div>
            <p class="shop-empty__title">All caught up</p>
            <p class="shop-empty__text">Every product has its shelf label. Scan a product to add one.</p>
        </div>

        <section class="shop-empty" x-show="error" x-cloak>
            <div class="shop-empty__icon"><x-shop.icon name="alert" size="xl" /></div>
            <p class="shop-empty__title">Something went wrong</p>
            <p class="shop-empty__text" x-text="error"></p>
        </section>

        {{-- Printed today: the history is a convenience, so it stays collapsed and
             never blocks the queue. Batch rows sit in an x-if; their handlers only
             touch component state, never $root. --}}
        <section class="shop-list" aria-label="Printed today" x-show="batches.length" x-cloak>
            <button class="shop-row" type="button" :aria-expanded="historyOpen" @click="historyOpen = ! historyOpen">
                <span class="shop-row__lead"><x-shop.icon name="printer" /></span>
                <span class="shop-row__main">
                    <span class="shop-row__title">Printed today</span>
                    <span class="shop-row__meta" x-text="historyMeta"></span>
                </span>
                <x-shop.icon name="chevron-right" class="shop-row__chev" x-bind:class="historyOpen && 'is-open'" />
            </button>
            <template x-if="historyOpen">
                <div class="shop-contents">
                    <template x-for="b in batches" :key="b.key">
                        <div class="shop-contents">
                            <button class="shop-row shop-row--batch" type="button" :aria-expanded="openBatch === b.key" @click="openBatch = openBatch === b.key ? null : b.key">
                                <span class="shop-row__main">
                                    <span class="shop-row__title" x-text="b.time + ' · ' + plural(b.count, 'label')"></span>
                                    <span class="shop-row__meta" x-text="plural(b.items.length, 'product')"></span>
                                </span>
                                <x-shop.icon name="chevron-right" class="shop-row__chev" x-bind:class="openBatch === b.key && 'is-open'" />
                            </button>
                            <template x-for="it in b.items" :key="it.key">
                                <div class="shop-row shop-row--nested" x-show="openBatch === b.key">
                                    <div class="shop-row__main">
                                        <span class="shop-row__title" x-text="it.name"></span>
                                        <span class="shop-row__meta" x-text="it.meta"></span>
                                    </div>
                                    <button class="shop-btn shop-btn--secondary shop-row__action" type="button" x-show="it.id && ! inQueue(it)" :disabled="busy" @click="requeue(b, it)">Back to queue</button>
                                    <span class="shop-pill shop-pill--ok" x-show="it.id && inQueue(it)">In queue</span>
                                </div>
                            </template>
                        </div>
                    </template>
                </div>
            </template>
        </section>

        {{-- The A4 sheet opens in a new tab, so this tab's queue is stale until the
             prints are logged; reload shortly after submitting. --}}
        {{-- shop-contents (display: contents) so the sticky Print bar is laid out in <main> and sticks. --}}
        <form class="shop-contents" method="POST" action="{{ route('labels.print-a4') }}" target="_blank"
              x-show="total > 0" x-cloak @submit="setTimeout(() => { load(); loadPrinted(); }, 1500)">
            @csrf
            <template x-for="id in ids" :key="id">
                <input type="hidden" name="products[]" :value="id">
            </template>

            <div class="shop-actions">
                @if ($canClear)
                    <button type="button" class="shop-btn shop-btn--secondary shop-btn--lg" :disabled="busy" @click="clear()">Clear queue</button>
                @endif
                <button type="submit" class="shop-btn shop-btn--primary shop-btn--lg">
                    <x-shop.icon name="printer" />
                    <span x-text="'Print ' + total + ' label' + (total === 1 ? '' : 's')"></span>
                </button>
            </div>
        </form>

        <div class="shop-toasts" role="status" x-show="toast" x-cloak>
            <div class="shop-toast" :class="toast && 'shop-toast--' + toast.tone">
                <span class="shop-toast__icon" x-show="! toast || toast.tone === 'ok'"><x-shop.icon name="check" size="sm" /></span>
                <span class="shop-toast__icon" x-show="toast && toast.tone !== 'ok'" x-cloak><x-shop.icon name="alert" size="sm" /></span>
                <span class="shop-toast__text" x-text="toast?.text"></span>
                {{-- "!!": Alpine calls a function an expression returns, so a bare
                     toast?.undo would run the undo the moment the toast shows. --}}
                <button class="shop-btn shop-btn--ghost" type="button" x-show="!! toast?.undo" @click="undo()">Undo</button>
            </div>
        </div>
    </main>
</x-shop-layout>
