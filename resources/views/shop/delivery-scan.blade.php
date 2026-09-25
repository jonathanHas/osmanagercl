<x-shop-layout :title="$session['supplier'].' · '.substr($session['id'], 0, 8)" :back="route('shop.deliveries')">
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
                    <x-shop.scan-input hint="Ready — scan the next item" />
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

                <section class="shop-card">
                    <h2 class="shop-label">Progress</h2>
                    <div class="shop-progress-meta">
                        <span x-show="hasInvoice"><strong x-text="progress.checked"></strong> of <span x-text="progress.total"></span> items</span>
                        <span x-show="! hasInvoice" x-cloak>No invoice lines loaded</span>
                        <span x-text="progress.issues + ' issues'"></span>
                    </div>
                    <div class="shop-progress shop-progress--lg" x-show="hasInvoice"
                         :class="{ 'is-done': progress.total && progress.checked === progress.total, 'is-issue': progress.issues > 0 }"
                         role="progressbar" aria-valuemin="0" aria-valuemax="100" :aria-valuenow="percent">
                        <span class="shop-progress__bar" :style="'width:' + percent + '%'"></span>
                    </div>
                    <span class="shop-pill shop-pill--sage" x-show="flag" x-cloak x-text="flag"></span>
                </section>

                <section class="shop-empty" x-show="error" x-cloak>
                    <div class="shop-empty__icon"><x-shop.icon name="alert" size="xl" /></div>
                    <p class="shop-empty__title">Something went wrong</p>
                    <p class="shop-empty__text" x-text="error"></p>
                </section>
            </div>

            <div class="shop-stack">
                <div class="shop-between">
                    <h2 class="shop-subtitle">Items</h2>
                    <div class="shop-seg" role="radiogroup" aria-label="Sort">
                        <label class="shop-seg__opt">
                            <input type="radio" name="sort" value="new" :checked="sort === 'new'" @change="sort = 'new'">
                            New first
                        </label>
                        <label class="shop-seg__opt">
                            <input type="radio" name="sort" value="scanned" :checked="sort === 'scanned'" @change="sort = 'scanned'">
                            Scanned first
                        </label>
                    </div>
                </div>

                <p class="shop-meta" x-show="! hasInvoice && rows.length" x-cloak>
                    No invoice lines are loaded for this supplier; everything below was scanned but cannot be checked.
                </p>

                <div class="shop-list" x-show="rows.length" x-cloak>
                    <template x-for="row in sorted" :key="row.barcode">
                        <div class="shop-row" :class="{ 'is-latest': row.barcode === latest, 'is-off': row.status === 'not_scanned' }">
                            <div class="shop-row__main">
                                <span class="shop-row__meta shop-code" x-text="row.code || row.barcode"></span>
                                <span class="shop-row__title" x-text="row.name"></span>
                            </div>
                            <div class="shop-row__aside">
                                <span class="shop-row__qty">
                                    <span x-text="row.scanned ?? 0"></span><small x-text="expectedLabel(row)"></small>
                                </span>
                                <span class="shop-pill" :class="pill(row).tone" x-text="pill(row).text"></span>
                            </div>
                            <div class="shop-qty" x-show="editing === row.barcode" x-cloak>
                                <button class="shop-iconbtn" type="button" aria-label="One fewer" :disabled="busy" @click="adjust(row, -1)"><x-shop.icon name="minus" /></button>
                                <span class="shop-qty__value" x-text="row.scanned ?? 0"></span>
                                <button class="shop-iconbtn" type="button" aria-label="One more" :disabled="busy" @click="adjust(row, 1)"><x-shop.icon name="plus" /></button>
                            </div>
                            <button class="shop-iconbtn shop-iconbtn--ghost" type="button" aria-label="Correct quantity"
                                    :aria-pressed="editing === row.barcode" @click="edit(row)">
                                <x-shop.icon name="pencil" />
                            </button>
                        </div>
                    </template>
                </div>

                <div class="shop-empty" x-show="! rows.length" x-cloak>
                    <div class="shop-empty__icon"><x-shop.icon name="list-checks" size="xl" /></div>
                    <p class="shop-empty__title">No invoice lines</p>
                    <p class="shop-empty__text">No invoice lines for this supplier are loaded. Scans are still recorded.</p>
                </div>
            </div>
        </div>

        <div class="shop-actions">
            <a class="shop-btn shop-btn--secondary shop-btn--lg" href="{{ route('shop.deliveries') }}">Deliveries</a>
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
