<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta name="robots" content="noindex, nofollow">
        @guest
            @if ($guestRefresh)
                <meta http-equiv="refresh" content="{{ $guestRefresh }}">
            @endif
        @endguest

        <title>{{ $title }} · Shop</title>

        {{-- The design needs Figtree 500–800; the admin layout's bunny.net link only carries 400–600. --}}
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Figtree:wght@500;600;700;800&display=swap">

        {{-- shop.js must load before app.js: app.js calls Alpine.start() on evaluation. --}}
        @vite(['resources/css/app.css', 'resources/css/shop.css', 'resources/js/shop.js', 'resources/js/app.js'])
        <style>body{margin:0;background:#f5ead8}[x-cloak]{display:none!important}</style>
    </head>
    <body data-shell="shop">
        <div class="shop" id="shop-root">
        {{-- Before paint, so the touch-only controls do not flash in on the till PC. --}}
        <script>if (window.matchMedia('(pointer: coarse)').matches) document.getElementById('shop-root').classList.add('is-touch');</script>

            @unless($bare)
                <x-shop.topbar :title="$title" :back="$back" :guest-safe="$guestSafe" />
            @endunless

            {{-- Server flash as a toast, so a redirect after an action says what happened.
                 Pages with their own client toast region can only overlap with this for the
                 few seconds after a redirect, before the person has done anything. --}}
            @if (session('success') || session('error'))
                <div class="shop-toasts" role="status" x-data="{ open: true }" x-show="open" x-init="setTimeout(() => open = false, 8000)">
                    <div class="shop-toast {{ session('error') ? 'shop-toast--bad' : 'shop-toast--ok' }}">
                        <span class="shop-toast__icon"><x-shop.icon :name="session('error') ? 'alert' : 'check'" size="sm" /></span>
                        <span class="shop-toast__text">{{ session('error') ?? session('success') }}</span>
                        <button class="shop-iconbtn shop-iconbtn--ghost" type="button" aria-label="Dismiss" @click="open = false"><x-shop.icon name="x" /></button>
                    </div>
                </div>
            @endif

            {{ $slot }}
        </div>

        @stack('scripts')

        @auth
        {{-- Stale page detector: a staff member who signed in on the shared tablet and walked away gets bounced to login instead of a 419 on their next tap. --}}
        <script>
            document.addEventListener('visibilitychange', function() {
                if (document.visibilityState === 'visible') {
                    fetch('{{ route("auth.check") }}', {
                        credentials: 'same-origin',
                        headers: { 'Accept': 'application/json' }
                    })
                    .then(r => r.json())
                    .then(data => {
                        if (!data.authenticated) {
                            window.location.href = '{{ route("login", ["redirect" => request()->getRequestUri()]) }}';
                        }
                    })
                    .catch(() => {});
                }
            });
        </script>
        @endauth
    </body>
</html>
