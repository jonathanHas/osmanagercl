{{--
    One card per requested line on the public guest board. Expects $item,
    $request and the $qty formatter.

    Guest-only since cycle 14: no phone number, no notes, no edit link and no
    actions — staff see the request rows instead.
--}}
@php
    $tone = [
        \App\Models\CustomerRequestItem::STATUS_ORDERED => 'shop-pill--sage',
        \App\Models\CustomerRequestItem::STATUS_PUT_ASIDE => 'shop-pill--ok',
        \App\Models\CustomerRequestItem::STATUS_COLLECTED => 'shop-pill--ok',
        \App\Models\CustomerRequestItem::STATUS_NOT_AVAILABLE => 'shop-pill--bad',
        \App\Models\CustomerRequestItem::STATUS_CANCELLED => 'shop-pill--muted',
    ];
@endphp
<article class="shop-request">
    <div class="shop-request__head">
        @if ($item->isLinkedToProduct())
            <x-shop.photo :url="$image ?? null" :alt="$item->label()" />
        @endif
        <div class="shop-stack shop-stack--tight">
            <span class="shop-request__item">{{ $item->label() }}</span>
            <span class="shop-request__who">{{ $request->customer_name }} &middot; {{ $qty($item->quantity) }}</span>
        </div>
    </div>

    <div class="shop-request__foot">
        @if ($request->wanted_on)
            @if ($request->isOverdue())
                <span class="shop-pill shop-pill--bad">Overdue {{ $request->wanted_on->diffInDays(today()) }}d</span>
            @elseif ($request->isDueToday())
                <span class="shop-pill shop-pill--warn">Due today</span>
            @else
                <span class="shop-pill shop-pill--muted">{{ $request->wanted_on->format('D j M') }}</span>
            @endif
        @endif

        <span class="shop-pill shop-pill--muted">{{ $item->isLinkedToProduct() ? 'Pre-order' : 'Sourcing' }}</span>

        @if ($item->status !== \App\Models\CustomerRequestItem::STATUS_PENDING)
            <span class="shop-pill {{ $tone[$item->status] ?? 'shop-pill--muted' }}">{{ $item->statusLabel() }}</span>
        @endif
    </div>
</article>
