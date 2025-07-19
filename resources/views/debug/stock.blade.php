<x-admin-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Stock Debug Information
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            
            <!-- Raw STOCKCURRENT Data -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                <h3 class="text-lg font-semibold mb-4">Raw STOCKCURRENT Data (First 10 rows)</h3>
                @if($stockData->count() > 0)
                    <div class="overflow-x-auto">
                        <pre class="bg-gray-100 p-4 rounded overflow-x-auto">{{ json_encode($stockData, JSON_PRETTY_PRINT) }}</pre>
                    </div>
                @else
                    <span class="text-red-600">No data found in STOCKCURRENT table</span>
                @endif
            </div>

            <!-- Product-Stock Relationship Tests -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                <h3 class="text-lg font-semibold mb-4">Product-Stock Relationship Tests</h3>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Product ID</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Product Name</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Raw Query</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Model Query</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">getCurrentStock()</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @foreach($stockTests as $test)
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-mono">{{ $test['product_id'] }}</td>
                                <td class="px-6 py-4 text-sm">{{ $test['product_name'] }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm">
                                    <span class="{{ $test['raw_stock_query'] !== 'NOT FOUND' ? 'text-green-600' : 'text-red-600' }}">
                                        {{ $test['raw_stock_query'] }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm">
                                    <span class="{{ $test['model_stock_query'] !== 'NOT FOUND' ? 'text-green-600' : 'text-red-600' }}">
                                        {{ $test['model_stock_query'] }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm">{{ $test['getCurrentStock_method'] }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Products with Stock Relationships -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                <h3 class="text-lg font-semibold mb-4">Products with Stock Relationships (First 10)</h3>
                <div class="space-y-4">
                    @foreach($productsWithStock as $product)
                    <div class="border-l-4 {{ $product->stockCurrent ? 'border-green-500 bg-green-50' : 'border-gray-300 bg-gray-50' }} p-4">
                        <div class="flex justify-between items-start">
                            <div>
                                <h4 class="font-semibold">{{ $product->NAME }}</h4>
                                <p class="text-sm text-gray-600">ID: {{ $product->ID }}</p>
                            </div>
                            <div class="text-right">
                                @if($product->stockCurrent)
                                    <span class="text-green-600 font-semibold">{{ $product->stockCurrent->UNITS }} units</span>
                                    <p class="text-xs text-gray-500">Location: {{ $product->stockCurrent->LOCATION }}</p>
                                @else
                                    <span class="text-gray-400">No stock record</span>
                                @endif
                            </div>
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>

            <!-- Debug Information -->
            <div class="bg-blue-50 border border-blue-200 rounded p-4">
                <h4 class="font-semibold text-blue-800 mb-2">Debug Notes</h4>
                <ul class="text-sm text-blue-700 space-y-1">
                    <li>• Check if PRODUCT IDs in STOCKCURRENT match the Product model IDs</li>
                    <li>• Verify the table name is correct (STOCKCURRENT vs stockcurrent)</li>
                    <li>• Ensure the relationship keys are correct (PRODUCT ↔ ID)</li>
                    <li>• Check if there are multiple locations affecting the relationship</li>
                </ul>
            </div>

        </div>
    </div>
</x-admin-layout>