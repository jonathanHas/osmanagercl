@php
    use App\Models\CustomerRequestItem as Line;

    $statusTone = [
        Line::STATUS_ORDERED => 'shop-pill--sage',
        Line::STATUS_PUT_ASIDE => 'shop-pill--ok',
        Line::STATUS_COLLECTED => 'shop-pill--ok',
        Line::STATUS_NOT_AVAILABLE => 'shop-pill--bad',
        Line::STATUS_CANCELLED => 'shop-pill--muted',
    ];
    $qty = fn ($value) => rtrim(rtrim(number_format((float) $value, 2, '.', ''), '0'), '.');

    // Same computation the office page used: every line's log entries, in time order.
    $logs = $customerRequest->items
        ->flatMap(fn ($item) => $item->statusLogs->map(fn ($log) => ['item' => $item, 'log' => $log]))
        ->sortBy(fn ($row) => [$row['log']->created_at->timestamp, $row['log']->id])
        ->values();
@endphp

<x-shop-layout title="Request" :back="route('customer-requests.index')">
    <main class="shop-page shop-page--narrow">
        @if (session('status'))
            <div class="shop-toasts" role="status">
                <div class="shop-toast shop-toast--ok">
                    <span class="shop-toast__icon"><x-shop.icon name="check" size="sm" /></span>
                    <span class="shop-toast__text">{{ session('status') }}</span>
                </div>
            </div>
        @endif

        @if ($errors->has('status'))
            <div class="shop-toasts" role="status">
                <div class="shop-toast shop-toast--bad">
                    <span class="shop-toast__icon"><x-shop.icon name="alert" size="sm" /></span>
                    <span class="shop-toast__text">{{ $errors->first('status') }}</span>
                </div>
            </div>
        @endif

        <section class="shop-card">
            <h2 class="shop-title">{{ $customerRequest->customer_name }}</h2>

            <p class="shop-meta">
                Taken by {{ optional($customerRequest->creator)->name ?? 'unknown' }}
                · {{ $customerRequest->created_at->format('D j M H:i') }}
                @if ($customerRequest->closed_at)
                    · Closed {{ $customerRequest->closed_at->format('D j M H:i') }}@if ($customerRequest->closer) by {{ $customerRequest->closer->name }}@endif
                @endif
            </p>

            <div class="shop-inline">
                @if ($customerRequest->wanted_on)
                    @if ($customerRequest->isOverdue())
                        <span class="shop-pill shop-pill--bad">Overdue {{ $customerRequest->wanted_on->diffInDays(today()) }}d</span>
                    @elseif ($customerRequest->isDueToday())
                        <span class="shop-pill shop-pill--warn">Due today</span>
                    @else
                        <span class="shop-pill shop-pill--muted">{{ $customerRequest->wanted_on->format('D j M') }}</span>
                    @endif
                @endif

                <span class="shop-pill {{ $customerRequest->isOpen() ? 'shop-pill--ok' : 'shop-pill--muted' }}">{{ $customerRequest->isOpen() ? 'Open' : 'Closed' }}</span>
            </div>

            @if ($customerRequest->customer_phone)
                <span class="shop-inline"><x-shop.icon name="phone" size="sm" />{{ $customerRequest->customer_phone }}</span>
            @endif

            @if ($customerRequest->notes)
                <p class="shop-meta">{{ $customerRequest->notes }}</p>
            @endif
        </section>

        <section class="shop-stack shop-stack--tight">
            <h2 class="shop-subtitle">Items</h2>
            <div class="shop-list">
                @foreach ($customerRequest->items as $item)
                    <div class="shop-row">
                        <div class="shop-row__main">
                            <span class="shop-row__title">{{ $item->label() }}</span>
                            <span class="shop-row__meta">
                                {{ $qty($item->quantity) }}
                                @if ($item->product_code)
                                    · <span class="shop-code">{{ $item->product_code }}</span>
                                @endif
                                · {{ $item->isLinkedToProduct() ? 'Pre-order' : 'Sourcing' }}
                                @if ($item->notes)
                                    · {{ $item->notes }}
                                @endif
                            </span>
                        </div>
                        <div class="shop-row__aside">
                            <span class="shop-pill {{ $statusTone[$item->status] ?? 'shop-pill--muted' }}">{{ $item->statusLabel() }}</span>
                        </div>
                    </div>
                @endforeach
            </div>
        </section>

        <section class="shop-stack shop-stack--tight">
            <h2 class="shop-subtitle">Status history</h2>

            @forelse ($logs as $row)
                @if ($loop->first)
                    <div class="shop-list">
                @endif
                <div class="shop-row">
                    <div class="shop-row__main">
                        <span class="shop-row__title">
                            @if ($row['log']->from_status)
                                {{ Line::labelFor($row['log']->from_status) }} &rarr;
                            @endif
                            {{ Line::labelFor($row['log']->to_status) }}
                        </span>
                        <span class="shop-row__meta">
                            {{ $row['log']->created_at->format('D j M H:i') }}
                            · {{ $row['item']->label() }}
                            · {{ optional($row['log']->user)->name ?? 'unknown' }}
                        </span>
                        @if ($row['log']->note)
                            <span class="shop-meta">{{ $row['log']->note }}</span>
                        @endif
                    </div>
                </div>
                @if ($loop->last)
                    </div>
                @endif
            @empty
                <div class="shop-empty">
                    <p class="shop-empty__text">No status changes recorded.</p>
                </div>
            @endforelse
        </section>

        <div class="shop-actions">
            <a class="shop-btn shop-btn--secondary shop-btn--lg" href="{{ route('customer-requests.index') }}">Back to board</a>
            <a class="shop-btn shop-btn--primary shop-btn--lg" href="{{ route('customer-requests.edit', $customerRequest) }}">
                <x-shop.icon name="pencil" />
                Edit
            </a>
        </div>
    </main>
</x-shop-layout>
