@php
    // Second top-bar line: the session and when it arrived. "today" reads better
    // on the floor than a date that is almost always today.
    $when = \Carbon\Carbon::parse($session['date']);
    // The first 8 characters, as the old single-line title used: a real session id
    // is a 36-character UUID, and the whole thing pushes the date out of a phone's
    // top bar entirely (measured at 390 px: 97 px of 396 px shown).
    $subtitle = substr($session['id'], 0, 8).' · '.($when->isToday() ? 'today' : $when->format('D j M'));
@endphp
<x-shop-layout :title="$session['supplier']" :subtitle="$subtitle" :back="route('shop.deliveries')">
    <main class="shop-page" x-data="shopDeliveryScan()"
          data-items-url="{{ route('delivery-legacy.items') }}"
          data-scan-url="{{ route('delivery-legacy.scan-increment') }}"
          data-update-url="{{ route('delivery-legacy.update-quantity') }}"
          data-del-id="{{ $session['id'] }}"
          data-supplier-id="{{ $session['supplierId'] }}"
          @scan="onScan($event.detail.code)"
          @scan-empty="pending && commit()">
        <div class="shop-split">
            <div class="shop-stack">
                @if ($session['completed'])
                    <section class="shop-card shop-card--flat">
                        <h2 class="shop-label">Completed</h2>
                        <p class="shop-meta">This delivery is completed. Corrections are made on the office page.</p>
                    </section>
                @else
                    <x-shop.scan-input inline placeholder="Scan item" hint="Ready — scan the next item" />
                @endif

                <section class="shop-card" x-show="pending" x-cloak x-ref="prompt">
                    <div class="shop-between">
                        <div class="shop-stack shop-stack--tight">
                            <h2 class="shop-subtitle" x-text="pending?.product.name"></h2>
                            <span class="shop-row__meta shop-code" x-text="pending ? [pending.product.barcode, pending.product.categoryName].filter(Boolean).join(' · ') : ''"></span>
                        </div>
                        <button class="shop-iconbtn shop-iconbtn--ghost" type="button" aria-label="Cancel" @click="cancelPending()">
                            <x-shop.icon name="x" />
                        </button>
                    </div>

                    <div class="shop-facts shop-facts--2">
                        <div class="shop-fact">
                            <span class="shop-label">Scanned so far</span>
                            <span class="shop-fact__value" x-text="pending?.scannedSoFar"></span>
                        </div>
                        <div class="shop-fact">
                            <span class="shop-label">Invoice</span>
                            <span class="shop-fact__value" x-text="pending?.expected ?? '—'"></span>
                        </div>
                        <div class="shop-fact">
                            <span class="shop-label">In stock</span>
                            <span class="shop-fact__value" x-text="pending?.product.currentStock ?? '—'"></span>
                        </div>
                        <div class="shop-fact" x-show="pending?.scanType === 'case'" x-cloak>
                            <span class="shop-label">Outer</span>
                            <span class="shop-pill shop-pill--sage" x-text="pending ? 'Case of ' + pending.caseUnits : ''"></span>
                        </div>
                    </div>

                    <div class="shop-stack shop-stack--tight">
                        <span class="shop-label">Quantity to add</span>
                        <div class="shop-stepper">
                            <button class="shop-iconbtn shop-iconbtn--lg" type="button" aria-label="One fewer" @click="bump(-1)"><x-shop.icon name="minus" size="lg" /></button>
                            <output class="shop-stepper__value" x-text="pending?.qty"></output>
                            <button class="shop-iconbtn shop-iconbtn--lg" type="button" aria-label="One more" @click="bump(1)"><x-shop.icon name="plus" size="lg" /></button>
                        </div>
                    </div>

                    <button class="shop-btn shop-btn--primary shop-btn--lg shop-btn--block" type="button" :disabled="busy" @click="commit()">
                        <x-shop.icon name="check" />
                        <span x-text="addLabel"></span>
                    </button>
                </section>

                <p class="shop-notice" x-show="! hasInvoice" x-cloak>
                    <x-shop.icon name="info" size="sm" />No invoice lines, so quantities can't be checked.
                </p>

                <div class="shop-status" x-show="hasInvoice" x-cloak>
                    <div class="shop-progress-meta">
                        <span><strong x-text="progress.checked"></strong> of <span x-text="progress.total"></span> lines checked</span>
                        <span x-text="progress.issues + (progress.issues === 1 ? ' issue' : ' issues')"></span>
                    </div>
                    <div class="shop-progress"
                         :class="{ 'is-done': progress.total && progress.checked === progress.total, 'is-issue': progress.issues > 0 }"
                         role="progressbar" aria-valuemin="0" aria-valuemax="100" :aria-valuenow="percent">
                        <span class="shop-progress__bar" :style="'width:' + percent + '%'"></span>
                    </div>
                    <span class="shop-pill shop-pill--sage" x-show="flag" x-cloak x-text="flag"></span>
                </div>

                {{-- A row is a button, so the stepper cannot live inside one. --}}
                <section class="shop-card" x-show="editing && ! pending" x-cloak x-ref="correct">
                    <div class="shop-between">
                        <h2 class="shop-subtitle">Correct quantity</h2>
                        <button class="shop-iconbtn shop-iconbtn--ghost" type="button" aria-label="Close" @click="editing = null">
                            <x-shop.icon name="x" />
                        </button>
                    </div>

                    <div class="shop-stack shop-stack--tight">
                        <span class="shop-row__title" x-text="editingRow?.name"></span>
                        <span class="shop-row__meta shop-code" x-text="editingRow?.code || editingRow?.barcode"></span>
                        <span class="shop-meta" x-show="editingRow?.stock !== null && editingRow" x-cloak
                              x-text="editingRow ? 'Stock ' + stockText(editingRow) : ''"></span>
                    </div>

                    <div class="shop-stepper">
                        <button class="shop-iconbtn shop-iconbtn--lg" type="button" aria-label="One fewer" :disabled="busy" @click="adjust(editingRow, -1)"><x-shop.icon name="minus" size="lg" /></button>
                        <output class="shop-stepper__value" x-text="editingRow?.scanned ?? 0"></output>
                        <button class="shop-iconbtn shop-iconbtn--lg" type="button" aria-label="One more" :disabled="busy" @click="adjust(editingRow, 1)"><x-shop.icon name="plus" size="lg" /></button>
                    </div>

                    <button class="shop-btn shop-btn--primary shop-btn--block" type="button" @click="editing = null">Done</button>
                </section>

                <section class="shop-empty" x-show="error" x-cloak>
                    <div class="shop-empty__icon"><x-shop.icon name="alert" size="xl" /></div>
                    <p class="shop-empty__title">Something went wrong</p>
                    <p class="shop-empty__text" x-text="error"></p>
                </section>
            </div>

            <div class="shop-stack">
                <div class="shop-between">
                    <h2 class="shop-group-title">Items <small x-text="rows.length"></small></h2>
                    {{-- The label names the order in force; tapping it flips. --}}
                    <button class="shop-btn shop-btn--ghost" type="button" @click="toggleSort()">
                        <x-shop.icon name="sort" size="sm" />
                        <span x-text="sort === 'new' ? 'New first' : 'Scanned first'"></span>
                    </button>
                </div>

                <div class="shop-list" x-show="rows.length" x-cloak>
                    <template x-for="row in sorted" :key="row.barcode">
                        <button class="shop-row shop-item" type="button"
                                :class="{ 'is-latest': row.barcode === latest, 'is-off': row.status === 'not_scanned' }"
                                :aria-pressed="editing === row.barcode" @click="edit(row)">
                            <span class="shop-row__main">
                                <span class="shop-row__title" x-text="row.name"></span>
                                <span class="shop-row__meta">
                                    <span class="shop-code" x-text="row.code || row.barcode"></span>
                                    <span class="shop-row__stock" x-show="row.stock !== null" x-cloak x-text="'Stock ' + stockText(row)"></span>
                                </span>
                            </span>
                            <span class="shop-row__aside">
                                <span class="shop-row__qty">
                                    <span x-text="row.scanned ?? 0"></span><small x-show="hasInvoice" x-text="expectedLabel(row)"></small>
                                </span>
                                <span class="shop-pill" x-show="pill(row)" x-cloak :class="pill(row)?.tone" x-text="pill(row)?.text"></span>
                            </span>
                        </button>
                    </template>
                </div>

                <div class="shop-empty" x-show="! rows.length" x-cloak>
                    <div class="shop-empty__icon"><x-shop.icon name="list-checks" size="xl" /></div>
                    <p class="shop-empty__title">Nothing scanned yet</p>
                    <p class="shop-empty__text">Scan the first item.</p>
                </div>
            </div>
        </div>

        <div class="shop-actions">
            {{-- The top bar's back button is the way back to Deliveries. --}}
            <a class="shop-btn shop-btn--primary shop-btn--lg" href="{{ route('shop.deliveries.summary', ['delID' => $session['id'], 'supplierID' => $session['supplierId']]) }}">
                Summary
                <span class="shop-btn__count" x-show="progress.issues > 0" x-cloak x-text="progress.issues"></span>
            </a>
        </div>

        <div class="shop-toasts" role="status" x-show="toast" x-cloak>
            <div class="shop-toast" :class="toast && 'shop-toast--' + toast.tone">
                {{-- x-shop.icon does not merge $attributes, so the directive goes on the styled span. --}}
                <span class="shop-toast__icon" x-show="! toast || toast.tone === 'ok'"><x-shop.icon name="check" size="sm" /></span>
                <span class="shop-toast__icon" x-show="toast && toast.tone !== 'ok'" x-cloak><x-shop.icon name="alert" size="sm" /></span>
                <span class="shop-toast__text" x-text="toast?.text"></span>
            </div>
        </div>
    </main>
</x-shop-layout>
