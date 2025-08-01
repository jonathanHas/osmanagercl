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
                        <h3 class="text-lg font-medium text-gray-900 mb-2">Sales Period (Default: Last 7 Days)</h3>
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
                            Last 7 Days
                        </button>
                        <button @click="setQuickDate(14)" 
                                class="bg-gray-100 text-gray-700 px-3 py-2 rounded-md hover:bg-gray-200 text-sm">
                            Last 14 Days
                        </button>
                        <button @click="setQuickDate(30)" 
                                class="bg-gray-100 text-gray-700 px-3 py-2 rounded-md hover:bg-gray-200 text-sm">
                            Last 30 Days
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
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>
                        <div class="ml-5">
                            <p class="text-gray-500 text-sm font-medium">Total Revenue</p>
                            <p class="text-2xl font-semibold text-gray-900" x-text="formatCurrency(stats.total_revenue)">£{{ number_format($stats['total_revenue'] ?? 0, 2) }}</p>
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
                                <span class="font-medium">£{{ number_format($categoryData['revenue'], 2) }}</span>
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
                    const end = new Date();
                    const start = new Date();
                    start.setDate(end.getDate() - days);
                    
                    this.endDate = end.toISOString().split('T')[0];
                    this.startDate = start.toISOString().split('T')[0];
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
                        
                        // Safely assign data with fallbacks to prevent Alpine.js reactivity issues
                        this.sales = data.sales || [];
                        this.stats = data.stats || {
                            total_units: 0,
                            total_revenue: 0,
                            unique_products: 0,
                            total_transactions: 0,
                            category_breakdown: {}
                        };
                        this.dailySales = data.daily_sales || [];
                        
                        // Only update chart if it exists and is properly initialized
                        if (this.chart && typeof this.chart.update === 'function') {
                            this.updateChart();
                        } else {
                            console.log('⚠️ Chart not ready for update, skipping...');
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
                        const ctx = document.getElementById('dailySalesChart').getContext('2d');
                        
                        // Check if Chart.js is loaded
                        if (typeof Chart === 'undefined') {
                            console.error('❌ Chart.js not loaded');
                            return;
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

                    this.chart = new Chart(ctx, {
                        type: 'line',
                        data: {
                            labels: labels,
                            datasets: [{
                                label: 'Revenue (£)',
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
                                        text: 'Revenue (£)'
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
                    
                    console.log('✅ Chart created successfully');
                    
                } catch (error) {
                    console.error('💥 Error creating chart:', error);
                    // Hide chart container if chart creation fails
                    const chartContainer = document.querySelector('#dailySalesChart').closest('.bg-white');
                    if (chartContainer) {
                        chartContainer.style.display = 'none';
                    }
                }
            },

                updateChart() {
                    if (!this.chart) {
                        console.log('⚠️ No chart instance available');
                        return;
                    }
                    
                    try {
                        // Handle empty data gracefully - don't update chart with empty data
                        if (!this.dailySales || this.dailySales.length === 0) {
                            console.log('📊 No daily sales data - skipping chart update to avoid recursion');
                            console.log('📊 Current dailySales value:', this.dailySales);
                            return; // Just return without updating the chart
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
                            units: unitsData.length 
                        });

                        this.chart.data.labels = labels;
                        this.chart.data.datasets[0].data = revenueData;
                        this.chart.data.datasets[1].data = unitsData;
                        this.chart.update('none'); // Use animation mode 'none' to prevent recursion
                        
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
                        currency: 'GBP'
                    }).format(amount);
                }
            }
        }
    </script>
</x-admin-layout>