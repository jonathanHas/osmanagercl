<x-admin-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('Manage Fruit & Vegetables') }}
            </h2>
            <div class="flex items-center gap-4">
                <!-- Create Product Dropdown -->
                <div x-data="{ open: false }" class="relative">
                    <button @click="open = !open"
                            @click.outside="open = false"
                            class="inline-flex items-center px-4 py-2 bg-green-600 text-white text-sm font-medium rounded-md hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 transition-colors">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                        </svg>
                        Create Product
                        <svg class="w-4 h-4 ml-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </button>
                    <div x-show="open"
                         x-cloak
                         x-transition:enter="transition ease-out duration-100"
                         x-transition:enter-start="opacity-0 scale-95"
                         x-transition:enter-end="opacity-100 scale-100"
                         x-transition:leave="transition ease-in duration-75"
                         x-transition:leave-start="opacity-100 scale-100"
                         x-transition:leave-end="opacity-0 scale-95"
                         class="absolute right-0 mt-2 w-48 rounded-md shadow-lg bg-white ring-1 ring-black ring-opacity-5 z-50">
                        <div class="py-1">
                            <a href="{{ route('products.create') }}?category=SUB1"
                               class="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-green-50 hover:text-green-700">
                                <span class="w-3 h-3 rounded-full bg-green-500 mr-3"></span>
                                Fruit
                            </a>
                            <a href="{{ route('products.create') }}?category=SUB2"
                               class="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-orange-50 hover:text-orange-700">
                                <span class="w-3 h-3 rounded-full bg-orange-500 mr-3"></span>
                                Vegetables
                            </a>
                            <a href="{{ route('products.create') }}?category=SUB3"
                               class="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-purple-50 hover:text-purple-700">
                                <span class="w-3 h-3 rounded-full bg-purple-500 mr-3"></span>
                                Veg Barcoded
                            </a>
                        </div>
                    </div>
                </div>
                <a href="{{ route('fruit-veg.index') }}" class="text-blue-600 hover:text-blue-800">
                    ← Back to Dashboard
                </a>
            </div>
        </div>
    </x-slot>

    {{-- Loading placeholder shown until Alpine initializes --}}
    <div x-data="{ ready: false }" x-init="$nextTick(() => ready = true)">
        <div x-show="!ready" class="py-6">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="bg-white rounded-lg shadow-sm p-8">
                    <div class="flex items-center justify-center gap-3 text-gray-400">
                        <svg class="animate-spin h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                        </svg>
                        <span class="text-sm">Loading products...</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="py-6" x-data="managementSystem()" x-cloak @update-country="handleCountryUpdate($event)" @update-unit="handleUnitUpdate($event)" @update-class="handleClassUpdate($event)">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

            <!-- Quick Search Widget -->
            <div class="bg-gradient-to-r from-indigo-50 to-blue-50 rounded-lg shadow-sm mb-4 p-4" x-data="quickSearchWidget()">
                <div class="flex items-center gap-4">
                    <div class="flex-1">
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                                </svg>
                            </div>
                            <input type="text" 
                                   x-model="quickSearchTerm" 
                                   @input.debounce.300ms="performQuickSearch()"
                                   @focus="showResults = true"
                                   placeholder="Quick search to add products (searches all products)..."
                                   class="block w-full pl-10 pr-3 py-2 border border-gray-300 rounded-md leading-5 bg-white placeholder-gray-500 focus:outline-none focus:placeholder-gray-400 focus:ring-1 focus:ring-indigo-500 focus:border-indigo-500">
                        </div>
                    </div>
                    <div class="text-sm text-gray-500">
                        <span x-show="!quickSearching && quickResults.length === 0 && quickSearchTerm.length === 0">
                            Type to search across all products
                        </span>
                        <span x-show="quickSearching" class="text-indigo-600">
                            Searching...
                        </span>
                        <span x-show="!quickSearching && quickResults.length > 0" class="text-green-600">
                            Found <span x-text="quickResults.length"></span> products
                        </span>
                    </div>
                </div>
                
                <!-- Quick Search Results -->
                <div x-show="showResults && quickResults && quickResults.length > 0" 
                     x-cloak 
                     class="mt-4 bg-white rounded-md shadow-lg border border-gray-200 max-h-80 overflow-y-auto">
                    <div class="p-2">
                        <template x-for="product in (quickResults || [])" :key="product.CODE">
                            <div class="flex items-center justify-between p-3 hover:bg-gray-50 rounded-md">
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center gap-3">
                                        <div class="w-8 h-8 rounded-lg overflow-hidden bg-gray-100 flex items-center justify-center flex-shrink-0">
                                            <img :src="'/fruit-veg/product-image/' + product.CODE" 
                                                 :alt="product.NAME"
                                                 class="w-full h-full object-cover"
                                                 loading="lazy"
                                                 @@error="$el.style.display='none'; $el.nextElementSibling.style.display='flex'"
                                                 @@load="if($el.naturalWidth === 1 && $el.naturalHeight === 1) { $el.style.display='none'; $el.nextElementSibling.style.display='flex'; }">
                                            <div class="hidden w-full h-full items-center justify-center text-gray-400 text-xs">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                                                </svg>
                                            </div>
                                        </div>
                                        <div class="flex-1 min-w-0">
                                            <p class="text-sm font-medium text-gray-900 truncate" x-text="product.NAME"></p>
                                            <p class="text-xs text-gray-500" x-text="product.CODE"></p>
                                        </div>
                                        <div class="text-right">
                                            <p class="text-sm font-medium text-gray-900" x-text="'€' + parseFloat(product.current_price || 0).toFixed(2)"></p>
                                        </div>
                                    </div>
                                </div>
                                <div class="ml-4 flex-shrink-0">
                                    <button @click="toggleQuickAvailability(product)" 
                                            :class="product.is_visible_on_till ? 
                                                    'bg-red-100 text-red-800 hover:bg-red-200' : 
                                                    'bg-green-100 text-green-800 hover:bg-green-200'"
                                            class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium transition-colors">
                                        <span x-text="product.is_visible_on_till ? 'Hide' : 'Show'"></span>
                                    </button>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>
                
                <!-- Click outside to close -->
                <div x-show="showResults" @click="showResults = false" class="fixed inset-0 z-10" style="z-index: -1;"></div>
            </div>
            
            <!-- Search and Filters -->
            <div class="bg-white rounded-lg shadow mb-6 p-6">
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <!-- Search Box -->
                    <div class="md:col-span-2">
                        <label for="search" class="block text-sm font-medium text-gray-700 mb-2">Search Products</label>
                        <input type="text" 
                               id="search"
                               x-model="searchTerm" 
                               @input.debounce.500ms="performSearch()"
                               placeholder="Search by name, code, or display name..."
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-indigo-500 focus:border-indigo-500">
                    </div>
                    
                    <!-- Category Filter -->
                    <div>
                        <label for="category" class="block text-sm font-medium text-gray-700 mb-2">Category</label>
                        <select id="category" 
                                x-model="categoryFilter" 
                                @change="performSearch()"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-indigo-500 focus:border-indigo-500">
                            <option value="all">All Categories</option>
                            <option value="fruit">Fruits</option>
                            <option value="vegetables">Vegetables</option>
                            <option value="veg_barcoded">Veg Barcoded</option>
                        </select>
                    </div>
                    
                    <!-- Availability Filter -->
                    <div>
                        <label for="availability" class="block text-sm font-medium text-gray-700 mb-2">Availability</label>
                        <select id="availability" 
                                x-model="availabilityFilter" 
                                @change="performSearch()"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-indigo-500 focus:border-indigo-500">
                            <option value="all">All Products</option>
                            <option value="available">Available Only</option>
                            <option value="unavailable">Unavailable Only</option>
                        </select>
                    </div>
                </div>
                
                <!-- Results Info -->
                <div class="mt-4 flex items-center justify-between">
                    <div class="text-sm text-gray-600">
                        <span x-show="!searching && products.length > 0">
                            Showing <span x-text="products.length"></span> products
                        </span>
                        <span x-show="searching" class="text-blue-600">Searching...</span>
                        <span x-show="!searching && products.length === 0 && (searchTerm || categoryFilter !== 'all' || availabilityFilter !== 'all')" class="text-gray-500">
                            No products found matching your criteria
                        </span>
                    </div>
                    
                    <!-- Clear Filters -->
                    <button @click="clearFilters()" 
                            x-show="searchTerm || categoryFilter !== 'all' || availabilityFilter !== 'all'"
                            class="text-sm text-indigo-600 hover:text-indigo-800">
                        Clear Filters
                    </button>
                </div>
            </div>

            <!-- Price Mismatch Warning Banner -->
            <div x-show="products.filter(p => p.price_mismatch).length > 0"
                 class="bg-amber-50 border border-amber-200 rounded-lg shadow mb-6 p-4">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <svg class="w-5 h-5 text-amber-500 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                        </svg>
                        <div>
                            <p class="text-sm font-medium text-amber-800">
                                <span x-text="products.filter(p => p.price_mismatch).length"></span> product(s) have prices that differ from the price history
                            </p>
                            <p class="text-xs text-amber-600 mt-0.5">Prices may have been changed directly on the till. Review and sync to update the history.</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-2">
                        <button @click="syncAllMismatches()"
                                class="px-3 py-1.5 bg-amber-600 text-white text-sm rounded-md hover:bg-amber-700 transition">
                            Sync All to POS Price
                        </button>
                        <a href="{{ route('fruit-veg.price-sync') }}"
                           class="px-3 py-1.5 bg-white text-amber-700 text-sm rounded-md border border-amber-300 hover:bg-amber-50 transition">
                            Review Details
                        </a>
                    </div>
                </div>
            </div>

            <!-- Bulk Actions Bar -->
            <div class="bg-white rounded-lg shadow mb-6 p-4" x-show="selectedProducts.length > 0">
                <div class="flex items-center justify-between">
                    <div class="text-sm text-gray-600">
                        <span x-text="selectedProducts.length"></span> products selected
                    </div>
                    <div class="flex gap-2">
                        <button @click="bulkUpdateAvailability(true)" 
                                class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition">
                            Mark Available
                        </button>
                        <button @click="bulkUpdateAvailability(false)" 
                                class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition">
                            Mark Unavailable
                        </button>
                        <button @click="selectedProducts = []" 
                                class="px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700 transition">
                            Clear Selection
                        </button>
                    </div>
                </div>
            </div>

            <!-- Products Table -->
            <div class="bg-white rounded-lg shadow overflow-hidden">
                <!-- Desktop Table (md: and larger) -->
                <div class="hidden md:block overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="w-8 px-3 py-3">
                                    <input type="checkbox" 
                                           x-model="selectAll" 
                                           @change="toggleAllProducts()"
                                           class="rounded border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                                </th>
                                <th class="w-12 px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Image
                                </th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Product
                                </th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Price & Origin
                                </th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Display & Unit
                                </th>
                                <th class="w-20 px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Available
                                </th>
                                <th class="w-16 px-2 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Print
                                </th>
                                <th class="w-24 px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Labels
                                </th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <template x-for="product in (products || [])" :key="product.CODE">
                                <tr :class="{ 'bg-amber-50 border-l-4 border-l-amber-400': product.in_print_queue, 'bg-gray-50': !product.in_print_queue && selectedProducts.includes(product.CODE), 'opacity-40': recentlyToggledOff.includes(product.CODE) }">
                                    <!-- Checkbox -->
                                    <td class="px-3 py-4">
                                        <input type="checkbox" 
                                               :value="product.CODE"
                                               x-model="selectedProducts"
                                               class="rounded border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                                    </td>
                                    
                                    <!-- Thumbnail -->
                                    <td class="px-3 py-4">
                                        <div class="w-10 h-10 rounded-lg overflow-hidden bg-gray-100 flex items-center justify-center">
                                            <img :src="'/fruit-veg/product-image/' + product.CODE" 
                                                 :alt="product.NAME"
                                                 class="w-full h-full object-cover"
                                                 loading="lazy"
                                                 @@error="$el.style.display='none'; $el.nextElementSibling.style.display='flex'"
                                                 @@load="if($el.naturalWidth === 1 && $el.naturalHeight === 1) { $el.style.display='none'; $el.nextElementSibling.style.display='flex'; }">
                                            <div class="hidden w-full h-full items-center justify-center text-gray-400 text-xs">
                                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                                                </svg>
                                            </div>
                                        </div>
                                    </td>
                                    
                                    <!-- Product Info -->
                                    <td class="px-4 py-4">
                                        <div class="text-sm font-medium text-blue-600 hover:text-blue-800 cursor-pointer" 
                                             @click="window.location.href = '/fruit-veg/product/' + product.CODE"
                                             x-text="product.NAME"></div>
                                        <div class="text-xs text-gray-500" x-text="product.CODE"></div>
                                        <div class="text-xs text-gray-400" x-text="product.category?.NAME"></div>
                                    </td>
                                    
                                    <!-- Price & Origin -->
                                    <td class="px-4 py-4">
                                        <!-- Price Editing -->
                                        <div x-data="{ 
                                                editing: false, 
                                                originalPrice: parseFloat(product.current_price).toFixed(2),
                                                newPrice: parseFloat(product.current_price).toFixed(2),
                                                async savePrice() {
                                                    try {
                                                        const response = await fetch('{{ route('fruit-veg.prices.update') }}', {
                                                            method: 'POST',
                                                            headers: {
                                                                'Content-Type': 'application/json',
                                                                'X-CSRF-TOKEN': '{{ csrf_token() }}'
                                                            },
                                                            body: JSON.stringify({
                                                                product_code: product.CODE,
                                                                new_price: parseFloat(this.newPrice)
                                                            })
                                                        });
                                                        
                                                        if (response.ok) {
                                                            // Update local state
                                                            product.current_price = this.newPrice;
                                                            product.price_mismatch = false;
                                                            product.history_price = null;
                                                            product.in_print_queue = true;
                                                            this.originalPrice = this.newPrice;
                                                            this.editing = false;

                                                            // Show success notification
                                                            const notification = document.createElement('div');
                                                            notification.className = 'fixed top-4 right-4 px-6 py-3 rounded-lg shadow-lg text-white z-50 bg-green-600';
                                                            notification.textContent = 'Price updated successfully!';
                                                            document.body.appendChild(notification);
                                                            setTimeout(() => notification.remove(), 3000);
                                                        } else {
                                                            const errorData = await response.json();
                                                            console.error('Price update failed:', errorData);
                                                            
                                                            // Show error notification
                                                            const notification = document.createElement('div');
                                                            notification.className = 'fixed top-4 right-4 px-6 py-3 rounded-lg shadow-lg text-white z-50 bg-red-600';
                                                            notification.textContent = errorData.error || 'Failed to update price';
                                                            document.body.appendChild(notification);
                                                            setTimeout(() => notification.remove(), 3000);
                                                        }
                                                    } catch (error) {
                                                        console.error('Error updating price:', error);
                                                        
                                                        // Show error notification
                                                        const notification = document.createElement('div');
                                                        notification.className = 'fixed top-4 right-4 px-6 py-3 rounded-lg shadow-lg text-white z-50 bg-red-600';
                                                        notification.textContent = 'An error occurred while updating price';
                                                        document.body.appendChild(notification);
                                                        setTimeout(() => notification.remove(), 3000);
                                                    }
                                                }
                                             }">
                                            <div x-show="!editing" 
                                                 @click="editing = true; $nextTick(() => $refs.priceInput.focus())" 
                                                 class="cursor-pointer hover:bg-yellow-50 px-2 py-1 rounded">
                                                <span class="text-sm font-medium text-gray-900">
                                                    €<span x-text="parseFloat(product.current_price).toFixed(2)"></span>
                                                </span>
                                                <div class="text-xs text-blue-600 mt-1">Click to edit</div>
                                                <template x-if="product.price_mismatch">
                                                    <div class="flex items-center gap-1 mt-1">
                                                        <svg class="w-3 h-3 text-amber-500" fill="currentColor" viewBox="0 0 20 20">
                                                            <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                                                        </svg>
                                                        <span class="text-xs text-amber-600">History: €<span x-text="parseFloat(product.history_price).toFixed(2)"></span></span>
                                                        <button @click.stop="$dispatch('sync-price', { code: product.CODE })"
                                                                class="text-xs text-amber-700 underline hover:text-amber-900">Sync</button>
                                                    </div>
                                                </template>
                                            </div>
                                            <div x-show="editing" x-cloak class="flex items-center gap-1">
                                                <span class="text-sm">€</span>
                                                <input type="number"
                                                       x-model="newPrice"
                                                       step="0.01"
                                                       @keyup.enter="savePrice()"
                                                       class="w-16 text-sm border-gray-300 rounded focus:ring-indigo-500 focus:border-indigo-500"
                                                       x-ref="priceInput">
                                                <button @click="savePrice()"
                                                        class="px-2 py-1 bg-green-600 text-white text-xs rounded hover:bg-green-700 transition">
                                                    ✓
                                                </button>
                                                <button @click="newPrice = originalPrice; editing = false"
                                                        class="px-2 py-1 bg-gray-600 text-white text-xs rounded hover:bg-gray-700 transition">
                                                    ✕
                                                </button>
                                            </div>
                                        </div>
                                        
                                        <!-- Country Editing -->
                                        <div class="mt-1" 
                                             x-data="{ 
                                                editing: false, 
                                                originalCountryId: product.veg_details?.country_id || null,
                                                selectedCountryId: product.veg_details?.country_id || null,
                                                countries: [],
                                                async saveCountry() {
                                                    // Convert both to integers for proper comparison
                                                    const selectedId = parseInt(this.selectedCountryId);
                                                    const originalId = parseInt(this.originalCountryId);
                                                    
                                                    if (selectedId !== originalId && !isNaN(selectedId) && selectedId > 0) {
                                                        // Use $dispatch to communicate with parent component
                                                        $dispatch('update-country', { productCode: product.CODE, countryId: selectedId });
                                                        
                                                        // Update the display after dispatching the event
                                                        const selectedCountry = this.countries.find(c => c.id === selectedId);
                                                        if (selectedCountry) {
                                                            if (!product.veg_details) {
                                                                product.veg_details = {};
                                                            }
                                                            product.veg_details.country_id = selectedCountry.id;
                                                            product.veg_details.country = selectedCountry;
                                                            this.originalCountryId = selectedId;
                                                        }
                                                    } else {
                                                        // Reset to original value if no valid change
                                                        this.selectedCountryId = this.originalCountryId;
                                                    }
                                                    this.editing = false;
                                                }
                                             }"
                                             x-init="$nextTick(() => fetch('/fruit-veg/countries').then(response => response.json()).then(data => countries = data))">
                                            <div x-show="!editing" 
                                                 @click="editing = true; selectedCountryId = product.veg_details?.country_id || null;" 
                                                 class="cursor-pointer text-xs text-gray-600 hover:text-gray-800 hover:bg-gray-100 px-1 py-1 rounded">
                                                <span x-show="product.veg_details?.country?.name" x-text="product.veg_details?.country?.name"></span>
                                                <span x-show="!product.veg_details?.country?.name" class="text-gray-400 italic">Set origin</span>
                                            </div>
                                            <div x-show="editing" x-cloak class="mt-1">
                                                <select x-model="selectedCountryId" 
                                                        @blur="saveCountry()"
                                                        @keydown.enter="saveCountry()"
                                                        @keydown.escape="editing = false; selectedCountryId = originalCountryId"
                                                        class="w-full text-xs border-gray-300 rounded focus:ring-indigo-500 focus:border-indigo-500">
                                                    <option value="">Select...</option>
                                                    <template x-for="country in countries" :key="country.id">
                                                        <option :value="country.id" x-text="country.name"></option>
                                                    </template>
                                                </select>
                                            </div>
                                        </div>
                                        
                                        <!-- Class Editing -->
                                        <div class="mt-1" 
                                             x-data="{ 
                                                editing: false, 
                                                originalClassId: product.veg_details?.class_id || null,
                                                selectedClassId: product.veg_details?.class_id || null,
                                                classes: [],
                                                async saveClass() {
                                                    // Convert both to integers for proper comparison
                                                    const selectedId = parseInt(this.selectedClassId);
                                                    const originalId = parseInt(this.originalClassId);
                                                    
                                                    if (selectedId !== originalId && !isNaN(selectedId) && selectedId > 0) {
                                                        // Use $dispatch to communicate with parent component
                                                        $dispatch('update-class', { productCode: product.CODE, classId: selectedId });
                                                        
                                                        // Update the display after dispatching the event
                                                        const selectedClass = this.classes.find(c => c.id === selectedId);
                                                        if (selectedClass) {
                                                            if (!product.veg_details) {
                                                                product.veg_details = {};
                                                            }
                                                            product.veg_details.class_id = selectedClass.id;
                                                            product.veg_details.class_name = selectedClass.name;
                                                            this.originalClassId = selectedId;
                                                        }
                                                    } else {
                                                        // Reset to original value if no valid change
                                                        this.selectedClassId = this.originalClassId;
                                                    }
                                                    this.editing = false;
                                                }
                                             }"
                                             x-init="$nextTick(() => fetch('/fruit-veg/classes').then(response => response.json()).then(data => classes = data))">
                                            <div x-show="!editing" 
                                                 @click="editing = true; selectedClassId = product.veg_details?.class_id || null;" 
                                                 class="cursor-pointer text-xs text-gray-600 hover:text-gray-800 hover:bg-gray-100 px-1 py-1 rounded">
                                                <span x-show="product.veg_details?.class_name" x-text="'Class: ' + product.veg_details?.class_name"></span>
                                                <span x-show="!product.veg_details?.class_name" class="text-gray-400 italic">Set class</span>
                                            </div>
                                            <div x-show="editing" x-cloak class="mt-1">
                                                <select x-model="selectedClassId" 
                                                        @blur="saveClass()"
                                                        @keydown.enter="saveClass()"
                                                        @keydown.escape="editing = false; selectedClassId = originalClassId"
                                                        class="w-full text-xs border-gray-300 rounded focus:ring-indigo-500 focus:border-indigo-500">
                                                    <option value="">Select...</option>
                                                    <template x-for="class_ in classes" :key="class_.id">
                                                        <option :value="class_.id" x-text="'Class ' + class_.name"></option>
                                                    </template>
                                                </select>
                                            </div>
                                        </div>
                                    </td>
                                    
                                    <!-- Display & Unit -->
                                    <td class="px-4 py-4">
                                        <!-- Display Name Editing -->
                                        <div x-data="{ 
                                                editing: false, 
                                                originalDisplay: product.DISPLAY || '',
                                                newDisplay: product.DISPLAY || ''
                                             }">
                                            <div x-show="!editing" 
                                                 @click="editing = true" 
                                                 class="cursor-pointer text-sm text-gray-900 hover:bg-gray-100 px-1 py-1 rounded">
                                                <span x-show="product.DISPLAY" x-text="product.DISPLAY"></span>
                                                <span x-show="!product.DISPLAY" class="text-gray-400 italic text-xs">Set display name</span>
                                            </div>
                                            <div x-show="editing" x-cloak class="mt-1">
                                                <input type="text" 
                                                       x-model="newDisplay" 
                                                       @keyup.enter="$root.updateDisplay(product.CODE, newDisplay); editing = false"
                                                       @blur="$root.updateDisplay(product.CODE, newDisplay); editing = false"
                                                       class="w-full text-sm border-gray-300 rounded focus:ring-indigo-500 focus:border-indigo-500"
                                                       placeholder="Enter display name...">
                                            </div>
                                        </div>
                                        <!-- Unit Editing -->
                                        <div class="mt-1" 
                                             x-data="{ 
                                                editing: false, 
                                                originalUnitId: product.veg_details?.unit_id || null,
                                                selectedUnitId: product.veg_details?.unit_id || null,
                                                units: [],
                                                async saveUnit() {
                                                    // Convert both to integers for proper comparison
                                                    const selectedId = parseInt(this.selectedUnitId);
                                                    const originalId = parseInt(this.originalUnitId);
                                                    
                                                    if (selectedId !== originalId && !isNaN(selectedId) && selectedId > 0) {
                                                        // Use $dispatch to communicate with parent component
                                                        $dispatch('update-unit', { productCode: product.CODE, unitId: selectedId });
                                                        
                                                        // Update the display after dispatching the event
                                                        const selectedUnit = this.units.find(u => u.id === selectedId);
                                                        if (selectedUnit) {
                                                            if (!product.veg_details) {
                                                                product.veg_details = {};
                                                            }
                                                            product.veg_details.unit_id = selectedUnit.id;
                                                            product.veg_details.unit_name = selectedUnit.abbreviation;
                                                            this.originalUnitId = selectedId;
                                                        }
                                                    } else {
                                                        // Reset to original value if no valid change
                                                        this.selectedUnitId = this.originalUnitId;
                                                    }
                                                    this.editing = false;
                                                }
                                             }"
                                             x-init="$nextTick(() => fetch('/fruit-veg/units').then(response => response.json()).then(data => units = data))">
                                            <div x-show="!editing" 
                                                 @click="editing = true; selectedUnitId = product.veg_details?.unit_id || null;" 
                                                 class="cursor-pointer text-xs text-gray-500 hover:text-gray-700 hover:bg-gray-100 px-1 py-1 rounded">
                                                <span x-text="product.veg_details?.unit_name || 'kg'"></span>
                                            </div>
                                            <div x-show="editing" x-cloak class="mt-1">
                                                <select x-model="selectedUnitId" 
                                                        @blur="saveUnit()"
                                                        @keydown.enter="saveUnit()"
                                                        @keydown.escape="editing = false; selectedUnitId = originalUnitId"
                                                        class="w-full text-xs border-gray-300 rounded focus:ring-indigo-500 focus:border-indigo-500">
                                                    <option value="">Select...</option>
                                                    <template x-for="unit in units" :key="unit.id">
                                                        <option :value="unit.id" x-text="unit.name + ' (' + unit.abbreviation + ')'"></option>
                                                    </template>
                                                </select>
                                            </div>
                                        </div>
                                    </td>
                                    
                                    <!-- Availability Toggle -->
                                    <td class="px-4 py-4 text-center">
                                        <button @click="toggleProductAvailability(product.CODE, !product.is_available)"
                                                class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                                                :class="product.is_available ? 'bg-green-600' : 'bg-gray-200'">
                                            <span class="sr-only">Toggle availability</span>
                                            <span :class="product.is_available ? 'translate-x-6' : 'translate-x-1'"
                                                  class="inline-block h-4 w-4 transform rounded-full bg-white transition-transform" />
                                        </button>
                                    </td>
                                    
                                    <!-- Print Zebra Label -->
                                    <td class="px-2 py-4 text-center">
                                        <button x-show="product.zebra_label"
                                                @click="openPrintModal(product)"
                                                class="relative p-1.5 text-gray-500 hover:text-green-600 hover:bg-green-50 rounded transition"
                                                title="Print label">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
                                            </svg>
                                            <span x-show="product.zebra_label?.mismatches" class="absolute -top-0.5 -right-0.5 w-2.5 h-2.5 bg-amber-500 rounded-full"></span>
                                        </button>
                                    </td>

                                    <!-- Labels Actions -->
                                    <td class="px-4 py-4 text-center">
                                        <div class="flex flex-col items-center gap-1">
                                            <!-- Print Queue Indicator -->
                                            <div x-show="product.in_print_queue" class="inline-flex items-center gap-1 px-2 py-0.5 text-xs font-medium text-amber-800 bg-amber-100 rounded-full">
                                                <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20">
                                                    <path d="M5 4a2 2 0 012-2h6a2 2 0 012 2v14l-5-2.5L5 18V4z"/>
                                                </svg>
                                                Queued
                                            </div>
                                            
                                            <!-- Add/Remove Button -->
                                            <button x-show="!product.in_print_queue"
                                                    @click="addToLabels(product.CODE)"
                                                    class="px-2 py-1 text-xs bg-blue-600 text-white rounded hover:bg-blue-700 transition flex items-center gap-1">
                                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                                                </svg>
                                                Add to Labels
                                            </button>
                                            <button x-show="product.in_print_queue"
                                                    @click="removeFromLabels(product.CODE)"
                                                    class="px-2 py-1 text-xs bg-amber-600 text-white rounded hover:bg-amber-700 transition flex items-center gap-1">
                                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                                </svg>
                                                On List
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                    
                    <!-- Empty State for Desktop -->
                    <div x-show="!searching && products.length === 0" class="text-center py-12">
                        <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2M4 13h2m13-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 11-2 0 1 1 0 012 0z" />
                        </svg>
                        <h3 class="mt-2 text-sm font-medium text-gray-900">No products found</h3>
                        <p class="mt-1 text-sm text-gray-500">Try adjusting your search or filter criteria.</p>
                    </div>
                </div>

                <!-- Mobile Card Layout (below md:) -->
                <div class="md:hidden bg-gray-100 p-4">
                    <!-- Mobile Products -->
                    <div x-show="products.length > 0" class="space-y-4">
                        <template x-for="product in (products || [])" :key="product.CODE">
                            <div class="rounded-lg shadow-sm border p-4 space-y-4" :class="{ 'bg-amber-50 border-amber-400': product.in_print_queue, 'bg-white border-gray-200': !product.in_print_queue, 'bg-gray-50': !product.in_print_queue && selectedProducts.includes(product.CODE), 'opacity-40': recentlyToggledOff.includes(product.CODE) }">
                                
                                <!-- Header: Checkbox, Image, Product Name -->
                                <div class="flex items-start space-x-3">
                                    <input type="checkbox" 
                                           :value="product.CODE"
                                           x-model="selectedProducts"
                                           class="mt-1 rounded border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                                    
                                    <div class="w-12 h-12 rounded-lg overflow-hidden bg-gray-100 flex items-center justify-center flex-shrink-0">
                                        <img :src="'/fruit-veg/product-image/' + product.CODE" 
                                             :alt="product.NAME"
                                             class="w-full h-full object-cover"
                                             loading="lazy"
                                             @@error="$el.style.display='none'; $el.nextElementSibling.style.display='flex'"
                                             @@load="if($el.naturalWidth === 1 && $el.naturalHeight === 1) { $el.style.display='none'; $el.nextElementSibling.style.display='flex'; }">
                                        <div class="hidden w-full h-full items-center justify-center text-gray-400 text-xs">
                                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                                            </svg>
                                        </div>
                                    </div>
                                    
                                    <div class="flex-1 min-w-0">
                                        <div class="text-base font-medium text-blue-600 hover:text-blue-800 cursor-pointer" 
                                             @click="window.location.href = '/fruit-veg/product/' + product.CODE"
                                             x-text="product.NAME"></div>
                                        <div class="text-sm text-gray-500" x-text="product.CODE"></div>
                                        <div class="text-sm text-gray-400" x-text="product.category?.NAME"></div>
                                    </div>
                                </div>

                                <!-- Price Section -->
                                <div class="bg-gray-50 rounded-lg p-3 space-y-3">
                                    <!-- Price Editing -->
                                    <div x-data="{ 
                                            editing: false, 
                                            originalPrice: parseFloat(product.current_price).toFixed(2),
                                            newPrice: parseFloat(product.current_price).toFixed(2),
                                            async savePrice() {
                                                try {
                                                    const response = await fetch('{{ route('fruit-veg.prices.update') }}', {
                                                        method: 'POST',
                                                        headers: {
                                                            'Content-Type': 'application/json',
                                                            'X-CSRF-TOKEN': '{{ csrf_token() }}'
                                                        },
                                                        body: JSON.stringify({
                                                            product_code: product.CODE,
                                                            new_price: parseFloat(this.newPrice)
                                                        })
                                                    });
                                                    
                                                    if (response.ok) {
                                                        product.current_price = this.newPrice;
                                                        product.price_mismatch = false;
                                                        product.history_price = null;
                                                        product.in_print_queue = true;
                                                        this.originalPrice = this.newPrice;
                                                        this.editing = false;

                                                        const notification = document.createElement('div');
                                                        notification.className = 'fixed top-4 right-4 px-6 py-3 rounded-lg shadow-lg text-white z-50 bg-green-600';
                                                        notification.textContent = 'Price updated successfully!';
                                                        document.body.appendChild(notification);
                                                        setTimeout(() => notification.remove(), 3000);
                                                    } else {
                                                        const errorData = await response.json();
                                                        console.error('Price update failed:', errorData);
                                                        
                                                        const notification = document.createElement('div');
                                                        notification.className = 'fixed top-4 right-4 px-6 py-3 rounded-lg shadow-lg text-white z-50 bg-red-600';
                                                        notification.textContent = errorData.error || 'Failed to update price';
                                                        document.body.appendChild(notification);
                                                        setTimeout(() => notification.remove(), 3000);
                                                    }
                                                } catch (error) {
                                                    console.error('Error updating price:', error);
                                                    
                                                    const notification = document.createElement('div');
                                                    notification.className = 'fixed top-4 right-4 px-6 py-3 rounded-lg shadow-lg text-white z-50 bg-red-600';
                                                    notification.textContent = 'An error occurred while updating price';
                                                    document.body.appendChild(notification);
                                                    setTimeout(() => notification.remove(), 3000);
                                                }
                                            }
                                         }">
                                        <div class="flex items-center justify-between">
                                            <span class="text-sm font-medium text-gray-700">Price:</span>
                                            <div x-show="!editing" 
                                                 @click="editing = true; $nextTick(() => $refs.priceInput.focus())" 
                                                 class="cursor-pointer hover:bg-white px-2 py-1 rounded">
                                                <span class="text-base font-semibold text-gray-900">
                                                    €<span x-text="parseFloat(product.current_price).toFixed(2)"></span>
                                                </span>
                                                <div class="text-xs text-blue-600">Tap to edit</div>
                                                <template x-if="product.price_mismatch">
                                                    <div class="flex items-center gap-1 mt-1">
                                                        <svg class="w-3 h-3 text-amber-500" fill="currentColor" viewBox="0 0 20 20">
                                                            <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                                                        </svg>
                                                        <span class="text-xs text-amber-600">History: €<span x-text="parseFloat(product.history_price).toFixed(2)"></span></span>
                                                        <button @click.stop="$dispatch('sync-price', { code: product.CODE })"
                                                                class="text-xs text-amber-700 underline hover:text-amber-900">Sync</button>
                                                    </div>
                                                </template>
                                            </div>
                                            <div x-show="editing" x-cloak class="flex items-center gap-2">
                                                <span class="text-base font-semibold">€</span>
                                                <input type="number"
                                                       x-model="newPrice"
                                                       step="0.01"
                                                       @keyup.enter="savePrice()"
                                                       class="w-20 text-base border-gray-300 rounded focus:ring-indigo-500 focus:border-indigo-500"
                                                       x-ref="priceInput">
                                                <button @click="savePrice()"
                                                        class="px-3 py-1 bg-green-600 text-white text-sm rounded hover:bg-green-700 transition">
                                                    Save
                                                </button>
                                                <button @click="newPrice = originalPrice; editing = false"
                                                        class="px-3 py-1 bg-gray-600 text-white text-sm rounded hover:bg-gray-700 transition">
                                                    Cancel
                                                </button>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Country Editing -->
                                    <div x-data="{ 
                                            editing: false, 
                                            originalCountryId: product.veg_details?.country_id || null,
                                            selectedCountryId: product.veg_details?.country_id || null,
                                            countries: [],
                                            async saveCountry() {
                                                const selectedId = parseInt(this.selectedCountryId);
                                                const originalId = parseInt(this.originalCountryId);
                                                
                                                if (selectedId !== originalId && !isNaN(selectedId) && selectedId > 0) {
                                                    $dispatch('update-country', { productCode: product.CODE, countryId: selectedId });
                                                    
                                                    const selectedCountry = this.countries.find(c => c.id === selectedId);
                                                    if (selectedCountry) {
                                                        if (!product.veg_details) {
                                                            product.veg_details = {};
                                                        }
                                                        product.veg_details.country_id = selectedCountry.id;
                                                        product.veg_details.country = selectedCountry;
                                                        this.originalCountryId = selectedId;
                                                    }
                                                } else {
                                                    // Reset to original value if no valid change
                                                    this.selectedCountryId = this.originalCountryId;
                                                }
                                                this.editing = false;
                                            }
                                         }"
                                         x-init="$nextTick(() => fetch('/fruit-veg/countries').then(response => response.json()).then(data => countries = data))">
                                        <div class="flex items-center justify-between">
                                            <span class="text-sm font-medium text-gray-700">Origin:</span>
                                            <div x-show="!editing" 
                                                 @click="editing = true; selectedCountryId = product.veg_details?.country_id || null;" 
                                                 class="cursor-pointer text-sm text-gray-900 hover:text-gray-700 hover:bg-white px-2 py-1 rounded min-h-[32px] flex items-center">
                                                <span x-show="product.veg_details?.country?.name" x-text="product.veg_details?.country?.name"></span>
                                                <span x-show="!product.veg_details?.country?.name" class="text-gray-400 italic">Tap to set origin</span>
                                            </div>
                                            <div x-show="editing" x-cloak class="flex-1 max-w-32">
                                                <select x-model="selectedCountryId" 
                                                        @blur="saveCountry()"
                                                        @keydown.enter="saveCountry()"
                                                        @keydown.escape="editing = false; selectedCountryId = originalCountryId"
                                                        class="w-full text-sm border-gray-300 rounded focus:ring-indigo-500 focus:border-indigo-500">
                                                    <option value="">Select...</option>
                                                    <template x-for="country in countries" :key="country.id">
                                                        <option :value="country.id" x-text="country.name"></option>
                                                    </template>
                                                </select>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Class Editing -->
                                    <div x-data="{ 
                                            editing: false, 
                                            originalClassId: product.veg_details?.class_id || null,
                                            selectedClassId: product.veg_details?.class_id || null,
                                            classes: [],
                                            async saveClass() {
                                                const selectedId = parseInt(this.selectedClassId);
                                                const originalId = parseInt(this.originalClassId);
                                                
                                                if (selectedId !== originalId && !isNaN(selectedId) && selectedId > 0) {
                                                    $dispatch('update-class', { productCode: product.CODE, classId: selectedId });
                                                    
                                                    const selectedClass = this.classes.find(c => c.id === selectedId);
                                                    if (selectedClass) {
                                                        if (!product.veg_details) {
                                                            product.veg_details = {};
                                                        }
                                                        product.veg_details.class_id = selectedClass.id;
                                                        product.veg_details.class_name = selectedClass.name;
                                                        this.originalClassId = selectedId;
                                                    }
                                                } else {
                                                    // Reset to original value if no valid change
                                                    this.selectedClassId = this.originalClassId;
                                                }
                                                this.editing = false;
                                            }
                                         }"
                                         x-init="$nextTick(() => fetch('/fruit-veg/classes').then(response => response.json()).then(data => classes = data))">
                                        <div class="flex items-center justify-between">
                                            <span class="text-sm font-medium text-gray-700">Class:</span>
                                            <div x-show="!editing" 
                                                 @click="editing = true; selectedClassId = product.veg_details?.class_id || null;" 
                                                 class="cursor-pointer text-sm text-gray-900 hover:text-gray-700 hover:bg-white px-2 py-1 rounded min-h-[32px] flex items-center">
                                                <span x-show="product.veg_details?.class_name" x-text="'Class ' + product.veg_details?.class_name"></span>
                                                <span x-show="!product.veg_details?.class_name" class="text-gray-400 italic">Tap to set class</span>
                                            </div>
                                            <div x-show="editing" x-cloak class="flex-1 max-w-32">
                                                <select x-model="selectedClassId" 
                                                        @blur="saveClass()"
                                                        @keydown.enter="saveClass()"
                                                        @keydown.escape="editing = false; selectedClassId = originalClassId"
                                                        class="w-full text-sm border-gray-300 rounded focus:ring-indigo-500 focus:border-indigo-500">
                                                    <option value="">Select...</option>
                                                    <template x-for="class_ in classes" :key="class_.id">
                                                        <option :value="class_.id" x-text="'Class ' + class_.name"></option>
                                                    </template>
                                                </select>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Display Name & Unit Section -->
                                <div class="bg-blue-50 rounded-lg p-3 space-y-3">
                                    <!-- Display Name Editing -->
                                    <div x-data="{ 
                                            editing: false, 
                                            originalDisplay: product.DISPLAY || '',
                                            newDisplay: product.DISPLAY || ''
                                         }">
                                        <div class="flex items-center justify-between">
                                            <span class="text-sm font-medium text-gray-700">Display Name:</span>
                                            <div x-show="!editing" 
                                                 @click="editing = true; $nextTick(() => $refs.displayInput.focus())" 
                                                 class="cursor-pointer text-sm text-gray-900 hover:bg-white px-2 py-1 rounded min-h-[32px] flex items-center flex-1 max-w-48">
                                                <span x-show="product.DISPLAY" x-text="product.DISPLAY" class="truncate"></span>
                                                <span x-show="!product.DISPLAY" class="text-gray-400 italic">Tap to set display name</span>
                                            </div>
                                            <div x-show="editing" x-cloak class="flex-1 max-w-48">
                                                <input type="text" 
                                                       x-model="newDisplay" 
                                                       @keyup.enter="$root.updateDisplay(product.CODE, newDisplay); editing = false"
                                                       @blur="$root.updateDisplay(product.CODE, newDisplay); editing = false"
                                                       class="w-full text-sm border-gray-300 rounded focus:ring-indigo-500 focus:border-indigo-500"
                                                       placeholder="Enter display name..."
                                                       x-ref="displayInput">
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Unit Editing -->
                                    <div x-data="{ 
                                            editing: false, 
                                            originalUnitId: product.veg_details?.unit_id || null,
                                            selectedUnitId: product.veg_details?.unit_id || null,
                                            units: [],
                                            async saveUnit() {
                                                const selectedId = parseInt(this.selectedUnitId);
                                                const originalId = parseInt(this.originalUnitId);
                                                
                                                if (selectedId !== originalId && !isNaN(selectedId) && selectedId > 0) {
                                                    $dispatch('update-unit', { productCode: product.CODE, unitId: selectedId });
                                                    
                                                    const selectedUnit = this.units.find(u => u.id === selectedId);
                                                    if (selectedUnit) {
                                                        if (!product.veg_details) {
                                                            product.veg_details = {};
                                                        }
                                                        product.veg_details.unit_id = selectedUnit.id;
                                                        product.veg_details.unit_name = selectedUnit.abbreviation;
                                                        this.originalUnitId = selectedId;
                                                    }
                                                } else {
                                                    // Reset to original value if no valid change
                                                    this.selectedUnitId = this.originalUnitId;
                                                }
                                                this.editing = false;
                                            }
                                         }"
                                         x-init="$nextTick(() => fetch('/fruit-veg/units').then(response => response.json()).then(data => units = data))">
                                        <div class="flex items-center justify-between">
                                            <span class="text-sm font-medium text-gray-700">Unit:</span>
                                            <div x-show="!editing" 
                                                 @click="editing = true; selectedUnitId = product.veg_details?.unit_id || null;" 
                                                 class="cursor-pointer text-sm text-gray-900 hover:bg-white px-2 py-1 rounded min-h-[32px] flex items-center">
                                                <span x-text="product.veg_details?.unit_name || 'kg'"></span>
                                            </div>
                                            <div x-show="editing" x-cloak class="flex-1 max-w-32">
                                                <select x-model="selectedUnitId" 
                                                        @blur="saveUnit()"
                                                        @keydown.enter="saveUnit()"
                                                        @keydown.escape="editing = false; selectedUnitId = originalUnitId"
                                                        class="w-full text-sm border-gray-300 rounded focus:ring-indigo-500 focus:border-indigo-500">
                                                    <option value="">Select...</option>
                                                    <template x-for="unit in units" :key="unit.id">
                                                        <option :value="unit.id" x-text="unit.name + ' (' + unit.abbreviation + ')'"></option>
                                                    </template>
                                                </select>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Actions Section -->
                                <div class="flex items-center justify-between pt-2 border-t border-gray-200">
                                    <!-- Availability Toggle -->
                                    <div class="flex items-center space-x-3">
                                        <span class="text-sm font-medium text-gray-700">Available:</span>
                                        <button @click="toggleProductAvailability(product.CODE, !product.is_available)"
                                                class="relative inline-flex h-7 w-12 items-center rounded-full transition-colors focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                                                :class="product.is_available ? 'bg-green-600' : 'bg-gray-200'">
                                            <span class="sr-only">Toggle availability</span>
                                            <span :class="product.is_available ? 'translate-x-6' : 'translate-x-1'"
                                                  class="inline-block h-5 w-5 transform rounded-full bg-white transition-transform shadow-md" />
                                        </button>
                                    </div>

                                    <!-- Labels Actions -->
                                    <div class="flex flex-col items-end space-y-1">
                                        <!-- Print Zebra Label -->
                                        <button x-show="product.zebra_label"
                                                @click="openPrintModal(product)"
                                                class="px-4 py-2 text-sm bg-green-600 text-white rounded-lg hover:bg-green-700 transition flex items-center gap-1">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
                                            </svg>
                                            Print Label
                                        </button>

                                        <!-- Print Queue Indicator -->
                                        <div x-show="product.in_print_queue" class="inline-flex items-center gap-1 px-2 py-0.5 text-xs font-medium text-amber-800 bg-amber-100 rounded-full">
                                            <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20">
                                                <path d="M5 4a2 2 0 012-2h6a2 2 0 012 2v14l-5-2.5L5 18V4z"/>
                                            </svg>
                                            Queued for Print
                                        </div>

                                        <!-- Add/Remove Button -->
                                        <button x-show="!product.in_print_queue"
                                                @click="addToLabels(product.CODE)"
                                                class="px-4 py-2 text-sm bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition flex items-center gap-1">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                                            </svg>
                                            Add to Labels
                                        </button>
                                        <button x-show="product.in_print_queue"
                                                @click="removeFromLabels(product.CODE)"
                                                class="px-4 py-2 text-sm bg-amber-600 text-white rounded-lg hover:bg-amber-700 transition flex items-center gap-1">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                            </svg>
                                            On List
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </template>
                    </div>
                    
                    <!-- Empty State for Mobile -->
                    <div x-show="!searching && products.length === 0" class="text-center py-12">
                        <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2M4 13h2m13-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 11-2 0 1 1 0 012 0z" />
                        </svg>
                        <h3 class="mt-2 text-sm font-medium text-gray-900">No products found</h3>
                        <p class="mt-1 text-sm text-gray-500">Try adjusting your search or filter criteria.</p>
                    </div>
                </div>
                
            {{-- Print Label Modal --}}
            <div x-show="printModal.open" x-cloak
                 class="fixed inset-0 z-50 overflow-y-auto"
                 @keydown.escape.window="printModal.open = false">
                <div class="flex items-center justify-center min-h-screen px-4">
                    <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" @click="printModal.open = false"></div>
                    <div class="relative bg-white rounded-lg shadow-xl max-w-sm w-full p-6" @click.stop>
                        <h3 class="text-lg font-medium text-gray-900 mb-4">Print Label</h3>

                        <div class="space-y-3 text-sm">
                            <div>
                                <span class="text-gray-500">Product:</span>
                                <span class="font-medium text-gray-900 ml-1" x-text="printModal.productName"></span>
                            </div>
                            <div>
                                <span class="text-gray-500">Label:</span>
                                <span class="text-gray-900 ml-1" x-text="printModal.labelName"></span>
                            </div>
                            <div>
                                <span class="text-gray-500">Size:</span>
                                <span class="text-gray-900 ml-1" x-text="printModal.labelSize"></span>
                            </div>

                            {{-- Mismatches --}}
                            <template x-if="printModal.mismatches?.price">
                                <div class="p-3 bg-amber-50 border border-amber-200 rounded-lg">
                                    <div class="flex items-center justify-between">
                                        <div>
                                            <div class="text-xs font-medium text-amber-800">Price mismatch</div>
                                            <div class="text-xs text-amber-700 mt-0.5">
                                                Label: <span class="font-mono font-medium" x-text="'€' + printModal.mismatches.price.label_value"></span>
                                                &rarr; DB: <span class="font-mono font-medium" x-text="'€' + printModal.mismatches.price.db_value"></span>
                                            </div>
                                        </div>
                                        <button @click="fixMismatch('price')" :disabled="printModal.fixing"
                                                class="px-2 py-1 bg-amber-600 text-white rounded text-xs font-medium hover:bg-amber-500 transition disabled:opacity-50">
                                            Update
                                        </button>
                                    </div>
                                </div>
                            </template>
                            <template x-if="printModal.mismatches?.country">
                                <div class="p-3 bg-amber-50 border border-amber-200 rounded-lg">
                                    <div class="flex items-center justify-between">
                                        <div>
                                            <div class="text-xs font-medium text-amber-800">Country mismatch</div>
                                            <div class="text-xs text-amber-700 mt-0.5">
                                                Label: <span class="font-medium" x-text="printModal.mismatches.country.label_value"></span>
                                                &rarr; DB: <span class="font-medium" x-text="printModal.mismatches.country.db_value"></span>
                                            </div>
                                        </div>
                                        <button @click="fixMismatch('country')" :disabled="printModal.fixing"
                                                class="px-2 py-1 bg-amber-600 text-white rounded text-xs font-medium hover:bg-amber-500 transition disabled:opacity-50">
                                            Update
                                        </button>
                                    </div>
                                </div>
                            </template>

                            <div class="pt-2">
                                <label class="block text-sm font-medium text-gray-700 mb-1">Number of labels</label>
                                <input type="number" x-model.number="printModal.copies" min="1" max="99"
                                       class="w-24 rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm text-center">
                            </div>
                        </div>

                        <div class="mt-5 flex items-center gap-3">
                            <button @click="sendPrint()" :disabled="printModal.printing"
                                    class="px-4 py-2 bg-green-600 text-white rounded-md text-sm font-medium hover:bg-green-500 transition disabled:opacity-50">
                                <span x-text="printModal.printing ? 'Printing...' : 'Print'"></span>
                            </button>
                            <button @click="printModal.open = false" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-md text-sm font-medium hover:bg-gray-200 transition">
                                Cancel
                            </button>
                            <p x-show="printModal.message" :class="printModal.success ? 'text-green-600' : 'text-red-600'" class="text-xs" x-text="printModal.message"></p>
                        </div>
                    </div>
                </div>
            </div>

            </div>
        </div>
    </div>

    @push('scripts')
    <script>
        function managementSystem() {
            return {
                products: {!! json_encode($products ?? []) !!},
                selectedProducts: [],
                recentlyToggledOff: [],
                selectAll: false,
                searchTerm: '',
                categoryFilter: 'all',
                availabilityFilter: 'available',
                searching: false,
                printModal: {
                    open: false,
                    labelId: null,
                    labelName: '',
                    labelSize: '',
                    productName: '',
                    productCode: '',
                    copies: 1,
                    printing: false,
                    fixing: false,
                    message: '',
                    success: false,
                    mismatches: null,
                    fields: [],
                },
                
                init() {
                    // Restore saved filters from localStorage
                    this.restoreFilters();
                    
                    // Listen for product availability changes from quick search
                    window.addEventListener('productAvailabilityChanged', (event) => {
                        const { productCode, isAvailable, productData } = event.detail;
                        const existingProductIndex = this.products.findIndex(p => p.CODE === productCode);
                        
                        if (existingProductIndex !== -1) {
                            // Update existing product
                            this.products[existingProductIndex].is_available = isAvailable;
                            this.products[existingProductIndex].is_visible_on_till = isAvailable;
                            
                            // Grey out instead of removing if it no longer matches filters
                            if (!isAvailable && !this.recentlyToggledOff.includes(this.products[existingProductIndex].CODE)) {
                                this.recentlyToggledOff.push(this.products[existingProductIndex].CODE);
                            } else if (isAvailable) {
                                this.recentlyToggledOff = this.recentlyToggledOff.filter(c => c !== this.products[existingProductIndex].CODE);
                            }
                        } else if (isAvailable && productData) {
                            // Add new product to the table if it matches current filters
                            const matchesFilters = this.productMatchesFilters(productData);
                            
                            if (matchesFilters) {
                                // Prepare product data to match table format
                                productData.is_available = isAvailable;
                                productData.in_print_queue = false; // Default to not in print queue
                                
                                // Add to the beginning of the products array
                                this.products.unshift(productData);
                                
                                // Show notification
                                this.showNotification(`${productData.NAME} added to the table`, 'success');
                            }
                        }
                    });

                    // Listen for price sync requests from individual product mismatch buttons
                    window.addEventListener('sync-price', (event) => {
                        this.syncProductPrice(event.detail.code);
                    });
                },

                async syncProductPrice(productCode) {
                    try {
                        const response = await fetch('{{ route("fruit-veg.price-sync.sync") }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': '{{ csrf_token() }}'
                            },
                            body: JSON.stringify({
                                product_code: productCode,
                                direction: 'pos_to_history'
                            })
                        });

                        if (response.ok) {
                            const product = this.products.find(p => p.CODE === productCode);
                            if (product) {
                                product.price_mismatch = false;
                                product.history_price = null;
                            }
                            this.showNotification('Price history synced to POS price', 'success');
                        } else {
                            this.showNotification('Failed to sync price', 'error');
                        }
                    } catch (error) {
                        console.error('Error syncing price:', error);
                        this.showNotification('Error syncing price', 'error');
                    }
                },

                async syncAllMismatches() {
                    const mismatched = this.products.filter(p => p.price_mismatch);
                    if (mismatched.length === 0) return;

                    try {
                        const response = await fetch('{{ route("fruit-veg.price-sync.bulk-sync") }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': '{{ csrf_token() }}'
                            },
                            body: JSON.stringify({
                                product_codes: mismatched.map(p => p.CODE),
                                direction: 'pos_to_history'
                            })
                        });

                        if (response.ok) {
                            mismatched.forEach(p => {
                                p.price_mismatch = false;
                                p.history_price = null;
                            });
                            this.showNotification(`${mismatched.length} product(s) synced to POS prices`, 'success');
                        } else {
                            this.showNotification('Failed to sync prices', 'error');
                        }
                    } catch (error) {
                        console.error('Error syncing prices:', error);
                        this.showNotification('Error syncing prices', 'error');
                    }
                },

                restoreFilters() {
                    const savedFilters = localStorage.getItem('fruitVegManageFilters');
                    if (savedFilters) {
                        try {
                            const filters = JSON.parse(savedFilters);
                            this.searchTerm = filters.searchTerm || '';
                            this.categoryFilter = filters.categoryFilter || 'all';
                            this.availabilityFilter = filters.availabilityFilter || 'available';
                            
                            // Perform search with restored filters if any are set
                            if (this.searchTerm || this.categoryFilter !== 'all' || this.availabilityFilter !== 'all') {
                                this.performSearch();
                            }
                        } catch (e) {
                            // If parsing fails, clear the stored data
                            localStorage.removeItem('fruitVegManageFilters');
                        }
                    }
                },
                
                saveFilters() {
                    const filters = {
                        searchTerm: this.searchTerm,
                        categoryFilter: this.categoryFilter,
                        availabilityFilter: this.availabilityFilter
                    };
                    localStorage.setItem('fruitVegManageFilters', JSON.stringify(filters));
                },
                
                toggleAllProducts() {
                    if (this.selectAll) {
                        this.selectedProducts = this.products.map(p => p.CODE);
                    } else {
                        this.selectedProducts = [];
                    }
                },
                
                async performSearch() {
                    this.searching = true;

                    // Save current filters to localStorage
                    this.saveFilters();

                    try {
                        const params = new URLSearchParams({
                            search: this.searchTerm,
                            category: this.categoryFilter,
                            availability: this.availabilityFilter,
                        });

                        const response = await fetch('{{ route('fruit-veg.manage') }}?' + params, {
                            headers: {
                                'Accept': 'application/json',
                                'X-Requested-With': 'XMLHttpRequest'
                            }
                        });
                        const data = await response.json();

                        this.products = data.products || [];
                        this.selectedProducts = [];
                        this.recentlyToggledOff = [];
                        this.selectAll = false;
                    } catch (error) {
                        console.error('Search error:', error);
                        this.showNotification('Search failed', 'error');
                    } finally {
                        this.searching = false;
                    }
                },
                
                clearFilters() {
                    this.searchTerm = '';
                    this.categoryFilter = 'all';
                    this.availabilityFilter = 'all';
                    
                    // Clear saved filters from localStorage
                    localStorage.removeItem('fruitVegManageFilters');
                    
                    this.performSearch();
                },
                
                productMatchesFilters(product) {
                    // Check search term
                    if (this.searchTerm) {
                        const searchLower = this.searchTerm.toLowerCase();
                        const matchesSearch = (
                            product.NAME.toLowerCase().includes(searchLower) ||
                            product.CODE.toLowerCase().includes(searchLower) ||
                            (product.DISPLAY && product.DISPLAY.toLowerCase().includes(searchLower))
                        );
                        if (!matchesSearch) return false;
                    }
                    
                    // Check category filter
                    if (this.categoryFilter !== 'all') {
                        const categoryId = product.CATEGORY || (product.category && product.category.ID);
                        if (this.categoryFilter === 'fruit' && categoryId !== '001') return false;
                        if (this.categoryFilter === 'vegetables' && categoryId !== '002') return false;
                        if (this.categoryFilter === 'veg_barcoded' && categoryId !== '126') return false;
                    }
                    
                    // Check availability filter
                    if (this.availabilityFilter !== 'all') {
                        if (this.availabilityFilter === 'available' && !product.is_visible_on_till) return false;
                        if (this.availabilityFilter === 'unavailable' && product.is_visible_on_till) return false;
                    }
                    
                    return true;
                },
                
                async toggleProductAvailability(productCode, isAvailable) {
                    try {
                        const response = await fetch('{{ route('fruit-veg.availability.toggle') }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': '{{ csrf_token() }}'
                            },
                            body: JSON.stringify({
                                product_code: productCode,
                                is_available: isAvailable
                            })
                        });
                        
                        if (response.ok) {
                            // Update local state
                            const product = this.products.find(p => p.CODE === productCode);
                            if (product) {
                                product.is_available = isAvailable;
                                product.is_visible_on_till = isAvailable;
                                if (isAvailable) {
                                    product.in_print_queue = true;
                                }
                            }

                            // Track toggled-off products so they stay visible but greyed out
                            if (!isAvailable) {
                                if (!this.recentlyToggledOff.includes(productCode)) {
                                    this.recentlyToggledOff.push(productCode);
                                }
                            } else {
                                this.recentlyToggledOff = this.recentlyToggledOff.filter(c => c !== productCode);
                            }
                            
                            // Dispatch event to notify other components
                            window.dispatchEvent(new CustomEvent('productAvailabilityChanged', {
                                detail: {
                                    productCode: productCode,
                                    isAvailable: isAvailable
                                }
                            }));
                        } else {
                            this.showNotification('Failed to update till visibility', 'error');
                        }
                    } catch (error) {
                        console.error('Error:', error);
                        this.showNotification('An error occurred', 'error');
                    }
                },
                
                async bulkUpdateAvailability(isAvailable) {
                    if (this.selectedProducts.length === 0) return;
                    
                    if (!confirm('Mark ' + this.selectedProducts.length + ' products as ' + (isAvailable ? 'available' : 'unavailable') + '?')) {
                        return;
                    }
                    
                    try {
                        const response = await fetch('{{ route('fruit-veg.availability.bulk') }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': '{{ csrf_token() }}'
                            },
                            body: JSON.stringify({
                                product_codes: this.selectedProducts,
                                is_available: isAvailable
                            })
                        });
                        
                        if (response.ok) {
                            // Update local state and track toggled-off products
                            const updatedCodes = [...this.selectedProducts];
                            updatedCodes.forEach(code => {
                                const product = this.products.find(p => p.CODE === code);
                                if (product) {
                                    product.is_available = isAvailable;
                                    product.is_visible_on_till = isAvailable;
                                }
                                if (!isAvailable && !this.recentlyToggledOff.includes(code)) {
                                    this.recentlyToggledOff.push(code);
                                } else if (isAvailable) {
                                    this.recentlyToggledOff = this.recentlyToggledOff.filter(c => c !== code);
                                }
                            });

                            this.showNotification('Updated ' + updatedCodes.length + ' products successfully!', 'success');
                            this.selectedProducts = [];
                            this.selectAll = false;
                        } else {
                            this.showNotification('Failed to update till visibility', 'error');
                        }
                    } catch (error) {
                        console.error('Error:', error);
                        this.showNotification('An error occurred', 'error');
                    }
                },
                
                async updatePrice(productCode, newPrice) {
                    try {
                        const response = await fetch('{{ route('fruit-veg.prices.update') }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': '{{ csrf_token() }}'
                            },
                            body: JSON.stringify({
                                product_code: productCode,
                                new_price: parseFloat(newPrice)
                            })
                        });
                        
                        if (response.ok) {
                            // Update local state
                            const product = this.products.find(p => p.CODE === productCode);
                            if (product) {
                                product.current_price = newPrice;
                                product.price_mismatch = false;
                                product.history_price = null;
                                product.in_print_queue = true;
                            }
                            this.showNotification('Price updated successfully!', 'success');
                        } else {
                            this.showNotification('Failed to update price', 'error');
                        }
                    } catch (error) {
                        console.error('Error:', error);
                        this.showNotification('An error occurred', 'error');
                    }
                },
                
                async updateCountry(productCode, countryId) {
                    try {
                        const response = await fetch('{{ route('fruit-veg.country.update') }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': '{{ csrf_token() }}'
                            },
                            body: JSON.stringify({
                                product_code: productCode,
                                country_id: parseInt(countryId)
                            })
                        });
                        
                        if (response.ok) {
                            const product = this.products.find(p => p.CODE === productCode);
                            if (product) {
                                product.in_print_queue = true;
                            }
                            this.showNotification('Country updated successfully!', 'success');
                            return true;
                        } else {
                            this.showNotification('Failed to update country', 'error');
                            return false;
                        }
                    } catch (error) {
                        console.error('Error updating country:', error);
                        this.showNotification('An error occurred', 'error');
                        return false;
                    }
                },

                async updateUnit(productCode, unitId) {
                    try {
                        const response = await fetch('{{ route('fruit-veg.unit.update') }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': '{{ csrf_token() }}'
                            },
                            body: JSON.stringify({
                                product_code: productCode,
                                unit_id: parseInt(unitId)
                            })
                        });
                        
                        if (response.ok) {
                            const product = this.products.find(p => p.CODE === productCode);
                            if (product) {
                                product.in_print_queue = true;
                            }
                            this.showNotification('Unit updated successfully!', 'success');
                            return true;
                        } else {
                            this.showNotification('Failed to update unit', 'error');
                            return false;
                        }
                    } catch (error) {
                        console.error('Error updating unit:', error);
                        this.showNotification('An error occurred', 'error');
                        return false;
                    }
                },

                async updateClass(productCode, classId) {
                    try {
                        const response = await fetch('{{ route('fruit-veg.class.update') }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': '{{ csrf_token() }}'
                            },
                            body: JSON.stringify({
                                product_code: productCode,
                                class_id: parseInt(classId)
                            })
                        });
                        
                        if (response.ok) {
                            const product = this.products.find(p => p.CODE === productCode);
                            if (product) {
                                product.in_print_queue = true;
                            }
                            this.showNotification('Class updated successfully!', 'success');
                            return true;
                        } else {
                            this.showNotification('Failed to update class', 'error');
                            return false;
                        }
                    } catch (error) {
                        console.error('Error updating class:', error);
                        this.showNotification('An error occurred', 'error');
                        return false;
                    }
                },
                
                async updateDisplay(productCode, display) {
                    try {
                        const response = await fetch('{{ route('fruit-veg.display.update') }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': '{{ csrf_token() }}'
                            },
                            body: JSON.stringify({
                                product_code: productCode,
                                display: display
                            })
                        });
                        
                        if (response.ok) {
                            // Update local state
                            const product = this.products.find(p => p.CODE === productCode);
                            if (product) {
                                product.DISPLAY = display;
                                product.in_print_queue = true;
                            }
                            this.showNotification('Display name updated successfully!', 'success');
                        } else {
                            this.showNotification('Failed to update display name', 'error');
                        }
                    } catch (error) {
                        console.error('Error:', error);
                        this.showNotification('An error occurred', 'error');
                    }
                },
                
                openPrintModal(product) {
                    const label = product.zebra_label;
                    if (!label) return;
                    this.printModal = {
                        open: true,
                        labelId: label.id,
                        labelName: label.name,
                        labelSize: (label.width_mm || '?') + 'mm × ' + (label.height_mm || '?') + 'mm',
                        productName: product.NAME,
                        productCode: product.CODE,
                        copies: label.default_copies || 1,
                        printing: false,
                        fixing: false,
                        message: '',
                        success: false,
                        mismatches: label.mismatches ? JSON.parse(JSON.stringify(label.mismatches)) : null,
                        fields: label.fields ? [...label.fields] : [],
                    };
                },

                async sendPrint() {
                    if (this.printModal.printing) return;
                    this.printModal.printing = true;
                    this.printModal.message = '';
                    try {
                        const res = await fetch('/labels/zebra/manage/' + this.printModal.labelId + '/print', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                                'Accept': 'application/json',
                            },
                            body: JSON.stringify({ copies: this.printModal.copies }),
                        });
                        const data = await res.json();
                        this.printModal.message = data.success ? data.message : ('Failed: ' + (data.output || data.message));
                        this.printModal.success = data.success;
                        if (data.success) {
                            setTimeout(() => { this.printModal.open = false; }, 1500);
                        }
                    } catch (err) {
                        this.printModal.message = 'Failed: ' + err.message;
                        this.printModal.success = false;
                    }
                    this.printModal.printing = false;
                },

                async fixMismatch(type) {
                    const mismatch = this.printModal.mismatches?.[type];
                    if (!mismatch || this.printModal.fixing) return;
                    this.printModal.fixing = true;

                    // Build fields array with the fix applied
                    const fields = [...this.printModal.fields];
                    fields[mismatch.field_index] = mismatch.new_field;

                    try {
                        const res = await fetch('/labels/zebra/manage/' + this.printModal.labelId + '/fields', {
                            method: 'PATCH',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                                'Accept': 'application/json',
                            },
                            body: JSON.stringify({ fields }),
                        });
                        const data = await res.json();
                        if (data.success) {
                            // Update local state — remove the fixed mismatch
                            delete this.printModal.mismatches[type];
                            if (Object.keys(this.printModal.mismatches).length === 0) {
                                this.printModal.mismatches = null;
                            }
                            // Update fields to reflect saved state
                            this.printModal.fields = data.fields;

                            // Also update the product's zebra_label in the products array
                            const product = this.products.find(p => p.CODE === this.printModal.productCode);
                            if (product?.zebra_label) {
                                product.zebra_label.fields = data.fields;
                                if (this.printModal.mismatches) {
                                    product.zebra_label.mismatches = JSON.parse(JSON.stringify(this.printModal.mismatches));
                                } else {
                                    product.zebra_label.mismatches = null;
                                }
                            }
                        }
                    } catch (err) {
                        // silently fail
                    }
                    this.printModal.fixing = false;
                },

                async addToLabels(productCode) {
                    try {
                        const response = await fetch('{{ route('fruit-veg.labels.add') }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': '{{ csrf_token() }}'
                            },
                            body: JSON.stringify({
                                product_code: productCode
                            })
                        });
                        
                        const result = await response.json();
                        
                        if (result.success) {
                            // Update local state
                            const product = this.products.find(p => p.CODE === productCode);
                            if (product) {
                                product.in_print_queue = true;
                            }
                            this.showNotification('Product added to print queue!', 'success');
                        } else {
                            this.showNotification(result.message || 'Failed to add product to print queue', 'error');
                        }
                    } catch (error) {
                        console.error('Error:', error);
                        this.showNotification('An error occurred', 'error');
                    }
                },
                
                async removeFromLabels(productCode) {
                    try {
                        const response = await fetch('{{ route('fruit-veg.labels.remove') }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': '{{ csrf_token() }}'
                            },
                            body: JSON.stringify({
                                product_code: productCode
                            })
                        });
                        
                        const result = await response.json();
                        
                        if (result.success) {
                            // Update local state
                            const product = this.products.find(p => p.CODE === productCode);
                            if (product) {
                                product.in_print_queue = false;
                            }
                            this.showNotification('Product removed from print queue!', 'success');
                        } else {
                            this.showNotification(result.message || 'Failed to remove product from print queue', 'error');
                        }
                    } catch (error) {
                        console.error('Error:', error);
                        this.showNotification('An error occurred', 'error');
                    }
                },
                
                showNotification(message, type) {
                    const notification = document.createElement('div');
                    const bgColor = type === 'success' ? 'bg-green-600' : 
                                   type === 'error' ? 'bg-red-600' : 
                                   'bg-blue-600'; // info type
                    notification.className = `fixed top-4 right-4 px-6 py-3 rounded-lg shadow-lg text-white z-50 ${bgColor}`;
                    notification.textContent = message;
                    
                    document.body.appendChild(notification);
                    
                    setTimeout(() => {
                        notification.remove();
                    }, 3000);
                },

                async handleCountryUpdate(event) {
                    const { productCode, countryId } = event.detail;
                    await this.updateCountry(productCode, countryId);
                },

                async handleUnitUpdate(event) {
                    const { productCode, unitId } = event.detail;
                    await this.updateUnit(productCode, unitId);
                },

                async handleClassUpdate(event) {
                    const { productCode, classId } = event.detail;
                    await this.updateClass(productCode, classId);
                }
            }
        }

        // Quick Search Widget Component
        function quickSearchWidget() {
            return {
                quickSearchTerm: '',
                quickResults: [],
                quickSearching: false,
                showResults: false,
                
                init() {
                    // Listen for product availability changes from main table
                    window.addEventListener('productAvailabilityChanged', (event) => {
                        const { productCode, isAvailable } = event.detail;
                        const product = this.quickResults.find(p => p.CODE === productCode);
                        if (product) {
                            product.is_visible_on_till = isAvailable;
                        }
                    });
                },

                async performQuickSearch() {
                    if (this.quickSearchTerm.length < 2) {
                        this.quickResults = [];
                        return;
                    }

                    this.quickSearching = true;

                    try {
                        const params = new URLSearchParams({
                            search: this.quickSearchTerm,
                            limit: 10
                        });

                        const response = await fetch('{{ route('fruit-veg.quick-search') }}?' + params, {
                            headers: {
                                'Accept': 'application/json',
                                'X-Requested-With': 'XMLHttpRequest'
                            }
                        });

                        const data = await response.json();
                        this.quickResults = data.products || [];
                        this.showResults = true;
                    } catch (error) {
                        console.error('Quick search error:', error);
                        this.quickResults = [];
                    } finally {
                        this.quickSearching = false;
                    }
                },

                async toggleQuickAvailability(product) {
                    try {
                        const response = await fetch('{{ route('fruit-veg.availability.toggle') }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': '{{ csrf_token() }}'
                            },
                            body: JSON.stringify({
                                product_code: product.CODE,
                                is_available: !product.is_visible_on_till
                            })
                        });

                        if (response.ok) {
                            // Update the product state in quick search results
                            product.is_visible_on_till = !product.is_visible_on_till;
                            
                            // Dispatch custom event to update main table
                            window.dispatchEvent(new CustomEvent('productAvailabilityChanged', {
                                detail: {
                                    productCode: product.CODE,
                                    isAvailable: product.is_visible_on_till,
                                    productData: product  // Include full product data
                                }
                            }));

                            // Show notification
                            this.showNotification(
                                `${product.NAME} ${product.is_visible_on_till ? 'added to' : 'removed from'} till`, 
                                'success'
                            );
                        } else {
                            throw new Error('Failed to toggle availability');
                        }
                    } catch (error) {
                        console.error('Toggle error:', error);
                        this.showNotification('Failed to toggle availability', 'error');
                    }
                },

                showNotification(message, type) {
                    const notification = document.createElement('div');
                    const bgColor = type === 'success' ? 'bg-green-600' : 
                                   type === 'error' ? 'bg-red-600' : 
                                   'bg-blue-600'; // info type
                    notification.className = `fixed top-4 right-4 px-6 py-3 rounded-lg shadow-lg text-white z-50 ${bgColor}`;
                    notification.textContent = message;
                    
                    document.body.appendChild(notification);
                    
                    setTimeout(() => {
                        notification.remove();
                    }, 3000);
                }
            }
        }
    </script>
    @endpush
</x-admin-layout>