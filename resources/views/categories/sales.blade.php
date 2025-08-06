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
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Product</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Units Sold</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Revenue</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Avg Price</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <template x-for="product in filteredProducts" :key="product.product_code">
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4">
                                        <div>
                                            <div class="text-sm font-medium text-gray-900" x-text="product.product_name"></div>
                                            <div class="text-sm text-gray-500" x-text="product.product_code"></div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 text-right text-sm text-gray-900" x-text="formatNumber(product.total_units)"></td>
                                    <td class="px-6 py-4 text-right text-sm text-gray-900" x-text="formatCurrency(product.total_revenue)"></td>
                                    <td class="px-6 py-4 text-right text-sm text-gray-900" x-text="formatCurrency(product.avg_price)"></td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                    <div x-show="filteredProducts.length === 0" class="text-center py-8">
                        <p class="text-gray-500">No products found</p>
                    </div>
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
                        this.filteredProducts = this.salesData;
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
                    if (!this.searchTerm) {
                        this.filteredProducts = this.salesData;
                        return;
                    }
                    const term = this.searchTerm.toLowerCase();
                    this.filteredProducts = this.salesData.filter(p => 
                        p.product_name.toLowerCase().includes(term) || 
                        p.product_code.toLowerCase().includes(term)
                    );
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
                    return new Intl.NumberFormat().format(num || 0);
                },

                formatCurrency(amount) {
                    return '€' + (amount || 0).toFixed(2);
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