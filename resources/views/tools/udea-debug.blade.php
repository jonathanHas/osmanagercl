<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                Udea Scraper Diagnostics
            </h2>
            <span class="text-sm text-gray-500 dark:text-gray-400">Quickly test supplier lookups & cache hits</span>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-900 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900 dark:text-gray-100 space-y-6">
                    <form method="GET" action="{{ route('tools.udea-debug') }}" class="space-y-4">
                        <div>
                            <label for="supplier_code" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                Supplier / Article Code
                            </label>
                            <input
                                type="text"
                                id="supplier_code"
                                name="supplier_code"
                                value="{{ $supplierCode ?? '' }}"
                                required
                                class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                placeholder="e.g. 97393"
                            />
                        </div>

                        <div class="flex items-center">
                            <input
                                type="checkbox"
                                id="fresh"
                                name="fresh"
                                value="1"
                                class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500"
                                @checked(request()->boolean('fresh'))
                            >
                            <label for="fresh" class="ml-2 text-sm text-gray-600 dark:text-gray-300">
                                Force fresh scrape (bypass cached result)
                            </label>
                        </div>

                        <div class="flex items-center justify-end space-x-3">
                            @if($supplierCode)
                                <a
                                    href="{{ route('tools.udea-debug') }}"
                                    class="inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 text-sm font-medium rounded-md text-gray-700 dark:text-gray-200 bg-white dark:bg-gray-800 hover:bg-gray-50"
                                >
                                    Reset
                                </a>
                            @endif
                            <button
                                type="submit"
                                class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                            >
                                Run Lookup
                            </button>
                        </div>
                    </form>

                    @if($error)
                        <div class="rounded-md bg-red-50 dark:bg-red-900/30 p-4">
                            <div class="flex">
                                <div class="flex-shrink-0">
                                    <svg class="h-5 w-5 text-red-400" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm.707-10.293a1 1 0 00-1.414 0L7 10l2.293 2.293a1 1 0 001.414-1.414L9.414 10l1.293-1.293a1 1 0 000-1.414z" clip-rule="evenodd"/>
                                    </svg>
                                </div>
                                <div class="ml-3">
                                    <h3 class="text-sm font-medium text-red-800 dark:text-red-200">
                                        Lookup failed
                                    </h3>
                                    <div class="mt-2 text-sm text-red-700 dark:text-red-100">
                                        {{ $error }}
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endif

                    @if($result)
                        <div class="border border-gray-200 dark:border-gray-700 rounded-lg divide-y divide-gray-200 dark:divide-gray-700">
                            <div class="p-4 bg-gray-50 dark:bg-gray-800/60 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
                                <div>
                                    <p class="text-sm text-gray-500 dark:text-gray-400 uppercase tracking-wide">Lookup Details</p>
                                    <p class="text-lg font-semibold text-gray-900 dark:text-gray-100">
                                        Code: <code>{{ $result['supplier_code'] }}</code>
                                    </p>
                                </div>
                                <div class="text-sm text-gray-600 dark:text-gray-300 space-y-1">
                                    <p>Forced refresh: <span class="font-medium">{{ $result['fresh_request'] ? 'Yes' : 'No' }}</span></p>
                                    <p>Served from cache: <span class="font-medium">{{ $result['from_cache'] ? 'Yes' : 'No' }}</span></p>
                                </div>
                            </div>
                            <div class="p-4 grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <p class="text-xs uppercase text-gray-500 dark:text-gray-400">Barcode</p>
                                    <p class="text-base font-mono">
                                        {{ $result['data']['barcode'] ?? '—' }}
                                    </p>
                                </div>
                                <div>
                                    <p class="text-xs uppercase text-gray-500 dark:text-gray-400">Description</p>
                                    <p class="text-base">
                                        {{ $result['data']['description'] ?? '—' }}
                                    </p>
                                </div>
                                <div>
                                    <p class="text-xs uppercase text-gray-500 dark:text-gray-400">Case Price</p>
                                    <p class="text-base">
                                        {{ $result['data']['case_price'] ?? '—' }}
                                    </p>
                                </div>
                                <div>
                                    <p class="text-xs uppercase text-gray-500 dark:text-gray-400">Units / Case</p>
                                    <p class="text-base">
                                        {{ $result['data']['units_per_case'] ?? '—' }}
                                    </p>
                                </div>
                            </div>
                            <div class="p-4">
                                <p class="text-xs uppercase text-gray-500 dark:text-gray-400 mb-2">Raw payload</p>
                                <pre class="text-xs bg-gray-900 text-green-300 rounded-lg p-4 overflow-auto">{{ json_encode($result['data'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre>
                            </div>
                        </div>
                    @elseif($supplierCode === '' && ! $error)
                        <p class="text-sm text-gray-500 dark:text-gray-400">
                            Enter a supplier code above to run the scraper test. Use “Force fresh scrape” when you need to bypass the cached result.
                        </p>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
