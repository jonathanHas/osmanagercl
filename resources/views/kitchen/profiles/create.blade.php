<x-admin-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('Create Ingredient Profile') }}
            </h2>
            <a href="{{ route('kitchen.profiles.index') }}" class="text-gray-600 hover:text-gray-900">
                &larr; Back to Profiles
            </a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            <!-- Info Box -->
            <div class="mb-6 bg-blue-50 border border-blue-200 rounded-lg p-4">
                <h3 class="font-medium text-blue-800 mb-2">Creating a Profile</h3>
                <p class="text-sm text-blue-700">
                    Link to a POS product for automatic cost updates, or enter costs manually for ingredients not yet in POS.
                </p>
            </div>

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6" x-data="profileForm()">
                    <form method="POST" action="{{ route('kitchen.profiles.store') }}" id="profileForm">
                        @csrf

                        <!-- Cost Source Toggle -->
                        <div class="mb-6">
                            <label class="block text-sm font-medium text-gray-700 mb-3">Cost Source</label>
                            <div class="flex space-x-4">
                                <label class="inline-flex items-center cursor-pointer">
                                    <input type="radio" x-model="mode" value="product" class="form-radio text-indigo-600">
                                    <span class="ml-2 text-sm text-gray-700">Link to POS Product</span>
                                </label>
                                <label class="inline-flex items-center cursor-pointer">
                                    <input type="radio" x-model="mode" value="manual" class="form-radio text-indigo-600">
                                    <span class="ml-2 text-sm text-gray-700">Manual Entry</span>
                                </label>
                            </div>
                        </div>

                        <!-- Product Search (shown when mode = product) -->
                        <div class="mb-6" x-show="mode === 'product'" x-transition>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                POS Product
                            </label>
                            <div class="relative">
                                <input type="text" x-model="searchQuery" @input.debounce.300ms="searchProducts()"
                                       placeholder="Search products by name or code..."
                                       autocomplete="off"
                                       class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <div x-show="showResults && results.length > 0" x-cloak
                                     class="absolute z-10 w-full mt-1 bg-white border border-gray-300 rounded-md shadow-lg max-h-60 overflow-y-auto">
                                    <template x-for="product in results" :key="product.id">
                                        <div @click.stop="selectProduct(product)"
                                             class="px-4 py-2 hover:bg-gray-100 cursor-pointer">
                                            <div class="font-medium" x-text="product.name"></div>
                                            <div class="text-sm text-gray-500">
                                                <span x-text="product.code"></span> · €<span x-text="parseFloat(product.cost || 0).toFixed(2)"></span>
                                            </div>
                                        </div>
                                    </template>
                                </div>
                            </div>
                            <input type="hidden" name="pos_product_id" x-model="selectedProductId">
                            @error('pos_product_id')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror

                            <!-- Selected Product Display -->
                            <div x-show="selectedProductId" class="mt-3 p-3 bg-green-50 border border-green-200 rounded-md">
                                <div class="flex justify-between items-start">
                                    <div>
                                        <p class="font-medium text-green-900" x-text="selectedProductName"></p>
                                        <p class="text-sm text-green-600" x-text="selectedProductCode"></p>
                                    </div>
                                    <div class="text-right">
                                        <p class="text-sm text-green-600">Supplier Cost:</p>
                                        <p class="font-medium text-green-800">€<span x-text="supplierCost.toFixed(2)"></span></p>
                                    </div>
                                </div>
                                <button type="button" @click="clearProduct()" class="mt-2 text-sm text-red-600 hover:text-red-800">
                                    &times; Clear selection
                                </button>
                            </div>
                        </div>

                        <!-- Manual Cost (shown when mode = manual) -->
                        <div class="mb-6" x-show="mode === 'manual'" x-transition>
                            <label for="manual_cost" class="block text-sm font-medium text-gray-700 mb-1">
                                Supplier Cost <span class="text-red-500">*</span>
                            </label>
                            <div class="relative">
                                <span class="absolute left-3 top-2 text-gray-500">€</span>
                                <input type="number" name="manual_cost" id="manual_cost" x-model="manualCost"
                                       step="0.01" min="0"
                                       class="w-full pl-8 rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                       placeholder="0.00">
                            </div>
                            <p class="mt-1 text-xs text-gray-500">Enter the total supplier cost for the purchase size below</p>
                            @error('manual_cost')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Delivery Markup -->
                        <div class="mb-6 p-4 bg-amber-50 border border-amber-200 rounded-lg">
                            <div class="flex items-start gap-3">
                                <input type="checkbox" name="apply_delivery_markup" id="apply_delivery_markup"
                                       x-model="applyDeliveryMarkup" value="1"
                                       class="mt-1 rounded border-gray-300 text-amber-600 focus:ring-amber-500">
                                <div class="flex-1">
                                    <label for="apply_delivery_markup" class="block text-sm font-medium text-amber-800 cursor-pointer">
                                        Apply Delivery Markup
                                    </label>
                                    <p class="text-xs text-amber-600 mt-1">
                                        Add delivery cost markup for imported products (Udea, Dynamis)
                                    </p>
                                    <div x-show="applyDeliveryMarkup" x-transition class="mt-3 flex items-center gap-3">
                                        <input type="number" name="delivery_markup_percent" id="delivery_markup_percent"
                                               x-model="deliveryMarkupPercent"
                                               x-bind:disabled="!applyDeliveryMarkup"
                                               step="0.1" min="0" max="100"
                                               class="w-24 rounded-md border-amber-300 shadow-sm focus:border-amber-500 focus:ring-amber-500">
                                        <span class="text-sm text-amber-700">% markup</span>
                                    </div>
                                    <div x-show="isImportedSupplier && mode === 'product'" class="mt-2">
                                        <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-amber-200 text-amber-800">
                                            Auto-detected: Imported supplier
                                        </span>
                                    </div>
                                </div>
                            </div>
                            @error('delivery_markup_percent')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Profile Name -->
                        <div class="mb-6">
                            <label for="name" class="block text-sm font-medium text-gray-700 mb-1">
                                Profile Name <span class="text-red-500">*</span>
                            </label>
                            <input type="text" name="name" id="name" x-model="profileName" value="{{ old('name') }}"
                                   class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                   placeholder="e.g., Plain Flour">
                            @error('name')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Purchase Size -->
                        <div class="mb-6">
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                Purchase Size <span class="text-red-500">*</span>
                            </label>
                            <p class="text-xs text-gray-500 mb-2">How is this product purchased from supplier?</p>
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label for="purchase_quantity" class="sr-only">Quantity</label>
                                    <input type="number" name="purchase_quantity" id="purchase_quantity" x-model="purchaseQuantity"
                                           value="{{ old('purchase_quantity', 1) }}"
                                           step="0.0001" min="0.0001"
                                           class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                           placeholder="e.g., 25">
                                    @error('purchase_quantity')
                                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>
                                <div>
                                    <label for="purchase_unit" class="sr-only">Unit</label>
                                    <select name="purchase_unit" id="purchase_unit" x-model="purchaseUnit"
                                            class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                        <optgroup label="Weight">
                                            <option value="kg" {{ old('purchase_unit') == 'kg' ? 'selected' : '' }}>Kilograms (kg)</option>
                                            <option value="g" {{ old('purchase_unit') == 'g' ? 'selected' : '' }}>Grams (g)</option>
                                        </optgroup>
                                        <optgroup label="Volume">
                                            <option value="L" {{ old('purchase_unit') == 'L' ? 'selected' : '' }}>Litres (L)</option>
                                            <option value="ml" {{ old('purchase_unit') == 'ml' ? 'selected' : '' }}>Millilitres (ml)</option>
                                            <option value="tbsp" {{ old('purchase_unit') == 'tbsp' ? 'selected' : '' }}>Tablespoons (tbsp)</option>
                                            <option value="tsp" {{ old('purchase_unit') == 'tsp' ? 'selected' : '' }}>Teaspoons (tsp)</option>
                                        </optgroup>
                                        <optgroup label="Count">
                                            <option value="unit" {{ old('purchase_unit', 'unit') == 'unit' ? 'selected' : '' }}>Units</option>
                                            <option value="dozen" {{ old('purchase_unit') == 'dozen' ? 'selected' : '' }}>Dozen (12)</option>
                                            <option value="pack" {{ old('purchase_unit') == 'pack' ? 'selected' : '' }}>Pack</option>
                                        </optgroup>
                                    </select>
                                    @error('purchase_unit')
                                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>
                            </div>
                            <p class="mt-2 text-sm text-gray-500">
                                Example: If flour comes in 25kg bags, enter "25" and select "Kilograms (kg)"
                            </p>
                        </div>

                        <!-- Cost Preview -->
                        <div x-show="canShowPreview()" x-transition
                             class="mb-6 p-4 bg-indigo-50 border border-indigo-200 rounded-lg">
                            <h4 class="font-medium text-indigo-800 mb-2">Cost Calculation Preview</h4>
                            <div class="grid grid-cols-2 gap-4 text-sm">
                                <div>
                                    <p class="text-indigo-600">Purchase Size:</p>
                                    <p class="font-medium text-indigo-900" x-text="purchaseQuantity + ' ' + purchaseUnit">-</p>
                                </div>
                                <div>
                                    <p class="text-indigo-600">Supplier Cost:</p>
                                    <p class="font-medium text-indigo-900">€<span x-text="getBaseCost().toFixed(2)"></span></p>
                                </div>
                                <template x-if="applyDeliveryMarkup && deliveryMarkupPercent > 0">
                                    <div class="col-span-2 pt-2 border-t border-indigo-200">
                                        <div class="flex justify-between items-center">
                                            <span class="text-amber-600">+ Delivery Markup (<span x-text="deliveryMarkupPercent"></span>%):</span>
                                            <span class="font-medium text-amber-700">€<span x-text="(getBaseCost() * deliveryMarkupPercent / 100).toFixed(2)"></span></span>
                                        </div>
                                        <div class="flex justify-between items-center mt-1">
                                            <span class="text-indigo-600">Effective Cost:</span>
                                            <span class="font-medium text-indigo-900">€<span x-text="getEffectiveCost().toFixed(2)"></span></span>
                                        </div>
                                    </div>
                                </template>
                                <div class="col-span-2 pt-2 border-t border-indigo-200">
                                    <p class="text-indigo-600">Cost per Base Unit:</p>
                                    <p class="text-xl font-bold text-indigo-900" x-text="getCostPerUnit()">-</p>
                                </div>
                            </div>
                        </div>

                        <!-- Density Section (for weight↔volume conversions) -->
                        <div class="mb-6" x-show="showDensitySection()" x-transition>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                Density (optional)
                            </label>
                            <p class="text-xs text-gray-500 mb-2">
                                For weight↔volume conversions. Enter grams per ml (g/ml).
                            </p>
                            <div class="flex items-center gap-3">
                                <input type="number" name="density" id="density" x-model="density"
                                       step="0.001" min="0" max="10"
                                       class="w-32 rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                       placeholder="e.g., 0.53">
                                <span class="text-sm text-gray-600">g/ml</span>
                            </div>
                            <p class="text-xs text-gray-500 mt-2">
                                Common: Ground spices ~0.53, Flour ~0.53, Sugar ~0.85, Salt ~1.2, Oil ~0.92
                            </p>
                            <div x-show="density > 0" class="mt-2 text-sm text-indigo-600">
                                1 tbsp (15ml) = <span x-text="(density * 15).toFixed(1)"></span>g
                            </div>
                        </div>

                        <!-- Notes -->
                        <div class="mb-6">
                            <label for="notes" class="block text-sm font-medium text-gray-700 mb-1">
                                Notes
                            </label>
                            <textarea name="notes" id="notes" rows="3"
                                      class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                      placeholder="Optional notes about this ingredient...">{{ old('notes') }}</textarea>
                        </div>

                        <!-- Submit -->
                        <div class="flex justify-end space-x-3">
                            <a href="{{ route('kitchen.profiles.index') }}"
                               class="px-4 py-2 bg-gray-200 text-gray-700 rounded-md hover:bg-gray-300">
                                Cancel
                            </a>
                            <button type="submit" :disabled="!canSubmit()"
                                    class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 disabled:opacity-50 disabled:cursor-not-allowed">
                                Create Profile
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
    <script>
        function profileForm() {
            return {
                mode: 'product',
                searchQuery: '',
                results: [],
                showResults: false,
                selectedProductId: '{{ old('pos_product_id', $prefillProduct?->ID ?? '') }}',
                selectedProductName: '{{ old('name', $prefillProduct?->NAME ?? '') }}',
                selectedProductCode: '{{ $prefillProduct?->CODE ?? '' }}',
                supplierCost: {{ $prefillProduct?->PRICEBUY ?? 0 }},
                manualCost: {{ old('manual_cost', 0) }},
                profileName: '{{ old('name', $prefillProduct?->NAME ?? '') }}',
                purchaseQuantity: {{ old('purchase_quantity', 1) }},
                purchaseUnit: '{{ old('purchase_unit', 'unit') }}',
                density: {{ old('density', 0) }},
                applyDeliveryMarkup: {{ old('apply_delivery_markup') ? 'true' : 'false' }},
                deliveryMarkupPercent: {{ old('delivery_markup_percent', 15) }},
                isImportedSupplier: false,

                unitConversions: {
                    'kg': { base: 'g', multiplier: 1000 },
                    'g': { base: 'g', multiplier: 1 },
                    'L': { base: 'ml', multiplier: 1000 },
                    'ml': { base: 'ml', multiplier: 1 },
                    'tbsp': { base: 'ml', multiplier: 15 },
                    'tsp': { base: 'ml', multiplier: 5 },
                    'unit': { base: 'unit', multiplier: 1 },
                    'dozen': { base: 'unit', multiplier: 12 },
                    'pack': { base: 'unit', multiplier: 1 }
                },

                async searchProducts() {
                    if (this.searchQuery.length < 2) {
                        this.results = [];
                        this.showResults = false;
                        return;
                    }

                    try {
                        const response = await fetch(`{{ route('kitchen.api.profiles.products.search') }}?q=${encodeURIComponent(this.searchQuery)}`);
                        this.results = await response.json();
                        this.showResults = true;
                    } catch (error) {
                        console.error('Search failed:', error);
                    }
                },

                selectProduct(product) {
                    this.selectedProductId = product.id;
                    this.selectedProductName = product.name;
                    this.selectedProductCode = product.code;
                    this.supplierCost = parseFloat(product.cost) || 0;
                    if (!this.profileName) {
                        this.profileName = product.name;
                    }

                    // Auto-enable delivery markup for imported suppliers (Udea, Dynamis)
                    this.isImportedSupplier = product.is_imported_supplier || false;
                    if (this.isImportedSupplier) {
                        this.applyDeliveryMarkup = true;
                    }

                    this.searchQuery = '';
                    this.showResults = false;
                    this.results = [];
                },

                clearProduct() {
                    this.selectedProductId = '';
                    this.selectedProductName = '';
                    this.selectedProductCode = '';
                    this.supplierCost = 0;
                    this.isImportedSupplier = false;
                    this.applyDeliveryMarkup = false;
                },

                getBaseCost() {
                    if (this.mode === 'product') {
                        return this.supplierCost;
                    }
                    return parseFloat(this.manualCost) || 0;
                },

                getEffectiveCost() {
                    let baseCost = this.getBaseCost();
                    if (this.applyDeliveryMarkup && this.deliveryMarkupPercent > 0) {
                        baseCost *= (1 + (this.deliveryMarkupPercent / 100));
                    }
                    return baseCost;
                },

                canShowPreview() {
                    const hasCost = this.getBaseCost() > 0;
                    const hasQuantity = parseFloat(this.purchaseQuantity) > 0;
                    return hasCost && hasQuantity;
                },

                getCostPerUnit() {
                    const cost = this.getEffectiveCost();
                    const quantity = parseFloat(this.purchaseQuantity) || 0;
                    const conversion = this.unitConversions[this.purchaseUnit];

                    if (!conversion || quantity <= 0 || cost <= 0) {
                        return '-';
                    }

                    const baseUnits = quantity * conversion.multiplier;
                    const costPerUnit = cost / baseUnits;
                    return `€${costPerUnit.toFixed(6)} per ${conversion.base}`;
                },

                canSubmit() {
                    const hasName = this.profileName.trim().length > 0;
                    const hasQuantity = parseFloat(this.purchaseQuantity) > 0;

                    if (this.mode === 'product') {
                        return hasName && hasQuantity && this.selectedProductId;
                    } else {
                        return hasName && hasQuantity && parseFloat(this.manualCost) > 0;
                    }
                },

                showDensitySection() {
                    // Show density section for weight or volume units (not count)
                    const weightUnits = ['kg', 'g'];
                    const volumeUnits = ['L', 'ml', 'tbsp', 'tsp'];
                    return weightUnits.includes(this.purchaseUnit) || volumeUnits.includes(this.purchaseUnit);
                }
            };
        }
    </script>
    @endpush
</x-admin-layout>
