<x-shop-layout title="Shop">
    <main class="shop-page">
        <div class="shop-stack shop-stack--tight">
            <h2 class="shop-title">{{ $greeting }}</h2>
            <p class="shop-meta">{{ $today }}</p>
        </div>

        @if (empty($tiles))
            <div class="shop-empty">
                <div class="shop-empty__icon"><x-shop.icon name="inbox" size="xl" /></div>
                <p class="shop-empty__title">Nothing to do here yet</p>
                <p class="shop-empty__text">Ask a manager to give you access to a task.</p>
            </div>
        @else
            <nav class="shop-tiles" aria-label="Tasks">
                @foreach ($tiles as $tile)
                    <x-shop.tile
                        :href="route($tile['route'])"
                        :label="$tile['label']"
                        :hint="$tile['hint']"
                        :icon="$tile['icon']"
                        :badge="$tile['count']"
                        :tone="$tile['tone'] ?? null"
                    />
                @endforeach
            </nav>
        @endif
    </main>
</x-shop-layout>
