@php
    $money = fn ($n) => '€'.number_format((float) $n, 2);
    $gross = $invoice['gross'] ?? null;
    $shipping = (float) ($invoice['shipping'] ?? 0);
    $actualTransport = $gross && $shipping > 0 ? 1 + $shipping / $gross : null;
@endphp

<x-admin-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-baseline gap-4">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                Dynamis pricing
                <span class="font-normal text-gray-500 dark:text-gray-400">
                    {{ $invoice['invoice_number'] ?? $delivery->delivery_number }}
                    @if (! empty($invoice['invoice_date']))
                        &middot; {{ \Carbon\Carbon::parse($invoice['invoice_date'])->format('j M Y') }}
                    @endif
                </span>
            </h2>
            <a href="{{ route('deliveries.show', $delivery) }}" class="text-sm text-indigo-600 dark:text-indigo-400 hover:underline">Delivery docket</a>
            <a href="{{ route('fruit-veg.labels') }}" class="text-sm text-indigo-600 dark:text-indigo-400 hover:underline">Label queue</a>
        </div>
    </x-slot>

    <div class="py-6" x-data="dynamisPricing(@js([
            'lines' => $lines['produce'],
            'settings' => $settings,
            'urls' => [
                'settings' => route('deliveries.dynamis-pricing.settings', $delivery),
                'line' => url("/deliveries/{$delivery->id}/dynamis-pricing/lines"),
            ],
        ]))">
        <div class="max-w-[1600px] mx-auto px-4 sm:px-6 lg:px-8 space-y-4">

            @if (session('success'))
                <div class="rounded-md bg-green-50 dark:bg-green-900/30 border border-green-200 dark:border-green-800 px-4 py-3 text-sm text-green-800 dark:text-green-200">{{ session('success') }}</div>
            @endif
            @foreach ($warnings as $warning)
                <div class="rounded-md bg-amber-50 dark:bg-amber-900/30 border border-amber-300 dark:border-amber-700 px-4 py-3 text-sm text-amber-900 dark:text-amber-100">{{ $warning }}</div>
            @endforeach

            {{-- Invoice figures and the two multipliers --}}
            <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg p-4 flex flex-wrap items-end gap-x-8 gap-y-4">
                <div>
                    <div class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">Goods</div>
                    <div class="text-lg font-semibold text-gray-900 dark:text-gray-100 tabular-nums">{{ $gross !== null ? $money($gross) : 'not read' }}</div>
                </div>
                <div>
                    <div class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">Delivery charge</div>
                    <div class="text-lg font-semibold text-gray-900 dark:text-gray-100 tabular-nums">{{ $shipping > 0 ? $money($shipping) : 'None on this invoice' }}</div>
                </div>
                <div>
                    <div class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">Actual transport</div>
                    <div class="text-lg font-semibold text-gray-900 dark:text-gray-100 tabular-nums">
                        @if ($actualTransport)
                            &times;{{ number_format($actualTransport, 3) }}
                            <span class="text-sm font-normal text-gray-500 dark:text-gray-400">({{ number_format(($actualTransport - 1) * 100, 1) }}% of goods)</span>
                        @else
                            &ndash;
                        @endif
                    </div>
                </div>

                <div class="flex items-end gap-3 ml-auto">
                    <label class="block">
                        <span class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">Transport &times;</span>
                        <input type="number" step="0.01" min="1" max="3" x-model.number="settings.transport" x-on:change="saveSettings()"
                               class="mt-1 block w-24 rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100 shadow-sm text-sm tabular-nums">
                    </label>
                    <label class="block">
                        <span class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">Markup &times;</span>
                        <input type="number" step="0.01" min="1" max="5" x-model.number="settings.markup" x-on:change="saveSettings()"
                               class="mt-1 block w-24 rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100 shadow-sm text-sm tabular-nums">
                    </label>
                    <div class="text-xs text-gray-500 dark:text-gray-400 pb-2 max-w-[14rem]">
                        Guide RRP = cost &times; transport &times; markup. For this invoice only
                        (new invoices start at &times;{{ rtrim(rtrim(number_format($defaults['transport'], 2), '0'), '.') }} and &times;{{ rtrim(rtrim(number_format($defaults['markup'], 2), '0'), '.') }}).
                        <button type="button" class="text-indigo-600 dark:text-indigo-400 hover:underline" x-on:click="saveSettings(true)">Make these the default</button>
                        <span x-show="settingsNote" x-cloak x-text="settingsNote" class="text-green-700 dark:text-green-400"></span>
                    </div>
                </div>
            </div>

            {{-- Fruit & veg lines --}}
            <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg">
                <div class="px-4 py-3 flex flex-wrap items-center gap-4 border-b border-gray-200 dark:border-gray-700">
                    <h3 class="font-semibold text-gray-900 dark:text-gray-100">Fruit &amp; veg</h3>
                    <span class="text-sm text-gray-500 dark:text-gray-400">
                        <span x-text="counts.applied"></span> priced,
                        <span x-text="counts.skipped"></span> skipped,
                        <span x-text="counts.todo"></span> to do<template x-if="counts.unmatched"><span>, <span x-text="counts.unmatched"></span> with no till product</span></template>
                    </span>
                    <label class="inline-flex items-center gap-2 text-sm text-gray-600 dark:text-gray-300">
                        <input type="checkbox" x-model="hideDone" class="rounded border-gray-300 dark:border-gray-600 text-indigo-600"> Hide finished lines
                    </label>
                    <div class="ml-auto flex items-center gap-3">
                        <button type="button" x-on:click="tickReady()" class="text-sm text-indigo-600 dark:text-indigo-400 hover:underline">Tick all with a new price</button>
                        <button type="button" x-on:click="applyTicked()" :disabled="tickedLines.length === 0 || bulkBusy"
                                class="px-4 py-2 rounded-md text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 disabled:opacity-40 disabled:cursor-not-allowed">
                            Apply ticked (<span x-text="tickedLines.length"></span>)
                        </button>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-gray-50 dark:bg-gray-900/40 text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">
                            <tr>
                                <th class="px-3 py-2 w-8"></th>
                                <th class="px-3 py-2 text-left">Invoice line</th>
                                <th class="px-3 py-2 text-right">Guide RRP</th>
                                <th class="px-3 py-2 text-left">Till product</th>
                                <th class="px-3 py-2 text-left">Sell as</th>
                                <th class="px-3 py-2 text-left">New price</th>
                                <th class="px-3 py-2"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                            <template x-for="line in visibleLines" :key="line.id">
                                <tr class="align-top" :class="rowClass(line)">
                                    <td class="px-3 py-3">
                                        <input type="checkbox" x-model="line.ticked" :disabled="!canApply(line)"
                                               class="rounded border-gray-300 dark:border-gray-600 text-indigo-600 disabled:opacity-30">
                                    </td>

                                    <td class="px-3 py-3 max-w-xs">
                                        <div class="font-medium text-gray-900 dark:text-gray-100" x-text="line.description"></div>
                                        <div class="text-xs text-gray-500 dark:text-gray-400 tabular-nums">
                                            <span x-text="line.code"></span> &middot; <span x-text="line.origin"></span> &middot;
                                            <span x-text="packText(line)"></span> &middot;
                                            cost <span x-text="money(line.net_unit_price)"></span>/<span x-text="unitWord(line.unit)"></span>
                                        </div>
                                    </td>

                                    <td class="px-3 py-3 text-right tabular-nums whitespace-nowrap">
                                        <div class="font-medium text-gray-900 dark:text-gray-100">
                                            <span x-text="money(guide(line))"></span><span class="text-gray-500 dark:text-gray-400">/<span x-text="unitWord(line.unit)"></span></span>
                                        </div>
                                        <div class="text-xs text-gray-500 dark:text-gray-400" x-show="line.unit !== 'COL'">
                                            <span x-text="money(boxPrice(line))"></span> a box
                                        </div>
                                    </td>

                                    <td class="px-3 py-3 max-w-[16rem]">
                                        <template x-if="line.product">
                                            <div>
                                                <div class="text-gray-900 dark:text-gray-100"><span x-text="line.product.name"></span><span x-show="line.product.on_till" class="ml-1 inline-flex items-center px-1.5 py-0.5 rounded text-[11px] font-medium bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-200">On till</span><span x-show="!line.product.on_till" class="ml-1 inline-flex items-center px-1.5 py-0.5 rounded text-[11px] font-medium bg-amber-100 text-amber-900 dark:bg-amber-900/40 dark:text-amber-100" title="This product is hidden from the till buttons">Not on till</span><button type="button" x-show="!line.product.on_till" x-on:click="showOnTill(line)" :disabled="line.busy" class="ml-1 text-xs font-medium text-indigo-600 dark:text-indigo-400 hover:underline disabled:opacity-50">Show on till</button></div>
                                                <div class="text-xs text-gray-500 dark:text-gray-400 tabular-nums">
                                                    now <span x-text="money(line.product.till_price)"></span>
                                                    <span x-show="line.product.till_unit" x-text="line.product.till_unit === 'kg' ? 'per kg' : line.product.till_unit"></span>
                                                    &middot; <button type="button" class="text-indigo-600 dark:text-indigo-400 hover:underline" x-on:click="openPicker(line)">change</button>
                                                </div>
                                                <div class="mt-1 text-xs" x-show="line.origin">
                                                    <span x-show="countryState(line) === 'same'" class="text-gray-500 dark:text-gray-400">Origin <span x-text="line.product.country_name"></span></span>
                                                    <span x-show="countryState(line) === 'differs'" class="text-amber-700 dark:text-amber-400">
                                                        Till says <span class="font-medium" x-text="line.product.country_name || 'no country'"></span>, invoice says <span class="font-medium" x-text="line.origin_country ? line.origin_country.name : ''"></span>
                                                        <button type="button" x-on:click="useInvoiceCountry(line)" :disabled="line.busy"
                                                                class="mt-1 block px-2 py-1 rounded font-medium bg-amber-100 text-amber-900 hover:bg-amber-200 dark:bg-amber-900/40 dark:text-amber-100 disabled:opacity-50">Change to <span x-text="line.origin_country ? line.origin_country.name : ''"></span></button>
                                                    </span>
                                                    <span x-show="countryState(line) === 'unknown'" class="text-gray-500 dark:text-gray-400">
                                                        Till says <span x-text="line.product.country_name || 'no country'"></span>; invoice origin <span class="font-medium" x-text="line.origin"></span> is not in the country list
                                                    </span>
                                                    <span x-show="otherOrigins(line)" class="block text-gray-500 dark:text-gray-400">Also from <span x-text="otherOrigins(line)"></span> on this invoice</span>
                                                </div>
                                            </div>
                                        </template>
                                        <template x-if="!line.product && line.suggestion">
                                            <div>
                                                <div class="text-gray-700 dark:text-gray-300">
                                                    <span class="text-xs text-amber-700 dark:text-amber-400">Looks like</span>
                                                    <span x-text="line.suggestion.name"></span><span x-show="line.suggestion.on_till" class="ml-1 inline-flex items-center px-1.5 py-0.5 rounded text-[11px] font-medium bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-200">On till</span><span x-show="!line.suggestion.on_till" class="ml-1 inline-flex items-center px-1.5 py-0.5 rounded text-[11px] font-medium bg-amber-100 text-amber-900 dark:bg-amber-900/40 dark:text-amber-100" title="This product is hidden from the till buttons">Not on till</span>
                                                    <span class="text-xs text-gray-500 dark:text-gray-400 tabular-nums">(<span x-text="money(line.suggestion.till_price)"></span>)</span>
                                                </div>
                                                <div class="mt-1 flex gap-2">
                                                    <button type="button" x-on:click="link(line, line.suggestion.id)" :disabled="line.busy"
                                                            class="px-2 py-1 rounded text-xs font-medium bg-amber-100 text-amber-900 hover:bg-amber-200 dark:bg-amber-900/40 dark:text-amber-100">Yes, use this</button>
                                                    <button type="button" x-on:click="openPicker(line)"
                                                            class="px-2 py-1 rounded text-xs font-medium border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700">Choose another</button>
                                                </div>
                                            </div>
                                        </template>
                                        <template x-if="!line.product && !line.suggestion">
                                            <button type="button" x-on:click="openPicker(line)"
                                                    class="px-2 py-1 rounded text-xs font-medium border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700">Choose till product</button>
                                        </template>
                                    </td>

                                    <td class="px-3 py-3">
                                        <div class="inline-flex rounded-md shadow-sm" role="group">
                                            <button type="button" x-on:click="setMode(line, 'per_kg')" :class="modeClass(line, 'per_kg')"
                                                    class="px-3 py-1.5 text-xs font-medium border rounded-l-md">Per kg</button>
                                            <button type="button" x-on:click="setMode(line, 'each')" :class="modeClass(line, 'each')"
                                                    class="px-3 py-1.5 text-xs font-medium border-t border-b border-r rounded-r-md">Each</button>
                                        </div>

                                        <div class="mt-1.5 text-xs text-gray-600 dark:text-gray-300" x-show="line.mode === 'each' && line.unit !== 'PCE'">
                                            <label class="inline-flex items-center gap-1.5">
                                                <input type="number" min="1" step="1" x-model.number="line.units_per_box" x-on:input="line.count_source = null"
                                                       placeholder="count" :class="line.units_per_box ? '' : 'border-amber-500 ring-1 ring-amber-500'"
                                                       class="w-20 rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100 text-xs py-1 tabular-nums">
                                                in a box
                                            </label>
                                            <div class="text-amber-700 dark:text-amber-400" x-show="line.count_source === 'size'">From &ldquo;SIZE <span x-text="line.units_per_box"></span>&rdquo; on the invoice. Check the box.</div>
                                            <div class="text-gray-500 dark:text-gray-400" x-show="line.count_source === 'saved'">Count used last time. Check the box.</div>
                                            <div class="text-amber-700 dark:text-amber-400" x-show="!line.units_per_box">Count what is in a box.</div>
                                        </div>

                                        <div class="mt-1.5 text-xs text-gray-600 dark:text-gray-300" x-show="line.mode === 'per_kg' && line.unit !== 'KG'">
                                            <label class="inline-flex items-center gap-1.5">
                                                <input type="number" min="0.1" step="0.1" x-model.number="line.kg_per_box"
                                                       placeholder="kg" :class="line.kg_per_box ? '' : 'border-amber-500 ring-1 ring-amber-500'"
                                                       class="w-20 rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100 text-xs py-1 tabular-nums">
                                                kg in a box
                                            </label>
                                        </div>

                                        <div class="mt-1 text-xs text-amber-700 dark:text-amber-400" x-show="unitClash(line)" x-text="unitClash(line)"></div>
                                    </td>

                                    <td class="px-3 py-3 whitespace-nowrap">
                                        <div class="flex items-center gap-1.5">
                                            <span class="text-gray-500 dark:text-gray-400">&euro;</span>
                                            <input type="number" min="0" step="0.01" :value="priceValue(line)"
                                                   x-on:input="line.price = $event.target.value; line.priceSource = 'typed'"
                                                   class="w-24 rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100 text-sm py-1 tabular-nums font-medium">
                                            <span class="text-xs text-gray-500 dark:text-gray-400" x-text="line.mode === 'per_kg' ? '/kg' : 'each'"></span>
                                        </div>
                                        <div class="mt-1 text-xs tabular-nums text-gray-500 dark:text-gray-400">
                                            <template x-if="suggested(line) !== null">
                                                <button type="button" x-on:click="line.priceSource = 'guide'" title="Put the guide price in the box"
                                                        :class="line.priceSource === 'guide' ? 'bg-indigo-600 border-indigo-600 text-white' : 'border-gray-300 dark:border-gray-600 text-indigo-700 dark:text-indigo-300 hover:bg-indigo-50 dark:hover:bg-gray-700'"
                                                        class="px-1.5 py-0.5 rounded border font-medium">Use guide <span x-text="money(suggested(line))"></span></button>
                                            </template>
                                            <template x-if="tillPrice(line) !== null && line.priceSource !== 'till'">
                                                <button type="button" x-on:click="line.priceSource = 'till'" class="ml-1 underline decoration-dotted" title="Go back to the price on the till now">keep <span x-text="money(tillPrice(line))"></span></button>
                                            </template>
                                            <template x-if="line.product && priceNumber(line) !== null">
                                                <span :class="changeClass(line)" x-text="changeText(line)"></span>
                                            </template>
                                        </div>
                                        <div class="mt-1 text-xs tabular-nums" x-show="landedCost(line) !== null"
                                             :class="belowCost(line) ? 'text-red-600 dark:text-red-400 font-medium' : 'text-gray-500 dark:text-gray-400'"
                                             title="Invoice cost × transport, for one kg or one piece as sold. Margin is on the price before VAT.">
                                            cost <span x-text="money(landedCost(line))"></span> <span x-text="line.mode === 'per_kg' ? '/kg' : 'each'"></span> inc. delivery<template x-if="margin(line) !== null"><span> &middot; <span x-text="belowCost(line) ? 'below cost' : 'margin ' + Math.round(margin(line) * 100) + '%'"></span></span></template>
                                        </div>
                                    </td>

                                    <td class="px-3 py-3 text-right whitespace-nowrap">
                                        <template x-if="line.skipped">
                                            <div>
                                                <span class="text-xs text-gray-500 dark:text-gray-400">Skipped</span>
                                                <button type="button" x-on:click="skip(line, false)" class="ml-2 text-xs text-indigo-600 dark:text-indigo-400 hover:underline">Undo</button>
                                            </div>
                                        </template>
                                        <template x-if="!line.skipped">
                                            <div class="flex items-center justify-end gap-2">
                                                <button type="button" x-on:click="skip(line, true)" :disabled="line.busy"
                                                        class="text-xs text-gray-500 dark:text-gray-400 hover:underline">Skip</button>
                                                <button type="button" x-on:click="apply(line)" :disabled="!canApply(line) || line.busy"
                                                        class="px-3 py-1.5 rounded-md text-xs font-medium text-white bg-indigo-600 hover:bg-indigo-700 disabled:opacity-40 disabled:cursor-not-allowed"
                                                        x-text="line.applied_price !== null ? 'Apply again' : 'Apply'"></button>
                                            </div>
                                        </template>
                                        <div class="mt-1 text-xs text-green-700 dark:text-green-400 tabular-nums" x-show="line.applied_price !== null">
                                            &#10003; <span x-text="money(line.applied_price)"></span> on the till<span x-show="line.applied_at">, <span x-text="line.applied_at"></span></span>
                                        </div>
                                        <div class="mt-1 text-xs text-red-600 dark:text-red-400 whitespace-normal max-w-[12rem] ml-auto" x-show="line.error" x-text="line.error"></div>
                                    </td>
                                </tr>
                            </template>
                            <tr x-show="visibleLines.length === 0" x-cloak>
                                <td colspan="7" class="px-3 py-8 text-center text-gray-500 dark:text-gray-400">Every line is priced or skipped.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- Barcoded grocery lines: listed here, priced on the docket --}}
            @if (count($lines['barcoded']))
                <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg">
                    <div class="px-4 py-3 border-b border-gray-200 dark:border-gray-700 flex flex-wrap items-baseline gap-3">
                        <h3 class="font-semibold text-gray-900 dark:text-gray-100">Barcoded lines</h3>
                        <span class="text-sm text-gray-500 dark:text-gray-400">
                            Not priced here. Their costs and prices are handled on the
                            <a href="{{ route('deliveries.show', $delivery) }}" class="text-indigo-600 dark:text-indigo-400 hover:underline">delivery docket</a>.
                        </span>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead class="bg-gray-50 dark:bg-gray-900/40 text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                <tr>
                                    <th class="px-3 py-2 text-left">Invoice line</th>
                                    <th class="px-3 py-2 text-left">EAN</th>
                                    <th class="px-3 py-2 text-right">Qty</th>
                                    <th class="px-3 py-2 text-right">Cost</th>
                                    <th class="px-3 py-2 text-right">Guide RRP ex VAT</th>
                                    <th class="px-3 py-2 text-left">Till product</th>
                                    <th class="px-3 py-2 text-right">Till price</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-700 tabular-nums">
                                @foreach ($lines['barcoded'] as $line)
                                    <tr>
                                        <td class="px-3 py-2 text-gray-900 dark:text-gray-100">{{ $line['description'] }} <span class="text-xs text-gray-500 dark:text-gray-400">{{ $line['code'] }}</span></td>
                                        <td class="px-3 py-2 text-gray-600 dark:text-gray-300">{{ $line['ean'] }}</td>
                                        <td class="px-3 py-2 text-right text-gray-600 dark:text-gray-300">{{ rtrim(rtrim(number_format($line['quantity'], 2), '0'), '.') }}</td>
                                        <td class="px-3 py-2 text-right text-gray-600 dark:text-gray-300">{{ $money($line['net_unit_price']) }}</td>
                                        <td class="px-3 py-2 text-right text-gray-900 dark:text-gray-100">{{ $money(\App\Services\DynamisPricingService::guidePrice($line['net_unit_price'], $settings['transport'], $settings['markup'])) }}</td>
                                        <td class="px-3 py-2 text-gray-600 dark:text-gray-300">{{ $line['product']['name'] ?? 'Not on the till' }}</td>
                                        <td class="px-3 py-2 text-right text-gray-600 dark:text-gray-300">{{ isset($line['product']) ? $money($line['product']['till_price']) : '' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif
        </div>

        {{-- One shared product picker for whichever line asked for it --}}
        <div x-show="picking" x-cloak class="fixed inset-0 z-50 flex items-start justify-center pt-24 px-4 bg-gray-900/50"
             x-on:keydown.escape.window="picking = null" x-on:click.self="picking = null">
            <div class="w-full max-w-xl bg-white dark:bg-gray-800 rounded-lg shadow-xl p-5"
                 x-on:product-search:selected="link(picking, $event.detail.id); picking = null">
                <div class="flex items-start justify-between gap-4 mb-3">
                    <div>
                        <div class="font-semibold text-gray-900 dark:text-gray-100">Choose the till product</div>
                        <div class="text-sm text-gray-500 dark:text-gray-400" x-text="picking ? picking.description : ''"></div>
                    </div>
                    <button type="button" x-on:click="picking = null" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200">&#10005;</button>
                </div>
                <x-product-search mode="picker" :stocked="false" :show-till="true" placeholder="Search fruit and veg by name or code…" />
                <template x-if="picking && picking.product">
                    <button type="button" class="mt-4 text-sm text-red-600 dark:text-red-400 hover:underline"
                            x-on:click="link(picking, null); picking = null">Remove the link to <span x-text="picking.product.name"></span></button>
                </template>
                <p class="mt-4 text-xs text-gray-500 dark:text-gray-400">The choice is remembered for this Dynamis code on future invoices.</p>
            </div>
        </div>
    </div>

    @push('scripts')
    <script>
        function dynamisPricing(config) {
            return {
                lines: config.lines.map((line) => ({ ...line, price: '', priceSource: 'till', ticked: false, busy: false, error: null })),
                settings: config.settings,
                settingsNote: '',
                hideDone: false,
                picking: null,
                bulkBusy: false,

                // ---- the formula (mirrors App\Services\DynamisPricingService) ----
                guide(line) {
                    const vat = line.product ? line.product.vat_rate : 0;
                    return line.net_unit_price * this.settings.transport * this.settings.markup * (1 + vat);
                },
                boxPrice(line) {
                    return this.guide(line) * line.quantity / line.packages;
                },
                suggested(line) {
                    const divisor = line.mode === 'per_kg' ? line.kg_per_box : line.units_per_box;
                    if (!divisor || divisor <= 0) return null;
                    return Math.round(this.boxPrice(line) / divisor * 100) / 100;
                },

                // ---- cost ----
                // What one kg / one piece as sold costs us, delivery included (ex VAT).
                landedCost(line) {
                    const divisor = line.mode === 'per_kg' ? line.kg_per_box : line.units_per_box;
                    if (!divisor || divisor <= 0) return null;
                    return line.net_unit_price * this.settings.transport * line.quantity / line.packages / divisor;
                },
                // Share of the price before VAT that is left after the landed cost.
                margin(line) {
                    const cost = this.landedCost(line), price = this.priceNumber(line);
                    if (cost === null || price === null) return null;
                    const product = line.product || line.suggestion;
                    const exVat = price / (1 + (product ? product.vat_rate : 0));
                    return (exVat - cost) / exVat;
                },
                belowCost(line) {
                    const m = this.margin(line);
                    return m !== null && m < 0;
                },

                // ---- price box ----
                // The box starts on the price already on the till. The guide only
                // goes in when "Use guide" is clicked; typing overrides both.
                priceValue(line) {
                    if (line.priceSource === 'typed') return line.price;
                    if (line.priceSource === 'till') {
                        // Before a product is confirmed, the suggested product's till price stands in.
                        const now = this.tillPrice(line);
                        return now === null ? '' : now.toFixed(2);
                    }
                    const s = this.suggested(line);
                    return s === null ? '' : s.toFixed(2);
                },
                tillPrice(line) {
                    const product = line.product || line.suggestion;
                    return product ? Number(product.till_price) : null;
                },
                changesTill(line) {
                    const price = this.priceNumber(line);
                    return !!line.product && price !== null && Math.abs(price - line.product.till_price) >= 0.005;
                },
                priceNumber(line) {
                    const n = parseFloat(this.priceValue(line));
                    return Number.isFinite(n) && n > 0 ? n : null;
                },
                changeText(line) {
                    const price = this.priceNumber(line), now = line.product.till_price;
                    if (Math.abs(price - now) < 0.005) return ' · no change';
                    const pct = now > 0 ? ' (' + (price > now ? '+' : '') + Math.round((price - now) / now * 100) + '%)' : '';
                    return ' · ' + (price > now ? 'up ' : 'down ') + this.money(Math.abs(price - now)) + pct;
                },
                changeClass(line) {
                    const price = this.priceNumber(line), now = line.product.till_price;
                    if (Math.abs(price - now) < 0.005) return '';
                    return price > now ? 'text-amber-700 dark:text-amber-400' : 'text-sky-700 dark:text-sky-400';
                },

                // ---- wording ----
                money(n) { return '€' + Number(n).toFixed(2); },
                unitWord(unit) { return { KG: 'kg', COL: 'box', PCE: 'piece' }[unit] || unit.toLowerCase(); },
                trim(n) { return String(Math.round(n * 100) / 100); },
                packText(line) {
                    const per = line.quantity / line.packages;
                    const boxes = line.packages + (line.packages === 1 ? ' box' : ' boxes');
                    if (line.unit === 'KG') return boxes + ' × ' + this.trim(per) + ' kg';
                    if (line.unit === 'PCE') return boxes + ' × ' + this.trim(per) + ' pieces';
                    return boxes;
                },
                unitClash(line) {
                    const unit = line.product ? line.product.till_unit : null;
                    if (!unit) return '';
                    if (unit === 'kg' && line.mode === 'each') return 'The till sells this per kg.';
                    if (unit !== 'kg' && line.mode === 'per_kg') return 'The till sells this per ' + unit + ', not per kg.';
                    return '';
                },
                modeClass(line, mode) {
                    return line.mode === mode && !line.skipped
                        ? 'bg-indigo-600 border-indigo-600 text-white'
                        : 'bg-white dark:bg-gray-800 border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700';
                },
                rowClass(line) {
                    if (line.skipped) return 'opacity-50';
                    if (line.applied_price !== null) return 'bg-green-50/60 dark:bg-green-900/10';
                    return '';
                },

                // ---- lists ----
                isDone(line) { return line.skipped || line.applied_price !== null; },
                canApply(line) { return !line.skipped && !!line.product && this.priceNumber(line) !== null; },
                get visibleLines() { return this.hideDone ? this.lines.filter((l) => !this.isDone(l)) : this.lines; },
                get tickedLines() { return this.lines.filter((l) => l.ticked && this.canApply(l)); },
                get counts() {
                    const applied = this.lines.filter((l) => l.applied_price !== null && !l.skipped).length;
                    const skipped = this.lines.filter((l) => l.skipped).length;
                    return {
                        applied, skipped,
                        todo: this.lines.length - applied - skipped,
                        unmatched: this.lines.filter((l) => !l.product && !l.skipped).length,
                    };
                },
                tickReady() {
                    this.lines.forEach((l) => { l.ticked = this.canApply(l) && this.changesTill(l) && l.applied_price === null; });
                },

                // ---- actions ----
                setMode(line, mode) {
                    line.mode = mode;
                    if (line.skipped) this.skip(line, false);
                },
                openPicker(line) {
                    this.picking = line;
                    this.$nextTick(() => this.$root.querySelector('.product-search input[type=text], .product-search input[type=search]')?.focus());
                },
                async post(url, body) {
                    const response = await fetch(url, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                        },
                        body: JSON.stringify(body),
                    });
                    const data = await response.json().catch(() => ({}));
                    if (!response.ok || data.success === false) {
                        const first = data.errors ? Object.values(data.errors)[0][0] : null;
                        throw new Error(first || data.message || 'Something went wrong (' + response.status + ').');
                    }
                    return data;
                },
                merge(line, fresh, keepChoices) {
                    const keep = keepChoices
                        ? { mode: line.mode, units_per_box: line.units_per_box, kg_per_box: line.kg_per_box, count_source: line.count_source }
                        : {};
                    Object.assign(line, fresh, keep);
                },
                async run(line, work) {
                    line.busy = true;
                    line.error = null;
                    try { await work(); return true; } catch (e) { line.error = e.message; return false; } finally { line.busy = false; }
                },
                link(line, productId) {
                    return this.run(line, async () => {
                        const data = await this.post(`${config.urls.line}/${line.id}/product`, { product_id: productId });
                        // A newly chosen product decides per kg / each unless a price was already applied
                        this.merge(line, data.line, line.applied_price !== null);
                        line.priceSource = 'till';
                    });
                },
                apply(line) {
                    return this.run(line, async () => {
                        const data = await this.post(`${config.urls.line}/${line.id}/apply`, {
                            mode: line.mode,
                            units_per_box: line.units_per_box || null,
                            kg_per_box: line.kg_per_box || null,
                            price: this.priceNumber(line),
                        });
                        const price = this.priceNumber(line);
                        this.merge(line, data.line, false);
                        line.price = price.toFixed(2);
                        line.priceSource = 'typed';
                        line.ticked = false;
                    });
                },
                skip(line, skip) {
                    return this.run(line, async () => {
                        const mode = line.mode;
                        const data = await this.post(`${config.urls.line}/${line.id}/skip`, { skip });
                        this.merge(line, data.line, false);
                        if (!skip) line.mode = mode;
                        line.ticked = false;
                    });
                },
                countryState(line) {
                    if (!line.product || !line.origin) return null;
                    if (!line.origin_country) return 'unknown';
                    return line.product.country_id === line.origin_country.id ? 'same' : 'differs';
                },
                // Other origins the same till product arrives from on this invoice.
                otherOrigins(line) {
                    if (!line.product) return '';
                    const names = this.lines
                        .filter((other) => other !== line && other.product && other.product.id === line.product.id && other.origin && other.origin !== line.origin)
                        .map((other) => (other.origin_country ? other.origin_country.name : other.origin));
                    return [...new Set(names)].join(', ');
                },
                useInvoiceCountry(line) {
                    return this.run(line, async () => {
                        const data = await this.post(`${config.urls.line}/${line.id}/country`, {});
                        // One till product can sit on several lines; they all show its new country.
                        this.lines.filter((other) => other.product && other.product.id === data.line.product.id).forEach((other) => {
                            other.product.country_id = data.line.product.country_id;
                            other.product.country_name = data.line.product.country_name;
                        });
                    });
                },
                showOnTill(line) {
                    return this.run(line, async () => {
                        const data = await this.post(`${config.urls.line}/${line.id}/show-on-till`, {});
                        line.product.on_till = data.line.product.on_till;
                    });
                },
                async applyTicked() {
                    this.bulkBusy = true;
                    for (const line of this.tickedLines) {
                        await this.apply(line);
                    }
                    this.bulkBusy = false;
                },
                async saveSettings(asDefault = false) {
                    this.settingsNote = '';
                    try {
                        await this.post(config.urls.settings, {
                            transport: this.settings.transport,
                            markup: this.settings.markup,
                            as_default: asDefault,
                        });
                        this.settingsNote = asDefault ? 'Saved as the default.' : 'Saved.';
                    } catch (e) {
                        this.settingsNote = e.message;
                    }
                },
            };
        }
    </script>
    @endpush
</x-admin-layout>
