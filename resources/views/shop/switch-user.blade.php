<x-shop-layout title="Switch user" :back="$signedIn ? route('shop.home') : null" guest-safe>
    <main class="shop-page shop-page--narrow">
        <h2 class="shop-title">Who is working?</h2>

        @if ($people->isEmpty())
            <section class="shop-empty">
                <div class="shop-empty__icon"><x-shop.icon name="users" size="xl" /></div>
                <p class="shop-empty__title">Nobody has a PIN yet</p>
                <p class="shop-empty__text">A manager can set one on the staff page in the office.</p>
            </section>
        @else
            <div class="shop-people">
                @foreach ($people as $person)
                    <a class="shop-person" href="{{ route('shop.switch.pin', $person['id']) }}">
                        <span class="shop-avatar shop-avatar--lg {{ $person['tone'] }}">{{ $person['initials'] }}</span>
                        <span>{{ $person['first_name'] }}</span>
                        @if ($person['is_current'])
                            <span class="shop-pill shop-pill--ok">Signed in</span>
                        @endif
                    </a>
                @endforeach
            </div>
        @endif

        <p class="shop-meta">Managers sign in with a password.</p>
        <div class="shop-inline">
            <a class="shop-btn shop-btn--secondary" href="{{ route('login', ['redirect' => route('shop.home', absolute: false)]) }}"><x-shop.icon name="login" />Sign in with password</a>
        </div>
    </main>
</x-shop-layout>
