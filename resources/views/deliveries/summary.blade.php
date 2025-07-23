<x-admin-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <div>
                <h2 class="text-2xl font-bold text-gray-900 dark:text-gray-100">Delivery Summary - {{ $delivery->delivery_number }}</h2>
                <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                    {{ $delivery->supplier->Supplier ?? 'Unknown Supplier' }} • {{ $delivery->delivery_date->format('d/m/Y') }}
                    • Status: <span class="px-2 py-1 text-xs font-medium rounded-full {{ $delivery->status_badge_class }}">{{ ucfirst($delivery->status) }}</span>
                </p>
            </div>
            <div class="flex gap-3">
                <a href="{{ route('deliveries.show', $delivery) }}" 
                   class="inline-flex items-center px-4 py-2 bg-gray-600 hover:bg-gray-700 text-white font-medium rounded-md transition-colors duration-200">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                    </svg>
                    Back to Delivery
                </a>
                
                @if($summary['discrepancies']->count() > 0)
                    <a href="{{ route('deliveries.export-discrepancies', $delivery) }}" 
                       class="inline-flex items-center px-4 py-2 bg-orange-600 hover:bg-orange-700 text-white font-medium rounded-md transition-colors duration-200">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                        </svg>
                        Export Discrepancies
                    </a>
                @endif
                
                @if($delivery->status === 'receiving')
                    <a href="{{ route('deliveries.scan', $delivery) }}" 
                       class="inline-flex items-center px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white font-medium rounded-md transition-colors duration-200">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                        </svg>
                        Continue Scanning
                    </a>
                @endif
                
                @if($delivery->status === 'receiving' && $summary['missing_items'] == 0)
                    <form method="POST" action="{{ route('deliveries.complete', $delivery) }}" class="inline">
                        @csrf
                        <button type="submit" 
                                onclick="return confirm('Complete delivery and update stock? This cannot be undone.')"
                                class="inline-flex items-center px-4 py-2 bg-green-600 hover:bg-green-700 text-white font-medium rounded-md transition-colors duration-200">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                            </svg>
                            Complete & Update Stock
                        </button>
                    </form>
                @endif
            </div>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            
            <x-alert type="success" :message="session('success')" />
            <x-alert type="error" :message="session('error')" />

            <!-- Summary Overview Cards -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                    <div class="flex items-center">
                        <div class="p-2 bg-blue-100 rounded-lg">
                            <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                            </svg>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm text-gray-600 dark:text-gray-400">Total Items</p>
                            <p class="text-2xl font-bold text-gray-900 dark:text-gray-100">{{ $summary['total_items'] }}</p>
                        </div>
                    </div>
                </div>

                <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                    <div class="flex items-center">
                        <div class="p-2 bg-green-100 rounded-lg">
                            <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm text-gray-600 dark:text-gray-400">Complete Items</p>
                            <p class="text-2xl font-bold text-gray-900 dark:text-gray-100">{{ $summary['complete_items'] }}</p>
                            <p class="text-xs text-green-600">{{ number_format(($summary['complete_items'] / $summary['total_items']) * 100, 1) }}%</p>
                        </div>
                    </div>
                </div>

                <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                    <div class="flex items-center">
                        <div class="p-2 bg-red-100 rounded-lg">
                            <svg class="w-6 h-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.996-.833-2.464 0L3.34 16.5c-.77.833.192 2.5 1.732 2.5z"/>
                            </svg>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm text-gray-600 dark:text-gray-400">Issues</p>
                            <p class="text-2xl font-bold text-gray-900 dark:text-gray-100">
                                {{ $summary['partial_items'] + $summary['missing_items'] + $summary['excess_items'] }}
                            </p>
                            <p class="text-xs text-red-600">
                                {{ $summary['partial_items'] }} partial, {{ $summary['missing_items'] }} missing, {{ $summary['excess_items'] }} excess
                            </p>
                        </div>
                    </div>
                </div>

                <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                    <div class="flex items-center">
                        <div class="p-2 bg-yellow-100 rounded-lg">
                            <svg class="w-6 h-6 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1"/>
                            </svg>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm text-gray-600 dark:text-gray-400">Value Difference</p>
                            <p class="text-2xl font-bold text-gray-900 dark:text-gray-100">
                                €{{ number_format($summary['total_received_value'] - $summary['total_expected_value'], 2) }}
                            </p>
                            <p class="text-xs text-gray-600 dark:text-gray-400">
                                Expected: €{{ number_format($summary['total_expected_value'], 2) }}<br>
                                Received: €{{ number_format($summary['total_received_value'], 2) }}
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Status Breakdown -->
            <div class="grid grid-cols-2 md:grid-cols-5 gap-4 mb-8">
                <div class="bg-green-50 dark:bg-green-900/20 rounded-lg p-4 text-center">
                    <div class="text-3xl font-bold text-green-600">{{ $summary['complete_items'] }}</div>
                    <div class="text-sm text-gray-600 dark:text-gray-400">Complete</div>
                    <div class="text-xs text-gray-500">
                        {{ $summary['total_items'] > 0 ? number_format(($summary['complete_items'] / $summary['total_items']) * 100, 1) : 0 }}%
                    </div>
                </div>
                <div class="bg-yellow-50 dark:bg-yellow-900/20 rounded-lg p-4 text-center">
                    <div class="text-3xl font-bold text-yellow-600">{{ $summary['partial_items'] }}</div>
                    <div class="text-sm text-gray-600 dark:text-gray-400">Partial</div>
                    <div class="text-xs text-gray-500">
                        {{ $summary['total_items'] > 0 ? number_format(($summary['partial_items'] / $summary['total_items']) * 100, 1) : 0 }}%
                    </div>
                </div>
                <div class="bg-red-50 dark:bg-red-900/20 rounded-lg p-4 text-center">
                    <div class="text-3xl font-bold text-red-600">{{ $summary['missing_items'] }}</div>
                    <div class="text-sm text-gray-600 dark:text-gray-400">Missing</div>
                    <div class="text-xs text-gray-500">
                        {{ $summary['total_items'] > 0 ? number_format(($summary['missing_items'] / $summary['total_items']) * 100, 1) : 0 }}%
                    </div>
                </div>
                <div class="bg-purple-50 dark:bg-purple-900/20 rounded-lg p-4 text-center">
                    <div class="text-3xl font-bold text-purple-600">{{ $summary['excess_items'] }}</div>
                    <div class="text-sm text-gray-600 dark:text-gray-400">Excess</div>
                    <div class="text-xs text-gray-500">
                        {{ $summary['total_items'] > 0 ? number_format(($summary['excess_items'] / $summary['total_items']) * 100, 1) : 0 }}%
                    </div>
                </div>
                <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-4 text-center">
                    <div class="text-3xl font-bold text-gray-600">{{ $summary['unmatched_scans'] }}</div>
                    <div class="text-sm text-gray-600 dark:text-gray-400">Unknown</div>
                    <div class="text-xs text-gray-500">Unmatched scans</div>
                </div>
            </div>

            @if($summary['discrepancies']->count() > 0)
                <!-- Discrepancies Table -->
                <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg overflow-hidden mb-8">
                    <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                        <div class="flex justify-between items-center">
                            <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">Discrepancies Requiring Attention</h3>
                            <span class="text-sm text-gray-600 dark:text-gray-400">{{ $summary['discrepancies']->count() }} items</span>
                        </div>
                    </div>
                    
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead class="bg-gray-50 dark:bg-gray-700">
                                <tr>
                                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                        Image
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                        Product
                                    </th>
                                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                        Ordered
                                    </th>
                                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                        Received
                                    </th>
                                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                        Difference
                                    </th>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                        Value Impact
                                    </th>
                                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                        Status
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                @foreach($summary['discrepancies'] as $discrepancy)
                                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                        <td class="px-6 py-4 text-center">
                                            @php
                                                $item = $delivery->items->where('supplier_code', $discrepancy['code'])->first();
                                            @endphp
                                            <div class="mx-auto">
                                                @if($item && $item->product)
                                                    <x-product-image 
                                                        :product="$item->product" 
                                                        :supplier-service="$supplierService" 
                                                        size="md" 
                                                        :hover="true"
                                                        hover-size="w-48 h-48" />
                                                @elseif($item && $item->barcode && $item->is_new_product)
                                                    @php
                                                        // For new products, create a temporary product-like object
                                                        $tempProduct = (object)[
                                                            'NAME' => $discrepancy['description'],
                                                            'supplier' => (object)['SupplierID' => $delivery->supplier_id],
                                                            'barcode' => $item->barcode
                                                        ];
                                                    @endphp
                                                    <x-product-image 
                                                        :product="$tempProduct" 
                                                        :supplier-service="$supplierService" 
                                                        size="md" 
                                                        :hover="true"  
                                                        hover-size="w-48 h-48" />
                                                @else
                                                    <x-product-image 
                                                        :product="null" 
                                                        size="md" 
                                                        :fallback="true" />
                                                @endif
                                            </div>
                                        </td>
                                        <td class="px-6 py-4">
                                            <div class="text-sm font-medium text-gray-900 dark:text-gray-100">
                                                {{ $discrepancy['description'] }}
                                            </div>
                                            <div class="text-sm text-gray-500 dark:text-gray-400">
                                                Code: {{ $discrepancy['code'] }}
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 text-center text-sm text-gray-900 dark:text-gray-100">
                                            {{ $discrepancy['ordered'] }}
                                        </td>
                                        <td class="px-6 py-4 text-center text-sm text-gray-900 dark:text-gray-100">
                                            {{ $discrepancy['received'] }}
                                        </td>
                                        <td class="px-6 py-4 text-center text-sm">
                                            <span class="{{ $discrepancy['difference'] > 0 ? 'text-green-600' : 'text-red-600' }} font-medium">
                                                {{ $discrepancy['difference'] > 0 ? '+' : '' }}{{ $discrepancy['difference'] }}
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 text-right text-sm">
                                            <span class="{{ $discrepancy['value_difference'] > 0 ? 'text-green-600' : 'text-red-600' }} font-medium">
                                                {{ $discrepancy['value_difference'] > 0 ? '+' : '' }}€{{ number_format($discrepancy['value_difference'], 2) }}
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 text-center">
                                            @if($discrepancy['difference'] > 0)
                                                <span class="px-2 py-1 text-xs font-medium bg-purple-100 text-purple-800 rounded-full">
                                                    Excess
                                                </span>
                                            @elseif($discrepancy['received'] == 0)
                                                <span class="px-2 py-1 text-xs font-medium bg-red-100 text-red-800 rounded-full">
                                                    Missing
                                                </span>
                                            @else
                                                <span class="px-2 py-1 text-xs font-medium bg-yellow-100 text-yellow-800 rounded-full">
                                                    Partial
                                                </span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @else
                <!-- No Discrepancies Message -->
                <div class="bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-lg p-6 mb-8">
                    <div class="flex items-center">
                        <svg class="w-8 h-8 text-green-600 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        <div>
                            <h3 class="text-lg font-medium text-green-800 dark:text-green-200">Perfect Delivery!</h3>
                            <p class="text-sm text-green-700 dark:text-green-300 mt-1">
                                All items were received exactly as ordered with no discrepancies.
                            </p>
                        </div>
                    </div>
                </div>
            @endif

            @if($delivery->scans->where('matched', false)->count() > 0)
                <!-- Unmatched Scans -->
                <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg overflow-hidden mb-8">
                    <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">Unknown Products Scanned</h3>
                        <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                            Products that were scanned but not found in the delivery manifest
                        </p>
                    </div>
                    
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead class="bg-gray-50 dark:bg-gray-700">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                        Barcode
                                    </th>
                                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                        Quantity
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                        Scanned By
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                        Time
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                @foreach($delivery->scans->where('matched', false) as $scan)
                                    <tr>
                                        <td class="px-6 py-4 text-sm">
                                            <code class="px-2 py-1 bg-red-100 dark:bg-red-900 text-red-800 dark:text-red-200 rounded text-xs">
                                                {{ $scan->barcode }}
                                            </code>
                                        </td>
                                        <td class="px-6 py-4 text-center text-sm text-gray-900 dark:text-gray-100">
                                            {{ $scan->quantity }}
                                        </td>
                                        <td class="px-6 py-4 text-sm text-gray-900 dark:text-gray-100">
                                            {{ $scan->scanned_by }}
                                        </td>
                                        <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">
                                            {{ $scan->created_at->format('d/m/Y H:i:s') }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif

            <!-- Action Recommendations -->
            @if($delivery->status === 'receiving')
                <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-6">
                    <h3 class="text-lg font-medium text-blue-800 dark:text-blue-200 mb-4">Next Steps</h3>
                    
                    @if($summary['missing_items'] > 0)
                        <div class="flex items-start mb-4">
                            <svg class="w-5 h-5 text-red-600 mr-2 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.996-.833-2.464 0L3.34 16.5c-.77.833.192 2.5 1.732 2.5z"/>
                            </svg>
                            <div>
                                <p class="text-sm text-blue-700 dark:text-blue-300">
                                    <strong>{{ $summary['missing_items'] }} items are still missing.</strong>
                                    Continue scanning or contact the supplier about missing products.
                                </p>
                            </div>
                        </div>
                    @endif
                    
                    @if($summary['discrepancies']->count() > 0)
                        <div class="flex items-start mb-4">
                            <svg class="w-5 h-5 text-yellow-600 mr-2 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.996-.833-2.464 0L3.34 16.5c-.77.833.192 2.5 1.732 2.5z"/>
                            </svg>
                            <div>
                                <p class="text-sm text-blue-700 dark:text-blue-300">
                                    <strong>Review discrepancies</strong> and export the report for supplier reconciliation.
                                </p>
                            </div>
                        </div>
                    @endif
                    
                    @if($summary['missing_items'] == 0)
                        <div class="flex items-start">
                            <svg class="w-5 h-5 text-green-600 mr-2 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            <div>
                                <p class="text-sm text-blue-700 dark:text-blue-300">
                                    <strong>Ready to complete!</strong>
                                    All items have been processed. You can now complete the delivery to update stock levels.
                                </p>
                            </div>
                        </div>
                    @endif
                </div>
            @elseif($delivery->status === 'completed')
                <div class="bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-lg p-6">
                    <div class="flex items-center">
                        <svg class="w-8 h-8 text-green-600 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        <div>
                            <h3 class="text-lg font-medium text-green-800 dark:text-green-200">Delivery Completed</h3>
                            <p class="text-sm text-green-700 dark:text-green-300 mt-1">
                                This delivery has been completed and stock levels have been updated. 
                                Final value: €{{ number_format($delivery->total_received ?? 0, 2) }}
                            </p>
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </div>
</x-admin-layout>