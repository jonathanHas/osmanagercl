<x-admin-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <div>
                <h2 class="text-2xl font-bold text-gray-900 dark:text-gray-100">Delivery {{ $delivery->delivery_number }}</h2>
                <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                    {{ $delivery->supplier->Supplier ?? 'Unknown Supplier' }} • {{ $delivery->delivery_date->format('d/m/Y') }}
                </p>
            </div>
            <div class="flex gap-3">
                <a href="{{ route('deliveries.index') }}" 
                   class="inline-flex items-center px-4 py-2 bg-gray-600 hover:bg-gray-700 text-white font-medium rounded-md transition-colors duration-200">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                    </svg>
                    Back to Deliveries
                </a>
                
                @if($delivery->status === 'draft')
                    <a href="{{ route('deliveries.scan', $delivery) }}" 
                       class="inline-flex items-center px-4 py-2 bg-green-600 hover:bg-green-700 text-white font-medium rounded-md transition-colors duration-200">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                        </svg>
                        Start Scanning
                    </a>
                @elseif($delivery->status === 'receiving')
                    <a href="{{ route('deliveries.scan', $delivery) }}" 
                       class="inline-flex items-center px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white font-medium rounded-md transition-colors duration-200">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                        </svg>
                        Continue Scanning
                    </a>
                    <a href="{{ route('deliveries.summary', $delivery) }}" 
                       class="inline-flex items-center px-4 py-2 bg-purple-600 hover:bg-purple-700 text-white font-medium rounded-md transition-colors duration-200">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                        </svg>
                        View Summary
                    </a>
                @elseif($delivery->status === 'completed')
                    <a href="{{ route('deliveries.summary', $delivery) }}" 
                       class="inline-flex items-center px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white font-medium rounded-md transition-colors duration-200">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                        </svg>
                        View Final Report
                    </a>
                @endif
            </div>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            
            @if(session('success'))
                <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-6" role="alert">
                    <span class="block sm:inline">{{ session('success') }}</span>
                </div>
            @endif

            @if(session('error'))
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6" role="alert">
                    <span class="block sm:inline">{{ session('error') }}</span>
                </div>
            @endif

            <!-- Delivery Overview -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                    <div class="flex items-center">
                        <div class="p-2 bg-blue-100 rounded-lg">
                            <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                            </svg>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm text-gray-600 dark:text-gray-400">Total Items</p>
                            <p class="text-2xl font-bold text-gray-900 dark:text-gray-100">{{ $delivery->items->count() }}</p>
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
                            <p class="text-sm text-gray-600 dark:text-gray-400">Progress</p>
                            <p class="text-2xl font-bold text-gray-900 dark:text-gray-100">{{ number_format($delivery->completion_percentage, 0) }}%</p>
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
                            <p class="text-sm text-gray-600 dark:text-gray-400">Expected Value</p>
                            <p class="text-2xl font-bold text-gray-900 dark:text-gray-100">€{{ number_format($delivery->total_expected ?? 0, 2) }}</p>
                        </div>
                    </div>
                </div>

                <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                    <div class="flex items-center">
                        <div class="p-2 bg-purple-100 rounded-lg">
                            <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                            </svg>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm text-gray-600 dark:text-gray-400">Status</p>
                            <p class="text-sm font-medium">
                                <span class="px-2 py-1 rounded-full {{ $delivery->status_badge_class }}">
                                    {{ ucfirst($delivery->status) }}
                                </span>
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Progress Bar -->
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6 mb-8">
                <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">Delivery Progress</h3>
                <div class="w-full bg-gray-200 rounded-full h-4 mb-2">
                    <div class="bg-green-600 h-4 rounded-full transition-all duration-300" 
                         style="width: {{ $delivery->completion_percentage }}%"></div>
                </div>
                <div class="flex justify-between text-sm text-gray-600 dark:text-gray-400">
                    <span>{{ $delivery->items->where('status', '!=', 'pending')->count() }} of {{ $delivery->items->count() }} items processed</span>
                    <span>{{ number_format($delivery->completion_percentage, 1) }}% complete</span>
                </div>
            </div>

            <!-- Items Table -->
            <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                    <div class="flex justify-between items-center">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">Delivery Items</h3>
                        <div class="flex gap-2">
                            <span class="px-2 py-1 text-xs font-medium bg-gray-100 text-gray-800 rounded">
                                {{ $delivery->items->where('status', 'pending')->count() }} Pending
                            </span>
                            <span class="px-2 py-1 text-xs font-medium bg-blue-100 text-blue-800 rounded">
                                {{ $delivery->items->where('status', 'partial')->count() }} Partial
                            </span>
                            <span class="px-2 py-1 text-xs font-medium bg-green-100 text-green-800 rounded">
                                {{ $delivery->items->where('status', 'complete')->count() }} Complete
                            </span>
                            <span class="px-2 py-1 text-xs font-medium bg-orange-100 text-orange-800 rounded">
                                {{ $delivery->items->where('status', 'excess')->count() }} Excess
                            </span>
                        </div>
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
                                    Barcode
                                </th>
                                <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                    Ordered
                                </th>
                                <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                    Received
                                </th>
                                <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                    Status
                                </th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                    Cost
                                </th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                    Actions
                                </th>
                            </tr>
                        </thead>
                        <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                            @foreach($delivery->items as $item)
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                    <td class="px-6 py-4 text-center">
                                        @php
                                            $imageUrl = null;
                                            $hasIntegration = false;
                                            
                                            if ($item->product && $item->product->supplier && $supplierService->hasExternalIntegration($item->product->supplier->SupplierID)) {
                                                // Existing product with supplier integration
                                                $imageUrl = $supplierService->getExternalImageUrl($item->product);
                                                $hasIntegration = true;
                                            } elseif ($item->barcode && $item->is_new_product && $supplierService->hasExternalIntegration($delivery->supplier_id)) {
                                                // New product with barcode and supplier integration
                                                $imageUrl = $supplierService->getExternalImageUrlByBarcode($delivery->supplier_id, $item->barcode);
                                                $hasIntegration = true;
                                            }
                                        @endphp

                                        @if($hasIntegration && $imageUrl)
                                            <div class="relative w-10 h-10 mx-auto group">
                                                <img 
                                                    src="{{ $imageUrl }}" 
                                                    alt="{{ $item->description }}"
                                                    class="w-10 h-10 object-cover rounded border border-gray-200 dark:border-gray-700 animate-pulse cursor-pointer"
                                                    loading="lazy"
                                                    onload="this.classList.remove('animate-pulse')"
                                                    onerror="this.style.display='none'; this.parentElement.style.display='none'"
                                                >
                                                <!-- Hover preview -->
                                                <div class="absolute left-1/2 transform -translate-x-1/2 bottom-full mb-2 z-50 opacity-0 group-hover:opacity-100 transition-opacity duration-200 pointer-events-none">
                                                    <img 
                                                        src="{{ $imageUrl }}" 
                                                        alt="{{ $item->description }}"
                                                        class="w-48 h-48 object-cover rounded-lg border-2 border-white dark:border-gray-600 shadow-xl"
                                                        loading="lazy"
                                                    >
                                                    <div class="absolute inset-x-0 bottom-0 bg-black bg-opacity-75 text-white text-xs p-2 rounded-b-lg">
                                                        {{ $item->description }}
                                                        @if($item->barcode)
                                                            <br><span class="text-gray-300 text-xs">{{ $item->barcode }}</span>
                                                        @endif
                                                    </div>
                                                </div>
                                            </div>
                                        @else
                                            <div class="w-10 h-10 mx-auto bg-gray-100 dark:bg-gray-700 rounded border border-gray-200 dark:border-gray-600 flex items-center justify-center">
                                                <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                                </svg>
                                            </div>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="text-sm font-medium text-gray-900 dark:text-gray-100">
                                            {{ $item->description }}
                                        </div>
                                        <div class="text-sm text-gray-500 dark:text-gray-400">
                                            Code: {{ $item->supplier_code }}
                                            @if($item->is_new_product)
                                                <span class="ml-2 px-2 py-1 text-xs font-medium bg-yellow-100 text-yellow-800 rounded">
                                                    New Product
                                                </span>
                                            @endif
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 text-center text-sm">
                                        @if($item->barcode)
                                            <code class="px-2 py-1 bg-gray-100 dark:bg-gray-700 rounded text-xs">
                                                {{ $item->barcode }}
                                            </code>
                                        @else
                                            <span class="text-gray-400 text-xs">
                                                @if($item->is_new_product)
                                                    Retrieving...
                                                @else
                                                    No barcode
                                                @endif
                                            </span>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 text-center text-sm text-gray-900 dark:text-gray-100">
                                        {{ $item->ordered_quantity }}
                                    </td>
                                    <td class="px-6 py-4 text-center text-sm text-gray-900 dark:text-gray-100">
                                        {{ $item->received_quantity }}
                                        @if($item->received_quantity != $item->ordered_quantity)
                                            <span class="text-xs text-gray-500 block">
                                                ({{ $item->received_quantity > $item->ordered_quantity ? '+' : '' }}{{ $item->received_quantity - $item->ordered_quantity }})
                                            </span>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 text-center">
                                        <span class="px-2 py-1 text-xs font-medium rounded-full {{ $item->status_badge_class }}">
                                            {{ ucfirst($item->status) }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 text-right text-sm">
                                        <div class="text-gray-900 dark:text-gray-100">
                                            €{{ number_format($item->unit_cost * $item->ordered_quantity, 2) }}
                                        </div>
                                        @if($item->received_quantity > 0)
                                            <div class="text-xs text-gray-500">
                                                Received: €{{ number_format($item->unit_cost * $item->received_quantity, 2) }}
                                            </div>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 text-right text-sm font-medium">
                                        @if($item->is_new_product && !$item->barcode)
                                            <form method="POST" action="{{ route('delivery-items.refresh-barcode', $item) }}" class="inline">
                                                @csrf
                                                <button type="submit" 
                                                        class="text-blue-600 hover:text-blue-900 dark:text-blue-400 dark:hover:text-blue-300 text-xs">
                                                    Refresh Barcode
                                                </button>
                                            </form>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            @if($delivery->scans->where('matched', false)->count() > 0)
                <!-- Unmatched Scans -->
                <div class="mt-8 bg-white dark:bg-gray-800 shadow-sm rounded-lg overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">Unmatched Scans</h3>
                        <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                            Products scanned but not found in the delivery manifest
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
                                            {{ $scan->created_at->format('H:i:s') }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif
        </div>
    </div>
</x-admin-layout>