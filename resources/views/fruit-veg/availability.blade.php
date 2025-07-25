<x-admin-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('Manage Fruit & Vegetable Availability') }}
            </h2>
            <a href="{{ route('fruit-veg.index') }}" class="text-blue-600 hover:text-blue-800">
                ← Back to Dashboard
            </a>
        </div>
    </x-slot>

    <div class="py-6" x-data="availabilityManager()">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            
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
                <div class="overflow-x-auto">
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
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <template x-for="product in products" :key="product.CODE">
                                <tr :class="{ 'bg-gray-50': selectedProducts.includes(product.CODE) }">
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
                                            <img :src="`{{ route('fruit-veg.product-image', '') }}/${product.CODE}`" 
                                                 :alt="product.NAME"
                                                 class="w-full h-full object-cover"
                                                 @error="$el.style.display='none'; $el.nextElementSibling.style.display='flex'">
                                            <div class="hidden w-full h-full items-center justify-center text-gray-400 text-xs">
                                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                                                </svg>
                                            </div>
                                        </div>
                                    </td>
                                    
                                    <!-- Product Info -->
                                    <td class="px-4 py-4">
                                        <div class="text-sm font-medium text-gray-900" x-text="product.NAME"></div>
                                        <div class="text-xs text-gray-500" x-text="product.CODE"></div>
                                        <div class="text-xs text-gray-400" x-text="product.category?.NAME"></div>
                                    </td>
                                    
                                    <!-- Price & Origin -->
                                    <td class="px-4 py-4">
                                        <div class="text-sm font-medium text-gray-900">
                                            €<span x-text="parseFloat(product.current_price).toFixed(2)"></span>
                                        </div>
                                        <!-- Country Editing -->
                                        <div class="mt-1" 
                                             x-data="{ 
                                                editing: false, 
                                                originalCountryId: product.veg_details?.country_code || null,
                                                selectedCountryId: product.veg_details?.country_code || null,
                                                countries: []
                                             }"
                                             x-init="
                                                fetch('{{ route('fruit-veg.countries') }}')
                                                    .then(response => response.json())
                                                    .then(data => countries = data)
                                             ">
                                            <div x-show="!editing" 
                                                 @click="editing = true" 
                                                 class="cursor-pointer text-xs text-gray-600 hover:text-gray-800 hover:bg-gray-100 px-1 py-1 rounded">
                                                <span x-show="product.veg_details?.country?.country" x-text="product.veg_details?.country?.country"></span>
                                                <span x-show="!product.veg_details?.country?.country" class="text-gray-400 italic">Set origin</span>
                                            </div>
                                            <div x-show="editing" x-cloak class="mt-1">
                                                <select x-model="selectedCountryId" 
                                                        @change="$root.updateCountry(product.CODE, selectedCountryId); editing = false"
                                                        @blur="editing = false"
                                                        class="w-full text-xs border-gray-300 rounded focus:ring-indigo-500 focus:border-indigo-500">
                                                    <option value="">Select...</option>
                                                    <template x-for="country in countries" :key="country.ID">
                                                        <option :value="country.ID" x-text="country.country"></option>
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
                                        <div class="text-xs text-gray-500 mt-1" x-text="product.veg_details?.unit_name || 'kg'"></div>
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
                                </tr>
                            </template>
                        </tbody>
                    </table>
                    
                    <!-- Empty State -->
                    <div x-show="!searching && products.length === 0" class="text-center py-12">
                        <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2M4 13h2m13-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 11-2 0 1 1 0 012 0z" />
                        </svg>
                        <h3 class="mt-2 text-sm font-medium text-gray-900">No products found</h3>
                        <p class="mt-1 text-sm text-gray-500">Try adjusting your search or filter criteria.</p>
                    </div>
                </div>
                
                <!-- Load More Button -->
                <div x-show="hasMore && !searching" class="border-t border-gray-200 px-6 py-4 text-center">
                    <button @click="loadMore()" 
                            :disabled="loading"
                            class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition disabled:opacity-50">
                        <span x-show="!loading">Load More Products</span>
                        <span x-show="loading">Loading...</span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
    <script>
        function availabilityManager() {
            return {
                products: @json($products),
                selectedProducts: [],
                selectAll: false,
                searchTerm: '',
                categoryFilter: 'all',
                availabilityFilter: 'all',
                searching: false,
                loading: false,
                hasMore: true,
                offset: {{ count($products) }},
                
                init() {
                    // Initial setup
                    this.hasMore = this.products.length >= 50;
                },
                
                toggleAllProducts() {
                    if (this.selectAll) {
                        this.selectedProducts = this.products.map(p => p.CODE);
                    } else {
                        this.selectedProducts = [];
                    }
                },
                
                async performSearch() {
                    if (this.searchTerm.length < 2 && this.searchTerm.length > 0) return;
                    
                    this.searching = true;
                    this.offset = 0;
                    
                    try {
                        const params = new URLSearchParams({
                            search: this.searchTerm,
                            category: this.categoryFilter,
                            availability: this.availabilityFilter,
                            offset: 0,
                            limit: 50
                        });
                        
                        const response = await fetch(`{{ route('fruit-veg.search') }}?${params}`);
                        const data = await response.json();
                        
                        this.products = data.products;
                        this.hasMore = data.hasMore;
                        this.offset = this.products.length;
                        this.selectedProducts = [];
                        this.selectAll = false;
                    } catch (error) {
                        console.error('Search error:', error);
                        this.showNotification('Search failed', 'error');
                    } finally {
                        this.searching = false;
                    }
                },
                
                async loadMore() {
                    this.loading = true;
                    
                    try {
                        const params = new URLSearchParams({
                            search: this.searchTerm,
                            category: this.categoryFilter,
                            availability: this.availabilityFilter,
                            offset: this.offset,
                            limit: 50
                        });
                        
                        const response = await fetch(`{{ route('fruit-veg.search') }}?${params}`);
                        const data = await response.json();
                        
                        this.products = [...this.products, ...data.products];
                        this.hasMore = data.hasMore;
                        this.offset = this.products.length;
                    } catch (error) {
                        console.error('Load more error:', error);
                        this.showNotification('Failed to load more products', 'error');
                    } finally {
                        this.loading = false;
                    }
                },
                
                clearFilters() {
                    this.searchTerm = '';
                    this.categoryFilter = 'all';
                    this.availabilityFilter = 'all';
                    this.performSearch();
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
                            }
                        } else {
                            this.showNotification('Failed to update availability', 'error');
                        }
                    } catch (error) {
                        console.error('Error:', error);
                        this.showNotification('An error occurred', 'error');
                    }
                },
                
                async bulkUpdateAvailability(isAvailable) {
                    if (this.selectedProducts.length === 0) return;
                    
                    if (!confirm(`Mark ${this.selectedProducts.length} products as ${isAvailable ? 'available' : 'unavailable'}?`)) {
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
                            // Update local state
                            this.selectedProducts.forEach(code => {
                                const product = this.products.find(p => p.CODE === code);
                                if (product) {
                                    product.is_available = isAvailable;
                                }
                            });
                            
                            this.selectedProducts = [];
                            this.selectAll = false;
                            this.showNotification(`Updated ${this.selectedProducts.length} products successfully!`, 'success');
                        } else {
                            this.showNotification('Failed to update availability', 'error');
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
                            this.showNotification('Country updated successfully!', 'success');
                            // Refresh the specific product data
                            setTimeout(() => window.location.reload(), 1000);
                        } else {
                            this.showNotification('Failed to update country', 'error');
                        }
                    } catch (error) {
                        console.error('Error:', error);
                        this.showNotification('An error occurred', 'error');
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
                
                showNotification(message, type) {
                    const notification = document.createElement('div');
                    notification.className = `fixed top-4 right-4 px-6 py-3 rounded-lg shadow-lg text-white z-50 ${
                        type === 'success' ? 'bg-green-600' : 'bg-red-600'
                    }`;
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