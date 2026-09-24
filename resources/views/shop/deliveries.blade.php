<x-shop-layout title="Receive delivery" :back="route('shop.home')">
    <main class="shop-page shop-page--narrow">
        <section class="shop-card">
            <h2 class="shop-label">Start a delivery</h2>
            <form method="POST" action="{{ route('delivery-legacy.create-session') }}" class="shop-stack shop-stack--tight">
                @csrf
                <input type="hidden" name="return" value="shop">
                <div class="shop-field">
                    <label class="shop-field__label" for="supplierID">Supplier</label>
                    <select class="shop-input" id="supplierID" name="supplierID" required>
                        <option value="">Choose a supplier…</option>
                        @foreach ($suppliers as $supplier)
                            <option value="{{ $supplier->SupplierID }}">{{ $supplier->Supplier }}</option>
                        @endforeach
                    </select>
                </div>
                <button class="shop-btn shop-btn--primary shop-btn--block" type="submit">
                    <x-shop.icon name="scan" />
                    Start scanning
                </button>
            </form>
        </section>

        <section class="shop-stack shop-stack--tight">
            <div class="shop-between">
                <h2 class="shop-label">Open deliveries</h2>
                <span class="shop-meta">{{ count($open) }} open</span>
            </div>

            @if (count($open))
                <div class="shop-list">
                    @foreach ($open as $session)
                        @php($items = $counts[$session->ID] ?? 0)
                        <a class="shop-row" href="{{ route('shop.deliveries.scan', ['delID' => $session->ID, 'supplierID' => $session->supID]) }}">
                            <span class="shop-row__lead"><x-shop.icon name="truck" /></span>
                            <div class="shop-row__main">
                                <span class="shop-row__title">{{ $session->Supplier ?? 'Unknown supplier' }}</span>
                                <span class="shop-row__meta shop-code">{{ substr($session->ID, 0, 8) }} · {{ $session->dateUpload ? \Illuminate\Support\Carbon::parse($session->dateUpload)->format('D j M, H:i') : 'no date' }}</span>
                                <span class="shop-row__meta">{{ $items }} items scanned</span>
                            </div>
                            <div class="shop-row__aside">
                                <span class="shop-pill {{ $items > 0 ? 'shop-pill--sage' : 'shop-pill--muted' }}">{{ $items > 0 ? 'In progress' : 'Not started' }}</span>
                            </div>
                            <x-shop.icon name="chevron-right" class="shop-row__chev" />
                        </a>
                    @endforeach
                </div>
            @else
                <div class="shop-empty">
                    <div class="shop-empty__icon"><x-shop.icon name="inbox" size="xl" /></div>
                    <p class="shop-empty__title">No open deliveries</p>
                    <p class="shop-empty__text">Choose a supplier above to start scanning one in.</p>
                </div>
            @endif
        </section>

        @if (count($completed))
            <section class="shop-stack shop-stack--tight">
                <h2 class="shop-label">Recently completed</h2>
                <div class="shop-list">
                    @foreach ($completed as $session)
                        <div class="shop-row is-off">
                            <span class="shop-row__lead"><x-shop.icon name="check" /></span>
                            <div class="shop-row__main">
                                <span class="shop-row__title">{{ $session->Supplier ?? 'Unknown supplier' }}</span>
                                <span class="shop-row__meta shop-code">{{ substr($session->ID, 0, 8) }} · {{ $counts[$session->ID] ?? 0 }} items</span>
                            </div>
                            <div class="shop-row__aside">
                                <span class="shop-pill shop-pill--ok">Completed</span>
                            </div>
                        </div>
                    @endforeach
                </div>
            </section>
        @endif
    </main>
</x-shop-layout>
