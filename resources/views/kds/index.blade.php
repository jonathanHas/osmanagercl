<x-admin-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            Coffee KDS
        </h2>
    </x-slot>

    {{-- Geist + Geist Mono fonts (loaded once for this view) --}}
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Geist:wght@400;500;600;700&family=Geist+Mono:wght@500;600&display=swap" rel="stylesheet">

    {{-- /kds design styles (mobile-first, ported from Claude Design bundle Z5SX0Nv6IX4Uh6uEQjriLA) --}}
    <style>
        /* Mobile only: hide the admin layout's white top bar.
           The new KDS bar (beige) provides its own hamburger + title. */
        @media (max-width: 1023px) {
            header.bg-white.shadow-sm { display: none !important; }
            .kds { min-height: 100vh; }
        }

        .kds {
            --bg:        #fbf7f0;
            --bg-2:      #f3ede2;
            --panel:     #ffffff;
            --line:      #ece4d4;
            --line-2:    #ddd2bd;
            --ink:       #1c1714;
            --ink-2:     #4a423a;
            --muted:     #8a8073;
            --muted-2:   #b3a896;

            --accent:    #ba6531;
            --accent-fg: #ffffff;

            --fresh:     #2f7d4f;
            --warn:      #c87a1e;
            --late:      #c43c2e;
            --ready:     #1f6f47;

            --drink-tag: #5b3a26;
            --bakery-tag:#6b4516;

            --r-card:    18px;
            --r-pill:    999px;
            --pad-x:     14px;

            background: var(--bg);
            color: var(--ink);
            font-family: 'Geist', system-ui, -apple-system, sans-serif;
            font-feature-settings: "ss01", "cv11", "cv02";
            letter-spacing: -0.005em;
            min-height: calc(100vh - 64px);
            display: flex;
            flex-direction: column;
        }
        .kds * { box-sizing: border-box; }

        /* top bar */
        .kds__bar {
            background: var(--bg);
            border-bottom: 1px solid var(--line);
            position: sticky;
            top: 0;
            z-index: 5;
        }
        .kds__bar-top {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px var(--pad-x);
        }
        .kds__menu {
            width: 38px; height: 38px;
            border-radius: 10px;
            border: 1px solid var(--line);
            background: var(--panel);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 4px;
            cursor: pointer;
            padding: 0;
            flex-shrink: 0;
        }
        .kds__menu span {
            display: block;
            width: 16px; height: 2px;
            background: var(--ink-2);
            border-radius: 2px;
        }
        /* Hamburger only relevant on mobile — desktop shows the admin chrome above */
        @media (min-width: 1024px) {
            .kds__menu { display: none; }
        }
        .kds__brand {
            flex: 1;
            min-width: 0;
            display: flex;
            flex-direction: column;
            gap: 1px;
        }
        .kds__brand > * { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .kds__title {
            font-size: 16px;
            font-weight: 600;
            color: var(--ink);
            line-height: 1.1;
        }
        .kds__status {
            font-family: 'Geist Mono', ui-monospace, monospace;
            font-size: 11px;
            color: var(--muted);
            display: flex;
            align-items: center;
            gap: 6px;
            font-variant-numeric: tabular-nums;
        }
        .kds__live {
            width: 7px; height: 7px;
            border-radius: 50%;
            background: var(--fresh);
            box-shadow: 0 0 0 0 rgba(47, 125, 79, 0.5);
            animation: kdsPulse 2s infinite;
        }
        .kds__live--down {
            background: var(--late);
            animation: none;
        }
        @keyframes kdsPulse {
            0%   { box-shadow: 0 0 0 0 rgba(47,125,79,0.45); }
            70%  { box-shadow: 0 0 0 8px rgba(47,125,79,0); }
            100% { box-shadow: 0 0 0 0 rgba(47,125,79,0); }
        }

        /* chip */
        .kds-chip {
            display: inline-flex;
            flex-direction: column;
            align-items: center;
            gap: 0;
            padding: 4px 10px;
            border-radius: 10px;
            background: var(--panel);
            border: 1px solid var(--line);
            min-width: 48px;
            cursor: pointer;
            font: inherit;
            color: var(--ink);
            transition: background 0.15s ease, border-color 0.15s ease;
        }
        .kds-chip:hover { background: var(--bg-2); }
        .kds-chip:active { transform: scale(0.97); }
        .kds-chip--active {
            background: var(--ink);
            color: var(--bg);
            border-color: var(--ink);
        }
        .kds-chip__num {
            font-family: 'Geist Mono', ui-monospace, monospace;
            font-size: 16px;
            font-weight: 600;
            line-height: 1.05;
            font-variant-numeric: tabular-nums;
        }
        .kds-chip__lbl {
            font-size: 9px;
            font-weight: 600;
            letter-spacing: 0.1em;
            text-transform: uppercase;
            color: var(--muted);
            line-height: 1.1;
        }
        .kds-chip--active .kds-chip__lbl { color: var(--bg); opacity: 0.7; }

        /* clear dropdown */
        .kds__clear-wrap { position: relative; }
        .kds__clear-menu {
            position: absolute;
            right: 0;
            top: calc(100% + 4px);
            background: var(--panel);
            border: 1px solid var(--line);
            border-radius: 10px;
            min-width: 200px;
            padding: 4px;
            box-shadow: 0 8px 24px rgba(0,0,0,0.08);
            z-index: 10;
        }
        .kds__clear-menu[hidden] { display: none; }
        .kds__clear-item {
            display: block;
            width: 100%;
            text-align: left;
            background: transparent;
            border: 0;
            padding: 8px 10px;
            border-radius: 6px;
            font: inherit;
            font-size: 13px;
            color: var(--ink);
            cursor: pointer;
        }
        .kds__clear-item:hover { background: var(--bg-2); }
        .kds__clear-item--danger { color: var(--late); }

        /* POS-disconnected strip */
        .kds__pos-down {
            background: #fee2e2;
            color: #7f1d1d;
            font-size: 12px;
            padding: 8px var(--pad-x);
            border-bottom: 1px solid #fecaca;
        }

        /* feed */
        .kds__feed {
            flex: 1;
            padding: 12px var(--pad-x) 40px;
            display: flex;
            flex-direction: column;
            gap: 12px;
            background: var(--bg);
        }
        .order-wrap {
            transition: opacity 0.3s ease, transform 0.3s ease, max-height 0.3s ease, margin 0.3s ease;
        }
        .order-wrap--fresh-entry {
            animation: kdsIn 0.28s cubic-bezier(0.2, 0.8, 0.2, 1);
        }
        .order-wrap--leaving {
            opacity: 0;
            transform: translateX(110%) rotate(2deg);
            max-height: 0;
            margin-bottom: -12px;
            pointer-events: none;
        }
        @keyframes kdsIn {
            from { opacity: 0; transform: translateY(6px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        /* card */
        .order {
            background: var(--panel);
            border: 1px solid var(--line);
            border-radius: var(--r-card);
            overflow: hidden;
            position: relative;
            display: flex;
            flex-direction: column;
        }
        .order::before {
            content: "";
            position: absolute;
            left: 0; top: 0; bottom: 0;
            width: 4px;
            background: var(--fresh);
            transition: background 0.4s ease;
        }
        .order--warn::before { background: var(--warn); }
        .order--late::before {
            background: var(--late);
            animation: kdsRail 1.6s ease-in-out infinite;
        }
        @keyframes kdsRail {
            0%, 100% { opacity: 1; }
            50%      { opacity: 0.4; }
        }

        .order__head {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 12px;
            padding: 12px 14px 8px 18px;
        }
        .order__id-block { flex: 1; min-width: 0; }
        .order__id {
            font-family: 'Geist Mono', ui-monospace, monospace;
            font-size: 22px;
            font-weight: 600;
            color: var(--ink);
            font-variant-numeric: tabular-nums;
            line-height: 1;
        }
        .order__meta {
            margin-top: 4px;
            font-size: 11px;
            color: var(--muted);
            display: flex;
            align-items: center;
            gap: 5px;
            white-space: nowrap;
        }
        .order__channel {
            font-weight: 500;
            color: var(--ink-2);
            text-transform: uppercase;
            letter-spacing: 0.06em;
            font-size: 10px;
        }
        .order__placed {
            font-family: 'Geist Mono', ui-monospace, monospace;
            font-variant-numeric: tabular-nums;
        }
        .order__dot { opacity: 0.4; }

        .order__timer {
            text-align: right;
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            gap: 2px;
            flex-shrink: 0;
            white-space: nowrap;
        }
        .order__time {
            font-family: 'Geist Mono', ui-monospace, monospace;
            font-size: 26px;
            font-weight: 600;
            font-variant-numeric: tabular-nums;
            line-height: 1;
            letter-spacing: -0.02em;
            color: var(--fresh);
        }
        .order--warn .order__time { color: var(--warn); }
        .order--late .order__time { color: var(--late); }
        .order__time-label {
            font-size: 9px;
            letter-spacing: 0.12em;
            font-weight: 600;
            color: var(--fresh);
            text-transform: uppercase;
        }
        .order--warn .order__time-label { color: var(--warn); }
        .order--late .order__time-label { color: var(--late); }

        .order__customer {
            padding: 0 14px 4px 18px;
            display: flex;
            align-items: baseline;
            gap: 6px;
            font-size: 13px;
        }
        .order__customer-label {
            color: var(--muted);
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            font-weight: 500;
        }
        .order__customer-name {
            font-weight: 600;
            color: var(--ink);
            font-size: 14px;
        }

        .order__groups {
            padding: 4px 14px 12px 18px;
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        .group {
            border-top: 1px dashed var(--line);
            padding-top: 8px;
        }
        .group:first-child { border-top: none; padding-top: 4px; }
        .group__label {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 4px;
        }
        .group__dot {
            width: 6px; height: 6px;
            border-radius: 50%;
            background: var(--drink-tag);
        }
        .group--bakery .group__dot { background: var(--bakery-tag); border-radius: 2px; }
        .group__name {
            font-size: 10px;
            font-weight: 600;
            letter-spacing: 0.12em;
            text-transform: uppercase;
            color: var(--ink-2);
        }
        .group--drink .group__name  { color: var(--drink-tag); }
        .group--bakery .group__name { color: var(--bakery-tag); }
        .group__count {
            font-family: 'Geist Mono', ui-monospace, monospace;
            font-size: 10px;
            color: var(--muted);
            margin-left: auto;
            font-variant-numeric: tabular-nums;
        }
        .group__list {
            list-style: none;
            margin: 0;
            padding: 0;
            display: flex;
            flex-direction: column;
            gap: 2px;
        }

        .item {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            padding: 8px 4px 8px 0;
            cursor: pointer;
            border-radius: 8px;
            user-select: none;
            -webkit-tap-highlight-color: transparent;
            transition: background 0.15s ease;
        }
        .item:active { background: var(--bg-2); }
        .item__check {
            width: 24px; height: 24px;
            border-radius: 7px;
            border: 1.5px solid var(--line-2);
            background: transparent;
            flex-shrink: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            color: transparent;
            font-size: 14px;
            font-weight: 700;
            transition: all 0.15s ease;
            margin-top: 1px;
        }
        .item--done .item__check {
            background: var(--ready);
            border-color: var(--ready);
            color: #fff;
        }
        .item__body { flex: 1; min-width: 0; }
        .item__main {
            display: flex;
            align-items: baseline;
            gap: 8px;
            font-size: 16px;
            line-height: 1.2;
        }
        .item__qty {
            font-family: 'Geist Mono', ui-monospace, monospace;
            font-weight: 600;
            color: var(--ink);
            font-variant-numeric: tabular-nums;
            min-width: 26px;
        }
        .item__name {
            font-weight: 600;
            color: var(--ink);
            letter-spacing: -0.01em;
        }
        .item--done .item__name,
        .item--done .item__qty {
            text-decoration: line-through;
            text-decoration-color: var(--muted-2);
            text-decoration-thickness: 1.5px;
            color: var(--muted);
        }
        .item__mods {
            margin-top: 4px;
            display: flex;
            flex-wrap: wrap;
            gap: 4px 6px;
        }
        .item__mod {
            font-size: 11px;
            padding: 2px 7px;
            border-radius: 5px;
            background: var(--bg-2);
            color: var(--ink-2);
            font-weight: 500;
            border: 1px solid var(--line);
            white-space: nowrap;
        }
        .item--done .item__mod {
            opacity: 0.5;
            text-decoration: line-through;
        }
        .item__notes {
            margin-top: 4px;
            font-size: 12px;
            color: var(--warn);
            font-weight: 500;
        }

        /* cta */
        .order__cta {
            margin: 0;
            border: none;
            border-top: 1px solid var(--line);
            background: var(--ready);
            color: #ffffff;
            padding: 14px;
            font: inherit;
            font-weight: 600;
            font-size: 15px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            border-top-color: var(--ready);
        }
        .order__cta:active { transform: scale(0.995); }
        .order__cta-progress {
            font-family: 'Geist Mono', ui-monospace, monospace;
            font-variant-numeric: tabular-nums;
            font-size: 12px;
            background: rgba(255,255,255,0.18);
            color: #ffffff;
            border: 1px solid rgba(255,255,255,0.25);
            padding: 2px 7px;
            border-radius: 6px;
            margin-left: 4px;
        }
        .order__cta-check { font-size: 18px; line-height: 1; }

        /* empty */
        .kds__empty {
            margin: 40px auto;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 6px;
            color: var(--muted);
            text-align: center;
        }
        .kds__empty-mark {
            width: 56px; height: 56px;
            border-radius: 50%;
            background: var(--bg-2);
            border: 1px solid var(--line);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--ready);
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 8px;
        }
        .kds__empty-title { font-size: 16px; font-weight: 600; color: var(--ink); }
        .kds__empty-sub { font-size: 13px; }

        /* done panel */
        .kds__done-panel {
            background: var(--bg-2);
            border-top: 1px solid var(--line);
            padding: 12px var(--pad-x);
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        .kds__done-panel[hidden] { display: none; }
        .kds__done-title {
            font-size: 10px;
            font-weight: 600;
            letter-spacing: 0.12em;
            text-transform: uppercase;
            color: var(--muted);
        }
        .kds__done-row {
            background: var(--panel);
            border: 1px solid var(--line);
            border-radius: 10px;
            padding: 10px 12px;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 13px;
        }
        .kds__done-id {
            font-family: 'Geist Mono', ui-monospace, monospace;
            font-weight: 600;
            color: var(--ink);
            font-size: 14px;
            flex-shrink: 0;
        }
        .kds__done-items {
            flex: 1;
            color: var(--ink-2);
            font-size: 12px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .kds__done-time {
            font-family: 'Geist Mono', ui-monospace, monospace;
            color: var(--muted);
            font-size: 12px;
        }
        .kds__done-restore {
            background: transparent;
            border: 1px solid var(--line-2);
            color: var(--ink-2);
            border-radius: 6px;
            padding: 4px 10px;
            font: inherit;
            font-size: 12px;
            cursor: pointer;
        }
        .kds__done-restore:hover { background: var(--bg-2); }
        .kds__done-empty {
            color: var(--muted);
            font-size: 13px;
            text-align: center;
            padding: 16px;
        }
    </style>

    <div class="kds" id="kds-root">
        <header class="kds__bar">
            <div class="kds__bar-top">
                <button type="button" class="kds__menu" @click="sidebarOpen = true" aria-label="Open menu">
                    <span></span><span></span><span></span>
                </button>
                <div class="kds__brand">
                    <div class="kds__title">Coffee KDS</div>
                    <div class="kds__status">
                        <span id="kds-live" class="kds__live"></span>
                        <span>Live · <span id="kds-clock">--:--:--</span></span>
                    </div>
                </div>

                <div class="kds__clear-wrap">
                    <button type="button" class="kds-chip" onclick="toggleClearMenu(event)" aria-haspopup="true">
                        <span class="kds-chip__num">⌫</span>
                        <span class="kds-chip__lbl">Clear</span>
                    </button>
                    <div id="clear-menu" class="kds__clear-menu" hidden>
                        <button type="button" class="kds__clear-item" onclick="clearCompleted()">
                            Clear completed (1hr+)
                        </button>
                        <button type="button" class="kds__clear-item kds__clear-item--danger" onclick="if(confirm('Mark all active orders as completed?')) clearAll()">
                            Complete all orders
                        </button>
                    </div>
                </div>

                <button type="button" class="kds-chip" id="done-chip" onclick="toggleDonePanel()" aria-pressed="false">
                    <span class="kds-chip__num" id="done-count">0</span>
                    <span class="kds-chip__lbl">Done</span>
                </button>

                <div class="kds-chip" aria-label="Open tickets">
                    <span class="kds-chip__num" id="open-count">{{ $orders->count() }}</span>
                    <span class="kds-chip__lbl">Open</span>
                </div>
            </div>

            @if(!$systemStatus['pos_connected'])
                <div class="kds__pos-down">
                    <strong>POS database disconnected</strong> — new orders will not be detected.
                </div>
            @endif
        </header>

        <main class="kds__feed" id="orders-container">
            @forelse($orders as $order)
                @php
                    $drinks = $order->items->filter(fn ($i) => $i->kind === 'drink');
                    $bakery = $order->items->filter(fn ($i) => $i->kind !== 'drink');
                @endphp
                <div class="order-wrap order-wrap--fresh-entry" data-order-id="{{ $order->id }}">
                    <article class="order order--fresh"
                             data-placed-at="{{ $order->order_time->valueOf() }}"
                             data-order-id="{{ $order->id }}">
                        <header class="order__head">
                            <div class="order__id-block">
                                <div class="order__id">#{{ $order->ticket_number }}</div>
                                <div class="order__meta">
                                    <span class="order__channel">In-store</span>
                                    <span class="order__dot">·</span>
                                    <span class="order__placed">{{ $order->order_time->format('H:i') }}</span>
                                </div>
                            </div>
                            <div class="order__timer">
                                <div class="order__time" data-role="time">00:00</div>
                                <div class="order__time-label" data-role="time-label">FRESH</div>
                            </div>
                        </header>

                        @if($order->person_name)
                            <div class="order__customer">
                                <span class="order__customer-label">Taken by</span>
                                <span class="order__customer-name">{{ $order->person_name }}</span>
                            </div>
                        @endif

                        <div class="order__groups">
                            @if($drinks->count())
                                <section class="group group--drink">
                                    <div class="group__label">
                                        <span class="group__dot"></span>
                                        <span class="group__name">Drinks</span>
                                        <span class="group__count">{{ $drinks->sum(fn ($i) => (float) $i->quantity) }}</span>
                                    </div>
                                    <ul class="group__list">
                                        @foreach($drinks as $item)
                                            @include('kds._item', ['item' => $item, 'orderId' => $order->id])
                                        @endforeach
                                    </ul>
                                </section>
                            @endif
                            @if($bakery->count())
                                <section class="group group--bakery">
                                    <div class="group__label">
                                        <span class="group__dot"></span>
                                        <span class="group__name">Bakery</span>
                                        <span class="group__count">{{ $bakery->sum(fn ($i) => (float) $i->quantity) }}</span>
                                    </div>
                                    <ul class="group__list">
                                        @foreach($bakery as $item)
                                            @include('kds._item', ['item' => $item, 'orderId' => $order->id])
                                        @endforeach
                                    </ul>
                                </section>
                            @endif
                        </div>

                        <button type="button" class="order__cta" onclick="completeOrder({{ $order->id }})">
                            <span class="order__cta-check">✓</span>
                            <span>Complete order</span>
                            @if($order->items->count() > 1)
                                <span class="order__cta-progress" data-role="progress">0/{{ $order->items->count() }}</span>
                            @endif
                        </button>
                    </article>
                </div>
            @empty
                <div class="kds__empty" id="empty-state">
                    <div class="kds__empty-mark">✓</div>
                    <div class="kds__empty-title">All caught up</div>
                    <div class="kds__empty-sub">No open tickets right now.</div>
                </div>
            @endforelse
        </main>

        <div id="done-panel" class="kds__done-panel" hidden>
            <div class="kds__done-title">Recently completed (last 30 min)</div>
            <div id="done-list"></div>
        </div>
    </div>

    @push('scripts')
    <script>
        const CSRF = '{{ csrf_token() }}';
        const STREAM_URL = '{{ route('kds.stream') }}';
        const ORDERS_URL = '{{ route('kds.orders') }}';
        const POLL_URL = '{{ route('kds.poll') }}';
        const REALTIME_URL = '{{ route('kds.realtime-check') }}';
        const CLEAR_COMPLETED_URL = '{{ route('kds.clear-completed') }}';
        const CLEAR_ALL_URL = '{{ route('kds.clear-all') }}';

        const WARN_SECONDS = 240;
        const LATE_SECONDS = 540;

        // Per-item tick state: Map<"orderId:itemId", boolean>
        const tickState = new Map();
        // Track which order IDs were previously rendered so we can avoid
        // re-firing the entry animation on cards that were already on screen.
        const seenOrderIds = new Set([...document.querySelectorAll('[data-order-id]')]
            .map(el => el.dataset.orderId));

        // Cached SSE payload + completed-panel state
        let lastCompleted = [];
        let donePanelOpen = false;
        let eventSource = null;
        let isRefreshing = false;

        /* ─────────────── helpers ─────────────── */
        const pad = (n) => String(n).padStart(2, '0');
        const fmtElapsed = (ms) => {
            const s = Math.max(0, Math.floor(ms / 1000));
            return `${pad(Math.floor(s / 60))}:${pad(s % 60)}`;
        };
        const fmtClock = (d) => `${pad(d.getHours())}:${pad(d.getMinutes())}:${pad(d.getSeconds())}`;
        const fmtHM = (ms) => {
            const d = new Date(ms);
            return `${pad(d.getHours())}:${pad(d.getMinutes())}`;
        };
        const urgencyOf = (elapsedSec) => {
            if (elapsedSec >= LATE_SECONDS) return 'late';
            if (elapsedSec >= WARN_SECONDS) return 'warn';
            return 'fresh';
        };
        const escapeHtml = (s) => String(s ?? '').replace(/[&<>"']/g, c =>
            ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
        const modifiersHtml = (mods) => {
            if (!mods) return '';
            const list = Array.isArray(mods)
                ? mods
                : Object.entries(mods).map(([k, v]) => `${k}: ${v}`);
            return list.length
                ? `<div class="item__mods">${list.map(m => `<span class="item__mod">${escapeHtml(m)}</span>`).join('')}</div>`
                : '';
        };

        /* ─────────────── card render ─────────────── */
        function itemHtml(orderId, item) {
            const key = `${orderId}:${item.id}`;
            const done = tickState.get(key) === true;
            return `
                <li class="item ${done ? 'item--done' : ''}" data-item-id="${item.id}" onclick="toggleItem(${orderId}, ${item.id})">
                    <button type="button" class="item__check" tabindex="-1" aria-label="${done ? 'Uncheck' : 'Check'}">
                        ${done ? '✓' : ''}
                    </button>
                    <div class="item__body">
                        <div class="item__main">
                            <span class="item__qty">${escapeHtml(item.quantity)}×</span>
                            <span class="item__name">${escapeHtml(item.product_name)}</span>
                        </div>
                        ${modifiersHtml(item.modifiers)}
                        ${item.notes ? `<div class="item__notes">Note: ${escapeHtml(item.notes)}</div>` : ''}
                    </div>
                </li>`;
        }

        function groupHtml(orderId, kind, items, label) {
            if (!items.length) return '';
            const totalQty = items.reduce((s, i) => s + (parseFloat(i.quantity) || 0), 0);
            const qtyDisplay = totalQty === Math.floor(totalQty) ? String(totalQty) : totalQty.toFixed(2);
            return `
                <section class="group group--${kind}">
                    <div class="group__label">
                        <span class="group__dot"></span>
                        <span class="group__name">${label}</span>
                        <span class="group__count">${qtyDisplay}</span>
                    </div>
                    <ul class="group__list">
                        ${items.map(it => itemHtml(orderId, it)).join('')}
                    </ul>
                </section>`;
        }

        function cardHtml(order) {
            const drinks = order.items.filter(i => i.kind === 'drink');
            const bakery = order.items.filter(i => i.kind !== 'drink');
            const totalItems = order.items.length;
            const doneCount = order.items.filter(i => tickState.get(`${order.id}:${i.id}`) === true).length;

            return `
                <article class="order order--fresh"
                         data-placed-at="${order.placed_at_ts}"
                         data-order-id="${order.id}">
                    <header class="order__head">
                        <div class="order__id-block">
                            <div class="order__id">#${escapeHtml(order.ticket_number)}</div>
                            <div class="order__meta">
                                <span class="order__channel">In-store</span>
                                <span class="order__dot">·</span>
                                <span class="order__placed">${fmtHM(order.placed_at_ts)}</span>
                            </div>
                        </div>
                        <div class="order__timer">
                            <div class="order__time" data-role="time">00:00</div>
                            <div class="order__time-label" data-role="time-label">FRESH</div>
                        </div>
                    </header>
                    ${order.person_name ? `
                        <div class="order__customer">
                            <span class="order__customer-label">Taken by</span>
                            <span class="order__customer-name">${escapeHtml(order.person_name)}</span>
                        </div>` : ''}
                    <div class="order__groups">
                        ${groupHtml(order.id, 'drink',  drinks, 'Drinks')}
                        ${groupHtml(order.id, 'bakery', bakery, 'Bakery')}
                    </div>
                    <button type="button" class="order__cta" onclick="completeOrder(${order.id})">
                        <span class="order__cta-check">✓</span>
                        <span>Complete order</span>
                        ${totalItems > 1 ? `<span class="order__cta-progress" data-role="progress">${doneCount}/${totalItems}</span>` : ''}
                    </button>
                </article>`;
        }

        function renderActive(orders) {
            const container = document.getElementById('orders-container');
            document.getElementById('open-count').textContent = orders.length;

            // Play notification sound for orders we've never seen
            orders.forEach(o => {
                if (!seenOrderIds.has(String(o.id)) && o.status === 'new') {
                    playNotificationSound();
                }
            });

            const newIdSet = new Set(orders.map(o => String(o.id)));

            if (orders.length === 0) {
                container.innerHTML = `
                    <div class="kds__empty" id="empty-state">
                        <div class="kds__empty-mark">✓</div>
                        <div class="kds__empty-title">All caught up</div>
                        <div class="kds__empty-sub">No open tickets right now.</div>
                    </div>`;
                seenOrderIds.clear();
                return;
            }

            container.innerHTML = orders.map(o => {
                const isNew = !seenOrderIds.has(String(o.id));
                return `<div class="order-wrap ${isNew ? 'order-wrap--fresh-entry' : ''}" data-order-id="${o.id}">${cardHtml(o)}</div>`;
            }).join('');

            // Update memory of which order IDs are on screen
            seenOrderIds.clear();
            newIdSet.forEach(id => seenOrderIds.add(id));

            updateTimers();
        }

        function renderCompleted(completed) {
            lastCompleted = completed || [];
            document.getElementById('done-count').textContent = lastCompleted.length;
            if (!donePanelOpen) return;
            const list = document.getElementById('done-list');
            if (!lastCompleted.length) {
                list.innerHTML = `<div class="kds__done-empty">No completed orders in the last 30 minutes.</div>`;
                return;
            }
            list.innerHTML = lastCompleted.map(o => `
                <div class="kds__done-row" id="done-row-${o.id}">
                    <span class="kds__done-id">#${escapeHtml(o.ticket_number)}</span>
                    <span class="kds__done-items">${o.items.map(i => `${escapeHtml(i.quantity)}× ${escapeHtml(i.product_name)}`).join(', ')}</span>
                    <span class="kds__done-time">${escapeHtml(o.completed_time)}</span>
                    <button type="button" class="kds__done-restore" onclick="restoreOrder(${o.id})">↺ Restore</button>
                </div>`).join('');
        }

        function updateTimers() {
            const now = Date.now();
            document.querySelectorAll('.order[data-placed-at]').forEach(card => {
                const placed = parseInt(card.dataset.placedAt, 10);
                if (!placed) return;
                const elapsedMs = now - placed;
                const elapsedSec = Math.floor(elapsedMs / 1000);
                const urgency = urgencyOf(elapsedSec);
                card.classList.remove('order--fresh', 'order--warn', 'order--late');
                card.classList.add(`order--${urgency}`);
                const timeEl = card.querySelector('[data-role="time"]');
                const labelEl = card.querySelector('[data-role="time-label"]');
                if (timeEl) timeEl.textContent = fmtElapsed(elapsedMs);
                if (labelEl) labelEl.textContent = urgency === 'late' ? 'LATE' : urgency === 'warn' ? 'AGING' : 'FRESH';
            });
            document.getElementById('kds-clock').textContent = fmtClock(new Date());
        }

        /* ─────────────── interactions ─────────────── */
        window.toggleItem = function (orderId, itemId) {
            const key = `${orderId}:${itemId}`;
            const newVal = !tickState.get(key);
            tickState.set(key, newVal);

            const card = document.querySelector(`.order[data-order-id="${orderId}"]`);
            if (!card) return;
            const li = card.querySelector(`.item[data-item-id="${itemId}"]`);
            if (li) {
                li.classList.toggle('item--done', newVal);
                const check = li.querySelector('.item__check');
                if (check) check.textContent = newVal ? '✓' : '';
            }
            // Update progress badge
            const progress = card.querySelector('[data-role="progress"]');
            if (progress) {
                const total = card.querySelectorAll('.item').length;
                const done = card.querySelectorAll('.item--done').length;
                progress.textContent = `${done}/${total}`;
            }
        };

        window.completeOrder = async function (orderId) {
            const wrap = document.querySelector(`.order-wrap[data-order-id="${orderId}"]`);
            if (wrap) wrap.classList.add('order-wrap--leaving');
            try {
                await fetch(`/kds/orders/${orderId}/status`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF },
                    body: JSON.stringify({ status: 'completed' }),
                });
            } catch (e) {
                console.error('Failed to complete order', e);
            }
            setTimeout(() => wrap && wrap.remove(), 320);
        };

        window.restoreOrder = async function (orderId) {
            try {
                const r = await fetch(`/kds/orders/${orderId}/status`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF },
                    body: JSON.stringify({ status: 'new' }),
                });
                if (!r.ok) throw new Error('restore failed');
                const row = document.getElementById(`done-row-${orderId}`);
                if (row) row.remove();
                manualRefresh();
            } catch (e) {
                console.error('Failed to restore order', e);
                alert('Failed to restore order.');
            }
        };

        window.toggleDonePanel = function () {
            donePanelOpen = !donePanelOpen;
            const panel = document.getElementById('done-panel');
            const chip = document.getElementById('done-chip');
            panel.hidden = !donePanelOpen;
            chip.classList.toggle('kds-chip--active', donePanelOpen);
            chip.setAttribute('aria-pressed', donePanelOpen ? 'true' : 'false');
            if (donePanelOpen) renderCompleted(lastCompleted);
        };

        window.toggleClearMenu = function (e) {
            e.stopPropagation();
            const menu = document.getElementById('clear-menu');
            menu.hidden = !menu.hidden;
        };
        document.addEventListener('click', (e) => {
            const menu = document.getElementById('clear-menu');
            if (menu && !menu.hidden && !menu.parentElement.contains(e.target)) {
                menu.hidden = true;
            }
        });

        window.clearCompleted = async function () {
            try {
                const r = await fetch(CLEAR_COMPLETED_URL, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': CSRF },
                });
                const data = await r.json();
                if (data.success) {
                    document.getElementById('clear-menu').hidden = true;
                    manualRefresh();
                }
            } catch (e) { console.error(e); alert('Failed to clear completed orders.'); }
        };

        window.clearAll = async function () {
            try {
                const r = await fetch(CLEAR_ALL_URL, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': CSRF },
                });
                const data = await r.json();
                if (data.success) {
                    document.getElementById('clear-menu').hidden = true;
                    renderActive([]);
                    setTimeout(manualRefresh, 1500);
                }
            } catch (e) { console.error(e); alert('Failed to clear orders.'); }
        };

        function playNotificationSound() {
            try {
                const a = new Audio('/sounds/notification.mp3');
                a.play().catch(() => {});
            } catch (e) {}
        }

        /* ─────────────── data layer (SSE + polling) ─────────────── */
        function initializeSSE() {
            if (eventSource) eventSource.close();
            eventSource = new EventSource(STREAM_URL);
            eventSource.onmessage = (event) => {
                try {
                    const data = JSON.parse(event.data);
                    if (data.orders) renderActive(data.orders);
                    if (data.completed) renderCompleted(data.completed);
                    setLive(true);
                } catch (e) { console.error('SSE parse error', e); }
            };
            eventSource.onerror = () => {
                setLive(false);
                setTimeout(initializeSSE, 5000);
            };
            eventSource.onopen = () => setLive(true);
        }

        function setLive(active) {
            const dot = document.getElementById('kds-live');
            if (dot) dot.classList.toggle('kds__live--down', !active);
        }

        async function manualRefresh() {
            if (isRefreshing) return;
            isRefreshing = true;
            try {
                const [_, ordersResp] = await Promise.all([
                    fetch(POLL_URL, { method: 'POST', headers: { 'X-CSRF-TOKEN': CSRF } }),
                    fetch(ORDERS_URL),
                ]);
                const data = await ordersResp.json();
                if (data.active) renderActive(data.active);
                if (data.completed) renderCompleted(data.completed);
            } catch (e) { console.error('Refresh failed', e); }
            finally { isRefreshing = false; }
        }

        async function fastRealtimeCheck() {
            try {
                const r = await fetch(REALTIME_URL);
                const data = await r.json();
                if (data.success && data.orders_created > 0) {
                    const ordersResp = await fetch(ORDERS_URL);
                    const ordersData = await ordersResp.json();
                    if (ordersData.active) renderActive(ordersData.active);
                    if (ordersData.completed) renderCompleted(ordersData.completed);
                }
                setLive(true);
            } catch (e) {
                setLive(false);
            }
        }

        /* ─────────────── boot ─────────────── */
        document.addEventListener('DOMContentLoaded', () => {
            updateTimers();
            setInterval(updateTimers, 1000);

            initializeSSE();
            manualRefresh();
            setInterval(fastRealtimeCheck, 2000);
            setInterval(manualRefresh, 10000);
        });

        window.addEventListener('beforeunload', () => {
            if (eventSource) eventSource.close();
        });
        window.addEventListener('focus', manualRefresh);
    </script>
    @endpush
</x-admin-layout>
