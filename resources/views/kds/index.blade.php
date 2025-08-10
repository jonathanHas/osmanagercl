<x-admin-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-2 sm:gap-4">
            <h2 class="font-semibold text-base sm:text-xl text-gray-800 dark:text-gray-200 leading-tight">
                <span class="hidden sm:inline">Coffee KDS - Kitchen Display System</span>
                <span class="sm:hidden">Coffee KDS</span>
            </h2>
            <div class="flex flex-wrap items-center gap-2 sm:gap-4">
                <!-- System Status Indicator -->
                <div id="system-status" class="flex items-center gap-1 sm:gap-2 px-2 sm:px-3 py-1 rounded-lg text-xs sm:text-sm bg-green-100 dark:bg-green-900">
                    <span class="relative flex h-2 sm:h-3 w-2 sm:w-3">
                        <span id="status-ping" class="animate-ping absolute inline-flex h-full w-full rounded-full bg-green-400 opacity-75"></span>
                        <span id="status-dot" class="relative inline-flex rounded-full h-2 sm:h-3 w-2 sm:w-3 bg-green-500"></span>
                    </span>
                    <div class="text-sm">
                        <span id="status-text" class="font-semibold text-green-700 dark:text-green-300">
                            System: Active
                        </span>
                        <span id="last-check" class="text-gray-600 dark:text-gray-400 ml-1">
                            Last check: just now
                        </span>
                    </div>
                </div>
                
                <span class="hidden sm:inline text-xs sm:text-sm text-gray-600 dark:text-gray-400">
                    Auto-refresh: <span id="refresh-status" class="font-semibold text-green-600">Active</span>
                </span>
                <button onclick="manualRefresh()" class="px-2 sm:px-3 py-1 bg-blue-600 text-white rounded hover:bg-blue-700 text-xs sm:text-sm">
                    <span class="hidden sm:inline">Refresh Now</span>
                    <span class="sm:hidden">Refresh</span>
                </button>
                
                <!-- Clear Orders Dropdown -->
                <div class="relative" x-data="{ open: false }">
                    <button @click="open = !open" class="px-2 sm:px-3 py-1 bg-gray-600 text-white rounded hover:bg-gray-700 text-xs sm:text-sm">
                        Clear ▼
                    </button>
                    <div x-show="open" @click.away="open = false" 
                         class="absolute right-0 mt-2 w-48 bg-white dark:bg-gray-800 rounded-md shadow-lg z-50">
                        <button onclick="clearCompleted()" 
                                class="block w-full text-left px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700">
                            Clear Completed (1hr+)
                        </button>
                        <button onclick="if(confirm('Clear ALL orders? This cannot be undone!')) clearAll()" 
                                class="block w-full text-left px-4 py-2 text-sm text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20">
                            Clear All Orders
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-full mx-auto sm:px-6 lg:px-8">
            <!-- POS Connection Warning (only if disconnected) -->
            @if(!$systemStatus['pos_connected'])
            <div class="mb-2 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg p-2 sm:p-3">
                <div class="flex items-center gap-2">
                    <svg class="h-4 w-4 text-red-400 flex-shrink-0" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                    </svg>
                    <div class="text-xs sm:text-sm text-red-700 dark:text-red-300">
                        <span class="font-medium">POS Database Disconnected</span> - 
                        Unable to connect to POS system. New orders will not be detected.
                    </div>
                </div>
            </div>
            @endif

            <!-- Orders Grid -->
            <div id="orders-container" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
                @forelse($orders as $order)
                    <div id="order-{{ $order->id }}" class="order-card bg-white dark:bg-gray-800 rounded-lg shadow-lg p-4 border-t-4 
                        {{ $order->status === 'new' ? 'border-red-500' : '' }}
                        {{ $order->status === 'viewed' ? 'border-yellow-500' : '' }}
                        {{ $order->status === 'preparing' ? 'border-blue-500' : '' }}
                        {{ $order->status === 'ready' ? 'border-green-500' : '' }}">
                        
                        <!-- Order Header -->
                        <div class="flex justify-between items-start mb-3">
                            <div>
                                <h3 class="text-2xl font-bold">#{{ $order->ticket_number }}</h3>
                                <p class="text-sm text-gray-600 dark:text-gray-400">{{ $order->order_time->format('H:i:s') }}</p>
                            </div>
                            <div class="text-right">
                                <span class="text-lg font-semibold text-red-600">{{ $order->waiting_time_formatted }}</span>
                                @if($order->customer_info)
                                    <p class="text-sm text-gray-600 dark:text-gray-400">{{ $order->customer_info['name'] ?? '' }}</p>
                                @endif
                            </div>
                        </div>

                        <!-- Order Items -->
                        <div class="border-t border-gray-200 dark:border-gray-700 pt-3 mb-3">
                            @foreach($order->items as $item)
                                <div class="mb-2">
                                    <div class="flex justify-between">
                                        <span class="font-semibold">{{ $item->formatted_quantity }}x</span>
                                        <span>{{ $item->display_name }}</span>
                                    </div>
                                    @if($item->modifiers)
                                        <div class="text-sm text-gray-600 dark:text-gray-400 ml-6">
                                            @foreach($item->modifiers as $key => $value)
                                                <span class="inline-block mr-2">{{ $key }}: {{ $value }}</span>
                                            @endforeach
                                        </div>
                                    @endif
                                    @if($item->notes)
                                        <div class="text-sm text-yellow-600 ml-6">
                                            Note: {{ $item->notes }}
                                        </div>
                                    @endif
                                </div>
                            @endforeach
                        </div>

                        <!-- Single Complete Button -->
                        <div class="mt-3">
                            <button onclick="updateOrderStatus({{ $order->id }}, 'completed')" 
                                class="w-full px-4 py-3 bg-green-600 text-white rounded-lg hover:bg-green-700 font-semibold text-lg">
                                ✓ Complete Order
                            </button>
                        </div>
                    </div>
                @empty
                    <div class="col-span-full text-center py-12">
                        <p class="text-gray-500 dark:text-gray-400 text-lg">No active coffee orders</p>
                        <p class="text-gray-400 dark:text-gray-500 text-sm mt-2">Orders will appear here automatically when placed</p>
                    </div>
                @endforelse
            </div>
            
            <!-- Completed Orders Section -->
            @if(isset($completedOrders) && $completedOrders->count() > 0)
            <div id="completed-orders-section" class="mt-8">
                <h3 class="text-lg font-semibold text-gray-700 dark:text-gray-300 mb-4">Recently Completed Orders</h3>
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow overflow-hidden">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-700">
                            <tr>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Order #</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Items</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Completed</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Action</th>
                            </tr>
                        </thead>
                        <tbody id="completed-orders-tbody" class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                            @foreach($completedOrders as $order)
                            <tr id="completed-{{ $order->id }}" class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                <td class="px-4 py-2 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-gray-100">
                                    #{{ $order->ticket_number }}
                                </td>
                                <td class="px-4 py-2 text-sm text-gray-600 dark:text-gray-400">
                                    @foreach($order->items as $item)
                                        <span class="inline-block">
                                            {{ $item->formatted_quantity }}x {{ $item->display_name }}
                                            @if(!$loop->last), @endif
                                        </span>
                                    @endforeach
                                </td>
                                <td class="px-4 py-2 whitespace-nowrap text-sm text-gray-600 dark:text-gray-400">
                                    {{ $order->completed_at ? $order->completed_at->format('H:i:s') : '' }}
                                </td>
                                <td class="px-4 py-2 whitespace-nowrap">
                                    <button onclick="restoreOrder({{ $order->id }})" 
                                        class="px-3 py-1 bg-blue-500 text-white text-sm rounded hover:bg-blue-600">
                                        ↺ Restore
                                    </button>
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

    @push('scripts')
    <script>
        let eventSource = null;
        let isRefreshing = false;

        // Initialize SSE connection
        function initializeSSE() {
            if (eventSource) {
                eventSource.close();
            }

            eventSource = new EventSource('{{ route('kds.stream') }}');
            
            eventSource.onmessage = function(event) {
                const data = JSON.parse(event.data);
                console.log('SSE Update: Received', data.orders.length, 'active orders,', (data.completed ? data.completed.length : 0), 'completed');
                updateOrdersDisplay(data.orders);
                if (data.completed) {
                    updateCompletedOrdersTable(data.completed);
                }
            };

            eventSource.onerror = function(error) {
                console.error('SSE Error:', error);
                document.getElementById('refresh-status').textContent = 'Disconnected';
                document.getElementById('refresh-status').className = 'font-semibold text-red-600';
                
                // Reconnect after 5 seconds
                setTimeout(() => {
                    initializeSSE();
                }, 5000);
            };

            eventSource.onopen = function() {
                document.getElementById('refresh-status').textContent = 'Active';
                document.getElementById('refresh-status').className = 'font-semibold text-green-600';
            };
        }

        // Restore completed order back to active
        async function restoreOrder(orderId) {
            try {
                const response = await fetch(`{{ url('kds/orders') }}/${orderId}/status`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                    body: JSON.stringify({ status: 'new' })
                });

                if (!response.ok) {
                    throw new Error('Failed to restore order');
                }

                const result = await response.json();
                
                // Remove from completed table
                const row = document.getElementById(`completed-${orderId}`);
                if (row) {
                    row.style.transition = 'opacity 0.5s';
                    row.style.opacity = '0';
                    setTimeout(() => row.remove(), 500);
                }
                
                // Trigger refresh to show in active orders
                setTimeout(() => manualRefresh(), 600);
                
            } catch (error) {
                console.error('Error restoring order:', error);
                alert('Failed to restore order. Please try again.');
            }
        }

        // Update completed orders table
        function updateCompletedOrdersTable(completedOrders) {
            // Find or create the completed orders section
            let completedSection = document.getElementById('completed-orders-section');
            
            if (!completedSection && completedOrders.length > 0) {
                // Create the section if it doesn't exist and we have completed orders
                const container = document.querySelector('.max-w-full.mx-auto');
                const section = document.createElement('div');
                section.id = 'completed-orders-section';
                section.className = 'mt-8';
                section.innerHTML = `
                    <h3 class="text-lg font-semibold text-gray-700 dark:text-gray-300 mb-4">Recently Completed Orders</h3>
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow overflow-hidden">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead class="bg-gray-50 dark:bg-gray-700">
                                <tr>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Order #</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Items</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Completed</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Action</th>
                                </tr>
                            </thead>
                            <tbody id="completed-orders-tbody" class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                            </tbody>
                        </table>
                    </div>
                `;
                container.appendChild(section);
                completedSection = section;
            }
            
            if (completedSection) {
                const tbody = document.getElementById('completed-orders-tbody') || completedSection.querySelector('tbody');
                
                if (completedOrders.length === 0) {
                    // Hide section if no completed orders
                    completedSection.style.display = 'none';
                } else {
                    // Show section and update content
                    completedSection.style.display = 'block';
                    
                    tbody.innerHTML = completedOrders.map(order => `
                        <tr id="completed-${order.id}" class="hover:bg-gray-50 dark:hover:bg-gray-700">
                            <td class="px-4 py-2 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-gray-100">
                                #${order.ticket_number}
                            </td>
                            <td class="px-4 py-2 text-sm text-gray-600 dark:text-gray-400">
                                ${order.items.map(item => 
                                    `<span class="inline-block">${item.quantity}x ${item.product_name}</span>`
                                ).join(', ')}
                            </td>
                            <td class="px-4 py-2 whitespace-nowrap text-sm text-gray-600 dark:text-gray-400">
                                ${order.completed_time}
                            </td>
                            <td class="px-4 py-2 whitespace-nowrap">
                                <button onclick="restoreOrder(${order.id})" 
                                    class="px-3 py-1 bg-blue-500 text-white text-sm rounded hover:bg-blue-600">
                                    ↺ Restore
                                </button>
                            </td>
                        </tr>
                    `).join('');
                }
            }
        }

        // Update orders display
        function updateOrdersDisplay(orders) {
            const container = document.getElementById('orders-container');
            
            if (orders.length === 0) {
                container.innerHTML = `
                    <div class="col-span-full text-center py-12">
                        <p class="text-gray-500 dark:text-gray-400 text-lg">No active coffee orders</p>
                        <p class="text-gray-400 dark:text-gray-500 text-sm mt-2">Orders will appear here automatically when placed</p>
                    </div>
                `;
                return;
            }

            // Check for new orders to play notification sound
            orders.forEach(order => {
                if (!document.getElementById(`order-${order.id}`) && order.status === 'new') {
                    playNotificationSound();
                }
            });

            // Rebuild the orders display
            container.innerHTML = orders.map(order => createOrderCard(order)).join('');
        }

        // Create order card HTML
        function createOrderCard(order) {
            const statusColors = {
                'new': 'border-red-500',
                'viewed': 'border-yellow-500',
                'preparing': 'border-blue-500',
                'ready': 'border-green-500'
            };

            const itemsHtml = order.items.map(item => {
                let modifiersHtml = '';
                if (item.modifiers && Object.keys(item.modifiers).length > 0) {
                    modifiersHtml = `
                        <div class="text-sm text-gray-600 dark:text-gray-400 ml-6">
                            ${Object.entries(item.modifiers).map(([key, value]) => 
                                `<span class="inline-block mr-2">${key}: ${value}</span>`
                            ).join('')}
                        </div>
                    `;
                }

                let notesHtml = '';
                if (item.notes) {
                    notesHtml = `
                        <div class="text-sm text-yellow-600 ml-6">
                            Note: ${item.notes}
                        </div>
                    `;
                }

                return `
                    <div class="mb-2">
                        <div class="flex justify-between">
                            <span class="font-semibold">${item.quantity}x</span>
                            <span>${item.product_name}</span>
                        </div>
                        ${modifiersHtml}
                        ${notesHtml}
                    </div>
                `;
            }).join('');

            const buttonsHtml = getActionButtons(order.id, order.status);

            return `
                <div id="order-${order.id}" class="order-card bg-white dark:bg-gray-800 rounded-lg shadow-lg p-4 border-t-4 ${statusColors[order.status] || ''}">
                    <div class="flex justify-between items-start mb-3">
                        <div>
                            <h3 class="text-2xl font-bold">#${order.ticket_number}</h3>
                            <p class="text-sm text-gray-600 dark:text-gray-400">${order.order_time}</p>
                        </div>
                        <div class="text-right">
                            <span class="text-lg font-semibold text-red-600">${order.waiting_time}</span>
                            ${order.customer_info ? `<p class="text-sm text-gray-600 dark:text-gray-400">${order.customer_info.name || ''}</p>` : ''}
                        </div>
                    </div>
                    <div class="border-t border-gray-200 dark:border-gray-700 pt-3 mb-3">
                        ${itemsHtml}
                    </div>
                    <div class="mt-3">
                        ${buttonsHtml}
                    </div>
                </div>
            `;
        }

        // Get action buttons - simplified to just Complete button
        function getActionButtons(orderId, status) {
            return `
                <button onclick="updateOrderStatus(${orderId}, 'completed')" 
                    class="w-full px-4 py-3 bg-green-600 text-white rounded-lg hover:bg-green-700 font-semibold text-lg">
                    ✓ Complete Order
                </button>
            `;
        }

        // Update order status
        async function updateOrderStatus(orderId, status) {
            try {
                const response = await fetch(`{{ url('kds/orders') }}/${orderId}/status`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                    body: JSON.stringify({ status })
                });

                if (!response.ok) {
                    throw new Error('Failed to update order status');
                }

                const result = await response.json();
                
                // Remove card if completed
                if (status === 'completed') {
                    const card = document.getElementById(`order-${orderId}`);
                    if (card) {
                        card.style.transition = 'opacity 0.5s';
                        card.style.opacity = '0';
                        setTimeout(() => card.remove(), 500);
                    }
                }
                
                // Trigger manual refresh to get updated data
                manualRefresh();
                
            } catch (error) {
                console.error('Error updating order status:', error);
                alert('Failed to update order status. Please try again.');
            }
        }

        // Manual refresh
        async function manualRefresh() {
            if (isRefreshing) return;
            
            isRefreshing = true;
            try {
                // Trigger polling job and get orders in parallel for speed
                const [pollResponse, ordersResponse] = await Promise.all([
                    fetch('{{ route('kds.poll') }}', {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': '{{ csrf_token() }}'
                        }
                    }),
                    fetch('{{ route('kds.orders') }}')
                ]);

                const data = await ordersResponse.json();
                
                // Handle both old format (array) and new format (object with active/completed)
                if (Array.isArray(data)) {
                    updateOrdersDisplay(data);
                } else if (data.active) {
                    updateOrdersDisplay(data.active);
                    if (data.completed) {
                        updateCompletedOrdersTable(data.completed);
                    }
                }
                
            } catch (error) {
                console.error('Error refreshing orders:', error);
            } finally {
                isRefreshing = false;
            }
        }

        // Play notification sound for new orders
        function playNotificationSound() {
            const audio = new Audio('/sounds/notification.mp3');
            audio.play().catch(e => console.log('Could not play notification sound'));
        }

        // Clear completed orders
        async function clearCompleted() {
            try {
                const response = await fetch('{{ route('kds.clear-completed') }}', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    }
                });
                
                const result = await response.json();
                
                if (result.success) {
                    alert(result.message);
                    manualRefresh();
                }
            } catch (error) {
                console.error('Error clearing completed orders:', error);
                alert('Failed to clear completed orders');
            }
        }

        // Clear all orders
        async function clearAll() {
            console.log('Clear All: Starting clear operation...');
            try {
                const response = await fetch('{{ route('kds.clear-all') }}', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    }
                });
                
                const result = await response.json();
                console.log('Clear All: Response received', result);
                
                if (result.success) {
                    alert(result.message);
                    
                    // Log debug info if available
                    if (result.debug) {
                        console.log('Clear All Debug Info:', result.debug);
                    }
                    
                    // Wait a moment before refreshing to ensure clear is processed
                    console.log('Clear All: Waiting 2 seconds before refresh...');
                    setTimeout(() => {
                        console.log('Clear All: Triggering manual refresh');
                        manualRefresh();
                    }, 2000);
                } else {
                    console.error('Clear All: Failed', result.message);
                    alert('Failed: ' + result.message);
                }
            } catch (error) {
                console.error('Error clearing all orders:', error);
                alert('Failed to clear all orders');
            }
        }

        let lastSuccessfulCheck = Date.now();
        let failedChecks = 0;
        
        // Fast direct polling for new orders
        async function fastRealtimeCheck() {
            try {
                const response = await fetch('{{ route('kds.realtime-check') }}');
                const data = await response.json();
                
                if (data.success) {
                    // Update status to show success
                    lastSuccessfulCheck = Date.now();
                    failedChecks = 0;
                    updateSystemStatus(true, data.duration_ms);
                    
                    if (data.orders_created > 0) {
                        console.log(`Created ${data.orders_created} new orders in ${data.duration_ms}ms`);
                        // Refresh display immediately
                        const ordersResponse = await fetch('{{ route('kds.orders') }}');
                        const ordersData = await ordersResponse.json();
                        
                        if (ordersData.active) {
                            updateOrdersDisplay(ordersData.active);
                            if (ordersData.completed) {
                                updateCompletedOrdersTable(ordersData.completed);
                            }
                        }
                    }
                } else {
                    failedChecks++;
                    updateSystemStatus(false);
                }
            } catch (error) {
                console.error('Realtime check error:', error);
                failedChecks++;
                updateSystemStatus(false);
            }
        }
        
        // Update system status indicator
        function updateSystemStatus(isActive, responseTime = null) {
            const statusDiv = document.getElementById('system-status');
            const statusText = document.getElementById('status-text');
            const statusPing = document.getElementById('status-ping');
            const statusDot = document.getElementById('status-dot');
            const lastCheck = document.getElementById('last-check');
            
            if (isActive) {
                statusDiv.className = 'flex items-center gap-1 sm:gap-2 px-2 sm:px-3 py-1 rounded-lg text-xs sm:text-sm bg-green-100 dark:bg-green-900';
                statusText.className = 'font-semibold text-green-700 dark:text-green-300';
                statusText.textContent = 'System: Active';
                statusPing.className = 'animate-ping absolute inline-flex h-full w-full rounded-full bg-green-400 opacity-75';
                statusDot.className = 'relative inline-flex rounded-full h-2 sm:h-3 w-2 sm:w-3 bg-green-500';
                
                if (responseTime) {
                    lastCheck.textContent = `Response: ${responseTime}ms`;
                } else {
                    lastCheck.textContent = 'Last check: just now';
                }
            } else if (failedChecks >= 3) {
                statusDiv.className = 'flex items-center gap-1 sm:gap-2 px-2 sm:px-3 py-1 rounded-lg text-xs sm:text-sm bg-red-100 dark:bg-red-900';
                statusText.className = 'font-semibold text-red-700 dark:text-red-300';
                statusText.textContent = 'System: Disconnected';
                statusPing.className = 'hidden';
                statusDot.className = 'relative inline-flex rounded-full h-2 sm:h-3 w-2 sm:w-3 bg-red-500';
                lastCheck.textContent = 'Connection lost';
            } else {
                statusDiv.className = 'flex items-center gap-1 sm:gap-2 px-2 sm:px-3 py-1 rounded-lg text-xs sm:text-sm bg-yellow-100 dark:bg-yellow-900';
                statusText.className = 'font-semibold text-yellow-700 dark:text-yellow-300';
                statusText.textContent = 'System: Checking...';
                statusDot.className = 'relative inline-flex rounded-full h-2 sm:h-3 w-2 sm:w-3 bg-yellow-500';
            }
        }
        
        // Initialize on page load
        document.addEventListener('DOMContentLoaded', function() {
            initializeSSE();
            
            // Trigger initial poll to check for orders
            manualRefresh();
            
            // Fast polling every 2 seconds for new orders
            setInterval(fastRealtimeCheck, 2000);
            
            // Regular refresh as backup (every 5 seconds)
            setInterval(manualRefresh, 5000);
        });

        // Clean up on page unload
        window.addEventListener('beforeunload', function() {
            if (eventSource) {
                eventSource.close();
            }
        });
        
        // Refresh immediately when page gets focus
        window.addEventListener('focus', function() {
            console.log('Page focused - triggering refresh');
            manualRefresh();
        });
    </script>
    @endpush
</x-admin-layout>