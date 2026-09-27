<x-shop-layout title="Locked" bare :guest-refresh="60">
    {{-- The clock is server-rendered and the layout's meta refresh keeps it
         honest, so the Locked screen needs no JavaScript at all. --}}
    <main class="shop-lock">
        <span class="shop-topbar__mark"><x-shop.icon name="lock" /></span>
        <div class="shop-lock__time">{{ now()->format('H:i') }}</div>
        <p class="shop-lock__date">{{ now()->format('l j F') }}</p>
        <p class="shop-meta">Locked after {{ config('shop.idle_lock_minutes') }} minutes without activity</p>
        <div class="shop-inline">
            <a class="shop-btn shop-btn--primary shop-btn--lg" href="{{ route('shop.switch') }}">Tap to unlock</a>
        </div>
    </main>
</x-shop-layout>
