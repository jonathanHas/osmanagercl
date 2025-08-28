<x-admin-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('F&V Price Synchronization') }}
            </h2>
            <a href="{{ route('fruit-veg.index') }}" class="text-blue-600 hover:text-blue-800">
                ← Back to F&V Dashboard
            </a>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            
            <!-- Statistics Cards -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
                <div class="bg-white overflow-hidden shadow-sm rounded-lg p-4">
                    <div class="text-sm text-gray-500">Total F&V Products</div>
                    <div class="text-2xl font-bold text-gray-900">{{ number_format($stats['total_fv_products']) }}</div>
                </div>
                <div class="bg-white overflow-hidden shadow-sm rounded-lg p-4">
                    <div class="text-sm text-gray-500">With Price History</div>
                    <div class="text-2xl font-bold text-blue-600">{{ number_format($stats['products_with_history']) }}</div>
                </div>
                <div class="bg-white overflow-hidden shadow-sm rounded-lg p-4">
                    <div class="text-sm text-gray-500">Synchronized</div>
                    <div class="text-2xl font-bold text-green-600">{{ number_format($stats['synchronized']) }}</div>
                </div>
                <div class="bg-white overflow-hidden shadow-sm rounded-lg p-4">
                    <div class="text-sm text-gray-500">Out of Sync</div>
                    <div class="text-2xl font-bold text-red-600">{{ number_format($stats['out_of_sync']) }}</div>
                </div>
            </div>

            @if(count($discrepancies) > 0)
                <!-- Bulk Actions -->
                <div class="bg-white shadow-sm rounded-lg p-6 mb-6">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Bulk Actions</h3>
                    <div class="flex flex-wrap gap-4">
                        <button type="button" id="selectAllBtn" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md text-sm font-medium">
                            Select All
                        </button>
                        <button type="button" id="clearSelectionBtn" class="bg-gray-300 hover:bg-gray-400 text-gray-700 px-4 py-2 rounded-md text-sm font-medium">
                            Clear Selection
                        </button>
                        <button type="button" id="bulkSyncHistoryToPosBtn" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-md text-sm font-medium" disabled>
                            Sync Selected: History → POS
                        </button>
                        <button type="button" id="bulkSyncPosToHistoryBtn" class="bg-orange-600 hover:bg-orange-700 text-white px-4 py-2 rounded-md text-sm font-medium" disabled>
                            Sync Selected: POS → History
                        </button>
                    </div>
                    <div id="selectionCount" class="mt-2 text-sm text-gray-600">
                        0 products selected
                    </div>
                </div>

                <!-- Price Discrepancies Table -->
                <div class="bg-white shadow-sm rounded-lg">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h3 class="text-lg font-medium text-gray-900">Price Discrepancies</h3>
                        <p class="text-sm text-gray-500 mt-1">Products where POS price and price history don't match</p>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="w-12 px-6 py-3">
                                        <input type="checkbox" id="selectAllCheckbox" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Product</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">POS Price</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">History Price</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Difference</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Last Changed</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                @foreach($discrepancies as $discrepancy)
                                    <tr>
                                        <td class="px-6 py-4">
                                            <input type="checkbox" class="product-checkbox rounded border-gray-300 text-blue-600 focus:ring-blue-500" value="{{ $discrepancy['code'] }}">
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm font-medium text-gray-900">{{ $discrepancy['name'] }}</div>
                                            <div class="text-sm text-gray-500">{{ $discrepancy['code'] }}</div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm text-gray-900">€{{ number_format($discrepancy['pos_price'], 2) }}</div>
                                            <div class="text-xs text-gray-500">Net: €{{ number_format($discrepancy['pos_net_price'], 4) }}</div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm text-gray-900">€{{ number_format($discrepancy['history_price'], 2) }}</div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm {{ $discrepancy['difference'] > 0 ? 'text-green-600' : 'text-red-600' }}">
                                                {{ $discrepancy['difference'] > 0 ? '+' : '' }}€{{ number_format($discrepancy['difference'], 2) }}
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            {{ \Carbon\Carbon::parse($discrepancy['last_changed'])->format('M j, Y H:i') }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                            <div class="flex gap-2">
                                                <button type="button" 
                                                        class="sync-btn text-green-600 hover:text-green-900"
                                                        data-product-code="{{ $discrepancy['code'] }}"
                                                        data-direction="history_to_pos"
                                                        title="Update POS to €{{ number_format($discrepancy['history_price'], 2) }}">
                                                    History → POS
                                                </button>
                                                <button type="button" 
                                                        class="sync-btn text-orange-600 hover:text-orange-900"
                                                        data-product-code="{{ $discrepancy['code'] }}"
                                                        data-direction="pos_to_history"
                                                        title="Update history to €{{ number_format($discrepancy['pos_price'], 2) }}">
                                                    POS → History
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @else
                <!-- No Discrepancies -->
                <div class="bg-white shadow-sm rounded-lg p-8 text-center">
                    <div class="text-green-600 mb-4">
                        <svg class="mx-auto h-16 w-16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                        </svg>
                    </div>
                    <h3 class="text-lg font-medium text-gray-900 mb-2">All Prices Synchronized!</h3>
                    <p class="text-gray-500">All F&V products with price history are properly synchronized between the POS database and price history.</p>
                </div>
            @endif

        </div>
    </div>

    <!-- Loading overlay -->
    <div id="loadingOverlay" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden z-50">
        <div class="flex items-center justify-center h-full">
            <div class="bg-white p-6 rounded-lg shadow-lg">
                <div class="flex items-center">
                    <svg class="animate-spin -ml-1 mr-3 h-5 w-5 text-blue-600" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    <span>Processing...</span>
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const selectAllCheckbox = document.getElementById('selectAllCheckbox');
            const productCheckboxes = document.querySelectorAll('.product-checkbox');
            const selectAllBtn = document.getElementById('selectAllBtn');
            const clearSelectionBtn = document.getElementById('clearSelectionBtn');
            const bulkSyncHistoryToPosBtn = document.getElementById('bulkSyncHistoryToPosBtn');
            const bulkSyncPosToHistoryBtn = document.getElementById('bulkSyncPosToHistoryBtn');
            const selectionCount = document.getElementById('selectionCount');
            const loadingOverlay = document.getElementById('loadingOverlay');

            // Update selection count and button states
            function updateSelectionState() {
                const selectedCount = document.querySelectorAll('.product-checkbox:checked').length;
                selectionCount.textContent = `${selectedCount} product${selectedCount !== 1 ? 's' : ''} selected`;
                
                const hasSelection = selectedCount > 0;
                bulkSyncHistoryToPosBtn.disabled = !hasSelection;
                bulkSyncPosToHistoryBtn.disabled = !hasSelection;
                
                if (hasSelection) {
                    bulkSyncHistoryToPosBtn.classList.remove('opacity-50', 'cursor-not-allowed');
                    bulkSyncPosToHistoryBtn.classList.remove('opacity-50', 'cursor-not-allowed');
                } else {
                    bulkSyncHistoryToPosBtn.classList.add('opacity-50', 'cursor-not-allowed');
                    bulkSyncPosToHistoryBtn.classList.add('opacity-50', 'cursor-not-allowed');
                }
            }

            // Select all functionality
            selectAllBtn.addEventListener('click', function() {
                productCheckboxes.forEach(checkbox => checkbox.checked = true);
                selectAllCheckbox.checked = true;
                updateSelectionState();
            });

            // Clear selection functionality
            clearSelectionBtn.addEventListener('click', function() {
                productCheckboxes.forEach(checkbox => checkbox.checked = false);
                selectAllCheckbox.checked = false;
                updateSelectionState();
            });

            // Header checkbox functionality
            selectAllCheckbox.addEventListener('change', function() {
                productCheckboxes.forEach(checkbox => checkbox.checked = this.checked);
                updateSelectionState();
            });

            // Individual checkboxes
            productCheckboxes.forEach(checkbox => {
                checkbox.addEventListener('change', updateSelectionState);
            });

            // Single product sync
            document.querySelectorAll('.sync-btn').forEach(btn => {
                btn.addEventListener('click', async function() {
                    const productCode = this.dataset.productCode;
                    const direction = this.dataset.direction;
                    
                    loadingOverlay.classList.remove('hidden');
                    
                    try {
                        const response = await fetch('{{ route('fruit-veg.price-sync.sync') }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                            },
                            body: JSON.stringify({
                                product_code: productCode,
                                direction: direction
                            })
                        });

                        const data = await response.json();
                        
                        if (data.success) {
                            showNotification(data.message, 'success');
                            // Refresh the page to show updated state
                            setTimeout(() => window.location.reload(), 1000);
                        } else {
                            showNotification(data.error || 'Failed to sync price', 'error');
                        }
                    } catch (error) {
                        showNotification('Network error occurred', 'error');
                    } finally {
                        loadingOverlay.classList.add('hidden');
                    }
                });
            });

            // Bulk sync functionality
            async function bulkSync(direction) {
                const selectedCodes = Array.from(document.querySelectorAll('.product-checkbox:checked'))
                    .map(checkbox => checkbox.value);
                
                if (selectedCodes.length === 0) return;
                
                loadingOverlay.classList.remove('hidden');
                
                try {
                    const response = await fetch('{{ route('fruit-veg.price-sync.bulk-sync') }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                        },
                        body: JSON.stringify({
                            product_codes: selectedCodes,
                            direction: direction
                        })
                    });

                    const data = await response.json();
                    
                    if (data.success) {
                        showNotification(data.message, 'success');
                    } else {
                        showNotification(data.message + (data.errors.length > 0 ? '\n\nErrors:\n' + data.errors.join('\n') : ''), 'warning');
                    }
                    
                    // Refresh the page to show updated state
                    setTimeout(() => window.location.reload(), 2000);
                } catch (error) {
                    showNotification('Network error occurred', 'error');
                } finally {
                    loadingOverlay.classList.add('hidden');
                }
            }

            bulkSyncHistoryToPosBtn.addEventListener('click', () => bulkSync('history_to_pos'));
            bulkSyncPosToHistoryBtn.addEventListener('click', () => bulkSync('pos_to_history'));

            // Notification function
            function showNotification(message, type = 'info') {
                const colors = {
                    success: 'bg-green-600',
                    error: 'bg-red-600',
                    warning: 'bg-yellow-600',
                    info: 'bg-blue-600'
                };

                const notification = document.createElement('div');
                notification.className = `fixed top-4 right-4 px-6 py-3 rounded-lg shadow-lg text-white z-50 ${colors[type]}`;
                notification.style.whiteSpace = 'pre-line';
                notification.textContent = message;
                
                document.body.appendChild(notification);
                
                setTimeout(() => {
                    notification.remove();
                }, 5000);
            }

            // Initial state
            updateSelectionState();
        });
    </script>
    @endpush
</x-admin-layout>