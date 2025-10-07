<x-admin-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Order Review Mockup #1 - Chart-Heavy Approach
        </h2>
    </x-slot>

    <div class="py-6">
        <div class="max-w-none mx-auto px-4 sm:px-6 lg:px-8">

            <!-- Order Header -->
            <div class="bg-white rounded-lg shadow p-6 mb-6">
                <div class="flex justify-between items-center">
                    <div>
                        <h3 class="text-2xl font-bold text-gray-900">Udea Weekly Order</h3>
                        <p class="text-gray-600 mt-1">Delivery: Monday, June 10, 2025 • Generated: Today at 14:35</p>
                    </div>
                    <div class="text-right">
                        <p class="text-sm text-gray-600">Order Total</p>
                        <p class="text-3xl font-bold text-blue-600">€2,847.50</p>
                        <p class="text-sm text-gray-600 mt-1">156 items</p>
                    </div>
                </div>
            </div>

            <!-- Quick Stats -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
                <div class="bg-red-50 rounded-lg shadow p-4 border-l-4 border-red-500">
                    <p class="text-red-600 text-sm font-medium uppercase">Requires Review</p>
                    <p class="text-3xl font-bold text-red-700 mt-1">12</p>
                    <p class="text-red-600 text-xs mt-1">High priority items</p>
                </div>
                <div class="bg-yellow-50 rounded-lg shadow p-4 border-l-4 border-yellow-500">
                    <p class="text-yellow-600 text-sm font-medium uppercase">Standard</p>
                    <p class="text-3xl font-bold text-yellow-700 mt-1">84</p>
                    <p class="text-yellow-600 text-xs mt-1">Quick review needed</p>
                </div>
                <div class="bg-green-50 rounded-lg shadow p-4 border-l-4 border-green-500">
                    <p class="text-green-600 text-sm font-medium uppercase">Auto-Approved</p>
                    <p class="text-3xl font-bold text-green-700 mt-1">60</p>
                    <p class="text-green-600 text-xs mt-1">Ready to order</p>
                </div>
                <div class="bg-blue-50 rounded-lg shadow p-4 border-l-4 border-blue-500">
                    <p class="text-blue-600 text-sm font-medium uppercase">Stock Alert</p>
                    <p class="text-3xl font-bold text-blue-700 mt-1">8</p>
                    <p class="text-blue-600 text-xs mt-1">Low stock items</p>
                </div>
            </div>

            <!-- Filter Tabs -->
            <div class="bg-white rounded-lg shadow mb-6">
                <div class="border-b border-gray-200">
                    <nav class="flex -mb-px px-4" aria-label="Tabs">
                        <button class="border-red-500 text-red-600 whitespace-nowrap py-4 px-6 border-b-2 font-medium text-sm flex items-center">
                            <span class="w-2 h-2 bg-red-500 rounded-full mr-2"></span>
                            Review (12)
                        </button>
                        <button class="border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 whitespace-nowrap py-4 px-6 border-b-2 font-medium text-sm flex items-center">
                            <span class="w-2 h-2 bg-yellow-500 rounded-full mr-2"></span>
                            Standard (84)
                        </button>
                        <button class="border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 whitespace-nowrap py-4 px-6 border-b-2 font-medium text-sm flex items-center">
                            <span class="w-2 h-2 bg-green-500 rounded-full mr-2"></span>
                            Safe (60)
                        </button>
                        <button class="border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 whitespace-nowrap py-4 px-6 border-b-2 font-medium text-sm">
                            All (156)
                        </button>
                    </nav>
                </div>
            </div>

            <!-- Product Cards with Charts -->
            <div class="space-y-4">

                <!-- Product 1 - High Priority -->
                <div class="bg-white rounded-lg shadow-md overflow-hidden border-l-4 border-red-500 hover:shadow-lg transition-shadow">
                    <div class="p-6">
                        <div class="flex items-start justify-between">
                            <!-- Product Info -->
                            <div class="flex-1">
                                <div class="flex items-center mb-3">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800 mr-3">
                                        🔴 Review
                                    </span>
                                    <h4 class="text-lg font-bold text-gray-900">Broccoli, Organic Class I NL</h4>
                                    <span class="ml-3 text-sm text-gray-500">Code: 115</span>
                                </div>

                                <!-- Visual Stats Row -->
                                <div class="grid grid-cols-4 gap-4 mb-4">
                                    <div class="bg-blue-50 rounded p-3">
                                        <p class="text-xs text-blue-600 font-medium uppercase">Current Stock</p>
                                        <p class="text-2xl font-bold text-blue-700">12 kg</p>
                                        <p class="text-xs text-blue-600 mt-1">⚠️ 2 days left</p>
                                    </div>
                                    <div class="bg-green-50 rounded p-3">
                                        <p class="text-xs text-green-600 font-medium uppercase">Weekly Avg</p>
                                        <p class="text-2xl font-bold text-green-700">45 kg</p>
                                        <p class="text-xs text-green-600 mt-1">↗ +15% trend</p>
                                    </div>
                                    <div class="bg-purple-50 rounded p-3">
                                        <p class="text-xs text-purple-600 font-medium uppercase">Suggested</p>
                                        <p class="text-2xl font-bold text-purple-700">5 cases</p>
                                        <p class="text-xs text-purple-600 mt-1">60 kg total</p>
                                    </div>
                                    <div class="bg-amber-50 rounded p-3">
                                        <p class="text-xs text-amber-600 font-medium uppercase">Cost</p>
                                        <p class="text-2xl font-bold text-amber-700">€190.20</p>
                                        <p class="text-xs text-amber-600 mt-1">€3.17/kg</p>
                                    </div>
                                </div>

                                <!-- Sales Chart (4 week trend) -->
                                <div class="bg-gray-50 rounded p-4">
                                    <p class="text-xs font-medium text-gray-700 mb-2">4-Week Sales Trend</p>
                                    <div style="height: 100px; position: relative;">
                                        <canvas id="chart_product_1"></canvas>
                                    </div>
                                </div>
                            </div>

                            <!-- Order Controls -->
                            <div class="ml-6 flex flex-col items-center space-y-3 min-w-[200px]">
                                <div class="text-center">
                                    <p class="text-xs text-gray-600 font-medium uppercase mb-2">Order Quantity</p>
                                    <div class="flex items-center justify-center space-x-2">
                                        <button class="w-10 h-10 bg-red-100 hover:bg-red-200 text-red-700 rounded-full font-bold text-lg">−</button>
                                        <input type="number" value="5" class="w-20 text-center text-2xl font-bold border-2 border-gray-300 rounded-lg py-2">
                                        <button class="w-10 h-10 bg-green-100 hover:bg-green-200 text-green-700 rounded-full font-bold text-lg">+</button>
                                    </div>
                                    <p class="text-sm text-gray-600 mt-2">cases</p>
                                    <p class="text-xs text-gray-500">= 60 kg</p>
                                </div>

                                <button class="w-full bg-blue-600 text-white px-6 py-2 rounded-lg font-medium hover:bg-blue-700">
                                    ✓ Approve
                                </button>
                                <button class="w-full bg-gray-200 text-gray-700 px-6 py-2 rounded-lg text-sm hover:bg-gray-300">
                                    Skip Item
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Product 2 - Standard Priority -->
                <div class="bg-white rounded-lg shadow-md overflow-hidden border-l-4 border-yellow-500 hover:shadow-lg transition-shadow">
                    <div class="p-6">
                        <div class="flex items-start justify-between">
                            <div class="flex-1">
                                <div class="flex items-center mb-3">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800 mr-3">
                                        🟡 Standard
                                    </span>
                                    <h4 class="text-lg font-bold text-gray-900">Avocado, Ready to Eat, Mexico</h4>
                                    <span class="ml-3 text-sm text-gray-500">Code: 203</span>
                                </div>

                                <div class="grid grid-cols-4 gap-4 mb-4">
                                    <div class="bg-blue-50 rounded p-3">
                                        <p class="text-xs text-blue-600 font-medium uppercase">Current Stock</p>
                                        <p class="text-2xl font-bold text-blue-700">32 ea</p>
                                        <p class="text-xs text-blue-600 mt-1">✓ 4 days left</p>
                                    </div>
                                    <div class="bg-green-50 rounded p-3">
                                        <p class="text-xs text-green-600 font-medium uppercase">Weekly Avg</p>
                                        <p class="text-2xl font-bold text-green-700">56 ea</p>
                                        <p class="text-xs text-green-600 mt-1">→ Stable</p>
                                    </div>
                                    <div class="bg-purple-50 rounded p-3">
                                        <p class="text-xs text-purple-600 font-medium uppercase">Suggested</p>
                                        <p class="text-2xl font-bold text-purple-700">3 cases</p>
                                        <p class="text-xs text-purple-600 mt-1">72 each</p>
                                    </div>
                                    <div class="bg-amber-50 rounded p-3">
                                        <p class="text-xs text-amber-600 font-medium uppercase">Cost</p>
                                        <p class="text-2xl font-bold text-amber-700">€86.40</p>
                                        <p class="text-xs text-amber-600 mt-1">€1.20/ea</p>
                                    </div>
                                </div>

                                <div class="bg-gray-50 rounded p-4">
                                    <p class="text-xs font-medium text-gray-700 mb-2">4-Week Sales Trend</p>
                                    <div style="height: 100px; position: relative;">
                                        <canvas id="chart_product_2"></canvas>
                                    </div>
                                </div>
                            </div>

                            <div class="ml-6 flex flex-col items-center space-y-3 min-w-[200px]">
                                <div class="text-center">
                                    <p class="text-xs text-gray-600 font-medium uppercase mb-2">Order Quantity</p>
                                    <div class="flex items-center justify-center space-x-2">
                                        <button class="w-10 h-10 bg-red-100 hover:bg-red-200 text-red-700 rounded-full font-bold text-lg">−</button>
                                        <input type="number" value="3" class="w-20 text-center text-2xl font-bold border-2 border-gray-300 rounded-lg py-2">
                                        <button class="w-10 h-10 bg-green-100 hover:bg-green-200 text-green-700 rounded-full font-bold text-lg">+</button>
                                    </div>
                                    <p class="text-sm text-gray-600 mt-2">cases</p>
                                    <p class="text-xs text-gray-500">= 72 each</p>
                                </div>

                                <button class="w-full bg-blue-600 text-white px-6 py-2 rounded-lg font-medium hover:bg-blue-700">
                                    ✓ Approve
                                </button>
                                <button class="w-full bg-gray-200 text-gray-700 px-6 py-2 rounded-lg text-sm hover:bg-gray-300">
                                    Skip Item
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Product 3 - Safe/Auto-Approved -->
                <div class="bg-white rounded-lg shadow-md overflow-hidden border-l-4 border-green-500 hover:shadow-lg transition-shadow opacity-75">
                    <div class="p-6">
                        <div class="flex items-start justify-between">
                            <div class="flex-1">
                                <div class="flex items-center mb-3">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 mr-3">
                                        🟢 Auto-Approved
                                    </span>
                                    <h4 class="text-lg font-bold text-gray-900">Organic Pasta Penne, 500g</h4>
                                    <span class="ml-3 text-sm text-gray-500">Code: 412</span>
                                </div>

                                <div class="grid grid-cols-4 gap-4 mb-4">
                                    <div class="bg-blue-50 rounded p-3">
                                        <p class="text-xs text-blue-600 font-medium uppercase">Current Stock</p>
                                        <p class="text-2xl font-bold text-blue-700">24 pkg</p>
                                        <p class="text-xs text-blue-600 mt-1">✓ 7 days left</p>
                                    </div>
                                    <div class="bg-green-50 rounded p-3">
                                        <p class="text-xs text-green-600 font-medium uppercase">Weekly Avg</p>
                                        <p class="text-2xl font-bold text-green-700">18 pkg</p>
                                        <p class="text-xs text-green-600 mt-1">→ Stable</p>
                                    </div>
                                    <div class="bg-purple-50 rounded p-3">
                                        <p class="text-xs text-purple-600 font-medium uppercase">Suggested</p>
                                        <p class="text-2xl font-bold text-purple-700">2 cases</p>
                                        <p class="text-xs text-purple-600 mt-1">20 packages</p>
                                    </div>
                                    <div class="bg-amber-50 rounded p-3">
                                        <p class="text-xs text-amber-600 font-medium uppercase">Cost</p>
                                        <p class="text-2xl font-bold text-amber-700">€42.00</p>
                                        <p class="text-xs text-amber-600 mt-1">€2.10/pkg</p>
                                    </div>
                                </div>

                                <div class="bg-gray-50 rounded p-4">
                                    <p class="text-xs font-medium text-gray-700 mb-2">4-Week Sales Trend (Stable Pattern - Auto-Approved)</p>
                                    <div style="height: 100px; position: relative;">
                                        <canvas id="chart_product_3"></canvas>
                                    </div>
                                </div>
                            </div>

                            <div class="ml-6 flex flex-col items-center space-y-3 min-w-[200px]">
                                <div class="text-center opacity-50">
                                    <p class="text-xs text-gray-600 font-medium uppercase mb-2">Order Quantity</p>
                                    <div class="flex items-center justify-center space-x-2">
                                        <button class="w-10 h-10 bg-gray-200 text-gray-400 rounded-full font-bold text-lg cursor-not-allowed">−</button>
                                        <input type="number" value="2" disabled class="w-20 text-center text-2xl font-bold border-2 border-gray-200 rounded-lg py-2 bg-gray-50">
                                        <button class="w-10 h-10 bg-gray-200 text-gray-400 rounded-full font-bold text-lg cursor-not-allowed">+</button>
                                    </div>
                                    <p class="text-sm text-gray-600 mt-2">cases</p>
                                    <p class="text-xs text-gray-500">= 20 pkg</p>
                                </div>

                                <button class="w-full bg-green-600 text-white px-6 py-2 rounded-lg font-medium">
                                    ✓ Approved
                                </button>
                                <button class="w-full bg-gray-200 text-gray-700 px-6 py-2 rounded-lg text-sm hover:bg-gray-300">
                                    Modify
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

            </div>

        </div>
    </div>

    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        // Run once when DOM is loaded
        document.addEventListener('DOMContentLoaded', function() {
            // Prevent multiple executions
            if (window.chartsInitialized) return;
            window.chartsInitialized = true;

            // Hardcoded sales data for charts
            const salesData1 = [35, 42, 48, 45]; // Week 1-4 for Broccoli
            const salesData2 = [52, 58, 54, 56]; // Week 1-4 for Avocado
            const salesData3 = [17, 18, 19, 18]; // Week 1-4 for Pasta (stable)

            // Product 1 Chart - Trending up
            new Chart(document.getElementById('chart_product_1'), {
            type: 'line',
            data: {
                labels: ['Week 1', 'Week 2', 'Week 3', 'Week 4'],
                datasets: [{
                    label: 'Units Sold',
                    data: salesData1,
                    borderColor: 'rgb(239, 68, 68)',
                    backgroundColor: 'rgba(239, 68, 68, 0.1)',
                    tension: 0.3,
                    fill: true,
                    borderWidth: 3
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return context.parsed.y + ' kg sold';
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: { font: { size: 10 } }
                    },
                    x: {
                        ticks: { font: { size: 10 } }
                    }
                }
            }
        });

        // Product 2 Chart - Stable
        new Chart(document.getElementById('chart_product_2'), {
            type: 'line',
            data: {
                labels: ['Week 1', 'Week 2', 'Week 3', 'Week 4'],
                datasets: [{
                    label: 'Units Sold',
                    data: salesData2,
                    borderColor: 'rgb(234, 179, 8)',
                    backgroundColor: 'rgba(234, 179, 8, 0.1)',
                    tension: 0.3,
                    fill: true,
                    borderWidth: 3
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return context.parsed.y + ' each sold';
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: { font: { size: 10 } }
                    },
                    x: {
                        ticks: { font: { size: 10 } }
                    }
                }
            }
        });

        // Product 3 Chart - Very stable (auto-approved)
        new Chart(document.getElementById('chart_product_3'), {
            type: 'line',
            data: {
                labels: ['Week 1', 'Week 2', 'Week 3', 'Week 4'],
                datasets: [{
                    label: 'Units Sold',
                    data: salesData3,
                    borderColor: 'rgb(34, 197, 94)',
                    backgroundColor: 'rgba(34, 197, 94, 0.1)',
                    tension: 0.3,
                    fill: true,
                    borderWidth: 3
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return context.parsed.y + ' pkg sold';
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: { font: { size: 10 } }
                    },
                    x: {
                        ticks: { font: { size: 10 } }
                    }
                }
            }
        });

        }); // End of DOMContentLoaded
    </script>
</x-admin-layout>
