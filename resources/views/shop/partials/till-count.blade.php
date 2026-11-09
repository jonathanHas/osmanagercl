{{--
    The till count form. Included by the Shop screen (shop/till-count) and the office
    page (management/cash-reconciliation/index) inside an element carrying
    x-data="shopTillCount()" and the data-state / data-*-url attributes.

    Expects: $till, $date, $statuses, $adjacent, $sessions, $session, $state, $open,
    $navRoute (route name for till/date navigation), $doneUrl.
--}}
@php
    $navRoute = $navRoute ?? 'shop.till-count';
    $doneUrl = $doneUrl ?? route('shop.home');
@endphp

<section class="shop-stack shop-stack--tight">
    <div class="shop-between">
        <div class="shop-inline">
            @if ($adjacent['prev'])
                <a class="shop-iconbtn" href="{{ route($navRoute, ['till' => $till?->id, 'date' => $adjacent['prev']]) }}" aria-label="Previous close"><x-shop.icon name="back" /></a>
            @endif
            <div class="shop-stack shop-stack--tight">
                <h2 class="shop-subtitle">{{ $date->format('l j F Y') }}</h2>
                @if ($session)
                    <p class="shop-meta">Closed {{ $session->DATEEND->format('H:i') }} · opened {{ $session->DATESTART?->format('D H:i') }} · {{ $session->NOSALES }} receipts</p>
                @endif
            </div>
            @if ($adjacent['next'])
                <a class="shop-iconbtn" href="{{ route($navRoute, ['till' => $till?->id, 'date' => $adjacent['next']]) }}" aria-label="Next close"><x-shop.icon name="chevron-right" /></a>
            @endif
        </div>
        @unless ($date->isToday())
            <a class="shop-btn shop-btn--ghost" href="{{ route($navRoute, ['till' => $till?->id]) }}">Today</a>
        @endunless
    </div>

    @if (count($statuses) > 0)
        <nav class="shop-seg shop-seg--block" aria-label="Till">
            @foreach ($statuses as $s)
                <a class="shop-seg__opt" href="{{ route($navRoute, ['till' => $s['id'], 'date' => $date->toDateString()]) }}" @if ($till && $s['id'] === $till->id) aria-current="page" @endif>
                    {{ $s['name'] }}
                    @if ($s['counted'])
                        <span class="shop-pill shop-pill--ok">Counted</span>
                    @elseif ($s['session_id'])
                        <span class="shop-pill shop-pill--warn">Not counted</span>
                    @elseif ($s['open'])
                        <span class="shop-pill shop-pill--muted">Open</span>
                    @else
                        <span class="shop-pill shop-pill--muted">No close</span>
                    @endif
                </a>
            @endforeach
        </nav>
    @endif

    @if ($sessions->count() > 1)
        <div class="shop-inline">
            <span class="shop-meta">Closed {{ $sessions->count() }} times this day:</span>
            @foreach ($sessions as $s)
                <a class="shop-pill {{ $session && $s->MONEY === $session->MONEY ? 'shop-pill--ok' : '' }}" href="{{ route($navRoute, ['till' => $till->id, 'date' => $date->toDateString(), 'session' => $s->MONEY]) }}">{{ $s->DATEEND->format('H:i') }}</a>
            @endforeach
        </div>
    @endif
</section>

@if (! $till)
    <section class="shop-empty">
        <div class="shop-empty__icon"><x-shop.icon name="cash" size="xl" /></div>
        <p class="shop-empty__title">No tills yet</p>
        <p class="shop-empty__text">A till appears here once it has been closed on the POS.</p>
    </section>
@elseif (! $session)
    <section class="shop-empty">
        <div class="shop-empty__icon"><x-shop.icon name="cash" size="xl" /></div>
        @if ($open)
            <p class="shop-empty__title">{{ $till->name }} is still open</p>
            <p class="shop-empty__text">Close the till on the POS, then come back to count it.</p>
        @else
            <p class="shop-empty__title">{{ $till->name }} did not close on this date</p>
            <p class="shop-empty__text">Pick another day or another till.</p>
        @endif
        @if ($adjacent['prev'])
            <a class="shop-btn shop-btn--secondary" href="{{ route($navRoute, ['till' => $till->id, 'date' => $adjacent['prev']]) }}">Last close: {{ \Carbon\Carbon::parse($adjacent['prev'])->format('D j M') }}</a>
        @endif
    </section>
@else
    {{-- Saved: the result --}}
    <section class="shop-card" x-show="saved" x-cloak>
        <div class="shop-inline"><span class="shop-avatar shop-avatar--accent"><x-shop.icon name="check" /></span><h2 class="shop-title">{{ $till->name }} counted</h2></div>
        <div class="shop-totals">
            <div class="shop-total"><span class="shop-label">Bag to lodge</span><span class="shop-total__value" x-text="money(saved?.bag)"></span></div>
            <div class="shop-total" :class="saved && 'shop-total--' + tone(saved.variance)"><span class="shop-label">Cash variance</span><span class="shop-total__value" x-text="money(saved?.variance, true)"></span></div>
        </div>
        <p class="shop-meta">Seal the bag, write <strong>{{ $till->name }} · {{ $date->format('j M') }}</strong> on it, and put it in the safe.</p>
        <p class="shop-notice" x-show="!! saved?.note_missing" x-cloak><x-shop.icon name="alert" />The variance is large and there is no note. Identify yourself, add a note and save again.</p>
        <div class="shop-inline">
            @if (count($statuses) > 1)
                <a class="shop-btn shop-btn--primary" href="{{ route($navRoute, ['date' => $date->toDateString()]) }}">Count the other till</a>
            @endif
            <a class="shop-btn shop-btn--secondary" href="{{ $doneUrl }}">Done</a>
        </div>
    </section>

    @if ($state['counted'])
        <div class="shop-notice" x-show="! saved">
            <x-shop.icon name="check" />
            <span>
                Counted by <strong>{{ $state['counted_by'] ?? 'the old system' }}</strong>@if ($state['counted_at']) at {{ $state['counted_at'] }}@endif.
                @if ($state['source'] === 'legacy') Imported from the old system. @endif
                @if ($state['bag_verified']) The bag has already been verified by a manager. @endif
                <span x-show="locked">To change it, say who you are below.</span>
            </span>
        </div>
    @endif

    {{-- Who is counting --}}
    <section class="shop-card" x-show="! saved">
        <template x-if="identity">
            <div class="shop-between">
                <div class="shop-inline">
                    <span class="shop-avatar shop-avatar--accent" x-text="initials(identity.name)"></span>
                    <div class="shop-stack shop-stack--tight">
                        <h2 class="shop-subtitle" x-text="'Counting as ' + identity.first_name"></h2>
                        <p class="shop-meta">The count is saved against your name.</p>
                    </div>
                </div>
                <button class="shop-btn shop-btn--ghost" type="button" @click="forget()">Not you?</button>
            </div>
        </template>

        <template x-if="! identity && ! person">
            <div class="shop-stack">
                <h2 class="shop-subtitle">Who is counting?</h2>
                <p class="shop-meta">Tap your name, then your PIN or password. Whoever is signed in on this device stays signed in.</p>
                <div class="shop-people">
                    <template x-for="p in people" :key="p.id">
                        <button class="shop-person" type="button" @click="choose(p)">
                            <span class="shop-avatar shop-avatar--lg" :class="p.pin ? 'shop-avatar--accent' : ''" x-text="p.initials"></span>
                            <span x-text="p.first_name"></span>
                            <span class="shop-person__role" x-text="p.pin ? 'PIN' : 'Password'"></span>
                        </button>
                    </template>
                </div>
            </div>
        </template>

        <template x-if="! identity && person">
            <div class="shop-pin" :class="{ 'is-error': idError }">
                <div class="shop-pin__who">
                    <span class="shop-avatar shop-avatar--xl shop-avatar--accent" x-text="person.initials"></span>
                    <h2 class="shop-title" x-text="'Hi ' + person.first_name"></h2>
                    <p class="shop-meta" x-text="usePassword ? 'Enter your password' : 'Enter your ' + (person.pin_length || 4) + '-digit PIN'"></p>
                </div>

                <template x-if="! usePassword">
                    <div class="shop-stack">
                        <div class="shop-pin__dots" :aria-label="pinDigits.length + ' digits entered'">
                            <template x-for="i in (person.pin_length || 4)" :key="i">
                                <span class="shop-pin__dot" :class="{ 'is-filled': pinDigits.length >= i }"></span>
                            </template>
                        </div>
                        <p class="shop-pin__msg" x-text="idError"></p>
                        <div class="shop-pin__pad">
                            @foreach ([1, 2, 3, 4, 5, 6, 7, 8, 9] as $digit)
                                <button class="shop-pin__key" type="button" @click="pressPin('{{ $digit }}')">{{ $digit }}</button>
                            @endforeach
                            <button class="shop-pin__key shop-pin__key--fn" type="button" @click="unchoose()">Not you?</button>
                            <button class="shop-pin__key" type="button" @click="pressPin('0')">0</button>
                            <button class="shop-pin__key shop-pin__key--fn" type="button" aria-label="Delete" @click="pinDigits = pinDigits.slice(0, -1)"><x-shop.icon name="backspace" size="lg" /></button>
                        </div>
                        <div class="shop-inline">
                            <button class="shop-btn shop-btn--ghost" type="button" @click="usePassword = true">Use password instead</button>
                        </div>
                    </div>
                </template>

                <template x-if="usePassword">
                    <form class="shop-stack" @submit.prevent="submitPassword()">
                        <div class="shop-field">
                            <label for="till-count-password">Password</label>
                            <input id="till-count-password" class="shop-input" type="password" autocomplete="current-password" x-model="password" x-init="$nextTick(() => $el.focus())">
                        </div>
                        <p class="shop-pin__msg" x-text="idError"></p>
                        <div class="shop-inline">
                            <button class="shop-btn shop-btn--primary" type="submit" :disabled="busy">Continue</button>
                            <button class="shop-btn shop-btn--ghost" type="button" @click="unchoose()">Not you?</button>
                            <button class="shop-btn shop-btn--ghost" type="button" x-show="person.pin" @click="usePassword = false">Use PIN</button>
                        </div>
                    </form>
                </template>
            </div>
        </template>
    </section>

    <div class="shop-count-grid" x-show="! saved">
        <div class="shop-stack">
            {{-- The drawer --}}
            <section class="shop-card">
                <div class="shop-between">
                    <h2 class="shop-subtitle">Count the drawer</h2>
                    <span class="shop-hint">Tap a number to type it</span>
                </div>
                <div class="shop-count">
                    <template x-for="d in denominations" :key="d.key">
                        <div class="shop-count__row" :class="{ 'is-set': counts[d.key] > 0 }">
                            <span class="shop-count__face" x-text="d.label"></span>
                            <div class="shop-count__qty">
                                <button class="shop-iconbtn" type="button" :aria-label="'Fewer ' + d.label" :disabled="locked || ! counts[d.key]" @click="bump(d.key, -1)"><x-shop.icon name="minus" /></button>
                                <button class="shop-count__value" type="button" :disabled="locked" @click="open(d.key, 'How many ' + d.label)" x-text="counts[d.key] || 0"></button>
                                <button class="shop-iconbtn" type="button" :aria-label="'More ' + d.label" :disabled="locked" @click="bump(d.key, 1)"><x-shop.icon name="plus" /></button>
                            </div>
                            <span class="shop-count__sub" x-text="money(subtotal(d))"></span>
                        </div>
                    </template>
                </div>
                <div class="shop-facts">
                    <div class="shop-fact"><span class="shop-label">Notes</span><span class="shop-fact__value" x-text="money(notesTotal)"></span></div>
                    <div class="shop-fact"><span class="shop-label">Coins</span><span class="shop-fact__value" x-text="money(coinsTotal)"></span></div>
                </div>
                <div class="shop-amount shop-amount--static">
                    <span class="shop-amount__label">Total in the drawer</span>
                    <span class="shop-amount__value" x-text="money(countedTotal)"></span>
                </div>
            </section>

            {{-- Float --}}
            <section class="shop-card">
                <h2 class="shop-subtitle">Float for tomorrow</h2>
                <div class="shop-amount">
                    <span class="shop-amount__label">Notes kept<small>What stays in the drawer</small></span>
                    <button class="shop-amount__value" type="button" :disabled="locked" @click="open('noteFloat', 'Notes kept as float')" x-text="money(noteFloat)"></button>
                </div>
                <div class="shop-amount">
                    <span class="shop-amount__label">Keep all the coins<small x-text="keepAllCoins ? 'Every coin stays in' : 'Some coins go in the bag'"></small></span>
                    <label class="shop-switch"><input type="checkbox" x-model="keepAllCoins" :disabled="locked"><span class="shop-switch__track"></span><span class="shop-switch__text"></span></label>
                </div>
                <div class="shop-amount" x-show="! keepAllCoins" x-cloak>
                    <span class="shop-amount__label">Coins kept</span>
                    <button class="shop-amount__value" type="button" :disabled="locked" @click="open('coinFloatManual', 'Coins kept as float')" x-text="money(coinFloat)"></button>
                </div>
                <div class="shop-ledger">
                    <div class="shop-ledger__line"><span>Float kept</span><span x-text="money(floatTotal)"></span></div>
                    <div class="shop-ledger__line is-total"><span>To bag</span><span x-text="money(bag)"></span></div>
                </div>
                <div class="shop-amount" :class="previousKnown ? 'shop-amount--static' : ''">
                    <span class="shop-amount__label">
                        Float carried in
                        <small>
                            @if ($state['previous_float'])
                                Left in on {{ \Carbon\Carbon::parse($state['previous_float']['date'])->format('D j M') }}@if ($state['previous_float']['source'] === 'legacy') (old system)@endif
                            @else
                                Not known: type what was left in last time
                            @endif
                        </small>
                    </span>
                    <template x-if="previousKnown"><span class="shop-amount__value" x-text="money(previousFloat)"></span></template>
                    <template x-if="! previousKnown"><button class="shop-amount__value" type="button" :disabled="locked" @click="open('previousFloat', 'Float carried in')" x-text="money(previousFloat)"></button></template>
                </div>
            </section>
        </div>

        <div class="shop-stack">
            {{-- Card terminal --}}
            <section class="shop-card">
                <h2 class="shop-subtitle">Card terminal</h2>
                <div class="shop-amount">
                    <span class="shop-amount__label">Terminal total<small>From the card machine's end-of-day report</small></span>
                    <button class="shop-amount__value" type="button" :disabled="locked" @click="open('card', 'Card terminal total')" x-text="money(card)"></button>
                </div>
                <div class="shop-ledger">
                    <div class="shop-ledger__line"><span>Till card sales</span><span x-text="money(state.pos.card)"></span></div>
                    <div class="shop-ledger__line" x-show="state.customer.card > 0"><span>+ Customer invoices paid by card</span><span x-text="money(state.customer.card)"></span></div>
                    <div class="shop-ledger__line is-total" x-show="hasCard"><span>Card variance</span><span x-text="money(cardVariance, true)"></span></div>
                </div>
            </section>

            {{-- Paid out, money in, note --}}
            <section class="shop-card">
                <details class="shop-disclosure" @if (! empty($state['payments']) || $state['money_added'] > 0) open @endif>
                    <summary><x-shop.icon name="more" /><span>Paid out of the till, money added</span><x-shop.icon name="chevron-down" size="sm" /></summary>
                    <div class="shop-stack">
                        <template x-for="(p, i) in payments" :key="i">
                            <div class="shop-amount">
                                <select class="shop-input shop-select" x-model="p.supplier_id" :disabled="locked" aria-label="Who was paid">
                                    <option value="">Who was paid?</option>
                                    <template x-for="s in suppliers" :key="s.id"><option :value="s.id" x-text="s.name"></option></template>
                                </select>
                                <button class="shop-amount__value" type="button" :disabled="locked" @click="open('payment:' + i, 'Paid to ' + (supplierName(p.supplier_id) || 'supplier'))" x-text="money(p.amount)"></button>
                                <button class="shop-iconbtn shop-iconbtn--ghost" type="button" aria-label="Remove" :disabled="locked" @click="removePayment(i)"><x-shop.icon name="x" /></button>
                            </div>
                        </template>
                        <div class="shop-inline">
                            <button class="shop-btn shop-btn--secondary" type="button" :disabled="locked" @click="addPayment()"><x-shop.icon name="plus" />Supplier paid from the till</button>
                        </div>
                        <div class="shop-amount">
                            <span class="shop-amount__label">Money added to the till<small>Change brought in from the safe</small></span>
                            <button class="shop-amount__value" type="button" :disabled="locked" @click="open('moneyAdded', 'Money added to the till')" x-text="money(moneyAdded)"></button>
                        </div>
                    </div>
                </details>
                <div class="shop-field">
                    <label for="till-count-note">Note</label>
                    <textarea id="till-count-note" class="shop-input" rows="2" x-model="note" :disabled="locked" placeholder="Anything to note about today?"></textarea>
                    <p class="shop-hint" x-show="noteRequired && note.trim() === ''" x-cloak>The cash variance is large, so say what happened before saving.</p>
                </div>
            </section>

            {{-- Does it balance --}}
            <section class="shop-card">
                <h2 class="shop-subtitle">Does it balance?</h2>
                <div class="shop-totals">
                    <div class="shop-total"><span class="shop-label">To bag</span><span class="shop-total__value" x-text="money(bag)"></span></div>
                    <div class="shop-total" :class="anythingCounted ? 'shop-total--' + tone(variance) : ''"><span class="shop-label">Cash variance</span><span class="shop-total__value" x-text="anythingCounted ? money(variance, true) : '–'"></span></div>
                </div>
                <div class="shop-ledger">
                    <div class="shop-ledger__line"><span>Counted</span><span x-text="money(countedTotal)"></span></div>
                    <div class="shop-ledger__line" x-show="supplierTotal > 0"><span>+ Paid to suppliers</span><span x-text="money(supplierTotal)"></span></div>
                    <div class="shop-ledger__line"><span>− Float carried in</span><span x-text="money(previousFloat)"></span></div>
                    <div class="shop-ledger__line" x-show="moneyAdded > 0"><span>− Money added</span><span x-text="money(moneyAdded)"></span></div>
                    <div class="shop-ledger__line is-total"><span>Day's cash</span><span x-text="money(daysCash)"></span></div>
                    <div class="shop-ledger__line"><span>Till cash sales</span><span x-text="money(state.pos.cash)"></span></div>
                    <div class="shop-ledger__line" x-show="state.customer.cash > 0"><span>+ Customer invoices paid in cash</span><span x-text="money(state.customer.cash)"></span></div>
                    <div class="shop-ledger__line is-total"><span>Expected cash</span><span x-text="money(expectedCash)"></span></div>
                </div>
                @php
                    $alsoRecorded = array_filter([
                        'vouchers' => $state['pos']['voucher'],
                        'free' => $state['pos']['free'],
                        'on account' => $state['pos']['debt'],
                        'debt paid' => $state['pos']['debtpaid'],
                    ] + $state['pos']['other'], fn ($v) => abs($v) >= 0.005);
                @endphp
                @if ($alsoRecorded)
                    <p class="shop-hint">The till also recorded: @foreach ($alsoRecorded as $label => $amount){{ $label }} €{{ number_format($amount, 2) }}@if (! $loop->last), @endif @endforeach. None of it is cash or card.</p>
                @endif
                @if (! empty($state['legacy']))
                    <p class="shop-hint">Old-system fields on this count: @foreach ($state['legacy'] as $label => $amount){{ str_replace('_', ' ', $label) }} €{{ number_format($amount, 2) }}@if (! $loop->last), @endif @endforeach.</p>
                @endif
            </section>
        </div>
    </div>

    <div class="shop-actions" x-show="! saved">
        <div class="shop-actions__summary">
            <strong x-text="'Bag ' + money(bag)"></strong>
            <span x-text="anythingCounted ? 'Cash variance ' + money(variance, true) : 'Count the drawer to begin'"></span>
            <span class="shop-hint" x-show="saveBlocker === 'note'" x-cloak>The variance is over the limit, so the count needs a note before it can be saved.</span>
        </div>
        <button class="shop-btn shop-btn--primary shop-btn--lg shop-btn--fit" type="button" :disabled="! canSave" @click="save()">
            <x-shop.icon name="check" /><span x-text="saveLabel"></span>
        </button>
    </div>

    {{-- Numpad --}}
    <template x-if="pad">
        <div class="shop-sheet-backdrop shop-count-sheet" @click.self="cancelPad()">
            <div class="shop-sheet" role="dialog" aria-modal="true" :aria-label="pad.label">
                <div class="shop-sheet__head">
                    <h2 class="shop-subtitle" x-text="pad.label"></h2>
                    <button class="shop-iconbtn shop-iconbtn--ghost" type="button" aria-label="Close" @click="cancelPad()"><x-shop.icon name="x" /></button>
                </div>
                <div class="shop-sheet__body">
                    <div class="shop-numpad">
                        <div class="shop-numpad__display">
                            <span class="shop-label" x-text="pad.typed === '' ? 'Now ' + (pad.decimals ? money(pad.current) : pad.current) : 'New'"></span>
                            <span x-text="pad.typed === '' ? '' : (pad.decimals ? '€' : '') + pad.typed"></span>
                        </div>
                        @foreach ([1, 2, 3, 4, 5, 6, 7, 8, 9] as $digit)
                            <button class="shop-key" type="button" @click="key('{{ $digit }}')">{{ $digit }}</button>
                        @endforeach
                        <button class="shop-key shop-key--fn" type="button" aria-label="Decimal point" :disabled="pad.decimals === 0" @click="key('.')">.</button>
                        <button class="shop-key" type="button" @click="key('0')">0</button>
                        <button class="shop-key shop-key--fn" type="button" aria-label="Delete" @click="key('backspace')"><x-shop.icon name="backspace" size="lg" /></button>
                    </div>
                </div>
                <div class="shop-sheet__foot">
                    <button class="shop-btn shop-btn--secondary" type="button" @click="cancelPad()">Cancel</button>
                    <button class="shop-btn shop-btn--primary" type="button" @click="confirmPad()">OK</button>
                </div>
            </div>
        </div>
    </template>
@endif

<div class="shop-toasts" role="status" x-show="toast" x-cloak>
    <div class="shop-toast" :class="toast && 'shop-toast--' + toast.tone">
        <span class="shop-toast__icon" x-show="! toast || toast.tone === 'ok'"><x-shop.icon name="check" size="sm" /></span>
        <span class="shop-toast__icon" x-show="toast && toast.tone !== 'ok'" x-cloak><x-shop.icon name="alert" size="sm" /></span>
        <span class="shop-toast__text" x-text="toast?.text"></span>
    </div>
</div>
