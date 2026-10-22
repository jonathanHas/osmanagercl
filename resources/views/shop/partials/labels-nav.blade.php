{{-- Shelf labels (A4 queue) and Zebra labels (counter printer) are two halves of
     "Print labels"; the tabs keep both inside Shop mode.
     The @if sits tight against the quote so the rendered tag has no stray
     whitespace — a test asserting on the attribute pair would otherwise miss. --}}
<nav class="shop-seg shop-seg--block" aria-label="Label type">
    <a class="shop-seg__opt" href="{{ route('shop.labels') }}"@if ($active === 'shelf') aria-current="page"@endif><x-shop.icon name="tag" size="sm" />Shelf labels</a>
    <a class="shop-seg__opt" href="{{ route('shop.zebra') }}"@if ($active === 'zebra') aria-current="page"@endif><x-shop.icon name="barcode" size="sm" />Zebra labels</a>
</nav>
