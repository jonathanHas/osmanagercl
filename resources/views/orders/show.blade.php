<x-admin-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    Order #{{ $order->id }} - {{ $order->supplier->Supplier ?? 'Unknown Supplier' }}
                </h2>
                <p class="text-sm text-gray-600 mt-1">
                    Delivery Date: {{ $order->order_date ? $order->order_date->format('l, F j, Y') : 'Not set' }}
                    • Status: <span class="font-semibold">{{ ucfirst($order->status) }}</span>
                </p>
            </div>
            <div class="flex space-x-2">
                @if($order->isEditable())
                    <button onclick="autoApproveSafeItems()" 
                            class="bg-green-500 hover:bg-green-700 text-white font-bold py-2 px-4 rounded">
                        Auto-Approve Safe Items
                    </button>
                    <button onclick="completeOrder()" 
                            class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
                        Complete Order
                    </button>
                @endif
                <a href="{{ route('orders.export', $order) }}" 
                   class="bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded">
                    Export CSV
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-6" x-data="orderReview()">
        <div class="max-w-none mx-auto px-4 sm:px-6 lg:px-8">
            
            <!-- Order Statistics -->
            <div class="grid grid-cols-1 md:grid-cols-5 gap-4 mb-6">
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-4">
                    <div class="text-sm font-medium text-gray-500">Total Items</div>
                    <div class="mt-1 text-2xl font-semibold text-gray-900" x-text="orderTotals.total_items">
                        {{ $statistics['total_items'] }}
                    </div>
                </div>
                <div class="bg-red-50 overflow-hidden shadow-sm sm:rounded-lg p-4">
                    <div class="text-sm font-medium text-red-600">Requires Review</div>
                    <div class="mt-1 text-2xl font-semibold text-red-700">
                        {{ $statistics['review_items'] }}
                    </div>
                </div>
                <div class="bg-yellow-50 overflow-hidden shadow-sm sm:rounded-lg p-4">
                    <div class="text-sm font-medium text-yellow-600">Standard</div>
                    <div class="mt-1 text-2xl font-semibold text-yellow-700">
                        {{ $statistics['standard_items'] }}
                    </div>
                </div>
                <div class="bg-green-50 overflow-hidden shadow-sm sm:rounded-lg p-4">
                    <div class="text-sm font-medium text-green-600">Safe Items</div>
                    <div class="mt-1 text-2xl font-semibold text-green-700">
                        {{ $statistics['safe_items'] }}
                    </div>
                </div>
                <div class="bg-blue-50 overflow-hidden shadow-sm sm:rounded-lg p-4">
                    <div class="text-sm font-medium text-blue-600">Total Value</div>
                    <div class="mt-1 text-2xl font-semibold text-blue-700" x-text="'€' + orderTotals.total_value">
                        €{{ number_format($order->total_value, 2) }}
                    </div>
                </div>
            </div>

            <!-- Priority Filter Tabs and Controls -->
            <div class="bg-white shadow-sm sm:rounded-lg mb-6">
                <div class="border-b border-gray-200">
                    <div class="flex justify-between items-center px-6">
                        <nav class="-mb-px flex space-x-8" aria-label="Tabs">
                            <button @click="activeTab = 'to_order'" 
                                    :class="activeTab === 'to_order' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                                    class="whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">
                                📦 To Order (<span x-text="getItemsToOrderCount()"></span>)
                            </button>
                            <button @click="activeTab = 'review'" 
                                    :class="activeTab === 'review' ? 'border-red-500 text-red-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                                    class="whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">
                                🔴 Requires Review ({{ $statistics['review_items'] }})
                            </button>
                            <button @click="activeTab = 'standard'" 
                                    :class="activeTab === 'standard' ? 'border-yellow-500 text-yellow-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                                    class="whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">
                                🟡 Standard ({{ $statistics['standard_items'] }})
                            </button>
                            <button @click="activeTab = 'safe'" 
                                    :class="activeTab === 'safe' ? 'border-green-500 text-green-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                                    class="whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">
                                🟢 Safe Items ({{ $statistics['safe_items'] }})
                            </button>
                            <button @click="activeTab = 'not_ordered'" 
                                    :class="activeTab === 'not_ordered' ? 'border-gray-500 text-gray-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                                    class="whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">
                                📋 Not Ordered (<span x-text="getNotOrderedCount()"></span>)
                            </button>
                            <button @click="activeTab = 'all'" 
                                    :class="activeTab === 'all' ? 'border-purple-500 text-purple-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                                    class="whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">
                                All Items ({{ $statistics['total_items'] }})
                            </button>
                        </nav>
                        
                        <div class="flex items-center space-x-4 py-4">
                            <label class="text-sm text-gray-600">
                                Sort by:
                                <select @change="sortOrder = $event.target.value" class="ml-2 text-sm border-gray-300 rounded">
                                    <option value="quantity_desc">Order Quantity (High to Low)</option>
                                    <option value="sales_desc">Total Sales (High to Low)</option>
                                    <option value="name_asc">Product Name (A-Z)</option>
                                    <option value="priority_review">Priority (Review First)</option>
                                </select>
                            </label>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Order Items Table -->
            <div class="bg-white shadow-sm sm:rounded-lg overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-1/4">
                                    Product
                                </th>
                                <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-1/6">
                                    Sales Context
                                </th>
                                <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-1/8">
                                    Suggested
                                </th>
                                <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-1/6">
                                    Final Quantity
                                </th>
                                <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-1/8">
                                    Cost
                                </th>
                                <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-1/12">
                                    Priority
                                </th>
                                <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-1/12">
                                    Actions
                                </th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <template x-for="item in sortedItems" :key="item.id">
                                <tr x-show="shouldShowItem(item)" 
                                    x-transition:enter="transition ease-out duration-100"
                                    x-transition:enter-start="opacity-0 transform scale-95"
                                    x-transition:enter-end="opacity-100 transform scale-100"
                                    class="hover:bg-gray-50">
                                    
                                    <td class="px-3 py-3">
                                        <div class="flex items-center">
                                            <div class="flex-shrink-0 w-10 h-10">
                                                <div :class="{
                                                    'w-10 h-10 bg-red-100 rounded-full flex items-center justify-center': item.review_priority === 'review',
                                                    'w-10 h-10 bg-green-100 rounded-full flex items-center justify-center': item.review_priority === 'safe',
                                                    'w-10 h-10 bg-yellow-100 rounded-full flex items-center justify-center': item.review_priority === 'standard'
                                                }">
                                                    <span :class="{
                                                        'text-red-600 text-xs font-bold': item.review_priority === 'review',
                                                        'text-green-600 text-xs': item.review_priority === 'safe',
                                                        'text-yellow-600 text-xs': item.review_priority === 'standard'
                                                    }" x-text="item.review_priority === 'review' ? '!' : (item.review_priority === 'safe' ? '✓' : '●')"></span>
                                                </div>
                                            </div>
                                            <div class="ml-4">
                                                <div class="text-sm font-medium text-gray-900 cursor-pointer" 
                                                     x-on:click="showProductDetails(item.id)"
                                                     x-on:mouseenter="loadProductTooltip(item.id, $event)"
                                                     x-on:mouseleave="hideTooltip()"
                                                     x-text="item.product_name"></div>
                                                <div class="text-sm text-gray-500">
                                                    Code: <span x-text="item.product_code"></span>
                                                    <span x-show="item.auto_approved" class="ml-2 inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                                        Auto-Approved
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    
                                    <td class="px-3 py-3 whitespace-nowrap text-sm text-gray-500">
                                        <div class="space-y-1" x-show="item.context_data">
                                            <div>Weekly avg: <span x-text="item.context_data?.avg_weekly_sales || 'N/A'"></span></div>
                                            <div>Current stock: <span x-text="item.context_data?.current_stock || 'N/A'"></span></div>
                                            <div class="flex items-center" x-show="item.context_data?.sales_trend">
                                                Trend: 
                                                <span :class="{
                                                    'text-green-600 ml-1': item.context_data?.sales_trend === 'up',
                                                    'text-red-600 ml-1': item.context_data?.sales_trend === 'down',
                                                    'text-gray-600 ml-1': item.context_data?.sales_trend === 'stable'
                                                }" x-text="item.context_data?.sales_trend === 'up' ? '↗ Up' : (item.context_data?.sales_trend === 'down' ? '↘ Down' : '→ Stable')"></span>
                                            </div>
                                            <div class="text-xs text-gray-400">
                                                6m sales: <span x-text="item.context_data?.total_sales_6m || 0"></span>
                                            </div>
                                            <div class="text-xs text-gray-400" x-show="item.context_data?.last_sale_date">
                                                Last sale: <span x-text="item.context_data?.last_sale_date"></span>
                                            </div>
                                        </div>
                                        <span class="text-gray-400" x-show="!item.context_data">No data</span>
                                    </td>
                                    
                                    <td class="px-3 py-3 whitespace-nowrap">
                                        <div class="text-sm font-medium text-gray-900">
                                            <span x-show="item.is_case_product" x-text="item.suggested_cases + ' cases (' + item.suggested_quantity + ' units)'"></span>
                                            <span x-show="!item.is_case_product" x-text="item.suggested_quantity + ' units'"></span>
                                        </div>
                                        <div class="text-xs text-blue-600" x-show="item.was_adjusted" x-text="(item.adjustment_percentage > 0 ? '+' : '') + item.adjustment_percentage + '%'"></div>
                                    </td>
                                    
                                    <td class="px-3 py-3 whitespace-nowrap">
                                        <div x-show="orderEditable">
                                            <!-- Case Products -->
                                            <div x-show="item.is_case_product" class="space-y-2">
                                                <div class="flex items-center space-x-2">
                                                    <button @click="adjustCaseQuantity(item.id, -1)" 
                                                            class="w-8 h-8 bg-gray-200 hover:bg-gray-300 rounded-full flex items-center justify-center text-sm">
                                                        −
                                                    </button>
                                                    <input type="number" 
                                                           :value="itemCaseQuantities[item.id] || item.final_cases"
                                                           @change="updateCaseQuantity(item.id, $event.target.value)"
                                                           step="1" 
                                                           min="0"
                                                           class="w-16 text-center border-gray-300 rounded-md text-sm">
                                                    <button @click="adjustCaseQuantity(item.id, 1)" 
                                                            class="w-8 h-8 bg-gray-200 hover:bg-gray-300 rounded-full flex items-center justify-center text-sm">
                                                        +
                                                    </button>
                                                    <span class="text-xs text-gray-600">cases</span>
                                                </div>
                                                <div class="text-xs text-gray-500 text-center">
                                                    = <span x-text="(itemCaseQuantities[item.id] || item.final_cases) * item.case_units"></span> units
                                                </div>
                                            </div>
                                            
                                            <!-- Unit Products -->
                                            <div x-show="!item.is_case_product" class="flex items-center space-x-2">
                                                <button @click="adjustQuantity(item.id, -1)" 
                                                        class="w-8 h-8 bg-gray-200 hover:bg-gray-300 rounded-full flex items-center justify-center text-sm">
                                                    −
                                                </button>
                                                <input type="number" 
                                                       :value="itemQuantities[item.id] || item.final_quantity"
                                                       @change="updateQuantity(item.id, $event.target.value)"
                                                       step="0.1" 
                                                       min="0"
                                                       class="w-20 text-center border-gray-300 rounded-md text-sm">
                                                <button @click="adjustQuantity(item.id, 1)" 
                                                        class="w-8 h-8 bg-gray-200 hover:bg-gray-300 rounded-full flex items-center justify-center text-sm">
                                                    +
                                                </button>
                                                <span class="text-xs text-gray-600">units</span>
                                            </div>
                                        </div>
                                        
                                        <div x-show="!orderEditable" class="text-sm font-medium text-gray-900">
                                            <span x-show="item.is_case_product" x-text="item.final_cases + ' cases (' + item.final_quantity + ' units)'"></span>
                                            <span x-show="!item.is_case_product" x-text="item.final_quantity + ' units'"></span>
                                        </div>
                                    </td>
                                    
                                    <td class="px-3 py-3 whitespace-nowrap text-sm text-gray-900">
                                        <div class="flex items-center space-x-2" x-show="orderEditable && !item.context_data?.has_cost_data">
                                            <span class="text-red-600 text-xs">No cost data</span>
                                            <input type="number" 
                                                   step="0.01" 
                                                   min="0"
                                                   placeholder="Enter cost"
                                                   @change="updateItemCost(item.id, $event.target.value)"
                                                   class="w-20 text-xs border-red-300 rounded">
                                        </div>
                                        <div x-show="!orderEditable || item.context_data?.has_cost_data">
                                            <div class="flex items-center">
                                                <span>Unit: €<span x-text="item.unit_cost.toFixed(2)"></span></span>
                                                <span x-show="orderEditable && item.context_data?.has_cost_data" 
                                                      class="ml-2 text-blue-600 cursor-pointer text-xs"
                                                      @click="editCost(item.id, item.unit_cost)">✏️</span>
                                            </div>
                                            <div class="font-medium">Total: €<span x-text="item.total_cost.toFixed(2)"></span></div>
                                            <div class="text-xs text-gray-500 space-y-1">
                                                <div x-show="item.context_data?.cost_source && item.context_data.cost_source !== 'purchase_price'" 
                                                     :title="'Cost from: ' + item.context_data?.cost_source">
                                                    <span x-text="getCostSourceIcon(item.context_data?.cost_source) + ' ' + item.context_data?.cost_source"></span>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    
                                    <td class="px-3 py-3 whitespace-nowrap">
                                        <select x-show="orderEditable" 
                                                :value="item.review_priority"
                                                @change="updateProductPriority(item.product_id, $event.target.value)"
                                                class="text-xs border-gray-300 rounded">
                                            <option value="safe">🟢 Safe</option>
                                            <option value="standard">🟡 Standard</option>
                                            <option value="review">🔴 Review</option>
                                        </select>
                                        <span x-show="!orderEditable" 
                                              :class="{
                                                  'text-green-600': item.review_priority === 'safe',
                                                  'text-yellow-600': item.review_priority === 'standard',
                                                  'text-red-600': item.review_priority === 'review'
                                              }"
                                              x-text="item.review_priority === 'safe' ? '🟢 Safe' : (item.review_priority === 'standard' ? '🟡 Standard' : '🔴 Review')"></span>
                                    </td>
                                    
                                    <td class="px-3 py-3 whitespace-nowrap text-sm font-medium">
                                        <div x-show="orderEditable" class="flex space-x-2">
                                            <button @click="setQuantityToZero(item.id)" 
                                                    class="text-red-600 hover:text-red-900">
                                                Skip
                                            </button>
                                            <button @click="resetToSuggested(item.id, item.suggested_quantity)" 
                                                    class="text-blue-600 hover:text-blue-900">
                                                Reset
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Alpine.js Component -->
    <script>
        function orderReview() {
            return {
                activeTab: 'to_order',
                sortOrder: 'quantity_desc',
                itemQuantities: {},
                itemCaseQuantities: {},
                orderEditable: {{ $order->isEditable() ? 'true' : 'false' }},
                orderTotals: {
                    total_items: {{ $statistics['total_items'] }},
                    total_value: {{ $order->total_value }}
                },
                items: [
                    @foreach($order->items as $item)
                    {
                        id: {{ $item->id }},
                        product_id: '{{ $item->product_id }}',
                        product_name: '{{ addslashes($item->product->NAME) }}',
                        product_code: '{{ $item->product->CODE }}',
                        suggested_quantity: {{ $item->suggested_quantity }},
                        final_quantity: {{ $item->final_quantity }},
                        case_units: {{ $item->case_units }},
                        suggested_cases: {{ $item->suggested_cases }},
                        final_cases: {{ $item->final_cases }},
                        is_case_product: {{ $item->case_units > 1 ? 'true' : 'false' }},
                        unit_cost: {{ $item->unit_cost }},
                        total_cost: {{ $item->total_cost }},
                        review_priority: '{{ $item->review_priority }}',
                        auto_approved: {{ $item->auto_approved ? 'true' : 'false' }},
                        was_adjusted: {{ $item->wasAdjusted() ? 'true' : 'false' }},
                        adjustment_percentage: {{ $item->wasAdjusted() ? round($item->getAdjustmentPercentage(), 1) : 0 }},
                        context_data: {!! json_encode($item->context_data) !!}
                    },
                    @endforeach
                ],
                
                get sortedItems() {
                    let sorted = [...this.items];
                    
                    // For not_ordered tab, default to sales_desc sorting
                    if (this.activeTab === 'not_ordered' && this.sortOrder === 'quantity_desc') {
                        return sorted.sort((a, b) => (b.context_data?.total_sales_6m || 0) - (a.context_data?.total_sales_6m || 0));
                    }
                    
                    switch (this.sortOrder) {
                        case 'quantity_desc':
                            return sorted.sort((a, b) => {
                                if (b.final_quantity !== a.final_quantity) {
                                    return b.final_quantity - a.final_quantity;
                                }
                                return (b.context_data?.total_sales_6m || 0) - (a.context_data?.total_sales_6m || 0);
                            });
                        case 'sales_desc':
                            return sorted.sort((a, b) => (b.context_data?.total_sales_6m || 0) - (a.context_data?.total_sales_6m || 0));
                        case 'name_asc':
                            return sorted.sort((a, b) => a.product_name.localeCompare(b.product_name));
                        case 'priority_review':
                            const priorityOrder = { 'review': 0, 'standard': 1, 'safe': 2 };
                            return sorted.sort((a, b) => priorityOrder[a.review_priority] - priorityOrder[b.review_priority]);
                        default:
                            return sorted;
                    }
                },
                
                shouldShowItem(item) {
                    if (this.activeTab === 'all') return true;
                    if (this.activeTab === 'to_order') {
                        return item.final_quantity > 0;
                    }
                    if (this.activeTab === 'not_ordered') {
                        return item.final_quantity === 0;
                    }
                    return this.activeTab === item.review_priority;
                },
                
                adjustQuantity(itemId, change) {
                    const currentQty = this.itemQuantities[itemId] || this.getItemById(itemId)?.final_quantity || 0;
                    const newQty = Math.max(0, currentQty + change);
                    this.updateQuantity(itemId, newQty);
                },
                
                updateQuantity(itemId, newQuantity) {
                    this.itemQuantities[itemId] = parseFloat(newQuantity);
                    
                    // Update local item data
                    const item = this.getItemById(itemId);
                    if (item) {
                        item.final_quantity = parseFloat(newQuantity);
                        item.total_cost = item.final_quantity * item.unit_cost;
                    }
                    
                    // Send AJAX request to update quantity
                    fetch(`/order-items/${itemId}/quantity`, {
                        method: 'PATCH',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                        },
                        body: JSON.stringify({
                            quantity: newQuantity
                        })
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            this.orderTotals = data.order_totals;
                        }
                    })
                    .catch(error => console.error('Error:', error));
                },
                
                setQuantityToZero(itemId) {
                    const item = this.getItemById(itemId);
                    if (item && item.is_case_product) {
                        this.updateCaseQuantity(itemId, 0);
                    } else {
                        this.updateQuantity(itemId, 0);
                    }
                },
                
                resetToSuggested(itemId, suggestedQty) {
                    const item = this.getItemById(itemId);
                    if (item && item.is_case_product) {
                        this.updateCaseQuantity(itemId, item.suggested_cases);
                    } else {
                        this.updateQuantity(itemId, suggestedQty);
                    }
                },
                
                adjustCaseQuantity(itemId, change) {
                    const currentQty = this.itemCaseQuantities[itemId] || this.getItemById(itemId)?.final_cases || 0;
                    const newQty = Math.max(0, currentQty + change);
                    this.updateCaseQuantity(itemId, newQty);
                },
                
                updateCaseQuantity(itemId, newCaseQuantity) {
                    this.itemCaseQuantities[itemId] = parseFloat(newCaseQuantity);
                    
                    // Update local item data
                    const item = this.getItemById(itemId);
                    if (item) {
                        item.final_cases = parseFloat(newCaseQuantity);
                        item.final_quantity = item.final_cases * item.case_units;
                        item.total_cost = item.final_quantity * item.unit_cost;
                    }
                    
                    // Send AJAX request to update case quantity
                    fetch(`/order-items/${itemId}/cases`, {
                        method: 'PATCH',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                        },
                        body: JSON.stringify({
                            cases: newCaseQuantity
                        })
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            this.orderTotals = data.order_totals;
                            // Update the item with server response
                            if (item) {
                                item.final_cases = data.item.final_cases;
                                item.final_quantity = data.item.final_quantity;
                                item.total_cost = data.item.total_cost;
                                item.was_adjusted = data.item.was_adjusted;
                                item.adjustment_percentage = data.item.adjustment_percentage;
                            }
                        }
                    })
                    .catch(error => console.error('Error:', error));
                },
                
                updateItemCost(itemId, newCost) {
                    if (!newCost || newCost <= 0) return;
                    
                    // Update local item data
                    const item = this.getItemById(itemId);
                    if (item) {
                        item.unit_cost = parseFloat(newCost);
                        item.total_cost = item.final_quantity * item.unit_cost;
                        if (item.context_data) {
                            item.context_data.has_cost_data = true;
                            item.context_data.cost_source = 'manual_entry';
                        }
                    }
                    
                    // Send AJAX request to update cost
                    fetch(`/order-items/${itemId}/cost`, {
                        method: 'PATCH',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                        },
                        body: JSON.stringify({
                            cost: newCost
                        })
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            this.orderTotals = data.order_totals;
                            // Update the item with server response
                            if (item) {
                                item.unit_cost = data.item.unit_cost;
                                item.total_cost = data.item.total_cost;
                            }
                        }
                    })
                    .catch(error => console.error('Error:', error));
                },
                
                editCost(itemId, currentCost) {
                    const newCost = prompt('Enter new unit cost:', currentCost);
                    if (newCost && parseFloat(newCost) >= 0) {
                        this.updateItemCost(itemId, parseFloat(newCost));
                    }
                },
                
                getItemById(itemId) {
                    return this.items.find(item => item.id === itemId);
                },
                
                updateProductPriority(productId, priority) {
                    fetch('/products/update-priority', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                        },
                        body: JSON.stringify({
                            product_id: productId,
                            priority: priority
                        })
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            // Update local item priority
                            const item = this.items.find(item => item.product_id === productId);
                            if (item) {
                                item.review_priority = priority;
                            }
                            
                            // Show success message
                            this.showMessage(data.message, 'success');
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        this.showMessage('Failed to update priority', 'error');
                    });
                },
                
                showProductDetails(itemId) {
                    const item = this.getItemById(itemId);
                    if (item && item.context_data) {
                        const details = `
                            Product: ${item.product_name}
                            Code: ${item.product_code}
                            
                            Sales Data:
                            • Weekly Average: ${item.context_data.avg_weekly_sales || 'N/A'}
                            • 6 Month Total: ${item.context_data.total_sales_6m || 0}
                            • Last Sale: ${item.context_data.last_sale_date || 'Never'}
                            • Sales Trend: ${item.context_data.sales_trend || 'N/A'}
                            
                            Stock Data:
                            • Current Stock: ${item.context_data.current_stock || 'N/A'}
                            • Days Remaining: ${item.context_data.stock_days_remaining || 'N/A'}
                            • Safety Factor: ${item.context_data.safety_factor || 'N/A'}
                        `;
                        alert(details);
                    }
                },
                
                loadProductTooltip(itemId, event) {
                    // Basic tooltip implementation
                    const item = this.getItemById(itemId);
                    if (item && item.context_data) {
                        event.target.title = `6m Sales: ${item.context_data.total_sales_6m || 0} | Last Sale: ${item.context_data.last_sale_date || 'Never'}`;
                    }
                },
                
                hideTooltip() {
                    // Remove tooltip
                },
                
                showMessage(message, type) {
                    // Simple message display (could be enhanced with a proper toast system)
                    const className = type === 'success' ? 'text-green-600' : 'text-red-600';
                    const messageEl = document.createElement('div');
                    messageEl.className = `fixed top-4 right-4 p-4 rounded bg-white shadow-lg border ${className}`;
                    messageEl.textContent = message;
                    document.body.appendChild(messageEl);
                    
                    setTimeout(() => {
                        messageEl.remove();
                    }, 3000);
                },
                
                getCostSourceIcon(source) {
                    const icons = {
                        'purchase_price': '✅',
                        'supplier_link': '🔗',
                        'retail_price': '⚠️',
                        'recent_purchase': '📊',
                        'manual_entry': '✏️',
                        'no_cost_data': '❌'
                    };
                    return icons[source] || '❓';
                },
                
                getItemsToOrderCount() {
                    return this.items.filter(item => item.final_quantity > 0).length;
                },
                
                getNotOrderedCount() {
                    return this.items.filter(item => item.final_quantity === 0).length;
                }
            }
        }
        
        function autoApproveSafeItems() {
            if (confirm('Auto-approve all safe items? This will mark them as approved without further review.')) {
                fetch(`/orders/{{ $order->id }}/auto-approve-safe`, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                    }
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        location.reload();
                    }
                })
                .catch(error => console.error('Error:', error));
            }
        }
        
        function completeOrder() {
            if (confirm('Complete this order? This will mark it as ready for submission to the supplier.')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = '/orders/{{ $order->id }}/complete';
                
                const token = document.createElement('input');
                token.type = 'hidden';
                token.name = '_token';
                token.value = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
                form.appendChild(token);
                
                document.body.appendChild(form);
                form.submit();
            }
        }
    </script>
</x-admin-layout>