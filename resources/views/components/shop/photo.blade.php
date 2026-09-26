@props(['url' => null, 'alt' => '', 'placeholder' => 'package'])
{{--
    A product picture for a server-rendered row, with its placeholder.

    The Alpine sibling `x-shop.product-thumb` needs a product object in scope;
    these pages have only a URL from ProductSearchService::imageUrlsByCode(), so
    this component carries its own tiny scope for the error fallback.

    The URL may not load: `products.image` needs `auth` + `products.view`, so a
    guest on the public board gets a login redirect and the image errors. The
    supplier CDN URLs work for everyone. Either way the placeholder takes over.
--}}
@if ($url)
    <span class="shop-photo" x-data="{ ok: true }">
        <img class="shop-thumb" x-show="ok" src="{{ $url }}" alt="{{ $alt }}" loading="lazy" decoding="async" x-on:error="ok = false">
        <span class="shop-row__lead" x-show="! ok" x-cloak><x-shop.icon :name="$placeholder" /></span>
    </span>
@else
    <span class="shop-row__lead"><x-shop.icon :name="$placeholder" /></span>
@endif
