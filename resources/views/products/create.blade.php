<x-admin-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                Create New Product
            </h2>
            @php
                $backAction = [
                    'type' => 'link',
                    'color' => 'secondary',
                    'class' => 'inline-flex items-center px-4 py-2 bg-gray-600 hover:bg-gray-700 text-white font-medium rounded-md transition-colors duration-200',
                    'icon' => 'M10 19l-7-7m0 0l7-7m-7 7h18'
                ];
                
                if ($deliveryItemId) {
                    $deliveryItem = \App\Models\DeliveryItem::find($deliveryItemId);
                    if ($deliveryItem && $deliveryItem->delivery) {
                        $backAction['route'] = 'deliveries.show';
                        $backAction['params'] = ['delivery' => $deliveryItem->delivery->id];
                        $backAction['label'] = 'Back to Delivery';
                    } else {
                        $backAction['route'] = 'products.index';
                        $backAction['label'] = 'Back to Products';
                    }
                } else {
                    $backAction['route'] = 'products.index';
                    $backAction['label'] = 'Back to Products';
                }
            @endphp
            <x-action-buttons :actions="[$backAction]" size="lg" />
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <x-alert type="error" :messages="$errors->all()" />

            <form action="{{ route('products.store') }}" method="POST">
                @csrf
                
                @if($deliveryItemId)
                    <input type="hidden" name="delivery_item_id" value="{{ $deliveryItemId }}">
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
                                    
                                    <!-- UDEA Website Link - Only show for UDEA suppliers -->
                                    @if((isset($prefillData['supplier_code']) || old('supplier_code')) && 
                                        isset($prefillData['supplier_id']) && 
                                        in_array($prefillData['supplier_id'], $udeaSupplierIds))
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
                                    
                                    <!-- Independent Test Link - Only show for Independent supplier -->
                                    @if((isset($prefillData['supplier_code']) || old('supplier_code')) && 
                                        isset($prefillData['supplier_id']) && 
                                        $prefillData['supplier_id'] == 37)
                                        <div class="mt-3 flex gap-2">
                                            <a href="{{ route('products.independent-test', ['supplier_code' => $prefillData['supplier_code'] ?? old('supplier_code')]) }}" 
                                               target="_blank" 
                                               class="inline-flex items-center px-3 py-2 border border-purple-300 shadow-sm text-sm font-medium rounded-md text-purple-700 bg-purple-50 hover:bg-purple-100 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-purple-500 dark:bg-purple-900/20 dark:text-purple-200 dark:border-purple-600 dark:hover:bg-purple-800/30 transition-colors duration-200">
                                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                                                </svg>
                                                Test Independent Product Lookup
                                            </a>
                                            <a href="https://iihealthfoods.com/search?q={{ urlencode($prefillData['supplier_code'] ?? old('supplier_code')) }}" 
                                               target="_blank" 
                                               class="inline-flex items-center px-3 py-2 border border-green-300 shadow-sm text-sm font-medium rounded-md text-green-700 bg-green-50 hover:bg-green-100 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 dark:bg-green-900/20 dark:text-green-200 dark:border-green-600 dark:hover:bg-green-800/30 transition-colors duration-200">
                                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
                                                </svg>
                                                View on Independent Website
                                            </a>
                                        </div>
                                        
                                        <!-- Independent Product Image Preview -->
                                        <div id="independent-product-image" class="mt-3 p-3 bg-green-50 border border-green-200 rounded-lg dark:bg-green-900/20 dark:border-green-800" style="display: none;">
                                            <div class="flex items-start">
                                                <div class="flex-shrink-0 mr-3">
                                                    <img id="independent-image" 
                                                         src="" 
                                                         alt="Product image from Independent" 
                                                         class="w-24 h-24 object-contain rounded border border-green-300 dark:border-green-700 bg-white cursor-pointer hover:opacity-80 transition-opacity"
                                                         onclick="openImageModal(this.src.replace('?width=533', ''), '{{ $prefillData['supplier_code'] ?? '' }}')"
                                                         onerror="this.parentElement.parentElement.parentElement.style.display='none';">
                                                </div>
                                                <div class="flex-1">
                                                    <div class="flex items-center mb-2">
                                                        <svg class="w-4 h-4 text-green-600 dark:text-green-400 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                                        </svg>
                                                        <h4 class="text-sm font-semibold text-green-800 dark:text-green-200">Product Image from Independent</h4>
                                                    </div>
                                                    <p class="text-sm text-green-700 dark:text-green-300">
                                                        This image is retrieved from Independent Health Foods website.
                                                    </p>
                                                    <p class="text-xs text-green-600 dark:text-green-400 mt-1">
                                                        Click image to view full size
                                                    </p>
                                                    <a id="independent-image-link" 
                                                       href="" 
                                                       target="_blank"
                                                       class="text-xs text-green-600 hover:text-green-800 dark:text-green-400 dark:hover:text-green-300 mt-1 inline-block">
                                                        View full size →
                                                    </a>
                                                </div>
                                            </div>
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
                                <div class="relative">
                                    <label for="category" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Product Category *
                                        <span class="ml-1 text-red-500">●</span>
                                    </label>
                                    <select id="category" 
                                            name="category"
                                            class="w-full rounded-md border-2 border-red-300 dark:border-red-600 dark:bg-gray-700 focus:border-red-500 focus:ring-red-500 bg-red-50 dark:bg-red-900/20">
                                        <option value="" class="text-red-600 dark:text-red-300">⚠ Please select a category</option>
                                        @foreach($categories as $category)
                                            <option value="{{ $category->ID }}" {{ (old('category') ?: $categoryId) === $category->ID ? 'selected' : '' }}>
                                                {{ $category->NAME }}
                                            </option>
                                        @endforeach
                                    </select>
                                    <div class="absolute -top-1 -right-1 w-3 h-3 bg-red-500 rounded-full animate-pulse"></div>
                                </div>
                            </div>
                        </div>

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

                            <!-- Stock Management Option -->
                            <div class="mt-6 pt-4 border-t border-gray-200 dark:border-gray-700">
                                <div class="flex items-start">
                                    <input type="checkbox" 
                                           id="include_in_stocking" 
                                           name="include_in_stocking" 
                                           value="1" 
                                           {{ old('include_in_stocking', '1') ? 'checked' : '' }}
                                           class="mt-1 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                                    <label for="include_in_stocking" class="ml-3 block text-sm text-gray-700 dark:text-gray-300">
                                        <span class="font-medium">Include in Stock Management</span>
                                        <span class="block text-xs text-gray-500 mt-1">
                                            Add this product to stocking operations. Uncheck for one-time items that won't be regularly stocked.
                                        </span>
                                    </label>
                                </div>
                            </div>
                        </div>

                    </div>
                    
                    <!-- Right Column -->
                    <div class="space-y-6">
                        <!-- Pricing & Tax Section -->
                        <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6 border-2 border-green-200 dark:border-green-700">
                            <div class="border-b border-gray-200 dark:border-gray-700 pb-3 mb-4">
                                <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 flex items-center">
                                    <svg class="w-5 h-5 mr-2 text-green-600" fill="currentColor" viewBox="0 0 20 20">
                                        <path d="M8.433 7.418c.155-.103.346-.196.567-.267v1.698a2.305 2.305 0 01-.567-.267C8.07 8.34 8 8.114 8 8c0-.114.07-.34.433-.582zM11 12.849v-1.698c.22.071.412.164.567.267.364.243.433.468.433.582 0 .114-.07.34-.433.582a2.305 2.305 0 01-.567.267z"/>
                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-13a1 1 0 10-2 0v.092a4.535 4.535 0 00-1.676.662C6.602 6.234 6 7.009 6 8c0 .99.602 1.765 1.324 2.246.48.32 1.054.545 1.676.662v1.941c-.391-.127-.68-.317-.843-.504a1 1 0 10-1.51 1.31c.562.649 1.413 1.076 2.353 1.253V15a1 1 0 102 0v-.092a4.535 4.535 0 001.676-.662C13.398 13.766 14 12.991 14 12c0-.99-.602-1.765-1.324-2.246A4.535 4.535 0 0011 9.092V7.151c.391.127.68.317.843.504a1 1 0 101.511-1.31c-.563-.649-1.413-1.076-2.354-1.253V5z" clip-rule="evenodd"/>
                                    </svg>
                                    Pricing & Tax
                                    <span class="ml-2 inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">
                                        Priority Review
                                    </span>
                                </h3>
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
                                <div class="relative">
                                    <label for="tax_category" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Tax Category *
                                        <span class="ml-1 text-red-500">●</span>
                                    </label>
                                    <select id="tax_category" 
                                            name="tax_category" 
                                            required
                                            class="w-full rounded-md border-2 border-red-300 dark:border-red-600 dark:bg-gray-700 focus:border-red-500 focus:ring-red-500 bg-red-50 dark:bg-red-900/20">
                                        <option value="" class="text-red-600 dark:text-red-300">⚠ Please select tax category</option>
                                        @foreach($taxCategories as $taxCategory)
                                            <option value="{{ $taxCategory->ID }}" 
                                                {{ (old('tax_category') ?: ($prefillData['tax_category'] ?? '')) === $taxCategory->ID ? 'selected' : '' }}>
                                                {{ $taxCategory->NAME }}
                                                @if($prefillData && isset($prefillData['tax_category']) && $prefillData['tax_category'] === $taxCategory->ID)
                                                    (Auto-selected)
                                                @endif
                                            </option>
                                        @endforeach
                                    </select>
                                    <div class="absolute -top-1 -right-1 w-3 h-3 bg-red-500 rounded-full animate-pulse"></div>
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
                            
                            <!-- Action Buttons -->
                            <div class="flex items-center justify-end space-x-4 mt-6">
                                @if($deliveryItemId)
                                    @php
                                        $deliveryItem = \App\Models\DeliveryItem::find($deliveryItemId);
                                        $cancelRoute = ($deliveryItem && $deliveryItem->delivery) 
                                            ? route('deliveries.show', ['delivery' => $deliveryItem->delivery->id])
                                            : route('products.index');
                                    @endphp
                                    <a href="{{ $cancelRoute }}" 
                                       class="px-4 py-2 bg-gray-300 hover:bg-gray-400 text-gray-800 rounded-md transition-colors duration-200">
                                        Cancel
                                    </a>
                                @else
                                    <a href="{{ route('products.index') }}" 
                                       class="px-4 py-2 bg-gray-300 hover:bg-gray-400 text-gray-800 rounded-md transition-colors duration-200">
                                        Cancel
                                    </a>
                                @endif
                                <button type="submit" 
                                        class="px-6 py-2 bg-green-600 hover:bg-green-700 text-white rounded-md transition-colors duration-200">
                                    Create Product
                                </button>
                            </div>
                        </div>

                        <!-- Till Display Settings -->
                        <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6">
                            <div class="border-b border-gray-200 dark:border-gray-700 pb-3 mb-4">
                                <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Till Display Settings</h3>
                                <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                                    Optional custom display name for products that need till buttons (products without barcodes)
                                </p>
                            </div>

                            <div class="space-y-6">
                                <!-- Display Name Input -->
                                <div>
                                    <label for="display_name" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Display Name
                                    </label>
                                    <input type="text" 
                                           id="display_name" 
                                           name="display_name"
                                           value="{{ old('display_name') }}" 
                                           class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 focus:border-indigo-500 focus:ring-indigo-500"
                                           placeholder="Custom display name (leave empty to use product name)">
                                    <p class="text-xs text-gray-500 mt-1">
                                        Used on till buttons and displays. Supports line breaks with &lt;br&gt; tags.
                                    </p>
                                </div>

                                <!-- Till Visibility Toggle -->
                                <div class="flex items-start">
                                    <input type="checkbox" 
                                           id="show_on_till" 
                                           name="show_on_till" 
                                           value="1" 
                                           {{ old('show_on_till') ? 'checked' : '' }}
                                           class="mt-1 rounded border-gray-300 text-green-600 focus:ring-green-500">
                                    <label for="show_on_till" class="ml-3 block text-sm text-gray-700 dark:text-gray-300">
                                        <span class="font-medium">Show on Till</span>
                                        <span class="block text-xs text-gray-500 mt-1">
                                            Make this product visible on the POS till. Uncheck to hide from till buttons.
                                        </span>
                                    </label>
                                </div>

                                <!-- Display Name Preview -->
                                <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-4">
                                    <div class="flex items-center justify-between mb-2">
                                        <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300">Till Button Preview</h4>
                                        <span class="text-xs text-gray-500 dark:text-gray-400">How it will appear on POS</span>
                                    </div>
                                    
                                    <!-- Mock Till Button -->
                                    <div id="till-button-preview" class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-600 rounded-lg p-4 text-center shadow-sm max-w-xs mx-auto">
                                        <div id="display-preview" class="text-sm font-medium text-gray-900 dark:text-gray-100 min-h-[2.5rem] flex items-center justify-center">
                                            <span class="text-gray-400 dark:text-gray-500 italic" id="preview-placeholder">Enter display name above</span>
                                        </div>
                                        <div class="mt-2 flex items-center justify-center gap-2">
                                            <div id="visibility-indicator" class="w-2 h-2 rounded-full bg-green-500"></div>
                                            <span class="text-xs text-gray-500 dark:text-gray-400" id="visibility-text">Visible on Till</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                    </div>
                </div>

            </form>
        </div>
    </div>

    <script>
        // Track if we have scraped pricing data
        const hasScrapedPrice = @json(isset($prefillData['price_source']) && $prefillData['price_source'] === 'udea_scraped');
        
        // Track if user has manually entered a selling price
        let userHasSetSellingPrice = false;
        
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
        
        // Tax rates mapping from PHP (actual database values)
        const taxRates = @json($taxRates);
        
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
            // Get tax rate from the PHP-provided rates, convert string to float
            const taxRate = taxCategoryId && taxRates[taxCategoryId] !== undefined ? parseFloat(taxRates[taxCategoryId]) : 0.00;
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
        
        // Track when user manually enters selling price
        const priceSellEl = document.getElementById('price_sell');
        if (priceSellEl) {
            priceSellEl.addEventListener('input', function() {
                // Mark that user has manually set a selling price
                if (this.value && this.value.trim() !== '') {
                    userHasSetSellingPrice = true;
                }
                updatePricingBreakdown();
            });
        }

        const priceBuyEl = document.getElementById('price_buy');
        if (priceBuyEl) {
            priceBuyEl.addEventListener('input', function() {
                const costPrice = parseFloat(this.value);
                const sellPriceField = document.getElementById('price_sell');
                const hasDeliveryCost = document.getElementById('has_delivery_cost').checked;
                const taxCategoryId = document.getElementById('tax_category').value;
                const taxRate = taxCategoryId && taxRates[taxCategoryId] !== undefined ? parseFloat(taxRates[taxCategoryId]) : 0.00;
            
            if (!isNaN(costPrice) && costPrice > 0) {
                // Only auto-update selling price if:
                // 1. No scraped price AND field is empty, OR
                // 2. User hasn't manually set a selling price yet
                const shouldAutoUpdate = (!hasScrapedPrice && !sellPriceField.value) || !userHasSetSellingPrice;
                
                if (shouldAutoUpdate) {
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
        }
        
        const hasDeliveryCostEl = document.getElementById('has_delivery_cost');
        if (hasDeliveryCostEl) {
            hasDeliveryCostEl.addEventListener('change', updatePricingBreakdown);
        }
        
        const taxCategoryEl = document.getElementById('tax_category');
        if (taxCategoryEl) {
            taxCategoryEl.addEventListener('change', function() {
                updatePricingBreakdown();
                updateTaxCategoryFieldStyling(this, this.value !== '');
            });
        }

        // Auto-fill supplier cost from cost price
        if (priceBuyEl) {
            priceBuyEl.addEventListener('input', function() {
                const costPrice = parseFloat(this.value);
                if (!isNaN(costPrice) && costPrice > 0) {
                    const supplierCostField = document.getElementById('supplier_cost');
                    if (supplierCostField && !supplierCostField.value) {
                        supplierCostField.value = costPrice.toFixed(2);
                    }
                }
            });
        }

        // Auto-calculate stock cost from cost price and initial stock
        function updateStockCost() {
            const priceBuyField = document.getElementById('price_buy');
            const initialStockField = document.getElementById('initial_stock');
            const stockCostField = document.getElementById('stock_cost');
            
            if (!priceBuyField || !initialStockField || !stockCostField) return;
            
            const costPrice = parseFloat(priceBuyField.value) || 0;
            const initialStock = parseFloat(initialStockField.value) || 0;
            
            if (costPrice > 0 && initialStock > 0 && !stockCostField.value) {
                stockCostField.value = (costPrice * initialStock).toFixed(2);
            }
        }

        if (priceBuyEl) {
            priceBuyEl.addEventListener('input', updateStockCost);
        }
        
        const initialStockEl = document.getElementById('initial_stock');
        if (initialStockEl) {
            initialStockEl.addEventListener('input', updateStockCost);
        }
        
        // Initialize pricing breakdown
        document.addEventListener('DOMContentLoaded', function() {
            console.log('DOM loaded, initializing...');
            
            // Set default tax category to Tax aZero (0%) if none selected and not prefilled
            const taxCategorySelect = document.getElementById('tax_category');
            if (taxCategorySelect) {
                console.log('Current tax category:', taxCategorySelect.value);
                
                // If tax category is already selected (prefilled), update styling
                if (taxCategorySelect.value) {
                    updateTaxCategoryFieldStyling(taxCategorySelect, true);
                    console.log('Tax category prefilled:', taxCategorySelect.value);
                } else {
                    // Only set default if no value is prefilled
                    taxCategorySelect.value = '000'; // Tax aZero (0%)
                    console.log('Set default tax category to 000 (Tax aZero)');
                }
            } else {
                console.error('Tax category select not found');
            }
            
            // Function to update tax category field styling
            function updateTaxCategoryFieldStyling(field, isSelected) {
                if (isSelected) {
                    // Change to success styling when tax category is selected
                    field.className = field.className
                        .replace('border-red-300 dark:border-red-600', 'border-green-300 dark:border-green-600')
                        .replace('focus:border-red-500 focus:ring-red-500', 'focus:border-green-500 focus:ring-green-500')
                        .replace('bg-red-50 dark:bg-red-900/20', 'bg-green-50 dark:bg-green-900/20');
                    
                    // Hide the warning indicator
                    const indicator = field.parentElement.querySelector('.animate-pulse');
                    if (indicator) {
                        indicator.style.display = 'none';
                    }
                } else {
                    // Reset to warning styling
                    field.className = field.className
                        .replace('border-green-300 dark:border-green-600', 'border-red-300 dark:border-red-600')
                        .replace('focus:border-green-500 focus:ring-green-500', 'focus:border-red-500 focus:ring-red-500')
                        .replace('bg-green-50 dark:bg-green-900/20', 'bg-red-50 dark:bg-red-900/20');
                    
                    // Show the warning indicator
                    const indicator = field.parentElement.querySelector('.animate-pulse');
                    if (indicator) {
                        indicator.style.display = 'block';
                    }
                }
            }
            
            // Add color change functionality for required fields
            const categorySelect = document.getElementById('category');
            
            function updateFieldStyling(field) {
                if (field.value) {
                    field.classList.remove('border-red-300', 'dark:border-red-600', 'bg-red-50', 'dark:bg-red-900/20');
                    field.classList.add('border-green-300', 'dark:border-green-600', 'bg-green-50', 'dark:bg-green-900/20');
                    const indicator = field.parentElement.querySelector('.animate-pulse');
                    if (indicator) {
                        indicator.classList.remove('bg-red-500', 'animate-pulse');
                        indicator.classList.add('bg-green-500');
                    }
                } else {
                    field.classList.remove('border-green-300', 'dark:border-green-600', 'bg-green-50', 'dark:bg-green-900/20');
                    field.classList.add('border-red-300', 'dark:border-red-600', 'bg-red-50', 'dark:bg-red-900/20');
                    const indicator = field.parentElement.querySelector('.bg-green-500');
                    if (indicator) {
                        indicator.classList.remove('bg-green-500');
                        indicator.classList.add('bg-red-500', 'animate-pulse');
                    }
                }
            }
            
            if (categorySelect) {
                categorySelect.addEventListener('change', () => updateFieldStyling(categorySelect));
                updateFieldStyling(categorySelect);
            }
            
            if (taxCategorySelect) {
                taxCategorySelect.addEventListener('change', () => updateFieldStyling(taxCategorySelect));
                updateFieldStyling(taxCategorySelect);
            }
            
            // Test if we can find the margin elements
            const marginAmount = document.getElementById('margin-amount');
            const marginPercentage = document.getElementById('margin-percentage');
            console.log('Margin elements found:', {marginAmount, marginPercentage});
            
            // Call pricing breakdown update after a small delay to ensure all fields are initialized
            setTimeout(() => {
                updatePricingBreakdown();
            }, 100);
            
            // Add UDEA and Independent link update functionality
            const supplierCodeField = document.getElementById('supplier_code');
            const supplierIdField = document.getElementById('supplier_id');
            if (supplierCodeField) {
                supplierCodeField.addEventListener('input', function() {
                    updateSupplierLinks(this.value);
                    updateIndependentImage(this.value);
                });
            }
            if (supplierIdField) {
                supplierIdField.addEventListener('change', function() {
                    const supplierCode = document.getElementById('supplier_code').value;
                    updateSupplierLinks(supplierCode);
                    updateIndependentImage(supplierCode);
                });
            }
            
            // Load Independent image on page load if applicable
            if (supplierIdField && supplierCodeField) {
                const initialSupplierId = parseInt(supplierIdField.value);
                const initialSupplierCode = supplierCodeField.value;
                if (initialSupplierId === 37 && initialSupplierCode) {
                    updateIndependentImage(initialSupplierCode);
                }
            }

            // Check if selling price is already prefilled (e.g., from scraped data)
            const sellPriceField = document.getElementById('price_sell');
            if (sellPriceField && sellPriceField.value && sellPriceField.value.trim() !== '') {
                userHasSetSellingPrice = true;
            }

            // Initialize display name preview (with error handling)
            try {
                initializeDisplayNamePreview();
            } catch (e) {
                console.log('Display name preview initialization skipped:', e.message);
            }
            
            // Initialize till visibility preview (with error handling)
            try {
                initializeTillVisibilityPreview();
            } catch (e) {
                console.log('Till visibility preview initialization skipped:', e.message);
            }
        });
        
        // UDEA supplier IDs from PHP
        const udeaSupplierIds = @json($udeaSupplierIds);
        
        function updateSupplierLinks(supplierCode) {
            // Remove existing supplier links
            const existingLinks = document.querySelector('.supplier-website-links');
            if (existingLinks) {
                existingLinks.remove();
            }
            
            const supplierId = parseInt(document.getElementById('supplier_id').value);
            if (!supplierId || !supplierCode.trim()) {
                return;
            }
            
            const nameFieldContainer = document.getElementById('name').closest('div');
            let linkHtml = '';
            
            // Check if current supplier is a UDEA supplier
            if (udeaSupplierIds.includes(supplierId)) {
                linkHtml = `
                    <div class="mt-3 supplier-website-links">
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
            }
            // Check if current supplier is Independent (ID 37)
            else if (supplierId === 37) {
                linkHtml = `
                    <div class="mt-3 supplier-website-links flex gap-2">
                        <a href="/products/independent-test?supplier_code=${encodeURIComponent(supplierCode)}" 
                           target="_blank" 
                           class="inline-flex items-center px-3 py-2 border border-purple-300 shadow-sm text-sm font-medium rounded-md text-purple-700 bg-purple-50 hover:bg-purple-100 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-purple-500 dark:bg-purple-900/20 dark:text-purple-200 dark:border-purple-600 dark:hover:bg-purple-800/30 transition-colors duration-200">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                            </svg>
                            Test Independent Product Lookup
                        </a>
                        <a href="https://iihealthfoods.com/search?q=${encodeURIComponent(supplierCode)}" 
                           target="_blank" 
                           class="inline-flex items-center px-3 py-2 border border-green-300 shadow-sm text-sm font-medium rounded-md text-green-700 bg-green-50 hover:bg-green-100 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 dark:bg-green-900/20 dark:text-green-200 dark:border-green-600 dark:hover:bg-green-800/30 transition-colors duration-200">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
                            </svg>
                            View on Independent Website
                        </a>
                    </div>
                `;
            }
            
            if (linkHtml) {
                nameFieldContainer.insertAdjacentHTML('afterend', linkHtml);
            }
        }
        
        // Function to update Independent product image
        function updateIndependentImage(supplierCode) {
            const supplierIdField = document.getElementById('supplier_id');
            if (!supplierIdField) {
                console.log('Supplier ID field not found');
                return;
            }
            
            const supplierId = parseInt(supplierIdField.value);
            const imageContainer = document.getElementById('independent-product-image');
            
            console.log('updateIndependentImage called:', {
                supplierCode: supplierCode,
                supplierId: supplierId,
                isIndependent: supplierId === 37,
                hasContainer: !!imageContainer
            });
            
            // Only proceed if it's Independent supplier (ID 37)
            if (supplierId !== 37 || !supplierCode || !supplierCode.trim()) {
                if (imageContainer) {
                    imageContainer.style.display = 'none';
                }
                return;
            }
            
            // Create a dynamic container if it doesn't exist (for dynamic links)
            if (!imageContainer) {
                const nameFieldContainer = document.getElementById('name').closest('div');
                const imageHtml = `
                    <div id="independent-product-image" class="mt-3 p-3 bg-green-50 border border-green-200 rounded-lg dark:bg-green-900/20 dark:border-green-800" style="display: none;">
                        <div class="flex items-start">
                            <div class="flex-shrink-0 mr-3">
                                <img id="independent-image" 
                                     src="" 
                                     alt="Product image from Independent" 
                                     class="w-24 h-24 object-contain rounded border border-green-300 dark:border-green-700 bg-white cursor-pointer hover:opacity-80 transition-opacity"
                                     onerror="this.parentElement.parentElement.parentElement.style.display='none';">
                            </div>
                            <div class="flex-1">
                                <div class="flex items-center mb-2">
                                    <svg class="w-4 h-4 text-green-600 dark:text-green-400 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                    </svg>
                                    <h4 class="text-sm font-semibold text-green-800 dark:text-green-200">Product Image from Independent</h4>
                                </div>
                                <p class="text-sm text-green-700 dark:text-green-300">
                                    This image is retrieved from Independent Health Foods website.
                                </p>
                                <p class="text-xs text-green-600 dark:text-green-400 mt-1">
                                    Click image to view full size
                                </p>
                                <a id="independent-image-link" 
                                   href="" 
                                   target="_blank"
                                   class="text-xs text-green-600 hover:text-green-800 dark:text-green-400 dark:hover:text-green-300 mt-1 inline-block">
                                    View full size →
                                </a>
                            </div>
                        </div>
                    </div>
                `;
                
                // Find where to insert the image container
                const existingLinks = document.querySelector('.supplier-website-links');
                if (existingLinks) {
                    existingLinks.insertAdjacentHTML('afterend', imageHtml);
                } else {
                    nameFieldContainer.insertAdjacentHTML('afterend', imageHtml);
                }
            }
            
            const updatedContainer = document.getElementById('independent-product-image');
            const imageElement = document.getElementById('independent-image');
            const imageLinkElement = document.getElementById('independent-image-link');
            
            if (updatedContainer && imageElement && imageLinkElement) {
                // Try multiple image paths and formats
                const imagePaths = [
                    {
                        thumb: `https://iihealthfoods.com/cdn/shop/files/${supplierCode}_1.webp?width=533`,
                        full: `https://iihealthfoods.com/cdn/shop/files/${supplierCode}_1.webp`,
                        type: 'files/webp'
                    },
                    {
                        thumb: `https://iihealthfoods.com/cdn/shop/products/${supplierCode}_1.jpg?width=533`,
                        full: `https://iihealthfoods.com/cdn/shop/products/${supplierCode}_1.jpg`,
                        type: 'products/jpg'
                    },
                    {
                        thumb: `https://iihealthfoods.com/cdn/shop/files/${supplierCode}_1.jpg?width=533`,
                        full: `https://iihealthfoods.com/cdn/shop/files/${supplierCode}_1.jpg`,
                        type: 'files/jpg'
                    }
                ];
                
                console.log('Trying Independent image paths for:', supplierCode);
                
                // Function to try loading images in sequence
                function tryLoadImage(index) {
                    if (index >= imagePaths.length) {
                        console.log('No working image found for:', supplierCode);
                        updatedContainer.style.display = 'none';
                        return;
                    }
                    
                    const currentPath = imagePaths[index];
                    const testImage = new Image();
                    
                    testImage.onload = function() {
                        console.log('Independent image loaded successfully:', currentPath.type);
                        
                        // Update image source and link
                        imageElement.src = currentPath.thumb;
                        imageLinkElement.href = currentPath.full;
                        
                        // Add click handler for modal
                        imageElement.onclick = function() {
                            openImageModal(currentPath.full, supplierCode);
                        };
                        
                        // Show the container
                        updatedContainer.style.display = 'block';
                    };
                    
                    testImage.onerror = function() {
                        console.log('Failed to load image from:', currentPath.type);
                        // Try next path
                        tryLoadImage(index + 1);
                    };
                    
                    testImage.src = currentPath.thumb;
                }
                
                // Start trying to load images
                tryLoadImage(0);
            } else {
                console.log('Missing elements:', {
                    container: !!updatedContainer,
                    image: !!imageElement,
                    link: !!imageLinkElement
                });
            }
        }
        
        // Add helpful tooltip if scraped pricing is being used
        @if(isset($prefillData['price_source']) && $prefillData['price_source'] === 'udea_scraped')
            document.getElementById('price_sell').title = "This price was automatically retrieved from UDEA website. You can still modify it if needed.";
        @endif

        // Display Name Preview Functionality
        function initializeDisplayNamePreview() {
            const displayNameField = document.getElementById('display_name');
            const productNameField = document.getElementById('name');
            
            if (!displayNameField) return;
            
            // Live preview update on display name input
            displayNameField.addEventListener('input', function() {
                updateDisplayPreview();
            });
            
            // Also update when product name changes (for fallback)
            if (productNameField) {
                productNameField.addEventListener('input', function() {
                    updateDisplayPreview();
                });
            }
            
            // Initial update
            updateDisplayPreview();
        }

        function updateDisplayPreview() {
            const displayNameField = document.getElementById('display_name');
            const productNameField = document.getElementById('name');
            const preview = document.getElementById('display-preview');
            const placeholder = document.getElementById('preview-placeholder');
            
            if (!displayNameField || !preview) return;
            
            const displayName = displayNameField.value.trim();
            const productName = productNameField ? productNameField.value.trim() : '';
            
            if (displayName) {
                // Hide placeholder and show formatted display name
                if (placeholder) {
                    placeholder.style.display = 'none';
                }
                
                // Convert HTML entities and handle <br> tags
                let formattedName = displayName
                    .replace(/&lt;/g, '<')
                    .replace(/&gt;/g, '>')
                    .replace(/&amp;/g, '&')
                    .replace(/&quot;/g, '"')
                    .replace(/&#39;/g, "'");
                
                // Convert <br> tags and newlines to line breaks
                formattedName = formattedName
                    .replace(/\n/g, '<br>')
                    .replace(/<br\s*\/?>/gi, '<br>');
                
                preview.innerHTML = formattedName;
                preview.className = 'text-sm font-medium text-gray-900 dark:text-gray-100 min-h-[2.5rem] flex items-center justify-center';
            } else if (productName) {
                // Show product name as fallback
                if (placeholder) {
                    placeholder.style.display = 'none';
                }
                preview.innerHTML = productName;
                preview.className = 'text-sm font-medium text-gray-500 dark:text-gray-400 min-h-[2.5rem] flex items-center justify-center italic';
            } else {
                // Show placeholder
                if (placeholder) {
                    placeholder.style.display = 'inline';
                }
                preview.innerHTML = '<span class="text-gray-400 dark:text-gray-500 italic" id="preview-placeholder">Enter display name above</span>';
                preview.className = 'text-sm font-medium text-gray-900 dark:text-gray-100 min-h-[2.5rem] flex items-center justify-center';
            }
        }

        // Till Visibility Preview Functionality
        function initializeTillVisibilityPreview() {
            const showOnTillCheckbox = document.getElementById('show_on_till');
            
            if (!showOnTillCheckbox) return;
            
            // Update preview when checkbox changes
            showOnTillCheckbox.addEventListener('change', function() {
                updateTillVisibilityPreview();
            });
            
            // Initial update
            updateTillVisibilityPreview();
        }

        function updateTillVisibilityPreview() {
            const showOnTillCheckbox = document.getElementById('show_on_till');
            const tillButtonPreview = document.getElementById('till-button-preview');
            const visibilityIndicator = document.getElementById('visibility-indicator');
            const visibilityText = document.getElementById('visibility-text');
            
            if (!showOnTillCheckbox || !tillButtonPreview || !visibilityIndicator || !visibilityText) return;
            
            const isVisible = showOnTillCheckbox.checked;
            
            if (isVisible) {
                // Show as visible
                tillButtonPreview.className = 'bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-600 rounded-lg p-4 text-center shadow-sm max-w-xs mx-auto';
                visibilityIndicator.className = 'w-2 h-2 rounded-full bg-green-500';
                visibilityText.textContent = 'Visible on Till';
                visibilityText.className = 'text-xs text-gray-500 dark:text-gray-400';
            } else {
                // Show as hidden
                tillButtonPreview.className = 'bg-gray-100 dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg p-4 text-center shadow-sm max-w-xs mx-auto opacity-60';
                visibilityIndicator.className = 'w-2 h-2 rounded-full bg-red-500';
                visibilityText.textContent = 'Hidden from Till';
                visibilityText.className = 'text-xs text-red-600 dark:text-red-400';
            }
        }
        
        // Modal Functions
        function openImageModal(imageUrl, supplierCode) {
            const modal = document.getElementById('imageModal');
            const modalImage = document.getElementById('modalImage');
            const modalSupplierCode = document.getElementById('modalSupplierCode');
            const modalImageLink = document.getElementById('modalImageLink');
            
            if (modal && modalImage) {
                modalImage.src = imageUrl;
                modalSupplierCode.textContent = supplierCode || 'Product';
                modalImageLink.href = imageUrl;
                modal.classList.remove('hidden');
                
                // Prevent body scroll when modal is open
                document.body.style.overflow = 'hidden';
            }
        }
        
        function closeImageModal() {
            const modal = document.getElementById('imageModal');
            if (modal) {
                modal.classList.add('hidden');
                // Restore body scroll
                document.body.style.overflow = '';
            }
        }
        
        // Close modal on Escape key
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                closeImageModal();
            }
        });
    </script>
    
    <!-- Image Modal -->
    <div id="imageModal" class="fixed inset-0 z-50 hidden overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
        <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
            <!-- Background overlay -->
            <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" aria-hidden="true" onclick="closeImageModal()"></div>

            <!-- Modal panel -->
            <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
            <div class="inline-block align-bottom bg-white dark:bg-gray-800 rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-4xl sm:w-full">
                <div class="bg-white dark:bg-gray-800 px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                    <div class="sm:flex sm:items-start">
                        <div class="mt-3 text-center sm:mt-0 sm:text-left w-full">
                            <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-gray-100 mb-4" id="modal-title">
                                Product Image - <span id="modalSupplierCode"></span>
                            </h3>
                            <div class="mt-2">
                                <img id="modalImage" src="" alt="Full size product image" class="w-full h-auto max-h-[70vh] object-contain">
                            </div>
                        </div>
                    </div>
                </div>
                <div class="bg-gray-50 dark:bg-gray-700 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                    <button type="button" onclick="closeImageModal()" 
                            class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-gray-600 text-base font-medium text-white hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500 sm:ml-3 sm:w-auto sm:text-sm">
                        Close
                    </button>
                    <a id="modalImageLink" href="" target="_blank" 
                       class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white dark:bg-gray-600 text-base font-medium text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-500 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
                        Open in New Tab
                    </a>
                </div>
            </div>
        </div>
    </div>
</x-admin-layout>