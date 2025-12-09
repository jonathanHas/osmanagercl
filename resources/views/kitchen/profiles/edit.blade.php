<x-admin-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('Edit Ingredient Profile') }}
            </h2>
            <a href="{{ route('kitchen.profiles.index') }}" class="text-gray-600 hover:text-gray-900">
                &larr; Back to Profiles
            </a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6" x-data="profileEditForm()">
                    <form method="POST" action="{{ route('kitchen.profiles.update', $profile) }}" id="profileForm">
                        @csrf
                        @method('PUT')

                        <!-- Cost Source Section -->
                        <div class="mb-6 p-4 rounded-lg" :class="hasProduct ? 'bg-green-50 border border-green-200' : 'bg-amber-50 border border-amber-200'">
                            <div class="flex justify-between items-start">
                                <div>
                                    <h3 class="text-sm font-medium" :class="hasProduct ? 'text-green-800' : 'text-amber-800'">
                                        <span x-show="hasProduct">Linked POS Product</span>
                                        <span x-show="!hasProduct">Manual Cost Entry</span>
                                    </h3>

                                    <!-- Linked product info -->
                                    <div x-show="hasProduct" class="mt-2">
                                        <p class="font-medium text-green-900" x-text="productName"></p>
                                        <p class="text-sm text-green-600" x-text="productCode"></p>
                                    </div>

                                    <!-- Manual mode info -->
                                    <p x-show="!hasProduct" class="mt-2 text-sm text-amber-700">
                                        This profile uses a manually entered cost. Link to a POS product to enable automatic cost updates.
                                    </p>
                                </div>
                                <div class="text-right">
                                    <p class="text-sm" :class="hasProduct ? 'text-green-600' : 'text-amber-600'">Current Cost:</p>
                                    <p class="font-medium" :class="hasProduct ? 'text-green-800' : 'text-amber-800'">
                                        €<span x-text="getEffectiveCost().toFixed(2)"></span>
                                    </p>
                                    <span class="text-xs px-2 py-0.5 rounded-full" :class="hasProduct ? 'bg-green-200 text-green-800' : 'bg-amber-200 text-amber-800'" x-text="hasProduct ? 'POS' : 'Manual'"></span>
                                </div>
                            </div>

                            <!-- Link/Unlink Product Actions -->
                            <div class="mt-3 pt-3 border-t" :class="hasProduct ? 'border-green-200' : 'border-amber-200'">
                                <template x-if="hasProduct">
                                    <button type="button" @click="unlinkProduct()" class="text-sm text-red-600 hover:text-red-800">
                                        &times; Unlink product (switch to manual cost)
                                    </button>
                                </template>
                                <template x-if="!hasProduct && !showProductSearch">
                                    <button type="button" @click="showProductSearch = true" class="text-sm text-indigo-600 hover:text-indigo-800">
                                        + Link to POS Product
                                    </button>
                                </template>
                            </div>

                            <!-- Product Search (when linking) -->
                            <div x-show="showProductSearch && !hasProduct" class="mt-3 pt-3 border-t border-amber-200">
                                <label class="block text-sm font-medium text-gray-700 mb-1">Search POS Products</label>
                                <div class="relative">
                                    <input type="text" x-model="searchQuery" @input.debounce.300ms="searchProducts()"
                                           placeholder="Search by name or code..."
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
                                <button type="button" @click="showProductSearch = false" class="mt-2 text-sm text-gray-500 hover:text-gray-700">
                                    Cancel
                                </button>
                            </div>
                        </div>

                        <input type="hidden" name="pos_product_id" x-model="posProductId">

                        <!-- Manual Cost (when no product linked) -->
                        <div class="mb-6" x-show="!hasProduct" x-transition>
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
                            <p class="mt-1 text-xs text-gray-500">Enter the total supplier cost for the purchase size</p>
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
                                    @if($profile->isFromImportedSupplier())
                                        <div class="mt-2">
                                            <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-amber-200 text-amber-800">
                                                Imported supplier detected
                                            </span>
                                        </div>
                                    @endif
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
                            <input type="text" name="name" id="name" x-model="profileName"
                                   class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
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
                                    <input type="number" name="purchase_quantity" id="purchase_quantity" x-model="purchaseQuantity"
                                           step="0.0001" min="0.0001"
                                           class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                    @error('purchase_quantity')
                                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>
                                <div>
                                    <select name="purchase_unit" id="purchase_unit" x-model="purchaseUnit"
                                            class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                        <optgroup label="Weight">
                                            <option value="kg">Kilograms (kg)</option>
                                            <option value="g">Grams (g)</option>
                                        </optgroup>
                                        <optgroup label="Volume">
                                            <option value="L">Litres (L)</option>
                                            <option value="ml">Millilitres (ml)</option>
                                            <option value="tbsp">Tablespoons (tbsp)</option>
                                            <option value="tsp">Teaspoons (tsp)</option>
                                        </optgroup>
                                        <optgroup label="Count">
                                            <option value="unit">Units</option>
                                            <option value="dozen">Dozen (12)</option>
                                            <option value="pack">Pack</option>
                                        </optgroup>
                                    </select>
                                    @error('purchase_unit')
                                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>
                            </div>
                        </div>

                        <!-- Cost Calculation Preview -->
                        <div x-show="canShowPreview()" x-transition
                             class="mb-6 p-4 bg-indigo-50 border border-indigo-200 rounded-lg">
                            <h4 class="font-medium text-indigo-800 mb-2">Cost Calculation</h4>
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
                            <textarea name="notes" id="notes" rows="3" x-model="notes"
                                      class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"></textarea>
                        </div>

                        <!-- Usage Info -->
                        @php
                            $usageCount = $profile->recipeIngredients()->count();
                        @endphp
                        @if($usageCount > 0)
                            <div class="mb-6 p-3 bg-yellow-50 border border-yellow-200 rounded-lg">
                                <p class="text-sm text-yellow-800">
                                    <svg class="w-4 h-4 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                    </svg>
                                    This profile is used in <strong>{{ $usageCount }}</strong> recipe ingredient(s).
                                    Changes will affect their cost calculations.
                                </p>
                            </div>
                        @endif

                        <!-- Submit -->
                        <div class="flex justify-end space-x-3">
                            <a href="{{ route('kitchen.profiles.index') }}"
                               class="px-4 py-2 bg-gray-200 text-gray-700 rounded-md hover:bg-gray-300">
                                Cancel
                            </a>
                            <button type="submit" :disabled="!canSubmit()"
                                    class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 disabled:opacity-50 disabled:cursor-not-allowed">
                                Update Profile
                            </button>
                        </div>
                    </form>

                    <!-- Delete Form (outside main form to avoid nesting) -->
                    <div class="mt-6 pt-6 border-t border-gray-200">
                        <form action="{{ route('kitchen.profiles.destroy', $profile) }}" method="POST"
                              onsubmit="return confirm('Delete this profile? This cannot be undone.');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="px-4 py-2 bg-red-100 text-red-700 rounded-md hover:bg-red-200">
                                Delete Profile
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
    <script>
        function profileEditForm() {
            return {
                posProductId: '{{ $profile->pos_product_id ?? '' }}',
                productName: '{{ $profile->product?->NAME ?? '' }}',
                productCode: '{{ $profile->product?->CODE ?? '' }}',
                productCost: {{ $profile->product?->PRICEBUY ?? 0 }},
                manualCost: {{ $profile->manual_cost ?? 0 }},
                profileName: '{{ old('name', $profile->name) }}',
                purchaseQuantity: {{ old('purchase_quantity', $profile->purchase_quantity) }},
                purchaseUnit: '{{ old('purchase_unit', $profile->purchase_unit) }}',
                density: {{ old('density', $profile->density ?? 0) }},
                applyDeliveryMarkup: {{ old('apply_delivery_markup', $profile->apply_delivery_markup) ? 'true' : 'false' }},
                deliveryMarkupPercent: {{ old('delivery_markup_percent', $profile->delivery_markup_percent ?? 15) }},
                notes: `{{ old('notes', $profile->notes ?? '') }}`,

                searchQuery: '',
                results: [],
                showResults: false,
                showProductSearch: false,

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

                get hasProduct() {
                    return this.posProductId && this.posProductId.length > 0;
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
                    this.posProductId = product.id;
                    this.productName = product.name;
                    this.productCode = product.code;
                    this.productCost = parseFloat(product.cost) || 0;

                    // Auto-enable delivery markup for imported suppliers (Udea, Dynamis)
                    if (product.is_imported_supplier) {
                        this.applyDeliveryMarkup = true;
                    }

                    this.searchQuery = '';
                    this.showResults = false;
                    this.showProductSearch = false;
                    this.results = [];
                },

                unlinkProduct() {
                    if (confirm('Unlink this product? You will need to enter a manual cost.')) {
                        this.posProductId = '';
                        this.productName = '';
                        this.productCode = '';
                        this.productCost = 0;
                        // Keep manualCost as the previous product cost for convenience
                        if (!this.manualCost || this.manualCost == 0) {
                            this.manualCost = {{ $profile->product?->PRICEBUY ?? 0 }};
                        }
                    }
                },

                getBaseCost() {
                    if (this.hasProduct) {
                        return this.productCost;
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

                    if (this.hasProduct) {
                        return hasName && hasQuantity;
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
