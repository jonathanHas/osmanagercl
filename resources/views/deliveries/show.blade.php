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
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            
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
                        <div class="flex items-center gap-4">
                            <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">Delivery Items</h3>
                            
                            @if($delivery->status !== 'completed')
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
                                    <option value="code">Code</option>
                                    <option value="description">Description</option>
                                    <option value="status">Status</option>
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
                
                <div class="overflow-x-auto" style="overflow-y: visible;">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 delivery-items-table">
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
            const retrievingElements = document.querySelectorAll('[id^="barcode-status-"]:contains("🔄")');
            
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
        function sortDeliveryItems() {
            const select = document.getElementById('sortSelect');
            const sortBy = select.value;
            const tbody = document.querySelector('.delivery-items-table tbody');
            const rows = Array.from(tbody.querySelectorAll('tr'));

            rows.sort((a, b) => {
                switch (sortBy) {
                    case 'new_first': {
                        const aIsNew = a.querySelector('.bg-yellow-100') !== null; // New Product badge
                        const bIsNew = b.querySelector('.bg-yellow-100') !== null;
                        if (aIsNew && !bIsNew) return -1;
                        if (!aIsNew && bIsNew) return 1;
                        // If both new or both existing, sort by code
                        const aCode = a.querySelector('td:nth-child(2) .font-medium')?.textContent || '';
                        const bCode = b.querySelector('td:nth-child(2) .font-medium')?.textContent || '';
                        return aCode.localeCompare(bCode);
                    }
                    case 'code': {
                        const aCode = a.querySelector('td:nth-child(2) .font-medium')?.textContent || '';
                        const bCode = b.querySelector('td:nth-child(2) .font-medium')?.textContent || '';
                        return aCode.localeCompare(bCode);
                    }
                    case 'description': {
                        const aDesc = a.querySelector('td:nth-child(2) .text-sm.font-medium')?.textContent || '';
                        const bDesc = b.querySelector('td:nth-child(2) .text-sm.font-medium')?.textContent || '';
                        return aDesc.localeCompare(bDesc);
                    }
                    case 'status': {
                        const aStatus = a.querySelector('td:nth-child(6) .px-2')?.textContent.toLowerCase() || '';
                        const bStatus = b.querySelector('td:nth-child(6) .px-2')?.textContent.toLowerCase() || '';
                        const statusOrder = { pending: 0, partial: 1, excess: 2, complete: 3 };
                        return (statusOrder[aStatus] || 99) - (statusOrder[bStatus] || 99);
                    }
                    default:
                        return 0;
                }
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

        // Start auto-refresh when page loads
        document.addEventListener('DOMContentLoaded', function() {
            startBarcodeAutoRefresh();
            // Initial sort
            sortDeliveryItems();
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
</x-admin-layout>