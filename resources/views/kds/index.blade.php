<x-admin-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                Coffee KDS - Kitchen Display System
            </h2>
            <div class="flex items-center gap-4">
                <span class="text-sm text-gray-600 dark:text-gray-400">
                    Auto-refresh: <span id="refresh-status" class="font-semibold text-green-600">Active</span>
                </span>
                <button onclick="manualRefresh()" class="px-3 py-1 bg-blue-600 text-white rounded hover:bg-blue-700">
                    Refresh Now
                </button>
            </div>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-full mx-auto sm:px-6 lg:px-8">
            <!-- Order Status Legend -->
            <div class="mb-6 bg-white dark:bg-gray-800 rounded-lg shadow p-4">
                <div class="flex flex-wrap gap-4 justify-center">
                    <div class="flex items-center gap-2">
                        <span class="w-4 h-4 bg-red-500 rounded"></span>
                        <span class="text-sm">New</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="w-4 h-4 bg-yellow-500 rounded"></span>
                        <span class="text-sm">Viewed</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="w-4 h-4 bg-blue-500 rounded"></span>
                        <span class="text-sm">Preparing</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="w-4 h-4 bg-green-500 rounded"></span>
                        <span class="text-sm">Ready</span>
                    </div>
                </div>
            </div>

            <!-- Orders Grid -->
            <div id="orders-container" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
                @forelse($orders as $order)
                    <div id="order-{{ $order->id }}" class="order-card bg-white dark:bg-gray-800 rounded-lg shadow-lg p-4 border-t-4 
                        {{ $order->status === 'new' ? 'border-red-500 animate-pulse' : '' }}
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

                        <!-- Action Buttons -->
                        <div class="grid grid-cols-2 gap-2">
                            @if($order->status === 'new')
                                <button onclick="updateOrderStatus({{ $order->id }}, 'viewed')" 
                                    class="px-3 py-2 bg-yellow-500 text-white rounded hover:bg-yellow-600 text-sm">
                                    View
                                </button>
                                <button onclick="updateOrderStatus({{ $order->id }}, 'preparing')" 
                                    class="px-3 py-2 bg-blue-500 text-white rounded hover:bg-blue-600 text-sm">
                                    Start
                                </button>
                            @elseif($order->status === 'viewed')
                                <button onclick="updateOrderStatus({{ $order->id }}, 'preparing')" 
                                    class="px-3 py-2 bg-blue-500 text-white rounded hover:bg-blue-600 text-sm col-span-2">
                                    Start Preparing
                                </button>
                            @elseif($order->status === 'preparing')
                                <button onclick="updateOrderStatus({{ $order->id }}, 'ready')" 
                                    class="px-3 py-2 bg-green-500 text-white rounded hover:bg-green-600 text-sm col-span-2">
                                    Mark Ready
                                </button>
                            @elseif($order->status === 'ready')
                                <button onclick="updateOrderStatus({{ $order->id }}, 'completed')" 
                                    class="px-3 py-2 bg-gray-500 text-white rounded hover:bg-gray-600 text-sm col-span-2">
                                    Complete
                                </button>
                            @endif
                        </div>
                    </div>
                @empty
                    <div class="col-span-full text-center py-12">
                        <p class="text-gray-500 dark:text-gray-400 text-lg">No active coffee orders</p>
                        <p class="text-gray-400 dark:text-gray-500 text-sm mt-2">Orders will appear here automatically when placed</p>
                    </div>
                @endforelse
            </div>
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
                updateOrdersDisplay(data.orders);
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
                'new': 'border-red-500 animate-pulse',
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
                    <div class="grid grid-cols-2 gap-2">
                        ${buttonsHtml}
                    </div>
                </div>
            `;
        }

        // Get action buttons based on status
        function getActionButtons(orderId, status) {
            switch(status) {
                case 'new':
                    return `
                        <button onclick="updateOrderStatus(${orderId}, 'viewed')" 
                            class="px-3 py-2 bg-yellow-500 text-white rounded hover:bg-yellow-600 text-sm">
                            View
                        </button>
                        <button onclick="updateOrderStatus(${orderId}, 'preparing')" 
                            class="px-3 py-2 bg-blue-500 text-white rounded hover:bg-blue-600 text-sm">
                            Start
                        </button>
                    `;
                case 'viewed':
                    return `
                        <button onclick="updateOrderStatus(${orderId}, 'preparing')" 
                            class="px-3 py-2 bg-blue-500 text-white rounded hover:bg-blue-600 text-sm col-span-2">
                            Start Preparing
                        </button>
                    `;
                case 'preparing':
                    return `
                        <button onclick="updateOrderStatus(${orderId}, 'ready')" 
                            class="px-3 py-2 bg-green-500 text-white rounded hover:bg-green-600 text-sm col-span-2">
                            Mark Ready
                        </button>
                    `;
                case 'ready':
                    return `
                        <button onclick="updateOrderStatus(${orderId}, 'completed')" 
                            class="px-3 py-2 bg-gray-500 text-white rounded hover:bg-gray-600 text-sm col-span-2">
                            Complete
                        </button>
                    `;
                default:
                    return '';
            }
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
                // Trigger polling job
                await fetch('{{ route('kds.poll') }}', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    }
                });

                // Get updated orders
                const response = await fetch('{{ route('kds.orders') }}');
                const orders = await response.json();
                updateOrdersDisplay(orders);
                
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

        // Initialize on page load
        document.addEventListener('DOMContentLoaded', function() {
            initializeSSE();
            
            // Trigger initial poll to check for orders
            manualRefresh();
            
            // Set up periodic polling as backup (every 10 seconds)
            setInterval(manualRefresh, 10000);
        });

        // Clean up on page unload
        window.addEventListener('beforeunload', function() {
            if (eventSource) {
                eventSource.close();
            }
        });
    </script>
    @endpush
</x-admin-layout>