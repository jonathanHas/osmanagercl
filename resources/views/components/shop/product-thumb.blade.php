@props(['expr' => 'p', 'placeholder' => 'package'])
{{--
    A product thumbnail with its placeholder, for any Alpine scope composed with
    `productImages()` (see `mix()`). `expr` names the product in that scope; extra
    attributes go on the `<img>`, so a caller can add hover handlers.

    The image may 404 (the search API's CDN fallback is a template URL), so the
    error event hides it and the lead circle takes over.
--}}
{{-- Optional chaining matters: x-show hides the element but :src and :alt are
     still evaluated, and the expression can be null before anything is picked. --}}
<img class="shop-thumb" x-show="hasImage({{ $expr }})" :src="{{ $expr }}?.image_url" :alt="{{ $expr }}?.name" loading="lazy" decoding="async" x-on:error="imageFailed({{ $expr }})" {{ $attributes }}>
<span class="shop-row__lead" x-show="! hasImage({{ $expr }})"><x-shop.icon :name="$placeholder" /></span>
