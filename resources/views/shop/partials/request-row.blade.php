{{--
    One staff row per requested line: date block, item and customer, the
    three-step lifecycle strip, the primary next step and a more menu.

    Expects $item and $request. Every action is a plain form to an existing
    endpoint; there is no confirm dialog because every step can be undone.
--}}
@php
    use App\Models\CustomerRequestItem as Line;

    $status = $item->status;
    $held = $item->isHeldForCollection();
    $isStopped = in_array($status, [Line::STATUS_NOT_AVAILABLE, Line::STATUS_CANCELLED], true);
    $isFinished = $isStopped || in_array($status, [Line::STATUS_COLLECTED, Line::STATUS_DONE], true);

    // The lifecycle strip: Ordered → Put aside → Collected for a line held for
    // the customer, Ordered → Done for one we just get in. A held line that was
    // marked done anyway shows the short strip, fully lit.
    $steps = array_values($item->lifecycleSteps());
    $reached = match ($status) {
        Line::STATUS_ORDERED => 1,
        Line::STATUS_PUT_ASIDE => 2,
        Line::STATUS_COLLECTED => 3,
        Line::STATUS_DONE => count($steps),
        default => 0,
    };

    $overdue = $request->isOverdue();
    $dueToday = $request->isDueToday();

    // The design makes the next step prominent only when it is wanted now, or
    // when it is the last one.
    $urgent = $overdue || $dueToday;

    $primary = match (true) {
        $status === Line::STATUS_PENDING && ! $held,
        $status === Line::STATUS_ORDERED && ! $held => ['status' => Line::STATUS_DONE, 'label' => 'Done', 'tone' => 'shop-btn--primary'],
        $status === Line::STATUS_PENDING => ['status' => Line::STATUS_ORDERED, 'label' => 'Mark ordered', 'tone' => $urgent ? 'shop-btn--primary' : 'shop-btn--secondary'],
        $status === Line::STATUS_ORDERED => ['status' => Line::STATUS_PUT_ASIDE, 'label' => 'Put aside', 'tone' => $urgent ? 'shop-btn--primary' : 'shop-btn--secondary'],
        $status === Line::STATUS_PUT_ASIDE => ['status' => Line::STATUS_COLLECTED, 'label' => 'Collected', 'tone' => 'shop-btn--primary'],
        $isStopped => ['status' => Line::STATUS_PENDING, 'label' => 'Reopen', 'tone' => 'shop-btn--secondary'],
        default => null,
    };

    $undo = match ($status) {
        Line::STATUS_ORDERED => Line::STATUS_PENDING,
        Line::STATUS_PUT_ASIDE => Line::STATUS_ORDERED,
        Line::STATUS_COLLECTED => Line::STATUS_PUT_ASIDE,
        Line::STATUS_DONE => Line::STATUS_ORDERED,
        default => null,
    };

    $searchText = trim($request->customer_name.' '.$item->label());
@endphp

<article class="shop-req {{ $isFinished ? 'is-done' : '' }}"
         data-text="{{ $searchText }}"
         x-show="matches($el.dataset.text)"
         x-data="{ more: false }">

    @if (! $request->wanted_on)
        <div class="shop-req__date">
            <span>No</span>
            <strong>&ndash;</strong>
            <span>date</span>
        </div>
    @elseif ($overdue)
        <div class="shop-req__date is-late">
            <span>Late</span>
            <strong>{{ $request->wanted_on->format('j') }}</strong>
            <span>{{ $request->wanted_on->format('M') }}</span>
            <span class="shop-sr-only">Overdue {{ $request->wanted_on->diffInDays(today()) }}d</span>
        </div>
    @elseif ($dueToday)
        <div class="shop-req__date is-today">
            <span>Today</span>
            <strong>{{ $request->wanted_on->format('j') }}</strong>
            <span>{{ $request->wanted_on->format('M') }}</span>
        </div>
    @else
        <div class="shop-req__date">
            <span>{{ $request->wanted_on->format('D') }}</span>
            <strong>{{ $request->wanted_on->format('j') }}</strong>
            <span>{{ $request->wanted_on->format('M') }}</span>
        </div>
    @endif

    <div class="shop-req__main">
        {{-- Pre-order lines are a stocked product, so show the picture staff saw
             when they picked it. Sourcing lines are free text: no photo, and no
             placeholder either, so their layout is unchanged. --}}
        @if ($item->isLinkedToProduct())
            <span class="shop-inline">
                <x-shop.photo :url="$image ?? null" :alt="$item->label()" />
                <h3 class="shop-req__title">{{ $item->label() }}</h3>
            </span>
        @else
            <h3 class="shop-req__title">{{ $item->label() }}</h3>
        @endif
        <div class="shop-req__meta">
            <span>{{ $request->customer_name }} &middot; {{ $item->quantityLabel() }}</span>
            @if ($request->customer_phone)
                <span class="shop-inline"><x-shop.icon name="phone" size="sm" />{{ $request->customer_phone }}</span>
            @endif
            <span class="shop-pill shop-pill--muted">{{ $item->isLinkedToProduct() ? 'Pre-order' : 'Sourcing' }}</span>
            @unless ($held)
                <span class="shop-pill shop-pill--muted">{{ $item->handlingLabel() }}</span>
            @endunless
            @if ($status === Line::STATUS_NOT_AVAILABLE)
                <span class="shop-pill shop-pill--bad">Not available</span>
            @elseif ($status === Line::STATUS_CANCELLED)
                <span class="shop-pill shop-pill--muted">Cancelled</span>
            @endif
        </div>
        @if ($item->notes ?: $request->notes)
            <span class="shop-meta">{{ $item->notes ?: $request->notes }}</span>
        @endif
        {{-- When it was taken, and by whom: the date block is the due date, and
             the two together say how long the customer has been waiting. --}}
        <span class="shop-meta shop-req__taken">
            Taken {{ $request->created_at->format('D j M') }}@if ($request->creator) by {{ $request->creator->name }}@endif
        </span>
    </div>

    <ol class="shop-steps {{ count($steps) === 2 ? 'shop-steps--2' : '' }} {{ $isStopped ? 'is-stopped' : '' }}" aria-label="Status">
        @foreach ($steps as $i => $label)
            <li class="shop-step {{ $reached > $i ? 'is-done' : '' }} {{ $reached === $i + 1 ? 'is-current' : '' }}">{{ $label }}</li>
        @endforeach
    </ol>

    <div class="shop-req__actions">
        @if ($primary)
            <form method="POST" action="{{ route('customer-requests.items.status', $item) }}">
                @csrf
                @method('PATCH')
                <input type="hidden" name="status" value="{{ $primary['status'] }}">
                <button class="shop-btn {{ $primary['tone'] }}" type="submit">{{ $primary['label'] }}</button>
            </form>
        @endif

        <button class="shop-iconbtn" type="button" aria-label="More actions"
                :aria-expanded="more" :aria-pressed="more" @click="more = ! more">
            <x-shop.icon name="more" />
        </button>
    </div>

    {{-- In flow, not floating: the card grows downward, so no control is ever
         underneath another row's. --}}
    <div class="shop-menu shop-menu--static shop-req__more" role="group" aria-label="More actions" x-show="more" x-cloak>

            <a class="shop-menu__item" href="{{ route('customer-requests.show', $request) }}">
                <x-shop.icon name="list-checks" />Details
            </a>

            <a class="shop-menu__item" href="{{ route('customer-requests.edit', $request) }}">
                <x-shop.icon name="pencil" />Edit
            </a>

            @if ($status === Line::STATUS_PENDING && $held)
                <form method="POST" action="{{ route('customer-requests.items.status', $item) }}">
                    @csrf
                    @method('PATCH')
                    <input type="hidden" name="status" value="{{ Line::STATUS_PUT_ASIDE }}">
                    <button class="shop-menu__item" type="submit"><x-shop.icon name="check" />Put aside now</button>
                </form>
            @endif

            {{-- The step the primary button skips: Ordered for a just-get-it-in
                 line, Done for a held one that turns out to need no holding. --}}
            @if ($status === Line::STATUS_PENDING && ! $held)
                <form method="POST" action="{{ route('customer-requests.items.status', $item) }}">
                    @csrf
                    @method('PATCH')
                    <input type="hidden" name="status" value="{{ Line::STATUS_ORDERED }}">
                    <button class="shop-menu__item" type="submit"><x-shop.icon name="package" />Mark ordered</button>
                </form>
            @endif

            @if (in_array($status, [Line::STATUS_PENDING, Line::STATUS_ORDERED], true) && $held)
                <form method="POST" action="{{ route('customer-requests.items.status', $item) }}">
                    @csrf
                    @method('PATCH')
                    <input type="hidden" name="status" value="{{ Line::STATUS_DONE }}">
                    <button class="shop-menu__item" type="submit"><x-shop.icon name="check" />Done, nothing to put aside</button>
                </form>
            @endif

            @if ($undo)
                <form method="POST" action="{{ route('customer-requests.items.status', $item) }}">
                    @csrf
                    @method('PATCH')
                    <input type="hidden" name="status" value="{{ $undo }}">
                    <button class="shop-menu__item" type="submit"><x-shop.icon name="history" />Undo last step</button>
                </form>
            @endif

            @if (in_array($status, [Line::STATUS_PENDING, Line::STATUS_ORDERED], true))
                <form method="POST" action="{{ route('customer-requests.items.status', $item) }}">
                    @csrf
                    @method('PATCH')
                    <input type="hidden" name="status" value="{{ Line::STATUS_NOT_AVAILABLE }}">
                    <button class="shop-menu__item" type="submit"><x-shop.icon name="alert" />Not available</button>
                </form>
            @endif

            @if ($item->isOpen())
                <div class="shop-menu__sep"></div>
                <form method="POST" action="{{ route('customer-requests.cancel', $request) }}">
                    @csrf
                    <button class="shop-menu__item shop-menu__item--danger" type="submit"><x-shop.icon name="x" />Cancel request</button>
                </form>
            @endif
    </div>
</article>
