<x-admin-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <div>
                <div class="flex items-center space-x-3 mb-2">
                    @php
                        $supplierName = $delivery->supplier->Supplier ?? 'Unknown Supplier';
                        $supplierBadgeClass = match(strtolower($supplierName)) {
                            'udea' => 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-300',
                            'ekoplaza' => 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300',
                            'bidfood' => 'bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-300',
                            default => 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300'
                        };
                    @endphp
                    <span class="{{ $supplierBadgeClass }} inline-flex items-center px-3 py-1 rounded-full text-sm font-semibold">
                        {{ $supplierName }}
                    </span>
                    <span class="px-2 py-1 text-xs font-medium rounded-full {{ $delivery->status_badge_class }}">
                        {{ ucfirst($delivery->status) }}
                    </span>
                </div>
                <h2 class="text-2xl font-bold text-gray-900 dark:text-gray-100">Delivery {{ $delivery->delivery_number }}</h2>
                <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                    Delivered on {{ $delivery->delivery_date->format('l, d/m/Y') }}
                </p>
            </div>
            @php
                $deliveryActions = [
                    [
                        'type' => 'link',
                        'route' => 'deliveries.index',
                        'label' => 'Back to Deliveries',
                        'color' => 'gray',
                        'class' => 'inline-flex items-center px-4 py-2 bg-gray-600 hover:bg-gray-700 text-white font-medium rounded-md transition-colors duration-200',
                        'icon' => 'M10 19l-7-7m0 0l7-7m-7 7h18'
                    ]
                ];
                
                if($delivery->status === 'draft') {
                    $deliveryActions[] = [
                        'type' => 'link',
                        'route' => 'deliveries.scan',
                        'params' => $delivery,
                        'label' => 'Start Scanning',
                        'color' => 'green',
                        'class' => 'inline-flex items-center px-4 py-2 bg-green-600 hover:bg-green-700 text-white font-medium rounded-md transition-colors duration-200',
                        'icon' => 'M12 4v16m8-8H4'
                    ];
                } elseif($delivery->status === 'receiving') {
                    $deliveryActions[] = [
                        'type' => 'link',
                        'route' => 'deliveries.scan',
                        'params' => $delivery,
                        'label' => 'Continue Scanning',
                        'color' => 'blue',
                        'class' => 'inline-flex items-center px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white font-medium rounded-md transition-colors duration-200',
                        'icon' => 'M13 10V3L4 14h7v7l9-11h-7z'
                    ];
                    $deliveryActions[] = [
                        'type' => 'link',
                        'route' => 'deliveries.summary',
                        'params' => $delivery,
                        'label' => 'View Summary',
                        'color' => 'info',
                        'class' => 'inline-flex items-center px-4 py-2 bg-purple-600 hover:bg-purple-700 text-white font-medium rounded-md transition-colors duration-200',
                        'icon' => 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z'
                    ];
                } elseif($delivery->status === 'completed') {
                    $deliveryActions[] = [
                        'type' => 'link',
                        'route' => 'deliveries.summary',
                        'params' => $delivery,
                        'label' => 'View Final Report',
                        'color' => 'indigo',
                        'class' => 'inline-flex items-center px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white font-medium rounded-md transition-colors duration-200',
                        'icon' => 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z'
                    ];
                }
                
                // Add delete action for non-completed deliveries
                if (in_array($delivery->status, ['draft', 'cancelled'])) {
                    $deliveryActions[] = [
                        'type' => 'form',
                        'method' => 'DELETE',
                        'route' => 'deliveries.destroy',
                        'params' => $delivery,
                        'label' => 'Delete Delivery',
                        'color' => 'red',
                        'class' => 'inline-flex items-center px-4 py-2 bg-red-600 hover:bg-red-700 text-white font-medium rounded-md transition-colors duration-200',
                        'icon' => 'M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16',
                        'onclick' => "return confirm('Are you sure you want to delete this delivery? This action cannot be undone.')"
                    ];
                }
            @endphp
            
            <x-action-buttons :actions="$deliveryActions" spacing="loose" size="lg" />
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-full mx-auto px-4 sm:px-6 lg:px-8">
            
            <x-alert type="success" :message="session('success')" />
            <x-alert type="error" :message="session('error')" />

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

            <!-- Progress Bar with Price Legend -->
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4 mb-6">
                <div class="flex items-center justify-between mb-3">
                    <div class="flex items-center gap-4">
                        <h3 class="text-base font-medium text-gray-900 dark:text-gray-100">Progress</h3>
                        <div class="flex-1 max-w-md">
                            <div class="w-full bg-gray-200 rounded-full h-2">
                                <div class="bg-green-600 h-2 rounded-full transition-all duration-300" 
                                     style="width: {{ $delivery->completion_percentage }}%"></div>
                            </div>
                        </div>
                        <span class="text-sm text-gray-600 dark:text-gray-400">
                            {{ $delivery->items->where('status', '!=', 'pending')->count() }}/{{ $delivery->items->count() }} 
                            ({{ number_format($delivery->completion_percentage, 0) }}%)
                        </span>
                    </div>
                </div>
                
                <div class="flex items-center justify-between text-xs text-gray-600 dark:text-gray-400 pt-2 border-t border-gray-200 dark:border-gray-700">
                    <div class="flex items-center gap-4">
                        <div class="flex items-center gap-2">
                            <span class="font-medium">Price Changes:</span>
                            <div class="flex gap-2">
                                <span class="px-1.5 py-0.5 bg-green-100 dark:bg-green-900/30 rounded text-xs">-10%+</span>
                                <span class="px-1.5 py-0.5 bg-blue-100 dark:bg-blue-900/30 rounded text-xs">-5%</span>
                                <span class="px-1.5 py-0.5 bg-yellow-100 dark:bg-yellow-900/30 rounded text-xs">+5%</span>
                                <span class="px-1.5 py-0.5 bg-red-100 dark:bg-red-900/30 rounded text-xs">+10%+</span>
                            </div>
                        </div>
                        <div class="flex items-center gap-1">
                            <svg class="w-3 h-3 text-red-500" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                            </svg>
                            <span class="text-red-600 dark:text-red-400 font-medium">Zero/Negative Margin</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Items Table -->
            <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg">
                <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                    <div class="flex justify-between items-center">
                        <div class="flex items-center gap-4">
                            <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">Delivery Items</h3>
                            
                            @if($delivery->status !== 'completed')
                                <!-- Auto Update Costs Button -->
                                <button onclick="autoUpdateCosts()" id="autoUpdateBtn"
                                        class="px-3 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm rounded-md transition-colors duration-200 flex items-center gap-2">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                                    </svg>
                                    Auto Update Costs
                                </button>
                                
                                <!-- Create New Product Button -->
                                <button onclick="alert('New product creation is available in the scanning interface. Use the \'Continue Scanning\' button above.')" 
                                        class="px-3 py-2 bg-green-600 hover:bg-green-700 text-white text-sm rounded-md transition-colors duration-200 flex items-center gap-2">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                                    </svg>
                                    New Product
                                </button>
                            @endif
                            
                            <!-- Sort Dropdown -->
                            <div class="flex items-center gap-2">
                                <span class="text-sm text-gray-600 dark:text-gray-400">Sort by:</span>
                                <select id="sortSelect" onchange="sortDeliveryItems()" 
                                        class="text-sm border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-md">
                                    <option value="new_first">New Products First</option>
                                    <option value="product">Product Name</option>
                                    <option value="code">Code</option>
                                    <option value="description">Description</option>
                                    <option value="status">Status</option>
                                    <option value="margin">Margin</option>
                                </select>
                            </div>
                        </div>
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
                
                <div class="relative overflow-x-auto max-h-[calc(100vh-24rem)] overflow-y-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 delivery-items-table">
                        <thead class="bg-gray-50 dark:bg-gray-700 sticky top-0 z-10 shadow-sm">
                            <tr>
                                <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                    Image
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider cursor-pointer hover:bg-gray-100 dark:hover:bg-gray-600 transition-colors"
                                    onclick="sortDeliveryItems('product')" title="Sort by Product">
                                    <div class="flex items-center justify-between">
                                        <span>Product</span>
                                        <svg id="sort-icon-product" class="w-3 h-3 text-gray-400 opacity-50" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16V4m0 0L3 8m4-4l4 4m6 0v12m0 0l4-4m-4 4l-4-4"/>
                                        </svg>
                                    </div>
                                </th>
                                <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                    Barcode
                                </th>
                                <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                    Invoiced
                                </th>
                                <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                    Received
                                </th>
                                <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider cursor-pointer hover:bg-gray-100 dark:hover:bg-gray-600 transition-colors"
                                    onclick="sortDeliveryItems('status')" title="Sort by Status">
                                    <div class="flex items-center justify-center gap-1">
                                        <span>Status</span>
                                        <svg id="sort-icon-status" class="w-3 h-3 text-gray-400 opacity-50" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16V4m0 0L3 8m4-4l4 4m6 0v12m0 0l4-4m-4 4l-4-4"/>
                                        </svg>
                                    </div>
                                </th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                    Delivery Cost
                                </th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                    Current Cost
                                </th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                    RSP
                                </th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                    Current Sell (inc VAT)
                                </th>
                                <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                    Current Stock
                                </th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider cursor-pointer hover:bg-gray-100 dark:hover:bg-gray-600 transition-colors"
                                    onclick="sortDeliveryItems('margin')" title="Sort by Margin">
                                    <div class="flex items-center justify-end gap-1">
                                        <span>Margin</span>
                                        <svg id="sort-icon-margin" class="w-3 h-3 text-gray-400 opacity-50" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16V4m0 0L3 8m4-4l4 4m6 0v12m0 0l4-4m-4 4l-4-4"/>
                                        </svg>
                                    </div>
                                </th>
                                <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                    Tax Rate
                                </th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider cursor-pointer hover:bg-gray-100 dark:hover:bg-gray-600 transition-colors"
                                    onclick="sortDeliveryItems('actions')" title="Sort by Actions (New Products First)">
                                    <div class="flex items-center justify-end gap-1">
                                        <span>Actions</span>
                                        <svg id="sort-icon-actions" class="w-3 h-3 text-gray-400 opacity-50" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16V4m0 0L3 8m4-4l4 4m6 0v12m0 0l4-4m-4 4l-4-4"/>
                                        </svg>
                                    </div>
                                </th>
                            </tr>
                        </thead>
                        <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                            @foreach($delivery->items as $item)
                                @php
                                    // Check if this item wasn't on the delivery (0 invoiced quantity)
                                    $notOnDelivery = $item->ordered_quantity == 0;
                                    
                                    // Determine row background class based on status
                                    if ($item->is_new_product) {
                                        $rowClass = 'bg-green-50 dark:bg-green-900/20 hover:bg-green-100 dark:hover:bg-green-900/30';
                                    } elseif ($notOnDelivery) {
                                        $rowClass = 'bg-amber-50 dark:bg-amber-900/20 hover:bg-amber-100 dark:hover:bg-amber-900/30';
                                    } else {
                                        $rowClass = 'hover:bg-gray-50 dark:hover:bg-gray-700';
                                    }
                                @endphp
                                <tr class="{{ $rowClass }}">
                                    <td class="px-6 py-4 text-center">
                                        <div id="image-cell-{{ $item->id }}" class="mx-auto">
                                            @if($item->product)
                                                <x-product-image 
                                                    :product="$item->product" 
                                                    :supplier-service="$supplierService" 
                                                    size="md" 
                                                    :hover="true" />
                                            @elseif($item->barcode && $item->is_new_product)
                                                @php
                                                    // For new products, create a temporary product-like object
                                                    $tempProduct = (object)[
                                                        'NAME' => $item->description,
                                                        'supplier' => (object)['SupplierID' => $delivery->supplier_id],
                                                        'barcode' => $item->barcode
                                                    ];
                                                @endphp
                                                <x-product-image 
                                                    :product="$tempProduct" 
                                                    :supplier-service="$supplierService" 
                                                    size="md" 
                                                    :hover="true" />
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
                                            @if($item->product)
                                                <a href="{{ route('products.show', $item->product->ID) }}" 
                                                   class="text-blue-600 hover:text-blue-900 dark:text-blue-400 dark:hover:text-blue-300 hover:underline"
                                                   title="View product details">
                                                    {{ $item->description }}
                                                </a>
                                            @else
                                                {{ $item->description }}
                                            @endif
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
                                        <div id="barcode-cell-{{ $item->id }}">
                                            @if($item->barcode)
                                                <code class="px-2 py-1 bg-gray-100 dark:bg-gray-700 rounded text-xs">
                                                    {{ $item->barcode }}
                                                </code>
                                            @elseif($item->barcode_retrieval_failed)
                                                <div class="flex items-center justify-center space-x-1">
                                                    <span class="text-red-500 text-xs">❌ Failed</span>
                                                    @if($item->barcode_retrieval_error)
                                                        <span class="text-gray-400 text-xs cursor-help" title="{{ $item->barcode_retrieval_error }}">ⓘ</span>
                                                    @endif
                                                </div>
                                            @else
                                                <span class="text-gray-400 text-xs" id="barcode-status-{{ $item->id }}">
                                                    @if($item->is_new_product)
                                                        🔄 Retrieving...
                                                    @else
                                                        No barcode
                                                    @endif
                                                </span>
                                            @endif
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 text-center text-sm {{ $item->ordered_quantity == 0 ? 'text-amber-700 dark:text-amber-400 font-medium' : 'text-gray-900 dark:text-gray-100' }}">
                                        {{ $item->ordered_quantity }}
                                        @if($item->ordered_quantity == 0)
                                            <span class="block text-xs text-amber-600 dark:text-amber-500">Not invoiced</span>
                                        @endif
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
                                        @php
                                            // Calculate cost difference if product exists
                                            $currentCost = $item->product ? $item->product->PRICEBUY : null;
                                            $costDifference = $currentCost !== null ? $item->unit_cost - $currentCost : null;
                                            $costPercentChange = $currentCost > 0 ? ($costDifference / $currentCost * 100) : null;
                                            
                                            // Determine highlighting based on percentage change
                                            $costHighlight = '';
                                            if ($costPercentChange !== null) {
                                                if (abs($costPercentChange) >= 10) {
                                                    $costHighlight = $costPercentChange > 0 ? 'bg-red-100 dark:bg-red-900/30' : 'bg-green-100 dark:bg-green-900/30';
                                                } elseif (abs($costPercentChange) >= 5) {
                                                    $costHighlight = $costPercentChange > 0 ? 'bg-yellow-100 dark:bg-yellow-900/30' : 'bg-blue-100 dark:bg-blue-900/30';
                                                }
                                            }
                                        @endphp
                                        <div class="{{ $costHighlight }} rounded px-2 py-1">
                                            <div class="text-gray-900 dark:text-gray-100 font-medium">
                                                €{{ number_format($item->unit_cost, 2) }}
                                            </div>
                                            @if($costDifference !== null && abs($costDifference) > 0.01)
                                                <div class="text-xs {{ $costDifference > 0 ? 'text-red-600 dark:text-red-400' : 'text-green-600 dark:text-green-400' }}">
                                                    {{ $costDifference > 0 ? '+' : '' }}€{{ number_format($costDifference, 2) }}
                                                    ({{ $costDifference > 0 ? '+' : '' }}{{ number_format($costPercentChange, 1) }}%)
                                                </div>
                                            @endif
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 text-right text-sm">
                                        @if($item->product)
                                            <div class="flex items-center justify-end gap-2">
                                                <div>
                                                    <div class="text-gray-900 dark:text-gray-100">
                                                        €{{ number_format($item->product->PRICEBUY, 2) }}
                                                    </div>
                                                    <div class="text-xs text-gray-500">
                                                        in POS
                                                    </div>
                                                </div>
                                                @if($delivery->status !== 'completed' && abs($item->unit_cost - $item->product->PRICEBUY) > 0.01)
                                                    <button onclick="updateProductCost('{{ $item->product->ID }}', {{ $item->unit_cost }}, '{{ addslashes($item->description) }}', event)"
                                                            class="p-1 text-blue-600 hover:text-blue-800 hover:bg-blue-100 rounded transition-colors"
                                                            title="Update cost to €{{ number_format($item->unit_cost, 2) }}">
                                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                                                  d="M13 7l5 5m0 0l-5 5m5-5H6"/>
                                                        </svg>
                                                    </button>
                                                @endif
                                            </div>
                                        @else
                                            <span class="text-gray-400 dark:text-gray-500 text-xs">New Product</span>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 text-right text-sm">
                                        @if($item->sale_price)
                                            <div class="text-gray-900 dark:text-gray-100">
                                                €{{ number_format($item->sale_price, 2) }}
                                            </div>
                                            <div class="text-xs text-gray-500">
                                                RSP
                                            </div>
                                        @else
                                            <span class="text-gray-400 dark:text-gray-500 text-xs">—</span>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 text-right text-sm">
                                        @php
                                            // Calculate sell price difference if product exists
                                            // Get the VAT-inclusive price (gross price)
                                            $currentSell = $item->product ? $item->product->getGrossPrice() : null;
                                            $rsp = $item->sale_price;
                                            $sellDifference = ($currentSell !== null && $rsp !== null) ? $rsp - $currentSell : null;
                                            $sellPercentChange = $currentSell > 0 ? ($sellDifference / $currentSell * 100) : null;
                                            
                                            // Determine highlighting for sell price
                                            $sellHighlight = '';
                                            if ($sellPercentChange !== null) {
                                                if (abs($sellPercentChange) >= 10) {
                                                    $sellHighlight = $sellPercentChange < 0 ? 'bg-orange-100 dark:bg-orange-900/30' : 'bg-green-100 dark:bg-green-900/30';
                                                } elseif (abs($sellPercentChange) >= 5) {
                                                    $sellHighlight = 'bg-yellow-100 dark:bg-yellow-900/30';
                                                }
                                            }
                                        @endphp
                                        @if($item->product && $delivery->status !== 'completed')
                                            @php
                                                $taxRate = 0;
                                                if ($item->product->taxCategory && $item->product->taxCategory->primaryTax) {
                                                    $taxRate = $item->product->taxCategory->primaryTax->RATE;
                                                }
                                            @endphp
                                            <div class="{{ $sellHighlight }} rounded px-2 py-1 cursor-pointer hover:ring-2 hover:ring-blue-300 transition-all group"
                                                 onclick="openPriceEditor({{ $item->id }}, '{{ $item->product->CODE }}', '{{ addslashes($item->description) }}', {{ $item->product->PRICESELL }}, {{ $item->unit_cost }}, {{ $taxRate }}, {{ $item->sale_price ?? 0 }})"
                                                 title="Click to edit price">
                                                <div class="flex items-center justify-between">
                                                    <div>
                                                        <div class="text-gray-900 dark:text-gray-100 font-medium">
                                                            €{{ number_format($currentSell, 2) }}
                                                        </div>
                                                        @if($sellDifference !== null && abs($sellDifference) > 0.01)
                                                            <div class="text-xs {{ $sellDifference > 0 ? 'text-green-600 dark:text-green-400' : 'text-orange-600 dark:text-orange-400' }}">
                                                                vs RSP: {{ $sellDifference > 0 ? '+' : '' }}€{{ number_format($sellDifference, 2) }}
                                                            </div>
                                                        @endif
                                                    </div>
                                                    <svg class="w-3 h-3 text-gray-400 group-hover:text-blue-500 transition-colors opacity-0 group-hover:opacity-100" 
                                                         fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                                              d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/>
                                                    </svg>
                                                </div>
                                            </div>
                                        @elseif($item->product)
                                            <div class="{{ $sellHighlight }} rounded px-2 py-1">
                                                <div class="text-gray-900 dark:text-gray-100 font-medium">
                                                    €{{ number_format($currentSell, 2) }}
                                                </div>
                                                @if($sellDifference !== null && abs($sellDifference) > 0.01)
                                                    <div class="text-xs {{ $sellDifference > 0 ? 'text-green-600 dark:text-green-400' : 'text-orange-600 dark:text-orange-400' }}">
                                                        vs RSP: {{ $sellDifference > 0 ? '+' : '' }}€{{ number_format($sellDifference, 2) }}
                                                    </div>
                                                @endif
                                            </div>
                                        @else
                                            <span class="text-gray-400 dark:text-gray-500 text-xs">—</span>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 text-center text-sm">
                                        @if($item->product)
                                            @php
                                                $currentStock = $item->product->getCurrentStock();
                                            @endphp
                                            <div class="text-gray-900 dark:text-gray-100">
                                                {{ number_format($currentStock, 1) }}
                                            </div>
                                            @if($currentStock <= 0)
                                                <div class="text-xs text-red-500 dark:text-red-400">
                                                    Out of stock
                                                </div>
                                            @elseif($currentStock <= 5)
                                                <div class="text-xs text-orange-500 dark:text-orange-400">
                                                    Low stock
                                                </div>
                                            @endif
                                        @else
                                            <span class="text-gray-400 dark:text-gray-500 text-xs">—</span>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 text-right text-sm">
                                        @php
                                            // Calculate margin using delivery cost and VAT-exclusive current sell price
                                            $margin = null;
                                            $marginPercent = null;
                                            $showWarning = false;
                                            
                                            if ($item->product && $item->unit_cost > 0 && $currentSell > 0) {
                                                // Get the tax rate from the tax category
                                                $taxRate = 0; // Default to 0% if no tax rate
                                                if ($item->product->taxCategory && $item->product->taxCategory->primaryTax) {
                                                    $taxRate = $item->product->taxCategory->primaryTax->RATE; // This is already a decimal (e.g., 0.20 for 20%)
                                                }
                                                
                                                // Calculate VAT-exclusive sell price
                                                $vatExclusiveSellPrice = $currentSell / (1 + $taxRate);
                                                
                                                // Calculate margin using VAT-exclusive price
                                                $margin = $vatExclusiveSellPrice - $item->unit_cost;
                                                $marginPercent = ($margin / $vatExclusiveSellPrice) * 100;
                                                $showWarning = $margin <= 0;
                                            }
                                        @endphp
                                        @if($margin !== null)
                                            <div class="flex items-center justify-end gap-1">
                                                @if($showWarning)
                                                    <svg class="w-4 h-4 text-red-500 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20" title="Zero or negative margin">
                                                        <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                                                    </svg>
                                                @endif
                                                <div class="text-right">
                                                    <div class="text-gray-900 dark:text-gray-100 font-medium {{ $showWarning ? 'text-red-600 dark:text-red-400' : '' }}">
                                                        €{{ number_format($margin, 2) }}
                                                    </div>
                                                    <div class="text-xs text-gray-500 dark:text-gray-400 {{ $showWarning ? 'text-red-500 dark:text-red-400' : '' }}">
                                                        {{ number_format($marginPercent, 1) }}%
                                                    </div>
                                                </div>
                                            </div>
                                        @else
                                            <span class="text-gray-400 dark:text-gray-500 text-xs">—</span>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 text-center text-sm">
                                        @if($item->tax_rate !== null)
                                            @if($item->normalized_tax_rate !== null && $item->normalized_tax_rate != $item->tax_rate)
                                                <div class="text-gray-900 dark:text-gray-100 font-medium">
                                                    {{ $item->formatted_normalized_tax_rate }}
                                                </div>
                                                <div class="text-xs text-gray-500">
                                                    (calc: {{ $item->formatted_tax_rate }})
                                                </div>
                                            @else
                                                <div class="text-gray-900 dark:text-gray-100">
                                                    {{ $item->formatted_tax_rate }}
                                                </div>
                                            @endif
                                            @if($item->isPotentialDepositScheme())
                                                <div class="text-xs text-orange-600 dark:text-orange-400">
                                                    Deposit?
                                                </div>
                                            @endif
                                        @else
                                            <span class="text-gray-400 dark:text-gray-500 text-xs">—</span>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 text-right text-sm font-medium">
                                        <div class="flex items-center justify-end space-x-2">
                                            @if($item->is_new_product && !$item->barcode)
                                                <button type="button" 
                                                        onclick="refreshBarcode({{ $item->id }})"
                                                        id="refresh-btn-{{ $item->id }}"
                                                        class="text-blue-600 hover:text-blue-900 dark:text-blue-400 dark:hover:text-blue-300 text-xs disabled:opacity-50 disabled:cursor-not-allowed">
                                                    🔄 Refresh Barcode
                                                </button>
                                            @endif
                                            
                                            @if($item->is_new_product && $delivery->status !== 'completed')
                                                <a href="{{ route('products.create', ['delivery_item' => $item->id]) }}" 
                                                   class="inline-flex items-center px-2 py-1 bg-green-600 hover:bg-green-700 text-white text-xs rounded transition-colors duration-200">
                                                    <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                                                    </svg>
                                                    Add to POS
                                                </a>
                                            @endif
                                            
                                        </div>
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
                                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                        Actions
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
                                        <td class="px-6 py-4 text-right text-sm font-medium">
                                            @if($delivery->status !== 'completed')
                                                <button onclick="convertScanToProduct('{{ $scan->barcode }}', {{ $scan->quantity }})" 
                                                        class="text-green-600 hover:text-green-900 dark:text-green-400 dark:hover:text-green-300 text-xs">
                                                    Convert to Product
                                                </button>
                                            @endif
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

    <script>
        // Auto-refresh barcode status every 10 seconds
        function startBarcodeAutoRefresh() {
            setInterval(() => {
                checkBarcodeUpdates();
            }, 10000); // Check every 10 seconds
        }

        // Check for barcode updates via AJAX
        function checkBarcodeUpdates() {
            const statusElements = document.querySelectorAll('[id^="barcode-status-"]');
            const retrievingElements = Array.from(statusElements).filter(el => el.textContent.includes('🔄'));
            
            if (retrievingElements.length === 0) return;

            fetch(window.location.href, {
                method: 'GET',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.items) {
                    data.items.forEach(item => {
                        updateBarcodeCell(item);
                    });
                }
            })
            .catch(error => {
                console.log('Auto-refresh check failed:', error);
            });
        }

        // Manual refresh barcode for specific item
        function refreshBarcode(itemId) {
            const button = document.getElementById(`refresh-btn-${itemId}`);
            const statusElement = document.getElementById(`barcode-status-${itemId}`);
            
            // Disable button and show loading
            button.disabled = true;
            button.textContent = '🔄 Refreshing...';
            
            if (statusElement) {
                statusElement.textContent = '🔄 Refreshing...';
            }

            fetch(`/delivery-items/${itemId}/refresh-barcode`, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Update the barcode cell with the new barcode
                    const barcodeCell = document.getElementById(`barcode-cell-${itemId}`);
                    if (barcodeCell) {
                        barcodeCell.innerHTML = `
                            <code class="px-2 py-1 bg-gray-100 dark:bg-gray-700 rounded text-xs">
                                ${data.barcode}
                            </code>
                        `;
                    }
                    
                    // Update image if available
                    if (data.has_integration && data.image_url) {
                        updateImageCell(itemId, data.image_url, data.description, data.barcode);
                    }
                    
                    // Remove the refresh button
                    button.remove();
                    
                    // Show success message
                    showMessage(data.message || 'Barcode retrieved successfully!', 'success');
                } else {
                    // Show error and re-enable button
                    showMessage(data.message || 'Failed to retrieve barcode', 'error');
                    button.disabled = false;
                    button.textContent = '🔄 Refresh Barcode';
                    
                    if (statusElement) {
                        statusElement.innerHTML = `
                            <span class="text-red-500 text-xs">❌ Failed</span>
                        `;
                    }
                }
            })
            .catch(error => {
                console.error('Refresh failed:', error);
                showMessage('Network error occurred', 'error');
                button.disabled = false;
                button.textContent = '🔄 Refresh Barcode';
                
                if (statusElement) {
                    statusElement.innerHTML = `
                        <span class="text-red-500 text-xs">❌ Error</span>
                    `;
                }
            });
        }

        // Update barcode cell content
        function updateBarcodeCell(item) {
            const barcodeCell = document.getElementById(`barcode-cell-${item.id}`);
            const refreshButton = document.getElementById(`refresh-btn-${item.id}`);
            
            if (!barcodeCell) return;

            if (item.barcode) {
                // Update cell with barcode
                barcodeCell.innerHTML = `
                    <code class="px-2 py-1 bg-gray-100 dark:bg-gray-700 rounded text-xs">
                        ${item.barcode}
                    </code>
                `;
                // Remove refresh button if it exists
                if (refreshButton) {
                    refreshButton.remove();
                }
                
                // Update image if available
                if (item.has_integration && item.image_url) {
                    updateImageCell(item.id, item.image_url, item.description, item.barcode);
                }
            } else if (item.barcode_retrieval_failed) {
                // Show failed status
                barcodeCell.innerHTML = `
                    <div class="flex items-center justify-center space-x-1">
                        <span class="text-red-500 text-xs">❌ Failed</span>
                        ${item.barcode_retrieval_error ? 
                            `<span class="text-gray-400 text-xs cursor-help" title="${item.barcode_retrieval_error}">ⓘ</span>` : 
                            ''
                        }
                    </div>
                `;
            }
        }

        // Update image cell with new image
        function updateImageCell(itemId, imageUrl, description, barcode) {
            const imageCell = document.getElementById(`image-cell-${itemId}`);
            if (!imageCell) return;


            imageCell.innerHTML = `
                <div class="relative w-10 h-10 mx-auto group">
                    <img 
                        src="${imageUrl}" 
                        alt="${description}"
                        class="w-10 h-10 object-cover rounded border border-gray-200 dark:border-gray-700 animate-pulse cursor-pointer"
                        loading="lazy"
                        onload="this.classList.remove('animate-pulse')"
                        onerror="this.style.display='none'; this.parentElement.style.display='none'"
                    >
                    <!-- Hover preview - Clean image only -->
                    <div class="absolute left-0 bottom-full mb-2 z-[9999] opacity-0 group-hover:opacity-100 transition-opacity duration-200 pointer-events-none">
                        <img 
                            src="${imageUrl}" 
                            alt="${description}"
                            class="w-64 h-64 object-cover rounded-lg border-2 border-white dark:border-gray-600 shadow-xl"
                            loading="lazy"
                            onerror="this.style.display='none'"
                        >
                    </div>
                </div>
            `;

            // Show success animation
            imageCell.style.opacity = '0.5';
            setTimeout(() => {
                imageCell.style.transition = 'opacity 0.3s ease-in-out';
                imageCell.style.opacity = '1';
            }, 100);
        }

        // Show message to user
        function showMessage(message, type = 'info') {
            // Create a toast notification
            const toast = document.createElement('div');
            toast.className = `fixed top-4 right-4 z-50 px-4 py-2 rounded shadow-lg text-white ${
                type === 'success' ? 'bg-green-500' : 
                type === 'error' ? 'bg-red-500' : 'bg-blue-500'
            }`;
            toast.textContent = message;
            
            document.body.appendChild(toast);
            
            setTimeout(() => {
                toast.remove();
            }, 3000);
        }

        // Sort delivery items function
        // Sort state tracking
        let currentSort = { column: 'new_first', direction: 'asc' };

        function sortDeliveryItems(column = null) {
            // If called from dropdown, use dropdown value
            if (!column) {
                const select = document.getElementById('sortSelect');
                column = select.value;
                currentSort = { column: column, direction: 'asc' };
            } else {
                // If called from column header, toggle direction
                if (currentSort.column === column) {
                    currentSort.direction = currentSort.direction === 'asc' ? 'desc' : 'asc';
                } else {
                    currentSort.column = column;
                    currentSort.direction = 'asc';
                }
                
                // Update dropdown to match
                const select = document.getElementById('sortSelect');
                if (column === 'actions') {
                    select.value = 'new_first'; // Map actions to new_first in dropdown
                } else {
                    select.value = column;
                }
            }

            updateSortIcons();
            performSort();
        }

        function updateSortIcons() {
            // Reset all icons
            document.querySelectorAll('[id^="sort-icon-"]').forEach(icon => {
                icon.className = 'w-3 h-3 text-gray-400 opacity-50';
                icon.innerHTML = '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16V4m0 0L3 8m4-4l4 4m6 0v12m0 0l4-4m-4 4l-4-4"/>';
            });

            // Highlight active sort column
            const activeIcon = document.getElementById(`sort-icon-${currentSort.column}`);
            if (activeIcon) {
                activeIcon.className = 'w-3 h-3 text-blue-500 opacity-100';
                if (currentSort.direction === 'asc') {
                    activeIcon.innerHTML = '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4h13M3 8h9m-9 4h6m4 0l4-4m0 0l4 4m-4-4v12"/>';
                } else {
                    activeIcon.innerHTML = '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4h13M3 8h9m-9 4h9m5-4v12m0 0l-4-4m4 4l4-4"/>';
                }
            }
        }

        function performSort() {
            const tbody = document.querySelector('.delivery-items-table tbody');
            const rows = Array.from(tbody.querySelectorAll('tr'));

            rows.sort((a, b) => {
                let result = 0;
                
                switch (currentSort.column) {
                    case 'new_first':
                    case 'actions': {
                        // Check for "New Product" badge in Product column (column 2)
                        const aIsNewBadge = a.querySelector('td:nth-child(2) .bg-yellow-100') !== null;
                        const bIsNewBadge = b.querySelector('td:nth-child(2) .bg-yellow-100') !== null;
                        
                        // Debug logging (remove after testing)
                        if (currentSort.column === 'actions') {
                            const aDesc = a.querySelector('td:nth-child(2) .font-medium')?.textContent || 'Unknown';
                            const bDesc = b.querySelector('td:nth-child(2) .font-medium')?.textContent || 'Unknown';
                            console.log('Actions sort (New Product badges):', { 
                                aDesc, bDesc, 
                                aIsNewBadge, bIsNewBadge
                            });
                        }
                        
                        if (aIsNewBadge && !bIsNewBadge) result = -1;
                        else if (!aIsNewBadge && bIsNewBadge) result = 1;
                        else {
                            // If both new or both existing, sort by code
                            const aCode = a.querySelector('td:nth-child(2) .font-medium')?.textContent || '';
                            const bCode = b.querySelector('td:nth-child(2) .font-medium')?.textContent || '';
                            result = aCode.localeCompare(bCode);
                        }
                        break;
                    }
                    case 'product':
                    case 'code': {
                        const aCode = a.querySelector('td:nth-child(2) .font-medium')?.textContent || '';
                        const bCode = b.querySelector('td:nth-child(2) .font-medium')?.textContent || '';
                        result = aCode.localeCompare(bCode);
                        break;
                    }
                    case 'description': {
                        const aDesc = a.querySelector('td:nth-child(2) .text-sm.font-medium')?.textContent || '';
                        const bDesc = b.querySelector('td:nth-child(2) .text-sm.font-medium')?.textContent || '';
                        result = aDesc.localeCompare(bDesc);
                        break;
                    }
                    case 'status': {
                        const aStatus = a.querySelector('td:nth-child(6) .px-2')?.textContent.toLowerCase() || '';
                        const bStatus = b.querySelector('td:nth-child(6) .px-2')?.textContent.toLowerCase() || '';
                        const statusOrder = { 'pending': 1, 'partial': 2, 'complete': 3, 'excess': 4 };
                        const aOrder = statusOrder[aStatus] || 999;
                        const bOrder = statusOrder[bStatus] || 999;
                        result = aOrder - bOrder;
                        break;
                    }
                    case 'margin': {
                        // Get margin percentage from nested structure: .text-right > .text-xs (now column 12 due to added stock column)
                        const aMarginPercentText = a.querySelector('td:nth-child(12) .text-right .text-xs')?.textContent || '0.0%';
                        const bMarginPercentText = b.querySelector('td:nth-child(12) .text-right .text-xs')?.textContent || '0.0%';
                        const aMarginPercent = parseFloat(aMarginPercentText.replace('%', '')) || 0;
                        const bMarginPercent = parseFloat(bMarginPercentText.replace('%', '')) || 0;
                        
                        // Debug logging (remove after testing)
                        console.log('Margin % sort:', { aMarginPercentText, bMarginPercentText, aMarginPercent, bMarginPercent });
                        
                        result = aMarginPercent - bMarginPercent;
                        break;
                    }
                    default:
                        result = 0;
                }

                // Apply sort direction
                return currentSort.direction === 'desc' ? -result : result;
            });

            // Re-append sorted rows
            rows.forEach(row => tbody.appendChild(row));
        }

        // Convert unmatched scan to product
        function convertScanToProduct(barcode, quantity) {
            const deliveryId = {{ $delivery->id }};
            const supplier = '{{ $delivery->supplier->Supplier ?? "Unknown" }}';
            
            // Pre-populate product data from scan
            const description = prompt('Enter product description:', 'Product for barcode ' + barcode);
            if (!description) return;
            
            const supplierCode = prompt('Enter supplier code:', '');
            if (!supplierCode) return;
            
            const unitCost = parseFloat(prompt('Enter unit cost (€):', '0.00'));
            if (isNaN(unitCost) || unitCost < 0) {
                alert('Please enter a valid unit cost');
                return;
            }
            
            // Create the product
            createProductFromScan(deliveryId, {
                supplier_code: supplierCode,
                description: description,
                barcode: barcode,
                ordered_quantity: quantity,
                unit_cost: unitCost,
                units_per_case: 1
            });
        }
        
        async function createProductFromScan(deliveryId, productData) {
            try {
                const response = await fetch(`/api/deliveries/${deliveryId}/items`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    },
                    body: JSON.stringify(productData)
                });
                
                const data = await response.json();
                
                if (data.success) {
                    showMessage('Product created successfully! Refreshing page...', 'success');
                    setTimeout(() => window.location.reload(), 2000);
                } else {
                    showMessage(data.message || 'Failed to create product', 'error');
                }
            } catch (error) {
                console.error('Failed to create product from scan:', error);
                showMessage('Network error - please try again', 'error');
            }
        }

        // Auto update costs functionality
        async function autoUpdateCosts() {
            const button = document.getElementById('autoUpdateBtn');
            const deliveryId = {{ $delivery->id }};
            
            // Show confirmation dialog
            const threshold = prompt(
                'Update costs for items with price differences above what percentage?\n' +
                '(Enter a number between 0-100, default is 5%)', 
                '5'
            );
            
            if (threshold === null) return; // User cancelled
            
            const thresholdNum = parseFloat(threshold);
            if (isNaN(thresholdNum) || thresholdNum < 0 || thresholdNum > 100) {
                alert('Please enter a valid percentage between 0 and 100');
                return;
            }
            
            // Count delivery items with price differences (delivery cost column is 7th)
            const deliveryCostCells = document.querySelectorAll('.delivery-items-table tbody tr td:nth-child(7) .bg-red-100, .delivery-items-table tbody tr td:nth-child(7) .bg-yellow-100, .delivery-items-table tbody tr td:nth-child(7) .bg-green-100, .delivery-items-table tbody tr td:nth-child(7) .bg-blue-100');
            const highlightedItemsCount = deliveryCostCells.length;
            
            // Check for zero cost items in delivery
            const zeroCostItems = [];
            const tableRows = document.querySelectorAll('.delivery-items-table tbody tr');
            tableRows.forEach(row => {
                const deliveryCostCell = row.querySelector('td:nth-child(7)'); // Delivery Cost column (7th)
                if (deliveryCostCell) {
                    const costText = deliveryCostCell.textContent.trim();
                    if (costText.includes('€0.00')) {
                        const productCell = row.querySelector('td:nth-child(2) .text-sm.font-medium');
                        if (productCell) {
                            zeroCostItems.push(productCell.textContent.trim());
                        }
                    }
                }
            });
            
            // Build warning message
            let warningMessage = `This will update costs for products with price differences of ${thresholdNum}% or more.\n\n`;
            warningMessage += `Approximately ${highlightedItemsCount} items may be affected.\n\n`;
            
            if (zeroCostItems.length > 0) {
                warningMessage += `⚠️  WARNING: ${zeroCostItems.length} items have €0.00 delivery cost (likely not delivered):\n`;
                warningMessage += zeroCostItems.slice(0, 3).map(name => `• ${name.substring(0, 40)}${name.length > 40 ? '...' : ''}`).join('\n');
                if (zeroCostItems.length > 3) {
                    warningMessage += `\n• ...and ${zeroCostItems.length - 3} more`;
                }
                warningMessage += `\n\nThese items will be automatically SKIPPED during the update.\n\n`;
            }
            
            warningMessage += `Are you sure you want to continue?`;
                                 
            if (!confirm(warningMessage)) return;
            
            // Disable button and show loading
            button.disabled = true;
            const originalText = button.innerHTML;
            button.innerHTML = `
                <svg class="animate-spin w-4 h-4 mr-2" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                Updating...
            `;
            
            try {
                const response = await fetch(`/deliveries/${deliveryId}/update-costs`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    },
                    body: JSON.stringify({ threshold: thresholdNum })
                });
                
                const data = await response.json();
                
                if (data.success) {
                    showMessage(data.message, 'success');
                    
                    // Refresh the page to show updated costs
                    setTimeout(() => {
                        window.location.reload();
                    }, 2000);
                } else {
                    showMessage(data.message || 'Cost update failed', 'error');
                }
                
            } catch (error) {
                console.error('Cost update failed:', error);
                showMessage('Network error occurred during cost update', 'error');
            } finally {
                // Re-enable button
                button.disabled = false;
                button.innerHTML = originalText;
            }
        }

        // Price editor functionality
        let currentEditingItem = null;

        function openPriceEditor(itemId, productCode, description, currentNetPrice, deliveryCost, taxRate = 0, rspPrice = 0) {
            currentEditingItem = {
                itemId: itemId,
                productCode: productCode,
                description: description,
                currentNetPrice: parseFloat(currentNetPrice),
                deliveryCost: parseFloat(deliveryCost),
                taxRate: parseFloat(taxRate)
            };
            
            // Show modal
            document.getElementById('priceEditorModal').classList.remove('hidden');
            document.getElementById('modalProductName').textContent = description;
            document.getElementById('modalProductCode').textContent = productCode;
            document.getElementById('modalCurrentPrice').textContent = '€' + currentNetPrice;
            document.getElementById('modalDeliveryCost').textContent = '€' + deliveryCost.toFixed(2);
            
            // Set initial values - default to gross mode with RSP as default
            document.getElementById('grossPriceInput').value = rspPrice > 0 ? rspPrice.toFixed(2) : '';
            document.getElementById('netPriceInput').value = currentNetPrice;
            document.getElementById('priceInputMode').value = 'gross';
            
            // Toggle to show gross mode UI
            togglePriceMode();
            
            // Calculate initial margin
            updateMarginDisplay();
        }

        function closePriceEditor() {
            document.getElementById('priceEditorModal').classList.add('hidden');
            currentEditingItem = null;
        }

        function updateMarginDisplay() {
            if (!currentEditingItem) return;
            
            const mode = document.getElementById('priceInputMode').value;
            const grossInput = parseFloat(document.getElementById('grossPriceInput').value) || 0;
            const netInput = parseFloat(document.getElementById('netPriceInput').value) || 0;
            
            let netPrice = mode === 'gross' ? grossInput / (1 + currentEditingItem.taxRate) : netInput;
            let margin = netPrice - currentEditingItem.deliveryCost;
            let marginPercent = netPrice > 0 ? (margin / netPrice) * 100 : 0;
            
            // Update margin display
            const marginDisplay = document.getElementById('marginDisplay');
            const marginPercentDisplay = document.getElementById('marginPercentDisplay');
            
            marginDisplay.textContent = '€' + margin.toFixed(2);
            marginPercentDisplay.textContent = marginPercent.toFixed(1) + '%';
            
            // Color coding
            const container = document.getElementById('marginContainer');
            container.className = 'p-3 rounded-lg border-2 ';
            
            if (margin <= 0) {
                container.className += 'border-red-300 bg-red-50';
                marginDisplay.className = 'font-medium text-red-600';
                marginPercentDisplay.className = 'text-sm text-red-500';
            } else if (marginPercent < 20) {
                container.className += 'border-yellow-300 bg-yellow-50';
                marginDisplay.className = 'font-medium text-yellow-700';
                marginPercentDisplay.className = 'text-sm text-yellow-600';
            } else {
                container.className += 'border-green-300 bg-green-50';
                marginDisplay.className = 'font-medium text-green-600';
                marginPercentDisplay.className = 'text-sm text-green-500';
            }
        }

        function togglePriceMode() {
            const mode = document.getElementById('priceInputMode').value;
            const grossContainer = document.getElementById('grossPriceContainer');
            const netContainer = document.getElementById('netPriceContainer');
            
            if (mode === 'gross') {
                grossContainer.classList.remove('hidden');
                netContainer.classList.add('hidden');
            } else {
                grossContainer.classList.add('hidden');
                netContainer.classList.remove('hidden');
            }
            
            updateMarginDisplay();
        }

        async function savePriceUpdate() {
            if (!currentEditingItem) return;
            
            const mode = document.getElementById('priceInputMode').value;
            const grossPrice = parseFloat(document.getElementById('grossPriceInput').value);
            const netPrice = parseFloat(document.getElementById('netPriceInput').value);
            
            const formData = {
                price_input_mode: mode
            };
            
            if (mode === 'gross') {
                formData.gross_price = grossPrice;
            } else {
                formData.net_price = netPrice;
            }
            
            try {
                const response = await fetch(`/deliveries/{{ $delivery->id }}/items/${currentEditingItem.itemId}/price`, {
                    method: 'PATCH',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    },
                    body: JSON.stringify(formData)
                });
                
                const data = await response.json();
                
                if (data.success) {
                    showMessage(data.message, 'success');
                    closePriceEditor();
                    
                    // Refresh the page to show updated prices and margins
                    setTimeout(() => {
                        window.location.reload();
                    }, 1500);
                } else {
                    showMessage(data.message || 'Failed to update price', 'error');
                }
                
            } catch (error) {
                console.error('Price update failed:', error);
                showMessage('Network error occurred during price update', 'error');
            }
        }

        // Quick cost update functionality
        async function updateProductCost(productId, newCost, productName, event) {
            
            if (!confirm(`Update cost for "${productName}" to €${newCost.toFixed(2)}?`)) {
                return;
            }

            try {
                const url = `/products/${productId}/cost`;
                const requestBody = { cost_price: newCost };
                const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
                
                const response = await fetch(url, {
                    method: 'PATCH',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify(requestBody)
                });
                
                const data = await response.json();

                if (response.ok) {
                    showMessage(data.message || 'Product cost updated successfully', 'success');
                    
                    // Update the UI without refreshing
                    // Find the button that was clicked and update its row
                    const button = event.target.closest('button');
                    const row = button.closest('tr');
                    
                    // Update the current cost display
                    const currentCostDiv = button.closest('td').querySelector('div');
                    if (currentCostDiv) {
                        currentCostDiv.innerHTML = `
                            <div class="text-gray-900 dark:text-gray-100">
                                €${newCost.toFixed(2)}
                            </div>
                            <div class="text-xs text-gray-500">
                                in POS
                            </div>
                        `;
                    }
                    
                    // Hide the update button since costs now match
                    button.style.display = 'none';
                    
                    // Update the delivery cost column to remove highlighting
                    const costCell = row.querySelector('td:nth-child(7)');
                    if (costCell) {
                        const costDiv = costCell.querySelector('div');
                        if (costDiv) {
                            // Remove background highlighting
                            costDiv.className = 'rounded px-2 py-1';
                            // Update content to show just the cost without difference
                            costDiv.innerHTML = `
                                <div class="text-gray-900 dark:text-gray-100 font-medium">
                                    €${newCost.toFixed(2)}
                                </div>
                            `;
                        }
                    }
                    
                } else {
                    const errorMessage = data.message || data.errors || 'Failed to update product cost';
                    showMessage(typeof errorMessage === 'object' ? JSON.stringify(errorMessage) : errorMessage, 'error');
                }

            } catch (error) {
                showMessage('Network error occurred during cost update', 'error');
            }
        }

        // Start auto-refresh when page loads
        document.addEventListener('DOMContentLoaded', function() {
            startBarcodeAutoRefresh();
            // Initial sort
            sortDeliveryItems();
            
            // Add event listeners for price inputs
            document.getElementById('grossPriceInput').addEventListener('input', updateMarginDisplay);
            document.getElementById('netPriceInput').addEventListener('input', updateMarginDisplay);
            document.getElementById('priceInputMode').addEventListener('change', togglePriceMode);
        });
    </script>

    <style>
        /* Ensure table cells allow hover previews to overflow */
        .delivery-items-table td {
            overflow: visible !important;
            position: relative !important;
        }
        
        /* Ensure table itself allows overflow for hover previews */
        .delivery-items-table {
            overflow: visible !important;
        }
        
        /* Ensure table body allows overflow */
        .delivery-items-table tbody {
            overflow: visible !important;
        }
        
        /* Ensure table rows allow overflow */
        .delivery-items-table tr {
            overflow: visible !important;
        }
        
        /* Make sure the hover preview appears above everything */
        .group .absolute.z-\[9999\] {
            position: absolute !important;
            z-index: 9999 !important;
            background: white !important;
            border: 2px solid white !important;
            border-radius: 0.5rem !important;
            box-shadow: 0 20px 25px -5px rgb(0 0 0 / 0.1), 0 10px 10px -5px rgb(0 0 0 / 0.04) !important;
        }
        
        /* Ensure the hover preview image is properly sized */
        .group .absolute.z-\[9999\] img {
            display: block !important;
            width: 256px !important;
            height: 256px !important;
            min-width: 256px !important;
            max-width: 256px !important;
            object-fit: contain !important;
            background-color: white !important;
        }
        
        /* Hide any other content that might interfere */
        .group .absolute.z-\[9999\] * {
            pointer-events: none !important;
        }
    </style>

    <!-- Price Editor Modal -->
    <div id="priceEditorModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50 hidden">
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-xl max-w-md w-full mx-4">
            <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                <div class="flex items-center justify-between">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">Edit Price</h3>
                    <button type="button" onclick="closePriceEditor()" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>
            </div>
            
            <div class="px-6 py-4">
                <!-- Product Info -->
                <div class="mb-4">
                    <h4 id="modalProductName" class="font-medium text-gray-900 dark:text-gray-100"></h4>
                    <p id="modalProductCode" class="text-sm text-gray-500 dark:text-gray-400"></p>
                </div>
                
                <!-- Current Info -->
                <div class="grid grid-cols-2 gap-4 mb-4 p-3 bg-gray-50 dark:bg-gray-700 rounded">
                    <div>
                        <span class="text-xs text-gray-500 dark:text-gray-400">Current Price (Net)</span>
                        <div id="modalCurrentPrice" class="font-medium text-gray-900 dark:text-gray-100"></div>
                    </div>
                    <div>
                        <span class="text-xs text-gray-500 dark:text-gray-400">Delivery Cost</span>
                        <div id="modalDeliveryCost" class="font-medium text-gray-900 dark:text-gray-100"></div>
                    </div>
                </div>
                
                <!-- Price Input Mode -->
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Input Mode</label>
                    <select id="priceInputMode" class="w-full border border-gray-300 dark:border-gray-600 rounded-md px-3 py-2 bg-white dark:bg-gray-700">
                        <option value="net">Net Price (excluding VAT)</option>
                        <option value="gross">Gross Price (including VAT)</option>
                    </select>
                </div>
                
                <!-- Price Inputs -->
                <div id="netPriceContainer" class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Net Price (€)</label>
                    <input type="number" id="netPriceInput" step="0.01" min="0" 
                           class="w-full border border-gray-300 dark:border-gray-600 rounded-md px-3 py-2 bg-white dark:bg-gray-700">
                </div>
                
                <div id="grossPriceContainer" class="mb-4 hidden">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Gross Price (€)</label>
                    <input type="number" id="grossPriceInput" step="0.01" min="0" 
                           class="w-full border border-gray-300 dark:border-gray-600 rounded-md px-3 py-2 bg-white dark:bg-gray-700">
                </div>
                
                <!-- Margin Preview -->
                <div id="marginContainer" class="p-3 rounded-lg border-2 border-gray-300 bg-gray-50">
                    <div class="text-center">
                        <div class="text-sm text-gray-600 dark:text-gray-400">Predicted Margin</div>
                        <div id="marginDisplay" class="font-medium text-gray-900">€0.00</div>
                        <div id="marginPercentDisplay" class="text-sm text-gray-500">0.0%</div>
                    </div>
                </div>
            </div>
            
            <div class="px-6 py-4 border-t border-gray-200 dark:border-gray-700 flex justify-end space-x-3">
                <button type="button" onclick="closePriceEditor()" 
                        class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 hover:bg-gray-200 dark:bg-gray-600 dark:text-gray-300 dark:hover:bg-gray-500 rounded-md">
                    Cancel
                </button>
                <button type="button" onclick="savePriceUpdate()" 
                        class="px-4 py-2 text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 rounded-md">
                    Update Price & Add to Labels
                </button>
            </div>
        </div>
    </div>
</x-admin-layout>