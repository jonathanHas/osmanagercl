{{--
    One card per requested line. Expects $item, $request, $canManage and the
    $statusTone / $actionLabels maps and $qty formatter from the board.

    Guests never see a phone number or an edit link.
--}}
@php
    $who = $request->customer_name.' · '.$qty($item->quantity);
    if ($canManage && $request->customer_phone) {
        $who .= ' · '.$request->customer_phone;
    }
@endphp
<article class="shop-request">
    <div class="shop-request__head">
        <div class="shop-stack shop-stack--tight">
            <span class="shop-request__item">{{ $item->label() }}</span>
            <span class="shop-request__who">{{ $who }}</span>
            @if ($canManage && ($item->notes || $request->notes))
                <span class="shop-meta">{{ $item->notes ?: $request->notes }}</span>
            @endif
        </div>

        @if ($canManage)
            <a class="shop-iconbtn shop-iconbtn--ghost" href="{{ route('customer-requests.edit', $request) }}" aria-label="Edit">
                <x-shop.icon name="pencil" />
            </a>
        @endif
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
            <span class="shop-pill {{ $statusTone[$item->status] ?? 'shop-pill--muted' }}">{{ $item->statusLabel() }}</span>
        @endif
    </div>

    @if ($canManage && $item->nextStatuses())
        <div class="shop-inline">
            @foreach ($item->nextStatuses() as $next)
                <form method="POST" action="{{ route('customer-requests.items.status', $item) }}">
                    @csrf
                    @method('PATCH')
                    <input type="hidden" name="status" value="{{ $next }}">
                    <button class="shop-btn {{ $next === \App\Models\CustomerRequestItem::STATUS_COLLECTED ? 'shop-btn--primary' : 'shop-btn--secondary' }}" type="submit">
                        {{ $actionLabels[$next] ?? \App\Models\CustomerRequestItem::labelFor($next) }}
                    </button>
                </form>
            @endforeach
        </div>
    @endif
</article>
