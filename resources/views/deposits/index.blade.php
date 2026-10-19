<x-admin-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-baseline gap-4">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                {{ __('Bottle deposits') }}
            </h2>
            <a href="{{ route('barrel-codes.index') }}" class="text-sm text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-200">
                &larr; Barrel codes
            </a>
        </div>
    </x-slot>

    @push('styles')
        @vite('resources/css/modernist.css')
    @endpush

    @php
        $qty = fn ($n) => number_format((float) $n, floor((float) $n) == (float) $n ? 0 : 2);
        $bar = fn ($pct) => min(100, max(0, $pct / 1.5));
        $statusValue = implode(',', $statuses);
        $check = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M20 6 9 17l-5-5"></path></svg>';
        $cross = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M18 6 6 18"></path><path d="m6 6 12 12"></path></svg>';
    @endphp

    {{-- The Modernist design system (owner's design project), scoped to this page. --}}
    <div class="ds-modernist">
        <div class="max-w-[1600px] mx-auto">
            <div class="nav">
                <div class="nav-brand">Bottle deposits</div>
                <span class="dr-muted dr-num" style="font-size: 13px" @if ($lastSyncedAt) title="{{ $lastSyncedAt }}" @endif>
                    Till synced {{ $lastSyncedAt?->diffForHumans() ?? 'never' }}
                </span>
                <form method="POST" action="{{ route('deposits.sync') }}">
                    @csrf
                    <button type="submit" class="btn btn-secondary" style="padding: 6px 12px; font-size: 13px">Sync till now</button>
                </form>
                <form method="POST" action="{{ route('deposits.refresh') }}">
                    @csrf
                    <button type="submit" class="btn btn-secondary" style="padding: 6px 12px; font-size: 13px">Recompute suggestions</button>
                </form>
                <form method="POST" action="{{ route('deposits.products.confirm-all') }}">
                    @csrf
                    <button type="submit" @disabled($suggestedCount === 0) title="Confirms the deposit only; prices unchanged"
                            class="btn btn-primary" style="padding: 6px 12px; font-size: 13px">
                        Confirm all suggested ({{ $suggestedCount }})
                    </button>
                </form>
            </div>

            @if (session('success') || $errors->any())
                <div class="px-4 pt-4 space-y-2" style="font-size: 14px">
                    @if (session('success'))
                        <div class="tag tag-neutral" style="display: block; padding: 10px 14px; font-size: 14px">{{ session('success') }}</div>
                    @endif
                    @foreach ($errors->all() as $error)
                        <div class="tag tag-outline" style="display: block; padding: 10px 14px; font-size: 14px">{{ $error }}</div>
                    @endforeach
                </div>
            @endif

            {{-- 1. Deposit codes strip --}}
            <div class="dr-section">
                <div class="flex items-baseline gap-3" style="margin-bottom: 8px">
                    <h6 style="margin: 0">Deposit codes</h6>
                    <span class="dr-muted" style="font-size: 12px">
                        Switch a code on to charge it to customers, then confirm the products below. A confirmed product gets the
                        deposit on the till: scanning it adds a "Bottle deposit" line underneath, at cost and zero-rated. Refunds are
                        the "Bottle deposit refund" buttons in the till's Bottle Deposits category.
                        @if ($codesAll)
                            <a href="{{ request()->fullUrlWithoutQuery(['codes']) }}">Show deposit codes only</a>
                        @else
                            <a href="{{ request()->fullUrlWithQuery(['codes' => 'all']) }}">Show all {{ $tierCount }} codes</a>
                        @endif
                    </span>
                </div>
                <div class="dr-strip">
                    @foreach ($stripTiers as $tier)
                        <div>
                            <div class="flex justify-between dr-num">
                                <span style="font-weight: 600">{{ $tier->supplier_code }}</span>
                                <span>€{{ number_format((float) $tier->unit_price, 2) }}</span>
                            </div>
                            <span class="dr-muted">{{ $tier->name ?: $tier->description }}</span>
                            <form method="POST" action="{{ route('deposits.tiers.update', $tier) }}" x-data>
                                @csrf
                                @method('PATCH')
                                <input type="hidden" name="charge_customer" value="0">
                                <label>
                                    <input type="checkbox" name="charge_customer" value="1" class="dr-check" @checked($tier->charge_customer)
                                           x-on:change="$el.form.submit()">
                                    Charge customers
                                </label>
                            </form>
                            <span class="dr-muted dr-num" style="font-size: 11px">
                                @if ($tier->pos_product_id && isset($products[$tier->pos_product_id]))
                                    {{ $products[$tier->pos_product_id]->CODE }}@if ($tier->pos_refund_product_id && isset($products[$tier->pos_refund_product_id])) / {{ $products[$tier->pos_refund_product_id]->CODE }}@endif
                                @else
                                    not in till yet
                                @endif
                                · {{ $tier->confirmed_count }} confirmed
                            </span>
                        </div>
                    @endforeach
                </div>
            </div>

            {{-- 2. Review band --}}
            <div class="dr-band">
                <div style="flex: 1; min-width: 200px">
                    <div class="dr-kicker">Supplier · Udea</div>
                    <h4 style="margin: 0">Price review</h4>
                    <div class="dr-muted dr-num" style="font-size: 13px">
                        {{ $decidedCount }} of {{ $rows->count() }} decided · {{ $reviewedCount }} {{ Str::plural('price', $reviewedCount) }} reviewed
                    </div>
                </div>

                <div class="field">
                    <label>Delivery charge</label>
                    {{-- Three small forms, so the − / + value is the only delivery_pct sent. --}}
                    <div class="dr-stepper">
                        <form method="POST" action="{{ route('deposits.settings') }}">
                            @csrf
                            @method('PATCH')
                            <input type="hidden" name="delivery_pct" value="{{ max(0, $deliveryPct - 1) }}">
                            <button type="submit" class="btn" title="One point less">−</button>
                        </form>
                        <form method="POST" action="{{ route('deposits.settings') }}" x-data>
                            @csrf
                            @method('PATCH')
                            <input type="number" name="delivery_pct" value="{{ $deliveryPct + 0 }}" step="1" min="0" max="100"
                                   x-on:change="$el.form.submit()" class="input">
                        </form>
                        <span class="dr-pct">%</span>
                        <form method="POST" action="{{ route('deposits.settings') }}">
                            @csrf
                            @method('PATCH')
                            <input type="hidden" name="delivery_pct" value="{{ min(100, $deliveryPct + 1) }}">
                            <button type="submit" class="btn" title="One point more">+</button>
                        </form>
                    </div>
                </div>

                <form method="GET" action="{{ route('deposits.index') }}" x-data class="flex flex-wrap items-end" style="gap: 24px">
                    <div class="field">
                        <label>Status</label>
                        <div class="seg dr-num">
                            @foreach ([
                                'suggested,confirmed' => 'Suggested + confirmed',
                                'suggested' => 'Suggested '.$counts['suggested'],
                                'confirmed' => 'Confirmed '.$counts['confirmed'],
                                'rejected' => 'Rejected '.$counts['rejected'],
                                'suggested,confirmed,rejected' => 'All',
                            ] as $value => $label)
                                <label class="seg-opt">
                                    <input type="radio" name="status" value="{{ $value }}" @checked($statusValue === $value) x-on:change="$el.form.submit()">{{ $label }}
                                </label>
                            @endforeach
                        </div>
                    </div>
                    <div class="field">
                        <label>Group by</label>
                        <div class="seg">
                            @foreach (['tier' => 'Tier', 'brand' => 'Brand', 'none' => 'None'] as $value => $label)
                                <label class="seg-opt">
                                    <input type="radio" name="group" value="{{ $value }}" @checked($group === $value) x-on:change="$el.form.submit()">{{ $label }}
                                </label>
                            @endforeach
                        </div>
                    </div>
                    <div class="field">
                        <label>Sort</label>
                        <select name="sort" x-on:change="$el.form.submit()" class="input">
                            @foreach ([
                                'name' => 'Brand / name A–Z',
                                'markup' => 'Markup, lowest first',
                                'sales' => 'Sales volume, highest first',
                            ] as $value => $label)
                                <option value="{{ $value }}" @selected($sort === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                </form>

            </div>

            {{-- 3. Grid --}}
            <div class="overflow-x-auto">
                <div class="min-w-[1300px]">
                    <div class="dr-cols dr-head">
                        <div>Product</div>
                        <div>Sales</div>
                        <div>Price</div>
                        <div>Markup now</div>
                        <div>Udea</div>
                        <div>New price</div>
                        <div>New markup</div>
                        <div>Decision</div>
                    </div>

                    @forelse ($groups as $g)
                        @if ($g['show'])
                            <div class="dr-group">
                                <span class="dr-group-title">{{ $g['title'] }}</span>
                                <span class="dr-group-sub">{{ $g['sub'] }}</span>
                                <span style="flex: 1"></span>
                                @if ($g['confirm'])
                                    <form method="POST" action="{{ route('deposits.products.confirm-all') }}">
                                        @csrf
                                        @foreach ($g['confirm'] as $field => $value)
                                            <input type="hidden" name="{{ $field }}" value="{{ $value }}">
                                        @endforeach
                                        <button type="submit" @disabled($g['open'] === 0) title="Confirms the deposit only; prices unchanged" class="btn btn-secondary">
                                            {{ $g['open'] > 0 ? 'Confirm '.$g['open'] : 'All decided' }}
                                        </button>
                                    </form>
                                @endif
                            </div>
                        @endif

                        @foreach ($g['rows'] as $row)
                            @php
                                $product = $products[$row->product_id] ?? null;
                                $detail = $details[$row->id] ?? ['udea_code' => null, 'others' => []];
                                $p = $prices[$row->product_id] ?? null;
                                $gross = $p['gross'] ?? null;
                                $landed = $p['landed'] ?? null;
                                $deposit = (float) $row->barrelCode?->unit_price;
                                $canLower = $gross !== null && round($gross - $deposit, 2) > 0;
                                $suggested = $row->status === 'suggested';
                                $markNow = isset($p['markup_pct']) ? (int) round($p['markup_pct']) : null;
                                $stepTitle = 'Full-price till lines (discounted lines ignored); a change counts once it holds for three selling days.'
                                    .(($p['cost_before_step'] ?? null) !== null ? ' Udea cost then '.number_format($p['cost_before_step'], 2).'.' : '');
                            @endphp
                            <div x-data="depositRow(@js([
                                    'name' => $product?->NAME ?? $row->product_code,
                                    'gross' => $gross,
                                    'dep' => $deposit,
                                    'vatRatio' => $p['vat_ratio'] ?? 1,
                                    'landed' => $landed,
                                    'markNow' => $markNow,
                                ]))"
                                 class="dr-cols dr-row {{ $suggested ? '' : 'is-decided' }}">

                                {{-- 1. Product: name; barcode · Udea code with the tier select beside it --}}
                                <div>
                                    @if ($product)
                                        <a href="{{ route('products.edit', $product->ID) }}" class="dr-name" title="{{ $product->NAME }}">{{ $product->NAME }}</a>
                                    @else
                                        <span class="dr-muted">{{ $row->product_code ?? $row->product_id }} (not on till)</span>
                                    @endif
                                    <div class="dr-sub dr-small dr-muted dr-num">
                                        <span>{{ $product?->CODE ?? $row->product_code }}@if ($detail['udea_code']) · Udea {{ $detail['udea_code'] }}@endif @if ($row->isManual()) · manual @endif</span>
                                        <form method="POST" action="{{ route('deposits.products.update', $row) }}" x-data>
                                            @csrf
                                            @method('PATCH')
                                            <select name="barrel_code_id" x-on:change="$el.form.submit()" class="input" title="Deposit tier" aria-label="Deposit tier">
                                                @if (! $chargeTiers->contains('id', $row->barrel_code_id))
                                                    <option value="{{ $row->barrel_code_id }}" selected>
                                                        {{ $row->barrelCode?->supplier_code }} · €{{ number_format((float) $row->barrelCode?->unit_price, 2) }} (off)
                                                    </option>
                                                @endif
                                                @foreach ($chargeTiers as $tier)
                                                    <option value="{{ $tier->id }}" @selected($tier->id == $row->barrel_code_id)>
                                                        {{ $tier->supplier_code }} · €{{ number_format((float) $tier->unit_price, 2) }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </form>
                                    </div>
                                </div>

                                {{-- 2. Sales: at most three lines --}}
                                <div class="dr-num">
                                    @if (($p['sold_12m'] ?? null) !== null)
                                        <div class="dr-nowrap dr-sales1">sold {{ $qty($p['sold_12m']) }} in 12 mo · last {{ $p['last_sale_on'] }}</div>
                                    @else
                                        <div class="dr-nowrap dr-sales1 dr-muted">no till sales</div>
                                    @endif
                                    @if ($row->sightings_count > 0)
                                        <div class="dr-nowrap dr-small dr-muted" @if ($row->last_seen_on) title="last docket {{ $row->last_seen_on->toDateString() }}" @endif>
                                            Udea {{ $row->sightings_units }} {{ Str::plural('unit', $row->sightings_units) }} · {{ $row->sightings_count }} {{ Str::plural('docket', $row->sightings_count) }}
                                        </div>
                                    @elseif ($row->isManual())
                                        <div class="dr-nowrap dr-small dr-muted">added by hand</div>
                                    @endif
                                    @if ($row->conflicting_units > 0)
                                        <div class="dr-nowrap dr-small dr-accent">
                                            @forelse ($detail['others'] as $code => $units)
                                                also {{ $code }} ({{ $units }} {{ Str::plural('unit', $units) }})@if (! $loop->last), @endif
                                            @empty
                                                also another code ({{ $row->conflicting_units }} units)
                                            @endforelse
                                        </div>
                                    @endif
                                </div>

                                {{-- 3. Price: gross, one step line, the changed-since-sale line when it applies --}}
                                <div class="dr-num" title="{{ $stepTitle }}">
                                    @if ($gross !== null)
                                        <div class="dr-price">€{{ number_format($gross, 2) }}</div>
                                        @if ($p['step'])
                                            <div class="dr-nowrap dr-small dr-muted">
                                                {{ number_format($p['step']['from'], 2) }} → {{ number_format($p['step']['to'], 2) }} · {{ $p['step']['on'] }}
                                            </div>
                                        @endif
                                        @if ($p['changed_since_sale'])
                                            <div class="dr-small dr-amber">
                                                now {{ number_format($p['net'], 2) }}, was {{ number_format($p['last_sold']['net'], 2) }} (no sale since {{ $p['last_sold']['on'] }})
                                            </div>
                                        @endif
                                    @else
                                        —
                                    @endif
                                </div>

                                {{-- 4. Markup now --}}
                                <div class="dr-meter">
                                    @if ($markNow !== null)
                                        <div class="flex justify-between">
                                            <span>Markup</span>
                                            <span class="{{ $markNow < 0 ? 'dr-negative' : '' }}" style="font-weight: 600">{{ $markNow }}%</span>
                                        </div>
                                        <div class="dr-bar"><i class="{{ $markNow < 0 ? 'is-negative' : '' }}" style="width: {{ $bar($markNow) }}%"></i></div>
                                        <div class="dr-muted">landed {{ number_format($landed, 2) }} · net {{ number_format($p['net'], 2) }}</div>
                                    @else
                                        —
                                    @endif
                                </div>

                                {{-- 5. Udea --}}
                                <div class="dr-num flex items-center" style="font-size: 12px">
                                    {{ ($p['udea_rrp'] ?? null) !== null ? '€'.number_format($p['udea_rrp'], 2) : '—' }}
                                </div>

                                {{-- 6. New price --}}
                                <div class="flex flex-col justify-center" style="gap: 4px" x-bind:class="choice === 'custom' ? 'dr-custom' : ''">
                                    @if ($row->isPriceReviewed())
                                        <div class="dr-small">
                                            @if ($row->price_review === 'kept')
                                                <span class="tag tag-neutral" style="padding: 1px 8px">kept</span>
                                            @else
                                                <span class="tag tag-accent" style="padding: 1px 8px">{{ $row->price_review }} from €{{ number_format((float) $row->price_review_from, 2) }}</span>
                                            @endif
                                            <span class="dr-muted">{{ $row->price_reviewed_at?->toDateString() }}@if ($row->priceReviewedBy) · {{ $row->priceReviewedBy->name }}@endif</span>
                                        </div>
                                    @endif
                                    @if ($suggested && $gross !== null)
                                        <div class="dr-choice">
                                            <div class="seg dr-num">
                                                <label class="seg-opt">
                                                    <input type="radio" name="choice-{{ $row->id }}" value="keep" x-model="choice" x-on:change="custom = ''">Keep
                                                </label>
                                                <label class="seg-opt {{ $canLower ? '' : 'is-disabled' }}" @unless ($canLower) title="The price is not above the deposit." @endunless>
                                                    <input type="radio" name="choice-{{ $row->id }}" value="lower" x-model="choice" x-on:change="custom = ''" @disabled(! $canLower)>−€{{ number_format($deposit, 2) }}
                                                </label>
                                            </div>
                                            <div class="dr-euro">
                                                <span>€</span>
                                                <input type="text" inputmode="decimal" placeholder="price" title="Type a custom price" class="input"
                                                       x-model="custom" x-on:input="choice = 'custom'">
                                            </div>
                                        </div>
                                    @elseif (! $row->isPriceReviewed())
                                        <span class="dr-muted">—</span>
                                    @endif
                                </div>

                                {{-- 7. New markup --}}
                                <div class="dr-meter">
                                    @if ($suggested && $landed && $gross !== null)
                                        <div class="flex justify-between items-baseline">
                                            <span style="font-weight: 800; font-size: 15px" x-bind:class="newMark < 0 ? 'dr-negative' : ''" x-text="newMark + '%'">{{ $markNow }}%</span>
                                            <span class="dr-muted" x-text="deltaText">no change</span>
                                        </div>
                                        <div class="dr-bar"><i style="width: {{ $bar($markNow) }}%" x-bind:class="newMark < 0 ? 'is-negative' : ''" x-bind:style="'width: ' + barWidth + '%'"></i></div>
                                        <div class="dr-muted" x-text="'€' + profit.toFixed(2) + ' profit / unit'">€{{ number_format($p['profit'], 2) }} profit / unit</div>
                                    @else
                                        <span class="dr-muted">—</span>
                                    @endif
                                </div>

                                {{-- 8. Decision --}}
                                <div class="dr-decision">
                                    @if ($suggested)
                                        <form method="POST" action="{{ route('deposits.products.update', $row) }}"
                                              x-on:submit="if (choice !== 'keep' && ! confirm(confirmText)) $event.preventDefault()">
                                            @csrf
                                            @method('PATCH')
                                            <input type="hidden" name="status" value="confirmed">
                                            <input type="hidden" name="price_action" value="keep" x-bind:value="choice">
                                            <input type="hidden" name="new_price" value="" x-bind:value="choice === 'custom' ? custom : ''">
                                            <button type="submit" title="Confirm" aria-label="Confirm" class="btn btn-primary btn-icon">{!! $check !!}</button>
                                        </form>
                                        <form method="POST" action="{{ route('deposits.products.update', $row) }}">
                                            @csrf
                                            @method('PATCH')
                                            <input type="hidden" name="status" value="rejected">
                                            <button type="submit" title="Reject" aria-label="Reject" class="btn btn-secondary btn-icon">{!! $cross !!}</button>
                                        </form>
                                    @else
                                        <span class="tag {{ $row->status === 'confirmed' ? 'tag-accent' : 'tag-neutral' }}">{{ ucfirst($row->status) }}</span>
                                        <form method="POST" action="{{ route('deposits.products.price', $row) }}"
                                              @if ($row->price_review_from !== null && in_array($row->price_review, ['lowered', 'set'], true))
                                                  x-on:submit="if (! confirm(@js(sprintf('Undo also restores the price to €%.2f. Continue?', (float) $row->price_review_from)))) $event.preventDefault()"
                                              @endif>
                                            @csrf
                                            @method('PATCH')
                                            <input type="hidden" name="action" value="undo">
                                            <button type="submit" class="btn btn-ghost">Undo</button>
                                        </form>
                                    @endif
                                    @if ($row->pos_sync_error)
                                        <span class="tag tag-outline dr-small" style="padding: 1px 6px" title="{{ $row->pos_sync_error }}">till: {{ Str::limit($row->pos_sync_error, 24) }}</span>
                                    @endif
                                    @if ($row->isManual())
                                        <form method="POST" action="{{ route('deposits.products.destroy', $row) }}">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-ghost">Remove</button>
                                        </form>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    @empty
                        <div class="dr-empty">No products here. Switch a deposit code on and recompute suggestions, or add one below.</div>
                    @endforelse
                </div>
            </div>

            <div class="dr-footer">
                Markup = net price ÷ (Udea cost × (1 + delivery)) − 1, with delivery at {{ $deliveryPct + 0 }}%. Net is the price ex VAT.
                New net uses each product's own price-to-net ratio. Markups are against this landed cost, so they read lower than the
                bare-cost figures shown before.
            </div>

            {{-- 4. Add a product (Tailwind: the picker component is shared) --}}
            <div class="bg-white shadow-sm" style="border-top: 2px solid var(--color-divider)">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="font-medium text-gray-900" style="font-size: 16px; margin: 0">Add a product</h3>
                </div>
                <div class="p-6">
                    @if ($chargeTiers->isEmpty())
                        <p class="text-sm text-gray-500">Switch a deposit tier on first.</p>
                    @else
                        <form method="POST" action="{{ route('deposits.products.store') }}" class="flex flex-wrap items-start gap-4"
                              x-data="{ picked: null }"
                              x-on:product-search:selected="picked = $event.detail"
                              x-on:product-search:cleared="picked = null">
                            @csrf
                            <div class="flex-1 min-w-[280px]">
                                <x-product-search mode="picker" name="product_id" :sync-url="false" :autofocus="$rows->isEmpty()" placeholder="Scan or type a barcode, or part of the name…" />
                                {{-- The picker clears its own box after a pick, so say what was chosen. --}}
                                <p x-show="picked" x-cloak class="mt-2 text-sm text-gray-900">
                                    Selected: <span class="font-medium" x-text="picked?.name"></span>
                                    (<span class="font-mono" x-text="picked?.code"></span>)
                                </p>
                                <p x-show="!picked" class="mt-2 text-xs text-gray-500">
                                    Scan or type a barcode, or type part of the name, then choose a result; Enter selects the top match.
                                </p>
                            </div>
                            <select name="barrel_code_id" class="rounded-md border-gray-300">
                                @foreach ($chargeTiers as $tier)
                                    <option value="{{ $tier->id }}">{{ $tier->supplier_code }} — €{{ number_format((float) $tier->unit_price, 2) }}</option>
                                @endforeach
                            </select>
                            <button type="submit" x-bind:disabled="!picked"
                                    class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 disabled:opacity-50 disabled:cursor-not-allowed">Add</button>
                        </form>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <script>
        // One price row on /deposits: the owner's choice (keep / lower by the
        // deposit / a typed gross price) and the markup it would give.
        document.addEventListener('alpine:init', () => {
            Alpine.data('depositRow', (o) => ({
                choice: 'keep',
                custom: '',
                ...o,
                get newGross() {
                    if (this.choice === 'keep') return this.gross;
                    if (this.choice === 'lower') return Math.round((this.gross - this.dep) * 100) / 100;
                    return parseFloat(String(this.custom).replace(',', '.')) || 0;
                },
                get newNet() { return this.newGross / this.vatRatio; },
                get newMark() { return this.landed ? Math.round((this.newNet / this.landed - 1) * 100) : 0; },
                get profit() { return this.landed ? this.newNet - this.landed : 0; },
                get barWidth() { return Math.min(100, Math.max(0, this.newMark / 1.5)); },
                get deltaText() {
                    const d = this.newMark - (this.markNow ?? this.newMark);
                    return d === 0 ? 'no change' : (d > 0 ? '+' : '−') + Math.abs(d) + ' pts';
                },
                get confirmText() {
                    return 'Confirm ' + this.name + ' and change its till price from €' + (this.gross ?? 0).toFixed(2)
                        + ' to €' + this.newGross.toFixed(2) + '?';
                },
            }));
        });
    </script>
</x-admin-layout>
