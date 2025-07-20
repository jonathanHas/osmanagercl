<x-admin-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <div>
                <h1 class="text-3xl font-bold text-gray-900 dark:text-gray-100">{{ $product->NAME }}</h1>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                    SKU: {{ $product->CODE }} 
                    @if($product->REFERENCE)
                        • REF: {{ $product->REFERENCE }}
                    @endif
                </p>
            </div>
            <a href="{{ route('products.index') }}" class="inline-flex items-center px-4 py-2 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-500 rounded-md font-semibold text-xs text-gray-700 dark:text-gray-300 uppercase tracking-widest shadow-sm hover:bg-gray-50 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 disabled:opacity-25 transition ease-in-out duration-150">
                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                </svg>
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

            <!-- Quick Stats Bar -->
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg mb-6">
                <div class="p-6">
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
                        <!-- Current Price -->
                        <div class="relative">
                            <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400 mb-1">Current Price (incl. VAT)</h3>
                            <p class="text-2xl font-bold text-gray-900 dark:text-gray-100">{{ $product->formatted_price_with_vat }}</p>
                            <button onclick="togglePriceEdit()" class="absolute top-0 right-0 text-indigo-600 hover:text-indigo-900 dark:text-indigo-400 dark:hover:text-indigo-600">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                                </svg>
                            </button>
                        </div>
                        
                        <!-- Stock Status -->
                        <div>
                            <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400 mb-1">Stock Status</h3>
                            @if($product->isService())
                                <p class="text-lg font-semibold text-gray-500">Service Item</p>
                            @else
                                <p class="text-2xl font-bold {{ $product->getCurrentStock() > 0 ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400' }}">
                                    {{ number_format($product->getCurrentStock(), 1) }}
                                </p>
                                @if($product->getCurrentStock() > 0 && $product->getCurrentStock() < 10)
                                    <p class="text-xs text-yellow-600 dark:text-yellow-400">Low Stock</p>
                                @endif
                            @endif
                        </div>
                        
                        <!-- VAT Rate -->
                        <div>
                            <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400 mb-1">VAT Rate</h3>
                            <span class="inline-flex items-center px-3 py-1 text-lg font-semibold rounded-full {{ $product->tax_category_badge_class }}">
                                {{ $product->formatted_vat_rate }}
                            </span>
                        </div>
                        
                        <!-- Category -->
                        <div>
                            <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400 mb-1">Category</h3>
                            <p class="text-lg font-semibold text-gray-900 dark:text-gray-100">{{ $product->CATEGORY }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Price Edit Form (Hidden by default) -->
            <div id="priceEditForm" class="hidden bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg mb-6">
                <div class="p-6">
                    <h3 class="text-lg font-semibold mb-4">Edit Product Price</h3>
                    <form method="POST" action="{{ route('products.update-price', $product->ID) }}" onsubmit="return confirmPriceUpdate()">
                        @csrf
                        @method('PATCH')
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div>
                                <label for="net_price" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Net Price (excl. VAT)</label>
                                <input type="number" 
                                       name="net_price" 
                                       id="net_price" 
                                       value="{{ $product->PRICESELL }}" 
                                       step="0.01" 
                                       min="0" 
                                       max="999999.99"
                                       oninput="calculateGrossPrice()"
                                       class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600"
                                       required>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">VAT Amount</label>
                                <p id="vatAmount" class="mt-1 px-3 py-2 text-gray-900 dark:text-gray-100 bg-gray-50 dark:bg-gray-700 rounded-md">
                                    €{{ number_format($product->getVatAmount(), 2) }}
                                </p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Gross Price (incl. VAT)</label>
                                <p id="grossPrice" class="mt-1 px-3 py-2 text-gray-900 dark:text-gray-100 bg-gray-50 dark:bg-gray-700 rounded-md font-semibold">
                                    {{ $product->formatted_price_with_vat }}
                                </p>
                            </div>
                        </div>
                        <div class="mt-4 flex gap-2">
                            <button type="submit" class="inline-flex items-center px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white font-semibold text-xs uppercase tracking-widest rounded-md transition">
                                Update Price
                            </button>
                            <button type="button" onclick="togglePriceEdit()" class="inline-flex items-center px-4 py-2 bg-gray-300 hover:bg-gray-400 text-gray-700 font-semibold text-xs uppercase tracking-widest rounded-md transition">
                                Cancel
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            
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
                        <!-- Pricing Details Card -->
                        <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-6 mb-6">
                            <h3 class="text-lg font-semibold mb-4">Pricing Details</h3>
                            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                                <div>
                                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Buy Price</dt>
                                    <dd class="mt-1 text-lg font-semibold text-gray-900 dark:text-gray-100">€{{ number_format($product->PRICEBUY, 2) }}</dd>
                                </div>
                                <div>
                                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Net Sell Price</dt>
                                    <dd class="mt-1 text-lg font-semibold text-gray-900 dark:text-gray-100">€{{ $product->formatted_price }}</dd>
                                </div>
                                <div>
                                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">VAT Amount</dt>
                                    <dd class="mt-1 text-lg font-semibold text-gray-900 dark:text-gray-100">€{{ number_format($product->getVatAmount(), 2) }}</dd>
                                </div>
                                <div>
                                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Margin</dt>
                                    <dd class="mt-1 text-lg font-semibold {{ $product->PRICESELL > $product->PRICEBUY ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400' }}">
                                        €{{ number_format($product->PRICESELL - $product->PRICEBUY, 2) }}
                                    </dd>
                                </div>
                            </div>
                        </div>

                        <!-- Supplier External Information (if available) -->
                        <x-supplier-external-info :product="$product" :supplier-service="$supplierService" />

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-6">
                            <!-- Product Information -->
                            <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-6">
                                <h3 class="text-lg font-semibold mb-4">Product Information</h3>
                                <dl class="space-y-3">
                                    <div>
                                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Product ID</dt>
                                        <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100 font-mono text-xs">{{ $product->ID }}</dd>
                                    </div>
                                    <div>
                                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Warranty</dt>
                                        <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ $product->WARRANTY }} days</dd>
                                    </div>
                                    <div>
                                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Stock Cost</dt>
                                        <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100">€{{ number_format($product->STOCKCOST, 2) }}</dd>
                                    </div>
                                </dl>
                            </div>

                            <!-- Product Attributes -->
                            <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-6">
                                <h3 class="text-lg font-semibold mb-4">Product Attributes</h3>
                                <div class="grid grid-cols-2 gap-3">
                                    <label class="flex items-center">
                                        <input type="checkbox" {{ $product->ISCOM ? 'checked' : '' }} disabled class="rounded border-gray-300 text-indigo-600">
                                        <span class="ml-2 text-sm text-gray-600 dark:text-gray-400">Commission</span>
                                    </label>
                                    <label class="flex items-center">
                                        <input type="checkbox" {{ $product->ISSCALE ? 'checked' : '' }} disabled class="rounded border-gray-300 text-indigo-600">
                                        <span class="ml-2 text-sm text-gray-600 dark:text-gray-400">Sold by Weight</span>
                                    </label>
                                    <label class="flex items-center">
                                        <input type="checkbox" {{ $product->ISKITCHEN ? 'checked' : '' }} disabled class="rounded border-gray-300 text-indigo-600">
                                        <span class="ml-2 text-sm text-gray-600 dark:text-gray-400">Kitchen Item</span>
                                    </label>
                                    <label class="flex items-center">
                                        <input type="checkbox" {{ $product->PRINTKB ? 'checked' : '' }} disabled class="rounded border-gray-300 text-indigo-600">
                                        <span class="ml-2 text-sm text-gray-600 dark:text-gray-400">Print to Kitchen</span>
                                    </label>
                                    <label class="flex items-center">
                                        <input type="checkbox" {{ $product->SENDSTATUS ? 'checked' : '' }} disabled class="rounded border-gray-300 text-indigo-600">
                                        <span class="ml-2 text-sm text-gray-600 dark:text-gray-400">Send Status</span>
                                    </label>
                                    <label class="flex items-center">
                                        <input type="checkbox" {{ $product->ISSERVICE ? 'checked' : '' }} disabled class="rounded border-gray-300 text-indigo-600">
                                        <span class="ml-2 text-sm text-gray-600 dark:text-gray-400">Service</span>
                                    </label>
                                    <label class="flex items-center">
                                        <input type="checkbox" {{ $product->ISVPRICE ? 'checked' : '' }} disabled class="rounded border-gray-300 text-indigo-600">
                                        <span class="ml-2 text-sm text-gray-600 dark:text-gray-400">Variable Price</span>
                                    </label>
                                    <label class="flex items-center">
                                        <input type="checkbox" {{ $product->ISVERPATRIB ? 'checked' : '' }} disabled class="rounded border-gray-300 text-indigo-600">
                                        <span class="ml-2 text-sm text-gray-600 dark:text-gray-400">Variable Attributes</span>
                                    </label>
                                </div>
                            </div>
                        </div>

                        <!-- Tax Assignment -->
                        <div class="mt-6 bg-gray-50 dark:bg-gray-700 rounded-lg p-6">
                            <h3 class="text-lg font-semibold mb-4">Tax Configuration</h3>
                            <form method="POST" action="{{ route('products.update-tax', $product->ID) }}" class="flex items-end gap-4">
                                @csrf
                                @method('PATCH')
                                <div class="flex-1">
                                    <label for="tax_category" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Tax Category</label>
                                    <select name="tax_category" id="tax_category" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600">
                                        @foreach($taxCategories as $category)
                                            <option value="{{ $category->ID }}" {{ $product->TAXCAT == $category->ID ? 'selected' : '' }}>
                                                {{ $category->NAME }} ({{ $category->primaryTax?->formatted_rate ?? '0%' }})
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <button type="submit" class="inline-flex items-center px-4 py-2 bg-gray-800 dark:bg-gray-200 border border-transparent rounded-md font-semibold text-xs text-white dark:text-gray-800 uppercase tracking-widest hover:bg-gray-700 dark:hover:bg-white focus:bg-gray-700 dark:focus:bg-white active:bg-gray-900 dark:active:bg-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition ease-in-out duration-150">
                                    Update Tax
                                </button>
                            </form>
                        </div>

                        <!-- Additional Information -->
                        @if($product->TEXTTIP || $product->DISPLAY)
                            <div class="mt-6 bg-gray-50 dark:bg-gray-700 rounded-lg p-6">
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
                        <!-- Product Info Header -->
                        <div class="mb-6 pb-6 border-b border-gray-200 dark:border-gray-700">
                            <h2 class="text-2xl font-bold text-gray-900 dark:text-gray-100 mb-2">{{ $product->NAME }}</h2>
                            <div class="flex items-center gap-6 text-sm text-gray-600 dark:text-gray-400">
                                <span>SKU: {{ $product->CODE }}</span>
                                <span>Current Price: <strong class="text-gray-900 dark:text-gray-100">{{ $product->formatted_price_with_vat }}</strong></span>
                                <span>VAT Rate: <strong class="text-gray-900 dark:text-gray-100">{{ $product->formatted_vat_rate }}</strong></span>
                            </div>
                        </div>

                        <!-- Time Period Selection -->
                        <div class="flex flex-wrap gap-2 mb-6" id="timePeriodButtons">
                            <button onclick="loadSalesData(4)" class="period-btn active px-4 py-2 text-sm font-medium rounded-md bg-indigo-600 text-white">
                                Last 4 Months
                            </button>
                            <button onclick="loadSalesData(6)" class="period-btn px-4 py-2 text-sm font-medium rounded-md bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300 border border-gray-300 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-700">
                                Last 6 Months
                            </button>
                            <button onclick="loadSalesData(12)" class="period-btn px-4 py-2 text-sm font-medium rounded-md bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300 border border-gray-300 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-700">
                                Last 12 Months
                            </button>
                            <button onclick="loadSalesData('ytd')" class="period-btn px-4 py-2 text-sm font-medium rounded-md bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300 border border-gray-300 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-700">
                                Year to Date
                            </button>
                        </div>

                        <!-- Chart Container -->
                        <div class="bg-white dark:bg-gray-800 p-4 rounded-lg shadow mb-6">
                            <div class="relative" style="height: 300px;">
                                <canvas id="salesChart"></canvas>
                                <div id="chartLoading" class="hidden absolute inset-0 bg-white dark:bg-gray-800 bg-opacity-75 flex items-center justify-center">
                                    <div class="text-center">
                                        <svg class="animate-spin h-8 w-8 text-indigo-600 mx-auto" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                        </svg>
                                        <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">Loading sales data...</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        @if(isset($salesHistory) && count($salesHistory) > 0)
                            <!-- Sales Statistics Cards -->
                            @if(isset($salesStats))
                                <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
                                    <div class="bg-gray-50 dark:bg-gray-700 p-4 rounded-lg">
                                        <div class="text-sm font-medium text-gray-500 dark:text-gray-400">Total Sales (12m)</div>
                                        <div class="mt-1 text-2xl font-semibold text-gray-900 dark:text-gray-100" data-stat="total_sales_12m">{{ number_format($salesStats['total_sales_12m'], 0) }}</div>
                                    </div>
                                    <div class="bg-gray-50 dark:bg-gray-700 p-4 rounded-lg">
                                        <div class="text-sm font-medium text-gray-500 dark:text-gray-400">Avg Monthly</div>
                                        <div class="mt-1 text-2xl font-semibold text-gray-900 dark:text-gray-100" data-stat="avg_monthly_sales">{{ number_format($salesStats['avg_monthly_sales'], 1) }}</div>
                                    </div>
                                    <div class="bg-gray-50 dark:bg-gray-700 p-4 rounded-lg">
                                        <div class="text-sm font-medium text-gray-500 dark:text-gray-400">This Month</div>
                                        <div class="mt-1 text-2xl font-semibold text-gray-900 dark:text-gray-100" data-stat="this_month_sales">{{ number_format($salesStats['this_month_sales'], 0) }}</div>
                                    </div>
                                    <div class="bg-gray-50 dark:bg-gray-700 p-4 rounded-lg">
                                        <div class="text-sm font-medium text-gray-500 dark:text-gray-400">Trend</div>
                                        <div class="mt-1 text-2xl font-semibold" data-stat="trend">
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
                            <h3 class="text-lg font-semibold mb-4">Sales by Month</h3>
                            <div class="overflow-x-auto">
                                <table id="salesTable" class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
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

    @push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        const vatRate = {{ $product->getVatRate() }};
        
        function togglePriceEdit() {
            const form = document.getElementById('priceEditForm');
            form.classList.toggle('hidden');
        }
        
        function calculateGrossPrice() {
            const netPrice = parseFloat(document.getElementById('net_price').value) || 0;
            const vatAmount = netPrice * vatRate;
            const grossPrice = netPrice + vatAmount;
            
            document.getElementById('vatAmount').textContent = '€' + vatAmount.toFixed(2);
            document.getElementById('grossPrice').textContent = '€' + grossPrice.toFixed(2);
        }
        
        function confirmPriceUpdate() {
            const originalPrice = {{ $product->PRICESELL }};
            const newPrice = parseFloat(document.getElementById('net_price').value);
            
            if (Math.abs(originalPrice - newPrice) < 0.01) {
                alert('Price has not changed.');
                return false;
            }
            
            return confirm(`Update price from €${originalPrice.toFixed(2)} to €${newPrice.toFixed(2)}?`);
        }

        let salesChart = null;
        const productId = '{{ $product->ID }}';
        
        // Initialize chart with existing data
        document.addEventListener('DOMContentLoaded', function() {
            @if(isset($salesHistory) && count($salesHistory) > 0)
                const initialData = @json(array_values($salesHistory));
                createChart(initialData);
            @endif
        });

        function createChart(salesData) {
            const ctx = document.getElementById('salesChart').getContext('2d');
            
            // Destroy existing chart if it exists
            if (salesChart) {
                salesChart.destroy();
            }
            
            // Prepare data
            const labels = salesData.map(item => item.month_short + ' ' + item.year);
            const data = salesData.map(item => item.units);
            
            // Create gradient
            const gradient = ctx.createLinearGradient(0, 0, 0, 250);
            gradient.addColorStop(0, 'rgba(99, 102, 241, 0.8)');
            gradient.addColorStop(1, 'rgba(99, 102, 241, 0.1)');
            
            salesChart = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'Units Sold',
                        data: data,
                        backgroundColor: gradient,
                        borderColor: 'rgb(99, 102, 241)',
                        borderWidth: 2,
                        borderRadius: 8,
                        borderSkipped: false,
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            backgroundColor: 'rgba(0, 0, 0, 0.8)',
                            padding: 12,
                            titleColor: 'white',
                            bodyColor: 'white',
                            borderColor: 'rgb(99, 102, 241)',
                            borderWidth: 1,
                            displayColors: false,
                            callbacks: {
                                label: function(context) {
                                    return 'Units Sold: ' + context.parsed.y.toFixed(1);
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            grid: {
                                color: 'rgba(156, 163, 175, 0.1)'
                            },
                            ticks: {
                                color: 'rgb(107, 114, 128)'
                            }
                        },
                        x: {
                            grid: {
                                display: false
                            },
                            ticks: {
                                color: 'rgb(107, 114, 128)'
                            }
                        }
                    },
                    animation: {
                        duration: 750,
                        easing: 'easeInOutQuart'
                    }
                }
            });
        }

        function loadSalesData(period) {
            // Update button states
            document.querySelectorAll('.period-btn').forEach(btn => {
                btn.classList.remove('active', 'bg-indigo-600', 'text-white');
                btn.classList.add('bg-white', 'dark:bg-gray-800', 'text-gray-700', 'dark:text-gray-300', 'border', 'border-gray-300', 'dark:border-gray-600');
            });
            event.target.classList.remove('bg-white', 'dark:bg-gray-800', 'text-gray-700', 'dark:text-gray-300', 'border', 'border-gray-300', 'dark:border-gray-600');
            event.target.classList.add('active', 'bg-indigo-600', 'text-white');
            
            // Show loading
            document.getElementById('chartLoading').classList.remove('hidden');
            
            // Fetch data
            fetch(`/products/${productId}/sales-data?period=${period}`, {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                }
            })
            .then(response => response.json())
            .then(data => {
                createChart(data.salesHistory);
                updateStatistics(data.salesStats);
                updateTable(data.salesHistory);
                document.getElementById('chartLoading').classList.add('hidden');
            })
            .catch(error => {
                console.error('Error loading sales data:', error);
                document.getElementById('chartLoading').classList.add('hidden');
                alert('Failed to load sales data. Please try again.');
            });
        }

        function updateStatistics(stats) {
            // Update statistics cards if they exist
            const statsElements = {
                'total_sales_12m': document.querySelector('[data-stat="total_sales_12m"]'),
                'avg_monthly_sales': document.querySelector('[data-stat="avg_monthly_sales"]'),
                'this_month_sales': document.querySelector('[data-stat="this_month_sales"]'),
                'trend': document.querySelector('[data-stat="trend"]')
            };
            
            if (statsElements.total_sales_12m) {
                statsElements.total_sales_12m.textContent = stats.total_sales_12m.toLocaleString();
            }
            if (statsElements.avg_monthly_sales) {
                statsElements.avg_monthly_sales.textContent = stats.avg_monthly_sales.toFixed(1);
            }
            if (statsElements.this_month_sales) {
                statsElements.this_month_sales.textContent = stats.this_month_sales.toLocaleString();
            }
            if (statsElements.trend) {
                const trendHtml = stats.trend === 'up' 
                    ? '<span class="text-green-600 dark:text-green-400">↑ Up</span>'
                    : stats.trend === 'down'
                    ? '<span class="text-red-600 dark:text-red-400">↓ Down</span>'
                    : '<span class="text-gray-600 dark:text-gray-400">→ Stable</span>';
                statsElements.trend.innerHTML = trendHtml;
            }
        }

        function updateTable(salesData) {
            // Update the sales table
            const tbody = document.querySelector('#salesTable tbody');
            if (!tbody) return;
            
            tbody.innerHTML = '';
            let previousUnits = null;
            
            salesData.forEach(monthData => {
                const row = document.createElement('tr');
                
                // Month cell
                const monthCell = document.createElement('td');
                monthCell.className = 'px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-gray-100';
                monthCell.textContent = monthData.month;
                row.appendChild(monthCell);
                
                // Units cell
                const unitsCell = document.createElement('td');
                unitsCell.className = 'px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100';
                unitsCell.textContent = monthData.units.toFixed(1);
                row.appendChild(unitsCell);
                
                // Trend cell
                const trendCell = document.createElement('td');
                trendCell.className = 'px-6 py-4 whitespace-nowrap text-sm';
                
                if (previousUnits !== null) {
                    if (monthData.units > previousUnits) {
                        const percent = ((monthData.units - previousUnits) / Math.max(previousUnits, 1)) * 100;
                        trendCell.innerHTML = `<span class="text-green-600 dark:text-green-400">↑ ${percent.toFixed(1)}%</span>`;
                    } else if (monthData.units < previousUnits) {
                        const percent = ((previousUnits - monthData.units) / Math.max(previousUnits, 1)) * 100;
                        trendCell.innerHTML = `<span class="text-red-600 dark:text-red-400">↓ ${percent.toFixed(1)}%</span>`;
                    } else {
                        trendCell.innerHTML = '<span class="text-gray-600 dark:text-gray-400">→ 0%</span>';
                    }
                } else {
                    trendCell.innerHTML = '<span class="text-gray-400 dark:text-gray-500">-</span>';
                }
                
                row.appendChild(trendCell);
                tbody.appendChild(row);
                
                previousUnits = monthData.units;
            });
        }
    </script>
    @endpush
</x-admin-layout>