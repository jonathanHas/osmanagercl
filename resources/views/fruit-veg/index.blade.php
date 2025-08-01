<x-admin-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('Fruit & Vegetables Management') }}
            </h2>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <!-- Statistics Cards -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-8">
                <!-- Total Fruits -->
                <div class="bg-white rounded-lg shadow p-6">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-green-100 text-green-800">
                            <svg class="h-8 w-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>
                        <div class="ml-5">
                            <p class="text-gray-500 text-sm font-medium">Total Fruits</p>
                            <p class="text-2xl font-semibold text-gray-900">{{ $stats['total_fruits'] }}</p>
                            <p class="text-sm text-green-600">{{ $stats['visible_fruits'] }} visible on till</p>
                        </div>
                    </div>
                </div>

                <!-- Total Vegetables -->
                <div class="bg-white rounded-lg shadow p-6">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-yellow-100 text-yellow-800">
                            <svg class="h-8 w-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                            </svg>
                        </div>
                        <div class="ml-5">
                            <p class="text-gray-500 text-sm font-medium">Total Vegetables</p>
                            <p class="text-2xl font-semibold text-gray-900">{{ $stats['total_vegetables'] }}</p>
                            <p class="text-sm text-green-600">{{ $stats['visible_vegetables'] }} visible on till</p>
                        </div>
                    </div>
                </div>

                <!-- Labels Needed -->
                <div class="bg-white rounded-lg shadow p-6">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-red-100 text-red-800">
                            <svg class="h-8 w-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z" />
                            </svg>
                        </div>
                        <div class="ml-5">
                            <p class="text-gray-500 text-sm font-medium">Labels Needed</p>
                            <p class="text-2xl font-semibold text-gray-900">{{ $stats['needs_labels'] }}</p>
                            <p class="text-sm text-gray-600">{{ $stats['recent_price_changes'] }} price changes</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Quick Search for Till Visibility -->
            <div class="bg-white rounded-lg shadow mb-8">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-medium text-gray-900">Quick Till Visibility Management</h3>
                </div>
                <div class="p-6">
                    <x-till-visibility-search 
                        category-type="fruit_veg" 
                        :show-filters="true"
                        placeholder="Search fruits & vegetables to manage till visibility..." />
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="bg-white rounded-lg shadow mb-8">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-medium text-gray-900">Quick Actions</h3>
                </div>
                <div class="p-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                        <a href="{{ route('fruit-veg.manage') }}" 
                           class="flex items-center justify-center px-4 py-3 bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition">
                            <svg class="h-5 w-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                            </svg>
                            Manage All
                        </a>
                        
                        <a href="{{ route('fruit-veg.availability') }}" 
                           class="flex items-center justify-center px-4 py-3 bg-green-600 text-white rounded-lg hover:bg-green-700 transition">
                            <svg class="h-5 w-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            Till Visibility
                        </a>
                        
                        <a href="{{ route('fruit-veg.prices') }}" 
                           class="flex items-center justify-center px-4 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition">
                            <svg class="h-5 w-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            Update Prices
                        </a>
                        
                        <a href="{{ route('fruit-veg.labels') }}" 
                           class="flex items-center justify-center px-4 py-3 bg-orange-600 text-white rounded-lg hover:bg-orange-700 transition">
                            <svg class="h-5 w-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                            </svg>
                            Print Labels
                        </a>
                    </div>
                    
                    <!-- Second row of actions -->
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mt-4">
                        <a href="{{ route('fruit-veg.sales') }}" 
                           class="flex items-center justify-center px-4 py-3 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition">
                            <svg class="h-5 w-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                            </svg>
                            View Sales
                        </a>
                    </div>
                </div>
            </div>


            <!-- Recently Added Products -->
            <div class="bg-white rounded-lg shadow mb-8" 
                 x-data="recentlyAddedManager()" 
                 @product-added-to-till.window="handleProductAdded($event.detail.product)"
                 @product-removed-from-till.window="handleProductRemoved($event.detail.product)">
                <div class="px-6 py-4 border-b border-gray-200">
                    <div class="flex justify-between items-center">
                        <h3 class="text-lg font-medium text-gray-900">Recently Added to Till</h3>
                        <span class="text-sm text-gray-500">Last 7 days</span>
                    </div>
                </div>
                <div class="p-6">
                    <div x-show="recentProducts.length > 0" class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-4">
                        <template x-for="product in recentProducts" :key="product.CODE">
                            <div class="product-card-container">
                                <a :href="'/fruit-veg/product/' + product.CODE" 
                                   class="block bg-blue-50 rounded-lg p-3 text-center hover:bg-blue-100 hover:shadow-md transition cursor-pointer border border-blue-200"
                                   :class="{ 'animate-slideIn': product.isNew }">
                                    <div class="aspect-square bg-white rounded-lg mb-2 overflow-hidden">
                                        <img :src="'/fruit-veg/product-image/' + product.CODE" 
                                             :alt="product.NAME"
                                             class="w-full h-full object-cover"
                                             loading="lazy">
                                    </div>
                                    <h4 class="text-sm font-medium text-gray-900 truncate" :title="product.NAME">
                                        <span x-show="product.DISPLAY" x-html="product.DISPLAY"></span>
                                        <span x-show="!product.DISPLAY" x-text="product.NAME"></span>
                                    </h4>
                                    <p class="text-xs text-gray-500 truncate" x-text="product.category?.NAME || 'Unknown'"></p>
                                    <div class="mt-2">
                                        <span class="text-sm font-semibold text-green-600">
                                            €<span x-text="parseFloat(product.current_price || 0).toFixed(2)"></span>
                                        </span>
                                        <p class="text-xs text-blue-600 mt-1" x-text="getTimeAgo(product.added_at)"></p>
                                    </div>
                                </a>
                            </div>
                        </template>
                    </div>
                    
                    <!-- Empty State -->
                    <div x-show="recentProducts.length === 0" class="text-center py-8">
                        <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <h3 class="mt-2 text-sm font-medium text-gray-900">No recent additions</h3>
                        <p class="mt-1 text-sm text-gray-500">Products added to till in the last 7 days will appear here.</p>
                    </div>
                </div>
            </div>

            <!-- Recent Price Changes -->
            @if($recentPriceChanges->count() > 0)
            <div class="bg-white rounded-lg shadow">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-medium text-gray-900">Recent Price Changes</h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Product</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Old Price</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">New Price</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Change</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @foreach($recentPriceChanges as $change)
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                    {{ $change->product_name }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    €{{ number_format($change->old_price, 2) }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    €{{ number_format($change->new_price, 2) }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm">
                                    @php
                                        $diff = $change->new_price - $change->old_price;
                                        $percent = $change->old_price > 0 ? ($diff / $change->old_price) * 100 : 0;
                                    @endphp
                                    <span class="{{ $diff > 0 ? 'text-red-600' : 'text-green-600' }}">
                                        {{ $diff > 0 ? '+' : '' }}€{{ number_format($diff, 2) }}
                                        ({{ $diff > 0 ? '+' : '' }}{{ number_format($percent, 1) }}%)
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    {{ \Carbon\Carbon::parse($change->changed_at)->format('d/m/Y H:i') }}
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
            @endif
        </div>
    </div>

    @push('styles')
    <style>
        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(-10px) scale(0.95);
            }
            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }
        
        .animate-slideIn {
            animation: slideIn 0.3s ease-out;
        }
        
        .product-card-container {
            transition: all 0.3s ease;
        }
    </style>
    @endpush

    @push('scripts')
    <script>
        function recentlyAddedManager() {
            return {
                recentProducts: @json($recentlyAdded ?? []),
                
                init() {
                    // Set added_at for existing products if not present
                    this.recentProducts.forEach(product => {
                        if (!product.added_at) {
                            product.added_at = new Date();
                        }
                    });
                },
                
                handleProductAdded(product) {
                    // Check if product is already in the recent list
                    const existingIndex = this.recentProducts.findIndex(p => p.CODE === product.CODE);
                    
                    if (existingIndex === -1) {
                        // Add animation flag and timestamp
                        product.isNew = true;
                        product.added_at = new Date();
                        
                        // Add to the beginning of the list
                        this.recentProducts.unshift(product);
                        
                        // Keep only the most recent 10 products
                        if (this.recentProducts.length > 10) {
                            this.recentProducts = this.recentProducts.slice(0, 10);
                        }
                        
                        // Remove animation flag after animation completes
                        setTimeout(() => {
                            product.isNew = false;
                        }, 300);
                    } else {
                        // Update timestamp for existing product
                        this.recentProducts[existingIndex].added_at = new Date();
                        this.recentProducts[existingIndex].isNew = true;
                        
                        // Move to front
                        const [updatedProduct] = this.recentProducts.splice(existingIndex, 1);
                        this.recentProducts.unshift(updatedProduct);
                        
                        // Remove animation flag after animation completes
                        setTimeout(() => {
                            updatedProduct.isNew = false;
                        }, 300);
                    }
                },
                
                handleProductRemoved(product) {
                    // Remove from recent list if present
                    const index = this.recentProducts.findIndex(p => p.CODE === product.CODE);
                    if (index !== -1) {
                        this.recentProducts.splice(index, 1);
                    }
                },
                
                getTimeAgo(dateString) {
                    if (!dateString) return 'Just now';
                    
                    const date = new Date(dateString);
                    const now = new Date();
                    const diffInSeconds = Math.floor((now - date) / 1000);
                    
                    if (diffInSeconds < 60) {
                        return 'Just now';
                    } else if (diffInSeconds < 3600) {
                        const minutes = Math.floor(diffInSeconds / 60);
                        return `${minutes} minute${minutes !== 1 ? 's' : ''} ago`;
                    } else if (diffInSeconds < 86400) {
                        const hours = Math.floor(diffInSeconds / 3600);
                        return `${hours} hour${hours !== 1 ? 's' : ''} ago`;
                    } else {
                        const days = Math.floor(diffInSeconds / 86400);
                        return `${days} day${days !== 1 ? 's' : ''} ago`;
                    }
                }
            }
        }
    </script>
    @endpush
</x-admin-layout>