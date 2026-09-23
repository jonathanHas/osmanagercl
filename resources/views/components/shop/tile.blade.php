@props([
    'href',
    'label',
    'hint' => null,
    'icon',
    'badge' => null,
    'tone' => null,
])
<a class="shop-tile" href="{{ $href }}">
    <span class="shop-tile__icon{{ $tone === 'sage' ? ' shop-tile__icon--sage' : '' }}"><x-shop.icon :name="$icon" size="lg" /></span>
    @if ($badge > 0)
        <span class="shop-tile__badge" aria-label="{{ $badge }} waiting">{{ $badge }}</span>
    @endif
    <span class="shop-tile__text">
        <span class="shop-tile__label">{{ $label }}</span>
        @if ($hint)
            <span class="shop-tile__hint">{{ $hint }}</span>
        @endif
    </span>
</a>
