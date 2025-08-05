<x-admin-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center py-2">
            <div class="flex items-center gap-3">
                <h2 class="text-lg font-semibold">📦 {{ $delivery->delivery_number }}</h2>
                <span class="text-sm text-gray-600 hidden sm:inline">{{ $delivery->supplier?->Supplier ?? 'Unknown' }}</span>
            </div>
            <div class="flex items-center gap-2 text-xs text-gray-500">
                <span class="hidden md:inline">{{ $delivery->delivery_date?->format('d/m/Y') ?? 'No Date' }}</span>
                <a href="{{ route('deliveries.show', $delivery) }}" 
                   class="px-2 py-1 bg-gray-100 hover:bg-gray-200 rounded text-xs">
                    Details
                </a>
            </div>
        </div>
    </x-slot>

    <div x-data="deliveryScanner({{ $delivery->id }})" class="py-2">
        <div class="max-w-7xl mx-auto px-2 sm:px-4">
            <!-- Compact Progress Header - Always Visible -->
            <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-3 mb-3">
                <div class="flex items-center justify-between">
                    <div class="text-sm font-medium text-blue-900 dark:text-blue-100">
                        <span x-text="stats.complete"></span>/<span x-text="stats.total"></span> items
                        (<span x-text="stats.total > 0 ? Math.round((stats.complete / stats.total) * 100) : 0"></span>%)
                    </div>
                    <div class="flex gap-1 text-xs">
                        <span class="bg-red-100 text-red-800 px-2 py-1 rounded" x-text="stats.missing"></span>
                        <span class="bg-yellow-100 text-yellow-800 px-2 py-1 rounded" x-text="stats.partial"></span>
                        <span class="bg-green-100 text-green-800 px-2 py-1 rounded" x-text="stats.complete"></span>
                    </div>
                </div>
                <div class="w-full bg-blue-200 dark:bg-blue-800 rounded-full h-1.5 mt-2">
                    <div class="bg-blue-600 h-1.5 rounded-full transition-all duration-300" 
                         :style="{width: stats.total > 0 ? (stats.complete / stats.total * 100) + '%' : '0%'}"></div>
                </div>
            </div>

            <!-- Compact Scan Input Section -->
            <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg p-3 mb-3">
                <div class="flex gap-2">
                    <input type="text" 
                           x-model="barcode"
                           @keydown.enter="handleBarcodeScan"
                           x-ref="barcodeInput"
                           placeholder="Scan barcode..."
                           class="flex-1 text-lg rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 focus:ring-indigo-500">
                    <input type="number" 
                           x-model="quantity"
                           x-ref="quantityInput"
                           @keydown.enter="processScan"
                           @input="cancelAutoScan"
                           @focus="cancelAutoScan"
                           min="1"
                           placeholder="Qty"
                           class="w-16 text-center rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300">
                    <button @click="processScan" 
                            class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition-colors font-medium touch-manipulation">
                        Add
                    </button>
                </div>
                
                <!-- Compact Recent Scan Feedback -->
                <div x-show="lastScan" x-transition class="mt-2 p-2 rounded text-sm"
                     :class="lastScan?.success ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <span x-show="lastScan?.scan_type === 'case'" class="text-blue-600">📦</span>
                            <span x-show="lastScan?.scan_type === 'unit'" class="text-purple-600">📱</span>
                            <span x-text="lastScan?.message" class="text-xs"></span>
                        </div>
                        <span x-show="lastScan?.success && lastScan?.units_added" 
                              class="text-xs font-semibold">
                            +<span x-text="lastScan?.units_added"></span>
                        </span>
                    </div>
                </div>
                
                <!-- Auto-scan Countdown -->
                <div x-show="autoScanCountdown > 0" 
                     x-transition 
                     class="mt-2 p-2 bg-yellow-50 text-yellow-800 rounded text-sm flex items-center justify-between">
                    <span>Auto-adding in <span x-text="autoScanCountdown"></span>s...</span>
                    <button @click="cancelAutoScan" 
                            class="px-2 py-1 bg-yellow-200 hover:bg-yellow-300 text-yellow-800 rounded text-xs">
                        Cancel
                    </button>
                </div>
            </div>

            <!-- Delivery Items Table -->
            <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg overflow-hidden">
                <div class="px-3 py-2 border-b border-gray-200 dark:border-gray-700">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <h3 class="text-sm font-medium">Items</h3>
                            <select x-model="sortBy" 
                                    class="text-xs border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded px-2 py-1">
                                <option value="new_first">New First</option>
                                <option value="code">Code</option>
                                <option value="description">Name</option>
                                <option value="status">Status</option>
                            </select>
                        </div>
                        <div class="flex items-center gap-2">
                            <a href="{{ route('products.create') }}" 
                               class="px-2 py-1 bg-green-600 hover:bg-green-700 text-white text-xs rounded transition-colors">
                                + Product
                            </a>
                            
                            <!-- Compact Filter Buttons -->
                            <div class="flex gap-1">
                                <button @click="filter = 'all'" 
                                        :class="filter === 'all' ? 'bg-gray-200 dark:bg-gray-700' : ''"
                                        class="px-2 py-1 text-xs rounded hover:bg-gray-100 dark:hover:bg-gray-700">
                                    All
                                </button>
                                <button @click="filter = 'pending'" 
                                        :class="filter === 'pending' ? 'bg-red-200 dark:bg-red-900/30' : ''"
                                        class="px-2 py-1 text-xs rounded hover:bg-gray-100 dark:hover:bg-gray-700">
                                    Missing
                                </button>
                                <button @click="filter = 'partial'" 
                                        :class="filter === 'partial' ? 'bg-yellow-200 dark:bg-yellow-900/30' : ''"
                                        class="px-2 py-1 text-xs rounded hover:bg-gray-100 dark:hover:bg-gray-700">
                                    Partial
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                        <thead class="bg-gray-50 dark:bg-gray-700">
                            <tr>
                                <th class="px-2 py-2 text-center text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">
                                    Img
                                </th>
                                <th class="px-2 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">
                                    Code
                                </th>
                                <th class="px-2 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">
                                    Description
                                </th>
                                <th class="px-2 py-2 text-center text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">
                                    Ord
                                </th>
                                <th class="px-2 py-2 text-center text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">
                                    Rec
                                </th>
                                <th class="px-2 py-2 text-center text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">
                                    Status
                                </th>
                                <th class="px-2 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">
                                    +/-
                                </th>
                            </tr>
                        </thead>
                        <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                            <template x-for="item in filteredItems" :key="item.id">
                                <tr :class="{
                                    'bg-green-50 dark:bg-green-900/10': item.status === 'complete',
                                    'bg-yellow-50 dark:bg-yellow-900/10': item.status === 'partial',
                                    'bg-red-50 dark:bg-red-900/10': item.status === 'pending',
                                    'bg-purple-50 dark:bg-purple-900/10': item.status === 'excess'
                                }">
                                    <td class="px-2 py-2 text-center">
                                        <template x-if="item.product && item.product.supplier && item.has_external_integration">
                                            <div class="relative w-8 h-8 mx-auto group">
                                                <img 
                                                    :src="item.external_image_url" 
                                                    :alt="item.description"
                                                    class="w-8 h-8 object-cover rounded border border-gray-200 dark:border-gray-700 cursor-pointer"
                                                    loading="lazy"
                                                    @@error="$event.target.style.display='none'; $event.target.parentElement.style.display='none'"
                                                >
                                                <!-- Hover preview -->
                                                <div class="absolute left-0 bottom-full mb-2 z-50 opacity-0 group-hover:opacity-100 transition-opacity duration-200 pointer-events-none">
                                                    <img 
                                                        :src="item.external_image_url" 
                                                        :alt="item.description"
                                                        class="w-48 h-48 object-contain rounded-lg border-2 border-white dark:border-gray-600 shadow-xl bg-white"
                                                        loading="lazy"
                                                    >
                                                </div>
                                            </div>
                                        </template>
                                        <template x-if="!item.product || !item.product.supplier || !item.has_external_integration">
                                            <div class="w-8 h-8 mx-auto bg-gray-100 dark:bg-gray-700 rounded border border-gray-200 dark:border-gray-600 flex items-center justify-center">
                                                <svg class="w-3 h-3 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                                </svg>
                                            </div>
                                        </template>
                                    </td>
                                    <td class="px-2 py-2 text-xs">
                                        <div class="font-medium text-gray-900 dark:text-gray-100" x-text="item.supplier_code"></div>
                                        <div class="text-xs text-gray-500 truncate max-w-20" x-text="item.barcode || 'No barcode'"></div>
                                        <div x-show="item.outer_code" class="text-blue-600 text-xs">📦</div>
                                    </td>
                                    <td class="px-2 py-2 text-xs text-gray-900 dark:text-gray-100">
                                        <div class="truncate max-w-32" x-text="item.description" :title="item.description"></div>
                                        <span x-show="item.is_new_product" class="text-orange-600 text-xs">New</span>
                                    </td>
                                    <td class="px-2 py-2 text-xs text-center font-medium">
                                        <div x-text="item.total_ordered_units || item.ordered_quantity"></div>
                                    </td>
                                    <td class="px-2 py-2 text-xs text-center">
                                        <div class="font-medium" x-text="item.total_received_units || item.received_quantity"></div>
                                    </td>
                                    <td class="px-2 py-2 text-center">
                                        <span class="w-2 h-2 rounded-full inline-block"
                                              :class="{
                                                  'bg-green-500': item.status === 'complete',
                                                  'bg-yellow-500': item.status === 'partial',
                                                  'bg-red-500': item.status === 'pending',
                                                  'bg-purple-500': item.status === 'excess'
                                              }">
                                        </span>
                                    </td>
                                    <td class="px-2 py-2 text-center">
                                        <div class="flex gap-1">
                                            <button @click="adjustQuantity(item.id, 1)" 
                                                    class="text-green-600 hover:text-green-900 text-xs px-1">+</button>
                                            <button @click="adjustQuantity(item.id, -1)" 
                                                    :disabled="item.received_quantity === 0"
                                                    class="text-red-600 hover:text-red-900 disabled:opacity-50 text-xs px-1">-</button>
                                        </div>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Compact Action Buttons -->
            <div class="mt-3 flex justify-between gap-2">
                <button @click="showSummary" 
                        class="px-3 py-2 bg-gray-600 text-white rounded text-sm hover:bg-gray-700">
                    Summary
                </button>
                <div class="flex gap-2">
                    <button @click="saveProgress" 
                            class="px-3 py-2 bg-blue-600 text-white rounded text-sm hover:bg-blue-700">
                        Save
                    </button>
                    <button @click="completeDelivery" 
                            :disabled="stats.missing > 0"
                            class="px-3 py-2 bg-green-600 text-white rounded text-sm hover:bg-green-700 disabled:opacity-50 disabled:cursor-not-allowed">
                        Complete
                    </button>
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
    <script>
        function deliveryScanner(deliveryId) {
            return {
                deliveryId: deliveryId,
                barcode: '',
                quantity: 1,
                items: @json($processedItems),
                lastScan: null,
                autoScanCountdown: 0,
                autoScanTimer: null,
                filter: 'all',
                sortBy: 'new_first', // new_first, code, description, status
                stats: {
                    total: 0,
                    complete: 0,
                    partial: 0,
                    missing: 0,
                    excess: 0,
                    unmatched: 0
                },
                
                init() {
                    this.updateStats();
                    this.$refs.barcodeInput.focus();
                },
                
                handleBarcodeScan() {
                    if (!this.barcode) return;
                    
                    // Clear any existing timer
                    if (this.autoScanTimer) {
                        clearInterval(this.autoScanTimer);
                        this.autoScanTimer = null;
                    }
                    
                    // Focus quantity input to allow quick adjustment
                    this.$refs.quantityInput.focus();
                    this.$refs.quantityInput.select();
                    
                    // Start countdown for auto-process
                    this.autoScanCountdown = 3;
                    this.autoScanTimer = setInterval(() => {
                        this.autoScanCountdown--;
                        if (this.autoScanCountdown <= 0) {
                            clearInterval(this.autoScanTimer);
                            this.autoScanTimer = null;
                            this.autoScanCountdown = 0;
                            // Only auto-process if quantity input doesn't have focus
                            if (document.activeElement !== this.$refs.quantityInput) {
                                this.processScan();
                            }
                        }
                    }, 1000);
                },
                
                cancelAutoScan() {
                    if (this.autoScanTimer) {
                        clearInterval(this.autoScanTimer);
                        this.autoScanTimer = null;
                        this.autoScanCountdown = 0;
                    }
                },
                
                get filteredItems() {
                    let filtered = [];
                    if (this.filter === 'all') {
                        filtered = this.items;
                    } else if (this.filter === 'discrepancy') {
                        filtered = this.items.filter(item => ['partial', 'pending', 'excess'].includes(item.status));
                    } else {
                        filtered = this.items.filter(item => item.status === this.filter);
                    }
                    
                    // Apply sorting
                    return this.sortItems(filtered);
                },
                
                sortItems(items) {
                    switch (this.sortBy) {
                        case 'new_first':
                            return [...items].sort((a, b) => {
                                // New products first, then by supplier code
                                if (a.is_new_product && !b.is_new_product) return -1;
                                if (!a.is_new_product && b.is_new_product) return 1;
                                return (a.supplier_code || '').localeCompare(b.supplier_code || '');
                            });
                        case 'code':
                            return [...items].sort((a, b) => (a.supplier_code || '').localeCompare(b.supplier_code || ''));
                        case 'description':
                            return [...items].sort((a, b) => (a.description || '').localeCompare(b.description || ''));
                        case 'status':
                            return [...items].sort((a, b) => {
                                const statusOrder = { pending: 0, partial: 1, excess: 2, complete: 3 };
                                return (statusOrder[a.status] || 99) - (statusOrder[b.status] || 99);
                            });
                        default:
                            return items;
                    }
                },
                
                async processScan() {
                    if (!this.barcode) return;
                    
                    try {
                        const response = await fetch(`/deliveries/${this.deliveryId}/scan`, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                            },
                            body: JSON.stringify({
                                barcode: this.barcode,
                                quantity: parseInt(this.quantity)
                            })
                        });
                        
                        const data = await response.json();
                        this.lastScan = data;
                        
                        if (data.success && data.item) {
                            // Update local item
                            const index = this.items.findIndex(i => i.id === data.item.id);
                            if (index !== -1) {
                                this.items[index] = data.item;
                            }
                        }
                        
                        this.updateStats();
                        this.barcode = '';
                        this.quantity = 1;
                        this.cancelAutoScan(); // Clear any pending auto-scan
                        this.$refs.barcodeInput.focus();
                        
                    } catch (error) {
                        console.error('Scan error:', error);
                        this.lastScan = {
                            success: false,
                            message: 'Network error - please try again'
                        };
                    }
                },
                
                async adjustQuantity(itemId, delta) {
                    const item = this.items.find(i => i.id === itemId);
                    if (!item) return;
                    
                    const newQty = Math.max(0, item.received_quantity + delta);
                    
                    // Update via API
                    await this.updateItemQuantity(itemId, newQty);
                },
                
                async updateItemQuantity(itemId, quantity) {
                    try {
                        const response = await fetch(`/deliveries/${this.deliveryId}/items/${itemId}/quantity`, {
                            method: 'PATCH',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                            },
                            body: JSON.stringify({ quantity: quantity })
                        });
                        
                        if (response.ok) {
                            const data = await response.json();
                            const index = this.items.findIndex(i => i.id === itemId);
                            if (index !== -1) {
                                this.items[index] = data.item;
                                this.updateStats();
                            }
                        }
                    } catch (error) {
                        console.error('Failed to update quantity:', error);
                    }
                },
                
                updateStats() {
                    this.stats = {
                        total: this.items.length,
                        complete: this.items.filter(i => i.status === 'complete').length,
                        partial: this.items.filter(i => i.status === 'partial').length,
                        missing: this.items.filter(i => i.status === 'pending').length,
                        excess: this.items.filter(i => i.status === 'excess').length,
                        unmatched: 0
                    };
                },
                
                async showSummary() {
                    window.location.href = `/deliveries/${this.deliveryId}/summary`;
                },
                
                async saveProgress() {
                    try {
                        const response = await fetch(`/deliveries/${this.deliveryId}`, {
                            method: 'PATCH',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                            },
                            body: JSON.stringify({ status: 'receiving' })
                        });
                        
                        if (response.ok) {
                            this.lastScan = { success: true, message: 'Progress saved successfully' };
                        }
                    } catch (error) {
                        this.lastScan = { success: false, message: 'Failed to save progress' };
                    }
                },
                
                async completeDelivery() {
                    if (confirm('Complete delivery and update stock? This cannot be undone.')) {
                        try {
                            const response = await fetch(`/deliveries/${this.deliveryId}/complete`, {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'Accept': 'application/json',
                                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                                }
                            });
                            
                            if (response.ok) {
                                window.location.href = `/deliveries/${this.deliveryId}/summary`;
                            } else {
                                alert('Failed to complete delivery');
                            }
                        } catch (error) {
                            alert('Network error - please try again');
                        }
                    }
                }
            };
        }
    </script>
    @endpush
</x-admin-layout>