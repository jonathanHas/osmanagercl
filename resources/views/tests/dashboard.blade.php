<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Udea Scraping Test Dashboard') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <!-- Connection Status -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Connection Status</h3>
                    
                    @if($connection_status['success'])
                        <div class="bg-green-50 border border-green-200 rounded-md p-4">
                            <div class="flex">
                                <div class="flex-shrink-0">
                                    <svg class="h-5 w-5 text-green-400" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                                    </svg>
                                </div>
                                <div class="ml-3 flex-1">
                                    <h3 class="text-sm font-medium text-green-800">Connected</h3>
                                    <div class="text-sm text-green-700 mt-1">
                                        <p>Status Code: {{ $connection_status['status_code'] ?? 'N/A' }}</p>
                                        <p>Response Time: {{ $connection_status['response_time'] ?? 'N/A' }}ms</p>
                                        <p>Authentication: 
                                            <span class="{{ $connection_status['authenticated'] ? 'text-green-600 font-semibold' : 'text-red-600 font-semibold' }}">
                                                {{ $connection_status['authenticated'] ? 'Success' : 'Failed' }}
                                            </span>
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        @if(isset($connection_status['auth_debug']) && !$connection_status['authenticated'])
                            <div class="mt-4 bg-yellow-50 border border-yellow-200 rounded-md p-4">
                                <h4 class="text-sm font-medium text-yellow-800 mb-2">Authentication Debug Information</h4>
                                <div class="text-xs text-yellow-700 space-y-2">
                                    @if(isset($connection_status['auth_debug']['debug']))
                                        @foreach($connection_status['auth_debug']['debug'] as $step => $data)
                                            <div class="bg-yellow-100 p-2 rounded">
                                                <strong>{{ ucfirst(str_replace('_', ' ', $step)) }}:</strong>
                                                <ul class="ml-4 mt-1">
                                                    @foreach($data as $key => $value)
                                                        <li>{{ ucfirst(str_replace('_', ' ', $key)) }}: 
                                                            @if(is_bool($value))
                                                                <span class="{{ $value ? 'text-green-600' : 'text-red-600' }}">{{ $value ? 'Yes' : 'No' }}</span>
                                                            @elseif(is_array($value))
                                                                {{ implode(', ', $value) }}
                                                            @else
                                                                {{ $value ?? 'null' }}
                                                            @endif
                                                        </li>
                                                    @endforeach
                                                </ul>
                                            </div>
                                        @endforeach
                                    @endif
                                    
                                    @if(isset($connection_status['auth_debug']['error']))
                                        <div class="bg-red-100 p-2 rounded">
                                            <strong>Error:</strong> {{ $connection_status['auth_debug']['error'] }}
                                        </div>
                                    @endif
                                </div>
                            </div>
                        @endif
                    @else
                        <div class="bg-red-50 border border-red-200 rounded-md p-4">
                            <div class="flex">
                                <div class="flex-shrink-0">
                                    <svg class="h-5 w-5 text-red-400" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                                    </svg>
                                </div>
                                <div class="ml-3">
                                    <h3 class="text-sm font-medium text-red-800">Connection Failed</h3>
                                    <p class="text-sm text-red-700 mt-1">{{ $connection_status['error'] ?? 'Unknown error' }}</p>
                                </div>
                            </div>
                        </div>
                    @endif
                </div>
            </div>

            <!-- Test Methods -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Available Test Methods</h3>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="border border-gray-200 rounded-lg p-4">
                            <h4 class="font-medium text-gray-900 mb-2">Server-side Scraping</h4>
                            <p class="text-sm text-gray-600 mb-4">
                                Tests direct server-to-server communication using Guzzle HTTP client with session management.
                            </p>
                            <div class="space-y-2 text-sm">
                                <div class="flex items-center">
                                    <span class="w-2 h-2 bg-green-400 rounded-full mr-2"></span>
                                    <span>No CORS issues</span>
                                </div>
                                <div class="flex items-center">
                                    <span class="w-2 h-2 bg-green-400 rounded-full mr-2"></span>
                                    <span>Server-side caching</span>
                                </div>
                                <div class="flex items-center">
                                    <span class="w-2 h-2 bg-yellow-400 rounded-full mr-2"></span>
                                    <span>Requires valid credentials</span>
                                </div>
                            </div>
                            <a href="{{ route('tests.guzzle') }}" class="mt-4 inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                Test Server-side
                            </a>
                        </div>

                        <div class="border border-gray-200 rounded-lg p-4">
                            <h4 class="font-medium text-gray-900 mb-2">Client-side API</h4>
                            <p class="text-sm text-gray-600 mb-4">
                                Tests client-side JavaScript fetching data through our proxy API endpoint.
                            </p>
                            <div class="space-y-2 text-sm">
                                <div class="flex items-center">
                                    <span class="w-2 h-2 bg-green-400 rounded-full mr-2"></span>
                                    <span>Interactive testing</span>
                                </div>
                                <div class="flex items-center">
                                    <span class="w-2 h-2 bg-green-400 rounded-full mr-2"></span>
                                    <span>Real-time feedback</span>
                                </div>
                                <div class="flex items-center">
                                    <span class="w-2 h-2 bg-blue-400 rounded-full mr-2"></span>
                                    <span>Uses proxy endpoint</span>
                                </div>
                            </div>
                            <a href="{{ route('tests.client') }}" class="mt-4 inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500">
                                Test Client-side
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Debug Tools -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6" x-data="debugTools()">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Debug Tools</h3>
                    
                    <div class="flex space-x-4 mb-4">
                        <button @click="testApiRoute()" :disabled="loading"
                                class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 disabled:opacity-50">
                            <span x-show="!loading">Test API Route</span>
                            <span x-show="loading">Loading...</span>
                        </button>
                        
                        <button @click="testUdeaConnection()" :disabled="loading"
                                class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-orange-600 hover:bg-orange-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-orange-500 disabled:opacity-50">
                            <span x-show="!loading">Test Udea Connection</span>
                            <span x-show="loading">Loading...</span>
                        </button>
                        
                        <button @click="findLoginUrl()" :disabled="loading"
                                class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-purple-600 hover:bg-purple-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-purple-500 disabled:opacity-50">
                            <span x-show="!loading">Find Login URL</span>
                            <span x-show="loading">Loading...</span>
                        </button>
                        
                        <button @click="debugSearchRaw()" :disabled="loading"
                                class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-red-600 hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 disabled:opacity-50">
                            <span x-show="!loading">Debug Search Raw</span>
                            <span x-show="loading">Loading...</span>
                        </button>
                        
                        <button @click="debugLoginPage()" :disabled="loading"
                                class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 disabled:opacity-50">
                            <span x-show="!loading">Debug Login Page</span>
                            <span x-show="loading">Loading...</span>
                        </button>
                    </div>

                    <div x-show="debugResult" class="mt-4 p-4 bg-gray-50 rounded-lg">
                        <h4 class="font-medium text-gray-900 mb-2">Login Page Analysis</h4>
                        <pre x-text="debugResult" class="text-xs bg-gray-800 text-gray-100 p-3 rounded overflow-x-auto"></pre>
                    </div>

                    <div x-show="debugError" class="mt-4 p-3 rounded-md bg-red-50 border border-red-200 text-red-700">
                        <p x-text="debugError"></p>
                    </div>
                </div>
            </div>

            <!-- Cache Management -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6" x-data="cacheManager()">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Cache Management</h3>
                    
                    <div class="flex space-x-4 items-end">
                        <div class="flex-1">
                            <label for="product_code" class="block text-sm font-medium text-gray-700 mb-1">
                                Product Code (optional)
                            </label>
                            <input type="text" x-model="productCode" id="product_code" 
                                   placeholder="Leave empty to clear all cache"
                                   class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                        </div>
                        <button @click="clearCache()" :disabled="loading"
                                class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-red-600 hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 disabled:opacity-50">
                            <span x-show="!loading">Clear Cache</span>
                            <span x-show="loading">Clearing...</span>
                        </button>
                    </div>

                    <div x-show="message" class="mt-4 p-3 rounded-md" 
                         :class="success ? 'bg-green-50 border border-green-200 text-green-700' : 'bg-red-50 border border-red-200 text-red-700'">
                        <p x-text="message"></p>
                    </div>
                </div>
            </div>

            <!-- Configuration Help -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Configuration</h3>
                    
                    <div class="bg-gray-50 rounded-lg p-4">
                        <h4 class="font-medium text-gray-900 mb-2">Environment Variables</h4>
                        <p class="text-sm text-gray-600 mb-3">
                            Add these variables to your <code class="bg-gray-200 px-1 rounded">.env</code> file:
                        </p>
                        <pre class="text-xs bg-gray-800 text-gray-100 p-3 rounded overflow-x-auto"><code>UDEA_BASE_URI=https://www.udea.nl
UDEA_USERNAME=your_username
UDEA_PASSWORD=your_password
UDEA_TIMEOUT=30
UDEA_RATE_LIMIT_DELAY=2
UDEA_CACHE_TTL=3600</code></pre>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function debugTools() {
            return {
                loading: false,
                debugResult: '',
                debugError: '',

                async testApiRoute() {
                    this.loading = true;
                    this.debugResult = '';
                    this.debugError = '';

                    try {
                        const response = await fetch('/api/test-scraper/test-api', {
                            headers: {
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                            }
                        });

                        const responseText = await response.text();
                        
                        try {
                            const result = JSON.parse(responseText);
                            this.debugResult = JSON.stringify(result, null, 2);
                        } catch (jsonError) {
                            this.debugError = `API Test - Non-JSON response (Status: ${response.status})`;
                            this.debugResult = `Raw Response:\n${responseText}`;
                        }
                    } catch (err) {
                        this.debugError = 'API Test Network error: ' + err.message;
                    } finally {
                        this.loading = false;
                    }
                },

                async testUdeaConnection() {
                    this.loading = true;
                    this.debugResult = '';
                    this.debugError = '';

                    try {
                        const response = await fetch('/api/test-scraper/test-udea-connection', {
                            headers: {
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                            }
                        });

                        const responseText = await response.text();
                        
                        try {
                            const result = JSON.parse(responseText);
                            this.debugResult = JSON.stringify(result, null, 2);
                        } catch (jsonError) {
                            this.debugError = `Udea Connection - Non-JSON response (Status: ${response.status})`;
                            this.debugResult = `Raw Response:\n${responseText}`;
                        }
                    } catch (err) {
                        this.debugError = 'Udea Connection Network error: ' + err.message;
                    } finally {
                        this.loading = false;
                    }
                },

                async findLoginUrl() {
                    this.loading = true;
                    this.debugResult = '';
                    this.debugError = '';

                    try {
                        const response = await fetch('/api/test-scraper/find-login-url', {
                            headers: {
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                            }
                        });

                        const responseText = await response.text();
                        
                        try {
                            const result = JSON.parse(responseText);
                            this.debugResult = JSON.stringify(result, null, 2);
                        } catch (jsonError) {
                            this.debugError = `Find Login URL - Non-JSON response (Status: ${response.status})`;
                            this.debugResult = `Raw Response:\n${responseText}`;
                        }
                    } catch (err) {
                        this.debugError = 'Find Login URL Network error: ' + err.message;
                    } finally {
                        this.loading = false;
                    }
                },

                async debugSearchRaw() {
                    this.loading = true;
                    this.debugResult = '';
                    this.debugError = '';

                    try {
                        const response = await fetch('/api/test-scraper/debug-search-raw', {
                            headers: {
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                            }
                        });

                        const responseText = await response.text();
                        
                        try {
                            const result = JSON.parse(responseText);
                            this.debugResult = JSON.stringify(result, null, 2);
                        } catch (jsonError) {
                            this.debugError = `Debug Search Raw - Non-JSON response (Status: ${response.status})`;
                            this.debugResult = `Raw Response:\n${responseText.substring(0, 2000)}`;
                        }
                    } catch (err) {
                        this.debugError = 'Debug Search Raw Network error: ' + err.message;
                    } finally {
                        this.loading = false;
                    }
                },

                async debugLoginPage() {
                    this.loading = true;
                    this.debugResult = '';
                    this.debugError = '';

                    try {
                        const response = await fetch('/api/test-scraper/debug-login-page', {
                            headers: {
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                            }
                        });

                        const responseText = await response.text();
                        
                        // Try to parse as JSON first
                        let result;
                        try {
                            result = JSON.parse(responseText);
                        } catch (jsonError) {
                            // If not JSON, show the raw response
                            this.debugError = `Server returned non-JSON response (Status: ${response.status})`;
                            this.debugResult = `Raw Response:\n${responseText.substring(0, 2000)}${responseText.length > 2000 ? '\n... (truncated)' : ''}`;
                            return;
                        }

                        if (result.success) {
                            this.debugResult = JSON.stringify(result, null, 2);
                        } else {
                            this.debugError = result.error || 'Unknown error occurred';
                            if (result.config_check) {
                                this.debugResult = JSON.stringify(result, null, 2);
                            }
                        }
                    } catch (err) {
                        this.debugError = 'Network error: ' + err.message;
                    } finally {
                        this.loading = false;
                    }
                }
            }
        }

        function cacheManager() {
            return {
                loading: false,
                productCode: '',
                message: '',
                success: false,

                async clearCache() {
                    this.loading = true;
                    this.message = '';

                    try {
                        const response = await fetch('/api/test-scraper/clear-cache', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                            },
                            body: JSON.stringify({ 
                                product_code: this.productCode || null 
                            })
                        });

                        const result = await response.json();
                        this.success = result.success;
                        this.message = result.message || (result.success ? 'Cache cleared successfully' : 'Failed to clear cache');
                        
                        if (result.success) {
                            this.productCode = '';
                        }
                    } catch (err) {
                        this.success = false;
                        this.message = 'Network error: ' + err.message;
                    } finally {
                        this.loading = false;
                        
                        // Clear message after 3 seconds
                        setTimeout(() => {
                            this.message = '';
                        }, 3000);
                    }
                }
            }
        }
    </script>
</x-app-layout>