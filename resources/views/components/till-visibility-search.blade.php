@props([
    'categoryType' => 'fruit_veg',
    'showFilters' => false,
    'placeholder' => 'Search products to manage till visibility...'
])

<div x-data="tillVisibilitySearch()" 
     x-init="categoryType = '{{ $categoryType }}'"
     class="relative">
    
    <!-- Search Input with Results Dropdown -->
    <div class="relative">
        <input type="text" 
               x-model="searchTerm"
               @input.debounce.300ms="search()"
               @focus="showResults = true"
               placeholder="{{ $placeholder }}"
               class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500">
        
        <!-- Search Icon -->
        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
            <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
            </svg>
        </div>
        
        <!-- Loading Spinner -->
        <div x-show="searching" class="absolute inset-y-0 right-0 pr-3 flex items-center">
            <svg class="animate-spin h-5 w-5 text-gray-400" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
        </div>
    </div>
    
    <!-- Search Results Dropdown -->
    <div x-show="showResults && searchResults.length > 0" 
         @click.outside="showResults = false"
         x-transition
         class="absolute z-50 mt-1 w-full bg-white rounded-lg shadow-lg border border-gray-200 max-h-96 overflow-y-auto">
        
        <template x-for="product in searchResults" :key="product.CODE">
            <div class="border-b border-gray-100 last:border-b-0 hover:bg-gray-50 transition-colors">
                <div class="p-3 flex items-center justify-between">
                    <div class="flex-1">
                        <div class="flex items-center gap-2">
                            <h4 class="text-sm font-medium text-gray-900" x-text="product.NAME"></h4>
                            <span class="text-xs text-gray-500" x-text="'(' + product.CODE + ')'"></span>
                        </div>
                        <div class="text-xs text-gray-500 mt-1">
                            <span x-text="product.category?.NAME || 'Unknown Category'"></span>
                            <span x-show="product.current_price" class="ml-2">
                                €<span x-text="parseFloat(product.current_price).toFixed(2)"></span>
                            </span>
                        </div>
                    </div>
                    
                    <!-- Till Visibility Toggle -->
                    <button @click.stop="toggleVisibility(product)"
                            class="ml-4 relative inline-flex h-6 w-11 items-center rounded-full transition-colors focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2"
                            :class="product.is_visible_on_till ? 'bg-green-600' : 'bg-gray-200'">
                        <span class="sr-only">Toggle till visibility</span>
                        <span :class="product.is_visible_on_till ? 'translate-x-6' : 'translate-x-1'"
                              class="inline-block h-4 w-4 transform rounded-full bg-white transition-transform" />
                    </button>
                </div>
                
                <!-- Quick Actions -->
                <div class="px-3 pb-2 flex items-center gap-2 text-xs">
                    <a :href="'/fruit-veg/product/' + product.CODE" 
                       class="text-blue-600 hover:text-blue-800">
                        Edit Product
                    </a>
                    <span class="text-gray-300">|</span>
                    <button @click="updatePrice(product)" 
                            x-show="product.is_visible_on_till"
                            class="text-green-600 hover:text-green-800">
                        Update Price
                    </button>
                </div>
            </div>
        </template>
        
        <!-- No Results -->
        <div x-show="searchTerm.length >= 2 && searchResults.length === 0 && !searching" 
             class="p-4 text-center text-sm text-gray-500">
            No products found matching "<span x-text="searchTerm"></span>"
        </div>
    </div>
    
    <!-- Optional Filters -->
    @if($showFilters)
    <div class="mt-2 flex gap-2">
        <select x-model="categoryFilter" 
                @change="search()"
                class="text-sm px-3 py-1 border border-gray-300 rounded focus:ring-green-500 focus:border-green-500">
            <option value="all">All Categories</option>
            <option value="fruit">Fruits</option>
            <option value="vegetables">Vegetables</option>
        </select>
        
        <select x-model="visibilityFilter" 
                @change="search()"
                class="text-sm px-3 py-1 border border-gray-300 rounded focus:ring-green-500 focus:border-green-500">
            <option value="all">All Products</option>
            <option value="visible">Visible on Till</option>
            <option value="hidden">Hidden from Till</option>
        </select>
    </div>
    @endif
    
    <!-- Notifications -->
    <div x-show="notification.show" 
         x-transition
         @click="notification.show = false"
         class="fixed bottom-4 right-4 max-w-sm bg-white rounded-lg shadow-lg border p-4 cursor-pointer z-50"
         :class="notification.type === 'success' ? 'border-green-400' : 'border-red-400'">
        <div class="flex items-center">
            <svg x-show="notification.type === 'success'" class="h-5 w-5 text-green-400 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <svg x-show="notification.type === 'error'" class="h-5 w-5 text-red-400 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <span x-text="notification.message"></span>
        </div>
    </div>
</div>

@push('scripts')
<script>
function tillVisibilitySearch() {
    return {
        categoryType: 'fruit_veg',
        searchTerm: '',
        searchResults: [],
        showResults: false,
        searching: false,
        categoryFilter: 'all',
        visibilityFilter: 'all',
        notification: {
            show: false,
            message: '',
            type: 'success'
        },
        
        async search() {
            if (this.searchTerm.length < 2) {
                this.searchResults = [];
                return;
            }
            
            this.searching = true;
            
            try {
                const params = new URLSearchParams({
                    search: this.searchTerm,
                    category: this.categoryFilter,
                    availability: this.visibilityFilter === 'visible' ? 'available' : 
                                  (this.visibilityFilter === 'hidden' ? 'unavailable' : 'all'),
                    limit: 20
                });
                
                const response = await fetch(`{{ route('fruit-veg.search') }}?${params}`);
                const data = await response.json();
                
                this.searchResults = data.products || [];
                this.showResults = true;
            } catch (error) {
                console.error('Search error:', error);
                this.showNotification('Search failed', 'error');
            } finally {
                this.searching = false;
            }
        },
        
        async toggleVisibility(product) {
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
                    // Update local state
                    product.is_visible_on_till = !product.is_visible_on_till;
                    product.is_available = product.is_visible_on_till; // Compatibility
                    
                    // Emit custom events for dashboard integration
                    if (product.is_visible_on_till) {
                        this.$dispatch('product-added-to-till', { product: product });
                    } else {
                        this.$dispatch('product-removed-from-till', { product: product });
                    }
                    
                    this.showNotification(
                        product.is_visible_on_till 
                            ? `${product.NAME} is now visible on till` 
                            : `${product.NAME} is now hidden from till`,
                        'success'
                    );
                } else {
                    this.showNotification('Failed to update visibility', 'error');
                }
            } catch (error) {
                console.error('Toggle error:', error);
                this.showNotification('An error occurred', 'error');
            }
        },
        
        updatePrice(product) {
            // This could open a modal or navigate to the price update page
            window.location.href = `/fruit-veg/product/${product.CODE}#pricing`;
        },
        
        showNotification(message, type = 'success') {
            this.notification = {
                show: true,
                message: message,
                type: type
            };
            
            setTimeout(() => {
                this.notification.show = false;
            }, 3000);
        }
    }
}
</script>
@endpush