<x-admin-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="text-xl font-semibold">📂 Category Management</h2>
            <div class="flex items-center gap-2">
                <span class="text-sm text-gray-500">{{ $totalCategories }} categories</span>
                <span class="text-sm text-gray-500">•</span>
                <span class="text-sm text-gray-500">{{ $totalProducts }} products</span>
                <span class="text-sm text-gray-500">•</span>
                <span class="text-sm text-green-600">€{{ number_format($totalRevenue, 0) }} total revenue</span>
            </div>
        </div>
    </x-slot>

    <div class="py-4">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <!-- Search and Filter Bar -->
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-4 mb-6">
                <form method="GET" action="{{ route('categories.index') }}" class="flex gap-4">
                    <div class="flex-1">
                        <input type="text" 
                               name="search" 
                               value="{{ $search }}"
                               placeholder="Search categories..." 
                               class="w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300">
                    </div>
                    <div class="flex items-center gap-4">
                        <label class="flex items-center gap-2">
                            <input type="checkbox" 
                                   name="show_empty" 
                                   value="1" 
                                   {{ $showEmpty ? 'checked' : '' }}
                                   class="rounded border-gray-300 dark:border-gray-700">
                            <span class="text-sm text-gray-600 dark:text-gray-400">Show empty categories</span>
                        </label>
                        <div class="flex items-center gap-2">
                            <label class="text-sm text-gray-600 dark:text-gray-400">Revenue Period:</label>
                            <select name="period" class="rounded border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 text-sm">
                                <option value="week" {{ $period === 'week' ? 'selected' : '' }}>Last Week</option>
                                <option value="month" {{ $period === 'month' ? 'selected' : '' }}>Last Month</option>
                            </select>
                        </div>
                        <div class="flex items-center gap-2">
                            <label class="text-sm text-gray-600 dark:text-gray-400">Sort by:</label>
                            <select name="sort" class="rounded border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 text-sm">
                                <option value="revenue" {{ $sortBy === 'revenue' ? 'selected' : '' }}>Revenue (High to Low)</option>
                                <option value="name" {{ $sortBy === 'name' ? 'selected' : '' }}>Name (A-Z)</option>
                            </select>
                        </div>
                    </div>
                    <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">
                        Filter
                    </button>
                </form>
            </div>

            <!-- Categories Grid -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
                @forelse($categories as $category)
                    <a href="{{ route('categories.show', $category) }}" 
                       class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-6 hover:shadow-md transition-shadow group">
                        <div class="flex justify-between items-start mb-3">
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 group-hover:text-blue-600">
                                {{ $category->NAME }}
                            </h3>
                            @if($category->hasChildren())
                                <span class="text-xs bg-gray-100 dark:bg-gray-700 px-2 py-1 rounded">
                                    {{ $category->children->count() }} sub
                                </span>
                            @endif
                        </div>
                        
                        <div class="text-sm text-gray-600 dark:text-gray-400 space-y-2">
                            <div class="flex justify-between">
                                <span>Products:</span>
                                <span class="font-medium">{{ $category->total_products }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span>Revenue ({{ ucfirst($period) }}):</span>
                                <span class="font-medium text-green-600">€{{ number_format($category->revenue, 0) }}</span>
                            </div>
                            
                            <!-- Revenue Progress Bar -->
                            <div class="mt-3">
                                <div class="flex justify-between text-xs mb-1">
                                    <span>Revenue Contribution</span>
                                    <span>{{ $category->revenue_percentage }}%</span>
                                </div>
                                <div class="w-full bg-gray-200 rounded-full h-2">
                                    <div class="bg-blue-600 h-2 rounded-full" 
                                         style="width: {{ $category->revenue_percentage }}%"></div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Category Actions -->
                        <div class="mt-4 flex gap-2">
                            <span class="text-xs bg-blue-100 text-blue-700 px-2 py-1 rounded">
                                View Products
                            </span>
                            <span class="text-xs bg-purple-100 text-purple-700 px-2 py-1 rounded">
                                Sales Analytics
                            </span>
                        </div>
                    </a>
                @empty
                    <div class="col-span-full text-center py-12">
                        <p class="text-gray-500">No categories found matching your criteria.</p>
                    </div>
                @endforelse
            </div>

            <!-- Quick Stats -->
            <div class="mt-8 bg-gray-50 dark:bg-gray-900 rounded-lg p-6">
                <h3 class="text-lg font-semibold mb-4">Category Insights</h3>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <p class="text-sm text-gray-600 dark:text-gray-400">Most Products</p>
                        @php
                            $topCategory = $categories->sortByDesc('total_products')->first();
                        @endphp
                        @if($topCategory)
                            <p class="text-lg font-medium">{{ $topCategory->NAME }}</p>
                            <p class="text-sm text-gray-500">{{ $topCategory->total_products }} products</p>
                        @endif
                    </div>
                    <div>
                        <p class="text-sm text-gray-600 dark:text-gray-400">Top Revenue</p>
                        @php
                            $topRevenue = $categories->where('revenue', '>', 0)->sortByDesc('revenue')->first();
                        @endphp
                        @if($topRevenue)
                            <p class="text-lg font-medium">{{ $topRevenue->NAME }}</p>
                            <p class="text-sm text-green-600">€{{ number_format($topRevenue->revenue, 0) }}</p>
                        @endif
                    </div>
                    <div>
                        <p class="text-sm text-gray-600 dark:text-gray-400">Low Performers</p>
                        @php
                            $lowPerformer = $categories->where('total_products', '>', 0)->where('revenue', '>', 0)->sortBy('revenue')->first();
                        @endphp
                        @if($lowPerformer && $lowPerformer->revenue < ($totalRevenue * 0.05))
                            <p class="text-lg font-medium">{{ $lowPerformer->NAME }}</p>
                            <p class="text-sm text-orange-600">€{{ number_format($lowPerformer->revenue, 0) }} revenue</p>
                        @else
                            <p class="text-sm text-gray-500">All categories performing well</p>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-admin-layout>