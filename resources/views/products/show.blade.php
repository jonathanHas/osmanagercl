<x-admin-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                {{ __('Product Details') }}
            </h2>
            <a href="{{ route('products.index') }}" class="inline-flex items-center px-4 py-2 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-500 rounded-md font-semibold text-xs text-gray-700 dark:text-gray-300 uppercase tracking-widest shadow-sm hover:bg-gray-50 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 disabled:opacity-25 transition ease-in-out duration-150">
                Back to Products
            </a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            @if(session('success'))
                <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-6" role="alert">
                    <span class="block sm:inline">{{ session('success') }}</span>
                </div>
            @endif
            
            <!-- Tab Navigation -->
            <div x-data="{ activeTab: 'overview' }" class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="border-b border-gray-200 dark:border-gray-700">
                    <nav class="-mb-px flex" aria-label="Tabs">
                        <button @click="activeTab = 'overview'" 
                                :class="activeTab === 'overview' ? 'border-indigo-500 text-indigo-600 dark:text-indigo-400' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:text-gray-400 dark:hover:text-gray-300'"
                                class="whitespace-nowrap py-4 px-6 border-b-2 font-medium text-sm transition-colors duration-200">
                            Overview
                        </button>
                        <button @click="activeTab = 'sales'" 
                                :class="activeTab === 'sales' ? 'border-indigo-500 text-indigo-600 dark:text-indigo-400' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:text-gray-400 dark:hover:text-gray-300'"
                                class="whitespace-nowrap py-4 px-6 border-b-2 font-medium text-sm transition-colors duration-200">
                            Sales History
                        </button>
                        <button @click="activeTab = 'stock'" 
                                :class="activeTab === 'stock' ? 'border-indigo-500 text-indigo-600 dark:text-indigo-400' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:text-gray-400 dark:hover:text-gray-300'"
                                class="whitespace-nowrap py-4 px-6 border-b-2 font-medium text-sm transition-colors duration-200">
                            Stock Movement
                        </button>
                    </nav>
                </div>

                <div class="p-6 text-gray-900 dark:text-gray-100">
                    <!-- Overview Tab -->
                    <div x-show="activeTab === 'overview'" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 transform scale-95" x-transition:enter-end="opacity-100 transform scale-100">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <!-- Basic Information -->
                            <div>
                                <h3 class="text-lg font-semibold mb-4">Basic Information</h3>
                                <dl class="space-y-3">
                                    <div>
                                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Product ID</dt>
                                        <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ $product->ID }}</dd>
                                    </div>
                                    <div>
                                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Name</dt>
                                        <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ $product->NAME }}</dd>
                                    </div>
                                    <div>
                                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Code</dt>
                                        <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ $product->CODE }}</dd>
                                    </div>
                                    <div>
                                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Reference</dt>
                                        <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ $product->REFERENCE }}</dd>
                                    </div>
                                    <div>
                                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Category ID</dt>
                                        <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ $product->CATEGORY }}</dd>
                                    </div>
                                    <div>
                                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Tax Category</dt>
                                        <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100">
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full {{ $product->tax_category_badge_class }} mr-2">
                                                {{ $product->formatted_vat_rate }}
                                            </span>
                                            {{ $product->tax_category_name }} ({{ $product->TAXCAT }})
                                        </dd>
                                    </div>
                                </dl>
                            </div>

                            <!-- Pricing and Stock -->
                            <div>
                                <h3 class="text-lg font-semibold mb-4">Pricing & Stock</h3>
                                <dl class="space-y-3">
                                    <div>
                                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Buy Price</dt>
                                        <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100">€{{ number_format($product->PRICEBUY, 2) }}</dd>
                                    </div>
                                    <div>
                                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Net Sell Price</dt>
                                        <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100">€{{ $product->formatted_price }}</dd>
                                    </div>
                                    <div>
                                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Gross Sell Price (incl. VAT)</dt>
                                        <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100 font-semibold text-lg">{{ $product->formatted_price_with_vat }}</dd>
                                    </div>
                                    <div>
                                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">VAT Amount</dt>
                                        <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100">€{{ number_format($product->getVatAmount(), 2) }}</dd>
                                    </div>
                                    <div>
                                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Current Stock</dt>
                                        <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100">
                                            @if($product->isService())
                                                <span class="text-gray-500">N/A (Service)</span>
                                            @else
                                                <span class="{{ $product->getCurrentStock() > 0 ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400' }} font-semibold">
                                                    {{ number_format($product->getCurrentStock(), 1) }}
                                                </span>
                                            @endif
                                        </dd>
                                    </div>
                                    <div>
                                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Stock Cost</dt>
                                        <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100">€{{ number_format($product->STOCKCOST, 2) }}</dd>
                                    </div>
                                    <div>
                                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Warranty</dt>
                                        <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ $product->WARRANTY }} days</dd>
                                    </div>
                                </dl>
                            </div>
                        </div>

                        <!-- Product Attributes -->
                        <div class="mt-8">
                            <h3 class="text-lg font-semibold mb-4">Product Attributes</h3>
                            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                                <div class="flex items-center">
                                    <input type="checkbox" {{ $product->ISCOM ? 'checked' : '' }} disabled class="rounded border-gray-300 text-indigo-600">
                                    <label class="ml-2 text-sm text-gray-600 dark:text-gray-400">Is Commission</label>
                                </div>
                                <div class="flex items-center">
                                    <input type="checkbox" {{ $product->ISSCALE ? 'checked' : '' }} disabled class="rounded border-gray-300 text-indigo-600">
                                    <label class="ml-2 text-sm text-gray-600 dark:text-gray-400">Sold by Weight</label>
                                </div>
                                <div class="flex items-center">
                                    <input type="checkbox" {{ $product->ISKITCHEN ? 'checked' : '' }} disabled class="rounded border-gray-300 text-indigo-600">
                                    <label class="ml-2 text-sm text-gray-600 dark:text-gray-400">Kitchen Item</label>
                                </div>
                                <div class="flex items-center">
                                    <input type="checkbox" {{ $product->PRINTKB ? 'checked' : '' }} disabled class="rounded border-gray-300 text-indigo-600">
                                    <label class="ml-2 text-sm text-gray-600 dark:text-gray-400">Print to Kitchen</label>
                                </div>
                                <div class="flex items-center">
                                    <input type="checkbox" {{ $product->SENDSTATUS ? 'checked' : '' }} disabled class="rounded border-gray-300 text-indigo-600">
                                    <label class="ml-2 text-sm text-gray-600 dark:text-gray-400">Send Status</label>
                                </div>
                                <div class="flex items-center">
                                    <input type="checkbox" {{ $product->ISSERVICE ? 'checked' : '' }} disabled class="rounded border-gray-300 text-indigo-600">
                                    <label class="ml-2 text-sm text-gray-600 dark:text-gray-400">Is Service</label>
                                </div>
                                <div class="flex items-center">
                                    <input type="checkbox" {{ $product->ISVPRICE ? 'checked' : '' }} disabled class="rounded border-gray-300 text-indigo-600">
                                    <label class="ml-2 text-sm text-gray-600 dark:text-gray-400">Variable Price</label>
                                </div>
                                <div class="flex items-center">
                                    <input type="checkbox" {{ $product->ISVERPATRIB ? 'checked' : '' }} disabled class="rounded border-gray-300 text-indigo-600">
                                    <label class="ml-2 text-sm text-gray-600 dark:text-gray-400">Variable Attributes</label>
                                </div>
                            </div>
                        </div>

                        <!-- Tax Assignment Edit -->
                        <div class="mt-8">
                            <h3 class="text-lg font-semibold mb-4">Tax Assignment</h3>
                            <form method="POST" action="{{ route('products.update-tax', $product->ID) }}" class="space-y-4">
                                @csrf
                                @method('PATCH')
                                <div>
                                    <label for="tax_category" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Tax Category</label>
                                    <select name="tax_category" id="tax_category" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600">
                                        @foreach($taxCategories as $category)
                                            <option value="{{ $category->ID }}" {{ $product->TAXCAT == $category->ID ? 'selected' : '' }}>
                                                {{ $category->NAME }} ({{ $category->primaryTax?->formatted_rate ?? '0%' }})
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div>
                                    <button type="submit" class="inline-flex items-center px-4 py-2 bg-gray-800 dark:bg-gray-200 border border-transparent rounded-md font-semibold text-xs text-white dark:text-gray-800 uppercase tracking-widest hover:bg-gray-700 dark:hover:bg-white focus:bg-gray-700 dark:focus:bg-white active:bg-gray-900 dark:active:bg-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition ease-in-out duration-150">
                                        Update Tax Category
                                    </button>
                                </div>
                            </form>
                        </div>

                        <!-- Additional Information -->
                        @if($product->TEXTTIP || $product->DISPLAY)
                            <div class="mt-8">
                                <h3 class="text-lg font-semibold mb-4">Additional Information</h3>
                                <dl class="space-y-3">
                                    @if($product->TEXTTIP)
                                        <div>
                                            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Text Tip</dt>
                                            <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ $product->TEXTTIP }}</dd>
                                        </div>
                                    @endif
                                    @if($product->DISPLAY)
                                        <div>
                                            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Display</dt>
                                            <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ $product->DISPLAY }}</dd>
                                        </div>
                                    @endif
                                </dl>
                            </div>
                        @endif
                    </div>

                    <!-- Sales History Tab -->
                    <div x-show="activeTab === 'sales'" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 transform scale-95" x-transition:enter-end="opacity-100 transform scale-100">
                        @if(isset($salesHistory) && count($salesHistory) > 0)
                            <!-- Sales Statistics Cards -->
                            @if(isset($salesStats))
                                <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
                                    <div class="bg-gray-50 dark:bg-gray-700 p-4 rounded-lg">
                                        <div class="text-sm font-medium text-gray-500 dark:text-gray-400">Total Sales (12m)</div>
                                        <div class="mt-1 text-2xl font-semibold text-gray-900 dark:text-gray-100">{{ number_format($salesStats['total_sales_12m'], 0) }}</div>
                                    </div>
                                    <div class="bg-gray-50 dark:bg-gray-700 p-4 rounded-lg">
                                        <div class="text-sm font-medium text-gray-500 dark:text-gray-400">Avg Monthly</div>
                                        <div class="mt-1 text-2xl font-semibold text-gray-900 dark:text-gray-100">{{ number_format($salesStats['avg_monthly_sales'], 1) }}</div>
                                    </div>
                                    <div class="bg-gray-50 dark:bg-gray-700 p-4 rounded-lg">
                                        <div class="text-sm font-medium text-gray-500 dark:text-gray-400">This Month</div>
                                        <div class="mt-1 text-2xl font-semibold text-gray-900 dark:text-gray-100">{{ number_format($salesStats['this_month_sales'], 0) }}</div>
                                    </div>
                                    <div class="bg-gray-50 dark:bg-gray-700 p-4 rounded-lg">
                                        <div class="text-sm font-medium text-gray-500 dark:text-gray-400">Trend</div>
                                        <div class="mt-1 text-2xl font-semibold">
                                            @if($salesStats['trend'] === 'up')
                                                <span class="text-green-600 dark:text-green-400">↑ Up</span>
                                            @elseif($salesStats['trend'] === 'down')
                                                <span class="text-red-600 dark:text-red-400">↓ Down</span>
                                            @else
                                                <span class="text-gray-600 dark:text-gray-400">→ Stable</span>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            @endif

                            <!-- Sales by Month Table -->
                            <h3 class="text-lg font-semibold mb-4">Sales by Month (Last 4 Months)</h3>
                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                    <thead class="bg-gray-50 dark:bg-gray-700">
                                        <tr>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Month</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Units Sold</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Trend</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                        @php
                                            $previousUnits = null;
                                        @endphp
                                        @foreach($salesHistory as $monthData)
                                            <tr>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-gray-100">
                                                    {{ $monthData['month'] }}
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">
                                                    {{ number_format($monthData['units'], 1) }}
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm">
                                                    @if($previousUnits !== null)
                                                        @if($monthData['units'] > $previousUnits)
                                                            <span class="text-green-600 dark:text-green-400">↑ {{ number_format((($monthData['units'] - $previousUnits) / max($previousUnits, 1)) * 100, 1) }}%</span>
                                                        @elseif($monthData['units'] < $previousUnits)
                                                            <span class="text-red-600 dark:text-red-400">↓ {{ number_format((($previousUnits - $monthData['units']) / max($previousUnits, 1)) * 100, 1) }}%</span>
                                                        @else
                                                            <span class="text-gray-600 dark:text-gray-400">→ 0%</span>
                                                        @endif
                                                    @else
                                                        <span class="text-gray-400 dark:text-gray-500">-</span>
                                                    @endif
                                                </td>
                                            </tr>
                                            @php
                                                $previousUnits = $monthData['units'];
                                            @endphp
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @else
                            <div class="text-center py-12">
                                <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                                </svg>
                                <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-gray-100">No sales data</h3>
                                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">This product has no sales history.</p>
                            </div>
                        @endif
                    </div>

                    <!-- Stock Movement Tab -->
                    <div x-show="activeTab === 'stock'" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 transform scale-95" x-transition:enter-end="opacity-100 transform scale-100">
                        <div class="text-center py-12">
                            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4" />
                            </svg>
                            <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-gray-100">Stock Movement</h3>
                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Stock movement tracking coming soon.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-admin-layout>