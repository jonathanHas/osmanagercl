<x-admin-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <div>
                <nav class="text-sm text-gray-500 mb-2">
                    <a href="{{ route('categories.index') }}" class="hover:text-gray-700">Categories</a>
                    <span class="mx-2">/</span>
                    <a href="{{ route('categories.show', $category) }}" class="hover:text-gray-700">{{ $category->NAME }}</a>
                    <span class="mx-2">/</span>
                    <span class="text-gray-900 dark:text-gray-100">Products</span>
                </nav>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    {{ __('Manage ') . $category->NAME . __(' Products') }}
                </h2>
            </div>
            <a href="{{ route('categories.show', $category) }}" class="text-blue-600 hover:text-blue-800">
                ← Back to Category
            </a>
        </div>
    </x-slot>

    <div class="py-6" x-data="categoryManagementSystem()">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            
            <!-- Search and Filters -->
            <div class="bg-white rounded-lg shadow mb-6 p-6">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <!-- Search Box -->
                    <div class="md:col-span-2">
                        <label for="search" class="block text-sm font-medium text-gray-700 mb-2">Search Products</label>
                        <input type="text" 
                               id="search"
                               x-model="searchTerm" 
                               @input.debounce.500ms="performSearch()"
                               placeholder="Search products..."
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500">
                    </div>
                    
                    <!-- Availability Filter -->
                    <div>
                        <label for="availability" class="block text-sm font-medium text-gray-700 mb-2">Till Visibility</label>
                        <select id="availability" 
                                x-model="availabilityFilter" 
                                @change="performSearch()"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500">
                            <option value="all">All Products</option>
                            <option value="available">On Till</option>
                            <option value="unavailable">Not on Till</option>
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
                        <span x-show="!searching && products.length === 0 && searchTerm" class="text-gray-500">
                            No products found matching your criteria
                        </span>
                    </div>
                    
                    <!-- Clear Filters -->
                    <button @click="clearFilters()" 
                            x-show="searchTerm || availabilityFilter !== 'all'"
                            class="text-sm text-blue-600 hover:text-blue-800">
                        Clear Filters
                    </button>
                </div>
            </div>

            <!-- Products Table -->
            <div class="bg-white rounded-lg shadow overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="w-12 px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Image
                                </th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Product
                                </th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Price
                                </th>
                                <th class="w-20 px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Till Status
                                </th>
                                <th class="w-24 px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Actions
                                </th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <template x-for="product in products" :key="product.CODE">
                                <tr class="hover:bg-gray-50">
                                    <!-- Thumbnail -->
                                    <td class="px-3 py-4">
                                        <div class="w-10 h-10 rounded-lg overflow-hidden bg-gray-100 flex items-center justify-center">
                                            <img :src="'/categories/product-image/' + product.CODE" 
                                                 :alt="product.NAME"
                                                 class="w-full h-full object-cover"
                                                 @@error="$el.style.display='none'; $el.nextElementSibling.style.display='flex'">
                                            <div class="hidden w-full h-full items-center justify-center text-gray-400 text-xs">
                                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                                </svg>
                                            </div>
                                        </div>
                                    </td>
                                    
                                    <!-- Product Info -->
                                    <td class="px-4 py-4">
                                        <a :href="'/products/' + product.ID + '?from=category&category={{ $category->ID }}'"
                                           class="text-sm font-medium text-blue-600 hover:text-blue-800 cursor-pointer"
                                           x-text="product.NAME"></a>
                                        <div class="text-xs text-gray-500" x-text="product.CODE"></div>
                                        <div class="text-xs text-gray-400" x-text="product.category?.NAME || '{{ $category->NAME }}'"></div>
                                        
                                        <!-- Display Name Editing -->
                                        <div x-data="{ 
                                                editing: false, 
                                                originalDisplay: product.DISPLAY || '',
                                                newDisplay: product.DISPLAY || ''
                                             }"
                                             class="mt-1">
                                            <div x-show="!editing" 
                                                 @click="editing = true; $nextTick(() => $refs.displayInput.focus())" 
                                                 class="cursor-pointer text-xs text-gray-600 hover:bg-gray-100 px-1 py-1 rounded inline-block">
                                                <span x-show="product.DISPLAY" x-html="'Display: ' + product.DISPLAY.replace(/&lt;br&gt;/g, ' ')"></span>
                                                <span x-show="!product.DISPLAY" class="text-gray-400 italic">Set display name</span>
                                            </div>
                                            <div x-show="editing" x-cloak class="mt-1">
                                                <input type="text" 
                                                       x-ref="displayInput"
                                                       x-model="newDisplay" 
                                                       @keyup.enter="$root.updateDisplay(product.ID, newDisplay).then(() => { product.DISPLAY = newDisplay; editing = false; })"
                                                       @keyup.escape="editing = false; newDisplay = originalDisplay"
                                                       @blur="$root.updateDisplay(product.ID, newDisplay).then(() => { product.DISPLAY = newDisplay; editing = false; })"
                                                       placeholder="Enter display name"
                                                       class="w-full text-xs border-gray-300 rounded focus:ring-blue-500 focus:border-blue-500">
                                            </div>
                                        </div>
                                    </td>
                                    
                                    <!-- Price -->
                                    <td class="px-4 py-4">
                                        <div x-data="{ 
                                                editing: false, 
                                                newPrice: parseFloat(product.PRICESELL || 0).toFixed(2),
                                                originalPrice: parseFloat(product.PRICESELL || 0).toFixed(2)
                                             }">
                                            <div x-show="!editing" 
                                                 @click="editing = true; $nextTick(() => $refs.priceInput.focus())" 
                                                 class="cursor-pointer hover:bg-yellow-50 px-2 py-1 rounded">
                                                <span class="text-sm font-medium text-gray-900">
                                                    €<span x-text="parseFloat(product.current_price || 0).toFixed(2)"></span>
                                                </span>
                                                <div class="text-xs text-blue-600 mt-1">Click to edit</div>
                                            </div>
                                            <div x-show="editing" x-cloak class="flex items-center gap-1">
                                                <span class="text-sm">€</span>
                                                <input type="number" 
                                                       x-ref="priceInput"
                                                       x-model="newPrice" 
                                                       step="0.01" 
                                                       min="0"
                                                       @keyup.enter="$root.updatePrice(product.ID, newPrice).then(() => { product.PRICESELL = parseFloat(newPrice); product.current_price = parseFloat(newPrice) * (1 + (product.TAXCAT?.RATE || 0.135)); editing = false; })"
                                                       @keyup.escape="editing = false; newPrice = originalPrice"
                                                       @blur="$root.updatePrice(product.ID, newPrice).then(() => { product.PRICESELL = parseFloat(newPrice); product.current_price = parseFloat(newPrice) * (1 + (product.TAXCAT?.RATE || 0.135)); editing = false; })"
                                                       class="w-20 text-sm border-gray-300 rounded focus:ring-blue-500 focus:border-blue-500">
                                            </div>
                                        </div>
                                    </td>
                                    
                                    <!-- Availability Toggle -->
                                    <td class="px-4 py-4 text-center">
                                        <button @click="toggleProductAvailability(product.CODE, !product.is_available)"
                                                class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                                                :class="product.is_available ? 'bg-green-600' : 'bg-gray-200'"
                                                role="switch"
                                                :aria-checked="product.is_available">
                                            <span class="sr-only">Toggle availability</span>
                                            <span :class="product.is_available ? 'translate-x-6' : 'translate-x-1'"
                                                  class="inline-block h-4 w-4 transform rounded-full bg-white shadow-lg ring-0 transition-transform" />
                                        </button>
                                    </td>
                                    
                                    <!-- Actions -->
                                    <td class="px-4 py-4 text-center">
                                        <a :href="'/categories/{{ $category->ID }}/sales?search=' + encodeURIComponent(product.NAME)" 
                                           class="text-blue-600 hover:text-blue-900 text-sm">
                                            View Sales
                                        </a>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                    
                    <!-- Empty State -->
                    <div x-show="!searching && products.length === 0" class="text-center py-12">
                        <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                        </svg>
                        <h3 class="mt-2 text-sm font-medium text-gray-900">No products found</h3>
                        <p class="mt-1 text-sm text-gray-500">Try adjusting your search or filter criteria.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
    <script>
        function categoryManagementSystem() {
            return {
                products: {!! json_encode($products->map(function($product) {
                    $product->is_available = $product->is_visible;
                    $product->current_price = $product->PRICESELL * (1 + $product->getVatRate());
                    return $product;
                }) ?? []) !!},
                searchTerm: '{{ $search ?? '' }}',
                availabilityFilter: '{{ $availability ?? 'all' }}',
                searching: false,
                
                async performSearch() {
                    this.searching = true;
                    
                    try {
                        const params = new URLSearchParams({
                            search: this.searchTerm,
                            availability: this.availabilityFilter
                        });
                        
                        const response = await fetch('{{ route('categories.products', $category) }}?' + params, {
                            headers: {
                                'Accept': 'application/json',
                                'X-Requested-With': 'XMLHttpRequest'
                            }
                        });
                        const data = await response.json();
                        
                        this.products = data.products || [];
                    } catch (error) {
                        console.error('Search error:', error);
                        this.showNotification('Search failed', 'error');
                    } finally {
                        this.searching = false;
                    }
                },
                
                clearFilters() {
                    this.searchTerm = '';
                    this.availabilityFilter = 'all';
                    this.performSearch();
                },
                
                async toggleProductAvailability(productCode, isAvailable) {
                    try {
                        // Find product by CODE to get ID
                        const product = this.products.find(p => p.CODE === productCode);
                        if (!product) return;
                        
                        const response = await fetch('{{ route('categories.visibility.toggle') }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': '{{ csrf_token() }}'
                            },
                            body: JSON.stringify({
                                product_id: product.ID,
                                visible: isAvailable
                            })
                        });
                        
                        if (response.ok) {
                            // Update local state
                            product.is_available = isAvailable;
                            product.is_visible = isAvailable;
                            this.showNotification('Till visibility updated', 'success');
                        } else {
                            this.showNotification('Failed to update till visibility', 'error');
                        }
                    } catch (error) {
                        console.error('Error:', error);
                        this.showNotification('An error occurred', 'error');
                    }
                },
                
                async updatePrice(productId, newPrice) {
                    try {
                        const response = await fetch('/products/' + productId + '/update-price', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': '{{ csrf_token() }}'
                            },
                            body: JSON.stringify({ price: newPrice })
                        });

                        if (response.ok) {
                            this.showNotification('Price updated successfully', 'success');
                        } else {
                            this.showNotification('Failed to update price', 'error');
                        }
                    } catch (error) {
                        console.error('Error:', error);
                        this.showNotification('An error occurred', 'error');
                    }
                },
                
                async updateDisplay(productId, displayName) {
                    try {
                        const response = await fetch('/products/' + productId + '/update-display', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': '{{ csrf_token() }}'
                            },
                            body: JSON.stringify({ display: displayName })
                        });

                        if (response.ok) {
                            this.showNotification('Display name updated', 'success');
                        } else {
                            this.showNotification('Failed to update display name', 'error');
                        }
                    } catch (error) {
                        console.error('Error:', error);
                        this.showNotification('An error occurred', 'error');
                    }
                },
                
                showNotification(message, type = 'info') {
                    // Simple notification - you can enhance this with a toast library
                    const alertType = type === 'error' ? 'alert' : 'log';
                    console[alertType](message);
                }
            };
        }
    </script>
    @endpush
</x-admin-layout>