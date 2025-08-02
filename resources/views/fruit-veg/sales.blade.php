<x-admin-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('Fruit & Vegetables Sales') }}
            </h2>
        </div>
    </x-slot>

    <div class="py-6" x-data="salesData()">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            
            <!-- Date Range Controls -->
            <div class="bg-white rounded-lg shadow p-6 mb-6">
                <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                    <div>
                        <h3 class="text-lg font-medium text-gray-900 mb-2">Sales Period (Default: July 1-17, 2025)</h3>
                        <div class="flex flex-col sm:flex-row gap-4">
                            <div>
                                <label for="start_date" class="block text-sm font-medium text-gray-700 mb-1">Start Date</label>
                                <input type="date" id="start_date" x-model="startDate" 
                                       class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                            </div>
                            <div>
                                <label for="end_date" class="block text-sm font-medium text-gray-700 mb-1">End Date</label>
                                <input type="date" id="end_date" x-model="endDate"
                                       class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                            </div>
                            <div class="flex items-end">
                                <button @click="loadSalesData()" 
                                        class="bg-indigo-600 text-white px-4 py-2 rounded-md hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                                    Update
                                </button>
                            </div>
                        </div>
                    </div>
                    <div class="flex gap-2">
                        <button @click="setQuickDate(7)" 
                                class="bg-indigo-600 text-white px-3 py-2 rounded-md hover:bg-indigo-700 text-sm">
                            7 Days
                        </button>
                        <button @click="setQuickDate(14)" 
                                class="bg-gray-100 text-gray-700 px-3 py-2 rounded-md hover:bg-gray-200 text-sm">
                            14 Days
                        </button>
                        <button @click="setQuickDate(30)" 
                                class="bg-gray-100 text-gray-700 px-3 py-2 rounded-md hover:bg-gray-200 text-sm">
                            30 Days
                        </button>
                        <button @click="setRecentDates()" 
                                class="bg-green-100 text-green-700 px-3 py-2 rounded-md hover:bg-green-200 text-sm">
                            Latest Week
                        </button>
                    </div>
                </div>
            </div>

            <!-- Statistics Cards -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                <!-- Total Units -->
                <div class="bg-white rounded-lg shadow p-6">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-blue-100 text-blue-800">
                            <svg class="h-8 w-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 12l3-3 3 3 4-4M8 21l4-4 4 4M3 4h18M4 4h16v12a1 1 0 01-1 1H5a1 1 0 01-1-1V4z" />
                            </svg>
                        </div>
                        <div class="ml-5">
                            <p class="text-gray-500 text-sm font-medium">Total Units Sold</p>
                            <p class="text-2xl font-semibold text-gray-900" x-text="formatNumber(stats.total_units)">{{ number_format($stats['total_units'] ?? 0) }}</p>
                        </div>
                    </div>
                </div>

                <!-- Total Revenue -->
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
                            <p class="text-2xl font-semibold text-gray-900" x-text="formatCurrency(stats.total_revenue)">€{{ number_format($stats['total_revenue'] ?? 0, 2) }}</p>
                        </div>
                    </div>
                </div>

                <!-- Unique Products -->
                <div class="bg-white rounded-lg shadow p-6">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-purple-100 text-purple-800">
                            <svg class="h-8 w-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                            </svg>
                        </div>
                        <div class="ml-5">
                            <p class="text-gray-500 text-sm font-medium">Products Sold</p>
                            <p class="text-2xl font-semibold text-gray-900" x-text="stats.unique_products">{{ $stats['unique_products'] ?? 0 }}</p>
                        </div>
                    </div>
                </div>

                <!-- Total Transactions -->
                <div class="bg-white rounded-lg shadow p-6">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-orange-100 text-orange-800">
                            <svg class="h-8 w-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                            </svg>
                        </div>
                        <div class="ml-5">
                            <p class="text-gray-500 text-sm font-medium">Transactions</p>
                            <p class="text-2xl font-semibold text-gray-900" x-text="stats.total_transactions">{{ $stats['total_transactions'] ?? 0 }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Category Breakdown -->
            @if(isset($stats['category_breakdown']) && count($stats['category_breakdown']) > 0)
            <div class="bg-white rounded-lg shadow p-6 mb-6">
                <h3 class="text-lg font-medium text-gray-900 mb-4">Sales by Category</h3>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    @foreach($stats['category_breakdown'] as $categoryName => $categoryData)
                    <div class="border border-gray-200 rounded-lg p-4">
                        <h4 class="font-medium text-gray-900 mb-2">{{ $categoryName }}</h4>
                        <div class="space-y-1">
                            <div class="flex justify-between">
                                <span class="text-sm text-gray-600">Units:</span>
                                <span class="font-medium">{{ number_format($categoryData['units']) }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-sm text-gray-600">Revenue:</span>
                                <span class="font-medium">€{{ number_format($categoryData['revenue'], 2) }}</span>
                            </div>
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>
            @endif

            <!-- Daily Sales Chart -->
            <div class="bg-white rounded-lg shadow p-6 mb-6">
                <h3 class="text-lg font-medium text-gray-900 mb-4">Daily Sales Overview</h3>
                <div class="relative" style="height: 300px;">
                    <canvas id="dailySalesChart"></canvas>
                    <div id="chartLoading" class="hidden absolute inset-0 bg-white bg-opacity-75 flex items-center justify-center">
                        <div class="text-center">
                            <svg class="animate-spin -ml-1 mr-3 h-8 w-8 text-indigo-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            <p class="mt-2 text-sm text-gray-600">Loading chart...</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Sales Table -->
            <div class="bg-white rounded-lg shadow">
                <div class="px-6 py-4 border-b border-gray-200">
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                        <h3 class="text-lg font-medium text-gray-900">Product Sales Details</h3>
                        <div class="flex gap-2">
                            <input type="text" x-model="searchTerm" @input.debounce.500ms="loadSalesData()" 
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
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Product</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Category</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Units Sold</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Revenue</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Avg Price</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200" id="salesTableBody">
                            <!-- Loading state -->
                            <tr x-show="loading">
                                <td colspan="6" class="px-6 py-12 text-center">
                                    <div class="flex justify-center items-center">
                                        <svg class="animate-spin -ml-1 mr-3 h-5 w-5 text-indigo-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                        </svg>
                                        Loading sales data...
                                    </div>
                                </td>
                            </tr>
                            
                            <!-- No data state -->
                            <tr x-show="!loading && sales.length === 0">
                                <td colspan="6" class="px-6 py-12 text-center">
                                    <div class="text-gray-500">
                                        <svg class="mx-auto h-12 w-12 text-gray-400 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                                        </svg>
                                        <h3 class="mt-2 text-sm font-medium text-gray-900">No sales data</h3>
                                        <p class="mt-1 text-sm text-gray-500">No F&V sales found for the selected date range.</p>
                                        <p class="mt-2 text-xs text-gray-400">Try selecting a wider date range (30+ days) or check if there are recent sales.</p>
                                    </div>
                                </td>
                            </tr>
                            
                            <!-- Sales data -->
                            <template x-for="sale in sales" :key="sale.product_id">
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div>
                                            <div class="text-sm font-medium text-gray-900" x-text="sale.product_name"></div>
                                            <div class="text-sm text-gray-500" x-text="sale.product_code"></div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full"
                                              :class="{
                                                  'bg-green-100 text-green-800': sale.category === 'SUB1',
                                                  'bg-orange-100 text-orange-800': sale.category === 'SUB2',
                                                  'bg-purple-100 text-purple-800': sale.category === 'SUB3',
                                                  'bg-gray-100 text-gray-800': !['SUB1', 'SUB2', 'SUB3'].includes(sale.category)
                                              }"
                                              x-text="sale.category_name">
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900" x-text="formatNumber(sale.total_units)"></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900" x-text="formatCurrency(sale.total_revenue)"></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900" x-text="formatCurrency(sale.avg_price)"></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <a :href="'/fruit-veg/product/' + sale.product_code" 
                                           class="text-indigo-600 hover:text-indigo-900">View Details</a>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Include Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <script>
        function salesData() {
            return {
                startDate: '{{ $startDate->format("Y-m-d") }}',
                endDate: '{{ $endDate->format("Y-m-d") }}',
                searchTerm: '',
                loading: false,
                sales: [],
                stats: @json($stats),
                dailySales: @json($dailySales),
                chart: null,

                init() {
                    console.log('🚀 Initializing Fruit & Veg Sales Dashboard', {
                        initialStartDate: this.startDate,
                        initialEndDate: this.endDate,
                        initialDailySalesCount: this.dailySales?.length || 0,
                        initialStatsUnits: this.stats?.total_units || 0
                    });
                    
                    this.createChart();
                    this.loadInitialData();
                },

                loadInitialData() {
                    // Use server-side data if available
                    @if(isset($initialSalesData) && count($initialSalesData) > 0)
                        this.sales = @json($initialSalesData);
                    @else
                        // Load data via AJAX if no initial data
                        this.loadSalesData();
                    @endif
                },

                setQuickDate(days) {
                    // Use the last known date with F&V data (July 17, 2025) instead of today
                    const end = new Date('2025-07-17');
                    const start = new Date('2025-07-17');
                    start.setDate(end.getDate() - days + 1); // +1 to include end date
                    
                    this.endDate = end.toISOString().split('T')[0];
                    this.startDate = start.toISOString().split('T')[0];
                    
                    console.log(`📅 Quick date range: ${this.startDate} to ${this.endDate} (${days} days)`);
                    this.loadSalesData();
                },

                setRecentDates() {
                    // Set to the most recent week with actual F&V data (July 11-17)
                    this.endDate = '2025-07-17';
                    this.startDate = '2025-07-11';
                    
                    console.log(`📅 Recent sales range: ${this.startDate} to ${this.endDate}`);
                    this.loadSalesData();
                },

                async loadSalesData() {
                    console.log('🔄 Starting loadSalesData...', {
                        startDate: this.startDate,
                        endDate: this.endDate,
                        searchTerm: this.searchTerm
                    });
                    
                    this.loading = true;
                    document.getElementById('chartLoading').classList.remove('hidden');
                    
                    try {
                        const params = new URLSearchParams({
                            start_date: this.startDate,
                            end_date: this.endDate,
                            search: this.searchTerm,
                            limit: 50
                        });

                        const url = `{{ route('fruit-veg.sales.data') }}?${params}`;
                        console.log('📡 Making request to:', url);

                        const response = await fetch(url, {
                            headers: {
                                'X-Requested-With': 'XMLHttpRequest',
                                'Accept': 'application/json'
                            }
                        });

                        console.log('📥 Response status:', response.status, response.statusText);

                        if (!response.ok) {
                            const errorText = await response.text();
                            console.error('❌ Response not OK:', errorText);
                            throw new Error(`Network response was not ok: ${response.status} ${response.statusText}`);
                        }

                        const data = await response.json();
                        console.log('✅ Data received:', {
                            salesCount: data.sales?.length || 0,
                            statsUnits: data.stats?.total_units || 0,
                            dailySalesCount: data.daily_sales?.length || 0
                        });
                        console.log('📊 Daily sales data:', data.daily_sales);
                        console.log('📊 Sample daily sales item:', data.daily_sales?.[0]);
                        
                        // Safely assign data with fallbacks to prevent Alpine.js reactivity issues
                        this.sales = data.sales || [];
                        this.stats = data.stats || {
                            total_units: 0,
                            total_revenue: 0,
                            unique_products: 0,
                            total_transactions: 0,
                            category_breakdown: {}
                        };
                        console.log('📊 BEFORE updating dailySales:', {
                            oldDailySalesLength: this.dailySales?.length || 0,
                            oldSampleDate: this.dailySales?.[0]?.sale_date,
                            newDailySalesLength: data.daily_sales?.length || 0,
                            newSampleDate: data.daily_sales?.[0]?.sale_date
                        });
                        
                        this.dailySales = data.daily_sales || [];
                        
                        console.log('📊 AFTER updating dailySales:', {
                            dailySalesLength: this.dailySales.length,
                            sampleDate: this.dailySales?.[0]?.sale_date,
                            dateRange: `${this.startDate} to ${this.endDate}`
                        });
                        
                        // Show user feedback if no data found
                        if (this.dailySales.length === 0 && this.stats.total_units === 0) {
                            console.log('⚠️ No F&V sales data found for this date range');
                            this.showNoDataMessage();
                        } else {
                            this.hideNoDataMessage();
                        }
                        
                        // Only recreate chart if data actually changed or chart doesn't exist
                        const needsRecreation = !this.chart || 
                            this.chart.data.labels.length !== this.dailySales.length ||
                            (this.dailySales.length > 0 && this.chart.data.labels[0] !== this.formatDateLabel(this.dailySales[0].sale_date));
                        
                        if (needsRecreation) {
                            console.log('📊 Chart needs recreation', {
                                reason: !this.chart ? 'no chart' : 'data changed',
                                dailySalesLength: this.dailySales?.length || 0,
                                dateRange: `${this.startDate} to ${this.endDate}`,
                                sampleData: this.dailySales?.[0]
                            });
                            
                            if (this.chart) {
                                try {
                                    this.chart.destroy();
                                } catch (error) {
                                    console.error('⚠️ Error destroying chart:', error);
                                }
                                this.chart = null;
                                
                                // Wait a moment for Chart.js to clean up properly
                                setTimeout(() => {
                                    this.createChart();
                                }, 100);
                            } else {
                                // No existing chart, create new one immediately
                                this.createChart();
                            }
                        } else {
                            console.log('📊 Chart data unchanged, using simple update instead');
                            // Even if data looks the same, try updating in case the date range changed
                            if (this.chart && this.dailySales.length > 0) {
                                try {
                                    const labels = this.dailySales.map(item => this.formatDateLabel(item.sale_date));
                                    const revenueData = this.dailySales.map(item => item.daily_revenue || 0);
                                    const unitsData = this.dailySales.map(item => item.daily_units || 0);
                                    
                                    this.chart.data.labels = labels;
                                    this.chart.data.datasets[0].data = revenueData;
                                    this.chart.data.datasets[1].data = unitsData;
                                    this.chart.update('none');
                                    
                                    console.log('📊 Chart updated successfully with simple update');
                                } catch (error) {
                                    console.error('⚠️ Simple update failed, will recreate on next change:', error);
                                }
                            }
                        }
                        console.log('🎉 Sales data loaded successfully');
                    } catch (error) {
                        console.error('💥 Error loading sales data:', error);
                        alert(`Failed to load sales data: ${error.message}`);
                    } finally {
                        this.loading = false;
                        document.getElementById('chartLoading').classList.add('hidden');
                        console.log('🏁 loadSalesData finished');
                    }
                },

                createChart() {
                    try {
                        const canvas = document.getElementById('dailySalesChart');
                        if (!canvas) {
                            console.error('❌ Chart canvas element not found');
                            return;
                        }
                        
                        const ctx = canvas.getContext('2d');
                        if (!ctx) {
                            console.error('❌ Cannot get 2D context from canvas');
                            return;
                        }
                        
                        // Check if Chart.js is loaded
                        if (typeof Chart === 'undefined') {
                            console.error('❌ Chart.js not loaded');
                            return;
                        }
                        
                        // Clear any existing chart on this canvas
                        const existingChart = Chart.getChart(canvas);
                        if (existingChart) {
                            console.log('📊 Destroying existing chart instance on canvas');
                            existingChart.destroy();
                        }
                        
                        // Handle empty data gracefully on initial chart creation
                        let labels, revenueData, unitsData;
                        
                        if (!this.dailySales || this.dailySales.length === 0) {
                            console.log('📊 Creating chart with no initial data');
                            labels = ['No Data'];
                            revenueData = [0];
                            unitsData = [0];
                        } else {
                            labels = this.dailySales.map(item => {
                                const date = new Date(item.sale_date);
                                return date.toLocaleDateString('en-GB', { month: 'short', day: 'numeric' });
                            });
                            
                            revenueData = this.dailySales.map(item => item.daily_revenue || 0);
                            unitsData = this.dailySales.map(item => item.daily_units || 0);
                        }

                        console.log('📊 Creating chart with data:', {
                            labelsCount: labels.length,
                            revenueCount: revenueData.length,
                            unitsCount: unitsData.length,
                            sampleLabel: labels[0],
                            canvasId: canvas.id
                        });

                        this.chart = new Chart(ctx, {
                            type: 'line',
                            data: {
                                labels: labels,
                                datasets: [{
                                    label: 'Revenue (€)',
                                    data: revenueData,
                                    borderColor: 'rgb(59, 130, 246)',
                                    backgroundColor: 'rgba(59, 130, 246, 0.1)',
                                    yAxisID: 'y'
                                }, {
                                    label: 'Units Sold',
                                    data: unitsData,
                                    borderColor: 'rgb(16, 185, 129)',
                                    backgroundColor: 'rgba(16, 185, 129, 0.1)',
                                    yAxisID: 'y1'
                                }]
                            },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            interaction: {
                                mode: 'index',
                                intersect: false,
                            },
                            scales: {
                                y: {
                                    type: 'linear',
                                    display: true,
                                    position: 'left',
                                    title: {
                                        display: true,
                                        text: 'Revenue (€)'
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
                    
                        console.log('✅ Chart created successfully', {
                            chartType: this.chart.config.type,
                            datasetsCount: this.chart.data.datasets.length
                        });
                    
                } catch (error) {
                    console.error('💥 Error creating chart:', error);
                    
                    // Reset chart instance
                    this.chart = null;
                    
                    // Show error message in chart area
                    const chartContainer = document.querySelector('#dailySalesChart').closest('.bg-white');
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
                    if (!this.chart) {
                        console.log('⚠️ No chart instance available, creating new chart');
                        this.createChart();
                        return;
                    }
                    
                    try {
                        // Handle empty data gracefully - don't update chart with empty data
                        if (!this.dailySales || this.dailySales.length === 0) {
                            console.log('📊 No daily sales data - skipping chart update to avoid recursion');
                            console.log('📊 Current dailySales value:', this.dailySales);
                            return; // Just return without updating the chart
                        }
                        
                        // If chart was created with 'No Data', recreate it with real data
                        if (this.chart.data.labels.length === 1 && this.chart.data.labels[0] === 'No Data') {
                            console.log('📊 Chart has dummy data, recreating with real data');
                            this.chart.destroy();
                            this.createChart();
                            return;
                        }
                        
                        console.log('📊 About to update chart with dailySales:', this.dailySales.length, 'days');
                        
                        const labels = this.dailySales.map(item => {
                            const date = new Date(item.sale_date);
                            return date.toLocaleDateString('en-GB', { month: 'short', day: 'numeric' });
                        });
                        
                        const revenueData = this.dailySales.map(item => item.daily_revenue || 0);
                        const unitsData = this.dailySales.map(item => item.daily_units || 0);

                        console.log('📊 Updating chart with data:', { 
                            labels: labels.length, 
                            revenue: revenueData.length, 
                            units: unitsData.length,
                            sampleLabel: labels[0],
                            sampleRevenue: revenueData[0],
                            dateRange: `${this.startDate} to ${this.endDate}`
                        });

                        this.chart.data.labels = labels;
                        this.chart.data.datasets[0].data = revenueData;
                        this.chart.data.datasets[1].data = unitsData;
                        
                        console.log('📊 Chart data updated, calling chart.update()');
                        this.chart.update('none'); // Use animation mode 'none' to prevent recursion
                        console.log('📊 Chart update completed');
                        
                    } catch (error) {
                        console.error('💥 Error updating chart:', error);
                        // Don't throw the error, just log it
                    }
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
                    return new Intl.NumberFormat('en-GB').format(number);
                },

                formatCurrency(amount) {
                    return new Intl.NumberFormat('en-GB', {
                        style: 'currency',
                        currency: 'EUR'
                    }).format(amount);
                },

                showNoDataMessage() {
                    // You can enhance this with a toast notification or modal
                    const message = `No F&V sales data found for ${this.startDate} to ${this.endDate}. Try selecting July 1-17, 2025 for available data.`;
                    console.log('📝 User feedback:', message);
                    
                    // Simple alert for now - could be enhanced with better UI
                    if (this.stats.total_units === 0) {
                        // Only show alert if we have truly no data (avoid showing on every empty result during loading)
                        setTimeout(() => {
                            if (this.stats.total_units === 0) {
                                alert(message);
                            }
                        }, 500);
                    }
                },

                hideNoDataMessage() {
                    // Clear any no-data indicators
                    console.log('✅ Data found, hiding no-data message');
                },

                formatDateLabel(dateString) {
                    const date = new Date(dateString);
                    return date.toLocaleDateString('en-GB', { month: 'short', day: 'numeric' });
                }
            }
        }
    </script>
</x-admin-layout>