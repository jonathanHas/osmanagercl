<x-shop-layout title="Switch user" guest-safe>
    <main class="shop-page shop-page--narrow">
        <section class="shop-empty">
            <div class="shop-empty__icon"><x-shop.icon name="lock" size="xl" /></div>
            <p class="shop-empty__title">This device isn't set up for PIN sign-in</p>
            <p class="shop-empty__text">A manager can trust it from the Shop menu after signing in with a password.</p>
        </section>

        <div class="shop-inline">
            <a class="shop-btn shop-btn--primary" href="{{ route('login', ['redirect' => route('shop.home', absolute: false)]) }}"><x-shop.icon name="login" />Sign in with password</a>
        </div>
    </main>
</x-shop-layout>
