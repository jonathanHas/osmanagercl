{{-- Availability (screen 12) and Labels (screen 15) are later cycles; listing
     them now would be four tabs, two of which go nowhere.
     The @if sits tight against the quote so the rendered tag has no stray
     whitespace — a test asserting on the attribute pair would otherwise miss. --}}
<nav class="shop-seg" aria-label="Fruit and veg">
    <a class="shop-seg__opt" href="{{ route('shop.fv.waste') }}"@if ($active === 'waste') aria-current="page"@endif>Waste log</a>
    <a class="shop-seg__opt" href="{{ route('shop.fv.harvest') }}"@if ($active === 'harvest') aria-current="page"@endif>Harvest</a>
</nav>
