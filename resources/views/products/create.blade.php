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
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
            <x-alert type="error" :messages="$errors->all()" />

            <form action="{{ route('products.store') }}" method="POST" class="space-y-6">
                @csrf
                
                @if($deliveryItemId)
                    <input type="hidden" name="delivery_item_id" value="{{ $deliveryItemId }}">
                    <div class="bg-blue-50 border border-blue-200 text-blue-700 px-4 py-3 rounded mb-6">
                        <div class="font-bold">Creating product from delivery item</div>
                        <p class="mt-1">Some fields have been pre-populated from the delivery information.</p>
                    </div>
                @endif

                <!-- Basic Information Section -->
                <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6">
                    <div class="border-b border-gray-200 dark:border-gray-700 pb-4 mb-6">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Basic Information</h3>
                        <p class="text-sm text-gray-600 dark:text-gray-400">Enter the core product details</p>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
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
                        </div>

                        <!-- Product Code/Barcode -->
                        <x-form-group 
                            name="code" 
                            label="Product Code/Barcode *" 
                            type="text" 
                            :value="old('code', $prefillData['code'] ?? '')" 
                            required 
                            placeholder="Enter product code or scan barcode" />

                        <!-- Reference Code -->  
                        <div class="md:col-span-2">
                            <x-form-group 
                                name="reference" 
                                label="Reference Code" 
                                type="text" 
                                :value="old('reference')" 
                                placeholder="Optional reference code" 
                                containerClass="" />
                        </div>
                    </div>
                </div>

                <!-- Pricing Section -->
                <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6">
                    <div class="border-b border-gray-200 dark:border-gray-700 pb-4 mb-6">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Pricing & Tax</h3>
                        <p class="text-sm text-gray-600 dark:text-gray-400">Set pricing and tax information</p>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
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
                </div>

                <!-- Category Section -->
                <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6">
                    <div class="border-b border-gray-200 dark:border-gray-700 pb-4 mb-6">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Category</h3>
                        <p class="text-sm text-gray-600 dark:text-gray-400">Assign product to a category</p>
                    </div>

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

                <!-- Product Configuration -->
                <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6">
                    <div class="border-b border-gray-200 dark:border-gray-700 pb-4 mb-6">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Configuration</h3>
                        <p class="text-sm text-gray-600 dark:text-gray-400">Product type and behavior settings</p>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                        <!-- Service Item -->
                        <div class="flex items-center">
                            <input type="checkbox" 
                                   id="is_service" 
                                   name="is_service" 
                                   value="1" 
                                   {{ old('is_service') ? 'checked' : '' }}
                                   class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                            <label for="is_service" class="ml-2 block text-sm text-gray-700 dark:text-gray-300">
                                Service Item
                                <span class="block text-xs text-gray-500">No physical inventory</span>
                            </label>
                        </div>

                        <!-- Scale Item -->
                        <div class="flex items-center">
                            <input type="checkbox" 
                                   id="is_scale" 
                                   name="is_scale" 
                                   value="1" 
                                   {{ old('is_scale') ? 'checked' : '' }}
                                   class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                            <label for="is_scale" class="ml-2 block text-sm text-gray-700 dark:text-gray-300">
                                Sold by Weight
                                <span class="block text-xs text-gray-500">Price per kg/lb</span>
                            </label>
                        </div>

                        <!-- Kitchen Item -->
                        <div class="flex items-center">
                            <input type="checkbox" 
                                   id="is_kitchen" 
                                   name="is_kitchen" 
                                   value="1" 
                                   {{ old('is_kitchen') ? 'checked' : '' }}
                                   class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                            <label for="is_kitchen" class="ml-2 block text-sm text-gray-700 dark:text-gray-300">
                                Kitchen Item
                                <span class="block text-xs text-gray-500">Needs preparation</span>
                            </label>
                        </div>

                        <!-- Print to Kitchen -->
                        <div class="flex items-center">
                            <input type="checkbox" 
                                   id="print_kb" 
                                   name="print_kb" 
                                   value="1" 
                                   {{ old('print_kb') ? 'checked' : '' }}
                                   class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                            <label for="print_kb" class="ml-2 block text-sm text-gray-700 dark:text-gray-300">
                                Print to Kitchen
                                <span class="block text-xs text-gray-500">Send to kitchen printer</span>
                            </label>
                        </div>

                        <!-- Send Status -->
                        <div class="flex items-center">
                            <input type="checkbox" 
                                   id="send_status" 
                                   name="send_status" 
                                   value="1" 
                                   {{ old('send_status') ? 'checked' : '' }}
                                   class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                            <label for="send_status" class="ml-2 block text-sm text-gray-700 dark:text-gray-300">
                                Send Status Updates
                                <span class="block text-xs text-gray-500">Status notifications</span>
                            </label>
                        </div>

                        <!-- Commission -->
                        <div class="flex items-center">
                            <input type="checkbox" 
                                   id="is_com" 
                                   name="is_com" 
                                   value="1" 
                                   {{ old('is_com') ? 'checked' : '' }}
                                   class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                            <label for="is_com" class="ml-2 block text-sm text-gray-700 dark:text-gray-300">
                                Commission Item
                                <span class="block text-xs text-gray-500">Affects commission</span>
                            </label>
                        </div>
                    </div>
                </div>

                <!-- Stock Information -->
                <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6">
                    <div class="border-b border-gray-200 dark:border-gray-700 pb-4 mb-6">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Stock Information</h3>
                        <p class="text-sm text-gray-600 dark:text-gray-400">Initial stock and cost settings</p>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
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

                <!-- Supplier Information -->
                <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6">
                    <div class="border-b border-gray-200 dark:border-gray-700 pb-4 mb-6">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Supplier Information</h3>
                        <p class="text-sm text-gray-600 dark:text-gray-400">Link product to supplier (optional)</p>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
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
                                <span class="text-xs text-gray-500 block">Retail packages per wholesale case</span>
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
                                   placeholder="Auto-filled from cost price">
                        </div>
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="flex items-center justify-end space-x-4 pt-6">
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
        
        // Auto-calculate selling price based on cost price with 30% margin
        // Only update if no scraped price is available, or if user manually changed the cost
        document.getElementById('price_buy').addEventListener('input', function() {
            const costPrice = parseFloat(this.value);
            const sellPriceField = document.getElementById('price_sell');
            
            if (!isNaN(costPrice) && costPrice > 0) {
                // Only auto-update selling price if no scraped price or if field is empty
                if (!hasScrapedPrice || !sellPriceField.value) {
                    const sellPrice = costPrice * 1.3; // 30% margin
                    sellPriceField.value = sellPrice.toFixed(4);
                }
            }
        });

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
        
        // Add helpful tooltip if scraped pricing is being used
        @if(isset($prefillData['price_source']) && $prefillData['price_source'] === 'udea_scraped')
            document.getElementById('price_sell').title = "This price was automatically retrieved from UDEA website. You can still modify it if needed.";
        @endif
    </script>
</x-admin-layout>