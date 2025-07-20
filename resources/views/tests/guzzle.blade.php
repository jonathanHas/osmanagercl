<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Udea Guzzle Test') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    <div class="mb-6">
                        <h3 class="text-lg font-medium text-gray-900 mb-4">
                            Server-side Scraping Test
                        </h3>
                        <p class="text-sm text-gray-600 mb-4">
                            Testing product data retrieval for supplier code: <strong>{{ $product_code }}</strong>
                        </p>
                    </div>

                    @if($success && $data)
                        <div class="bg-green-50 border border-green-200 rounded-md p-4 mb-6">
                            <div class="flex">
                                <div class="flex-shrink-0">
                                    <svg class="h-5 w-5 text-green-400" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                                    </svg>
                                </div>
                                <div class="ml-3">
                                    <h3 class="text-sm font-medium text-green-800">Success</h3>
                                    <p class="text-sm text-green-700 mt-1">Product data retrieved successfully</p>
                                </div>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                            <!-- Product Information -->
                            <div class="bg-gray-50 p-4 rounded-lg">
                                <h4 class="font-medium text-gray-900 mb-3">Product Information</h4>
                                <dl class="space-y-2">
                                    <div>
                                        <dt class="text-sm font-medium text-gray-500">Product Code</dt>
                                        <dd class="text-sm text-gray-900 font-mono">{{ $data['product_code'] ?? 'N/A' }}</dd>
                                    </div>
                                    <div>
                                        <dt class="text-sm font-medium text-gray-500">Product Name</dt>
                                        <dd class="text-sm text-gray-900 font-semibold">
                                            @if($data['description'] ?? null)
                                                {{ $data['description'] }}
                                            @else
                                                <span class="text-gray-400 italic">Not extracted</span>
                                            @endif
                                        </dd>
                                    </div>
                                    <div>
                                        <dt class="text-sm font-medium text-gray-500">Brand</dt>
                                        <dd class="text-sm text-gray-900">{{ $data['brand'] ?? 'N/A' }}</dd>
                                    </div>
                                    <div>
                                        <dt class="text-sm font-medium text-gray-500">Size</dt>
                                        <dd class="text-sm text-gray-900">{{ $data['size'] ?? 'N/A' }}</dd>
                                    </div>
                                </dl>
                            </div>

                            <!-- Pricing Information -->
                            <div class="bg-blue-50 p-4 rounded-lg">
                                <h4 class="font-medium text-gray-900 mb-3">
                                    Pricing Information
                                    @if($data['is_discounted'] ?? false)
                                        <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-red-100 text-red-800 ml-2">
                                            🏷️ DISCOUNTED
                                        </span>
                                    @endif
                                </h4>
                                <dl class="space-y-2">
                                    @if($data['is_discounted'] ?? false)
                                        <!-- Discount pricing display -->
                                        <div>
                                            <dt class="text-sm font-medium text-gray-500">Current Price (Discounted)</dt>
                                            <dd class="text-lg text-red-600 font-bold">
                                                €{{ $data['discount_price'] ?? $data['case_price'] }}
                                            </dd>
                                        </div>
                                        <div>
                                            <dt class="text-sm font-medium text-gray-500">Original Price</dt>
                                            <dd class="text-sm text-gray-500 line-through">
                                                €{{ $data['original_price'] }}
                                            </dd>
                                        </div>
                                        @if($data['original_price'] && $data['discount_price'])
                                            <div class="pt-1">
                                                <dt class="text-sm font-medium text-gray-500">Discount Amount</dt>
                                                <dd class="text-sm text-green-600 font-semibold">
                                                    -€{{ number_format((float)str_replace(',', '.', $data['original_price']) - (float)str_replace(',', '.', $data['discount_price']), 2, ',', '') }}
                                                    ({{ round(((float)str_replace(',', '.', $data['original_price']) - (float)str_replace(',', '.', $data['discount_price'])) / (float)str_replace(',', '.', $data['original_price']) * 100, 1) }}% off)
                                                </dd>
                                            </div>
                                            <div class="mt-3 p-2 bg-yellow-50 border border-yellow-200 rounded">
                                                <div class="flex">
                                                    <div class="flex-shrink-0">
                                                        <svg class="h-4 w-4 text-yellow-400" viewBox="0 0 20 20" fill="currentColor">
                                                            <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                                                        </svg>
                                                    </div>
                                                    <div class="ml-2">
                                                        <p class="text-xs text-yellow-700">
                                                            <strong>Discount Alert:</strong> Monitor pricing regularly as discounts may end and prices could revert to €{{ $data['original_price'] }}.
                                                        </p>
                                                    </div>
                                                </div>
                                            </div>
                                        @endif
                                    @else
                                        <!-- Regular pricing display -->
                                        <div>
                                            <dt class="text-sm font-medium text-gray-500">Case Price</dt>
                                            <dd class="text-lg text-blue-900 font-bold">
                                                @if($data['case_price'] ?? null)
                                                    €{{ $data['case_price'] }}
                                                @else
                                                    N/A
                                                @endif
                                            </dd>
                                        </div>
                                    @endif
                                    
                                    <div>
                                        <dt class="text-sm font-medium text-gray-500">Unit Price</dt>
                                        <dd class="text-sm text-gray-900 font-semibold">
                                            @if($data['unit_price'] ?? null)
                                                €{{ $data['unit_price'] }}
                                            @else
                                                N/A
                                            @endif
                                        </dd>
                                    </div>
                                    <div>
                                        <dt class="text-sm font-medium text-gray-500">Units per Case</dt>
                                        <dd class="text-sm text-gray-900">
                                            @if($data['units_per_case'] ?? null)
                                                {{ $data['units_per_case'] }} units
                                            @else
                                                N/A
                                            @endif
                                        </dd>
                                    </div>
                                    @if(isset($data['case_price']) && $data['units_per_case'])
                                        <div class="pt-2 border-t border-blue-200">
                                            <dt class="text-sm font-medium text-gray-500">Price per Unit (calculated)</dt>
                                            <dd class="text-sm text-gray-700">
                                                €{{ number_format((float)str_replace(',', '.', $data['case_price']) / $data['units_per_case'], 2, ',', '') }}
                                            </dd>
                                        </div>
                                    @endif
                                </dl>
                            </div>

                            <!-- Scraping Information -->
                            <div class="bg-green-50 p-4 rounded-lg">
                                <h4 class="font-medium text-gray-900 mb-3">Scraping Details</h4>
                                <dl class="space-y-2">
                                    <div>
                                        <dt class="text-sm font-medium text-gray-500">Scraped At</dt>
                                        <dd class="text-sm text-gray-900">
                                            {{ \Carbon\Carbon::parse($data['scraped_at'])->format('Y-m-d H:i:s') }}
                                        </dd>
                                    </div>
                                    <div>
                                        <dt class="text-sm font-medium text-gray-500">Data Source</dt>
                                        <dd class="text-sm text-gray-900">Udea.nl</dd>
                                    </div>
                                    <div>
                                        <dt class="text-sm font-medium text-gray-500">Method</dt>
                                        <dd class="text-sm text-gray-900">Server-side (Guzzle)</dd>
                                    </div>
                                    <div>
                                        <dt class="text-sm font-medium text-gray-500">Cache Status</dt>
                                        <dd class="text-sm text-gray-900">
                                            <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                                Cached (1 hour TTL)
                                            </span>
                                        </dd>
                                    </div>
                                </dl>
                            </div>
                        </div>

                        <!-- Raw Data Section (for debugging) -->
                        <div class="mt-6 bg-gray-100 p-4 rounded-lg">
                            <h4 class="font-medium text-gray-900 mb-2">Raw Data (Debug)</h4>
                            <pre class="text-xs text-gray-700 bg-white p-3 rounded border overflow-x-auto">{{ json_encode($data, JSON_PRETTY_PRINT) }}</pre>
                        </div>
                    @else
                        <div class="bg-red-50 border border-red-200 rounded-md p-4">
                            <div class="flex">
                                <div class="flex-shrink-0">
                                    <svg class="h-5 w-5 text-red-400" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                                    </svg>
                                </div>
                                <div class="ml-3">
                                    <h3 class="text-sm font-medium text-red-800">Failed to retrieve data</h3>
                                    <p class="text-sm text-red-700 mt-1">
                                        Unable to scrape product data. This could be due to:
                                    </p>
                                    <ul class="text-sm text-red-700 mt-2 list-disc list-inside">
                                        <li>Missing or invalid credentials</li>
                                        <li>Network connectivity issues</li>
                                        <li>Changes in the website structure</li>
                                        <li>Rate limiting or blocking</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    @endif

                    <!-- Test Different Supplier Code -->
                    <div class="mt-8 bg-white border border-gray-200 rounded-lg p-6">
                        <h4 class="font-medium text-gray-900 mb-4">Test Different Supplier Code</h4>
                        <form method="GET" action="{{ route('tests.guzzle') }}" class="flex space-x-4">
                            <div class="flex-1">
                                <label for="product_code" class="block text-sm font-medium text-gray-700 mb-1">
                                    Supplier Code
                                </label>
                                <input type="text" name="product_code" id="product_code" 
                                       value="{{ $product_code }}" 
                                       placeholder="Enter supplier code (e.g., 5014415 or 2192)"
                                       class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                                <div class="mt-1 text-xs text-gray-500">
                                    <p class="mb-1">Example codes to try:</p>
                                    <div class="flex flex-wrap gap-2">
                                        <button type="button" onclick="document.getElementById('product_code').value='5014415'" 
                                                class="px-2 py-1 bg-gray-100 text-gray-700 rounded text-xs hover:bg-gray-200">
                                            5014415 (Agar-agar)
                                        </button>
                                        <button type="button" onclick="document.getElementById('product_code').value='2192'" 
                                                class="px-2 py-1 bg-red-100 text-red-700 rounded text-xs hover:bg-red-200">
                                            2192 (Discounted ice cream)
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <div class="flex items-end">
                                <button type="submit" 
                                        class="inline-flex items-center px-4 py-2 bg-blue-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-700 focus:bg-blue-700 active:bg-blue-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150">
                                    Test Product
                                </button>
                            </div>
                        </form>
                    </div>

                    <div class="mt-6 flex space-x-4">
                        <a href="{{ route('tests.client') }}" class="inline-flex items-center px-4 py-2 bg-blue-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-700 focus:bg-blue-700 active:bg-blue-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150">
                            Try Client-side Test
                        </a>
                        <a href="{{ route('tests.dashboard') }}" class="inline-flex items-center px-4 py-2 bg-gray-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 focus:bg-gray-700 active:bg-gray-900 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2 transition ease-in-out duration-150">
                            Test Dashboard
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>