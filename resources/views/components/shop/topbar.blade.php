@props([
    'title' => 'Shop',
    'back' => null,
    'guestSafe' => false,
])
@php
    $user = auth()->user();
    $words = $user ? preg_split('/\s+/', trim($user->name), -1, PREG_SPLIT_NO_EMPTY) : [];
    $initials = strtoupper(implode('', array_map(fn ($w) => mb_substr($w, 0, 1), array_slice($words, 0, 2))));
    $firstName = $words[0] ?? '';
@endphp
<header class="shop-topbar">
    @if ($back === null)
        <div class="shop-topbar__brand"><span class="shop-topbar__mark"><x-shop.icon name="leaf" /></span><span>Shop</span></div>
    @else
        <a class="shop-iconbtn" href="{{ $back }}" aria-label="Back"><x-shop.icon name="back" /></a>
        <h1 class="shop-topbar__title">{{ $title }}</h1>
    @endif

    @auth
        <details class="shop-usermenu">
            <summary class="shop-chip"><span class="shop-avatar">{{ $initials }}</span><span class="shop-chip__name">{{ $firstName }}</span><x-shop.icon name="chevron-down" size="sm" class="shop-chip__caret" /></summary>
            <div class="shop-menu" role="menu">
                <div class="shop-menu__head"><span class="shop-avatar">{{ $initials }}</span><div><div class="shop-menu__name">{{ $user->name }}</div><div class="shop-meta">{{ $user->role?->display_name }}</div></div></div>
                @unless ($user->isBarista())
                    <form method="POST" action="{{ route('ui-mode.set', 'office') }}">
                        @csrf
                        <button type="submit" class="shop-menu__item" role="menuitem"><x-shop.icon name="office" />Office</button>
                    </form>
                @endunless
                <div class="shop-menu__sep"></div>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="shop-menu__item shop-menu__item--danger" role="menuitem"><x-shop.icon name="logout" />Log out</button>
                </form>
            </div>
        </details>
    @else
        @if ($guestSafe)
            <a class="shop-btn shop-btn--secondary" href="{{ route('login', ['redirect' => request()->getRequestUri()]) }}"><x-shop.icon name="login" />Staff sign in</a>
        @endif
    @endauth
</header>
