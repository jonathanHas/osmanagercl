{{-- Availability (screen 12) and Labels (screen 15) are later cycles; listing
     them now would be four tabs, two of which go nowhere.
     Pricing (Dynamis invoices) is for managers and admins only, like the office
     page, so the tab is shown only to them.
     The @if sits tight against the quote so the rendered tag has no stray
     whitespace — a test asserting on the attribute pair would otherwise miss. --}}
<nav class="shop-seg" aria-label="Fruit and veg">
    @if (auth()->user()?->hasPermission('fruit_veg.operate'))
        <a class="shop-seg__opt" href="{{ route('shop.fv.waste') }}"@if ($active === 'waste') aria-current="page"@endif>Waste log</a>
        <a class="shop-seg__opt" href="{{ route('shop.fv.harvest') }}"@if ($active === 'harvest') aria-current="page"@endif>Harvest</a>
    @endif
    @if (auth()->user()?->hasAnyRole(['manager', 'admin']))
        <a class="shop-seg__opt" href="{{ route('shop.fv.pricing') }}"@if ($active === 'pricing') aria-current="page"@endif>Pricing</a>
    @endif
</nav>
