<x-admin-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Order Review Mockup #3 - Visual Dashboard Approach
        </h2>
    </x-slot>

    <div class="py-6">
        <div class="max-w-none mx-auto px-4 sm:px-6 lg:px-8">

            <!-- Dashboard Header with Big Visual Stats -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
                <!-- Main Order Card -->
                <div class="lg:col-span-2 bg-gradient-to-br from-blue-600 to-blue-800 rounded-xl shadow-xl p-8 text-white">
                    <div class="flex justify-between items-start">
                        <div>
                            <h3 class="text-3xl font-bold mb-2">Udea Weekly Order</h3>
                            <p class="text-blue-100 text-lg">Delivery: Monday, June 10, 2025</p>
                        </div>
                        <div class="text-right">
                            <p class="text-blue-200 text-sm uppercase tracking-wide">Order Total</p>
                            <p class="text-5xl font-bold mt-1">€2,847</p>
                        </div>
                    </div>
                    <div class="grid grid-cols-3 gap-4 mt-6">
                        <div class="bg-white bg-opacity-20 rounded-lg p-4">
                            <p class="text-blue-100 text-xs uppercase">Total Items</p>
                            <p class="text-3xl font-bold mt-1">156</p>
                        </div>
                        <div class="bg-white bg-opacity-20 rounded-lg p-4">
                            <p class="text-blue-100 text-xs uppercase">Progress</p>
                            <p class="text-3xl font-bold mt-1">12%</p>
                        </div>
                        <div class="bg-white bg-opacity-20 rounded-lg p-4">
                            <p class="text-blue-100 text-xs uppercase">Completion</p>
                            <p class="text-3xl font-bold mt-1">4/156</p>
                        </div>
                    </div>
                </div>

                <!-- Priority Breakdown -->
                <div class="bg-white rounded-xl shadow-xl p-6">
                    <h4 class="text-lg font-bold text-gray-900 mb-4">Review Priority</h4>

                    <!-- Priority Bars -->
                    <div class="space-y-4">
                        <div>
                            <div class="flex justify-between mb-2">
                                <span class="text-sm font-medium text-red-700">🔴 Review</span>
                                <span class="text-sm font-bold text-red-700">12 items</span>
                            </div>
                            <div class="w-full bg-red-100 rounded-full h-3">
                                <div class="bg-red-600 h-3 rounded-full" style="width: 8%"></div>
                            </div>
                        </div>

                        <div>
                            <div class="flex justify-between mb-2">
                                <span class="text-sm font-medium text-yellow-700">🟡 Standard</span>
                                <span class="text-sm font-bold text-yellow-700">84 items</span>
                            </div>
                            <div class="w-full bg-yellow-100 rounded-full h-3">
                                <div class="bg-yellow-500 h-3 rounded-full" style="width: 54%"></div>
                            </div>
                        </div>

                        <div>
                            <div class="flex justify-between mb-2">
                                <span class="text-sm font-medium text-green-700">🟢 Safe</span>
                                <span class="text-sm font-bold text-green-700">60 items</span>
                            </div>
                            <div class="w-full bg-green-100 rounded-full h-3">
                                <div class="bg-green-600 h-3 rounded-full" style="width: 38%"></div>
                            </div>
                        </div>
                    </div>

                    <div class="mt-6 pt-6 border-t border-gray-200">
                        <div class="flex items-center justify-between text-sm">
                            <span class="text-gray-600">Stock Alerts</span>
                            <span class="px-3 py-1 bg-red-100 text-red-700 rounded-full font-bold">8 critical</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Quick Filter Pills -->
            <div class="flex items-center justify-between mb-6">
                <div class="flex space-x-2">
                    <button class="px-6 py-3 bg-red-600 text-white rounded-full font-medium shadow-lg">
                        Review Items (12)
                    </button>
                    <button class="px-6 py-3 bg-white text-gray-700 rounded-full font-medium shadow hover:shadow-lg">
                        Standard (84)
                    </button>
                    <button class="px-6 py-3 bg-white text-gray-700 rounded-full font-medium shadow hover:shadow-lg">
                        Safe Items (60)
                    </button>
                    <button class="px-6 py-3 bg-white text-gray-700 rounded-full font-medium shadow hover:shadow-lg">
                        All Items
                    </button>
                </div>
                <button class="px-6 py-3 bg-green-600 text-white rounded-full font-medium shadow-lg hover:bg-green-700">
                    ✓ Approve All Safe
                </button>
            </div>

            <!-- Product Grid Cards -->
            <div class="grid grid-cols-1 gap-4">

                <!-- Product Card 1 - Critical Stock -->
                <div class="bg-white rounded-xl shadow-lg overflow-hidden hover:shadow-xl transition-shadow">
                    <div class="p-6">
                        <div class="flex gap-6">

                            <!-- Product Info & Visuals -->
                            <div class="flex-1">
                                <!-- Header -->
                                <div class="flex items-start justify-between mb-4">
                                    <div class="flex-1">
                                        <div class="flex items-center gap-3 mb-2">
                                            <span class="w-3 h-3 bg-red-500 rounded-full animate-pulse"></span>
                                            <h4 class="text-xl font-bold text-gray-900">Broccoli, Organic Class I NL</h4>
                                            <span class="px-3 py-1 bg-red-100 text-red-700 rounded-full text-xs font-bold uppercase">Review</span>
                                        </div>
                                        <p class="text-gray-600">Code: 115 • €3.17/kg • Case: 12kg</p>
                                    </div>
                                </div>

                                <!-- Visual Metrics Grid -->
                                <div class="grid grid-cols-5 gap-3 mb-4">
                                    <!-- Stock Gauge -->
                                    <div class="bg-gradient-to-br from-red-50 to-red-100 rounded-lg p-4 text-center">
                                        <div class="relative inline-block">
                                            <svg class="w-24 h-24">
                                                <circle cx="48" cy="48" r="40" fill="none" stroke="#fee2e2" stroke-width="8"/>
                                                <circle cx="48" cy="48" r="40" fill="none" stroke="#dc2626" stroke-width="8"
                                                        stroke-dasharray="251.2" stroke-dashoffset="188.4"
                                                        transform="rotate(-90 48 48)" stroke-linecap="round"/>
                                            </svg>
                                            <div class="absolute inset-0 flex items-center justify-center flex-col">
                                                <span class="text-2xl font-bold text-red-700">27%</span>
                                            </div>
                                        </div>
                                        <p class="text-xs font-medium text-red-700 mt-2 uppercase">Stock Level</p>
                                        <p class="text-lg font-bold text-red-900">12 kg</p>
                                        <p class="text-xs text-red-600">⚠️ 2 days left</p>
                                    </div>

                                    <!-- Sales Chart -->
                                    <div class="col-span-3 bg-gray-50 rounded-lg p-4">
                                        <p class="text-xs font-medium text-gray-700 mb-2 uppercase">4-Week Sales Trend</p>
                                        <div style="height: 120px; position: relative;">
                                            <canvas id="trend_1"></canvas>
                                        </div>
                                        <div class="flex justify-between mt-2 text-xs text-gray-600">
                                            <span>Week 1: 35kg</span>
                                            <span>Week 2: 42kg</span>
                                            <span>Week 3: 48kg</span>
                                            <span>Week 4: 45kg</span>
                                        </div>
                                        <div class="mt-2 flex justify-between">
                                            <span class="text-sm font-medium text-gray-700">Weekly Average:</span>
                                            <span class="text-sm font-bold text-green-700">45 kg ↗ +15%</span>
                                        </div>
                                    </div>

                                    <!-- Suggestion -->
                                    <div class="bg-gradient-to-br from-purple-50 to-purple-100 rounded-lg p-4 text-center flex flex-col justify-center">
                                        <p class="text-xs font-medium text-purple-700 uppercase mb-1">Suggested</p>
                                        <p class="text-4xl font-bold text-purple-900">5</p>
                                        <p class="text-sm font-medium text-purple-700">cases</p>
                                        <p class="text-xs text-purple-600 mt-2">60 kg total</p>
                                        <div class="mt-2 pt-2 border-t border-purple-200">
                                            <p class="text-lg font-bold text-purple-900">€190.20</p>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Order Controls -->
                            <div class="w-64 bg-gray-50 rounded-lg p-6 flex flex-col items-center justify-center space-y-4">
                                <div class="text-center w-full">
                                    <p class="text-xs font-bold text-gray-600 uppercase tracking-wide mb-3">Order Quantity</p>
                                    <div class="bg-white rounded-lg p-4 shadow-inner">
                                        <div class="flex items-center justify-center gap-3 mb-3">
                                            <button class="w-12 h-12 bg-red-500 hover:bg-red-600 text-white rounded-lg font-bold text-2xl shadow">−</button>
                                            <input type="number" value="5" class="w-20 text-center text-4xl font-bold border-0 bg-transparent">
                                            <button class="w-12 h-12 bg-green-500 hover:bg-green-600 text-white rounded-lg font-bold text-2xl shadow">+</button>
                                        </div>
                                        <p class="text-sm text-gray-600 font-medium">cases</p>
                                        <p class="text-xs text-gray-500">= 60 kg</p>
                                    </div>
                                </div>

                                <div class="w-full space-y-2">
                                    <button class="w-full bg-blue-600 hover:bg-blue-700 text-white px-6 py-3 rounded-lg font-bold shadow-lg text-lg">
                                        ✓ Approve
                                    </button>
                                    <button class="w-full bg-white hover:bg-gray-100 text-gray-700 px-6 py-2 rounded-lg font-medium shadow">
                                        Reset to Suggested
                                    </button>
                                    <button class="w-full bg-white hover:bg-gray-100 text-gray-700 px-6 py-2 rounded-lg font-medium shadow">
                                        Skip Item
                                    </button>
                                </div>
                            </div>

                        </div>
                    </div>
                </div>

                <!-- Product Card 2 - Standard -->
                <div class="bg-white rounded-xl shadow-lg overflow-hidden hover:shadow-xl transition-shadow">
                    <div class="p-6">
                        <div class="flex gap-6">

                            <div class="flex-1">
                                <div class="flex items-start justify-between mb-4">
                                    <div class="flex-1">
                                        <div class="flex items-center gap-3 mb-2">
                                            <span class="w-3 h-3 bg-yellow-500 rounded-full"></span>
                                            <h4 class="text-xl font-bold text-gray-900">Avocado, Ready to Eat, Mexico</h4>
                                            <span class="px-3 py-1 bg-yellow-100 text-yellow-700 rounded-full text-xs font-bold uppercase">Standard</span>
                                        </div>
                                        <p class="text-gray-600">Code: 203 • €1.20/ea • Case: 24 each</p>
                                    </div>
                                </div>

                                <div class="grid grid-cols-5 gap-3 mb-4">
                                    <!-- Stock Gauge -->
                                    <div class="bg-gradient-to-br from-blue-50 to-blue-100 rounded-lg p-4 text-center">
                                        <div class="relative inline-block">
                                            <svg class="w-24 h-24">
                                                <circle cx="48" cy="48" r="40" fill="none" stroke="#dbeafe" stroke-width="8"/>
                                                <circle cx="48" cy="48" r="40" fill="none" stroke="#2563eb" stroke-width="8"
                                                        stroke-dasharray="251.2" stroke-dashoffset="108"
                                                        transform="rotate(-90 48 48)" stroke-linecap="round"/>
                                            </svg>
                                            <div class="absolute inset-0 flex items-center justify-center flex-col">
                                                <span class="text-2xl font-bold text-blue-700">57%</span>
                                            </div>
                                        </div>
                                        <p class="text-xs font-medium text-blue-700 mt-2 uppercase">Stock Level</p>
                                        <p class="text-lg font-bold text-blue-900">32 ea</p>
                                        <p class="text-xs text-blue-600">✓ 4 days left</p>
                                    </div>

                                    <!-- Sales Chart -->
                                    <div class="col-span-3 bg-gray-50 rounded-lg p-4">
                                        <p class="text-xs font-medium text-gray-700 mb-2 uppercase">4-Week Sales Trend</p>
                                        <div style="height: 120px; position: relative;">
                                            <canvas id="trend_2"></canvas>
                                        </div>
                                        <div class="flex justify-between mt-2 text-xs text-gray-600">
                                            <span>W1: 52</span>
                                            <span>W2: 58</span>
                                            <span>W3: 54</span>
                                            <span>W4: 56</span>
                                        </div>
                                        <div class="mt-2 flex justify-between">
                                            <span class="text-sm font-medium text-gray-700">Weekly Average:</span>
                                            <span class="text-sm font-bold text-gray-700">56 each → Stable</span>
                                        </div>
                                    </div>

                                    <!-- Suggestion -->
                                    <div class="bg-gradient-to-br from-purple-50 to-purple-100 rounded-lg p-4 text-center flex flex-col justify-center">
                                        <p class="text-xs font-medium text-purple-700 uppercase mb-1">Suggested</p>
                                        <p class="text-4xl font-bold text-purple-900">3</p>
                                        <p class="text-sm font-medium text-purple-700">cases</p>
                                        <p class="text-xs text-purple-600 mt-2">72 each</p>
                                        <div class="mt-2 pt-2 border-t border-purple-200">
                                            <p class="text-lg font-bold text-purple-900">€86.40</p>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="w-64 bg-gray-50 rounded-lg p-6 flex flex-col items-center justify-center space-y-4">
                                <div class="text-center w-full">
                                    <p class="text-xs font-bold text-gray-600 uppercase tracking-wide mb-3">Order Quantity</p>
                                    <div class="bg-white rounded-lg p-4 shadow-inner">
                                        <div class="flex items-center justify-center gap-3 mb-3">
                                            <button class="w-12 h-12 bg-red-500 hover:bg-red-600 text-white rounded-lg font-bold text-2xl shadow">−</button>
                                            <input type="number" value="3" class="w-20 text-center text-4xl font-bold border-0 bg-transparent">
                                            <button class="w-12 h-12 bg-green-500 hover:bg-green-600 text-white rounded-lg font-bold text-2xl shadow">+</button>
                                        </div>
                                        <p class="text-sm text-gray-600 font-medium">cases</p>
                                        <p class="text-xs text-gray-500">= 72 each</p>
                                    </div>
                                </div>

                                <div class="w-full space-y-2">
                                    <button class="w-full bg-blue-600 hover:bg-blue-700 text-white px-6 py-3 rounded-lg font-bold shadow-lg text-lg">
                                        ✓ Approve
                                    </button>
                                    <button class="w-full bg-white hover:bg-gray-100 text-gray-700 px-6 py-2 rounded-lg font-medium shadow">
                                        Reset
                                    </button>
                                    <button class="w-full bg-white hover:bg-gray-100 text-gray-700 px-6 py-2 rounded-lg font-medium shadow">
                                        Skip
                                    </button>
                                </div>
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

            // Product 1 Chart
            new Chart(document.getElementById('trend_1'), {
            type: 'bar',
            data: {
                labels: ['Week 1', 'Week 2', 'Week 3', 'Week 4'],
                datasets: [{
                    label: 'Sales (kg)',
                    data: [35, 42, 48, 45],
                    backgroundColor: [
                        'rgba(239, 68, 68, 0.7)',
                        'rgba(239, 68, 68, 0.8)',
                        'rgba(239, 68, 68, 0.9)',
                        'rgba(239, 68, 68, 1)'
                    ],
                    borderColor: 'rgb(239, 68, 68)',
                    borderWidth: 2
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

        // Product 2 Chart
        new Chart(document.getElementById('trend_2'), {
            type: 'bar',
            data: {
                labels: ['Week 1', 'Week 2', 'Week 3', 'Week 4'],
                datasets: [{
                    label: 'Sales (each)',
                    data: [52, 58, 54, 56],
                    backgroundColor: [
                        'rgba(234, 179, 8, 0.7)',
                        'rgba(234, 179, 8, 0.8)',
                        'rgba(234, 179, 8, 0.9)',
                        'rgba(234, 179, 8, 1)'
                    ],
                    borderColor: 'rgb(234, 179, 8)',
                    borderWidth: 2
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

        }); // End of DOMContentLoaded
    </script>
</x-admin-layout>
