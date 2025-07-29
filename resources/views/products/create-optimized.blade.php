<x-admin-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                Create New Product
            </h2>
            <x-action-buttons :actions="[
                [
                    'type' => 'link',
                    'route' => 'products.index',
                    'label' => 'Back to Products',
                    'color' => 'secondary',
                    'class' => 'inline-flex items-center px-4 py-2 bg-gray-600 hover:bg-gray-700 text-white font-medium rounded-md transition-colors duration-200',
                    'icon' => 'M10 19l-7-7m0 0l7-7m-7 7h18'
                ]
            ]" size="lg" />
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <x-alert type="error" :messages="$errors->all()" />

            <form action="{{ route('products.store') }}" method="POST">
                @csrf
                
                @if($deliveryItemId)
                    <input type="hidden" name="delivery_item_id" value="{{ $deliveryItemId }}">
                    <div class="bg-blue-50 border border-blue-200 text-blue-700 px-4 py-3 rounded mb-6">
                        <div class="font-bold">Creating product from delivery item</div>
                        <p class="mt-1">Some fields have been pre-populated from the delivery information.</p>
                    </div>
                @endif

                <!-- Main Content Grid -->
                <div class="grid grid-cols-1 xl:grid-cols-2 gap-6">
                    <!-- Left Column -->
                    <div class="space-y-6">
                        <!-- Basic Information Section -->
                        <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6">
                            <div class="border-b border-gray-200 dark:border-gray-700 pb-3 mb-4">
                                <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Basic Information</h3>
                            </div>

                            <div class="space-y-4">
                                <!-- Product Name -->
                                <div>
                                    @php
                                        $nameLabel = 'Product Name *';
                                        if(isset($prefillData['delivery_name'])) {
                                            $nameLabel .= ' <span class="ml-2 inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200">
                                                <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                                    <path fill-rule="evenodd" d="M4 2a1 1 0 011 1v2.101a7.002 7.002 0 0111.601 2.566 1 1 0 11-1.885.666A5.002 5.002 0 005.999 7H9a1 1 0 010 2H4a1 1 0 01-1-1V3a1 1 0 011-1zm.008 9.057a1 1 0 011.276.61A5.002 5.002 0 0014.001 13H11a1 1 0 110-2h5a1 1 0 011 1v5a1 1 0 11-2 0v-2.101a7.002 7.002 0 01-11.601-2.566 1 1 0 01.61-1.276z" clip-rule="evenodd"/>
                                                </svg>
                                                From Delivery
                                            </span>';
                                        }
                                    @endphp
                                    <x-form-group 
                                        name="name" 
                                        label="{!! $nameLabel !!}" 
                                        type="text" 
                                        :value="old('name', $prefillData['name'] ?? '')" 
                                        required 
                                        placeholder="Enter product name" 
                                        containerClass="" />
                                    
                                    @if(isset($prefillData['scraped_name']) && $prefillData['scraped_name'] !== $prefillData['name'])
                                        <div class="mt-3 p-3 bg-green-50 border border-green-200 rounded-lg dark:bg-green-900/20 dark:border-green-800">
                                            <div class="flex items-start justify-between">
                                                <div class="flex-1">
                                                    <div class="flex items-center mb-2">
                                                        <svg class="w-4 h-4 text-green-600 dark:text-green-400 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                                        </svg>
                                                        <h4 class="text-sm font-semibold text-green-800 dark:text-green-200">Enhanced Product Name Available</h4>
                                                    </div>
                                                    <p class="text-sm text-green-700 dark:text-green-300 mb-2">
                                                        UDEA website provides a more complete product name:
                                                    </p>
                                                    <div class="bg-white dark:bg-gray-800 p-2 rounded border border-green-300 dark:border-green-700">
                                                        <span class="text-sm font-medium text-gray-900 dark:text-gray-100" id="scraped-name-display">{{ $prefillData['scraped_name'] }}</span>
                                                    </div>
                                                </div>
                                                <button type="button" 
                                                        id="use-scraped-name" 
                                                        class="ml-3 inline-flex items-center px-3 py-2 border border-green-300 shadow-sm text-sm font-medium rounded-md text-green-700 bg-white hover:bg-green-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 dark:bg-gray-700 dark:text-green-200 dark:border-green-600 dark:hover:bg-gray-600 transition-colors duration-200"
                                                        data-scraped-name="{{ $prefillData['scraped_name'] }}">
                                                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16l-4-4m0 0l4-4m-4 4h18"/>
                                                    </svg>
                                                    Use This Name
                                                </button>
                                            </div>
                                        </div>
                                    @endif
                                    
                                    <!-- UDEA Website Link -->
                                    @if(isset($prefillData['supplier_code']) || old('supplier_code'))
                                        <div class="mt-3">
                                            <a href="https://www.udea.nl/search/?qry={{ urlencode($prefillData['supplier_code'] ?? old('supplier_code')) }}" 
                                               target="_blank" 
                                               class="inline-flex items-center px-3 py-2 border border-blue-300 shadow-sm text-sm font-medium rounded-md text-blue-700 bg-blue-50 hover:bg-blue-100 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 dark:bg-blue-900/20 dark:text-blue-200 dark:border-blue-600 dark:hover:bg-blue-800/30 transition-colors duration-200">
                                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
                                                </svg>
                                                View on UDEA Website
                                            </a>
                                        </div>
                                    @endif
                                </div>

                                <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                                    <!-- Product Code/Barcode -->
                                    <x-form-group 
                                        name="code" 
                                        label="Product Code/Barcode *" 
                                        type="text" 
                                        :value="old('code', $prefillData['code'] ?? '')" 
                                        required 
                                        placeholder="Enter code or scan" />

                                    <!-- Reference Code -->  
                                    <x-form-group 
                                        name="reference" 
                                        label="Reference Code" 
                                        type="text" 
                                        :value="old('reference')" 
                                        placeholder="Optional reference" />
                                </div>

                                <!-- Product Category -->
                                <div>
                                    <label for="category" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Product Category
                                    </label>
                                    <select id="category" 
                                            name="category"
                                            class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 focus:border-indigo-500 focus:ring-indigo-500">
                                        <option value="">No category</option>
                                        @foreach($categories as $category)
                                            <option value="{{ $category->ID }}" {{ old('category') === $category->ID ? 'selected' : '' }}>
                                                {{ $category->NAME }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                        </div>

                        <!-- Pricing & Tax Section -->
                        <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6">
                            <div class="border-b border-gray-200 dark:border-gray-700 pb-3 mb-4">
                                <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Pricing & Tax</h3>
                            </div>

                            <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
                                <!-- Cost Price -->
                                <x-form-group 
                                    name="price_buy" 
                                    label="Cost Price (€) *" 
                                    type="number" 
                                    :value="old('price_buy', $prefillData['price_buy'] ?? '')" 
                                    required 
                                    step="0.01" 
                                    min="0" />

                                <!-- Selling Price -->
                                <div>
                                    <label for="price_sell" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Selling Price (€) *
                                        @if(isset($prefillData['price_source']) && $prefillData['price_source'] === 'udea_scraped')
                                            <span class="ml-2 inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">
                                                <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                                </svg>
                                                UDEA Price
                                            </span>
                                        @elseif(isset($prefillData['price_source']) && $prefillData['price_source'] === 'calculated')
                                            <span class="ml-2 inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200">
                                                <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                                    <path fill-rule="evenodd" d="M6 2a1 1 0 00-1 1v1H4a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V6a2 2 0 00-2-2h-1V3a1 1 0 10-2 0v1H7V3a1 1 0 00-1-1zm0 5a1 1 0 000 2h8a1 1 0 100-2H6z" clip-rule="evenodd"/>
                                                </svg>
                                                Calculated (+30%)
                                            </span>
                                        @endif
                                    </label>
                                    <input type="number" 
                                           id="price_sell" 
                                           name="price_sell" 
                                           value="{{ old('price_sell', $prefillData['price_sell_suggested'] ?? (isset($prefillData['price_buy']) ? number_format($prefillData['price_buy'] * 1.3, 4) : '')) }}" 
                                           step="0.0001" 
                                           min="0" 
                                           required
                                           class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 focus:border-indigo-500 focus:ring-indigo-500">
                                    @if(isset($prefillData['price_source']) && $prefillData['price_source'] === 'udea_scraped')
                                        <p class="mt-1 text-sm text-green-600 dark:text-green-400">
                                            Price automatically retrieved from UDEA website
                                            @if(isset($prefillData['scraped_data']['scraped_at']))
                                                (Updated: {{ \Carbon\Carbon::parse($prefillData['scraped_data']['scraped_at'])->format('j M, H:i') }})
                                            @endif
                                        </p>
                                    @elseif(isset($prefillData['price_source']) && $prefillData['price_source'] === 'calculated')
                                        <p class="mt-1 text-sm text-blue-600 dark:text-blue-400">
                                            Price calculated with 30% markup (UDEA price not available)
                                        </p>
                                    @endif
                                </div>

                                <!-- Tax Category -->
                                <div>
                                    <label for="tax_category" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Tax Category *
                                    </label>
                                    <select id="tax_category" 
                                            name="tax_category" 
                                            required
                                            class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 focus:border-indigo-500 focus:ring-indigo-500">
                                        <option value="">Select tax category</option>
                                        @foreach($taxCategories as $taxCategory)
                                            <option value="{{ $taxCategory->ID }}" {{ old('tax_category') === $taxCategory->ID ? 'selected' : '' }}>
                                                {{ $taxCategory->NAME }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            
                            <!-- Delivery Cost Option -->
                            <div class="mt-4">
                                <div class="flex items-center">
                                    <input type="checkbox" 
                                           id="has_delivery_cost" 
                                           name="has_delivery_cost" 
                                           value="1" 
                                           {{ old('has_delivery_cost') ? 'checked' : '' }}
                                           class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                                    <label for="has_delivery_cost" class="ml-2 block text-sm text-gray-700 dark:text-gray-300">
                                        Include delivery cost (15%)
                                        <span class="block text-xs text-gray-500">For suppliers with delivery charges</span>
                                    </label>
                                </div>
                            </div>
                            
                            <!-- Pricing Breakdown -->
                            <div class="mt-6 p-4 bg-gray-50 dark:bg-gray-700 rounded-lg">
                                <h4 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-3">Pricing Breakdown</h4>
                                
                                <div class="space-y-2 text-sm">
                                    <div class="flex justify-between">
                                        <span class="text-gray-600 dark:text-gray-400">Cost Price:</span>
                                        <span id="breakdown-cost" class="font-medium">€0.00</span>
                                    </div>
                                    <div class="flex justify-between" id="delivery-cost-row" style="display: none;">
                                        <span class="text-gray-600 dark:text-gray-400">Delivery Cost (15%):</span>
                                        <span id="breakdown-delivery" class="font-medium">€0.00</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-gray-600 dark:text-gray-400">Total Cost:</span>
                                        <span id="breakdown-total-cost" class="font-medium">€0.00</span>
                                    </div>
                                    <hr class="border-gray-300 dark:border-gray-600">
                                    <div class="flex justify-between">
                                        <span class="text-gray-600 dark:text-gray-400">Selling Price (ex VAT):</span>
                                        <span id="breakdown-selling-ex-vat" class="font-medium">€0.00</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-gray-600 dark:text-gray-400">VAT Amount:</span>
                                        <span id="breakdown-vat" class="font-medium">€0.00 (<span id="vat-rate">0%</span>)</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-gray-600 dark:text-gray-400">Selling Price (inc VAT):</span>
                                        <span id="breakdown-selling-inc-vat" class="font-medium">€0.00</span>
                                    </div>
                                    <hr class="border-gray-300 dark:border-gray-600">
                                    <div class="flex justify-between">
                                        <span class="text-gray-700 dark:text-gray-300 font-medium">Profit Margin:</span>
                                        <div class="text-right">
                                            <div id="margin-amount" class="font-semibold text-green-600 dark:text-green-400">€0.00</div>
                                            <div id="margin-percentage" class="text-sm text-green-600 dark:text-green-400">0%</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Right Column -->
                    <div class="space-y-6">
                        <!-- Supplier Information -->
                        <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6">
                            <div class="border-b border-gray-200 dark:border-gray-700 pb-3 mb-4">
                                <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Supplier Information</h3>
                            </div>

                            <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                                <div>
                                    <label for="supplier_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Supplier
                                    </label>
                                    <select id="supplier_id" 
                                            name="supplier_id"
                                            class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 focus:border-indigo-500 focus:ring-indigo-500">
                                        <option value="">No supplier</option>
                                        @foreach($suppliers as $supplier)
                                            <option value="{{ $supplier->SupplierID }}" 
                                                    {{ old('supplier_id', $prefillData['supplier_id'] ?? '') == $supplier->SupplierID ? 'selected' : '' }}>
                                                {{ $supplier->Supplier }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>

                                <div>
                                    <label for="supplier_code" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Supplier Code
                                    </label>
                                    <input type="text" 
                                           id="supplier_code" 
                                           name="supplier_code" 
                                           value="{{ old('supplier_code', $prefillData['supplier_code'] ?? '') }}"
                                           class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 focus:border-indigo-500 focus:ring-indigo-500"
                                           placeholder="Supplier's product code">
                                </div>

                                <div>
                                    <label for="units_per_case" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Units per Case
                                        <span class="text-xs text-gray-500 block">Retail packages per case</span>
                                    </label>
                                    <input type="number" 
                                           id="units_per_case" 
                                           name="units_per_case" 
                                           value="{{ old('units_per_case', $prefillData['units_per_case'] ?? '1') }}" 
                                           min="1"
                                           class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 focus:border-indigo-500 focus:ring-indigo-500">
                                </div>

                                <div>
                                    <label for="supplier_cost" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Supplier Cost (€)
                                    </label>
                                    <input type="number" 
                                           id="supplier_cost" 
                                           name="supplier_cost" 
                                           value="{{ old('supplier_cost') }}" 
                                           step="0.01" 
                                           min="0"
                                           class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 focus:border-indigo-500 focus:ring-indigo-500"
                                           placeholder="Auto-filled from cost">
                                </div>
                            </div>
                        </div>

                        <!-- Product Configuration -->
                        <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6">
                            <div class="border-b border-gray-200 dark:border-gray-700 pb-3 mb-4">
                                <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Configuration</h3>
                            </div>

                            <div class="grid grid-cols-2 gap-3">
                                <!-- Service Item -->
                                <div class="flex items-start">
                                    <input type="checkbox" 
                                           id="is_service" 
                                           name="is_service" 
                                           value="1" 
                                           {{ old('is_service') ? 'checked' : '' }}
                                           class="mt-1 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                                    <label for="is_service" class="ml-2 block text-sm text-gray-700 dark:text-gray-300">
                                        Service Item
                                    </label>
                                </div>

                                <!-- Scale Item -->
                                <div class="flex items-start">
                                    <input type="checkbox" 
                                           id="is_scale" 
                                           name="is_scale" 
                                           value="1" 
                                           {{ old('is_scale') ? 'checked' : '' }}
                                           class="mt-1 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                                    <label for="is_scale" class="ml-2 block text-sm text-gray-700 dark:text-gray-300">
                                        Sold by Weight
                                    </label>
                                </div>

                                <!-- Kitchen Item -->
                                <div class="flex items-start">
                                    <input type="checkbox" 
                                           id="is_kitchen" 
                                           name="is_kitchen" 
                                           value="1" 
                                           {{ old('is_kitchen') ? 'checked' : '' }}
                                           class="mt-1 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                                    <label for="is_kitchen" class="ml-2 block text-sm text-gray-700 dark:text-gray-300">
                                        Kitchen Item
                                    </label>
                                </div>

                                <!-- Print to Kitchen -->
                                <div class="flex items-start">
                                    <input type="checkbox" 
                                           id="print_kb" 
                                           name="print_kb" 
                                           value="1" 
                                           {{ old('print_kb') ? 'checked' : '' }}
                                           class="mt-1 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                                    <label for="print_kb" class="ml-2 block text-sm text-gray-700 dark:text-gray-300">
                                        Print to Kitchen
                                    </label>
                                </div>

                                <!-- Send Status -->
                                <div class="flex items-start">
                                    <input type="checkbox" 
                                           id="send_status" 
                                           name="send_status" 
                                           value="1" 
                                           {{ old('send_status') ? 'checked' : '' }}
                                           class="mt-1 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                                    <label for="send_status" class="ml-2 block text-sm text-gray-700 dark:text-gray-300">
                                        Send Status
                                    </label>
                                </div>

                                <!-- Commission -->
                                <div class="flex items-start">
                                    <input type="checkbox" 
                                           id="is_com" 
                                           name="is_com" 
                                           value="1" 
                                           {{ old('is_com') ? 'checked' : '' }}
                                           class="mt-1 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                                    <label for="is_com" class="ml-2 block text-sm text-gray-700 dark:text-gray-300">
                                        Commission Item
                                    </label>
                                </div>
                            </div>
                        </div>

                        <!-- Stock Information -->
                        <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6">
                            <div class="border-b border-gray-200 dark:border-gray-700 pb-3 mb-4">
                                <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Stock Information</h3>
                            </div>

                            <div class="grid grid-cols-1 gap-4">
                                <div>
                                    <label for="initial_stock" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Initial Stock Quantity
                                    </label>
                                    <input type="number" 
                                           id="initial_stock" 
                                           name="initial_stock" 
                                           value="{{ old('initial_stock', $prefillData['initial_stock'] ?? '0') }}" 
                                           step="0.01" 
                                           min="0"
                                           class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 focus:border-indigo-500 focus:ring-indigo-500">
                                </div>

                                <div>
                                    <label for="stock_cost" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Stock Cost Value (€)
                                    </label>
                                    <input type="number" 
                                           id="stock_cost" 
                                           name="stock_cost" 
                                           value="{{ old('stock_cost') }}" 
                                           step="0.01" 
                                           min="0"
                                           class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 focus:border-indigo-500 focus:ring-indigo-500"
                                           placeholder="Auto-calculated from cost price">
                                </div>

                                <div>
                                    <label for="stock_volume" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Stock Volume
                                    </label>
                                    <input type="number" 
                                           id="stock_volume" 
                                           name="stock_volume" 
                                           value="{{ old('stock_volume', '0') }}" 
                                           step="0.01" 
                                           min="0"
                                           class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 focus:border-indigo-500 focus:ring-indigo-500">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="flex items-center justify-end space-x-4 mt-6">
                    <a href="{{ route('products.index') }}" 
                       class="px-4 py-2 bg-gray-300 hover:bg-gray-400 text-gray-800 rounded-md transition-colors duration-200">
                        Cancel
                    </a>
                    <button type="submit" 
                            class="px-6 py-2 bg-green-600 hover:bg-green-700 text-white rounded-md transition-colors duration-200">
                        Create Product
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Track if we have scraped pricing data
        const hasScrapedPrice = @json(isset($prefillData['price_source']) && $prefillData['price_source'] === 'udea_scraped');
        
        // Scraped product name functionality
        @if(isset($prefillData['scraped_name']))
            document.addEventListener('DOMContentLoaded', function() {
                const useScrapedNameBtn = document.getElementById('use-scraped-name');
                const nameField = document.getElementById('name');
                
                if (useScrapedNameBtn && nameField) {
                    useScrapedNameBtn.addEventListener('click', function() {
                        const scrapedName = this.dataset.scrapedName;
                        
                        // Update the input field with the scraped name
                        nameField.value = scrapedName;
                        
                        // Visual feedback: briefly highlight the field
                        nameField.classList.add('ring-2', 'ring-green-500', 'border-green-500');
                        
                        // Update button to show it was used
                        this.innerHTML = `
                            <svg class="w-4 h-4 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                            </svg>
                            Name Updated!
                        `;
                        this.disabled = true;
                        this.classList.add('opacity-75', 'cursor-not-allowed');
                        
                        // Remove highlight after animation
                        setTimeout(() => {
                            nameField.classList.remove('ring-2', 'ring-green-500', 'border-green-500');
                        }, 2000);
                        
                        // Re-enable button after a moment in case user wants to use it again
                        setTimeout(() => {
                            this.disabled = false;
                            this.classList.remove('opacity-75', 'cursor-not-allowed');
                            this.innerHTML = `
                                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16l-4-4m0 0l4-4m-4 4h18"/>
                                </svg>
                                Use This Name
                            `;
                        }, 3000);
                    });
                }
            });
        @endif
        
        // Tax rates mapping
        const taxRates = {
            '000': 0.00,    // Tax aZero
            '001': 0.135,   // Tax Reduced (13.5%)
            '002': 0.23,    // Tax Standard (23%)
            '003': 0.09     // Tax Second Reduced (9%)
        };
        
        console.log('Script loaded, tax rates:', taxRates);
        
        // Test function to manually trigger calculation
        window.testCalculation = function() {
            console.log('Manual test triggered');
            updatePricingBreakdown();
        };
        
        // Simple test with hardcoded values
        window.testHardcoded = function() {
            const marginAmount = document.getElementById('margin-amount');
            const marginPercentage = document.getElementById('margin-percentage');
            
            if (marginAmount && marginPercentage) {
                marginAmount.textContent = '€2.60';
                marginPercentage.textContent = '36.4%';
                console.log('Hardcoded values set successfully');
            } else {
                console.error('Margin elements not found for hardcoded test');
            }
        };
        
        // Auto-calculate selling price based on cost price with 30% margin
        // Only update if no scraped price is available, or if user manually changed the cost
        function updatePricingBreakdown() {
            console.log('updatePricingBreakdown called');
            
            const costPriceEl = document.getElementById('price_buy');
            const sellPriceEl = document.getElementById('price_sell');
            const deliveryCostEl = document.getElementById('has_delivery_cost');
            const taxCategoryEl = document.getElementById('tax_category');
            
            if (!costPriceEl || !sellPriceEl || !deliveryCostEl || !taxCategoryEl) {
                console.error('Missing form elements:', {costPriceEl, sellPriceEl, deliveryCostEl, taxCategoryEl});
                return;
            }
            
            const costPrice = parseFloat(costPriceEl.value) || 0;
            const sellPrice = parseFloat(sellPriceEl.value) || 0;
            const hasDeliveryCost = deliveryCostEl.checked;
            const taxCategoryId = taxCategoryEl.value;
            console.log('Selected tax category ID:', taxCategoryId);
            // Default to 23% VAT (Tax Standard) if no category selected, but respect 0% for Tax aZero
            const taxRate = taxCategoryId ? (taxRates[taxCategoryId] !== undefined ? taxRates[taxCategoryId] : 0.23) : 0.23;
            console.log('Applied tax rate:', taxRate);
            
            // Calculate delivery cost
            const deliveryCost = hasDeliveryCost ? costPrice * 0.15 : 0;
            const totalCost = costPrice + deliveryCost;
            
            // Calculate VAT (selling price is VAT inclusive)
            // Special handling for 0% VAT
            const sellPriceExVat = taxRate === 0 ? sellPrice : sellPrice / (1 + taxRate);
            const vatAmount = taxRate === 0 ? 0 : sellPrice - sellPriceExVat;
            
            console.log('VAT calculation:', {sellPrice, taxRate, sellPriceExVat, vatAmount});
            
            // Calculate profit margin (excluding VAT) - based on selling price, not cost
            const marginAmount = sellPriceExVat - totalCost;
            const marginPercentage = sellPriceExVat > 0 ? (marginAmount / sellPriceExVat) * 100 : 0;
            
            // Debug logging
            console.log('Pricing breakdown:', {
                costPrice, sellPrice, hasDeliveryCost, taxCategoryId, taxRate,
                deliveryCost, totalCost, sellPriceExVat, vatAmount,
                marginAmount, marginPercentage
            });
            
            // Update breakdown display - check if elements exist
            const elements = {
                'breakdown-cost': costPrice.toFixed(2),
                'breakdown-delivery': deliveryCost.toFixed(2),
                'breakdown-total-cost': totalCost.toFixed(2),
                'breakdown-selling-ex-vat': sellPriceExVat.toFixed(2),
                'breakdown-vat': vatAmount.toFixed(2),
                'breakdown-selling-inc-vat': sellPrice.toFixed(2),
                'vat-rate': (taxRate * 100).toFixed(1) + '%'
            };
            
            for (const [id, value] of Object.entries(elements)) {
                const el = document.getElementById(id);
                if (el) {
                    el.textContent = (id === 'vat-rate') ? value : '€' + value;
                } else {
                    console.error('Element not found:', id);
                }
            }
            
            // Ensure margin elements exist before updating
            const marginAmountEl = document.getElementById('margin-amount');
            const marginPercentageEl = document.getElementById('margin-percentage');
            
            if (marginAmountEl && marginPercentageEl) {
                marginAmountEl.textContent = '€' + marginAmount.toFixed(2);
                marginPercentageEl.textContent = marginPercentage.toFixed(1) + '%';
                
                // Color coding for margin
                if (marginPercentage < 10) {
                    marginAmountEl.className = 'font-semibold text-red-600 dark:text-red-400';
                    marginPercentageEl.className = 'text-sm text-red-600 dark:text-red-400';
                } else if (marginPercentage < 20) {
                    marginAmountEl.className = 'font-semibold text-yellow-600 dark:text-yellow-400';
                    marginPercentageEl.className = 'text-sm text-yellow-600 dark:text-yellow-400';
                } else {
                    marginAmountEl.className = 'font-semibold text-green-600 dark:text-green-400';
                    marginPercentageEl.className = 'text-sm text-green-600 dark:text-green-400';
                }
            }
            
            // Show/hide delivery cost row
            const deliveryRow = document.getElementById('delivery-cost-row');
            if (deliveryRow) {
                deliveryRow.style.display = hasDeliveryCost ? 'flex' : 'none';
            }
        }
        
        document.getElementById('price_buy').addEventListener('input', function() {
            const costPrice = parseFloat(this.value);
            const sellPriceField = document.getElementById('price_sell');
            const hasDeliveryCost = document.getElementById('has_delivery_cost').checked;
            const taxCategoryId = document.getElementById('tax_category').value;
            const taxRate = taxRates[taxCategoryId] || 0.23; // Default to 23% VAT
            
            if (!isNaN(costPrice) && costPrice > 0) {
                // Only auto-update selling price if no scraped price or if field is empty
                if (!hasScrapedPrice || !sellPriceField.value) {
                    // Calculate total cost including delivery
                    const deliveryCost = hasDeliveryCost ? costPrice * 0.15 : 0;
                    const totalCost = costPrice + deliveryCost;
                    
                    // Add 30% margin then add VAT
                    const sellPriceExVat = totalCost * 1.3;
                    const sellPriceIncVat = sellPriceExVat * (1 + taxRate);
                    
                    sellPriceField.value = sellPriceIncVat.toFixed(4);
                }
            }
            updatePricingBreakdown();
        });
        
        document.getElementById('price_sell').addEventListener('input', updatePricingBreakdown);
        document.getElementById('has_delivery_cost').addEventListener('change', updatePricingBreakdown);
        document.getElementById('tax_category').addEventListener('change', updatePricingBreakdown);

        // Auto-fill supplier cost from cost price
        document.getElementById('price_buy').addEventListener('input', function() {
            const costPrice = parseFloat(this.value);
            if (!isNaN(costPrice) && costPrice > 0) {
                const supplierCostField = document.getElementById('supplier_cost');
                if (!supplierCostField.value) {
                    supplierCostField.value = costPrice.toFixed(2);
                }
            }
        });

        // Auto-calculate stock cost from cost price and initial stock
        function updateStockCost() {
            const costPrice = parseFloat(document.getElementById('price_buy').value) || 0;
            const initialStock = parseFloat(document.getElementById('initial_stock').value) || 0;
            const stockCostField = document.getElementById('stock_cost');
            
            if (costPrice > 0 && initialStock > 0 && !stockCostField.value) {
                stockCostField.value = (costPrice * initialStock).toFixed(2);
            }
        }

        document.getElementById('price_buy').addEventListener('input', updateStockCost);
        document.getElementById('initial_stock').addEventListener('input', updateStockCost);
        
        // Initialize pricing breakdown
        document.addEventListener('DOMContentLoaded', function() {
            console.log('DOM loaded, initializing...');
            
            // Set default tax category to Tax Standard if none selected
            const taxCategorySelect = document.getElementById('tax_category');
            if (taxCategorySelect) {
                console.log('Current tax category:', taxCategorySelect.value);
                if (!taxCategorySelect.value) {
                    taxCategorySelect.value = '002'; // Tax Standard (23%)
                    console.log('Set default tax category to 002');
                }
            } else {
                console.error('Tax category select not found');
            }
            
            // Test if we can find the margin elements
            const marginAmount = document.getElementById('margin-amount');
            const marginPercentage = document.getElementById('margin-percentage');
            console.log('Margin elements found:', {marginAmount, marginPercentage});
            
            updatePricingBreakdown();
            
            // Add UDEA link update functionality
            const supplierCodeField = document.getElementById('supplier_code');
            if (supplierCodeField) {
                supplierCodeField.addEventListener('input', function() {
                    updateUdeaLink(this.value);
                });
            }
        });
        
        function updateUdeaLink(supplierCode) {
            const existingLink = document.querySelector('.udea-website-link');
            if (existingLink) {
                existingLink.remove();
            }
            
            if (supplierCode.trim()) {
                const nameFieldContainer = document.getElementById('name').closest('div');
                const linkHtml = `
                    <div class="mt-3 udea-website-link">
                        <a href="https://www.udea.nl/search/?qry=${encodeURIComponent(supplierCode)}" 
                           target="_blank" 
                           class="inline-flex items-center px-3 py-2 border border-blue-300 shadow-sm text-sm font-medium rounded-md text-blue-700 bg-blue-50 hover:bg-blue-100 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 dark:bg-blue-900/20 dark:text-blue-200 dark:border-blue-600 dark:hover:bg-blue-800/30 transition-colors duration-200">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
                            </svg>
                            View on UDEA Website
                        </a>
                    </div>
                `;
                nameFieldContainer.insertAdjacentHTML('afterend', linkHtml);
            }
        }
        
        // Add helpful tooltip if scraped pricing is being used
        @if(isset($prefillData['price_source']) && $prefillData['price_source'] === 'udea_scraped')
            document.getElementById('price_sell').title = "This price was automatically retrieved from UDEA website. You can still modify it if needed.";
        @endif
    </script>
</x-admin-layout>