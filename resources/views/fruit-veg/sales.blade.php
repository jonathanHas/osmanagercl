<x-admin-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('Fruit & Vegetables Sales') }}
            </h2>
        </div>
    </x-slot>

    <div class="py-6" x-data="salesData()" x-init="init()">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            
            <!-- Date Range Controls -->
            <div class="bg-white rounded-lg shadow p-4 mb-6">
                <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
                    <div class="flex items-center gap-4">
                        <h3 class="text-lg font-medium text-gray-900">Sales Period:</h3>
                        <span class="text-sm text-gray-600 bg-gray-50 px-3 py-1 rounded-md" x-text="formatDateRange()"></span>
                    </div>
                    
                    <div class="flex flex-wrap items-center gap-2">
                        <!-- Quick Periods -->
                        <select @change="setQuickPeriod($event.target.value)" 
                                class="text-sm rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="">Quick Periods</option>
                            <option value="today">Today</option>
                            <option value="yesterday">Yesterday</option>
                            <option value="last7">Last 7 Days</option>
                            <option value="last30">Last 30 Days</option>
                            <option value="thisMonth">This Month</option>
                            <option value="lastMonth">Last Month</option>
                        </select>
                        
                        <!-- Date Inputs -->
                        <input type="date" x-model="startDate" @change="loadSalesData()"
                               class="text-sm rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <span class="text-gray-500">to</span>
                        <input type="date" x-model="endDate" @change="loadSalesData()"
                               class="text-sm rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
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
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
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

            <!-- Sales Table -->
            <div class="bg-white rounded-lg shadow overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200">
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                        <h3 class="text-lg font-medium text-gray-900">Product Sales Details</h3>
                        <div class="flex gap-2">
                            <input type="text" x-model="searchTerm" @input.debounce.500ms="filterSales()" 
                                   placeholder="Search products..." 
                                   class="block rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
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
                                    Image
                                </th>
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
                                        <svg class="animate-spin h-8 w-8 text-indigo-600" fill="none" viewBox="0 0 24 24">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                        </svg>
                                        <span class="ml-3 text-gray-600">Loading sales data...</span>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                            
                        <!-- No data state -->
                        <tbody x-show="!loading && filteredSales.length === 0" class="bg-white divide-y divide-gray-200">
                            <tr>
                                <td colspan="7" class="px-6 py-12 text-center">
                                    <div class="text-gray-500">
                                        <svg class="mx-auto h-12 w-12 text-gray-400 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                                        </svg>
                                        <h3 class="mt-2 text-sm font-medium text-gray-900">No sales data</h3>
                                        <p class="mt-1 text-sm text-gray-500">No F&V sales found for the selected date range.</p>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                            
                        <!-- Sales data rows -->
                            <template x-for="sale in paginatedSales" :key="sale.product_id">
                                <tbody>
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-3 py-4 whitespace-nowrap">
                                            <div class="w-10 h-10 rounded-lg overflow-hidden bg-gray-100 flex items-center justify-center">
                                                <img :src="'/fruit-veg/product-image/' + sale.product_code" 
                                                     :alt="sale.product_name"
                                                     class="w-full h-full object-cover"
                                                     @@error="$el.style.display='none'; $el.nextElementSibling.style.display='flex'">
                                                <div class="hidden w-full h-full items-center justify-center text-gray-400">
                                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
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
                                            <a :href="'/fruit-veg/product/' + sale.product_code" 
                                               class="text-indigo-600 hover:text-indigo-900">
                                                View Details
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
                                                    <svg class="animate-spin h-6 w-6 text-indigo-600" fill="none" viewBox="0 0 24 24">
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
                
                <!-- Pagination -->
                <div x-show="totalPages > 1" class="bg-gray-50 px-4 py-3 flex items-center justify-between sm:px-6">
                    <div class="flex-1 flex justify-between sm:hidden">
                        <button @click="previousPage()" :disabled="currentPage === 1"
                                class="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 disabled:opacity-50">
                            Previous
                        </button>
                        <button @click="nextPage()" :disabled="currentPage === totalPages"
                                class="ml-3 relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 disabled:opacity-50">
                            Next
                        </button>
                    </div>
                    <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
                        <div>
                            <p class="text-sm text-gray-700">
                                Showing <span class="font-medium" x-text="(currentPage - 1) * itemsPerPage + 1"></span>
                                to <span class="font-medium" x-text="Math.min(currentPage * itemsPerPage, filteredSales.length)"></span>
                                of <span class="font-medium" x-text="filteredSales.length"></span> results
                            </p>
                        </div>
                        <div>
                            <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px" aria-label="Pagination">
                                <button @click="previousPage()" :disabled="currentPage === 1"
                                        class="relative inline-flex items-center px-2 py-2 rounded-l-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50 disabled:opacity-50">
                                    <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M12.707 5.293a1 1 0 010 1.414L9.414 10l3.293 3.293a1 1 0 01-1.414 1.414l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 0z" clip-rule="evenodd" />
                                    </svg>
                                </button>
                                
                                <template x-for="page in visiblePages" :key="page">
                                    <button @click="goToPage(page)"
                                            :class="{'bg-indigo-50 border-indigo-500 text-indigo-600': page === currentPage,
                                                     'bg-white border-gray-300 text-gray-500 hover:bg-gray-50': page !== currentPage}"
                                            class="relative inline-flex items-center px-4 py-2 border text-sm font-medium"
                                            x-text="page">
                                    </button>
                                </template>
                                
                                <button @click="nextPage()" :disabled="currentPage === totalPages"
                                        class="relative inline-flex items-center px-2 py-2 rounded-r-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50 disabled:opacity-50">
                                    <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd" />
                                    </svg>
                                </button>
                            </nav>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function salesData() {
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
                
                // Pagination
                currentPage: 1,
                itemsPerPage: 20,
                
                // Expandable rows
                expandedProducts: [],
                dailySalesData: {},
                dailySalesLoading: {},
                dailySalesError: {},

                init() {
                    console.log('Initial dates:', this.startDate, 'to', this.endDate);
                    this.loadSalesData();
                },
                
                get paginatedSales() {
                    const start = (this.currentPage - 1) * this.itemsPerPage;
                    const end = start + this.itemsPerPage;
                    const paginated = this.filteredSales.slice(start, end);
                    console.log('📄 Paginated sales:', {
                        currentPage: this.currentPage,
                        itemsPerPage: this.itemsPerPage,
                        filteredCount: this.filteredSales.length,
                        paginatedCount: paginated.length,
                        sampleItem: paginated[0]
                    });
                    return paginated;
                },
                
                get totalPages() {
                    return Math.ceil(this.filteredSales.length / this.itemsPerPage);
                },
                
                get visiblePages() {
                    const pages = [];
                    const maxVisible = 7;
                    const halfVisible = Math.floor(maxVisible / 2);
                    
                    let start = Math.max(1, this.currentPage - halfVisible);
                    let end = Math.min(this.totalPages, start + maxVisible - 1);
                    
                    if (end - start < maxVisible - 1) {
                        start = Math.max(1, end - maxVisible + 1);
                    }
                    
                    for (let i = start; i <= end; i++) {
                        pages.push(i);
                    }
                    
                    return pages;
                },
                
                previousPage() {
                    if (this.currentPage > 1) {
                        this.currentPage--;
                    }
                },
                
                nextPage() {
                    if (this.currentPage < this.totalPages) {
                        this.currentPage++;
                    }
                },
                
                goToPage(page) {
                    this.currentPage = page;
                },

                async loadSalesData() {
                    this.loading = true;
                    console.log('🚀 Starting loadSalesData with dates:', this.startDate, 'to', this.endDate);
                    
                    try {
                        const params = new URLSearchParams({
                            start_date: this.startDate,
                            end_date: this.endDate,
                            search: this.searchTerm
                        });

                        const url = `{{ route('fruit-veg.sales.data') }}?${params}`;
                        console.log('📡 API Request URL:', url);

                        const response = await fetch(url, {
                            headers: {
                                'X-Requested-With': 'XMLHttpRequest',
                                'Accept': 'application/json'
                            }
                        });

                        console.log('📊 Response status:', response.status, response.statusText);

                        if (!response.ok) {
                            const errorText = await response.text();
                            console.error('❌ Server error response:', errorText);
                            throw new Error(`Server error: ${response.status} ${response.statusText}`);
                        }

                        const data = await response.json();
                        console.log('✅ Sales data received:', {
                            salesCount: data.sales?.length || 0,
                            statsUnits: data.stats?.total_units || 0,
                            executionTime: data.performance_info?.execution_time_ms || 'unknown'
                        });
                        
                        this.sales = data.sales || [];
                        this.stats = data.stats || this.stats;
                        
                        // Show helpful message if no data
                        if (this.sales.length === 0) {
                            console.log('ℹ️ No sales data found for date range:', this.startDate, 'to', this.endDate);
                        }
                        
                        this.filterSales();
                        
                    } catch (error) {
                        console.error('❌ Failed to load sales data:', error);
                        
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
                        console.log('🏁 Finished loading sales data. Loading state:', this.loading);
                    }
                },
                
                filterSales() {
                    console.log('Filtering sales. Total sales:', this.sales.length);
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
                        
                        if (typeof aVal === 'string') {
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
                    console.log('Filtered sales:', this.filteredSales.length);
                    this.currentPage = 1; // Reset to first page when filtering
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
                    return this.sortDirection === 'asc' ? 'text-indigo-600 rotate-180' : 'text-indigo-600';
                },
                
                getCategoryClass(category) {
                    const classes = {
                        'SUB1': 'bg-green-100 text-green-800',
                        'SUB2': 'bg-orange-100 text-orange-800',
                        'SUB3': 'bg-purple-100 text-purple-800'
                    };
                    return classes[category] || 'bg-gray-100 text-gray-800';
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
                        case 'last7':
                            end = new Date();
                            start = new Date();
                            start.setDate(start.getDate() - 6);
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
                    }
                    
                    this.startDate = start.toISOString().split('T')[0];
                    this.endDate = end.toISOString().split('T')[0];
                    this.loadSalesData();
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
                    
                    window.open(`{{ route('fruit-veg.sales.data') }}?${params}`, '_blank');
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
                        // Collapse
                        this.expandedProducts.splice(index, 1);
                    } else {
                        // Expand and load data if not already loaded
                        this.expandedProducts.push(productId);
                        
                        if (!this.dailySalesData[productId] && !this.dailySalesLoading[productId]) {
                            this.loadDailySales(productId, productCode);
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
                        
                        const response = await fetch(`{{ url('/fruit-veg/sales/product') }}/${productCode}/daily?${params}`, {
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
                            this.dailySalesData[productId] = this.formatDailySalesHtml(data);
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
                
                formatDailySalesHtml(data) {
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
                                            <div class="bg-indigo-500 h-2 rounded-full" style="width: ${percentage}%"></div>
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
                }
            }
        }
    </script>
</x-admin-layout>