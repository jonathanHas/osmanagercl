<x-shop-layout title="Print labels" :back="route('shop.home')">
    {{-- The list carries each label's print URL; the spool is read after render
         because it can take up to 15 s when the printer is down. --}}
    <main class="shop-page shop-page--narrow" x-data="shopZebra()"
          data-list-url="{{ route('labels.zebra-list') }}"
          data-queue-url="{{ route('labels.printer-queue') }}"
          data-cancel-url="{{ $canCancel ? route('labels.printer-cancel') : '' }}"
          @scan="onScan($event.detail.code)">
        @include('shop.partials.labels-nav', ['active' => 'zebra'])

        <div class="shop-printer" :class="printerClass" role="status">
            <span x-text="printerText"></span>
            @if ($canCancel)
                <button class="shop-btn shop-btn--ghost" type="button" x-show="printer?.ok && printer?.count > 0" x-cloak :disabled="busy" @click="cancelAll()"><x-shop.icon name="x" size="sm" />Cancel all</button>
            @endif
        </div>

        <x-shop.scan-input placeholder="Scan a product to print its label" hint="Ready — scan a product, or search below" />

        <div class="shop-search">
            <x-shop.icon name="search" />
            <input class="shop-input" type="search" placeholder="Search labels" aria-label="Search labels" x-model="query" autocomplete="off">
        </div>

        {{-- x-show, not x-if: print() closes the card, and a handler on an element
             an x-if removes loses $root (README rule 5). --}}
        <section class="shop-card" aria-label="Print label" x-show="selected" x-cloak>
            <div class="shop-between shop-between--top">
                <div class="shop-stack shop-stack--tight">
                    <h2 class="shop-subtitle" x-text="selected?.name"></h2>
                    <span class="shop-row__meta" x-text="selectedMeta"></span>
                </div>
                <button class="shop-iconbtn shop-iconbtn--ghost" type="button" aria-label="Close" @click="close()"><x-shop.icon name="x" /></button>
            </div>

            <div class="shop-inline" x-show="selected?.auto_print" x-cloak>
                <span class="shop-pill shop-pill--muted">Auto-prints on delivery</span>
            </div>

            <div class="shop-card shop-card--flat" x-show="selected?.mismatches?.price" x-cloak>
                <div class="shop-between">
                    <span class="shop-row__title" x-text="priceText"></span>
                    <span class="shop-pill shop-pill--warn">Price differs</span>
                </div>
                <label class="shop-switch"><input type="checkbox" x-model="useTillPrice"><span class="shop-switch__track"></span><span class="shop-switch__text"></span><span class="shop-switch__label">Print with till price</span></label>
                <p class="shop-hint">For this print only. The saved label is fixed in the office.</p>
            </div>

            {{-- Saves at once: the label's country text (when it has one) and the till record. --}}
            <div class="shop-field" x-show="selected?.kind === 'zebra'" x-cloak>
                <label class="shop-field__label" for="zebra-country">Country of origin</label>
                <select id="zebra-country" class="shop-input shop-select" x-model="countryId" :disabled="busy" @change="setCountry()">
                    <option value="">Choose…</option>
                    @foreach ($countries as $country)
                        <option value="{{ $country->id }}">{{ $country->name }}</option>
                    @endforeach
                </select>
                <p class="shop-notice" x-show="noCountryText(selected)" x-cloak><x-shop.icon name="help" size="sm" />Not printed on this label. Fix the label in the office.</p>
                <p class="shop-hint" x-show="countryHint" x-text="countryHint"></p>
            </div>

            <p class="shop-notice"><x-shop.icon name="info" size="sm" /><span x-text="stockText"></span></p>
            <p class="shop-notice" x-show="printer && ! printer.ok" x-cloak><x-shop.icon name="alert" size="sm" />Printer not reachable. Printing will fail until it is back.</p>

            <div class="shop-field">
                <span class="shop-field__label">Copies</span>
                <div class="shop-stepper">
                    <button class="shop-iconbtn shop-iconbtn--lg" type="button" aria-label="One fewer"
                            @click="bump(-1)"><x-shop.icon name="minus" size="lg" /></button>
                    <output class="shop-stepper__value" x-text="copies"></output>
                    <button class="shop-iconbtn shop-iconbtn--lg" type="button" aria-label="One more"
                            @click="bump(1)"><x-shop.icon name="plus" size="lg" /></button>
                </div>
            </div>

            <button class="shop-btn shop-btn--primary shop-btn--lg shop-btn--block" type="button" :disabled="busy" @click="print()">
                <x-shop.icon name="printer" />
                <span x-text="printLabel"></span>
            </button>
        </section>

        <template x-for="g in groups" :key="g.key">
            <section class="shop-stack shop-stack--tight" x-show="g.rows.length">
                <div class="shop-between">
                    <h2 class="shop-subtitle" x-text="g.title"></h2>
                    <span class="shop-meta" x-text="g.count"></span>
                </div>
                <p class="shop-hint" x-show="g.hint" x-text="g.hint"></p>
                <div class="shop-list">
                    <template x-for="r in g.rows" :key="r.kind + r.id">
                        <button class="shop-row" type="button" :class="{ 'is-latest': isSelected(r) }" @click="open(r)">
                            <span class="shop-row__main">
                                <span class="shop-row__title" x-text="r.name"></span>
                                <span class="shop-row__meta shop-row__meta--icon">
                                    <x-shop.icon name="help" size="sm" x-show="noCountryText(r)" />
                                    <span class="shop-sr-only" x-show="noCountryText(r)">Country not printed on this label</span>
                                    <span x-text="rowMeta(r)"></span>
                                </span>
                            </span>
                            <span class="shop-pill" :class="'shop-pill--' + pill(r)?.tone" x-show="pill(r)" x-text="pill(r)?.text"></span>
                        </button>
                    </template>
                </div>
            </section>
        </template>

        {{-- Translations run to hundreds; listed only once a search narrows them. --}}
        <section class="shop-stack shop-stack--tight" x-show="! query.trim() && translatedCount" x-cloak>
            <div class="shop-between">
                <h2 class="shop-subtitle">Translated labels</h2>
                <span class="shop-meta" x-text="translatedCount"></span>
            </div>
            <div class="shop-list">
                <div class="shop-row">
                    <span class="shop-row__lead"><x-shop.icon name="search" /></span>
                    <div class="shop-row__main">
                        <span class="shop-row__title">Scan or search to find one</span>
                        <span class="shop-row__meta" x-text="translatedCount + ' translated labels'"></span>
                    </div>
                </div>
            </div>
        </section>

        <div class="shop-empty" x-show="! loading && query.trim() && shown === 0 && ! error" x-cloak>
            <div class="shop-empty__icon"><x-shop.icon name="barcode" size="xl" /></div>
            <p class="shop-empty__title">No labels match</p>
            <p class="shop-empty__text">Try another name or code, or scan the product.</p>
        </div>

        <section class="shop-empty" x-show="error" x-cloak>
            <div class="shop-empty__icon"><x-shop.icon name="alert" size="xl" /></div>
            <p class="shop-empty__title">Something went wrong</p>
            <p class="shop-empty__text" x-text="error"></p>
        </section>

        <div class="shop-toasts" role="status" x-show="toast" x-cloak>
            <div class="shop-toast" :class="toast && 'shop-toast--' + toast.tone">
                <span class="shop-toast__icon" x-show="! toast || toast.tone === 'ok'"><x-shop.icon name="check" size="sm" /></span>
                <span class="shop-toast__icon" x-show="toast && toast.tone !== 'ok'" x-cloak><x-shop.icon name="alert" size="sm" /></span>
                <span class="shop-toast__text" x-text="toast?.text"></span>
            </div>
        </div>
    </main>
</x-shop-layout>
