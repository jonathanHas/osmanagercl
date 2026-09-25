@php($scanUrl = route('shop.deliveries.scan', ['delID' => $session['id'], 'supplierID' => $session['supplierId']]))
<x-shop-layout title="Delivery summary" :back="$scanUrl">
    <main class="shop-page shop-page--narrow" x-data="shopDeliverySummary()"
          data-items-url="{{ route('delivery-legacy.items') }}"
          data-del-id="{{ $session['id'] }}"
          data-supplier-id="{{ $session['supplierId'] }}">
        <div class="shop-stack shop-stack--tight">
            <h2 class="shop-subtitle">{{ $session['supplier'] ?? 'Unknown supplier' }} · {{ substr($session['id'], 0, 8) }}</h2>
            <p class="shop-meta">
                {{ $session['date'] ? \Illuminate\Support\Carbon::parse($session['date'])->format('D j M, H:i') : 'No date' }}
                · <span x-text="hasInvoice ? progress.total + ' items expected' : 'no invoice lines loaded'"></span>
            </p>
        </div>

        <section class="shop-card shop-card--flat" x-show="session && session.completed" x-cloak>
            <div class="shop-between">
                <p class="shop-meta">This delivery is completed.</p>
                <span class="shop-pill shop-pill--ok">Completed</span>
            </div>
        </section>

        <div class="shop-totals">
            <div class="shop-total">
                <span class="shop-label">Scanned</span>
                <span class="shop-total__value" x-text="totals.scanned"></span>
            </div>
            <div class="shop-total shop-total--ok">
                <span class="shop-label">OK</span>
                <span class="shop-total__value" x-text="totals.ok"></span>
            </div>
            <div class="shop-total shop-total--warn">
                <span class="shop-label">Short</span>
                <span class="shop-total__value" x-text="totals.short"></span>
            </div>
            <div class="shop-total">
                <span class="shop-label">Over</span>
                <span class="shop-total__value" x-text="totals.over"></span>
            </div>
            <div class="shop-total shop-total--bad">
                <span class="shop-label">Unexpected</span>
                <span class="shop-total__value" x-text="totals.unexpected"></span>
            </div>
            <div class="shop-total" :class="{ 'shop-total--warn': totals.missing > 0 }">
                <span class="shop-label">Not scanned</span>
                <span class="shop-total__value" x-text="totals.missing"></span>
            </div>
        </div>

        <section class="shop-stack shop-stack--tight">
            <h2 class="shop-label">Discrepancies</h2>

            <div class="shop-list" x-show="discrepancies.length" x-cloak>
                <template x-for="row in discrepancies" :key="row.barcode">
                    <div class="shop-row">
                        <div class="shop-row__main">
                            <span class="shop-row__title" x-text="row.name"></span>
                            <span class="shop-row__meta shop-code" x-text="meta(row)"></span>
                        </div>
                        <div class="shop-row__aside">
                            <span class="shop-pill" :class="tone(row)" x-text="label(row)"></span>
                        </div>
                    </div>
                </template>
            </div>

            <div class="shop-empty" x-show="! discrepancies.length && ! loading" x-cloak>
                <div class="shop-empty__icon"><x-shop.icon name="check" size="xl" /></div>
                <p class="shop-empty__title">Everything matches the invoice</p>
                <p class="shop-empty__text">Nothing is short, over or unexpected.</p>
            </div>
        </section>

        <section class="shop-card" x-show="confirming" x-cloak x-ref="confirm">
            <h2 class="shop-subtitle">Complete this delivery?</h2>

            <div class="shop-facts shop-facts--2">
                <div class="shop-fact">
                    <span class="shop-label">Units to add</span>
                    <span class="shop-fact__value" x-text="unitsToAdd"></span>
                </div>
                <div class="shop-fact">
                    <span class="shop-label">Products</span>
                    <span class="shop-fact__value" x-text="productsToUpdate"></span>
                </div>
            </div>

            <p class="shop-meta" x-show="unstockableCount > 0" x-cloak>
                <span x-text="unstockableCount"></span> scanned items are not in the product list and will not be added to stock.
            </p>

            <p class="shop-meta">Stock is updated now and the session is closed. Undo is only available to a manager on the office page.</p>

            <form method="POST" action="{{ route('delivery-legacy.complete') }}">
                @csrf
                <input type="hidden" name="delID" value="{{ $session['id'] }}">
                <input type="hidden" name="supplierID" value="{{ $session['supplierId'] }}">
                <input type="hidden" name="return" value="shop">
                <div class="shop-inline">
                    <button type="button" class="shop-btn shop-btn--secondary shop-btn--lg" @click="cancelComplete()">Not yet</button>
                    <button type="submit" class="shop-btn shop-btn--danger shop-btn--lg">
                        <x-shop.icon name="check" />
                        Yes, complete
                    </button>
                </div>
            </form>
        </section>

        <section class="shop-empty" x-show="error" x-cloak>
            <div class="shop-empty__icon"><x-shop.icon name="alert" size="xl" /></div>
            <p class="shop-empty__title">Something went wrong</p>
            <p class="shop-empty__text" x-text="error"></p>
        </section>

        <div class="shop-actions">
            <a class="shop-btn shop-btn--secondary shop-btn--lg" href="{{ $scanUrl }}">Keep scanning</a>
            <button type="button" class="shop-btn shop-btn--primary shop-btn--lg" x-show="canComplete" x-cloak
                    @click="askToComplete(); $nextTick(() => $refs.confirm.scrollIntoView({ block: 'nearest' }))">
                <x-shop.icon name="check" />
                Complete delivery
            </button>
        </div>
    </main>
</x-shop-layout>
