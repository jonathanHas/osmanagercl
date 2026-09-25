@props([
    'name',
    'size' => null,
    'class' => '',
])
{{-- Symbol ids are listed in docs/design/shop-mode/README.md --}}
<svg class="shop-ico{{ $size ? ' shop-ico--'.$size : '' }} {{ $class }}" aria-hidden="true" {{ $attributes }}><use href="{{ asset('images/shop-icons.svg') }}#{{ $name }}"></use></svg>
