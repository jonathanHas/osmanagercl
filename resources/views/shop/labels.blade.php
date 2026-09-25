<x-shop-layout title="Print labels" :back="route('shop.home')">
    <main class="shop-page" x-data="shopLabels()"
          data-queue-url="{{ route('labels.queue') }}"
          data-scan-url="{{ route('labels.scan') }}"
          data-dismiss-url="{{ route('labels.dismiss') }}"
          data-clear-url="{{ $canClear ? route('labels.dismiss-all') : '' }}"
          @scan="onScan($event.detail.code)">
        <x-shop.scan-input placeholder="Scan a product to add a label" hint="Ready — scan to add to the queue" />

        <nav class="shop-tiles shop-tiles--hub" aria-label="Label tools">
            <a class="shop-tile shop-tile--row" href="{{ $zebraUrl }}">
                <span class="shop-tile__icon"><x-shop.icon name="barcode" size="lg" /></span>
                <span class="shop-tile__text">
                    <span class="shop-tile__label">Zebra labels</span>
                    <span class="shop-tile__hint">Saved labels and the counter printer</span>
                </span>
            </a>
            <a class="shop-tile shop-tile--row" href="{{ route('labels.shelf-labels') }}">
                <span class="shop-tile__icon"><x-shop.icon name="list-checks" size="lg" /></span>
                <span class="shop-tile__text">
                    <span class="shop-tile__label">Printed recently</span>
                    <span class="shop-tile__hint">Office page: history and re-queue</span>
                </span>
            </a>
        </nav>

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
            <p class="shop-empty__text">Nothing needs a label. Scan a product to add one.</p>
        </div>

        <section class="shop-empty" x-show="error" x-cloak>
            <div class="shop-empty__icon"><x-shop.icon name="alert" size="xl" /></div>
            <p class="shop-empty__title">Something went wrong</p>
            <p class="shop-empty__text" x-text="error"></p>
        </section>

        {{-- The A4 sheet opens in a new tab, so this tab's queue is stale until the
             prints are logged; reload shortly after submitting. --}}
        <form method="POST" action="{{ route('labels.print-a4') }}" target="_blank"
              x-show="total > 0" x-cloak @submit="setTimeout(() => load(), 1500)">
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
            </div>
        </div>
    </main>
</x-shop-layout>
