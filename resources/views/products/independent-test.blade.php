<x-admin-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                Independent Supplier Test Page
            </h2>
            <a href="{{ route('products.create') }}" 
               class="inline-flex items-center px-4 py-2 bg-gray-600 hover:bg-gray-700 text-white font-medium rounded-md transition-colors duration-200">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                </svg>
                Back to Create Product
            </a>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <!-- Search Form -->
            <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6 mb-6">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-4">Test Supplier Code</h3>
                
                <form method="GET" action="{{ route('products.independent-test') }}" class="flex gap-4">
                    <input type="text" 
                           name="supplier_code" 
                           value="{{ $supplierCode }}" 
                           placeholder="Enter supplier code (e.g., 50433A)"
                           class="flex-1 rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 focus:border-indigo-500 focus:ring-indigo-500"
                           autofocus>
                    <button type="submit" 
                            class="px-6 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-md transition-colors duration-200">
                        Test Code
                    </button>
                </form>
                
                <div class="mt-4 text-sm text-gray-600 dark:text-gray-400">
                    <p>This page tests data retrieval from Independent Health Foods website.</p>
                    <p class="mt-1">Enter a supplier code to test image availability and product information scraping.</p>
                </div>
            </div>

            @if($supplierCode && isset($results))
                <!-- Results Section -->
                <div class="space-y-6">
                    <!-- Summary Card -->
                    <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-4">
                            Results for: <span class="text-blue-600 dark:text-blue-400">{{ $supplierCode }}</span>
                        </h3>
                        
                        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                            <div class="text-center p-3 bg-gray-50 dark:bg-gray-700 rounded">
                                <div class="text-2xl font-bold {{ count($results['images']) > 0 ? 'text-green-600' : 'text-red-600' }}">
                                    {{ count($results['images']) }}
                                </div>
                                <div class="text-sm text-gray-600 dark:text-gray-400">Images Found</div>
                            </div>
                            
                            <div class="text-center p-3 bg-gray-50 dark:bg-gray-700 rounded">
                                <div class="text-2xl font-bold {{ $results['search_data']['product_found'] ? 'text-green-600' : 'text-red-600' }}">
                                    {{ $results['search_data']['product_found'] ? '✓' : '✗' }}
                                </div>
                                <div class="text-sm text-gray-600 dark:text-gray-400">Product Found</div>
                            </div>
                            
                            <div class="text-center p-3 bg-gray-50 dark:bg-gray-700 rounded">
                                <div class="text-sm font-mono">
                                    {{ $results['timings']['images'] ?? 'N/A' }}
                                </div>
                                <div class="text-sm text-gray-600 dark:text-gray-400">Image Check Time</div>
                            </div>
                            
                            <div class="text-center p-3 bg-gray-50 dark:bg-gray-700 rounded">
                                <div class="text-sm font-mono">
                                    {{ $results['timings']['search'] ?? 'N/A' }}
                                </div>
                                <div class="text-sm text-gray-600 dark:text-gray-400">Search Time</div>
                            </div>
                        </div>
                    </div>

                    <!-- Images Section -->
                    <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-4">Product Images</h3>
                        
                        @if(count($results['images']) > 0)
                            <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
                                @foreach($results['images'] as $image)
                                    <div class="border border-gray-200 dark:border-gray-700 rounded-lg p-4">
                                        <img src="{{ $image['url'] }}" 
                                             alt="Product image {{ $image['variation'] }}"
                                             class="w-full h-48 object-contain mb-2"
                                             loading="lazy">
                                        <div class="text-xs space-y-1">
                                            <div class="flex justify-between">
                                                <span class="text-gray-500">Variation:</span>
                                                <span class="font-mono">_{{ $image['variation'] }}</span>
                                            </div>
                                            <div class="flex justify-between">
                                                <span class="text-gray-500">Width:</span>
                                                <span class="font-mono">{{ $image['width'] }}px</span>
                                            </div>
                                            @if(isset($image['format']))
                                                <div class="flex justify-between">
                                                    <span class="text-gray-500">Format:</span>
                                                    <span class="font-mono text-xs">{{ $image['format'] }}</span>
                                                </div>
                                            @endif
                                            @if(isset($image['path']))
                                                <div class="flex justify-between">
                                                    <span class="text-gray-500">Path:</span>
                                                    <span class="font-mono text-xs">{{ str_replace('https://iihealthfoods.com/cdn/shop/', '', $image['path']) }}</span>
                                                </div>
                                            @endif
                                            @if(isset($image['size']))
                                                <div class="flex justify-between">
                                                    <span class="text-gray-500">Size:</span>
                                                    <span class="font-mono">{{ number_format($image['size'] / 1024, 1) }}KB</span>
                                                </div>
                                            @endif
                                            <div class="mt-2">
                                                <a href="{{ $image['url'] }}" 
                                                   target="_blank"
                                                   class="text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300 text-xs">
                                                    View Full Size →
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                            
                            <div class="mt-4 p-3 bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded">
                                <p class="text-sm text-green-800 dark:text-green-200">
                                    ✓ Images can be directly linked from: 
                                    <code class="font-mono bg-green-100 dark:bg-green-900 px-1 rounded">
                                        https://iihealthfoods.com/cdn/shop/files/{SUPPLIER_CODE}_1.webp
                                    </code>
                                </p>
                            </div>
                        @else
                            <div class="p-4 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded">
                                <p class="text-red-800 dark:text-red-200">No images found for this supplier code.</p>
                                <p class="text-sm text-red-600 dark:text-red-400 mt-1">
                                    Checked variations _1, _2, and _3 with multiple sizes.
                                </p>
                            </div>
                        @endif
                    </div>

                    <!-- Search Data Section -->
                    <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-4">Search Page Data</h3>
                        
                        <div class="space-y-3">
                            <div class="flex items-start">
                                <span class="text-gray-600 dark:text-gray-400 w-32">Search URL:</span>
                                <a href="{{ $results['search_data']['url'] }}" 
                                   target="_blank"
                                   class="text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300 break-all">
                                    {{ $results['search_data']['url'] }} →
                                </a>
                            </div>
                            
                            @if($results['search_data']['product_found'])
                                <div class="flex items-start">
                                    <span class="text-gray-600 dark:text-gray-400 w-32">Product Name:</span>
                                    <span class="font-medium text-green-600 dark:text-green-400">
                                        {{ $results['search_data']['product_name'] ?? 'Not extracted' }}
                                    </span>
                                </div>
                                
                                @if($results['search_data']['product_url'])
                                    <div class="flex items-start">
                                        <span class="text-gray-600 dark:text-gray-400 w-32">Product URL:</span>
                                        <a href="{{ $results['search_data']['product_url'] }}" 
                                           target="_blank"
                                           class="text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300 break-all">
                                            {{ $results['search_data']['product_url'] }} →
                                        </a>
                                    </div>
                                @endif
                                
                                @if($results['search_data']['price'])
                                    <div class="flex items-start">
                                        <span class="text-gray-600 dark:text-gray-400 w-32">Price:</span>
                                        <span class="font-medium">{{ $results['search_data']['price'] }}</span>
                                    </div>
                                @endif
                            @else
                                <div class="p-3 bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-800 rounded">
                                    <p class="text-yellow-800 dark:text-yellow-200">
                                        {{ $results['search_data']['error'] ?? 'Product not found in search results' }}
                                    </p>
                                </div>
                            @endif
                        </div>
                        
                        @if($results['search_data']['raw_html'])
                            <details class="mt-4">
                                <summary class="cursor-pointer text-sm text-gray-600 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-200">
                                    View Raw HTML (first 5000 chars)
                                </summary>
                                <pre class="mt-2 p-3 bg-gray-50 dark:bg-gray-900 rounded text-xs overflow-x-auto">{{ $results['search_data']['raw_html'] }}</pre>
                            </details>
                        @endif
                    </div>

                    <!-- Integration Suggestions -->
                    <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-4">Integration Possibilities</h3>
                        
                        <div class="space-y-3">
                            @if(count($results['images']) > 0)
                                <div class="flex items-start">
                                    <svg class="w-5 h-5 text-green-500 mt-0.5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                    </svg>
                                    <div>
                                        <span class="font-medium">Direct Image Display:</span>
                                        <span class="text-gray-600 dark:text-gray-400 ml-2">
                                            Can display product images directly from CDN without scraping
                                        </span>
                                    </div>
                                </div>
                            @endif
                            
                            <div class="flex items-start">
                                <svg class="w-5 h-5 text-green-500 mt-0.5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                </svg>
                                <div>
                                    <span class="font-medium">Website Link:</span>
                                    <span class="text-gray-600 dark:text-gray-400 ml-2">
                                        Can link directly to search results using supplier code
                                    </span>
                                </div>
                            </div>
                            
                            @if($results['search_data']['product_found'])
                                <div class="flex items-start">
                                    <svg class="w-5 h-5 text-yellow-500 mt-0.5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                                    </svg>
                                    <div>
                                        <span class="font-medium">Product Name Extraction:</span>
                                        <span class="text-gray-600 dark:text-gray-400 ml-2">
                                            Possible but requires HTML parsing (may be fragile)
                                        </span>
                                    </div>
                                </div>
                            @endif
                            
                            <div class="flex items-start">
                                <svg class="w-5 h-5 text-red-500 mt-0.5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                                </svg>
                                <div>
                                    <span class="font-medium">No Barcode Data:</span>
                                    <span class="text-gray-600 dark:text-gray-400 ml-2">
                                        Website doesn't provide barcode information
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </div>
</x-admin-layout>