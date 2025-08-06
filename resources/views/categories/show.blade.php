<x-admin-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <div>
                <nav class="text-sm text-gray-500 mb-2">
                    <a href="{{ route('categories.index') }}" class="hover:text-gray-700">Categories</a>
                    <span class="mx-2">/</span>
                    <span class="text-gray-900 dark:text-gray-100">{{ $category->NAME }}</span>
                </nav>
                <h2 class="text-xl font-semibold">{{ $category->NAME }} Management</h2>
            </div>
            <div class="flex items-center gap-4">
                <div class="flex items-center gap-2">
                    <span class="text-sm text-gray-500">{{ $totalProducts }} products</span>
                    <span class="text-sm text-gray-500">•</span>
                    <span class="text-sm text-green-600">{{ $visibleProducts }} visible</span>
                </div>
                <a href="{{ route('products.create') }}?category={{ $category->ID }}" 
                   class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700 transition-colors text-sm font-medium">
                    + Create Product
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-4">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <!-- Quick Actions -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                <a href="{{ route('categories.products', $category) }}" 
                   class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-6 hover:shadow-md transition-shadow">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-gray-600 dark:text-gray-400">Manage Products</p>
                            <p class="text-2xl font-bold">{{ $totalProducts }}</p>
                            <p class="text-sm text-green-600">{{ $visibleProducts }} on till</p>
                        </div>
                        <svg class="w-12 h-12 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                  d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                        </svg>
                    </div>
                </a>

                <a href="{{ route('categories.sales', $category) }}" 
                   class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-6 hover:shadow-md transition-shadow">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-gray-600 dark:text-gray-400">Sales Analytics</p>
                            <p class="text-2xl font-bold">View</p>
                            <p class="text-sm text-gray-500">Performance data</p>
                        </div>
                        <svg class="w-12 h-12 text-purple-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                  d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                        </svg>
                    </div>
                </a>

                <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-gray-600 dark:text-gray-400">Till Visibility</p>
                            <p class="text-2xl font-bold">{{ $totalProducts > 0 ? round(($visibleProducts / $totalProducts) * 100) : 0 }}%</p>
                            <div class="w-full bg-gray-200 rounded-full h-2 mt-2">
                                <div class="bg-green-600 h-2 rounded-full" 
                                     style="width: {{ $totalProducts > 0 ? ($visibleProducts / $totalProducts) * 100 : 0 }}%"></div>
                            </div>
                        </div>
                        <svg class="w-12 h-12 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                  d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                  d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                        </svg>
                    </div>
                </div>
            </div>

            <!-- Subcategories if any -->
            @if($subcategories->count() > 0)
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-6 mb-6">
                    <h3 class="text-lg font-semibold mb-4">Subcategories</h3>
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                        @foreach($subcategories as $subcategory)
                            <a href="{{ route('categories.show', $subcategory) }}" 
                               class="bg-gray-50 dark:bg-gray-700 rounded-lg p-3 hover:bg-gray-100 dark:hover:bg-gray-600">
                                <p class="font-medium">{{ $subcategory->NAME }}</p>
                                <p class="text-sm text-gray-500">{{ $subcategory->products_count }} products</p>
                            </a>
                        @endforeach
                    </div>
                </div>
            @endif

            <!-- Two Column Layout for Latest and Top Sellers -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
                <!-- Latest Products -->
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-6">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-lg font-semibold">Latest Products</h3>
                        <a href="{{ route('categories.products', $category) }}" 
                           class="text-sm text-blue-600 hover:text-blue-700">
                            View all →
                        </a>
                    </div>
                    @if($latestProducts->count() > 0)
                        <div class="space-y-3">
                            @foreach($latestProducts->take(5) as $product)
                                <div class="flex items-center gap-3 p-2 hover:bg-gray-50 dark:hover:bg-gray-700 rounded">
                                    <div class="w-10 h-10 rounded bg-gray-100 flex items-center justify-center flex-shrink-0">
                                        <img src="{{ route('categories.product-image', $product->CODE) }}" 
                                             alt=""
                                             class="w-full h-full object-cover rounded"
                                             onerror="this.style.display='none'; this.nextElementSibling.style.display='flex'">
                                        <div class="hidden w-full h-full items-center justify-center text-gray-400">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                            </svg>
                                        </div>
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <p class="text-sm font-medium text-gray-900 dark:text-gray-100 truncate">
                                            {{ $product->NAME }}
                                        </p>
                                        <p class="text-xs text-gray-500">{{ $product->CODE }}</p>
                                    </div>
                                    <div class="text-right">
                                        <p class="text-sm font-semibold text-blue-600">
                                            €{{ number_format($product->PRICESELL * (1 + $product->getVatRate()), 2) }}
                                        </p>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <p class="text-gray-500 text-sm">No products in this category yet.</p>
                    @endif
                </div>

                <!-- Top 10 Sellers -->
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-6">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-lg font-semibold">Top Sellers (Last 30 Days)</h3>
                        <a href="{{ route('categories.sales', $category) }}" 
                           class="text-sm text-blue-600 hover:text-blue-700">
                            View analytics →
                        </a>
                    </div>
                    @if($topSellers->count() > 0)
                        <div class="space-y-3">
                            @foreach($topSellers->take(5) as $index => $product)
                                <div class="flex items-center gap-3 p-2 hover:bg-gray-50 dark:hover:bg-gray-700 rounded">
                                    <div class="w-6 h-6 rounded-full bg-blue-100 text-blue-600 flex items-center justify-center text-xs font-bold flex-shrink-0">
                                        {{ $index + 1 }}
                                    </div>
                                    <div class="w-10 h-10 rounded bg-gray-100 flex items-center justify-center flex-shrink-0">
                                        <img src="{{ route('categories.product-image', $product->product_code) }}" 
                                             alt=""
                                             class="w-full h-full object-cover rounded"
                                             onerror="this.style.display='none'; this.nextElementSibling.style.display='flex'">
                                        <div class="hidden w-full h-full items-center justify-center text-gray-400">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                            </svg>
                                        </div>
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <p class="text-sm font-medium text-gray-900 dark:text-gray-100 truncate">
                                            {{ $product->product_name }}
                                        </p>
                                        <p class="text-xs text-gray-500">
                                            {{ number_format($product->total_units) }} units • €{{ number_format($product->total_revenue, 2) }}
                                        </p>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <p class="text-gray-500 text-sm">No sales data available for the last 30 days.</p>
                    @endif
                </div>
            </div>

            <!-- Featured Products -->
            @if($featuredProducts->count() > 0)
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-6 mb-6">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-lg font-semibold">Featured Products</h3>
                        <a href="{{ route('categories.products', $category) }}" 
                           class="text-sm text-blue-600 hover:text-blue-700">
                            View all →
                        </a>
                    </div>
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                        @foreach($featuredProducts as $product)
                            <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-4">
                                <div class="flex items-center justify-between mb-2">
                                    <span class="text-xs font-medium text-gray-500">{{ $product->CODE }}</span>
                                    @if($product->is_visible)
                                        <span class="text-xs bg-green-100 text-green-700 px-2 py-1 rounded">On Till</span>
                                    @endif
                                </div>
                                <p class="font-medium text-sm mb-1">{{ Str::limit($product->NAME, 50) }}</p>
                                <p class="text-lg font-bold text-blue-600">
                                    €{{ number_format($product->PRICESELL * (1 + $product->getVatRate()), 2) }}
                                </p>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            <!-- Quick Stats -->
            <div class="mt-6 grid grid-cols-1 md:grid-cols-4 gap-4">
                <div class="bg-gray-50 dark:bg-gray-900 rounded-lg p-4">
                    <p class="text-sm text-gray-600 dark:text-gray-400">Category ID</p>
                    <p class="text-lg font-medium">{{ $category->ID }}</p>
                </div>
                <div class="bg-gray-50 dark:bg-gray-900 rounded-lg p-4">
                    <p class="text-sm text-gray-600 dark:text-gray-400">Parent Category</p>
                    <p class="text-lg font-medium">{{ $category->parent ? $category->parent->NAME : 'None' }}</p>
                </div>
                <div class="bg-gray-50 dark:bg-gray-900 rounded-lg p-4">
                    <p class="text-sm text-gray-600 dark:text-gray-400">Show Name</p>
                    <p class="text-lg font-medium">{{ $category->CATSHOWNAME ? 'Yes' : 'No' }}</p>
                </div>
                <div class="bg-gray-50 dark:bg-gray-900 rounded-lg p-4">
                    <p class="text-sm text-gray-600 dark:text-gray-400">Products Count</p>
                    <p class="text-lg font-medium">{{ $totalProducts }}</p>
                </div>
            </div>
        </div>
    </div>
</x-admin-layout>