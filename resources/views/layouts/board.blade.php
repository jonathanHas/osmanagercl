<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta name="robots" content="noindex, nofollow">
        @guest
            {{-- Guest tablets never post forms, so a periodic reload keeps the board fresh without risking a half-typed edit. --}}
            <meta http-equiv="refresh" content="300">
        @endguest

        <title>{{ $title }} - {{ config('app.name', 'Laravel') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        <style>[x-cloak] { display: none !important; }</style>
    </head>
    <body class="font-sans antialiased bg-gray-100 text-gray-900">
        <div class="min-h-screen">
            <header class="bg-gray-900 text-white">
                <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-3 flex items-center justify-between gap-4">
                    <div class="flex items-center gap-3 min-w-0">
                        <a href="{{ auth()->check() ? route('dashboard') : url('/') }}" class="flex-shrink-0">
                            <x-application-logo class="w-8 h-8 fill-current text-gray-300" />
                        </a>
                        <div class="min-w-0">
                            <h1 class="text-lg font-semibold leading-tight truncate">{{ $title }}</h1>
                            <p class="text-xs text-gray-400">{{ now()->format('l j F Y') }}</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-2 flex-shrink-0">
                        {{ $actions ?? '' }}
                        @auth
                            <span class="hidden sm:inline text-sm text-gray-300">{{ auth()->user()->name }}</span>
                            <a href="{{ route('dashboard') }}" class="text-sm text-gray-300 hover:text-white px-2 py-1">Dashboard</a>
                        @else
                            <a href="{{ route('login') }}" class="inline-flex items-center px-3 py-1.5 rounded-md bg-gray-700 hover:bg-gray-600 text-sm font-medium text-white">
                                Staff sign in
                            </a>
                        @endauth
                    </div>
                </div>
            </header>

            <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
                {{ $slot }}
            </main>
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
                            window.location.href = '{{ route("login") }}';
                        }
                    })
                    .catch(() => {});
                }
            });
        </script>
        @endauth
    </body>
</html>
