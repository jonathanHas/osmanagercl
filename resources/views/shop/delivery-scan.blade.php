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
          data-outer-url="{{ route('delivery-legacy.save-outer-barcode') }}"
          data-del-id="{{ $session['id'] }}"
          data-supplier-id="{{ $session['supplierId'] }}"
          data-search-url="{{ route('api.products.search') }}"
          @scan="onScan($event.detail.code)"
          @scan-empty="pending && commit()">
        <div class="shop-split">
            <div class="shop-stack">
                {{-- First in the stack, above the scan field: on a phone the camera
                     block is ~268 px tall, and with the prompt below it "Add" landed
                     off the bottom of the screen on every single item. --}}
                <section class="shop-card" x-show="pending" x-cloak x-ref="prompt">
                    <div class="shop-between">
                        <x-shop.product-thumb expr="pendingProduct" />
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
                            <span class="shop-fact__value" x-text="pending ? stockText(pending.scannedSoFar) : ''"></span>
                        </div>
                        <div class="shop-fact">
                            <span class="shop-label">Invoice</span>
                            <span class="shop-fact__value" x-text="pending?.expected ?? '—'"></span>
                        </div>
                        <div class="shop-fact">
                            <span class="shop-label">In stock</span>
                            <span class="shop-fact__value" x-text="pending?.product.currentStock == null ? '—' : stockText(pending.product.currentStock)"></span>
                        </div>
                        <div class="shop-fact" x-show="pending?.scanType === 'case'" x-cloak>
                            <span class="shop-label">Outer</span>
                            <span class="shop-pill shop-pill--sage" x-text="pending ? 'Case of ' + pending.caseUnits : ''"></span>
                        </div>
                    </div>

                    <div class="shop-stack shop-stack--tight" x-show="pending?.typed == null">
                        <span class="shop-label">Quantity to add</span>
                        <div class="shop-stepper">
                            <button class="shop-iconbtn shop-iconbtn--lg" type="button" aria-label="One fewer" @click="bump(-1)"><x-shop.icon name="minus" size="lg" /></button>
                            {{-- Tap the number to type it: a weight, or a count too big to
                                 step to. A case count stays on the stepper. --}}
                            <output class="shop-stepper__value" x-show="pending?.scanType === 'case'" x-text="pending?.qty"></output>
                            <button class="shop-stepper__value" type="button" aria-label="Type the quantity" x-show="pending?.scanType !== 'case'" x-on:click="typeQuantity()" x-text="pending?.qty"></button>
                            <button class="shop-iconbtn shop-iconbtn--lg" type="button" aria-label="One more" @click="bump(1)"><x-shop.icon name="plus" size="lg" /></button>
                        </div>
                    </div>

                    {{-- x-show with a null-safe getter/setter, not x-model="pending.typed"
                         (throws while pending is null) and not x-if: commit() empties
                         pending, and a handler on an element x-if has removed loses
                         $root for the rest of its run (the list reset then fails). --}}
                    <div class="shop-field" x-show="pending?.typed != null" x-cloak>
                        <label class="shop-field__label" for="delivery-qty">Quantity or weight</label>
                        <input class="shop-input" id="delivery-qty" type="text" inputmode="decimal" autocomplete="off" enterkeyhint="done"
                               x-ref="qty" x-model="typedQty" x-on:keydown.enter.prevent="commit()">
                    </div>

                    <button class="shop-btn shop-btn--primary shop-btn--lg shop-btn--block" type="button" :disabled="busy || qtyValue === null" @click="commit()">
                        <x-shop.icon name="check" />
                        <span x-text="addLabel"></span>
                    </button>
                </section>

                {{-- A code no product has, and the step that links it to a product as its
                     outer (case) barcode, as the office match page offers. Above the scan
                     field for the same reason the prompt is: on a phone the camera block
                     pushes anything under the field off screen. --}}
                <section class="shop-card" x-show="unknown" x-cloak x-ref="unknown" :class="{ 'shop-card--linking': unknown?.linking }">
                    <div class="shop-between">
                        <div class="shop-stack shop-stack--tight">
                            <h2 class="shop-subtitle" x-text="! unknown?.linking ? 'Not found' : (unknown?.candidate ? 'Link to this product?' : 'Link outer barcode')"></h2>
                            <span class="shop-row__meta shop-code" x-text="unknown?.code"></span>
                        </div>
                        <button class="shop-iconbtn shop-iconbtn--ghost" type="button" aria-label="Dismiss" @click="dismissUnknown()">
                            <x-shop.icon name="x" />
                        </button>
                    </div>

                    {{-- Not found: offer the link. --}}
                    <p class="shop-meta" x-show="! unknown?.linking">
                        No {{ $session['supplier'] ?? 'supplier' }} product has this barcode. If it is the barcode on a case, link it to the product inside.
                    </p>

                    {{-- Waiting for the unit barcode. Amber (shop-card--linking), and the
                         scan field with it, so a scan here cannot be mistaken for
                         counting the next item. --}}
                    <p class="shop-meta" x-show="unknown?.linking && ! unknown?.candidate" x-cloak>
                        Scan the barcode on one item from the case — not the next delivery item. Nothing is counted until you confirm.{{ $canSearch ? ' Or find it by name.' : '' }}
                    </p>

                    {{-- The product the scan found: nothing is saved until "Yes, link it". --}}
                    <div class="shop-inline" x-show="unknown?.candidate" x-cloak>
                        <x-shop.product-thumb expr="candidateProduct" />
                        <div class="shop-stack shop-stack--tight">
                            <span class="shop-row__title" x-text="candidateProduct?.name"></span>
                            <span class="shop-row__meta shop-code" x-text="candidateProduct?.barcode"></span>
                        </div>
                    </div>
                    <p class="shop-meta" x-show="unknown?.candidate" x-cloak x-text="unknown ? unknown.code + ' becomes the case barcode of this product.' : ''"></p>

                    <p class="shop-notice" x-show="unknown?.error" x-cloak>
                        <x-shop.icon name="alert" size="sm" /><span x-text="unknown?.error"></span>
                    </p>

                    <button class="shop-btn shop-btn--primary shop-btn--block" type="button" x-show="! unknown?.linking" :disabled="busy" @click="startLink()">
                        <x-shop.icon name="package" />Link as outer barcode
                    </button>
                    <button class="shop-btn shop-btn--ghost shop-btn--block" type="button" x-show="unknown?.linking && ! unknown?.candidate" x-cloak @click="dismissUnknown()">
                        <x-shop.icon name="x" size="sm" />Cancel linking
                    </button>
                    <button class="shop-btn shop-btn--primary shop-btn--block" type="button" x-show="unknown?.candidate" x-cloak :disabled="busy" @click="confirmLink()">
                        <x-shop.icon name="check" />Yes, link it
                    </button>
                    <button class="shop-btn shop-btn--ghost shop-btn--block" type="button" x-show="unknown?.candidate" x-cloak :disabled="busy" @click="rejectCandidate()">
                        Not this one
                    </button>
                </section>

                @if ($session['completed'])
                    <section class="shop-card shop-card--flat">
                        <h2 class="shop-label">Completed</h2>
                        <p class="shop-meta">This delivery is completed. Corrections are made on the office page.</p>
                    </section>
                @else
                    {{-- display: contents (Shop rule 7): the wrapper only scopes the amber
                         styling of the field while an unknown code waits for its unit barcode. --}}
                    <div class="shop-contents" :class="{ 'is-linking': unknown?.linking }">
                        <x-shop.scan-input inline placeholder="Scan item" hint="Ready — scan the next item" />
                    </div>

                    @if ($canSearch)
                        {{-- Items without a barcode (the weekly cheese): pick by name,
                             then type the amount. In flow, not a popup (README rule 6). --}}
                        <button class="shop-btn shop-btn--ghost" type="button" x-show="! manual" x-cloak x-on:click="openManual()">
                            <x-shop.icon name="search" size="sm" />No barcode? Find by name
                        </button>

                        <section class="shop-card" x-show="manual && ! pending" x-cloak x-ref="manual">
                            <div class="shop-between">
                                <h2 class="shop-subtitle">Find by name</h2>
                                <button class="shop-iconbtn shop-iconbtn--ghost" type="button" aria-label="Close" x-on:click="closeManual()">
                                    <x-shop.icon name="x" />
                                </button>
                            </div>

                            <div class="shop-search">
                                <x-shop.icon name="search" />
                                <input class="shop-input" type="search" x-model="query" x-ref="filter" autocomplete="off" enterkeyhint="search"
                                       x-on:input.debounce.250ms="filterManual()" x-on:keydown.enter.prevent="pickFirst()"
                                       :placeholder="everywhere ? 'Search all products' : {{ Js::from('Filter '.($session['supplier'] ?? 'supplier').' products') }}">
                            </div>

                            {{-- A failed search must not read as "no products". --}}
                            <p class="shop-notice" x-show="searchError" x-cloak><x-shop.icon name="alert" size="sm" /><span x-text="searchMessage"></span></p>
                            <button class="shop-btn shop-btn--ghost" type="button" x-show="searchError === 'failed'" x-cloak x-on:click="search()">Try again</button>

                            <div class="shop-list" x-show="results.length" x-cloak>
                                <template x-for="p in results" :key="p.id">
                                    <button class="shop-row" type="button" x-on:click="pickResult(p)">
                                        <x-shop.product-thumb />
                                        <div class="shop-row__main">
                                            <span class="shop-row__title" x-text="p.name"></span>
                                            {{-- "Not stocked" is part of the meta line, not a pill: at
                                                 phone width a pill squeezed the name into a narrow column. --}}
                                            <span class="shop-row__meta shop-code" x-text="p.code + (p.stock_units !== null ? ' · Stock ' + stockText(p.stock_units) : '') + (! p.is_stocked ? ' · Not stocked' : '')"></span>
                                        </div>
                                    </button>
                                </template>
                            </div>

                            <p class="shop-meta" x-show="total > results.length" x-cloak>
                                Showing the first <span x-text="results.length"></span>, type to narrow
                            </p>
                            <p class="shop-meta" x-show="noMatches" x-cloak>No products match</p>

                            <button class="shop-btn shop-btn--ghost" type="button" x-show="query.trim() !== '' && ! everywhere" x-cloak x-on:click="searchEverywhere()">
                                <x-shop.icon name="search" size="sm" />Search all products
                            </button>
                        </section>
                    @endif
                @endif

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

                    <div class="shop-inline">
                        <x-shop.product-thumb expr="editingRow" />
                        <div class="shop-stack shop-stack--tight">
                            <span class="shop-row__title" x-text="editingRow?.name"></span>
                            <span class="shop-row__meta shop-code" x-text="editingRow?.code || editingRow?.barcode"></span>
                            <span class="shop-meta" x-show="editingRow?.stock !== null && editingRow" x-cloak
                                  x-text="editingRow ? 'Stock ' + stockText(editingRow.stock) : ''"></span>
                        </div>
                    </div>

                    <div class="shop-stepper" x-show="editTyped === null">
                        <button class="shop-iconbtn shop-iconbtn--lg" type="button" aria-label="One fewer" :disabled="busy" @click="adjust(editingRow, -1)"><x-shop.icon name="minus" size="lg" /></button>
                        <button class="shop-stepper__value" type="button" aria-label="Type the quantity" x-on:click="typeCorrection()" x-text="stockText(editingRow?.scanned)"></button>
                        <button class="shop-iconbtn shop-iconbtn--lg" type="button" aria-label="One more" :disabled="busy" @click="adjust(editingRow, 1)"><x-shop.icon name="plus" size="lg" /></button>
                    </div>

                    <div class="shop-field" x-show="editTyped !== null" x-cloak>
                        <label class="shop-field__label" for="delivery-edit-qty">Quantity or weight</label>
                        <input class="shop-input" id="delivery-edit-qty" type="text" inputmode="decimal" autocomplete="off" enterkeyhint="done"
                               x-ref="editQty" x-model="editTyped" x-on:keydown.enter.prevent="setCorrection()">
                    </div>
                    <button class="shop-btn shop-btn--primary shop-btn--block" type="button" x-show="editTyped !== null" x-cloak
                            :disabled="busy" x-on:click="setCorrection()">Set</button>

                    {{-- Steps back while a correction is typed, so Set is the one prominent button. --}}
                    <button class="shop-btn shop-btn--block" type="button" :class="editTyped !== null ? 'shop-btn--ghost' : 'shop-btn--primary'" @click="editing = null">Done</button>
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
                    <div class="shop-inline">
                        {{-- The label names the order in force; tapping it flips. --}}
                        <button class="shop-btn shop-btn--ghost" type="button" @click="toggleSort()">
                            <x-shop.icon name="sort" size="sm" />
                            <span x-text="sort === 'new' ? 'New first' : 'Scanned first'"></span>
                        </button>
                        {{-- Summary lives here rather than in a sticky bar: it is wanted
                             once, at the end, and the bar cost a strip of every screen.
                             Icon and count only: measured at 390 px, the word as well
                             pushed this header onto a third line (160 px). The full
                             label is on the button where the list ends. --}}
                        <a class="shop-btn shop-btn--ghost" title="Summary" href="{{ route('shop.deliveries.summary', ['delID' => $session['id'], 'supplierID' => $session['supplierId']]) }}">
                            <x-shop.icon name="list-checks" size="sm" /><span class="shop-sr-only">Summary</span><span class="shop-btn__count" x-show="progress.issues > 0" x-cloak x-text="progress.issues"></span>
                        </a>
                    </div>
                </div>

                <div class="shop-list" x-show="rows.length" x-cloak>
                    <template x-for="row in sorted" :key="row.barcode">
                        <button class="shop-row shop-item shop-item--pic" type="button"
                                :class="{ 'is-latest': row.barcode === latest, 'is-off': row.status === 'not_scanned' }"
                                :aria-pressed="editing === row.barcode" @click="edit(row)">
                            <x-shop.product-thumb expr="row" />
                            <span class="shop-row__main">
                                <span class="shop-row__title" x-text="row.name"></span>
                                <span class="shop-row__meta">
                                    <span class="shop-code" x-text="row.code || row.barcode"></span>
                                    <span class="shop-row__stock" x-show="row.stock !== null" x-cloak x-text="'Stock ' + stockText(row.stock)"></span>
                                </span>
                            </span>
                            <span class="shop-row__aside">
                                <span class="shop-row__qty">
                                    <span x-text="stockText(row.scanned)"></span><small x-show="hasInvoice" x-text="expectedLabel(row)"></small>
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

        {{-- Static, not sticky: where the list ends is where someone who has
             worked down it wants Summary. The header link covers everyone else.
             The top bar's back button is the way back to Deliveries. --}}
        <div class="shop-actions shop-actions--static">
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
