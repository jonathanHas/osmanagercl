<x-admin-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                Edit Product: {{ $product->NAME }}
            </h2>
            @php
                $backAction = [
                    'type' => 'link',
                    'color' => 'secondary',
                    'class' => 'inline-flex items-center px-4 py-2 bg-gray-600 hover:bg-gray-700 text-white font-medium rounded-md transition-colors duration-200',
                    'icon' => 'M10 19l-7-7m0 0l7-7m-7 7h18'
                ];
                
                if ($fromDelivery) {
                    $backAction['route'] = 'deliveries.show';
                    $backAction['params'] = ['delivery' => $fromDelivery];
                    $backAction['label'] = 'Back to Delivery';
                } elseif ($fromContext === 'coffee') {
                    $backAction['route'] = 'coffee.products';
                    $backAction['label'] = 'Back to Coffee Products';
                } else {
                    $backAction['route'] = 'products.show';
                    $backAction['params'] = $product->ID;
                    $backAction['label'] = 'Back to Product Details';
                }
            @endphp
            <x-action-buttons :actions="[$backAction]" size="lg" />
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <x-alert type="error" :messages="$errors->all()" />
            <x-alert type="success" :message="session('success')" />

            <form action="{{ route('products.update', $product->ID) }}" method="POST">
                @csrf
                @method('PUT')
                
                <!-- Hidden product code field - required for validation -->
                <input type="hidden" name="code" value="{{ $prefillData['code'] }}">
                
                @if($fromDelivery)
                    <input type="hidden" name="from_delivery" value="{{ $fromDelivery }}">
                @elseif($fromContext)
                    <input type="hidden" name="from" value="{{ $fromContext }}">
                @endif

                <!-- Hidden field for override confirmation -->
                <input type="hidden" id="force_override" name="force_override" value="0">

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
                                <x-form-group 
                                    name="name" 
                                    label="Product Name *" 
                                    type="text" 
                                    :value="old('name', $prefillData['name'])" 
                                    required 
                                    placeholder="Enter product name" 
                                    containerClass="" />

                                <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                                    <!-- Product Code/Barcode (Read Only) -->
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                            Product Code/Barcode
                                        </label>
                                        <input type="text" 
                                               value="{{ $prefillData['code'] }}" 
                                               disabled
                                               class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 bg-gray-100 dark:bg-gray-600 text-gray-500 dark:text-gray-400">
                                        <p class="text-xs text-gray-500 mt-1">
                                            Barcode cannot be changed from this form. Use the barcode editor on the product detail page if needed.
                                        </p>
                                    </div>

                                    <!-- Reference Code -->  
                                    <x-form-group 
                                        name="reference" 
                                        label="Reference Code" 
                                        type="text" 
                                        :value="old('reference', $prefillData['reference'])" 
                                        placeholder="Optional reference" />
                                </div>

                                <!-- Product Category -->
                                <div>
                                    <label for="category" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Product Category *
                                        <span class="ml-1 text-red-500">●</span>
                                    </label>
                                    <select id="category" 
                                            name="category"
                                            required
                                            class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 focus:border-indigo-500 focus:ring-indigo-500">
                                        <option value="">Please select a category</option>
                                        @foreach($categories as $category)
                                            <option value="{{ $category->ID }}" {{ old('category', $product->CATEGORY) == $category->ID ? 'selected' : '' }}>
                                                {{ $category->NAME }}
                                            </option>
                                        @endforeach
                                    </select>
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
                                                    {{ old('supplier_id', $prefillData['supplier_id']) == $supplier->SupplierID ? 'selected' : '' }}>
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
                                           value="{{ old('supplier_code', $prefillData['supplier_code']) }}"
                                           class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 focus:border-indigo-500 focus:ring-indigo-500"
                                           placeholder="Supplier's product code">

                                    <!-- Duplicate Warning (initially hidden) -->
                                    <div id="duplicate-warning" class="mt-2 hidden">
                                        <div class="flex items-start p-3 bg-yellow-50 dark:bg-yellow-900 border border-yellow-300 dark:border-yellow-700 rounded-md">
                                            <svg class="w-5 h-5 text-yellow-600 dark:text-yellow-400 mr-2 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path>
                                            </svg>
                                            <div class="flex-1">
                                                <p class="text-sm font-medium text-yellow-800 dark:text-yellow-300">
                                                    This supplier code is already linked to:
                                                </p>
                                                <p id="conflict-product-info" class="text-sm text-yellow-700 dark:text-yellow-400 mt-1"></p>
                                                <a id="conflict-product-link" href="#" target="_blank" class="text-sm text-blue-600 dark:text-blue-400 hover:underline mt-1 inline-block">
                                                    View conflicting product →
                                                </a>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Checking Status -->
                                    <div id="duplicate-checking" class="mt-2 hidden">
                                        <p class="text-sm text-gray-500 dark:text-gray-400">
                                            <span class="inline-block animate-spin mr-1">⏳</span>
                                            Checking for duplicates...
                                        </p>
                                    </div>
                                </div>

                                <div>
                                    <label for="units_per_case" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Units per Case
                                        <span class="text-xs text-gray-500 block">Retail packages per case</span>
                                    </label>
                                    <input type="number" 
                                           id="units_per_case" 
                                           name="units_per_case" 
                                           value="{{ old('units_per_case', $prefillData['units_per_case']) }}" 
                                           min="1"
                                           class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 focus:border-indigo-500 focus:ring-indigo-500">
                                </div>

                            </div>

                            <!-- Stock Management Option -->
                            <div class="mt-6 pt-4 border-t border-gray-200 dark:border-gray-700">
                                <div class="flex items-start">
                                    <input type="checkbox" 
                                           id="include_in_stocking" 
                                           name="include_in_stocking" 
                                           value="1" 
                                           {{ old('include_in_stocking', $includeInStocking) ? 'checked' : '' }}
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
                                </h3>
                            </div>

                            <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
                                <!-- Cost Price -->
                                <x-form-group 
                                    name="price_buy" 
                                    label="Cost Price (€) *" 
                                    type="number" 
                                    :value="old('price_buy', $prefillData['price_buy'])" 
                                    required 
                                    step="0.01" 
                                    min="0" />

                                <!-- Selling Price -->
                                <div>
                                    <label for="price_sell" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Selling Price (€) *
                                    </label>
                                    <input type="number" 
                                           id="price_sell" 
                                           name="price_sell" 
                                           value="{{ old('price_sell', $prefillData['price_sell']) }}" 
                                           step="0.0001" 
                                           min="0" 
                                           required
                                           class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 focus:border-indigo-500 focus:ring-indigo-500">
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
                                        <option value="">Please select tax category</option>
                                        @foreach($taxCategories as $taxCategory)
                                            <option value="{{ $taxCategory->ID }}" 
                                                {{ old('tax_category', $product->TAXCAT) == $taxCategory->ID ? 'selected' : '' }}>
                                                {{ $taxCategory->NAME }}
                                            </option>
                                        @endforeach
                                    </select>
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
                                           value="{{ old('display_name', $prefillData['display_name']) }}" 
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
                                           {{ old('show_on_till', $showOnTill) ? 'checked' : '' }}
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

                        <!-- Short-Dated Product Settings (Admin/Manager Only) -->
                        @if(auth()->user()->hasAnyRole(['admin', 'manager']))
                            <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6">
                                <div class="border-b border-gray-200 dark:border-gray-700 pb-3 mb-4">
                                    <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Short-Dated Product</h3>
                                    <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                                        Flag products with short shelf life for special attention during ordering
                                    </p>
                                </div>

                                <div class="space-y-4">
                                    <!-- Short-Dated Checkbox -->
                                    <div class="flex items-start">
                                        <input type="checkbox"
                                               id="is_short_dated"
                                               name="is_short_dated"
                                               value="1"
                                               {{ old('is_short_dated', $orderSettings?->is_short_dated) ? 'checked' : '' }}
                                               class="mt-1 rounded border-gray-300 text-amber-600 focus:ring-amber-500">
                                        <label for="is_short_dated" class="ml-3 block text-sm text-gray-700 dark:text-gray-300">
                                            <span class="font-medium">Flag as short-dated</span>
                                            <span class="block text-xs text-gray-500 mt-1">
                                                Products flagged as short-dated will be highlighted on the orders page and automatically set to "review" priority
                                            </span>
                                        </label>
                                    </div>

                                    <!-- Shelf Life Days -->
                                    <div>
                                        <label for="shelf_life_days" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                            Shelf Life (Days)
                                            <span class="text-gray-500 font-normal">- Optional</span>
                                        </label>
                                        <input type="number"
                                               id="shelf_life_days"
                                               name="shelf_life_days"
                                               value="{{ old('shelf_life_days', $orderSettings?->shelf_life_days) }}"
                                               min="0"
                                               max="999"
                                               step="1"
                                               class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 focus:border-amber-500 focus:ring-amber-500"
                                               placeholder="e.g., 7">
                                        <p class="text-xs text-gray-500 mt-1">
                                            Specify how many days this product typically remains fresh (optional but recommended for short-dated items)
                                        </p>
                                    </div>

                                    <!-- Info box -->
                                    <div class="bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 rounded-lg p-3">
                                        <div class="flex items-start">
                                            <svg class="w-5 h-5 text-amber-600 dark:text-amber-400 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                                            </svg>
                                            <p class="ml-3 text-xs text-amber-800 dark:text-amber-200">
                                                Short-dated products will display with an amber border and warning icon on the orders page, helping you adjust quantities carefully to avoid waste.
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endif

                        <!-- Action Buttons -->
                        <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6">
                            <div class="flex items-center justify-end space-x-4">
                                @if($fromDelivery)
                                    <a href="{{ route('deliveries.show', ['delivery' => $fromDelivery]) }}" 
                                       class="px-4 py-2 bg-gray-300 hover:bg-gray-400 text-gray-800 rounded-md transition-colors duration-200">
                                        Cancel
                                    </a>
                                @elseif($fromContext === 'coffee')
                                    <a href="{{ route('coffee.products') }}" 
                                       class="px-4 py-2 bg-gray-300 hover:bg-gray-400 text-gray-800 rounded-md transition-colors duration-200">
                                        Cancel
                                    </a>
                                @else
                                    <a href="{{ route('products.show', $product->ID) }}" 
                                       class="px-4 py-2 bg-gray-300 hover:bg-gray-400 text-gray-800 rounded-md transition-colors duration-200">
                                        Cancel
                                    </a>
                                @endif
                                <button type="submit" 
                                        class="px-6 py-2 bg-green-600 hover:bg-green-700 text-white rounded-md transition-colors duration-200">
                                    Update Product
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Confirmation Modal for Supplier Link Override -->
    <div id="override-modal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
        <div class="relative top-20 mx-auto p-5 border w-11/12 md:w-3/4 lg:w-1/2 shadow-lg rounded-md bg-white dark:bg-gray-800">
            <div class="mt-3">
                <!-- Modal Header -->
                <div class="flex items-center justify-between pb-3 border-b border-gray-200 dark:border-gray-700">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 flex items-center">
                        <svg class="w-6 h-6 text-yellow-600 mr-2" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path>
                        </svg>
                        Supplier Link Conflict
                    </h3>
                </div>

                <!-- Modal Body -->
                <div class="mt-4 space-y-4">
                    <p class="text-sm text-gray-700 dark:text-gray-300">
                        This supplier code is already linked to another product. If you proceed, the supplier code will be reassigned to this product.
                    </p>

                    <div class="bg-gray-100 dark:bg-gray-700 rounded-lg p-4">
                        <h4 class="text-sm font-semibold text-gray-900 dark:text-gray-100 mb-2">Conflicting Product Details:</h4>
                        <dl class="space-y-2 text-sm">
                            <div class="flex">
                                <dt class="font-medium text-gray-700 dark:text-gray-300 w-32">Product Name:</dt>
                                <dd id="modal-product-name" class="text-gray-900 dark:text-gray-100"></dd>
                            </div>
                            <div class="flex">
                                <dt class="font-medium text-gray-700 dark:text-gray-300 w-32">Barcode:</dt>
                                <dd id="modal-product-barcode" class="text-gray-900 dark:text-gray-100 font-mono"></dd>
                            </div>
                            <div class="flex">
                                <dt class="font-medium text-gray-700 dark:text-gray-300 w-32">Supplier:</dt>
                                <dd id="modal-supplier-name" class="text-gray-900 dark:text-gray-100"></dd>
                            </div>
                        </dl>
                        <a id="modal-product-link" href="#" target="_blank" class="mt-3 inline-block text-sm text-blue-600 dark:text-blue-400 hover:underline">
                            View product details →
                        </a>
                    </div>

                    <div class="bg-yellow-50 dark:bg-yellow-900 border border-yellow-300 dark:border-yellow-700 rounded-md p-3">
                        <p class="text-sm text-yellow-800 dark:text-yellow-300">
                            <strong>⚠️ Warning:</strong> The supplier code will be removed from the product listed above and assigned to the current product.
                        </p>
                    </div>
                </div>

                <!-- Modal Footer -->
                <div class="mt-6 flex items-center justify-end space-x-3 pt-4 border-t border-gray-200 dark:border-gray-700">
                    <button type="button"
                            onclick="closeOverrideModal()"
                            class="px-4 py-2 bg-gray-300 hover:bg-gray-400 text-gray-800 rounded-md transition-colors duration-200">
                        Cancel
                    </button>
                    <button type="button"
                            onclick="confirmOverride()"
                            class="px-4 py-2 bg-red-600 hover:bg-red-700 text-white rounded-md transition-colors duration-200">
                        Proceed & Reassign
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Tax rates mapping from PHP (actual database values)
        const taxRates = @json($taxRates);
        
        console.log('Edit page script loaded, tax rates:', taxRates);
        
        // Track if user has manually entered selling price
        let userHasSetSellingPrice = true; // Default to true for edit form since price is already set
        
        // Pricing breakdown calculation
        function updatePricingBreakdown() {
            console.log('updatePricingBreakdown called');
            
            const costPriceEl = document.getElementById('price_buy');
            const sellPriceEl = document.getElementById('price_sell');
            const taxCategoryEl = document.getElementById('tax_category');
            
            if (!costPriceEl || !sellPriceEl || !taxCategoryEl) {
                console.error('Missing form elements:', {costPriceEl, sellPriceEl, taxCategoryEl});
                return;
            }
            
            const costPrice = parseFloat(costPriceEl.value) || 0;
            const sellPrice = parseFloat(sellPriceEl.value) || 0;
            const taxCategoryId = taxCategoryEl.value;
            
            console.log('Selected tax category ID:', taxCategoryId);
            // Get tax rate from the PHP-provided rates, convert string to float
            const taxRate = taxCategoryId && taxRates[taxCategoryId] !== undefined ? parseFloat(taxRates[taxCategoryId]) : 0.00;
            console.log('Applied tax rate:', taxRate);
            
            // Calculate VAT (selling price is VAT inclusive)
            // Special handling for 0% VAT
            const sellPriceExVat = taxRate === 0 ? sellPrice : sellPrice / (1 + taxRate);
            const vatAmount = taxRate === 0 ? 0 : sellPrice - sellPriceExVat;
            
            console.log('VAT calculation:', {sellPrice, taxRate, sellPriceExVat, vatAmount});
            
            // Calculate profit margin (excluding VAT) - based on selling price, not cost
            const marginAmount = sellPriceExVat - costPrice;
            const marginPercentage = sellPriceExVat > 0 ? (marginAmount / sellPriceExVat) * 100 : 0;
            
            // Debug logging
            console.log('Pricing breakdown:', {
                costPrice, sellPrice, taxCategoryId, taxRate,
                sellPriceExVat, vatAmount,
                marginAmount, marginPercentage
            });
            
            // Update breakdown display - check if elements exist
            const elements = {
                'breakdown-cost': costPrice.toFixed(2),
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
        }
        
        // Recalculate summary when cost price changes
        const priceBuyEl = document.getElementById('price_buy');
        if (priceBuyEl) {
            priceBuyEl.addEventListener('input', function() {
                updatePricingBreakdown();
            });
        }

        // Update pricing when selling price changes
        const priceSellEl = document.getElementById('price_sell');
        if (priceSellEl) {
            priceSellEl.addEventListener('input', function() {
                updatePricingBreakdown();
            });
        }
        
        // Update pricing when tax category changes
        const taxCategoryEl = document.getElementById('tax_category');
        if (taxCategoryEl) {
            taxCategoryEl.addEventListener('change', function() {
                updatePricingBreakdown();
            });
        }

        // Initialize display name preview (with error handling)
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
        
        // Initialize everything when DOM is ready
        document.addEventListener('DOMContentLoaded', function() {
            console.log('Edit page DOM loaded, initializing...');
            
            // Test if we can find the margin elements
            const marginAmount = document.getElementById('margin-amount');
            const marginPercentage = document.getElementById('margin-percentage');
            console.log('Margin elements found:', {marginAmount, marginPercentage});
            
            // Call pricing breakdown update after a small delay to ensure all fields are initialized
            setTimeout(() => {
                updatePricingBreakdown();
            }, 100);

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

            // Initialize supplier link duplicate checking
            try {
                initializeSupplierLinkDuplicateCheck();
            } catch (e) {
                console.log('Supplier link duplicate check initialization skipped:', e.message);
            }
        });

        // Supplier Link Duplicate Checking
        let duplicateCheckTimeout = null;
        let currentConflict = null;
        let isCheckingDuplicate = false;

        function initializeSupplierLinkDuplicateCheck() {
            const supplierCodeField = document.getElementById('supplier_code');
            const supplierIdField = document.getElementById('supplier_id');
            const form = supplierCodeField?.closest('form');

            if (!supplierCodeField || !supplierIdField || !form) return;

            // Add change listeners for real-time checking
            supplierCodeField.addEventListener('input', debounceCheckDuplicate);
            supplierIdField.addEventListener('change', checkDuplicate);

            // Intercept form submission
            form.addEventListener('submit', handleFormSubmit);

            // Check on page load if fields have values
            if (supplierCodeField.value && supplierIdField.value) {
                checkDuplicate();
            }
        }

        function debounceCheckDuplicate() {
            clearTimeout(duplicateCheckTimeout);
            duplicateCheckTimeout = setTimeout(checkDuplicate, 500);
        }

        async function checkDuplicate() {
            const supplierCodeField = document.getElementById('supplier_code');
            const supplierIdField = document.getElementById('supplier_id');
            const warningDiv = document.getElementById('duplicate-warning');
            const checkingDiv = document.getElementById('duplicate-checking');

            if (!supplierCodeField || !supplierIdField) return;

            const supplierCode = supplierCodeField.value.trim();
            const supplierId = supplierIdField.value;

            // Hide warning and checking if fields are empty
            if (!supplierCode || !supplierId) {
                warningDiv.classList.add('hidden');
                checkingDiv.classList.add('hidden');
                currentConflict = null;
                return;
            }

            // Show checking status
            warningDiv.classList.add('hidden');
            checkingDiv.classList.remove('hidden');
            isCheckingDuplicate = true;

            try {
                const response = await fetch('/api/products/check-supplier-link-duplicate', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({
                        supplier_code: supplierCode,
                        supplier_id: parseInt(supplierId),
                        current_product_id: '{{ $product->ID }}',
                    }),
                });

                const data = await response.json();
                checkingDiv.classList.add('hidden');
                isCheckingDuplicate = false;

                if (data.duplicate && data.conflict) {
                    // Show warning with conflict details
                    currentConflict = data.conflict;
                    displayDuplicateWarning(data.conflict);
                } else {
                    // No duplicate found
                    warningDiv.classList.add('hidden');
                    currentConflict = null;
                }
            } catch (error) {
                console.error('Error checking for duplicates:', error);
                checkingDiv.classList.add('hidden');
                isCheckingDuplicate = false;
            }
        }

        function displayDuplicateWarning(conflict) {
            const warningDiv = document.getElementById('duplicate-warning');
            const productInfo = document.getElementById('conflict-product-info');
            const productLink = document.getElementById('conflict-product-link');

            productInfo.textContent = `${conflict.product_name} (${conflict.product_barcode}) - Supplier: ${conflict.supplier_name}`;
            productLink.href = conflict.edit_url || '#';

            warningDiv.classList.remove('hidden');
        }

        function handleFormSubmit(e) {
            const forceOverrideField = document.getElementById('force_override');

            // If there's a conflict and override is not set, show modal
            if (currentConflict && forceOverrideField.value === '0') {
                e.preventDefault();
                showOverrideModal(currentConflict);
                return false;
            }

            // Allow form to submit normally
            return true;
        }

        function showOverrideModal(conflict) {
            const modal = document.getElementById('override-modal');
            const modalProductName = document.getElementById('modal-product-name');
            const modalProductBarcode = document.getElementById('modal-product-barcode');
            const modalSupplierName = document.getElementById('modal-supplier-name');
            const modalProductLink = document.getElementById('modal-product-link');

            if (!modal) return;

            // Populate modal with conflict details
            modalProductName.textContent = conflict.product_name || 'Unknown';
            modalProductBarcode.textContent = conflict.product_barcode || 'N/A';
            modalSupplierName.textContent = conflict.supplier_name || 'Unknown';
            modalProductLink.href = conflict.edit_url || '#';

            // Show modal
            modal.classList.remove('hidden');
        }

        function closeOverrideModal() {
            const modal = document.getElementById('override-modal');
            if (modal) {
                modal.classList.add('hidden');
            }
        }

        function confirmOverride() {
            const forceOverrideField = document.getElementById('force_override');
            const form = forceOverrideField.closest('form');

            // Set override flag
            forceOverrideField.value = '1';

            // Close modal
            closeOverrideModal();

            // Submit form
            if (form) {
                form.submit();
            }
        }

        // Close modal when clicking outside
        document.getElementById('override-modal')?.addEventListener('click', function(e) {
            if (e.target === this) {
                closeOverrideModal();
            }
        });
    </script>
</x-admin-layout>
