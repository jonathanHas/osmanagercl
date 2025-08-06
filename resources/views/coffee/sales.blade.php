<x-admin-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('Coffee Sales Analytics') }}
            </h2>
        </div>
    </x-slot>

    <div class="py-6" x-data="coffeeSalesData()" x-init="init()">
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
                                    class="p-1.5 text-gray-500 hover:text-amber-600 hover:bg-white rounded transition-colors"
                                    title="Previous Week">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                                </svg>
                            </button>
                            <button @click="navigateWeek(1)" 
                                    class="p-1.5 text-gray-500 hover:text-amber-600 hover:bg-white rounded transition-colors"
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
                                    class="p-1.5 text-gray-500 hover:text-amber-600 hover:bg-white rounded transition-colors"
                                    title="Previous Month">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 19l-7-7 7-7m8 14l-7-7 7-7" />
                                </svg>
                            </button>
                            <button @click="navigateMonth(1)" 
                                    class="p-1.5 text-gray-500 hover:text-amber-600 hover:bg-white rounded transition-colors"
                                    title="Next Month">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 5l7 7-7 7M5 5l7 7-7 7" />
                                </svg>
                            </button>
                        </div>
                        
                        <!-- Quick Periods Dropdown -->
                        <select @change="setQuickPeriod($event.target.value)" 
                                class="text-sm rounded-md border-gray-300 shadow-sm focus:border-amber-500 focus:ring-amber-500">
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
                                   class="text-xs rounded border-gray-300 shadow-sm focus:border-amber-500 focus:ring-amber-500 w-24 sm:w-28">
                            <span class="text-gray-400 hidden sm:inline">–</span>
                            <input type="date" x-model="endDate" @change="loadSalesData()"
                                   class="text-xs rounded border-gray-300 shadow-sm focus:border-amber-500 focus:ring-amber-500 w-24 sm:w-28">
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
                        <div class="p-3 rounded-full bg-amber-100 text-amber-800">
                            <svg class="h-8 w-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z" />
                            </svg>
                        </div>
                        <div class="ml-5">
                            <p class="text-gray-500 text-sm font-medium">Coffee Products Sold</p>
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

            <!-- Daily Sales Chart -->
            <div class="bg-white rounded-lg shadow p-6 mb-6">
                <h3 class="text-lg font-medium text-gray-900 mb-4">Daily Coffee Sales Overview</h3>
                <div class="relative" style="height: 300px;">
                    <canvas id="dailySalesChart"></canvas>
                    <div id="chartLoading" class="hidden absolute inset-0 bg-white bg-opacity-75 flex items-center justify-center">
                        <div class="text-center">
                            <svg class="animate-spin -ml-1 mr-3 h-8 w-8 text-amber-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            <p class="mt-2 text-sm text-gray-600">Loading chart...</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Sales Table -->
            <div class="bg-white rounded-lg shadow overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200">
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                        <h3 class="text-lg font-medium text-gray-900">Coffee Product Sales Details</h3>
                        <div class="flex gap-2">
                            <input type="text" x-model="searchTerm" @input.debounce.500ms="filterSales()" 
                                   placeholder="Search coffee products..." 
                                   class="block rounded-md border-gray-300 shadow-sm focus:border-amber-500 focus:ring-amber-500 sm:text-sm">
                            <button @click="exportSales()" 
                                    class="bg-green-600 text-white px-4 py-2 rounded-md hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2">
                                Export CSV
                            </button>
                        </div>
                    </div>
                </div>
                
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col" class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Product
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    <button @click="sortBy('product_name')" class="group inline-flex items-center">
                                        Coffee Product
                                        <span class="ml-2">
                                            <svg class="w-4 h-4" :class="getSortIcon('product_name')" fill="currentColor" viewBox="0 0 320 512">
                                                <path d="M41 288h238c21.4 0 32.1 25.9 17 41L177 448c-9.4 9.4-24.6 9.4-33.9 0L24 329c-15.1-15.1-4.4-41 17-41z"/>
                                            </svg>
                                        </span>
                                    </button>
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    <button @click="sortBy('category_name')" class="group inline-flex items-center">
                                        Category
                                        <span class="ml-2">
                                            <svg class="w-4 h-4" :class="getSortIcon('category_name')" fill="currentColor" viewBox="0 0 320 512">
                                                <path d="M41 288h238c21.4 0 32.1 25.9 17 41L177 448c-9.4 9.4-24.6 9.4-33.9 0L24 329c-15.1-15.1-4.4-41 17-41z"/>
                                            </svg>
                                        </span>
                                    </button>
                                </th>
                                <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    <button @click="sortBy('total_units')" class="group inline-flex items-center">
                                        Units Sold
                                        <span class="ml-2">
                                            <svg class="w-4 h-4" :class="getSortIcon('total_units')" fill="currentColor" viewBox="0 0 320 512">
                                                <path d="M41 288h238c21.4 0 32.1 25.9 17 41L177 448c-9.4 9.4-24.6 9.4-33.9 0L24 329c-15.1-15.1-4.4-41 17-41z"/>
                                            </svg>
                                        </span>
                                    </button>
                                </th>
                                <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    <button @click="sortBy('total_revenue')" class="group inline-flex items-center">
                                        Revenue
                                        <span class="ml-2">
                                            <svg class="w-4 h-4" :class="getSortIcon('total_revenue')" fill="currentColor" viewBox="0 0 320 512">
                                                <path d="M41 288h238c21.4 0 32.1 25.9 17 41L177 448c-9.4 9.4-24.6 9.4-33.9 0L24 329c-15.1-15.1-4.4-41 17-41z"/>
                                            </svg>
                                        </span>
                                    </button>
                                </th>
                                <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Avg Price
                                </th>
                                <th scope="col" class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Actions
                                </th>
                            </tr>
                        </thead>
                        <!-- Loading state -->
                        <tbody x-show="loading" class="bg-white divide-y divide-gray-200">
                            <tr>
                                <td colspan="7" class="px-6 py-12 text-center">
                                    <div class="flex justify-center items-center">
                                        <svg class="animate-spin h-8 w-8 text-amber-600" fill="none" viewBox="0 0 24 24">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                        </svg>
                                        <span class="ml-3 text-gray-600">Loading coffee sales data...</span>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                            
                        <!-- No data state -->
                        <tbody x-show="!loading && filteredSales.length === 0" class="bg-white divide-y divide-gray-200">
                            <tr>
                                <td colspan="7" class="px-6 py-12 text-center">
                                    <div class="text-gray-500">
                                        <svg class="mx-auto h-12 w-12 text-amber-400 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z" />
                                        </svg>
                                        <h3 class="mt-2 text-sm font-medium text-gray-900">No coffee sales data</h3>
                                        <p class="mt-1 text-sm text-gray-500">No coffee sales found for the selected date range.</p>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                            
                        <!-- Sales data rows -->
                            <template x-for="sale in paginatedSales" :key="sale.product_id">
                                <tbody>
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-3 py-4 whitespace-nowrap">
                                            <div class="w-10 h-10 rounded-lg overflow-hidden bg-amber-100 flex items-center justify-center">
                                                <img :src="'/coffee/product-image/' + sale.product_code" 
                                                     :alt="sale.product_name"
                                                     class="w-full h-full object-cover"
                                                     @@error="$el.style.display='none'; $el.nextElementSibling.style.display='flex'">
                                                <div class="hidden w-full h-full items-center justify-center text-amber-600">
                                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z" />
                                                    </svg>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="flex items-center justify-between">
                                                <div>
                                                    <div class="text-sm font-medium text-gray-900" x-text="sale.product_name"></div>
                                                    <div class="text-sm text-gray-500" x-text="sale.product_code"></div>
                                                </div>
                                                <button @click="toggleDailySales(sale.product_id, sale.product_code)" 
                                                        class="ml-4 p-1 text-gray-400 hover:text-gray-600 transition-colors"
                                                        :title="expandedProducts.includes(sale.product_id) ? 'Hide daily sales' : 'Show daily sales'">
                                                    <svg class="w-5 h-5 transition-transform duration-200" 
                                                         :class="{'rotate-180': expandedProducts.includes(sale.product_id)}" 
                                                         fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                                    </svg>
                                                </button>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full"
                                                  :class="getCategoryClass(sale.category)"
                                                  x-text="sale.category_name">
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right" x-text="formatNumber(sale.total_units)"></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right" x-text="formatCurrency(sale.total_revenue)"></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right" x-text="formatCurrency(sale.avg_price)"></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-center">
                                            <a :href="'/coffee/products?search=' + encodeURIComponent(sale.product_name)" 
                                               class="text-amber-600 hover:text-amber-900">
                                                View Product
                                            </a>
                                        </td>
                                    </tr>
                                    
                                    <!-- Expandable daily sales row -->
                                    <tr x-show="expandedProducts.includes(sale.product_id)" 
                                        x-transition:enter="transition ease-out duration-200"
                                        x-transition:enter-start="opacity-0"
                                        x-transition:enter-end="opacity-100"
                                        x-transition:leave="transition ease-in duration-150"
                                        x-transition:leave-start="opacity-100"
                                        x-transition:leave-end="opacity-0">
                                        <td colspan="7" class="px-6 py-0 bg-gray-50">
                                            <div class="py-4">
                                                <!-- Loading state -->
                                                <div x-show="dailySalesLoading[sale.product_id]" class="flex items-center justify-center py-8">  
                                                    <svg class="animate-spin h-6 w-6 text-amber-600" fill="none" viewBox="0 0 24 24">
                                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                                    </svg>
                                                    <span class="ml-3 text-gray-600">Loading daily sales...</span>
                                                </div>
                                                
                                                <!-- Daily sales content -->
                                                <div x-show="!dailySalesLoading[sale.product_id] && dailySalesData[sale.product_id]" 
                                                     x-html="dailySalesData[sale.product_id]">
                                                </div>
                                                
                                                <!-- Error state -->
                                                <div x-show="!dailySalesLoading[sale.product_id] && dailySalesError[sale.product_id]" 
                                                     class="text-center py-8 text-red-600">
                                                    <svg class="mx-auto h-8 w-8 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                    </svg>
                                                    <p x-text="dailySalesError[sale.product_id]"></p>
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

    <!-- Include Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <script>
        // Store chart instance outside of Alpine reactive data
        let chartInstance = null;
        
        function coffeeSalesData() {
            return {
                startDate: '{{ $startDate->format("Y-m-d") }}',
                endDate: '{{ $endDate->format("Y-m-d") }}',
                searchTerm: '',
                loading: false,
                sales: [],
                filteredSales: [],
                stats: {
                    total_units: 0,
                    total_revenue: 0,
                    unique_products: 0,
                    total_transactions: 0
                },
                
                // Sorting
                sortField: 'total_revenue',
                sortDirection: 'desc',
                
                // No pagination needed for Coffee Fresh
                
                // Expandable rows
                expandedProducts: [],
                dailySalesData: {},
                dailySalesRawData: {}, // Store raw data for chart recreation
                dailySalesLoading: {},
                dailySalesError: {},
                
                // Chart data
                dailySales: [],
                productCharts: {}, // Store individual product chart instances

                init() {
                    console.log('Initial dates:', this.startDate, 'to', this.endDate);
                    this.createChart();
                    this.loadSalesData();
                },
                
                get paginatedSales() {
                    // No pagination - return all filtered sales
                    console.log('📄 Coffee sales (no pagination):', {
                        filteredCount: this.filteredSales.length,
                        sampleItem: this.filteredSales[0]
                    });
                    return this.filteredSales;
                },
                

                async loadSalesData() {
                    this.loading = true;
                    console.log('🚀 Starting loadSalesData for coffee with dates:', this.startDate, 'to', this.endDate);
                    
                    // Show chart loading indicator
                    const chartLoading = document.getElementById('chartLoading');
                    if (chartLoading) {
                        chartLoading.classList.remove('hidden');
                    }
                    
                    try {
                        const params = new URLSearchParams({
                            start_date: this.startDate,
                            end_date: this.endDate,
                            search: this.searchTerm
                        });

                        const url = `{{ route('coffee.sales.data') }}?${params}`;
                        console.log('📡 Coffee API Request URL:', url);

                        const response = await fetch(url, {
                            headers: {
                                'X-Requested-With': 'XMLHttpRequest',
                                'Accept': 'application/json'
                            }
                        });

                        console.log('📊 Coffee Response status:', response.status, response.statusText);

                        if (!response.ok) {
                            const errorText = await response.text();
                            console.error('❌ Coffee server error response:', errorText);
                            throw new Error(`Server error: ${response.status} ${response.statusText}`);
                        }

                        const data = await response.json();
                        console.log('✅ Coffee sales data received:', {
                            salesCount: data.sales?.length || 0,
                            statsUnits: data.stats?.total_units || 0,
                            executionTime: data.performance_info?.execution_time_ms || 'unknown'
                        });
                        
                        this.sales = data.sales || [];
                        this.stats = data.stats || this.stats;
                        this.dailySales = data.daily_sales || [];
                        
                        console.log('📈 Coffee daily sales data for chart:', {
                            count: this.dailySales.length,
                            firstDate: this.dailySales[0]?.sale_date,
                            lastDate: this.dailySales[this.dailySales.length - 1]?.sale_date
                        });
                        
                        // Show helpful message if no data
                        if (this.sales.length === 0) {
                            console.log('ℹ️ No coffee sales data found for date range:', this.startDate, 'to', this.endDate);
                        }
                        
                        this.filterSales();
                        
                        // Ensure chart updates with new data
                        this.$nextTick(() => {
                            this.updateChart();
                        });
                        
                    } catch (error) {
                        console.error('❌ Failed to load coffee sales data:', error);
                        
                        // Show user-friendly error message
                        const errorMsg = error.message.includes('Failed to fetch') 
                            ? 'Unable to connect to server. Please check your connection and try again.'
                            : `Error loading data: ${error.message}`;
                            
                        alert(errorMsg);
                        
                        // Reset data on error
                        this.sales = [];
                        this.stats = {
                            total_units: 0,
                            total_revenue: 0,
                            unique_products: 0,
                            total_transactions: 0
                        };
                    } finally {
                        this.loading = false;
                        console.log('🏁 Finished loading coffee sales data. Loading state:', this.loading);
                        
                        // Hide chart loading indicator
                        const chartLoading = document.getElementById('chartLoading');
                        if (chartLoading) {
                            chartLoading.classList.add('hidden');
                        }
                    }
                },
                
                filterSales() {
                    console.log('Filtering coffee sales. Total sales:', this.sales.length);
                    let filtered = [...this.sales];
                    
                    // Apply search filter
                    if (this.searchTerm) {
                        const search = this.searchTerm.toLowerCase();
                        filtered = filtered.filter(sale => 
                            sale.product_name.toLowerCase().includes(search) ||
                            sale.product_code.toLowerCase().includes(search)
                        );
                    }
                    
                    // Apply sorting
                    filtered.sort((a, b) => {
                        let aVal = a[this.sortField];
                        let bVal = b[this.sortField];
                        
                        // Handle numeric fields (revenue, units, price)
                        if (this.sortField === 'total_revenue' || this.sortField === 'total_units' || this.sortField === 'avg_price') {
                            aVal = parseFloat(aVal) || 0;
                            bVal = parseFloat(bVal) || 0;
                        } else if (typeof aVal === 'string') {
                            // Handle string fields (names)
                            aVal = aVal.toLowerCase();
                            bVal = bVal.toLowerCase();
                        }
                        
                        if (this.sortDirection === 'asc') {
                            return aVal > bVal ? 1 : aVal < bVal ? -1 : 0;
                        } else {
                            return aVal < bVal ? 1 : aVal > bVal ? -1 : 0;
                        }
                    });
                    
                    this.filteredSales = filtered;
                    console.log('Filtered coffee sales:', this.filteredSales.length);
                },
                
                sortBy(field) {
                    if (this.sortField === field) {
                        this.sortDirection = this.sortDirection === 'asc' ? 'desc' : 'asc';
                    } else {
                        this.sortField = field;
                        this.sortDirection = 'desc';
                    }
                    this.filterSales();
                },
                
                getSortIcon(field) {
                    if (this.sortField !== field) {
                        return 'text-gray-400';
                    }
                    return this.sortDirection === 'asc' ? 'text-amber-600 rotate-180' : 'text-amber-600';
                },
                
                getCategoryClass(category) {
                    // Only Coffee Fresh (081) - consistent amber styling
                    return 'bg-amber-100 text-amber-800';
                },

                navigateWeek(direction) {
                    const start = new Date(this.startDate);
                    const end = new Date(this.endDate);
                    const days = direction * 7;
                    
                    start.setDate(start.getDate() + days);
                    end.setDate(end.getDate() + days);
                    
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
                
                getPeriodInfo() {
                    const start = new Date(this.startDate);
                    const end = new Date(this.endDate);
                    const days = Math.ceil((end - start) / (1000 * 60 * 60 * 24)) + 1;
                    
                    if (days === 1) {
                        return '1 day';
                    } else if (days === 7) {
                        return '1 week';  
                    } else if (days >= 28 && days <= 31) {
                        return '1 month';
                    } else {
                        return `${days} days`;
                    }
                },

                setQuickPeriod(period) {
                    if (!period) return;
                    
                    const today = new Date();
                    let start, end;
                    
                    switch (period) {
                        case 'today':
                            start = end = new Date();
                            break;
                        case 'yesterday':
                            start = end = new Date();
                            start.setDate(start.getDate() - 1);
                            end.setDate(end.getDate() - 1);
                            break;
                        case 'thisWeek':
                            // Start of this week (Monday)
                            start = new Date(today);
                            const dayOfWeek = start.getDay();
                            const daysToMonday = dayOfWeek === 0 ? 6 : dayOfWeek - 1;
                            start.setDate(start.getDate() - daysToMonday);
                            end = new Date(start);
                            end.setDate(end.getDate() + 6);
                            break;
                        case 'lastWeek':
                            // Start of last week (Monday)
                            start = new Date(today);
                            const lastWeekDay = start.getDay();
                            const daysToLastMonday = lastWeekDay === 0 ? 13 : lastWeekDay + 6;
                            start.setDate(start.getDate() - daysToLastMonday);
                            end = new Date(start);
                            end.setDate(end.getDate() + 6);
                            break;
                        case 'last30':
                            end = new Date();
                            start = new Date();
                            start.setDate(start.getDate() - 29);
                            break;
                        case 'thisMonth':
                            start = new Date(today.getFullYear(), today.getMonth(), 1);
                            end = new Date(today.getFullYear(), today.getMonth() + 1, 0);
                            break;
                        case 'lastMonth':
                            start = new Date(today.getFullYear(), today.getMonth() - 1, 1);
                            end = new Date(today.getFullYear(), today.getMonth(), 0);
                            break;
                        case 'latest':
                            // Use the default smart dates (most recent 30 days with data)
                            this.startDate = '{{ $startDate->format("Y-m-d") }}';
                            this.endDate = '{{ $endDate->format("Y-m-d") }}';
                            this.loadSalesData();
                            return;
                    }
                    
                    this.startDate = start.toISOString().split('T')[0];
                    this.endDate = end.toISOString().split('T')[0];
                    this.loadSalesData();
                    
                    // Reset the dropdown
                    event.target.value = '';
                },
                
                formatDateRange() {
                    const start = new Date(this.startDate);
                    const end = new Date(this.endDate);
                    
                    const options = { month: 'short', day: 'numeric', year: 'numeric' };
                    
                    if (this.startDate === this.endDate) {
                        return start.toLocaleDateString('en-GB', options);
                    }
                    
                    return `${start.toLocaleDateString('en-GB', { month: 'short', day: 'numeric' })} - ${end.toLocaleDateString('en-GB', options)}`;
                },
                
                exportSales() {
                    const params = new URLSearchParams({
                        start_date: this.startDate,
                        end_date: this.endDate,
                        search: this.searchTerm,
                        format: 'csv'
                    });
                    
                    window.open(`{{ route('coffee.sales.data') }}?${params}`, '_blank');
                },
                
                formatNumber(number) {
                    return new Intl.NumberFormat('en-GB').format(number || 0);
                },
                
                formatCurrency(amount) {
                    return new Intl.NumberFormat('en-GB', {
                        style: 'currency',
                        currency: 'EUR'
                    }).format(amount || 0);
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
                        
                        const response = await fetch(`{{ url('/coffee/sales/product') }}/${productCode}/daily?${params}`, {
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
                    
                    const maxUnits = Math.max(...data.daily_sales.map(d => d.daily_units));
                    
                    let html = `
                        <div class="bg-white rounded-lg border border-gray-200 overflow-hidden">
                            <div class="px-6 py-4 bg-gray-50 border-b border-gray-200">
                                <h4 class="text-lg font-medium text-gray-900">Daily Sales Breakdown</h4>
                                <div class="mt-2 grid grid-cols-2 md:grid-cols-4 gap-4">
                                    <div>
                                        <p class="text-xs text-gray-500 uppercase">Total Units</p>
                                        <p class="text-lg font-semibold text-gray-900">${this.formatNumber(data.summary.total_units)}</p>
                                    </div>
                                    <div>
                                        <p class="text-xs text-gray-500 uppercase">Total Revenue</p>
                                        <p class="text-lg font-semibold text-gray-900">${this.formatCurrency(data.summary.total_revenue)}</p>
                                    </div>
                                    <div>
                                        <p class="text-xs text-gray-500 uppercase">Days with Sales</p>
                                        <p class="text-lg font-semibold text-gray-900">${data.summary.days_with_sales}</p>
                                    </div>
                                    <div>
                                        <p class="text-xs text-gray-500 uppercase">Daily Average</p>
                                        <p class="text-lg font-semibold text-gray-900">${this.formatNumber(data.summary.avg_daily_units)}</p>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Product Daily Sales Chart -->
                            <div class="px-6 py-4 border-b border-gray-200">
                                <h5 class="text-sm font-medium text-gray-700 mb-3">Daily Sales Trend</h5>
                                <div class="relative" style="height: 200px;">
                                    <canvas id="productChart_${productId}"></canvas>
                                    <div id="productChartLoading_${productId}" class="hidden absolute inset-0 bg-white bg-opacity-75 flex items-center justify-center">
                                        <div class="text-center">
                                            <svg class="animate-spin -ml-1 mr-3 h-6 w-6 text-amber-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                            </svg>
                                            <p class="mt-1 text-xs text-gray-600">Loading chart...</p>
                                        </div>
                                    </div>
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
                        const percentage = maxUnits > 0 ? (day.daily_units / maxUnits) * 100 : 0;
                        const date = new Date(day.sale_date);
                        const dateStr = date.toLocaleDateString('en-GB', { 
                            weekday: 'short', 
                            month: 'short', 
                            day: 'numeric' 
                        });
                        
                        html += `
                            <tr class="${index % 2 === 0 ? 'bg-white' : 'bg-gray-50'}">
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">${dateStr}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right font-medium">${this.formatNumber(day.daily_units)}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-green-600 text-right font-medium">${this.formatCurrency(day.daily_revenue)}</td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <div class="flex-1 bg-gray-200 rounded-full h-2 max-w-xs">
                                            <div class="bg-amber-500 h-2 rounded-full" style="width: ${percentage}%"></div>
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
                
                createChart() {
                    try {
                        const canvas = document.getElementById('dailySalesChart');
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
                        let labels, revenueData, unitsData;
                        
                        if (!this.dailySales || this.dailySales.length === 0) {
                            labels = ['No Data'];
                            revenueData = [0];
                            unitsData = [0];
                        } else {
                            labels = this.dailySales.map(item => {
                                const date = new Date(item.sale_date);
                                return date.toLocaleDateString('en-GB', { month: 'short', day: 'numeric' });
                            });
                            
                            revenueData = this.dailySales.map(item => parseFloat(item.daily_revenue) || 0);
                            unitsData = this.dailySales.map(item => parseFloat(item.daily_units) || 0);
                        }

                        chartInstance = new Chart(ctx, {
                            type: 'line',
                            data: {
                                labels: labels,
                                datasets: [{
                                    label: 'Revenue (€)',
                                    data: revenueData,
                                    borderColor: 'rgb(245, 158, 11)',
                                    backgroundColor: 'rgba(245, 158, 11, 0.1)',
                                    yAxisID: 'y',
                                    tension: 0.1
                                }, {
                                    label: 'Units Sold',
                                    data: unitsData,
                                    borderColor: 'rgb(16, 185, 129)',
                                    backgroundColor: 'rgba(16, 185, 129, 0.1)',
                                    yAxisID: 'y1',
                                    tension: 0.1
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
                                                let label = context.dataset.label || '';
                                                if (label) {
                                                    label += ': ';
                                                }
                                                if (context.parsed.y !== null) {
                                                    if (context.dataset.label === 'Revenue (€)') {
                                                        label += new Intl.NumberFormat('en-GB', {
                                                            style: 'currency',
                                                            currency: 'EUR'
                                                        }).format(context.parsed.y);
                                                    } else {
                                                        label += new Intl.NumberFormat('en-GB').format(context.parsed.y);
                                                    }
                                                }
                                                return label;
                                            }
                                        }
                                    }
                                },
                                scales: {
                                    y: {
                                        type: 'linear',
                                        display: true,
                                        position: 'left',
                                        title: {
                                            display: true,
                                            text: 'Revenue (€)'
                                        },
                                        ticks: {
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
                                            text: 'Units Sold'
                                        },
                                        grid: {
                                            drawOnChartArea: false,
                                        },
                                    }
                                }
                            }
                        });
                        
                        console.log('✅ Coffee chart created successfully');
                        
                    } catch (error) {
                        console.error('❌ Coffee chart creation error:', error);
                        
                        // Reset chart instance
                        chartInstance = null;
                        
                        // Show error message in chart area
                        const chartContainer = document.querySelector('#dailySalesChart')?.closest('.bg-white');
                        if (chartContainer) {
                            const errorMsg = document.createElement('div');
                            errorMsg.className = 'text-center text-red-600 p-4';
                            errorMsg.innerHTML = `
                                <p>Chart Error: ${error.message}</p>
                                <p class="text-sm mt-2">Please refresh the page or try a different date range.</p>
                            `;
                            chartContainer.appendChild(errorMsg);
                        }
                    }
                },

                updateChart() {
                    console.log('🔄 updateChart called for coffee, chart exists:', !!chartInstance, 'dailySales count:', this.dailySales?.length);
                    
                    if (!chartInstance) {
                        console.log('📊 No coffee chart instance, creating new chart...');
                        this.createChart();
                        return;
                    }
                    
                    try {
                        // Handle empty data gracefully
                        if (!this.dailySales || this.dailySales.length === 0) {
                            console.log('⚠️ No coffee daily sales data to update chart');
                            
                            // Update chart with "No Data" placeholder
                            chartInstance.data.labels = ['No Data'];
                            chartInstance.data.datasets[0].data = [0];
                            chartInstance.data.datasets[1].data = [0];
                            chartInstance.update();
                            return;
                        }
                        
                        // If chart was created with 'No Data', and we now have data
                        if (chartInstance.data.labels.length === 1 && chartInstance.data.labels[0] === 'No Data' && this.dailySales.length > 0) {
                            console.log('🔄 Coffee chart has placeholder data, updating with real data...');
                        }
                        
                        const labels = this.dailySales.map(item => {
                            const date = new Date(item.sale_date);
                            return date.toLocaleDateString('en-GB', { month: 'short', day: 'numeric' });
                        });
                        
                        const revenueData = this.dailySales.map(item => parseFloat(item.daily_revenue) || 0);
                        const unitsData = this.dailySales.map(item => parseFloat(item.daily_units) || 0);

                        console.log('📊 Updating coffee chart data:', {
                            labels: labels.length,
                            firstLabel: labels[0],
                            lastLabel: labels[labels.length - 1],
                            totalRevenue: revenueData.reduce((a, b) => a + b, 0).toFixed(2),
                            totalUnits: unitsData.reduce((a, b) => a + b, 0)
                        });

                        // Update chart data
                        chartInstance.data.labels = labels;
                        chartInstance.data.datasets[0].data = revenueData;
                        chartInstance.data.datasets[1].data = unitsData;
                        
                        // Force chart update with animation
                        chartInstance.update('active');
                        
                        console.log('✅ Coffee chart updated successfully with', this.dailySales.length, 'data points');
                        
                    } catch (error) {
                        console.error('❌ Coffee chart update error:', error);
                        // Try to recreate chart on error
                        try {
                            if (chartInstance) {
                                chartInstance.destroy();
                                chartInstance = null;
                            }
                            this.createChart();
                        } catch (e) {
                            console.error('❌ Failed to recreate coffee chart:', e);
                        }
                    }
                },
                
                createProductChart(productId, dailySalesData) {
                    try {
                        const canvasId = `productChart_${productId}`;
                        const canvas = document.getElementById(canvasId);
                        if (!canvas) {
                            console.warn(`Coffee product chart canvas not found: ${canvasId}`);
                            return;
                        }
                        
                        const ctx = canvas.getContext('2d');
                        if (!ctx) {
                            console.warn('Could not get canvas context for coffee product chart');
                            return;
                        }
                        
                        // Check if Chart.js is loaded
                        if (typeof Chart === 'undefined') {
                            console.warn('Chart.js not loaded yet for coffee product chart');
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
                                    borderColor: 'rgb(245, 158, 11)',
                                    backgroundColor: 'rgba(245, 158, 11, 0.1)',
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
                                                        label += new Intl.NumberFormat('en-GB', {
                                                            style: 'currency',
                                                            currency: 'EUR'
                                                        }).format(context.parsed.y);
                                                    } else {
                                                        label += new Intl.NumberFormat('en-GB').format(context.parsed.y);
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
                        
                        console.log(`✅ Coffee product chart created successfully for product ${productId}`);
                        
                    } catch (error) {
                        console.error(`❌ Coffee product chart creation error for ${productId}:`, error);
                        
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
                        console.log(`🗑️ Coffee product chart destroyed for product ${productId}`);
                    }
                }
            }
        }
    </script>
</x-admin-layout>