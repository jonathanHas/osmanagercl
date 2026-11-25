@php
    $number = $invoice['invoice_number'] ?? $delivery->delivery_number;
    $date = ! empty($invoice['invoice_date']) ? \Carbon\Carbon::parse($invoice['invoice_date'])->format('j M') : null;
    $trim = fn ($n) => rtrim(rtrim(number_format((float) $n, 2), '0'), '.');
@endphp

<x-shop-layout title="Dynamis" :subtitle="$number.($date ? ' · '.$date : '')" :back="route('shop.fv.pricing')">
    {{-- The lines and URLs arrive as data-* attributes; the behaviour is
         resources/js/shop/dynamis-pricing.js. Every write goes to the office
         page's endpoints (deliveries.dynamis-pricing.*). --}}
    <main class="shop-page shop-page--narrow" x-data="shopDynamisPricing()"
          data-lines="{{ json_encode($lines) }}"
          data-settings="{{ json_encode($settings) }}"
          data-line-url="{{ url('/deliveries/'.$delivery->id.'/dynamis-pricing/lines') }}"
          data-search-url="{{ route('api.products.search') }}">

        @foreach ($warnings as $warning)
            <p class="shop-dyn__warn">{{ $warning }}</p>
        @endforeach

        <div class="shop-seg shop-seg--block" role="radiogroup" aria-label="Show">
            <template x-for="f in filters" :key="f.key">
                <button class="shop-seg__opt" type="button" :aria-pressed="filter === f.key" x-on:click="setFilter(f.key)" x-text="f.label + ' ' + f.count"></button>
            </template>
        </div>

        <div class="shop-list" x-show="visibleLines.length" x-cloak>
            <template x-for="line in visibleLines" :key="line.id">
                <article class="shop-dyn" :class="{ 'is-open': open === line.id, 'is-skipped': line.skipped }" :id="'line-' + line.id">
                    <button class="shop-row shop-dyn__head" type="button" :aria-expanded="open === line.id" x-on:click="toggle(line)">
                        <div class="shop-row__main">
                            <span class="shop-row__title" x-text="line.description"></span>
                            <span class="shop-row__meta" x-text="headText(line)"></span>
                        </div>
                        <div class="shop-row__aside">
                            <span class="shop-pill" :class="'shop-pill--' + status(line)[0]" x-text="status(line)[1]"></span>
                            <span class="shop-row__meta" x-text="guideText(line)"></span>
                        </div>
                        <x-shop.icon name="chevron-right" class="shop-row__chev" x-bind:class="{ 'is-open': open === line.id }" />
                    </button>

                    {{-- Opens in flow under its own row, one line at a time. --}}
                    <template x-if="open === line.id">
                        <div class="shop-dyn__body">
                            <p class="shop-meta shop-num" x-text="invoiceText(line)"></p>

                            {{-- Till product --}}
                            <section class="shop-dyn__block">
                                <h3 class="shop-label">Till product</h3>

                                <template x-if="line.product">
                                    <div class="shop-stack shop-stack--tight">
                                        <div class="shop-between">
                                            <span class="shop-dyn__name" x-text="line.product.name"></span>
                                            <span class="shop-pill" :class="line.product.on_till ? 'shop-pill--ok' : 'shop-pill--warn'" x-text="line.product.on_till ? 'On till' : 'Not on till'"></span>
                                        </div>
                                        <span class="shop-meta shop-num" x-text="nowText(line)"></span>

                                        <p class="shop-meta" x-show="countryState(line) === 'same'" x-text="'Origin ' + (line.product.country_name || '')"></p>
                                        <p class="shop-dyn__warn" x-show="countryState(line) === 'differs'" x-text="countryText(line)"></p>
                                        <p class="shop-meta" x-show="countryState(line) === 'unknown'" x-text="countryText(line)"></p>
                                        <p class="shop-meta" x-show="otherOrigins(line)" x-text="'Also from ' + otherOrigins(line) + ' on this invoice'"></p>

                                        <div class="shop-dyn__buttons">
                                            <button class="shop-btn shop-btn--secondary" type="button" x-show="countryState(line) === 'differs'" :disabled="line.busy" x-on:click="useInvoiceCountry(line)" x-text="'Change to ' + (line.origin_country ? line.origin_country.name : '')"></button>
                                            <button class="shop-btn shop-btn--secondary" type="button" x-show="! line.product.on_till" :disabled="line.busy" x-on:click="showOnTill(line)">Show on till</button>
                                            <button class="shop-btn shop-btn--ghost" type="button" x-on:click="openPicker(line)">Change product</button>
                                        </div>
                                    </div>
                                </template>

                                <template x-if="! line.product && line.suggestion">
                                    <div class="shop-stack shop-stack--tight">
                                        <div class="shop-between">
                                            <span class="shop-dyn__name"><small>Looks like</small> <span x-text="line.suggestion.name"></span></span>
                                            <span class="shop-pill" :class="line.suggestion.on_till ? 'shop-pill--ok' : 'shop-pill--warn'" x-text="line.suggestion.on_till ? 'On till' : 'Not on till'"></span>
                                        </div>
                                        <span class="shop-meta shop-num" x-text="'Now ' + money(line.suggestion.till_price)"></span>
                                        <div class="shop-dyn__buttons">
                                            <button class="shop-btn shop-btn--primary" type="button" :disabled="line.busy" x-on:click="link(line, line.suggestion.id)">Yes, use this</button>
                                            <button class="shop-btn shop-btn--secondary" type="button" x-on:click="openPicker(line)">Choose another</button>
                                        </div>
                                    </div>
                                </template>

                                <template x-if="! line.product && ! line.suggestion">
                                    <div class="shop-stack shop-stack--tight">
                                        <p class="shop-meta">No till product is linked to this line yet.</p>
                                        <div class="shop-dyn__buttons">
                                            <button class="shop-btn shop-btn--secondary" type="button" x-on:click="openPicker(line)">Choose till product</button>
                                        </div>
                                    </div>
                                </template>
                            </section>

                            {{-- Per kg / each, and what is in a box --}}
                            <section class="shop-dyn__block">
                                <h3 class="shop-label">Sell as</h3>
                                <div class="shop-seg shop-seg--block" role="radiogroup" aria-label="Sell as">
                                    <button class="shop-seg__opt" type="button" :aria-pressed="line.mode === 'per_kg' && ! line.skipped" x-on:click="setMode(line, 'per_kg')">Per kg</button>
                                    <button class="shop-seg__opt" type="button" :aria-pressed="line.mode === 'each' && ! line.skipped" x-on:click="setMode(line, 'each')">Each</button>
                                </div>

                                <div class="shop-field" x-show="line.mode === 'each' && line.unit !== 'PCE'">
                                    <label class="shop-field__label" :for="'count-' + line.id">How many in a box</label>
                                    <input class="shop-input shop-num" :class="{ 'is-missing': ! line.units_per_box }" type="number" inputmode="numeric" min="1" step="1" placeholder="Count the box"
                                           :id="'count-' + line.id" x-model.number="line.units_per_box" x-on:input="line.count_source = null">
                                    <span class="shop-hint" x-show="countNote(line)" x-text="countNote(line)"></span>
                                </div>

                                <div class="shop-field" x-show="line.mode === 'per_kg' && line.unit !== 'KG'">
                                    <label class="shop-field__label" :for="'kg-' + line.id">Kg in a box</label>
                                    <input class="shop-input shop-num" :class="{ 'is-missing': ! line.kg_per_box }" type="number" inputmode="decimal" min="0.1" step="0.1" placeholder="Weigh the box"
                                           :id="'kg-' + line.id" x-model.number="line.kg_per_box">
                                </div>

                                <p class="shop-dyn__warn" x-show="unitClash(line)" x-text="unitClash(line)"></p>
                            </section>

                            {{-- The price --}}
                            <section class="shop-dyn__block">
                                <label class="shop-label" :for="'price-' + line.id" x-text="'New price ' + per(line)"></label>
                                <div class="shop-dyn__price">
                                    <span class="shop-dyn__cur">€</span>
                                    <input class="shop-input shop-num" type="number" inputmode="decimal" min="0" step="0.01"
                                           :id="'price-' + line.id" :value="priceValue(line)" x-on:input="typePrice(line, $event.target.value)">
                                </div>
                                <div class="shop-dyn__buttons">
                                    <button class="shop-btn shop-btn--secondary" type="button" x-show="suggested(line) !== null" :aria-pressed="line.priceSource === 'guide'" x-on:click="line.priceSource = 'guide'" x-text="guideLabel(line)"></button>
                                    <button class="shop-btn shop-btn--ghost" type="button" x-show="tillPrice(line) !== null && line.priceSource !== 'till'" x-on:click="line.priceSource = 'till'" x-text="keepLabel(line)"></button>
                                </div>
                                <p class="shop-hint" x-show="suggested(line) === null" x-text="line.mode === 'each' ? 'Count the box to get a guide price.' : 'Enter the box weight to get a guide price.'"></p>
                                <p class="shop-meta shop-num" x-show="changeText(line)" x-text="changeText(line)"></p>
                                <p class="shop-meta shop-num" :class="{ 'shop-dyn__bad': belowCost(line) }" x-show="costText(line)" x-text="costText(line)"></p>
                            </section>

                            <p class="shop-dyn__ok shop-num" x-show="line.applied_price !== null && ! line.skipped" x-text="appliedText(line)"></p>
                            <p class="shop-dyn__warn shop-dyn__bad" x-show="line.error" x-text="line.error"></p>
                            <p class="shop-hint" x-show="! line.product && ! line.skipped">Choose the till product before applying a price.</p>

                            <div class="shop-dyn__actions">
                                <button class="shop-btn shop-btn--secondary" type="button" :disabled="line.busy" x-on:click="skip(line, ! line.skipped)" x-text="line.skipped ? 'Undo skip' : 'Skip'"></button>
                                <button class="shop-btn shop-btn--primary" type="button" :disabled="! canApply(line) || line.busy" x-on:click="apply(line)">
                                    <x-shop.icon name="check" /><span x-text="applyLabel(line)"></span>
                                </button>
                            </div>
                        </div>
                    </template>
                </article>
            </template>
        </div>

        <div class="shop-empty" x-show="! visibleLines.length" x-cloak>
            <div class="shop-empty__icon"><x-shop.icon name="check" size="xl" /></div>
            <p class="shop-empty__title" x-text="filter === 'todo' ? 'Every line is priced or skipped' : 'Nothing here'"></p>
            <p class="shop-empty__text" x-show="filter === 'todo'">Price labels for the changes are in the label queue.</p>
        </div>

        <p class="shop-hint">
            Guide price = cost × {{ $trim($settings['transport']) }} transport × {{ $trim($settings['markup']) }} markup.
            @if ($barcodedCount)
                {{ $barcodedCount }} barcoded {{ $barcodedCount === 1 ? 'line is' : 'lines are' }} not priced here.
            @endif
            New products and the transport and markup figures are on the
            <a class="shop-link" href="{{ route('deliveries.dynamis-pricing.show', $delivery) }}">office pricing page</a>.
        </p>

        {{-- One product picker for whichever line asked for it --}}
        <div class="shop-sheet-backdrop" x-show="picking" x-cloak x-on:click.self="closePicker()" x-on:keydown.escape.window="closePicker()">
            <section class="shop-sheet" role="dialog" aria-modal="true" aria-label="Choose the till product">
                <div class="shop-sheet__head">
                    <div class="shop-stack shop-stack--tight">
                        <h2 class="shop-subtitle">Choose the till product</h2>
                        <p class="shop-meta" x-text="picking?.description"></p>
                    </div>
                    <button class="shop-iconbtn shop-iconbtn--ghost" type="button" aria-label="Close" x-on:click="closePicker()">
                        <x-shop.icon name="x" />
                    </button>
                </div>

                <div class="shop-sheet__body">
                    <div class="shop-search">
                        <x-shop.icon name="search" />
                        <input class="shop-input" type="search" placeholder="Search by name or code" autocomplete="off" x-ref="pickerSearch"
                               x-model="query" x-on:input.debounce.250ms="search()" x-on:keydown.enter.prevent="search()">
                    </div>

                    <div class="shop-stack shop-stack--tight" x-show="searchError" x-cloak>
                        <p class="shop-meta" x-text="searchMessage"></p>
                        <button class="shop-btn shop-btn--secondary" type="button" x-show="searchError === 'failed'" x-on:click="search()">Try again</button>
                    </div>
                    <p class="shop-meta" x-show="noMatches" x-cloak>No products match</p>

                    <div class="shop-list shop-dyn-results" x-show="results.length" x-cloak>
                        <template x-for="p in results" :key="p.id">
                            <button class="shop-row" type="button" x-on:click="pickResult(p)">
                                <div class="shop-row__main">
                                    <span class="shop-row__title" x-text="p.name"></span>
                                    <span class="shop-row__meta" x-text="money(p.price_with_vat) + ' · ' + p.code"></span>
                                </div>
                                <div class="shop-row__aside">
                                    <span class="shop-pill" :class="p.on_till ? 'shop-pill--ok' : 'shop-pill--warn'" x-text="p.on_till ? 'On till' : 'Not on till'"></span>
                                </div>
                            </button>
                        </template>
                    </div>

                    <p class="shop-hint">The choice is remembered for this Dynamis code on future invoices.</p>
                    <button class="shop-btn shop-btn--ghost" type="button" x-show="picking?.product" x-on:click="unlink()" x-text="'Remove the link to ' + (picking?.product?.name ?? '')"></button>
                </div>
            </section>
        </div>

        <div class="shop-toasts" role="status" x-show="toast" x-cloak>
            <div class="shop-toast" :class="toast && 'shop-toast--' + toast.tone">
                <span class="shop-toast__icon" x-show="! toast || toast.tone === 'ok'"><x-shop.icon name="check" size="sm" /></span>
                <span class="shop-toast__icon" x-show="toast && toast.tone !== 'ok'" x-cloak><x-shop.icon name="alert" size="sm" /></span>
                <span class="shop-toast__text" x-text="toast?.text"></span>
            </div>
        </div>
    </main>
</x-shop-layout>
