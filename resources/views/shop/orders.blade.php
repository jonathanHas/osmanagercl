<x-shop-layout title="Orders" :back="route('shop.home')">
    <main class="shop-page shop-page--narrow">
        <section class="shop-stack shop-stack--tight">
            <div class="shop-between">
                <h2 class="shop-label">Draft orders</h2>
                <span class="shop-meta">{{ $draft_total }} {{ $draft_total === 1 ? 'draft' : 'drafts' }}</span>
            </div>

            @if (count($drafts))
                <div class="shop-list">
                    @foreach ($drafts as $session)
                        <a class="shop-row" href="{{ route('shop.orders.review', $session) }}">
                            <span class="shop-row__lead"><x-shop.icon name="chart" /></span>
                            <div class="shop-row__main">
                                <span class="shop-row__title">{{ $session->supplier?->Supplier ?: 'Unknown supplier' }}</span>
                                <span class="shop-row__meta">Delivery {{ $session->order_date?->format('D j M') ?? 'not set' }} · {{ strtok(trim($session->user?->name ?? ''), ' ') ?: 'unknown' }}</span>
                                <span class="shop-row__meta">{{ $session->items_count }} {{ $session->items_count === 1 ? 'product' : 'products' }} · €{{ number_format((float) $session->total_value, 2) }}</span>
                            </div>
                            <div class="shop-row__aside">
                                <span class="shop-pill shop-pill--sage">Draft</span>
                            </div>
                            <x-shop.icon name="chevron-right" class="shop-row__chev" />
                        </a>
                    @endforeach
                </div>

                @if ($draft_total > count($drafts))
                    <p class="shop-meta">Showing the {{ count($drafts) }} most recent</p>
                @endif
            @else
                <div class="shop-empty">
                    <div class="shop-empty__icon"><x-shop.icon name="inbox" size="xl" /></div>
                    <p class="shop-empty__title">No draft orders</p>
                    <p class="shop-empty__text">Orders are generated in the office; drafts appear here.</p>
                </div>
            @endif
        </section>

        @if (count($completed))
            <section class="shop-stack shop-stack--tight">
                <h2 class="shop-label">Recently completed</h2>
                <div class="shop-list">
                    @foreach ($completed as $session)
                        <a class="shop-row is-off" href="{{ route('shop.orders.review', $session) }}">
                            <span class="shop-row__lead"><x-shop.icon name="chart" /></span>
                            <div class="shop-row__main">
                                <span class="shop-row__title">{{ $session->supplier?->Supplier ?: 'Unknown supplier' }}</span>
                                <span class="shop-row__meta">Delivery {{ $session->order_date?->format('D j M') ?? 'not set' }} · {{ strtok(trim($session->user?->name ?? ''), ' ') ?: 'unknown' }}</span>
                                <span class="shop-row__meta">{{ $session->items_count }} {{ $session->items_count === 1 ? 'product' : 'products' }} · €{{ number_format((float) $session->total_value, 2) }}</span>
                            </div>
                            <div class="shop-row__aside">
                                <span class="shop-pill shop-pill--ok">Completed</span>
                            </div>
                            <x-shop.icon name="chevron-right" class="shop-row__chev" />
                        </a>
                    @endforeach
                </div>
            </section>
        @endif
    </main>
</x-shop-layout>
