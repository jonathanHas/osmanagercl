<x-admin-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('Wholesale Pricing') }}
            </h2>
            <div class="flex items-center space-x-4">
                <a href="{{ route('kitchen.index') }}" class="text-gray-600 hover:text-gray-900">
                    &larr; Back to Recipes
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-12" x-data="wholesalePricing(@js($rows), @js($taxRates), @js($defaultTarget), @js($marginBands))">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

            <!-- Statistics Cards -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-6 mb-8">
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <div class="flex items-center">
                            <div class="p-3 rounded-full bg-blue-100 text-blue-800">
                                <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                                </svg>
                            </div>
                            <div class="ml-4">
                                <p class="text-gray-500 text-sm">Total Recipes</p>
                                <p class="text-2xl font-semibold text-gray-900">{{ $stats['total'] }}</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <div class="flex items-center">
                            <div class="p-3 rounded-full bg-green-100 text-green-800">
                                <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                            </div>
                            <div class="ml-4">
                                <p class="text-gray-500 text-sm">Priced</p>
                                <p class="text-2xl font-semibold text-gray-900">{{ $stats['priced'] }}</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <div class="flex items-center">
                            <div class="p-3 rounded-full bg-gray-100 text-gray-800">
                                <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                            </div>
                            <div class="ml-4">
                                <p class="text-gray-500 text-sm">Not Priced</p>
                                <p class="text-2xl font-semibold text-gray-900">{{ $stats['unpriced'] }}</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <div class="flex items-center">
                            <div class="p-3 rounded-full bg-red-100 text-red-800">
                                <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 17h8m0 0V9m0 8l-8-8-4 4-6-6"/>
                                </svg>
                            </div>
                            <div class="ml-4">
                                <p class="text-gray-500 text-sm">Below Target</p>
                                <p class="text-2xl font-semibold text-gray-900">{{ $stats['below_target'] }}</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <div class="flex items-center">
                            <div class="p-3 rounded-full bg-purple-100 text-purple-800">
                                <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 8v8m-4-5v5m-4-2v2m-2 4h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                </svg>
                            </div>
                            <div class="ml-4">
                                <p class="text-gray-500 text-sm">Avg Margin</p>
                                <p class="text-2xl font-semibold text-gray-900">
                                    {{ $stats['average_margin'] !== null ? number_format($stats['average_margin'], 1).'%' : 'N/A' }}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Toolbar -->
            <div class="bg-white shadow-sm sm:rounded-lg p-6 mb-6">
                <div class="flex flex-wrap items-end gap-4">
                    <div class="flex-1 min-w-[200px]">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Search</label>
                        <input type="text" x-model="search" placeholder="Recipe name..."
                            class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    </div>

                    <div class="w-40">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Target margin %</label>
                        <input type="number" x-model.number="defaultTarget" min="0" max="99.9" step="0.1"
                            class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    </div>

                    <button type="button" @click="applyTargetToUnpriced()"
                        class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest hover:bg-gray-50"
                        title="Fill every unpriced row with the price that hits the target margin">
                        Apply to all unpriced
                    </button>

                    <div class="flex items-center gap-2 ml-auto">
                        <template x-for="f in ['all', 'unpriced', 'below target']" :key="f">
                            <button type="button" @click="filter = f"
                                class="px-3 py-1 rounded-full text-xs font-medium capitalize"
                                :class="filter === f ? 'bg-indigo-100 text-indigo-800' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'"
                                x-text="f"></button>
                        </template>
                    </div>
                </div>

                <p class="mt-4 text-sm text-gray-500">
                    Prices are entered <strong>including VAT</strong> &mdash; the ex-VAT price is derived automatically.
                    One wholesale unit is <strong>one full batch</strong> of the recipe.
                </p>
            </div>

            <!-- Recipes -->
            <div class="bg-white shadow-sm sm:rounded-lg overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Recipe</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Batch Cost</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Target %</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Wholesale Price (inc VAT)</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Margin</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Category / VAT</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Action</th>
                            </tr>
                        </thead>
                        <!-- One tbody per recipe: x-for needs a single root, and a
                             priced row renders both a data row and a breakdown row. -->
                        <template x-for="row in filtered()" :key="row.id">
                            <tbody class="bg-white divide-y divide-gray-200">
                                    <tr class="hover:bg-gray-50">
                                        <!-- Recipe -->
                                        <td class="px-6 py-4">
                                            <a :href="row.showUrl" class="text-sm font-medium text-indigo-600 hover:text-indigo-900" x-text="row.name"></a>
                                            <div class="text-xs text-gray-500 mt-0.5">
                                                <template x-if="row.retailProductName">
                                                    <span x-text="row.retailProductName"></span>
                                                </template>
                                                <template x-if="!row.retailProductName">
                                                    <span class="text-gray-400">Not linked to a retail product</span>
                                                </template>
                                            </div>
                                            <template x-if="row.wholesaleProductName">
                                                <div class="text-xs text-gray-400 mt-0.5">
                                                    <span x-text="row.wholesaleProductName"></span>
                                                    &middot; <span x-text="row.wholesaleProductCode"></span>
                                                </div>
                                            </template>
                                        </td>

                                        <!-- Batch cost -->
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <button type="button" @click="row.expanded = !row.expanded"
                                                class="flex items-center text-sm font-semibold text-gray-900 hover:text-indigo-600">
                                                <span x-text="money(row.batchCost)"></span>
                                                <svg class="w-4 h-4 ml-1 transition-transform" :class="row.expanded ? 'rotate-180' : ''"
                                                    fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                                                </svg>
                                            </button>
                                            <div class="text-xs text-gray-500 mt-0.5">
                                                <span x-text="row.portions"></span> portions &middot;
                                                <span x-text="money(row.costPerPortion)"></span>/portion
                                            </div>
                                        </td>

                                        <!-- Target -->
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="flex items-center gap-1">
                                                <input type="number" x-model.number="row.target" min="0" max="99.9" step="0.1"
                                                    :placeholder="defaultTarget"
                                                    class="w-20 text-sm rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                                <button type="button" @click="useTarget(row)"
                                                    class="px-2 py-1 text-xs font-medium text-indigo-600 hover:text-indigo-900"
                                                    title="Fill the price that hits this margin">Use</button>
                                            </div>
                                        </td>

                                        <!-- Price -->
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="relative">
                                                <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-500 text-sm">&euro;</span>
                                                <input type="number" x-model.number="row.incVat" step="0.01" min="0"
                                                    class="w-28 pl-7 text-sm rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                            </div>
                                            <div class="text-xs text-gray-500 mt-0.5" x-show="row.incVat > 0">
                                                ex VAT <span x-text="money(exVat(row))"></span>
                                                @ <span x-text="(vatRate(row) * 100).toFixed(1)"></span>%
                                            </div>
                                        </td>

                                        <!-- Margin -->
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <template x-if="margin(row) !== null">
                                                <div>
                                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium"
                                                        :class="marginClass(row)"
                                                        x-text="margin(row).toFixed(1) + '%'"></span>
                                                    <div class="text-xs text-gray-500 mt-0.5">
                                                        Profit <span x-text="money(exVat(row) - row.batchCost)"></span>/batch
                                                    </div>
                                                    <div class="text-xs text-amber-600 mt-0.5" x-show="belowCost(row)">
                                                        Below batch cost
                                                    </div>
                                                </div>
                                            </template>
                                            <template x-if="margin(row) === null">
                                                <span class="text-gray-400 text-sm">N/A</span>
                                            </template>
                                        </td>

                                        <!-- Category / VAT -->
                                        <td class="px-6 py-4">
                                            <template x-if="!row.needsClassification">
                                                <div class="text-sm text-gray-700" title="Inherited from the linked retail product">
                                                    <span x-text="categoryName(row.category)"></span>
                                                    <span class="text-gray-400">&middot;</span>
                                                    <span x-text="(vatRate(row) * 100).toFixed(1) + '%'"></span>
                                                </div>
                                            </template>
                                            <template x-if="row.needsClassification">
                                                <div class="space-y-1">
                                                    <select x-model="row.category" class="w-40 text-xs rounded-md border-gray-300 shadow-sm">
                                                        <option value="">Category...</option>
                                                        @foreach($categories as $category)
                                                            <option value="{{ $category->ID }}">{{ $category->NAME }}</option>
                                                        @endforeach
                                                    </select>
                                                    <select x-model="row.taxcat" class="w-40 text-xs rounded-md border-gray-300 shadow-sm">
                                                        <option value="">VAT rate...</option>
                                                        @foreach($taxCategories as $taxCategory)
                                                            <option value="{{ $taxCategory->ID }}">{{ $taxCategory->NAME }}</option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                            </template>
                                        </td>

                                        <!-- Status -->
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <template x-if="row.isPriced">
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">Priced</span>
                                            </template>
                                            <template x-if="!row.isPriced">
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">Not priced</span>
                                            </template>
                                            <template x-if="row.costChanged">
                                                <div class="mt-1">
                                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-amber-100 text-amber-800"
                                                        :title="'Priced against a batch cost of ' + money(row.pricedCost)">Cost changed</span>
                                                </div>
                                            </template>
                                            <template x-if="row.error">
                                                <div class="text-xs text-red-600 mt-1" x-text="row.error"></div>
                                            </template>
                                        </td>

                                        <!-- Action -->
                                        <td class="px-6 py-4 whitespace-nowrap text-right">
                                            <button type="button" @click="save(row)"
                                                :disabled="!canSave(row) || row.saving"
                                                class="inline-flex items-center px-3 py-2 border rounded-md font-semibold text-xs uppercase tracking-widest disabled:opacity-40 disabled:cursor-not-allowed"
                                                :class="row.isPriced
                                                    ? 'bg-white border-gray-300 text-gray-700 hover:bg-gray-50'
                                                    : 'bg-indigo-600 border-transparent text-white hover:bg-indigo-700'">
                                                <template x-if="row.saving">
                                                    <svg class="animate-spin w-4 h-4 mr-2" fill="none" viewBox="0 0 24 24">
                                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                                                    </svg>
                                                </template>
                                                <template x-if="row.saved && !row.saving">
                                                    <svg class="w-4 h-4 mr-2 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                                    </svg>
                                                </template>
                                                <span x-text="row.isPriced ? 'Update Price' : 'Set Price'"></span>
                                            </button>
                                        </td>
                                    </tr>

                                    <!-- Cost breakdown -->
                                    <tr x-show="row.expanded" x-cloak class="bg-gray-50">
                                        <td colspan="8" class="px-6 py-4">
                                            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                                                <div>
                                                    <p class="text-xs text-gray-500 uppercase tracking-wider">Ingredients</p>
                                                    <p class="text-sm font-semibold text-gray-900" x-text="money(row.breakdown.ingredient)"></p>
                                                    <p class="text-xs text-gray-400" x-text="pct(row.breakdown.ingredient, row.batchCost)"></p>
                                                </div>
                                                <div>
                                                    <p class="text-xs text-gray-500 uppercase tracking-wider">Labour</p>
                                                    <p class="text-sm font-semibold text-gray-900" x-text="money(row.breakdown.labour)"></p>
                                                    <p class="text-xs text-gray-400">
                                                        <span x-text="row.breakdown.labourMinutes"></span> min &middot;
                                                        <span x-text="pct(row.breakdown.labour, row.batchCost)"></span>
                                                    </p>
                                                </div>
                                                <div>
                                                    <p class="text-xs text-gray-500 uppercase tracking-wider">Electricity</p>
                                                    <p class="text-sm font-semibold text-gray-900" x-text="money(row.breakdown.electricity)"></p>
                                                    <p class="text-xs text-gray-400" x-text="pct(row.breakdown.electricity, row.batchCost)"></p>
                                                </div>
                                                <div>
                                                    <p class="text-xs text-gray-500 uppercase tracking-wider">Packaging</p>
                                                    <p class="text-sm font-semibold text-gray-900" x-text="money(row.breakdown.packaging)"></p>
                                                    <p class="text-xs text-gray-400" x-text="pct(row.breakdown.packaging, row.batchCost)"></p>
                                                </div>
                                            </div>
                                            <div class="mt-3 text-xs" :class="row.isFullyProfiled ? 'text-gray-500' : 'text-amber-600'">
                                                Cost accuracy <span x-text="row.costAccuracy"></span>%
                                                <template x-if="!row.isFullyProfiled">
                                                    <span>&mdash; some ingredients use legacy costing, so the batch cost is approximate.</span>
                                                </template>
                                            </div>
                                        </td>
                                    </tr>
                            </tbody>
                        </template>

                        <tbody class="bg-white">
                            <tr x-show="filtered().length === 0">
                                <td colspan="8" class="px-6 py-8 text-center text-sm text-gray-500">
                                    No recipes match this filter.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
    <script>
        function wholesalePricing(rows, taxRates, defaultTarget, marginBands) {
            return {
                rows: rows.map(r => ({ ...r, saving: false, saved: false, error: null })),
                taxRates,
                defaultTarget,
                marginBands,
                categoryNames: @js($categories->pluck('NAME', 'ID')),
                search: '',
                filter: 'all',

                money(value) {
                    return '€' + (Number(value) || 0).toFixed(2);
                },

                pct(part, total) {
                    if (!total) return '0%';
                    return ((part / total) * 100).toFixed(0) + '% of batch';
                },

                categoryName(id) {
                    return this.categoryNames[id] ?? id ?? '-';
                },

                vatRate(row) {
                    return this.taxRates[row.taxcat] ?? 0;
                },

                // PRICESELL is stored ex-VAT, so this mirrors the server-side
                // conversion in KitchenWholesaleService::exVat().
                exVat(row) {
                    const rate = this.vatRate(row);
                    const inc = Number(row.incVat) || 0;
                    return rate > 0 ? inc / (1 + rate) : inc;
                },

                margin(row) {
                    const ex = this.exVat(row);
                    if (ex <= 0) return null;
                    return ((ex - row.batchCost) / ex) * 100;
                },

                effectiveTarget(row) {
                    return row.target ?? this.defaultTarget;
                },

                suggested(row) {
                    const target = this.effectiveTarget(row);
                    if (target >= 100) return 0;
                    const ex = row.batchCost / (1 - target / 100);
                    return ex * (1 + this.vatRate(row));
                },

                useTarget(row) {
                    row.incVat = Number(this.suggested(row).toFixed(2));
                },

                applyTargetToUnpriced() {
                    this.rows.filter(r => !r.isPriced).forEach(r => this.useTarget(r));
                },

                // Bands come from KitchenCostingService constants so the server
                // and client thresholds cannot drift apart.
                marginClass(row) {
                    const m = this.margin(row);
                    if (m === null) return 'bg-gray-100 text-gray-800';
                    if (m >= this.marginBands.excellent) return 'bg-green-100 text-green-800';
                    if (m >= this.marginBands.good) return 'bg-yellow-100 text-yellow-800';
                    if (m >= this.marginBands.low) return 'bg-orange-100 text-orange-800';
                    return 'bg-red-100 text-red-800';
                },

                belowCost(row) {
                    return Number(row.incVat) > 0 && this.exVat(row) < row.batchCost;
                },

                canSave(row) {
                    if (!(Number(row.incVat) > 0)) return false;
                    if (row.needsClassification && (!row.category || !row.taxcat)) return false;
                    return row.incVat !== row.savedIncVat || !row.isPriced;
                },

                filtered() {
                    const term = this.search.trim().toLowerCase();

                    return this.rows.filter(row => {
                        if (term && !row.name.toLowerCase().includes(term)) return false;
                        if (this.filter === 'unpriced') return !row.isPriced;
                        if (this.filter === 'below target') {
                            const m = this.margin(row);
                            return m !== null && m < this.effectiveTarget(row);
                        }
                        return true;
                    });
                },

                async save(row) {
                    row.saving = true;
                    row.error = null;

                    try {
                        const response = await fetch(row.saveUrl, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                            },
                            body: JSON.stringify({
                                price_inc_vat: row.incVat,
                                target_margin: row.target,
                                category: row.category,
                                tax_category: row.taxcat,
                            }),
                        });

                        const data = await response.json();

                        if (!response.ok) {
                            row.error = data.message
                                ?? Object.values(data.errors ?? {}).flat().join(' ')
                                ?? 'Save failed.';
                            return;
                        }

                        // Replace in place so the expander state and any target
                        // the user typed survive the save.
                        Object.assign(row, data.row, {
                            expanded: row.expanded,
                            saving: false,
                            error: null,
                        });

                        row.saved = true;
                        setTimeout(() => { row.saved = false; }, 2000);
                    } catch (e) {
                        row.error = 'Save failed - check your connection.';
                    } finally {
                        row.saving = false;
                    }
                },
            };
        }
    </script>
    @endpush
</x-admin-layout>
