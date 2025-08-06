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

            <!-- Product Health Dashboard (Auto-loading) -->
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-6 mb-6" 
                 x-data="productHealthDashboard('{{ $category->ID }}')" 
                 x-cloak>
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-semibold">Product Health Dashboard</h3>
                    <div class="flex items-center gap-2">
                        <span x-show="!loading['good-sellers-silent'] && !loading['slow-movers'] && !loading['stagnant-stock'] && !loading['inventory-alerts'] && !hasAnyIssues()" 
                              class="text-sm text-green-600">
                            ✅ All systems healthy
                        </span>
                        <a href="{{ route('categories.products', $category) }}" 
                           class="text-sm text-blue-600 hover:text-blue-700">
                            View all products →
                        </a>
                    </div>
                </div>

                <!-- Tab Navigation -->
                <div class="border-b border-gray-200 dark:border-gray-700 mb-4">
                    <nav class="-mb-px flex space-x-8">
                        <button @click="activeTab = 'good-sellers-silent'"
                                :class="{ 
                                    'border-red-500 text-red-600': activeTab === 'good-sellers-silent',
                                    'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300': activeTab !== 'good-sellers-silent'
                                }"
                                class="whitespace-nowrap py-2 px-1 border-b-2 font-medium text-sm transition-colors">
                            🚨 Gone Silent
                            <span x-show="loading['good-sellers-silent']" class="ml-2">
                                <svg class="animate-spin h-3 w-3 text-gray-500 inline" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                            </span>
                            <span x-show="!loading['good-sellers-silent'] && counts['good-sellers-silent'] > 0" 
                                  class="ml-2 bg-red-100 text-red-800 text-xs font-medium px-2.5 py-0.5 rounded-full"
                                  x-text="counts['good-sellers-silent']"></span>
                        </button>
                        <button @click="activeTab = 'slow-movers'"
                                :class="{ 
                                    'border-orange-500 text-orange-600': activeTab === 'slow-movers',
                                    'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300': activeTab !== 'slow-movers'
                                }"
                                class="whitespace-nowrap py-2 px-1 border-b-2 font-medium text-sm transition-colors">
                            🐌 Slow Movers
                            <span x-show="loading['slow-movers']" class="ml-2">
                                <svg class="animate-spin h-3 w-3 text-gray-500 inline" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                            </span>
                            <span x-show="!loading['slow-movers'] && counts['slow-movers'] > 0" 
                                  class="ml-2 bg-orange-100 text-orange-800 text-xs font-medium px-2.5 py-0.5 rounded-full"
                                  x-text="counts['slow-movers']"></span>
                        </button>
                        <button @click="activeTab = 'stagnant-stock'"
                                :class="{ 
                                    'border-yellow-500 text-yellow-600': activeTab === 'stagnant-stock',
                                    'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300': activeTab !== 'stagnant-stock'
                                }"
                                class="whitespace-nowrap py-2 px-1 border-b-2 font-medium text-sm transition-colors">
                            ⚠️ Stagnant Stock
                            <span x-show="loading['stagnant-stock']" class="ml-2">
                                <svg class="animate-spin h-3 w-3 text-gray-500 inline" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                            </span>
                            <span x-show="!loading['stagnant-stock'] && counts['stagnant-stock'] > 0" 
                                  class="ml-2 bg-yellow-100 text-yellow-800 text-xs font-medium px-2.5 py-0.5 rounded-full"
                                  x-text="counts['stagnant-stock']"></span>
                        </button>
                        <button @click="activeTab = 'inventory-alerts'"
                                :class="{ 
                                    'border-blue-500 text-blue-600': activeTab === 'inventory-alerts',
                                    'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300': activeTab !== 'inventory-alerts'
                                }"
                                class="whitespace-nowrap py-2 px-1 border-b-2 font-medium text-sm transition-colors">
                            📊 Inventory Alerts
                            <span x-show="loading['inventory-alerts']" class="ml-2">
                                <svg class="animate-spin h-3 w-3 text-gray-500 inline" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                            </span>
                            <span x-show="!loading['inventory-alerts'] && counts['inventory-alerts'] > 0" 
                                  class="ml-2 bg-blue-100 text-blue-800 text-xs font-medium px-2.5 py-0.5 rounded-full"
                                  x-text="counts['inventory-alerts']"></span>
                        </button>
                    </nav>
                </div>

                <!-- Tab Content Container -->
                <div class="min-h-[200px]">
                    <!-- Loading State for Current Tab -->
                    <div x-show="loading[activeTab]" class="flex items-center justify-center py-12">
                        <div class="text-center">
                            <svg class="animate-spin h-8 w-8 text-gray-600 mx-auto mb-4" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            <p class="text-gray-500 text-sm">Loading <span x-text="activeTab.replace(/-/g, ' ')"></span> data...</p>
                        </div>
                    </div>

                    <!-- Content will be dynamically inserted here -->
                    <div x-show="!loading[activeTab]" x-html="tabContent[activeTab]"></div>
                </div>
            </div>

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

    <script>
        function productHealthDashboard(categoryId) {
            return {
                categoryId: categoryId,
                activeTab: 'good-sellers-silent',
                dashboardLoaded: false,
                loading: {
                    'good-sellers-silent': false,
                    'slow-movers': false,
                    'stagnant-stock': false,
                    'inventory-alerts': false
                },
                counts: {
                    'good-sellers-silent': 0,
                    'slow-movers': 0,
                    'stagnant-stock': 0,
                    'inventory-alerts': 0
                },
                tabContent: {
                    'good-sellers-silent': '',
                    'slow-movers': '',
                    'stagnant-stock': '',
                    'inventory-alerts': ''
                },

                init() {
                    // Set all tabs to loading state initially
                    this.loading['good-sellers-silent'] = true;
                    this.loading['slow-movers'] = true;
                    this.loading['stagnant-stock'] = true;
                    this.loading['inventory-alerts'] = true;
                    this.dashboardLoaded = true;
                    
                    // Start loading all tabs immediately
                    this.loadTab('good-sellers-silent');
                    this.loadTab('slow-movers');
                    this.loadTab('stagnant-stock');
                    this.loadTab('inventory-alerts');
                },

                loadDashboard() {
                    // This method is now only used for manual refresh if needed
                    this.dashboardLoaded = true;
                    this.loadTab('good-sellers-silent');
                    this.loadTab('slow-movers');
                    this.loadTab('stagnant-stock');
                    this.loadTab('inventory-alerts');
                },

                async loadTab(tabName) {
                    // Don't set loading to true if already loading (from init)
                    if (!this.loading[tabName]) {
                        this.loading[tabName] = true;
                    }
                    
                    try {
                        const response = await fetch(`/categories/${this.categoryId}/dashboard-data?section=${tabName}`, {
                            headers: {
                                'Accept': 'application/json',
                                'X-Requested-With': 'XMLHttpRequest'
                            }
                        });

                        if (!response.ok) throw new Error('Failed to load');

                        const result = await response.json();
                        
                        if (result.success) {
                            this.counts[tabName] = result.count || 0;
                            this.tabContent[tabName] = this.renderTabContent(tabName, result.data || []);
                        }
                    } catch (error) {
                        console.error(`Error loading ${tabName}:`, error);
                        this.tabContent[tabName] = '<div class="text-center py-8 text-red-500">Failed to load data</div>';
                    } finally {
                        this.loading[tabName] = false;
                    }
                },

                renderTabContent(tabName, data) {
                    if (!data || data.length === 0) {
                        return this.renderEmptyState(tabName);
                    }

                    let html = '<div class="grid grid-cols-1 md:grid-cols-2 gap-3">';
                    
                    data.forEach(product => {
                        html += this.renderProductCard(tabName, product);
                    });
                    
                    html += '</div>';
                    return html;
                },

                renderProductCard(tabName, product) {
                    const themes = {
                        'good-sellers-silent': 'red',
                        'slow-movers': 'orange',
                        'stagnant-stock': 'yellow',
                        'inventory-alerts': 'blue'
                    };
                    const theme = themes[tabName] || 'gray';
                    
                    // Generate product URL
                    const productUrl = `/products/${product.product_id}`;
                    
                    // Format stock level
                    const stockDisplay = product.current_stock !== null && product.current_stock !== undefined
                        ? `Stock: ${product.current_stock}`
                        : 'Stock: N/A';
                    
                    return `
                        <div class="bg-${theme}-50 dark:bg-${theme}-900/20 border border-${theme}-200 dark:border-${theme}-800 rounded-lg p-4 hover:shadow-md transition-shadow">
                            <div class="flex justify-between items-start mb-2">
                                <div class="flex-1 min-w-0">
                                    <a href="${productUrl}" class="font-medium text-sm text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300 truncate block">
                                        ${product.product_name || 'Unknown Product'}
                                    </a>
                                    <div class="flex items-center gap-3 text-xs text-gray-500 mt-1">
                                        <span>${product.product_code || ''}</span>
                                        <span class="font-medium">${stockDisplay}</span>
                                    </div>
                                </div>
                                ${this.renderBadge(tabName, product)}
                            </div>
                            ${this.renderMetrics(tabName, product)}
                        </div>
                    `;
                },

                renderBadge(tabName, product) {
                    if (tabName === 'good-sellers-silent') {
                        return `<span class="text-xs bg-red-100 text-red-700 px-2 py-1 rounded-full whitespace-nowrap">${product.days_since_last_sale}d ago</span>`;
                    } else if (tabName === 'slow-movers') {
                        const velocity = parseFloat(product.daily_velocity).toFixed(2);
                        return `<span class="text-xs bg-orange-100 text-orange-700 px-2 py-1 rounded-full whitespace-nowrap">${velocity}/day</span>`;
                    } else if (tabName === 'stagnant-stock') {
                        return `<span class="text-xs bg-yellow-100 text-yellow-700 px-2 py-1 rounded-full whitespace-nowrap">${product.days_since_last_sale}d ago</span>`;
                    } else {
                        const velocity = parseFloat(product.daily_velocity).toFixed(1);
                        return `<span class="text-xs bg-blue-100 text-blue-700 px-2 py-1 rounded-full whitespace-nowrap">${velocity}/day</span>`;
                    }
                },

                renderMetrics(tabName, product) {
                    if (tabName === 'good-sellers-silent') {
                        const avgFormatted = parseFloat(product.daily_average).toFixed(1);
                        return `
                            <div class="flex justify-between text-xs text-gray-600 dark:text-gray-400">
                                <span>Avg: ${avgFormatted}/day</span>
                                <span>Total: ${this.formatNumber(product.historical_units)}</span>
                            </div>
                        `;
                    } else if (tabName === 'slow-movers') {
                        return `
                            <div class="flex justify-between text-xs text-gray-600 dark:text-gray-400">
                                <span>60d: ${this.formatNumber(product.total_units)} units</span>
                                <span>€${this.formatNumber(product.total_revenue)}</span>
                            </div>
                        `;
                    } else if (tabName === 'stagnant-stock') {
                        const lastSale = product.last_sale_date 
                            ? new Date(product.last_sale_date).toLocaleDateString('en-IE', {month: 'short', day: 'numeric'})
                            : 'Unknown';
                        return `
                            <div class="flex justify-between text-xs text-gray-600 dark:text-gray-400">
                                <span>Prev: ${this.formatNumber(product.previous_units)}</span>
                                <span>Last: ${lastSale}</span>
                            </div>
                        `;
                    } else {
                        return `
                            <div class="flex justify-between text-xs text-gray-600 dark:text-gray-400">
                                <span>30d: ${this.formatNumber(product.monthly_units)}</span>
                                <span>Active: ${product.active_days}d</span>
                            </div>
                        `;
                    }
                },

                renderEmptyState(tabName) {
                    const messages = {
                        'good-sellers-silent': '✅ No issues found! All good sellers are active.',
                        'slow-movers': '🚀 Great! No slow-moving products detected.',
                        'stagnant-stock': '🔄 Excellent! No stagnant stock found.',
                        'inventory-alerts': '📊 No significant inventory alerts.'
                    };
                    
                    return `
                        <div class="text-center py-8">
                            <p class="text-gray-500">${messages[tabName]}</p>
                        </div>
                    `;
                },

                formatNumber(num) {
                    return new Intl.NumberFormat().format(num || 0);
                },

                hasAnyIssues() {
                    return Object.values(this.counts).reduce((sum, count) => sum + count, 0) > 0;
                }
            }
        }
    </script>
</x-admin-layout>