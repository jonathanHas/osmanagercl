{{--
    Staff board v2: segmented view filter, live search, request rows and the
    "New request" sheet.

    Two small Alpine scopes, deliberately separate: the list owns the search, the
    bottom bar and sheet own the open flag. Keeping the sheet's scope as a literal
    `{ open: … }` is also what keeps CustomerRequestTest's three assertions on
    that markup green.
--}}
@php
    $views = [
        'open' => ['label' => 'Open', 'count' => $rows['counts']['open'], 'query' => []],
        'aside' => ['label' => 'Put aside', 'count' => $rows['counts']['aside'], 'query' => ['show' => 'aside']],
        'done' => ['label' => 'Done', 'count' => null, 'query' => ['show' => 'done']],
    ];

    $groups = $view === 'done'
        ? [['done', 'Done recently', 'Nothing finished in the last 30 days']]
        : [['due', 'Due today', null], ['open', 'Coming up', null]];

    $emptyText = match ($view) {
        'aside' => 'Nothing put aside',
        'done' => 'Nothing finished in the last 30 days',
        default => 'No open requests',
    };

    $hasRows = count($rows['due']) || count($rows['open']) || count($rows['done']);
@endphp

<div class="shop-stack" x-data="shopRequestsBoard()">
    <div class="shop-between">
        <div class="shop-seg" role="group" aria-label="View">
            @foreach ($views as $key => $meta)
                <a class="shop-seg__opt"
                   href="{{ route('customer-requests.index', $meta['query']) }}"
                   @if ($view === $key) aria-current="page" @endif>
                    {{ $meta['label'] }}@if ($meta['count'] !== null) {{ $meta['count'] }}@endif
                </a>
            @endforeach
        </div>

        <div class="shop-search shop-search--grow">
            <x-shop.icon name="search" />
            <input class="shop-input" type="search" x-model="q"
                   placeholder="Search customer or item" autocomplete="off" aria-label="Search customer or item">
        </div>
    </div>

    @if ($hasRows)
        @foreach ($groups as [$key, $heading, $groupEmpty])
            @if (count($rows[$key]))
                <section class="shop-stack shop-stack--tight" x-show="groupMatches($el)">
                    <h2 class="shop-group-title">{{ $heading }} <small>{{ count($rows[$key]) }}</small></h2>
                    <div class="shop-reqs">
                        @foreach ($rows[$key] as $row)
                            @include('shop.partials.request-row', ['item' => $row['item'], 'request' => $row['request'], 'image' => $row['image_url'] ?? null])
                        @endforeach
                    </div>
                </section>
            @endif
        @endforeach

        {{-- Search hides rows and their groups; say so rather than showing a blank board. --}}
        <div class="shop-empty" x-show="q.trim() !== '' && ! anyMatch()" x-cloak>
            <div class="shop-empty__icon"><x-shop.icon name="search" size="xl" /></div>
            <p class="shop-empty__title">No matches</p>
            <p class="shop-empty__text">Nothing matches your search.</p>
        </div>
    @else
        <div class="shop-empty">
            <div class="shop-empty__icon"><x-shop.icon name="inbox" size="xl" /></div>
            <p class="shop-empty__title">Nothing here</p>
            <p class="shop-empty__text">{{ $emptyText }}</p>
        </div>
    @endif
</div>

<div x-data="{ open: @js($openNew) }" x-on:keydown.escape.window="open = false">
    <div class="shop-actions">
        <button class="shop-btn shop-btn--primary shop-btn--lg" type="button" @click="open = true">
            <x-shop.icon name="plus" />
            New request
        </button>
    </div>

    <div class="shop-sheet-backdrop" x-show="open" x-cloak @click.self="open = false">
        <section class="shop-sheet" role="dialog" aria-modal="true" aria-label="New request">
            <div class="shop-sheet__head">
                <h2 class="shop-subtitle">New request</h2>
                <button class="shop-iconbtn shop-iconbtn--ghost" type="button" aria-label="Close" @click="open = false">
                    <x-shop.icon name="x" />
                </button>
            </div>

            <div class="shop-sheet__body">
                @include('shop.partials.request-form', [
                    'searchUrl' => $searchUrl,
                    'seedItems' => $seedItems,
                ])
            </div>

            <div class="shop-sheet__foot">
                <button class="shop-btn shop-btn--secondary shop-btn--lg" type="button" @click="open = false">Cancel</button>
                <button class="shop-btn shop-btn--primary shop-btn--lg" type="submit" form="new-request-form">
                    <x-shop.icon name="check" />
                    Add request
                </button>
            </div>
        </section>
    </div>
</div>
