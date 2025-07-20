<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Udea Client-side Test') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    <div class="mb-6">
                        <h3 class="text-lg font-medium text-gray-900 mb-4">
                            Client-side API Test
                        </h3>
                        <p class="text-sm text-gray-600 mb-4">
                            Testing product data retrieval via proxy API for code: <strong>{{ $product_code }}</strong>
                        </p>
                    </div>

                    <div x-data="productFetcher('{{ $product_code }}')" class="space-y-6">
                        <!-- Loading State -->
                        <div x-show="loading" class="flex items-center justify-center py-8">
                            <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600"></div>
                            <span class="ml-2 text-gray-600">Fetching product data...</span>
                        </div>

                        <!-- Error State -->
                        <div x-show="error && !loading" class="bg-red-50 border border-red-200 rounded-md p-4">
                            <div class="flex">
                                <div class="flex-shrink-0">
                                    <svg class="h-5 w-5 text-red-400" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                                    </svg>
                                </div>
                                <div class="ml-3">
                                    <h3 class="text-sm font-medium text-red-800">API Error</h3>
                                    <p class="text-sm text-red-700 mt-1" x-text="error"></p>
                                </div>
                            </div>
                        </div>

                        <!-- Success State -->
                        <div x-show="data && !loading && !error" class="space-y-6">
                            <div class="bg-green-50 border border-green-200 rounded-md p-4">
                                <div class="flex">
                                    <div class="flex-shrink-0">
                                        <svg class="h-5 w-5 text-green-400" viewBox="0 0 20 20" fill="currentColor">
                                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                                        </svg>
                                    </div>
                                    <div class="ml-3">
                                        <h3 class="text-sm font-medium text-green-800">Success</h3>
                                        <p class="text-sm text-green-700 mt-1">Product data retrieved via API</p>
                                    </div>
                                </div>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div class="bg-gray-50 p-4 rounded-lg">
                                    <h4 class="font-medium text-gray-900 mb-2">Product Information</h4>
                                    <dl class="space-y-2">
                                        <div>
                                            <dt class="text-sm font-medium text-gray-500">Product Code</dt>
                                            <dd class="text-sm text-gray-900" x-text="data?.product_code || 'N/A'"></dd>
                                        </div>
                                        <div>
                                            <dt class="text-sm font-medium text-gray-500">Description</dt>
                                            <dd class="text-sm text-gray-900" x-text="data?.description || 'N/A'"></dd>
                                        </div>
                                        <div>
                                            <dt class="text-sm font-medium text-gray-500">Availability</dt>
                                            <dd class="text-sm text-gray-900" x-text="data?.availability || 'N/A'"></dd>
                                        </div>
                                    </dl>
                                </div>

                                <div class="bg-gray-50 p-4 rounded-lg">
                                    <h4 class="font-medium text-gray-900 mb-2">Pricing Information</h4>
                                    <dl class="space-y-2">
                                        <div>
                                            <dt class="text-sm font-medium text-gray-500">Case Cost</dt>
                                            <dd class="text-sm text-gray-900 font-semibold" x-text="data?.price || 'N/A'"></dd>
                                        </div>
                                        <div>
                                            <dt class="text-sm font-medium text-gray-500">Units per Case</dt>
                                            <dd class="text-sm text-gray-900" x-text="data?.units_per_case || 'N/A'"></dd>
                                        </div>
                                        <div>
                                            <dt class="text-sm font-medium text-gray-500">Scraped At</dt>
                                            <dd class="text-sm text-gray-900" x-text="data?.scraped_at || 'N/A'"></dd>
                                        </div>
                                    </dl>
                                </div>
                            </div>
                        </div>

                        <!-- Controls -->
                        <div class="flex space-x-4">
                            <button @click="fetchData()" :disabled="loading" 
                                    class="inline-flex items-center px-4 py-2 bg-blue-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-700 focus:bg-blue-700 active:bg-blue-900 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition ease-in-out duration-150 disabled:opacity-50">
                                <span x-show="!loading">Refresh</span>
                                <span x-show="loading">Loading...</span>
                            </button>
                            
                            <input type="text" x-model="productCode" placeholder="Product Code" 
                                   class="block w-32 rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                            
                            <button @click="fetchCustomProduct()" :disabled="loading || !productCode.trim()" 
                                    class="inline-flex items-center px-4 py-2 bg-green-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-green-700 focus:bg-green-700 active:bg-green-900 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2 transition ease-in-out duration-150 disabled:opacity-50">
                                Test Code
                            </button>
                        </div>
                    </div>

                    <div class="mt-6 flex space-x-4">
                        <a href="{{ route('tests.guzzle') }}" class="inline-flex items-center px-4 py-2 bg-blue-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-700 focus:bg-blue-700 active:bg-blue-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150">
                            Try Server-side Test
                        </a>
                        <a href="{{ route('tests.dashboard') }}" class="inline-flex items-center px-4 py-2 bg-gray-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 focus:bg-gray-700 active:bg-gray-900 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2 transition ease-in-out duration-150">
                            Test Dashboard
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function productFetcher(initialCode) {
            return {
                loading: false,
                data: null,
                error: null,
                productCode: initialCode,

                init() {
                    this.fetchData();
                },

                async fetchData() {
                    this.loading = true;
                    this.error = null;
                    this.data = null;

                    try {
                        const response = await fetch('/api/test-scraper/product-data', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                            },
                            body: JSON.stringify({ 
                                product_code: this.productCode || '{{ $product_code }}' 
                            })
                        });

                        const result = await response.json();

                        if (result.success) {
                            this.data = result.data;
                        } else {
                            this.error = result.error || 'Unknown error occurred';
                        }
                    } catch (err) {
                        this.error = 'Network error: ' + err.message;
                    } finally {
                        this.loading = false;
                    }
                },

                async fetchCustomProduct() {
                    if (!this.productCode.trim()) return;
                    await this.fetchData();
                }
            }
        }
    </script>
</x-app-layout>