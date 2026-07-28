<x-admin-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    {{ __('Live Stock Valuation') }}
                </h2>
                <p class="mt-1 text-sm text-gray-500">
                    Real-time view as of {{ $valuation['valuation_date']->format('d M Y H:i') }}
                </p>
            </div>
            <div class="flex space-x-3">
                <a href="{{ route('management.stock-valuation.index') }}"
                   class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                    <svg class="-ml-1 mr-2 h-5 w-5 text-gray-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                    </svg>
                    Back to Snapshots
                </a>
                <a href="{{ route('management.stock-valuation.create') }}"
                   class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700">
                    <svg class="-ml-1 mr-2 h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4" />
                    </svg>
                    Save Snapshot
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            @if(session('success'))
                <div class="mb-4 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative" role="alert">
                    <span class="block sm:inline">{{ session('success') }}</span>
                </div>
            @endif

            @if(session('error'))
                <div class="mb-4 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative" role="alert">
                    <span class="block sm:inline">{{ session('error') }}</span>
                </div>
            @endif

            <!-- Summary Cards -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <div class="text-sm font-medium text-gray-500">Total Stock Value</div>
                        <div class="mt-2 text-3xl font-bold text-gray-900">
                            &euro;{{ number_format($valuation['total_value'], 2) }}
                        </div>
                        <div class="mt-2 text-xs text-gray-500">
                            At cost (ex-VAT).
                            @if($valuation['negative_stock']['line_count'] > 0)
                                Excludes {{ number_format($valuation['negative_stock']['line_count']) }} lines at negative stock.
                            @endif
                        </div>
                    </div>
                </div>
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <div class="text-sm font-medium text-gray-500">Categories</div>
                        <div class="mt-2 text-3xl font-bold text-gray-900">
                            {{ $valuation['category_count'] }}
                        </div>
                    </div>
                </div>
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <div class="text-sm font-medium text-gray-500">Total Products</div>
                        <div class="mt-2 text-3xl font-bold text-gray-900">
                            {{ number_format($valuation['product_count']) }}
                        </div>
                    </div>
                </div>
            </div>

            <!-- Categories Table -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 bg-white border-b border-gray-200">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Valuation by Category</h3>

                    @if(count($valuation['categories']) > 0)
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Category
                                        </th>
                                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Products
                                        </th>
                                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Value
                                        </th>
                                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            % of Total
                                        </th>
                                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Actions
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    @foreach($valuation['categories'] as $category)
                                        <tr class="hover:bg-gray-50">
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="text-sm font-medium text-gray-900">
                                                    {{ $category['category_name'] }}
                                                </div>
                                                <div class="text-xs text-gray-500">
                                                    ID: {{ $category['category_id'] }}
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-right">
                                                <div class="text-sm text-gray-900">
                                                    {{ number_format($category['product_count']) }}
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-right">
                                                <div class="text-sm font-medium text-gray-900">
                                                    {{ number_format($category['total_value'], 2) }}
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-right">
                                                <div class="text-sm text-gray-500">
                                                    @if($valuation['total_value'] > 0)
                                                        {{ number_format(($category['total_value'] / $valuation['total_value']) * 100, 1) }}%
                                                    @else
                                                        0%
                                                    @endif
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                                <a href="{{ route('management.stock-valuation.live.category', $category['category_id']) }}"
                                                   class="text-indigo-600 hover:text-indigo-900">
                                                    View Products
                                                </a>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                                <tfoot class="bg-gray-50">
                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-bold text-gray-900">
                                            Total
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-bold text-gray-900">
                                            {{ number_format($valuation['product_count']) }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-bold text-gray-900">
                                            {{ number_format($valuation['total_value'], 2) }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-bold text-gray-900">
                                            100%
                                        </td>
                                        <td></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    @else
                        <div class="text-center py-8 text-gray-500">
                            No stock data found.
                        </div>
                    @endif
                </div>
            </div>

            <!-- Excluded: Negative Stock -->
            @if($valuation['negative_stock']['line_count'] > 0)
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mt-6" x-data="{ open: false }">
                    <div class="p-6 border-l-4 border-amber-400">
                        <button @click="open = !open" class="w-full flex items-start justify-between text-left">
                            <div>
                                <h3 class="text-lg font-medium text-gray-900">
                                    Excluded &mdash; Negative Stock
                                </h3>
                                <p class="mt-1 text-sm text-gray-600">
                                    {{ number_format($valuation['negative_stock']['line_count']) }} lines carry negative stock,
                                    a notional
                                    <span class="font-semibold text-amber-700">&euro;{{ number_format($valuation['negative_stock']['notional_value'], 2) }}</span>.
                                    These are sold at the till but never booked in, so they are excluded from the valuation above.
                                </p>
                            </div>
                            <svg class="ml-4 h-5 w-5 flex-shrink-0 text-gray-400 transition-transform" :class="open ? 'rotate-180' : ''"
                                 fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                            </svg>
                        </button>

                        <div x-show="open" x-cloak class="mt-4 overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Product</th>
                                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Category</th>
                                        <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Units</th>
                                        <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Unit Cost</th>
                                        <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Notional Value</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    @foreach($valuation['negative_stock']['items'] as $item)
                                        <tr class="hover:bg-gray-50">
                                            <td class="px-4 py-2">
                                                <div class="text-sm text-gray-900">{{ $item['product_name'] }}</div>
                                                <div class="text-xs text-gray-500">{{ $item['product_code'] }}</div>
                                            </td>
                                            <td class="px-4 py-2 text-sm text-gray-500">{{ $item['category_name'] }}</td>
                                            <td class="px-4 py-2 text-right text-sm font-medium text-amber-700">
                                                {{ number_format($item['stock_units'], 2) }}
                                            </td>
                                            <td class="px-4 py-2 text-right text-sm text-gray-500">
                                                &euro;{{ number_format($item['unit_cost'], 2) }}
                                            </td>
                                            <td class="px-4 py-2 text-right text-sm font-medium text-amber-700">
                                                &euro;{{ number_format($item['line_value'], 2) }}
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                            @if($valuation['negative_stock']['truncated'])
                                <p class="mt-3 text-xs text-gray-500">
                                    Showing the {{ count($valuation['negative_stock']['items']) }} worst of
                                    {{ number_format($valuation['negative_stock']['line_count']) }} lines. Full list is in the CSV export.
                                </p>
                            @endif
                        </div>
                    </div>
                </div>
            @endif

            <!-- Cost Price Anomalies -->
            @if($valuation['cost_anomalies']['line_count'] > 0)
                {{-- Stay open after an adjustment so the next line can be worked straight away --}}
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mt-6"
                     x-data="{ open: {{ session('success') || session('error') ? 'true' : 'false' }} }"
                     id="cost-anomalies">
                    <div class="p-6 border-l-4 border-red-400">
                        <button @click="open = !open" class="w-full flex items-start justify-between text-left">
                            <div>
                                <h3 class="text-lg font-medium text-gray-900">
                                    Cost Price Anomalies
                                </h3>
                                <p class="mt-1 text-sm text-gray-600">
                                    {{ number_format($valuation['cost_anomalies']['line_count']) }} stocked products have a cost price
                                    above their sell price, worth
                                    <span class="font-semibold text-red-700">&euro;{{ number_format($valuation['cost_anomalies']['value'], 2) }}</span>
                                    of the total. Usually a case cost entered against a unit price &mdash; these lines
                                    <span class="font-semibold">are included</span> in the valuation and may inflate it.
                                </p>
                            </div>
                            <svg class="ml-4 h-5 w-5 flex-shrink-0 text-gray-400 transition-transform" :class="open ? 'rotate-180' : ''"
                                 fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                            </svg>
                        </button>

                        <div x-show="open" x-cloak class="mt-4 overflow-x-auto">
                            <p class="mb-3 text-xs text-gray-500">
                                Where a bulk or case cost has been entered against a per-unit sell price, divide it down
                                to the real per-unit cost. The suggested divisor is a hint from the supplier case units
                                or the pack size in the name &mdash; check the resulting cost and margin before applying.
                                This updates the cost price in the POS.
                            </p>
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Product</th>
                                        <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Cost</th>
                                        <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Sell</th>
                                        <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Units</th>
                                        <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Line Value</th>
                                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Divide cost by</th>
                                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Result</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    @foreach($valuation['cost_anomalies']['items'] as $item)
                                        <tr class="hover:bg-gray-50 align-top"
                                            x-data="{
                                                divisor: '',
                                                cost: {{ $item['unit_cost'] }},
                                                sell: {{ $item['sell_price'] }},
                                                units: {{ $item['stock_units'] }},
                                                get valid() {
                                                    const d = parseFloat(this.divisor);
                                                    return !isNaN(d) && d > 0;
                                                },
                                                get newCost() { return this.cost / parseFloat(this.divisor); },
                                                get margin() {
                                                    if (!this.sell) return null;
                                                    return ((this.sell - this.newCost) / this.sell) * 100;
                                                },
                                                get newValue() { return this.newCost * this.units; },
                                                money(v) {
                                                    return '€' + v.toLocaleString('en-IE', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                                                },
                                            }">
                                            <td class="px-4 py-3">
                                                <a href="{{ route('products.show', $item['product_id']) }}"
                                                   class="text-sm text-indigo-600 hover:text-indigo-900">
                                                    {{ $item['product_name'] }}
                                                </a>
                                                <div class="text-xs text-gray-500">{{ $item['product_code'] }} &middot; {{ $item['category_name'] }}</div>
                                            </td>
                                            <td class="px-4 py-3 text-right text-sm font-medium text-red-700 whitespace-nowrap">
                                                &euro;{{ number_format($item['unit_cost'], 2) }}
                                            </td>
                                            <td class="px-4 py-3 text-right text-sm text-gray-500 whitespace-nowrap">
                                                &euro;{{ number_format($item['sell_price'], 2) }}
                                            </td>
                                            <td class="px-4 py-3 text-right text-sm text-gray-900 whitespace-nowrap">
                                                {{ number_format($item['stock_units'], 2) }}
                                            </td>
                                            <td class="px-4 py-3 text-right text-sm font-medium text-gray-900 whitespace-nowrap">
                                                &euro;{{ number_format($item['line_value'], 2) }}
                                            </td>

                                            <!-- Divisor input + suggestion -->
                                            <td class="px-4 py-3">
                                                <input type="number" step="any" min="0.0001" x-model="divisor"
                                                       placeholder="e.g. 20"
                                                       class="w-24 rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                                                @if($item['suggested_divisor'])
                                                    <div class="mt-1">
                                                        <button type="button"
                                                                @click="divisor = '{{ $item['suggested_divisor'] }}'"
                                                                class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-indigo-50 text-indigo-700 hover:bg-indigo-100">
                                                            use {{ rtrim(rtrim(number_format($item['suggested_divisor'], 2), '0'), '.') }}
                                                        </button>
                                                        <span class="ml-1 text-xs text-gray-400">{{ $item['suggestion_source'] }}</span>
                                                    </div>
                                                @endif
                                            </td>

                                            <!-- Live preview + confirm -->
                                            <td class="px-4 py-3">
                                                <template x-if="valid">
                                                    <div>
                                                        <div class="text-sm font-medium text-gray-900">
                                                            <span x-text="money(newCost)"></span>
                                                            <span class="text-xs font-normal text-gray-500">per unit</span>
                                                        </div>
                                                        <div class="text-xs" x-show="margin !== null"
                                                             :class="margin < 0 ? 'text-red-600' : (margin > 60 ? 'text-amber-600' : 'text-green-700')">
                                                            margin <span x-text="margin === null ? '' : margin.toFixed(1) + '%'"></span>
                                                            <span x-show="margin > 60" class="text-amber-600">&mdash; unusually high, check the divisor</span>
                                                            <span x-show="margin < 0" class="text-red-600">&mdash; still below cost</span>
                                                        </div>
                                                        <div class="text-xs text-gray-500">
                                                            value {{ number_format($item['line_value'], 2) }} &rarr;
                                                            <span x-text="money(newValue)"></span>
                                                        </div>
                                                        <form method="POST" action="{{ route('management.stock-valuation.adjust-cost') }}" class="mt-2"
                                                              @submit="return confirm('Update the cost price of {{ addslashes($item['product_name']) }} from €{{ number_format($item['unit_cost'], 4) }} to ' + money(newCost) + '? This changes the cost in the POS.')">
                                                            @csrf
                                                            <input type="hidden" name="product_id" value="{{ $item['product_id'] }}">
                                                            <input type="hidden" name="divisor" :value="divisor">
                                                            <button type="submit"
                                                                    class="inline-flex items-center px-3 py-1 border border-transparent rounded-md text-xs font-medium text-white bg-indigo-600 hover:bg-indigo-700">
                                                                Update cost
                                                            </button>
                                                        </form>
                                                    </div>
                                                </template>
                                                <template x-if="!valid">
                                                    <span class="text-xs text-gray-400">enter a divisor</span>
                                                </template>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                            @if($valuation['cost_anomalies']['truncated'])
                                <p class="mt-3 text-xs text-gray-500">
                                    Showing the {{ count($valuation['cost_anomalies']['items']) }} largest of
                                    {{ number_format($valuation['cost_anomalies']['line_count']) }} lines. Full list is in the CSV export.
                                </p>
                            @endif
                        </div>
                    </div>
                </div>
            @endif

            <!-- Basis note -->
            <div class="mt-6 text-xs text-gray-500">
                Valued at current cost price (PRICEBUY) &times; units on hand, excluding VAT. This is a
                replacement-cost basis rather than FIFO or lower-of-cost-and-NRV &mdash; confirm with your
                accountant before using a finalized snapshot in statutory year-end figures.
            </div>
        </div>
    </div>
</x-admin-layout>
