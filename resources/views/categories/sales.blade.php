<x-admin-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <div>
                <nav class="text-sm text-gray-500 mb-2">
                    <a href="{{ route('categories.index') }}" class="hover:text-gray-700">Categories</a>
                    <span class="mx-2">/</span>
                    <a href="{{ route('categories.show', $category) }}" class="hover:text-gray-700">{{ $category->NAME }}</a>
                    <span class="mx-2">/</span>
                    <span class="text-gray-900 dark:text-gray-100">Sales Analytics</span>
                </nav>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    {{ $category->NAME }} Sales Analytics
                </h2>
            </div>
            <a href="{{ route('categories.show', $category) }}" class="text-blue-600 hover:text-blue-800">
                ← Back to Category
            </a>
        </div>
    </x-slot>

    <div class="py-6" x-data="categorySalesData()" x-init="init()">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            
            <!-- Enhanced Date Navigation -->
            <div class="bg-white rounded-lg shadow p-4 mb-6">
                <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
                    <!-- Current Period Display -->
                    <div class="flex items-center gap-3">
                        <h3 class="text-lg font-medium text-gray-900">Sales Period:</h3>
                        <div class="flex items-center bg-gray-50 px-3 py-2 rounded-md">
                            <span class="text-sm font-medium text-gray-700" x-text="formatDateRange()"></span>
                            <span class="ml-2 text-xs text-gray-500" x-text="getPeriodInfo()"></span>
                        </div>
                    </div>
                    
                    <!-- Navigation Controls -->
                    <div class="flex items-center gap-2 flex-wrap">
                        <!-- Week Navigation -->
                        <div class="flex items-center bg-gray-50 rounded-lg p-1">
                            <span class="text-xs font-medium text-gray-600 px-2 hidden sm:inline">Week</span>
                            <button @click="navigateWeek(-1)" 
                                    class="p-1.5 text-gray-500 hover:text-blue-600 hover:bg-white rounded transition-colors"
                                    title="Previous Week">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                                </svg>
                            </button>
                            <button @click="navigateWeek(1)" 
                                    class="p-1.5 text-gray-500 hover:text-blue-600 hover:bg-white rounded transition-colors"
                                    title="Next Week">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                </svg>
                            </button>
                        </div>
                        
                        <!-- Month Navigation -->
                        <div class="flex items-center bg-gray-50 rounded-lg p-1">
                            <span class="text-xs font-medium text-gray-600 px-2 hidden sm:inline">Month</span>
                            <button @click="navigateMonth(-1)" 
                                    class="p-1.5 text-gray-500 hover:text-blue-600 hover:bg-white rounded transition-colors"
                                    title="Previous Month">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 19l-7-7 7-7m8 14l-7-7 7-7" />
                                </svg>
                            </button>
                            <button @click="navigateMonth(1)" 
                                    class="p-1.5 text-gray-500 hover:text-blue-600 hover:bg-white rounded transition-colors"
                                    title="Next Month">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 5l7 7-7 7M5 5l7 7-7 7" />
                                </svg>
                            </button>
                        </div>
                        
                        <!-- Quick Periods Dropdown -->
                        <select @change="setQuickPeriod($event.target.value)" 
                                class="text-sm rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            <option value="">Quick Jump</option>
                            <option value="today">Today</option>
                            <option value="yesterday">Yesterday</option>
                            <option value="thisWeek">This Week</option>
                            <option value="lastWeek">Last Week</option>
                            <option value="thisMonth">This Month</option>
                            <option value="lastMonth">Last Month</option>
                            <option value="last30">Last 30 Days</option>
                            <option value="latest">Latest Data</option>
                        </select>
                        
                        <!-- Manual Date Selection (Compact) -->
                        <div class="flex items-center gap-1 text-xs">
                            <input type="date" x-model="startDate" @change="loadSalesData()"
                                   class="text-xs rounded border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 w-24 sm:w-28">
                            <span class="text-gray-400 hidden sm:inline">–</span>
                            <input type="date" x-model="endDate" @change="loadSalesData()"
                                   class="text-xs rounded border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 w-24 sm:w-28">
                        </div>
                    </div>
                </div>
            </div>

            <!-- Statistics Cards -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                <div class="bg-white rounded-lg shadow p-6">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-blue-100 text-blue-800">
                            <svg class="h-8 w-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 12l3-3 3 3 4-4M8 21l4-4 4 4M3 4h18M4 4h16v12a1 1 0 01-1 1H5a1 1 0 01-1-1V4z" />
                            </svg>
                        </div>
                        <div class="ml-5">
                            <p class="text-gray-500 text-sm font-medium">Total Units Sold</p>
                            <p class="text-2xl font-semibold text-gray-900" x-text="formatNumber(stats.total_units)">0</p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-lg shadow p-6">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-green-100 text-green-800">
                            <svg class="h-8 w-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <circle cx="12" cy="12" r="10" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 8.5c-1-1.5-3-2.5-5-1.5s-3 3-3 5.5 1 4.5 3 5.5 4 0 5-1.5M8 10h4M8 14h4" />
                            </svg>
                        </div>
                        <div class="ml-5">
                            <p class="text-gray-500 text-sm font-medium">Total Revenue</p>
                            <p class="text-2xl font-semibold text-gray-900" x-text="formatCurrency(stats.total_revenue)">€0.00</p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-lg shadow p-6">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-purple-100 text-purple-800">
                            <svg class="h-8 w-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                            </svg>
                        </div>
                        <div class="ml-5">
                            <p class="text-gray-500 text-sm font-medium">Products Sold</p>
                            <p class="text-2xl font-semibold text-gray-900" x-text="stats.unique_products">0</p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-lg shadow p-6">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-orange-100 text-orange-800">
                            <svg class="h-8 w-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                            </svg>
                        </div>
                        <div class="ml-5">
                            <p class="text-gray-500 text-sm font-medium">Transactions</p>
                            <p class="text-2xl font-semibold text-gray-900" x-text="stats.total_transactions">0</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Sales Chart -->
            <div class="bg-white rounded-lg shadow p-6 mb-8">
                <h3 class="text-lg font-medium text-gray-900 mb-4">Daily Sales Trend</h3>
                <div class="h-64">
                    <canvas id="salesChart"></canvas>
                </div>
            </div>

            <!-- Top Products Table -->
            <div class="bg-white rounded-lg shadow overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200">
                    <div class="flex justify-between items-center">
                        <h3 class="text-lg font-medium text-gray-900">Top Selling Products</h3>
                        <input type="text" 
                               x-model="searchTerm" 
                               @input.debounce.300ms="filterProducts()"
                               placeholder="Search products..." 
                               class="text-sm px-3 py-1 border border-gray-300 rounded-md">
                    </div>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    <button @click="sortBy('product_name')" class="group inline-flex items-center">
                                        Product
                                        <span class="ml-2">
                                            <svg class="w-4 h-4" :class="getSortIcon('product_name')" fill="currentColor" viewBox="0 0 320 512">
                                                <path d="M41 288h238c21.4 0 32.1 25.9 17 41L177 448c-9.4 9.4-24.6 9.4-33.9 0L24 329c-15.1-15.1-4.4-41 17-41z"/>
                                            </svg>
                                        </span>
                                    </button>
                                </th>
                                <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    <button @click="sortBy('total_units')" class="group inline-flex items-center justify-end w-full">
                                        Units Sold
                                        <span class="ml-2">
                                            <svg class="w-4 h-4" :class="getSortIcon('total_units')" fill="currentColor" viewBox="0 0 320 512">
                                                <path d="M41 288h238c21.4 0 32.1 25.9 17 41L177 448c-9.4 9.4-24.6 9.4-33.9 0L24 329c-15.1-15.1-4.4-41 17-41z"/>
                                            </svg>
                                        </span>
                                    </button>
                                </th>
                                <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    <button @click="sortBy('total_revenue')" class="group inline-flex items-center justify-end w-full">
                                        Revenue
                                        <span class="ml-2">
                                            <svg class="w-4 h-4" :class="getSortIcon('total_revenue')" fill="currentColor" viewBox="0 0 320 512">
                                                <path d="M41 288h238c21.4 0 32.1 25.9 17 41L177 448c-9.4 9.4-24.6 9.4-33.9 0L24 329c-15.1-15.1-4.4-41 17-41z"/>
                                            </svg>
                                        </span>
                                    </button>
                                </th>
                                <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    <button @click="sortBy('avg_price')" class="group inline-flex items-center justify-end w-full">
                                        Avg Price
                                        <span class="ml-2">
                                            <svg class="w-4 h-4" :class="getSortIcon('avg_price')" fill="currentColor" viewBox="0 0 320 512">
                                                <path d="M41 288h238c21.4 0 32.1 25.9 17 41L177 448c-9.4 9.4-24.6 9.4-33.9 0L24 329c-15.1-15.1-4.4-41 17-41z"/>
                                            </svg>
                                        </span>
                                    </button>
                                </th>
                            </tr>
                        </thead>
                        
                        <!-- Loading state -->
                        <tbody x-show="loading" class="bg-white divide-y divide-gray-200">
                            <tr>
                                <td colspan="4" class="px-6 py-12 text-center">
                                    <div class="flex justify-center items-center">
                                        <svg class="animate-spin h-8 w-8 text-blue-600" fill="none" viewBox="0 0 24 24">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                        </svg>
                                        <span class="ml-3 text-gray-600">Loading sales data...</span>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                        
                        <!-- No data state -->
                        <tbody x-show="!loading && filteredProducts.length === 0" class="bg-white divide-y divide-gray-200">
                            <tr>
                                <td colspan="4" class="px-6 py-12 text-center">
                                    <div class="text-gray-500">
                                        <svg class="mx-auto h-12 w-12 text-blue-400 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                                        </svg>
                                        <h3 class="mt-2 text-sm font-medium text-gray-900">No sales data</h3>
                                        <p class="mt-1 text-sm text-gray-500">No sales found for the selected date range.</p>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                        
                        <!-- Sales data rows -->
                        <template x-for="product in filteredProducts" :key="product.product_id">
                            <tbody>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex items-center justify-between">
                                            <div>
                                                <div class="text-sm font-medium text-gray-900" x-text="product.product_name"></div>
                                                <div class="text-sm text-gray-500" x-text="product.product_code"></div>
                                            </div>
                                            <button @click="toggleDailySales(product.product_id, product.product_code)" 
                                                    class="ml-4 p-1 text-gray-400 hover:text-gray-600 transition-colors"
                                                    :title="expandedProducts.includes(product.product_id) ? 'Hide daily sales' : 'Show daily sales'">
                                                <svg class="w-5 h-5 transition-transform duration-200" 
                                                     :class="{'rotate-180': expandedProducts.includes(product.product_id)}" 
                                                     fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                                </svg>
                                            </button>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right" x-text="formatNumber(product.total_units)"></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right" x-text="formatCurrency(product.total_revenue)"></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right" x-text="formatCurrency(product.avg_price)"></td>
                                </tr>
                                
                                <!-- Expandable daily sales row -->
                                <tr x-show="expandedProducts.includes(product.product_id)" 
                                    x-transition:enter="transition ease-out duration-200"
                                    x-transition:enter-start="opacity-0"
                                    x-transition:enter-end="opacity-100"
                                    x-transition:leave="transition ease-in duration-150"
                                    x-transition:leave-start="opacity-100"
                                    x-transition:leave-end="opacity-0">
                                    <td colspan="4" class="px-6 py-0 bg-gray-50">
                                        <div class="py-4">
                                            <!-- Loading state -->
                                            <div x-show="dailySalesLoading[product.product_id]" class="flex items-center justify-center py-8">  
                                                <svg class="animate-spin h-6 w-6 text-blue-600" fill="none" viewBox="0 0 24 24">
                                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                                </svg>
                                                <span class="ml-3 text-gray-600">Loading daily sales...</span>
                                            </div>
                                            
                                            <!-- Daily sales content -->
                                            <div x-show="!dailySalesLoading[product.product_id] && dailySalesData[product.product_id]" 
                                                 x-html="dailySalesData[product.product_id]">
                                            </div>
                                            
                                            <!-- Error state -->
                                            <div x-show="!dailySalesLoading[product.product_id] && dailySalesError[product.product_id]" 
                                                 class="text-center py-8 text-red-600">
                                                <svg class="mx-auto h-8 w-8 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                </svg>
                                                <p x-text="dailySalesError[product.product_id]"></p>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </template>
                    </table>
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        // Store chart instance outside of Alpine reactive data
        let chartInstance = null;
        
        function categorySalesData() {
            return {
                startDate: '{{ $startDate->format('Y-m-d') }}',
                endDate: '{{ $endDate->format('Y-m-d') }}',
                stats: {
                    total_units: 0,
                    total_revenue: 0,
                    unique_products: 0,
                    total_transactions: 0
                },
                salesData: [],
                filteredProducts: [],
                searchTerm: '',
                dailySales: [],
                loading: false,
                
                // Expandable rows
                expandedProducts: [],
                dailySalesData: {},
                dailySalesRawData: {}, // Store raw data for chart recreation
                dailySalesLoading: {},
                dailySalesError: {},
                productCharts: {}, // Store individual product chart instances
                
                // Sorting
                sortField: 'total_units',
                sortDirection: 'desc',

                init() {
                    this.createChart();
                    this.loadSalesData();
                },

                async loadSalesData() {
                    this.loading = true;
                    try {
                        const response = await fetch(`{{ route('categories.sales.data', $category) }}?start_date=${this.startDate}&end_date=${this.endDate}`);
                        const data = await response.json();
                        
                        this.stats = data.stats || {};
                        this.salesData = data.sales || [];
                        
                        // Ensure all numeric fields are properly parsed
                        this.salesData = this.salesData.map(item => ({
                            ...item,
                            total_units: parseFloat(item.total_units) || 0,
                            total_revenue: parseFloat(item.total_revenue) || 0,
                            avg_price: parseFloat(item.avg_price) || 0
                        }));
                        
                        // Apply initial sorting and filtering
                        this.filterProducts();
                        this.dailySales = data.daily_sales || [];
                        
                        // Update chart with new data
                        this.$nextTick(() => {
                            this.updateChart();
                        });
                    } catch (error) {
                        console.error('Error loading sales data:', error);
                    } finally {
                        this.loading = false;
                    }
                },

                filterProducts() {
                    let filtered = this.salesData;
                    
                    // Apply search filter
                    if (this.searchTerm) {
                        const term = this.searchTerm.toLowerCase();
                        filtered = filtered.filter(p => 
                            p.product_name.toLowerCase().includes(term) || 
                            p.product_code.toLowerCase().includes(term)
                        );
                    }
                    
                    // Apply sorting
                    filtered = this.sortProducts(filtered);
                    
                    this.filteredProducts = filtered;
                },
                
                sortProducts(products) {
                    return [...products].sort((a, b) => {
                        let aVal = a[this.sortField];
                        let bVal = b[this.sortField];
                        
                        // Handle string comparison for product_name
                        if (this.sortField === 'product_name' || this.sortField === 'product_code') {
                            aVal = (aVal || '').toLowerCase();
                            bVal = (bVal || '').toLowerCase();
                            if (this.sortDirection === 'asc') {
                                return aVal > bVal ? 1 : -1;
                            } else {
                                return aVal < bVal ? 1 : -1;
                            }
                        }
                        
                        // Numeric comparison
                        aVal = parseFloat(aVal) || 0;
                        bVal = parseFloat(bVal) || 0;
                        
                        if (this.sortDirection === 'asc') {
                            return aVal - bVal;
                        } else {
                            return bVal - aVal;
                        }
                    });
                },
                
                sortBy(field) {
                    if (this.sortField === field) {
                        this.sortDirection = this.sortDirection === 'asc' ? 'desc' : 'asc';
                    } else {
                        this.sortField = field;
                        this.sortDirection = 'desc';
                    }
                    this.filterProducts();
                },
                
                getSortIcon(field) {
                    if (this.sortField !== field) {
                        return 'text-gray-400';
                    }
                    return this.sortDirection === 'asc' ? 'text-blue-600 rotate-180' : 'text-blue-600';
                },

                createChart() {
                    try {
                        const canvas = document.getElementById('salesChart');
                        if (!canvas) {
                            console.warn('Chart canvas not found');
                            return;
                        }
                        
                        const ctx = canvas.getContext('2d');
                        if (!ctx) {
                            console.warn('Could not get canvas context');
                            return;
                        }
                        
                        // Check if Chart.js is loaded
                        if (typeof Chart === 'undefined') {
                            console.warn('Chart.js not loaded yet');
                            setTimeout(() => this.createChart(), 100);
                            return;
                        }
                        
                        // Clear any existing chart on this canvas
                        const existingChart = Chart.getChart(canvas);
                        if (existingChart) {
                            existingChart.destroy();
                        }
                        
                        // Handle empty data gracefully on initial chart creation
                        let labels, revenueData;
                        
                        if (!this.dailySales || this.dailySales.length === 0) {
                            labels = ['No Data'];
                            revenueData = [0];
                        } else {
                            labels = this.dailySales.map(item => {
                                const date = new Date(item.sale_date);
                                return date.toLocaleDateString('en-GB', { month: 'short', day: 'numeric' });
                            });
                            
                            revenueData = this.dailySales.map(item => parseFloat(item.daily_revenue) || 0);
                        }

                        chartInstance = new Chart(ctx, {
                            type: 'line',
                            data: {
                                labels: labels,
                                datasets: [{
                                    label: 'Daily Revenue',
                                    data: revenueData,
                                    borderColor: 'rgb(59, 130, 246)',
                                    backgroundColor: 'rgba(59, 130, 246, 0.1)',
                                    tension: 0.1
                                }]
                            },
                            options: {
                                responsive: true,
                                maintainAspectRatio: false,
                                plugins: {
                                    legend: {
                                        display: false
                                    },
                                    tooltip: {
                                        callbacks: {
                                            title: function(context) {
                                                // Get the date from the original data
                                                const index = context[0].dataIndex;
                                                const dateStr = this.dailySales[index]?.sale_date;
                                                if (dateStr) {
                                                    const date = new Date(dateStr);
                                                    const dayName = date.toLocaleDateString('en-GB', { weekday: 'long' });
                                                    const formattedDate = date.toLocaleDateString('en-GB', { 
                                                        day: 'numeric',
                                                        month: 'short',
                                                        year: 'numeric'
                                                    });
                                                    return `${dayName}, ${formattedDate}`;
                                                }
                                                return context[0].label;
                                            }.bind(this),
                                            label: function(context) {
                                                return 'Revenue: €' + context.parsed.y.toFixed(2);
                                            }
                                        }
                                    }
                                },
                                scales: {
                                    y: {
                                        beginAtZero: true,
                                        ticks: {
                                            callback: function(value) {
                                                return '€' + value.toFixed(2);
                                            }
                                        }
                                    }
                                }
                            }
                        });
                        
                        console.log('✅ Category chart created successfully');
                        
                    } catch (error) {
                        console.error('❌ Category chart creation error:', error);
                        chartInstance = null;
                    }
                },

                updateChart() {
                    console.log('🔄 updateChart called for category, chart exists:', !!chartInstance, 'dailySales count:', this.dailySales?.length);
                    
                    if (!chartInstance) {
                        console.log('📊 No category chart instance, creating new chart...');
                        this.createChart();
                        return;
                    }
                    
                    try {
                        // Handle empty data gracefully
                        if (!this.dailySales || this.dailySales.length === 0) {
                            console.log('⚠️ No daily sales data to update chart');
                            
                            // Update chart with "No Data" placeholder
                            chartInstance.data.labels = ['No Data'];
                            chartInstance.data.datasets[0].data = [0];
                            chartInstance.update();
                            return;
                        }
                        
                        const labels = this.dailySales.map(item => {
                            const date = new Date(item.sale_date);
                            return date.toLocaleDateString('en-GB', { month: 'short', day: 'numeric' });
                        });
                        
                        const revenueData = this.dailySales.map(item => parseFloat(item.daily_revenue) || 0);

                        console.log('📊 Updating category chart data:', {
                            labels: labels.length,
                            firstLabel: labels[0],
                            lastLabel: labels[labels.length - 1],
                            totalRevenue: revenueData.reduce((a, b) => a + b, 0).toFixed(2)
                        });

                        // Update chart data
                        chartInstance.data.labels = labels;
                        chartInstance.data.datasets[0].data = revenueData;
                        
                        // Force chart update with animation
                        chartInstance.update('active');
                        
                        console.log('✅ Category chart updated successfully with', this.dailySales.length, 'data points');
                        
                    } catch (error) {
                        console.error('❌ Category chart update error:', error);
                        // Try to recreate chart on error
                        try {
                            if (chartInstance) {
                                chartInstance.destroy();
                                chartInstance = null;
                            }
                            this.createChart();
                        } catch (e) {
                            console.error('❌ Failed to recreate category chart:', e);
                        }
                    }
                },

                formatNumber(num) {
                    const value = parseFloat(num) || 0;
                    return new Intl.NumberFormat().format(value);
                },

                formatCurrency(amount) {
                    const value = parseFloat(amount) || 0;
                    return '€' + value.toFixed(2);
                },

                formatDateRange() {
                    const start = new Date(this.startDate);
                    const end = new Date(this.endDate);
                    return `${start.toLocaleDateString()} - ${end.toLocaleDateString()}`;
                },

                getPeriodInfo() {
                    const start = new Date(this.startDate);
                    const end = new Date(this.endDate);
                    const days = Math.ceil((end - start) / (1000 * 60 * 60 * 24)) + 1;
                    return `(${days} days)`;
                },

                navigateWeek(direction) {
                    const start = new Date(this.startDate);
                    const end = new Date(this.endDate);
                    start.setDate(start.getDate() + (direction * 7));
                    end.setDate(end.getDate() + (direction * 7));
                    this.startDate = start.toISOString().split('T')[0];
                    this.endDate = end.toISOString().split('T')[0];
                    this.loadSalesData();
                },

                navigateMonth(direction) {
                    const start = new Date(this.startDate);
                    const end = new Date(this.endDate);
                    start.setMonth(start.getMonth() + direction);
                    end.setMonth(end.getMonth() + direction);
                    this.startDate = start.toISOString().split('T')[0];
                    this.endDate = end.toISOString().split('T')[0];
                    this.loadSalesData();
                },

                toggleDailySales(productId, productCode) {
                    const index = this.expandedProducts.indexOf(productId);
                    
                    if (index > -1) {
                        // Collapse - clean up chart
                        this.expandedProducts.splice(index, 1);
                        this.destroyProductChart(productId);
                    } else {
                        // Expand and load data if not already loaded
                        this.expandedProducts.push(productId);
                        
                        if (!this.dailySalesData[productId] && !this.dailySalesLoading[productId]) {
                            this.loadDailySales(productId, productCode);
                        } else if (this.dailySalesData[productId] && this.dailySalesRawData[productId]) {
                            // Data already exists, create chart immediately
                            this.$nextTick(() => {
                                this.createProductChart(productId, this.dailySalesRawData[productId]);
                            });
                        }
                    }
                },
                
                async loadDailySales(productId, productCode) {
                    this.dailySalesLoading[productId] = true;
                    this.dailySalesError[productId] = null;
                    
                    try {
                        const params = new URLSearchParams({
                            start_date: this.startDate,
                            end_date: this.endDate
                        });
                        
                        const response = await fetch(`{{ route('categories.show', $category) }}/sales/product/${productCode}/daily?${params}`, {
                            headers: {
                                'X-Requested-With': 'XMLHttpRequest',
                                'Accept': 'application/json'
                            }
                        });
                        
                        if (!response.ok) {
                            throw new Error('Failed to load daily sales');
                        }
                        
                        const data = await response.json();
                        
                        if (data.success && data.daily_sales) {
                            this.dailySalesData[productId] = this.formatDailySalesHtml(data, productId);
                            this.dailySalesRawData[productId] = data.daily_sales; // Store raw data
                            
                            // Create chart after HTML is rendered (next tick)
                            this.$nextTick(() => {
                                this.createProductChart(productId, data.daily_sales);
                            });
                        } else {
                            throw new Error(data.error || 'No data available');
                        }
                        
                    } catch (error) {
                        console.error('Error loading daily sales:', error);
                        this.dailySalesError[productId] = error.message;
                    } finally {
                        this.dailySalesLoading[productId] = false;
                    }
                },
                
                formatDailySalesHtml(data, productId) {
                    if (!data.daily_sales || data.daily_sales.length === 0) {
                        return `
                            <div class="text-center py-8 text-gray-500">
                                <p>No daily sales data available for this period.</p>
                            </div>
                        `;
                    }
                    
                    const maxUnits = Math.max(...data.daily_sales.map(d => parseFloat(d.daily_units) || 0));
                    
                    // Helper functions for formatting
                    const formatNum = (num) => {
                        const value = parseFloat(num) || 0;
                        return new Intl.NumberFormat().format(value);
                    };
                    
                    const formatCurr = (amount) => {
                        const value = parseFloat(amount) || 0;
                        return '€' + value.toFixed(2);
                    };
                    
                    let html = `
                        <div class="bg-white rounded-lg border border-gray-200 overflow-hidden">
                            <div class="px-6 py-4 bg-gray-50 border-b border-gray-200">
                                <h4 class="text-lg font-medium text-gray-900">Daily Sales Breakdown</h4>
                                <div class="mt-2 grid grid-cols-2 md:grid-cols-4 gap-4">
                                    <div>
                                        <p class="text-xs text-gray-500 uppercase">Total Units</p>
                                        <p class="text-lg font-semibold text-gray-900">${formatNum(data.summary.total_units)}</p>
                                    </div>
                                    <div>
                                        <p class="text-xs text-gray-500 uppercase">Total Revenue</p>
                                        <p class="text-lg font-semibold text-gray-900">${formatCurr(data.summary.total_revenue)}</p>
                                    </div>
                                    <div>
                                        <p class="text-xs text-gray-500 uppercase">Days with Sales</p>
                                        <p class="text-lg font-semibold text-gray-900">${data.summary.days_with_sales || 0}</p>
                                    </div>
                                    <div>
                                        <p class="text-xs text-gray-500 uppercase">Daily Average</p>
                                        <p class="text-lg font-semibold text-gray-900">${formatNum(data.summary.avg_daily_units)}</p>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Product Daily Sales Chart -->
                            <div class="px-6 py-4 border-b border-gray-200">
                                <h5 class="text-sm font-medium text-gray-700 mb-3">Daily Sales Trend</h5>
                                <div class="relative" style="height: 200px;">
                                    <canvas id="productChart_${productId}"></canvas>
                                </div>
                            </div>
                            
                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Units</th>
                                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Revenue</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Trend</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-200">`;
                    
                    data.daily_sales.forEach((day, index) => {
                        const dayUnits = parseFloat(day.daily_units) || 0;
                        const dayRevenue = parseFloat(day.daily_revenue) || 0;
                        const percentage = maxUnits > 0 ? (dayUnits / maxUnits) * 100 : 0;
                        const date = new Date(day.sale_date);
                        const dateStr = date.toLocaleDateString('en-GB', { 
                            weekday: 'short', 
                            month: 'short', 
                            day: 'numeric' 
                        });
                        
                        html += `
                            <tr class="${index % 2 === 0 ? 'bg-white' : 'bg-gray-50'}">
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">${dateStr}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right font-medium">${formatNum(dayUnits)}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-green-600 text-right font-medium">${formatCurr(dayRevenue)}</td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <div class="flex-1 bg-gray-200 rounded-full h-2 max-w-xs">
                                            <div class="bg-blue-500 h-2 rounded-full" style="width: ${percentage}%"></div>
                                        </div>
                                        <span class="ml-2 text-xs text-gray-500">${Math.round(percentage)}%</span>
                                    </div>
                                </td>
                            </tr>`;
                    });
                    
                    html += `
                                    </tbody>
                                </table>
                            </div>
                        </div>`;
                    
                    return html;
                },
                
                createProductChart(productId, dailySalesData) {
                    try {
                        const canvasId = `productChart_${productId}`;
                        const canvas = document.getElementById(canvasId);
                        if (!canvas) {
                            console.warn(`Product chart canvas not found: ${canvasId}`);
                            return;
                        }
                        
                        const ctx = canvas.getContext('2d');
                        if (!ctx) {
                            console.warn('Could not get canvas context for product chart');
                            return;
                        }
                        
                        // Check if Chart.js is loaded
                        if (typeof Chart === 'undefined') {
                            console.warn('Chart.js not loaded yet for product chart');
                            setTimeout(() => this.createProductChart(productId, dailySalesData), 100);
                            return;
                        }
                        
                        // Clear any existing chart on this canvas
                        const existingChart = Chart.getChart(canvas);
                        if (existingChart) {
                            existingChart.destroy();
                        }
                        
                        // Prepare data
                        let labels, revenueData, unitsData;
                        
                        if (!dailySalesData || dailySalesData.length === 0) {
                            labels = ['No Data'];
                            revenueData = [0];
                            unitsData = [0];
                        } else {
                            labels = dailySalesData.map(item => {
                                const date = new Date(item.sale_date);
                                return date.toLocaleDateString('en-GB', { month: 'short', day: 'numeric' });
                            });
                            
                            revenueData = dailySalesData.map(item => parseFloat(item.daily_revenue) || 0);
                            unitsData = dailySalesData.map(item => parseFloat(item.daily_units) || 0);
                        }
                        
                        // Create chart with simplified configuration for product level
                        const productChart = new Chart(ctx, {
                            type: 'line',
                            data: {
                                labels: labels,
                                datasets: [{
                                    label: 'Revenue (€)',
                                    data: revenueData,
                                    borderColor: 'rgb(59, 130, 246)',
                                    backgroundColor: 'rgba(59, 130, 246, 0.1)',
                                    yAxisID: 'y',
                                    tension: 0.1,
                                    fill: true
                                }, {
                                    label: 'Units Sold',
                                    data: unitsData,
                                    borderColor: 'rgb(16, 185, 129)',
                                    backgroundColor: 'rgba(16, 185, 129, 0.1)',
                                    yAxisID: 'y1',
                                    tension: 0.1,
                                    fill: true
                                }]
                            },
                            options: {
                                responsive: true,
                                maintainAspectRatio: false,
                                interaction: {
                                    mode: 'index',
                                    intersect: false,
                                },
                                plugins: {
                                    legend: {
                                        position: 'top',
                                        labels: {
                                            boxWidth: 12,
                                            font: {
                                                size: 11
                                            }
                                        }
                                    },
                                    tooltip: {
                                        callbacks: {
                                            title: function(context) {
                                                // Get the date from the original data
                                                const index = context[0].dataIndex;
                                                const dateStr = dailySalesData[index]?.sale_date;
                                                if (dateStr) {
                                                    const date = new Date(dateStr);
                                                    const dayName = date.toLocaleDateString('en-GB', { weekday: 'long' });
                                                    const formattedDate = date.toLocaleDateString('en-GB', { 
                                                        day: 'numeric',
                                                        month: 'short',
                                                        year: 'numeric'
                                                    });
                                                    return `${dayName}, ${formattedDate}`;
                                                }
                                                return context[0].label;
                                            },
                                            label: function(context) {
                                                let label = context.dataset.label || '';
                                                if (label) {
                                                    label += ': ';
                                                }
                                                if (context.parsed.y !== null) {
                                                    if (context.dataset.label === 'Revenue (€)') {
                                                        label += '€' + context.parsed.y.toFixed(2);
                                                    } else {
                                                        label += context.parsed.y.toFixed(0);
                                                    }
                                                }
                                                return label;
                                            }
                                        }
                                    }
                                },
                                scales: {
                                    x: {
                                        ticks: {
                                            font: {
                                                size: 10
                                            }
                                        }
                                    },
                                    y: {
                                        type: 'linear',
                                        display: true,
                                        position: 'left',
                                        title: {
                                            display: true,
                                            text: 'Revenue (€)',
                                            font: {
                                                size: 10
                                            }
                                        },
                                        ticks: {
                                            font: {
                                                size: 10
                                            },
                                            callback: function(value) {
                                                return '€' + value.toLocaleString();
                                            }
                                        }
                                    },
                                    y1: {
                                        type: 'linear',
                                        display: true,
                                        position: 'right',
                                        title: {
                                            display: true,
                                            text: 'Units',
                                            font: {
                                                size: 10
                                            }
                                        },
                                        ticks: {
                                            font: {
                                                size: 10
                                            }
                                        },
                                        grid: {
                                            drawOnChartArea: false,
                                        },
                                    }
                                }
                            }
                        });
                        
                        // Store chart instance
                        this.productCharts[productId] = productChart;
                        
                        console.log(`✅ Product chart created successfully for product ${productId}`);
                        
                    } catch (error) {
                        console.error(`❌ Product chart creation error for ${productId}:`, error);
                        
                        // Show error message in chart area
                        const chartContainer = document.getElementById(`productChart_${productId}`)?.closest('.relative');
                        if (chartContainer) {
                            const errorMsg = document.createElement('div');
                            errorMsg.className = 'absolute inset-0 flex items-center justify-center text-center text-red-600 text-sm';
                            errorMsg.innerHTML = `
                                <div>
                                    <p>Chart Error</p>
                                    <p class="text-xs mt-1">Unable to load chart</p>
                                </div>
                            `;
                            chartContainer.appendChild(errorMsg);
                        }
                    }
                },
                
                destroyProductChart(productId) {
                    if (this.productCharts[productId]) {
                        this.productCharts[productId].destroy();
                        delete this.productCharts[productId];
                        console.log(`🗑️ Product chart destroyed for product ${productId}`);
                    }
                },

                setQuickPeriod(period) {
                    const today = new Date();
                    let start, end;
                    
                    switch(period) {
                        case 'today':
                            start = end = today;
                            break;
                        case 'yesterday':
                            start = end = new Date(today.setDate(today.getDate() - 1));
                            break;
                        case 'thisWeek':
                            start = new Date(today.setDate(today.getDate() - today.getDay()));
                            end = new Date();
                            break;
                        case 'lastWeek':
                            start = new Date(today.setDate(today.getDate() - today.getDay() - 7));
                            end = new Date(today.setDate(today.getDate() + 6));
                            break;
                        case 'thisMonth':
                            start = new Date(today.getFullYear(), today.getMonth(), 1);
                            end = new Date();
                            break;
                        case 'lastMonth':
                            start = new Date(today.getFullYear(), today.getMonth() - 1, 1);
                            end = new Date(today.getFullYear(), today.getMonth(), 0);
                            break;
                        case 'last30':
                            end = new Date();
                            start = new Date(today.setDate(today.getDate() - 29));
                            break;
                        default:
                            return;
                    }
                    
                    this.startDate = start.toISOString().split('T')[0];
                    this.endDate = end.toISOString().split('T')[0];
                    this.loadSalesData();
                }
            };
        }
    </script>
    @endpush
</x-admin-layout>