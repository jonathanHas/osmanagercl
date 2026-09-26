@php($qtyOf = fn ($value) => rtrim(rtrim(number_format((float) $value, 2, '.', ''), '0'), '.'))

<x-shop-layout
    title="Customer requests"
    :back="auth()->check() ? route('shop.home') : null"
    :guest-safe="true"
    :guest-refresh="auth()->check() ? null : 300">

    <main class="{{ $canManage ? 'shop-page' : 'shop-page shop-page--wide' }}">
        @if (session('status'))
            <div class="shop-toasts" role="status">
                <div class="shop-toast shop-toast--ok">
                    <span class="shop-toast__icon"><x-shop.icon name="check" size="sm" /></span>
                    <span class="shop-toast__text">{{ session('status') }}</span>
                </div>
            </div>
        @endif

        @if (! $openNew && ($errors->has('status') || $errors->has('items')))
            <div class="shop-toasts" role="status">
                <div class="shop-toast shop-toast--bad">
                    <span class="shop-toast__icon"><x-shop.icon name="alert" size="sm" /></span>
                    <span class="shop-toast__text">{{ $errors->first('status') ?: $errors->first('items') }}</span>
                </div>
            </div>
        @endif

        @if ($canManage)
            @include('shop.partials.requests-staff', [
                'rows' => $rows,
                'view' => $view,
                'openNew' => $openNew,
                'seedItems' => $seedItems,
                'searchUrl' => $searchUrl,
            ])
        @else
            <div class="shop-stack">
                <p class="shop-meta">Pre-orders and items we are sourcing for you. Ask at the counter if yours is ready.</p>

                @foreach ([['due', 'Due today', 'Nothing due today'], ['open', 'Coming up', 'No open requests']] as [$key, $heading, $emptyText])
                    <section class="shop-stack shop-stack--tight">
                        <h2 class="shop-group-title">{{ $heading }} <small>{{ count($rows[$key]) }}</small></h2>

                        @if (count($rows[$key]))
                            <div class="shop-board">
                                @foreach ($rows[$key] as $row)
                                    @include('shop.partials.request-card', [
                                        'item' => $row['item'],
                                        'request' => $row['request'],
                                        'qty' => $qtyOf,
                                        'image' => $row['image_url'] ?? null,
                                    ])
                                @endforeach
                            </div>
                        @else
                            <div class="shop-empty">
                                <p class="shop-empty__text">{{ $emptyText }}</p>
                            </div>
                        @endif
                    </section>
                @endforeach
            </div>
        @endif
    </main>
</x-shop-layout>
