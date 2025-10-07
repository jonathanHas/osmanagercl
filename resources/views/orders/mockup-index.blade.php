<x-admin-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Order Review UI Mockups
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-8 mb-6">
                <h3 class="text-2xl font-bold text-gray-900 mb-4">Choose a Mockup Design</h3>
                <p class="text-gray-600 mb-8">
                    Below are 3 different visual approaches for the order review interface. All use hardcoded test data so you can compare the designs without database connections.
                </p>

                <!-- Mockup Cards -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">

                    <!-- Mockup 1 -->
                    <a href="{{ route('orders.mockup.1') }}" class="group">
                        <div class="bg-white border-2 border-gray-200 rounded-lg overflow-hidden hover:border-blue-500 hover:shadow-xl transition-all">
                            <div class="bg-gradient-to-br from-blue-50 to-blue-100 p-6 border-b border-gray-200">
                                <h4 class="text-xl font-bold text-gray-900 mb-2">Mockup #1</h4>
                                <p class="text-sm font-medium text-blue-600">Chart-Heavy Approach</p>
                            </div>
                            <div class="p-6">
                                <ul class="space-y-2 text-sm text-gray-600">
                                    <li class="flex items-center">
                                        <svg class="w-5 h-5 text-green-500 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                        </svg>
                                        Large product cards
                                    </li>
                                    <li class="flex items-center">
                                        <svg class="w-5 h-5 text-green-500 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                        </svg>
                                        Full 4-week trend charts
                                    </li>
                                    <li class="flex items-center">
                                        <svg class="w-5 h-5 text-green-500 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                        </svg>
                                        Visual stat cards
                                    </li>
                                    <li class="flex items-center">
                                        <svg class="w-5 h-5 text-green-500 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                        </svg>
                                        Most detailed view
                                    </li>
                                </ul>
                                <div class="mt-6">
                                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                        Best for: Detailed Review
                                    </span>
                                </div>
                            </div>
                            <div class="bg-gray-50 px-6 py-4 border-t border-gray-200">
                                <span class="text-blue-600 font-medium group-hover:text-blue-700">
                                    View Mockup →
                                </span>
                            </div>
                        </div>
                    </a>

                    <!-- Mockup 2 -->
                    <a href="{{ route('orders.mockup.2') }}" class="group">
                        <div class="bg-white border-2 border-gray-200 rounded-lg overflow-hidden hover:border-green-500 hover:shadow-xl transition-all">
                            <div class="bg-gradient-to-br from-green-50 to-green-100 p-6 border-b border-gray-200">
                                <h4 class="text-xl font-bold text-gray-900 mb-2">Mockup #2</h4>
                                <p class="text-sm font-medium text-green-600">Compact Table + Sparklines</p>
                            </div>
                            <div class="p-6">
                                <ul class="space-y-2 text-sm text-gray-600">
                                    <li class="flex items-center">
                                        <svg class="w-5 h-5 text-green-500 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                        </svg>
                                        Dense table layout
                                    </li>
                                    <li class="flex items-center">
                                        <svg class="w-5 h-5 text-green-500 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                        </svg>
                                        Inline sparkline charts
                                    </li>
                                    <li class="flex items-center">
                                        <svg class="w-5 h-5 text-green-500 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                        </svg>
                                        Stock level progress bars
                                    </li>
                                    <li class="flex items-center">
                                        <svg class="w-5 h-5 text-green-500 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                        </svg>
                                        See more items at once
                                    </li>
                                </ul>
                                <div class="mt-6">
                                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                        Best for: Quick Scanning
                                    </span>
                                </div>
                            </div>
                            <div class="bg-gray-50 px-6 py-4 border-t border-gray-200">
                                <span class="text-green-600 font-medium group-hover:text-green-700">
                                    View Mockup →
                                </span>
                            </div>
                        </div>
                    </a>

                    <!-- Mockup 3 -->
                    <a href="{{ route('orders.mockup.3') }}" class="group">
                        <div class="bg-white border-2 border-gray-200 rounded-lg overflow-hidden hover:border-purple-500 hover:shadow-xl transition-all">
                            <div class="bg-gradient-to-br from-purple-50 to-purple-100 p-6 border-b border-gray-200">
                                <h4 class="text-xl font-bold text-gray-900 mb-2">Mockup #3</h4>
                                <p class="text-sm font-medium text-purple-600">Visual Dashboard</p>
                            </div>
                            <div class="p-6">
                                <ul class="space-y-2 text-sm text-gray-600">
                                    <li class="flex items-center">
                                        <svg class="w-5 h-5 text-green-500 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                        </svg>
                                        Circular stock gauges
                                    </li>
                                    <li class="flex items-center">
                                        <svg class="w-5 h-5 text-green-500 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                        </svg>
                                        Bar charts for trends
                                    </li>
                                    <li class="flex items-center">
                                        <svg class="w-5 h-5 text-green-500 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                        </svg>
                                        Dashboard-style header
                                    </li>
                                    <li class="flex items-center">
                                        <svg class="w-5 h-5 text-green-500 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                        </svg>
                                        Maximum visual appeal
                                    </li>
                                </ul>
                                <div class="mt-6">
                                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-purple-100 text-purple-800">
                                        Best for: Visual Users
                                    </span>
                                </div>
                            </div>
                            <div class="bg-gray-50 px-6 py-4 border-t border-gray-200">
                                <span class="text-purple-600 font-medium group-hover:text-purple-700">
                                    View Mockup →
                                </span>
                            </div>
                        </div>
                    </a>

                </div>
            </div>

            <!-- Comparison Table -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-8">
                <h3 class="text-xl font-bold text-gray-900 mb-4">Feature Comparison</h3>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Feature</th>
                                <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Mockup #1</th>
                                <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Mockup #2</th>
                                <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Mockup #3</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">Items visible at once</td>
                                <td class="px-6 py-4 whitespace-nowrap text-center text-sm text-gray-900">1-2</td>
                                <td class="px-6 py-4 whitespace-nowrap text-center text-sm text-gray-900 font-bold text-green-600">4-6</td>
                                <td class="px-6 py-4 whitespace-nowrap text-center text-sm text-gray-900">2-3</td>
                            </tr>
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">Chart detail level</td>
                                <td class="px-6 py-4 whitespace-nowrap text-center text-sm text-gray-900 font-bold text-green-600">High</td>
                                <td class="px-6 py-4 whitespace-nowrap text-center text-sm text-gray-900">Medium</td>
                                <td class="px-6 py-4 whitespace-nowrap text-center text-sm text-gray-900">High</td>
                            </tr>
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">Visual appeal</td>
                                <td class="px-6 py-4 whitespace-nowrap text-center text-sm text-gray-900">High</td>
                                <td class="px-6 py-4 whitespace-nowrap text-center text-sm text-gray-900">Medium</td>
                                <td class="px-6 py-4 whitespace-nowrap text-center text-sm text-gray-900 font-bold text-green-600">Very High</td>
                            </tr>
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">Quick scan speed</td>
                                <td class="px-6 py-4 whitespace-nowrap text-center text-sm text-gray-900">Low</td>
                                <td class="px-6 py-4 whitespace-nowrap text-center text-sm text-gray-900 font-bold text-green-600">High</td>
                                <td class="px-6 py-4 whitespace-nowrap text-center text-sm text-gray-900">Medium</td>
                            </tr>
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">Data density</td>
                                <td class="px-6 py-4 whitespace-nowrap text-center text-sm text-gray-900">Low</td>
                                <td class="px-6 py-4 whitespace-nowrap text-center text-sm text-gray-900 font-bold text-green-600">High</td>
                                <td class="px-6 py-4 whitespace-nowrap text-center text-sm text-gray-900">Medium</td>
                            </tr>
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">Best for large orders (100+ items)</td>
                                <td class="px-6 py-4 whitespace-nowrap text-center text-sm text-gray-900">❌</td>
                                <td class="px-6 py-4 whitespace-nowrap text-center text-sm text-gray-900 font-bold text-green-600">✅</td>
                                <td class="px-6 py-4 whitespace-nowrap text-center text-sm text-gray-900">❌</td>
                            </tr>
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">Best for detailed analysis</td>
                                <td class="px-6 py-4 whitespace-nowrap text-center text-sm text-gray-900 font-bold text-green-600">✅</td>
                                <td class="px-6 py-4 whitespace-nowrap text-center text-sm text-gray-900">❌</td>
                                <td class="px-6 py-4 whitespace-nowrap text-center text-sm text-gray-900">✅</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </div>
</x-admin-layout>
