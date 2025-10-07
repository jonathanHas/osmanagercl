<x-admin-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Order Review Mockup #2 - Compact Table with Sparklines
        </h2>
    </x-slot>

    <div class="py-6">
        <div class="max-w-none mx-auto px-4 sm:px-6 lg:px-8">

            <!-- Compact Header -->
            <div class="bg-gradient-to-r from-blue-600 to-blue-700 rounded-lg shadow-lg p-6 mb-6 text-white">
                <div class="flex justify-between items-center">
                    <div>
                        <h3 class="text-2xl font-bold">Udea Weekly Order</h3>
                        <p class="text-blue-100 mt-1">Delivery: Mon, Jun 10 • 156 items • €2,847.50</p>
                    </div>
                    <div class="flex space-x-6">
                        <div class="text-center">
                            <p class="text-4xl font-bold">12</p>
                            <p class="text-blue-100 text-sm">Review</p>
                        </div>
                        <div class="text-center">
                            <p class="text-4xl font-bold">84</p>
                            <p class="text-blue-100 text-sm">Standard</p>
                        </div>
                        <div class="text-center">
                            <p class="text-4xl font-bold">60</p>
                            <p class="text-blue-100 text-sm">Safe</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Filter Bar -->
            <div class="bg-white rounded-lg shadow mb-6 p-4 flex items-center justify-between">
                <div class="flex space-x-2">
                    <button class="px-4 py-2 bg-red-600 text-white rounded-lg font-medium">🔴 Review (12)</button>
                    <button class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg font-medium hover:bg-gray-200">🟡 Standard (84)</button>
                    <button class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg font-medium hover:bg-gray-200">🟢 Safe (60)</button>
                    <button class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg font-medium hover:bg-gray-200">All (156)</button>
                </div>
                <div class="flex items-center space-x-3">
                    <button class="px-4 py-2 bg-green-600 text-white rounded-lg font-medium hover:bg-green-700">
                        ✓ Approve All Safe Items
                    </button>
                    <button class="px-4 py-2 bg-blue-600 text-white rounded-lg font-medium hover:bg-blue-700">
                        Export CSV
                    </button>
                </div>
            </div>

            <!-- Compact Product Table -->
            <div class="bg-white rounded-lg shadow overflow-hidden">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase w-8"></th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Product</th>
                            <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase w-32">Stock Status</th>
                            <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase" style="width: 280px;">Stock Levels & 4-Week Trend</th>
                            <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase w-32">Suggested</th>
                            <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase w-40">Order Qty</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase w-32">Cost</th>
                            <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase w-32">Action</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">

                        <!-- Product 1 - High Priority -->
                        <tr class="hover:bg-red-50 border-l-4 border-red-500" style="height: 180px;">
                            <td class="px-4 py-4">
                                <div class="w-8 h-8 bg-red-100 rounded-full flex items-center justify-center">
                                    <span class="text-red-600 font-bold text-sm">!</span>
                                </div>
                            </td>
                            <td class="px-4 py-4">
                                <div class="font-medium text-gray-900">Broccoli, Organic Class I NL</div>
                                <div class="text-sm text-gray-500">Code: 115 • €3.17/kg • 45 kg/week avg</div>
                            </td>
                            <td class="px-4 py-4">
                                <div class="text-center">
                                    <div class="text-2xl font-bold text-red-600">12 kg</div>
                                    <div class="mt-1 w-full bg-gray-200 rounded-full h-2">
                                        <div class="bg-red-500 h-2 rounded-full" style="width: 27%"></div>
                                    </div>
                                    <div class="text-xs text-red-600 font-medium mt-1">⚠️ 2 days left</div>
                                </div>
                            </td>
                            <td class="px-4 py-4 align-bottom">
                                <!-- Bars and Chart Container -->
                                <div class="flex items-end gap-3" style="min-height: 140px;">
                                    <!-- Current Stock Bar (Left) -->
                                    <div style="width: 40px;">
                                        <div class="text-xs font-medium text-gray-600 mb-1 text-center">Now</div>
                                        <div class="w-8 bg-gray-200 rounded-full relative overflow-hidden mx-auto" style="height: 60px;">
                                            <div class="absolute bottom-0 w-full bg-red-500 rounded-full" style="height: 25%;" title="12kg = 25% of peak week (48kg)"></div>
                                        </div>
                                    </div>

                                    <!-- Chart -->
                                    <div style="height: 60px; position: relative; flex: 1;">
                                        <canvas id="spark_1"></canvas>
                                    </div>

                                    <!-- After Delivery Bar (Right) -->
                                    <div style="width: 40px;">
                                        <div class="text-xs font-medium text-gray-600 mb-1 text-center">After</div>
                                        <div class="w-8 bg-gray-200 rounded-full relative mx-auto" style="height: 60px;">
                                            <!-- 100% reference line -->
                                            <div class="absolute top-0 left-0 right-0 h-0.5 bg-gray-400 z-10" title="100% = Peak week demand"></div>
                                            <!-- Bar grows beyond container (150% of 60px = 90px) -->
                                            <div class="absolute bottom-0 w-full bg-green-500 rounded-full" style="height: 90px;" title="72kg = 150% of peak week (overstock is good!)"></div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Text Labels Row -->
                                <div class="flex gap-3 mt-1">
                                    <div class="text-center" style="width: 40px;">
                                        <div class="text-xs font-bold text-red-600">12kg</div>
                                        <div class="text-xs text-gray-500">25%</div>
                                    </div>
                                    <div class="flex-1 text-center">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-800">
                                            ↗ +15% trend
                                        </span>
                                    </div>
                                    <div class="text-center" style="width: 40px;">
                                        <div class="text-xs font-bold text-green-600">72kg</div>
                                        <div class="text-xs text-green-600 font-medium">150%↑</div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 py-4 text-center">
                                <div class="text-xl font-bold text-purple-700">5 cases</div>
                                <div class="text-sm text-gray-600">60 kg</div>
                            </td>
                            <td class="px-4 py-4">
                                <div class="flex items-center justify-center space-x-1">
                                    <button class="w-8 h-8 bg-red-100 hover:bg-red-200 text-red-700 rounded font-bold">−</button>
                                    <input type="number" value="5" class="w-14 text-center text-lg font-bold border-2 border-gray-300 rounded py-1">
                                    <button class="w-8 h-8 bg-green-100 hover:bg-green-200 text-green-700 rounded font-bold">+</button>
                                </div>
                                <div class="text-center text-xs text-gray-500 mt-1">cases</div>
                            </td>
                            <td class="px-4 py-4 text-right">
                                <div class="text-lg font-bold text-gray-900">€190.20</div>
                                <div class="text-sm text-gray-500">€3.17/kg</div>
                            </td>
                            <td class="px-4 py-4 text-center">
                                <button class="px-4 py-2 bg-blue-600 text-white rounded-lg font-medium hover:bg-blue-700 text-sm">
                                    Approve
                                </button>
                            </td>
                        </tr>

                        <!-- Product 2 - Standard Priority -->
                        <tr class="hover:bg-yellow-50 border-l-4 border-yellow-500" style="height: 180px;">
                            <td class="px-4 py-4">
                                <div class="w-8 h-8 bg-yellow-100 rounded-full flex items-center justify-center">
                                    <span class="text-yellow-600 font-bold text-lg">●</span>
                                </div>
                            </td>
                            <td class="px-4 py-4">
                                <div class="font-medium text-gray-900">Avocado, Ready to Eat, Mexico</div>
                                <div class="text-sm text-gray-500">Code: 203 • €1.20/ea • 56 ea/week avg</div>
                            </td>
                            <td class="px-4 py-4">
                                <div class="text-center">
                                    <div class="text-2xl font-bold text-blue-600">32 ea</div>
                                    <div class="mt-1 w-full bg-gray-200 rounded-full h-2">
                                        <div class="bg-blue-500 h-2 rounded-full" style="width: 57%"></div>
                                    </div>
                                    <div class="text-xs text-blue-600 font-medium mt-1">✓ 4 days left</div>
                                </div>
                            </td>
                            <td class="px-4 py-4 align-bottom">
                                <!-- Bars and Chart Container -->
                                <div class="flex items-end gap-3" style="min-height: 140px;">
                                    <!-- Current Stock Bar (Left) -->
                                    <div style="width: 40px;">
                                        <div class="text-xs font-medium text-gray-600 mb-1 text-center">Now</div>
                                        <div class="w-8 bg-gray-200 rounded-full relative overflow-hidden mx-auto" style="height: 60px;">
                                            <div class="absolute bottom-0 w-full bg-yellow-500 rounded-full" style="height: 55%;" title="32ea = 55% of peak week (58ea)"></div>
                                        </div>
                                    </div>

                                    <!-- Chart -->
                                    <div style="height: 60px; position: relative; flex: 1;">
                                        <canvas id="spark_2"></canvas>
                                    </div>

                                    <!-- After Delivery Bar (Right) -->
                                    <div style="width: 40px;">
                                        <div class="text-xs font-medium text-gray-600 mb-1 text-center">After</div>
                                        <div class="w-8 bg-gray-200 rounded-full relative mx-auto" style="height: 60px;">
                                            <!-- 100% reference line -->
                                            <div class="absolute top-0 left-0 right-0 h-0.5 bg-gray-400 z-10" title="100% = Peak week demand"></div>
                                            <!-- Bar grows beyond container (179% of 60px = 107px) -->
                                            <div class="absolute bottom-0 w-full bg-green-500 rounded-full" style="height: 107px;" title="104ea = 179% of peak week (well overstocked!)"></div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Text Labels Row -->
                                <div class="flex gap-3 mt-1">
                                    <div class="text-center" style="width: 40px;">
                                        <div class="text-xs font-bold text-yellow-700">32ea</div>
                                        <div class="text-xs text-gray-500">55%</div>
                                    </div>
                                    <div class="flex-1 text-center">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-700">
                                            → Stable
                                        </span>
                                    </div>
                                    <div class="text-center" style="width: 40px;">
                                        <div class="text-xs font-bold text-green-600">104ea</div>
                                        <div class="text-xs text-green-600 font-medium">179%↑</div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 py-4 text-center">
                                <div class="text-xl font-bold text-purple-700">3 cases</div>
                                <div class="text-sm text-gray-600">72 each</div>
                            </td>
                            <td class="px-4 py-4">
                                <div class="flex items-center justify-center space-x-1">
                                    <button class="w-8 h-8 bg-red-100 hover:bg-red-200 text-red-700 rounded font-bold">−</button>
                                    <input type="number" value="3" class="w-14 text-center text-lg font-bold border-2 border-gray-300 rounded py-1">
                                    <button class="w-8 h-8 bg-green-100 hover:bg-green-200 text-green-700 rounded font-bold">+</button>
                                </div>
                                <div class="text-center text-xs text-gray-500 mt-1">cases</div>
                            </td>
                            <td class="px-4 py-4 text-right">
                                <div class="text-lg font-bold text-gray-900">€86.40</div>
                                <div class="text-sm text-gray-500">€1.20/ea</div>
                            </td>
                            <td class="px-4 py-4 text-center">
                                <button class="px-4 py-2 bg-blue-600 text-white rounded-lg font-medium hover:bg-blue-700 text-sm">
                                    Approve
                                </button>
                            </td>
                        </tr>

                        <!-- Product 3 - Safe/Auto-Approved -->
                        <tr class="hover:bg-green-50 border-l-4 border-green-500 opacity-75" style="height: 200px;">
                            <td class="px-4 py-4">
                                <div class="w-8 h-8 bg-green-100 rounded-full flex items-center justify-center">
                                    <span class="text-green-600 font-bold text-lg">✓</span>
                                </div>
                            </td>
                            <td class="px-4 py-4">
                                <div class="font-medium text-gray-900">Organic Pasta Penne, 500g</div>
                                <div class="text-sm text-gray-500">Code: 412 • €2.10/pkg • 18 pkg/week avg</div>
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 mt-1">
                                    Auto-Approved
                                </span>
                            </td>
                            <td class="px-4 py-4">
                                <div class="text-center">
                                    <div class="text-2xl font-bold text-green-600">24 pkg</div>
                                    <div class="mt-1 w-full bg-gray-200 rounded-full h-2">
                                        <div class="bg-green-500 h-2 rounded-full" style="width: 75%"></div>
                                    </div>
                                    <div class="text-xs text-green-600 font-medium mt-1">✓ 7 days left</div>
                                </div>
                            </td>
                            <td class="px-4 py-4 align-bottom">
                                <!-- Bars and Chart Container -->
                                <div class="flex items-end gap-3" style="min-height: 160px;">
                                    <!-- Current Stock Bar (Left) -->
                                    <div style="width: 40px;">
                                        <div class="text-xs font-medium text-gray-600 mb-1 text-center">Now</div>
                                        <div class="w-8 bg-gray-200 rounded-full relative mx-auto" style="height: 60px;">
                                            <!-- 100% reference line -->
                                            <div class="absolute top-0 left-0 right-0 h-0.5 bg-gray-400 z-10" title="100% = Peak week demand"></div>
                                            <!-- Bar grows beyond container (126% of 60px = 76px) -->
                                            <div class="absolute bottom-0 w-full bg-green-500 rounded-full" style="height: 76px;" title="24pkg = 126% of peak week (already overstocked!)"></div>
                                        </div>
                                    </div>

                                    <!-- Chart -->
                                    <div style="height: 60px; position: relative; flex: 1;">
                                        <canvas id="spark_3"></canvas>
                                    </div>

                                    <!-- After Delivery Bar (Right) -->
                                    <div style="width: 40px;">
                                        <div class="text-xs font-medium text-gray-600 mb-1 text-center">After</div>
                                        <div class="w-8 bg-gray-200 rounded-full relative mx-auto" style="height: 60px;">
                                            <!-- 100% reference line -->
                                            <div class="absolute top-0 left-0 right-0 h-0.5 bg-gray-400 z-10" title="100% = Peak week demand"></div>
                                            <!-- Bar grows WAY beyond container (232% of 60px = 139px) -->
                                            <div class="absolute bottom-0 w-full bg-green-500 rounded-full" style="height: 139px;" title="44pkg = 232% of peak week (way overstocked!)"></div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Text Labels Row -->
                                <div class="flex gap-3 mt-1">
                                    <div class="text-center" style="width: 40px;">
                                        <div class="text-xs font-bold text-green-600">24pkg</div>
                                        <div class="text-xs text-green-600 font-medium">126%↑</div>
                                    </div>
                                    <div class="flex-1 text-center">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-700">
                                            → Very Stable
                                        </span>
                                    </div>
                                    <div class="text-center" style="width: 40px;">
                                        <div class="text-xs font-bold text-green-600">44pkg</div>
                                        <div class="text-xs text-green-600 font-medium">232%↑</div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 py-4 text-center">
                                <div class="text-xl font-bold text-purple-700">2 cases</div>
                                <div class="text-sm text-gray-600">20 pkg</div>
                            </td>
                            <td class="px-4 py-4">
                                <div class="flex items-center justify-center space-x-1">
                                    <button disabled class="w-8 h-8 bg-gray-200 text-gray-400 rounded font-bold cursor-not-allowed">−</button>
                                    <input type="number" value="2" disabled class="w-14 text-center text-lg font-bold border-2 border-gray-200 rounded py-1 bg-gray-50">
                                    <button disabled class="w-8 h-8 bg-gray-200 text-gray-400 rounded font-bold cursor-not-allowed">+</button>
                                </div>
                                <div class="text-center text-xs text-gray-500 mt-1">cases</div>
                            </td>
                            <td class="px-4 py-4 text-right">
                                <div class="text-lg font-bold text-gray-900">€42.00</div>
                                <div class="text-sm text-gray-500">€2.10/pkg</div>
                            </td>
                            <td class="px-4 py-4 text-center">
                                <button class="px-4 py-2 bg-green-600 text-white rounded-lg font-medium text-sm">
                                    ✓ Approved
                                </button>
                            </td>
                        </tr>

                        <!-- Product 4 - Low Stock Alert -->
                        <tr class="hover:bg-red-50 border-l-4 border-red-500" style="height: 180px;">
                            <td class="px-4 py-4">
                                <div class="w-8 h-8 bg-red-100 rounded-full flex items-center justify-center">
                                    <span class="text-red-600 font-bold text-sm">!</span>
                                </div>
                            </td>
                            <td class="px-4 py-4">
                                <div class="font-medium text-gray-900">Cherry Tomatoes, Organic, 250g</div>
                                <div class="text-sm text-gray-500">Code: 187 • €2.45/pkg • 28 pkg/week avg</div>
                            </td>
                            <td class="px-4 py-4">
                                <div class="text-center">
                                    <div class="text-2xl font-bold text-red-600">3 pkg</div>
                                    <div class="mt-1 w-full bg-gray-200 rounded-full h-2">
                                        <div class="bg-red-600 h-2 rounded-full animate-pulse" style="width: 11%"></div>
                                    </div>
                                    <div class="text-xs text-red-600 font-medium mt-1">🚨 CRITICAL</div>
                                </div>
                            </td>
                            <td class="px-4 py-4 align-bottom">
                                <!-- Bars and Chart Container -->
                                <div class="flex items-end gap-3" style="min-height: 140px;">
                                    <!-- Current Stock Bar (Left) -->
                                    <div style="width: 40px;">
                                        <div class="text-xs font-medium text-gray-600 mb-1 text-center">Now</div>
                                        <div class="w-8 bg-gray-200 rounded-full relative overflow-hidden mx-auto" style="height: 60px;">
                                            <div class="absolute bottom-0 w-full bg-red-600 rounded-full animate-pulse" style="height: 10%;" title="3pkg = 10% of peak week - CRITICAL!"></div>
                                        </div>
                                    </div>

                                    <!-- Chart -->
                                    <div style="height: 60px; position: relative; flex: 1;">
                                        <canvas id="spark_4"></canvas>
                                    </div>

                                    <!-- After Delivery Bar (Right) -->
                                    <div style="width: 40px;">
                                        <div class="text-xs font-medium text-gray-600 mb-1 text-center">After</div>
                                        <div class="w-8 bg-gray-200 rounded-full relative mx-auto" style="height: 60px;">
                                            <!-- 100% reference line -->
                                            <div class="absolute top-0 left-0 right-0 h-0.5 bg-gray-400 z-10" title="100% = Peak week demand"></div>
                                            <!-- Bar grows beyond container (148% of 60px = 89px) -->
                                            <div class="absolute bottom-0 w-full bg-green-500 rounded-full" style="height: 89px;" title="43pkg = 148% of peak week (problem solved!)"></div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Text Labels Row -->
                                <div class="flex gap-3 mt-1">
                                    <div class="text-center" style="width: 40px;">
                                        <div class="text-xs font-bold text-red-600">3pkg</div>
                                        <div class="text-xs text-red-600 font-bold">10%!</div>
                                    </div>
                                    <div class="flex-1 text-center">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-700">
                                            → Stable
                                        </span>
                                    </div>
                                    <div class="text-center" style="width: 40px;">
                                        <div class="text-xs font-bold text-green-600">43pkg</div>
                                        <div class="text-xs text-green-600 font-medium">148%↑</div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 py-4 text-center">
                                <div class="text-xl font-bold text-purple-700">4 cases</div>
                                <div class="text-sm text-gray-600">40 pkg</div>
                            </td>
                            <td class="px-4 py-4">
                                <div class="flex items-center justify-center space-x-1">
                                    <button class="w-8 h-8 bg-red-100 hover:bg-red-200 text-red-700 rounded font-bold">−</button>
                                    <input type="number" value="4" class="w-14 text-center text-lg font-bold border-2 border-red-300 rounded py-1">
                                    <button class="w-8 h-8 bg-green-100 hover:bg-green-200 text-green-700 rounded font-bold">+</button>
                                </div>
                                <div class="text-center text-xs text-gray-500 mt-1">cases</div>
                            </td>
                            <td class="px-4 py-4 text-right">
                                <div class="text-lg font-bold text-gray-900">€98.00</div>
                                <div class="text-sm text-gray-500">€2.45/pkg</div>
                            </td>
                            <td class="px-4 py-4 text-center">
                                <button class="px-4 py-2 bg-blue-600 text-white rounded-lg font-medium hover:bg-blue-700 text-sm">
                                    Approve
                                </button>
                            </td>
                        </tr>

                    </tbody>
                </table>
            </div>

            <!-- Footer Actions -->
            <div class="mt-6 bg-white rounded-lg shadow p-4 flex justify-between items-center">
                <div class="text-gray-700">
                    <span class="font-semibold">4 items reviewed</span> • <span>152 remaining</span>
                </div>
                <div class="flex space-x-3">
                    <button class="px-6 py-3 bg-gray-200 text-gray-700 rounded-lg font-medium hover:bg-gray-300">
                        Save Draft
                    </button>
                    <button class="px-6 py-3 bg-green-600 text-white rounded-lg font-medium hover:bg-green-700">
                        Complete Order
                    </button>
                </div>
            </div>

        </div>
    </div>

    <!-- Chart.js for sparklines -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        // Run once when DOM is loaded
        document.addEventListener('DOMContentLoaded', function() {
            // Prevent multiple executions
            if (window.chartsInitialized) return;
            window.chartsInitialized = true;

            // Sparkline configuration
            const sparklineOptions = {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    enabled: true,
                    backgroundColor: 'rgba(0, 0, 0, 0.8)',
                    padding: 8,
                    cornerRadius: 4,
                    titleFont: { size: 11, weight: 'bold' },
                    bodyFont: { size: 11 },
                    displayColors: false
                }
            },
            scales: {
                x: { display: false },
                y: { display: false }
            },
            elements: {
                point: { radius: 0 }
            }
        };

        // Product 1 - Trending up (red)
        new Chart(document.getElementById('spark_1'), {
            type: 'line',
            data: {
                labels: ['Week 1', 'Week 2', 'Week 3', 'Week 4'],
                datasets: [{
                    label: 'Sales',
                    data: [35, 42, 48, 45],
                    borderColor: 'rgb(239, 68, 68)',
                    backgroundColor: 'rgba(239, 68, 68, 0.2)',
                    tension: 0.4,
                    fill: true,
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: {
                    mode: 'index',
                    intersect: false
                },
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        enabled: true,
                        backgroundColor: 'rgba(0, 0, 0, 0.8)',
                        padding: 10,
                        cornerRadius: 4,
                        titleFont: { size: 12, weight: 'bold' },
                        bodyFont: { size: 12 },
                        displayColors: false,
                        callbacks: {
                            label: function(context) {
                                return context.parsed.y + ' kg sold';
                            }
                        }
                    }
                },
                scales: {
                    x: { display: false },
                    y: { display: false }
                },
                elements: {
                    point: { radius: 3, hoverRadius: 5 }
                }
            }
        });

        // Product 2 - Stable (yellow)
        new Chart(document.getElementById('spark_2'), {
            type: 'line',
            data: {
                labels: ['Week 1', 'Week 2', 'Week 3', 'Week 4'],
                datasets: [{
                    label: 'Sales',
                    data: [52, 58, 54, 56],
                    borderColor: 'rgb(234, 179, 8)',
                    backgroundColor: 'rgba(234, 179, 8, 0.2)',
                    tension: 0.4,
                    fill: true,
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: {
                    mode: 'index',
                    intersect: false
                },
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        enabled: true,
                        backgroundColor: 'rgba(0, 0, 0, 0.8)',
                        padding: 10,
                        cornerRadius: 4,
                        titleFont: { size: 12, weight: 'bold' },
                        bodyFont: { size: 12 },
                        displayColors: false,
                        callbacks: {
                            label: function(context) {
                                return context.parsed.y + ' each sold';
                            }
                        }
                    }
                },
                scales: {
                    x: { display: false },
                    y: { display: false }
                },
                elements: {
                    point: { radius: 3, hoverRadius: 5 }
                }
            }
        });

        // Product 3 - Very stable (green)
        new Chart(document.getElementById('spark_3'), {
            type: 'line',
            data: {
                labels: ['Week 1', 'Week 2', 'Week 3', 'Week 4'],
                datasets: [{
                    label: 'Sales',
                    data: [17, 18, 19, 18],
                    borderColor: 'rgb(34, 197, 94)',
                    backgroundColor: 'rgba(34, 197, 94, 0.2)',
                    tension: 0.4,
                    fill: true,
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: {
                    mode: 'index',
                    intersect: false
                },
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        enabled: true,
                        backgroundColor: 'rgba(0, 0, 0, 0.8)',
                        padding: 10,
                        cornerRadius: 4,
                        titleFont: { size: 12, weight: 'bold' },
                        bodyFont: { size: 12 },
                        displayColors: false,
                        callbacks: {
                            label: function(context) {
                                return context.parsed.y + ' pkg sold';
                            }
                        }
                    }
                },
                scales: {
                    x: { display: false },
                    y: { display: false }
                },
                elements: {
                    point: { radius: 3, hoverRadius: 5 }
                }
            }
        });

        // Product 4 - Stable
        new Chart(document.getElementById('spark_4'), {
            type: 'line',
            data: {
                labels: ['Week 1', 'Week 2', 'Week 3', 'Week 4'],
                datasets: [{
                    label: 'Sales',
                    data: [26, 29, 27, 28],
                    borderColor: 'rgb(107, 114, 128)',
                    backgroundColor: 'rgba(107, 114, 128, 0.2)',
                    tension: 0.4,
                    fill: true,
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: {
                    mode: 'index',
                    intersect: false
                },
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        enabled: true,
                        backgroundColor: 'rgba(0, 0, 0, 0.8)',
                        padding: 10,
                        cornerRadius: 4,
                        titleFont: { size: 12, weight: 'bold' },
                        bodyFont: { size: 12 },
                        displayColors: false,
                        callbacks: {
                            label: function(context) {
                                return context.parsed.y + ' pkg sold';
                            }
                        }
                    }
                },
                scales: {
                    x: { display: false },
                    y: { display: false }
                },
                elements: {
                    point: { radius: 3, hoverRadius: 5 }
                }
            }
        });

        }); // End of DOMContentLoaded
    </script>
</x-admin-layout>
