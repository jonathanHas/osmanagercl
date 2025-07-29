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
            @php
                $productActions = [
                    [
                        'type' => 'button',
                        'onclick' => "requeueProduct('{$product->ID}', this)",
                        'label' => 'Add Back to Products Needing Labels',
                        'color' => 'green',
                        'class' => 'inline-flex items-center px-4 py-2 bg-green-600 hover:bg-green-700 text-white font-semibold text-xs uppercase tracking-widest rounded-md transition',
                        'icon' => 'M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15'
                    ],
                    [
                        'type' => 'link',
                        'route' => 'products.print-label',
                        'params' => $product->ID,
                        'label' => 'Print Label',
                        'color' => 'indigo',
                        'class' => 'inline-flex items-center px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white font-semibold text-xs uppercase tracking-widest rounded-md transition',
                        'icon' => 'M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z',
                        'target' => '_blank'
                    ]
                ];

                // Add navigation button based on context
                if ($fromDelivery) {
                    $productActions[] = [
                        'type' => 'link',
                        'route' => 'deliveries.show',
                        'params' => ['delivery' => $fromDelivery],
                        'label' => 'Back to Delivery',
                        'color' => 'secondary',
                        'class' => 'inline-flex items-center px-4 py-2 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-500 rounded-md font-semibold text-xs text-gray-700 dark:text-gray-300 uppercase tracking-widest shadow-sm hover:bg-gray-50 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 disabled:opacity-25 transition ease-in-out duration-150',
                        'icon' => 'M10 19l-7-7m0 0l7-7m-7 7h18'
                    ];
                } else {
                    $productActions[] = [
                        'type' => 'link',
                        'route' => 'products.index',
                        'label' => 'Back to Products',
                        'color' => 'secondary',
                        'class' => 'inline-flex items-center px-4 py-2 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-500 rounded-md font-semibold text-xs text-gray-700 dark:text-gray-300 uppercase tracking-widest shadow-sm hover:bg-gray-50 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 disabled:opacity-25 transition ease-in-out duration-150',
                        'icon' => 'M10 19l-7-7m0 0l7-7m-7 7h18'
                    ];
                }
            @endphp
            
            <x-action-buttons :actions="$productActions" spacing="tight" size="lg" />
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <x-alert type="success" :message="session('success')" />

            <!-- Quick Stats Bar -->
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg mb-6">
                <div class="p-6">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
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
                            @if($product->category)
                                <div class="space-y-1">
                                    <span class="inline-flex items-center px-3 py-1 text-lg font-semibold rounded-full bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200">
                                        {{ $product->category->NAME }}
                                    </span>
                                    @if($product->category->parent)
                                        <p class="text-sm text-gray-500 dark:text-gray-400">
                                            Path: {{ $product->category_path }}
                                        </p>
                                    @endif
                                </div>
                            @else
                                <span class="inline-flex items-center px-3 py-1 text-lg font-semibold rounded-full bg-gray-100 text-gray-500 dark:bg-gray-700 dark:text-gray-400">
                                    Uncategorized
                                </span>
                            @endif
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
            
            <!-- Product Details Tabs -->
            <x-tab-group 
                :tabs="[
                    ['id' => 'overview', 'label' => 'Overview'],
                    ['id' => 'sales', 'label' => 'Sales History'],
                    ['id' => 'stock', 'label' => 'Stock Movement']
                ]"
                containerClass="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                
                <x-slot name="overview">
                    <div class="p-6 text-gray-900 dark:text-gray-100">
                        <!-- Consolidated Pricing Section -->
                        <x-product-pricing-section :product="$product" :supplier-service="$supplierService" :udea-pricing="$udeaPricing" />
                        
                        <!-- Supplier Information Card -->
                        <x-supplier-info-card :product="$product" :supplier-service="$supplierService" />

                        <!-- Product Details & Configuration -->
                        <div class="mt-6 bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                            <div class="p-6">
                                <h3 class="text-lg font-semibold mb-4 flex items-center">
                                    <svg class="w-5 h-5 mr-2 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                    </svg>
                                    Product Details & Configuration
                                </h3>
                                
                                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                                    <!-- Basic Information -->
                                    <div>
                                        <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-3">Basic Information</h4>
                                        <dl class="space-y-2">
                                            <div>
                                                <dt class="text-xs text-gray-500 dark:text-gray-400">Product ID</dt>
                                                <dd class="text-sm text-gray-900 dark:text-gray-100 font-mono">{{ $product->ID }}</dd>
                                            </div>
                                            <div>
                                                <dt class="text-xs text-gray-500 dark:text-gray-400">Warranty Period</dt>
                                                <dd class="text-sm text-gray-900 dark:text-gray-100">{{ $product->WARRANTY }} days</dd>
                                            </div>
                                            <div>
                                                <dt class="text-xs text-gray-500 dark:text-gray-400">Stock Cost</dt>
                                                <dd class="text-sm text-gray-900 dark:text-gray-100">€{{ number_format($product->STOCKCOST, 2) }}</dd>
                                            </div>
                                        </dl>
                                    </div>
                                    
                                    <!-- POS Configuration -->
                                    <div>
                                        <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-3">POS Configuration</h4>
                                        <div class="space-y-2">
                                            @php
                                                $posConfigs = [
                                                    ['field' => 'ISSCALE', 'label' => 'Sold by Weight', 'icon' => 'M3 6l3 1m0 0l-3 9a5.002 5.002 0 006.001 0M6 7l3 9M6 7l6-2m6 2l3-1m-3 1l-3 9a5.002 5.002 0 006.001 0M18 7l3 9m-3-9l-6-2m0-2v2m0 16V5m0 16H9m3 0h3'],
                                                    ['field' => 'ISKITCHEN', 'label' => 'Kitchen Item', 'icon' => 'M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4'],
                                                    ['field' => 'PRINTKB', 'label' => 'Print to Kitchen', 'icon' => 'M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z'],
                                                    ['field' => 'ISSERVICE', 'label' => 'Service Item', 'icon' => 'M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2-2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z'],
                                                ];
                                            @endphp
                                            @foreach($posConfigs as $config)
                                                <div class="flex items-center justify-between">
                                                    <span class="text-sm text-gray-600 dark:text-gray-400">{{ $config['label'] }}</span>
                                                    @if($product->{$config['field']})
                                                        <svg class="w-5 h-5 text-green-500" fill="currentColor" viewBox="0 0 20 20">
                                                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                                        </svg>
                                                    @else
                                                        <svg class="w-5 h-5 text-gray-300 dark:text-gray-600" fill="currentColor" viewBox="0 0 20 20">
                                                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                                                        </svg>
                                                    @endif
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                    
                                    <!-- Additional Settings -->
                                    <div>
                                        <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-3">Additional Settings</h4>
                                        <div class="space-y-2">
                                            @php
                                                $additionalConfigs = [
                                                    ['field' => 'ISCOM', 'label' => 'Commission Product'],
                                                    ['field' => 'SENDSTATUS', 'label' => 'Send Status Updates'],
                                                    ['field' => 'ISVPRICE', 'label' => 'Variable Pricing'],
                                                    ['field' => 'ISVERPATRIB', 'label' => 'Variable Attributes'],
                                                ];
                                            @endphp
                                            @foreach($additionalConfigs as $config)
                                                <div class="flex items-center justify-between">
                                                    <span class="text-sm text-gray-600 dark:text-gray-400">{{ $config['label'] }}</span>
                                                    @if($product->{$config['field']})
                                                        <span class="text-xs font-medium text-green-600 dark:text-green-400">Active</span>
                                                    @else
                                                        <span class="text-xs text-gray-400 dark:text-gray-500">Inactive</span>
                                                    @endif
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
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
                </x-slot>
                
                <x-slot name="sales">
                    <div class="p-6 text-gray-900 dark:text-gray-100">
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
                        @php
                            $timePeriodButtons = [
                                [
                                    'label' => 'Last 4 Months',
                                    'action' => 'onclick=loadSalesData(4)',
                                    'type' => 'button',
                                    'color' => 'indigo',
                                    'class' => 'period-btn active',
                                ],
                                [
                                    'label' => 'Last 6 Months',
                                    'action' => 'onclick=loadSalesData(6)',
                                    'type' => 'button',
                                    'color' => 'secondary',
                                    'class' => 'period-btn',
                                ],
                                [
                                    'label' => 'Last 12 Months',
                                    'action' => 'onclick=loadSalesData(12)',
                                    'type' => 'button',
                                    'color' => 'secondary',
                                    'class' => 'period-btn',
                                ],
                                [
                                    'label' => 'Year to Date',
                                    'action' => 'onclick=loadSalesData(\'ytd\')',
                                    'type' => 'button',
                                    'color' => 'secondary',
                                    'class' => 'period-btn',
                                ]
                            ];
                        @endphp
                        <div class="mb-6" id="timePeriodButtons">
                            <x-action-buttons :buttons="$timePeriodButtons" spacing="compact" />
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
                </x-slot>
                
                <x-slot name="stock">
                    <div class="p-6 text-gray-900 dark:text-gray-100">
                        <div class="text-center py-12">
                            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4" />
                            </svg>
                            <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-gray-100">Stock Movement</h3>
                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Stock movement tracking coming soon.</p>
                        </div>
                    </div>
                </x-slot>
            </x-tab-group>
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

        // Udea Price Refresh Functionality
        function refreshUdeaPricing() {
            const button = document.getElementById('refreshPricingBtn');
            const originalContent = button.innerHTML;
            
            // Show loading state
            button.disabled = true;
            button.innerHTML = '<svg class="animate-spin w-4 h-4 mr-2" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>Refreshing...';
            
            fetch(`/products/${productId}/refresh-udea-pricing`, {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Reload the page to show updated pricing
                    window.location.reload();
                } else {
                    alert('Failed to refresh pricing: ' + (data.error || 'Unknown error'));
                }
            })
            .catch(error => {
                console.error('Error refreshing pricing:', error);
                alert('Network error while refreshing pricing. Please try again.');
            })
            .finally(() => {
                // Restore button state
                button.disabled = false;
                button.innerHTML = originalContent;
            });
        }

        // Update Product Cost
        function updateProductCost(newCost) {
            if (!confirm(`Update product cost to €${parseFloat(newCost).toFixed(2)}?`)) {
                return;
            }

            const form = document.createElement('form');
            form.method = 'POST';
            form.action = `/products/${productId}/cost`;
            
            // Add CSRF token
            const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
            const csrfInput = document.createElement('input');
            csrfInput.type = 'hidden';
            csrfInput.name = '_token';
            csrfInput.value = csrfToken;
            form.appendChild(csrfInput);
            
            // Add method override for PATCH
            const methodInput = document.createElement('input');
            methodInput.type = 'hidden';
            methodInput.name = '_method';
            methodInput.value = 'PATCH';
            form.appendChild(methodInput);
            
            // Add cost price
            const costInput = document.createElement('input');
            costInput.type = 'hidden';
            costInput.name = 'cost_price';
            costInput.value = newCost;
            form.appendChild(costInput);
            
            document.body.appendChild(form);
            form.submit();
        }

        // Update Product Price
        function updateProductPrice(newPrice) {
            if (!confirm(`Update product selling price to €${parseFloat(newPrice).toFixed(2)} (net)?`)) {
                return;
            }

            const form = document.createElement('form');
            form.method = 'POST';
            form.action = `/products/${productId}/price`;
            
            // Add CSRF token
            const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
            const csrfInput = document.createElement('input');
            csrfInput.type = 'hidden';
            csrfInput.name = '_token';
            csrfInput.value = csrfToken;
            form.appendChild(csrfInput);
            
            // Add method override for PATCH
            const methodInput = document.createElement('input');
            methodInput.type = 'hidden';
            methodInput.name = '_method';
            methodInput.value = 'PATCH';
            form.appendChild(methodInput);
            
            // Add net price
            const priceInput = document.createElement('input');
            priceInput.type = 'hidden';
            priceInput.name = 'net_price';
            priceInput.value = newPrice;
            form.appendChild(priceInput);
            
            document.body.appendChild(form);
            form.submit();
        }

        // Calculate net price from VAT-inclusive price
        function calculateNetFromGross() {
            const grossPrice = parseFloat(document.getElementById('quickPriceUpdateVat').value) || 0;
            const netPrice = grossPrice / (1 + vatRate);
            document.getElementById('calculatedNetPrice').textContent = netPrice.toFixed(2);
        }

        // Update Product Price from VAT-inclusive input
        function updateProductPriceFromVat() {
            const grossPrice = parseFloat(document.getElementById('quickPriceUpdateVat').value);
            const netPrice = grossPrice / (1 + vatRate);
            
            if (!confirm(`Update product selling price to €${grossPrice.toFixed(2)} (incl. VAT) / €${netPrice.toFixed(2)} (net)?`)) {
                return;
            }

            const form = document.createElement('form');
            form.method = 'POST';
            form.action = `/products/${productId}/price`;
            
            // Add CSRF token
            const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
            const csrfInput = document.createElement('input');
            csrfInput.type = 'hidden';
            csrfInput.name = '_token';
            csrfInput.value = csrfToken;
            form.appendChild(csrfInput);
            
            // Add method override for PATCH
            const methodInput = document.createElement('input');
            methodInput.type = 'hidden';
            methodInput.name = '_method';
            methodInput.value = 'PATCH';
            form.appendChild(methodInput);
            
            // Add net price (calculated from gross) - use full precision
            const priceInput = document.createElement('input');
            priceInput.type = 'hidden';
            priceInput.name = 'net_price';
            priceInput.value = netPrice;
            form.appendChild(priceInput);
            
            document.body.appendChild(form);
            form.submit();
        }

        // Requeue product function
        function requeueProduct(productId, buttonElement) {
            const button = buttonElement || event?.target?.closest('button');
            
            fetch('/labels/requeue', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                },
                body: JSON.stringify({ product_id: productId })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Show success message briefly
                    if (button) {
                        const originalText = button.innerHTML;
                        button.innerHTML = '<svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>Added Back!';
                        button.disabled = true;
                        
                        setTimeout(() => {
                            button.innerHTML = originalText;
                            button.disabled = false;
                        }, 2000);
                    } else {
                        alert('Product added back to Products Needing Labels!');
                    }
                } else {
                    alert('Error: ' + (data.message || data.error));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error adding product back to labels list');
            });
        }
    </script>
    @endpush
</x-admin-layout>