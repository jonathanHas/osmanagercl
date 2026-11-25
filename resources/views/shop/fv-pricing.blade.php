<x-shop-layout title="Fruit & veg" :back="route('shop.home')">
    <main class="shop-page shop-page--narrow">
        @include('shop.partials.fv-nav', ['active' => 'pricing'])

        <section class="shop-stack shop-stack--tight">
            <h2 class="shop-label">Dynamis invoices</h2>

            @if (count($invoices))
                <div class="shop-list">
                    @foreach ($invoices as $invoice)
                        <a class="shop-row{{ $invoice['todo'] === 0 ? ' is-off' : '' }}" href="{{ route('shop.fv.pricing.show', $invoice['delivery']) }}">
                            <span class="shop-row__lead"><x-shop.icon name="tag" /></span>
                            <div class="shop-row__main">
                                <span class="shop-row__title">{{ $invoice['date']?->format('D j M') ?? 'No date' }}</span>
                                <span class="shop-row__meta">{{ $invoice['invoice_number'] ?? $invoice['delivery']->delivery_number }} · {{ $invoice['lines'] }} fruit &amp; veg {{ $invoice['lines'] === 1 ? 'line' : 'lines' }}</span>
                                <span class="shop-row__meta">{{ $invoice['priced'] }} priced{{ $invoice['skipped'] ? ' · '.$invoice['skipped'].' skipped' : '' }}</span>
                            </div>
                            <div class="shop-row__aside">
                                @if ($invoice['todo'] > 0)
                                    <span class="shop-pill shop-pill--warn">{{ $invoice['todo'] }} to do</span>
                                @else
                                    <span class="shop-pill shop-pill--ok">Done</span>
                                @endif
                            </div>
                            <x-shop.icon name="chevron-right" class="shop-row__chev" />
                        </a>
                    @endforeach
                </div>
            @else
                <div class="shop-empty">
                    <div class="shop-empty__icon"><x-shop.icon name="inbox" size="xl" /></div>
                    <p class="shop-empty__title">No invoices yet</p>
                    <p class="shop-empty__text">Upload the Dynamis invoice PDF in the office (Delivery Dockets, New); it then appears here.</p>
                </div>
            @endif
        </section>
    </main>
</x-shop-layout>
