<x-admin-layout>
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
        {{-- Header Section --}}
        <div class="flex justify-between items-center mb-6">
            <div>
                <h2 class="text-2xl font-bold text-gray-100">Order Manager</h2>
                <p class="text-gray-400 mt-1">Monitor stock levels for selected suppliers to identify ordering needs</p>
            </div>
            <div class="flex space-x-2">
                <a href="{{ route('order-manager.check') }}"
                   class="bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-4 rounded text-sm">
                    <i class="fas fa-clipboard-check mr-2"></i>Run Stock Check
                </a>
                <a href="{{ route('suppliers.index') }}"
                   class="bg-gray-600 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded text-sm">
                    <i class="fas fa-arrow-left mr-2"></i>Back to Suppliers
                </a>
            </div>
        </div>

        {{-- Stats Section --}}
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
            <div class="bg-gray-800 rounded-lg p-4 text-center">
                <div class="text-3xl font-bold text-blue-400">{{ $stats['managed_suppliers_count'] }}</div>
                <div class="text-sm text-gray-400">Managed Suppliers</div>
            </div>
            <div class="bg-gray-800 rounded-lg p-4 text-center">
                <div class="text-3xl font-bold text-yellow-400">{{ $stats['low_stock_products_count'] }}</div>
                <div class="text-sm text-gray-400">Products Below Threshold</div>
            </div>
            <div class="bg-gray-800 rounded-lg p-4 text-center">
                <div class="text-3xl font-bold text-red-400">{{ $stats['out_of_stock_count'] }}</div>
                <div class="text-sm text-gray-400">Out of Stock</div>
            </div>
        </div>

        {{-- Alert if items need attention --}}
        @if($stats['low_stock_products_count'] > 0)
            <div class="bg-yellow-900/50 border-l-4 border-yellow-500 p-4 mb-6 rounded">
                <div class="flex items-center">
                    <i class="fas fa-exclamation-triangle text-yellow-500 mr-3"></i>
                    <div>
                        <p class="text-yellow-200 font-medium">Stock Attention Required</p>
                        <p class="text-yellow-300/70 text-sm">
                            {{ $stats['low_stock_products_count'] }} product(s) from managed suppliers are below threshold.
                            <a href="{{ route('order-manager.check') }}" class="underline hover:text-yellow-200">Run Stock Check</a> for details.
                        </p>
                    </div>
                </div>
            </div>
        @endif

        {{-- Instructions --}}
        <div class="bg-gray-800 rounded-lg p-4 mb-6">
            <h3 class="text-lg font-semibold text-gray-100 mb-2">How to Use</h3>
            <ul class="text-gray-400 text-sm space-y-1">
                <li><i class="fas fa-check text-green-500 mr-2"></i>Toggle suppliers below to add them to Order Manager monitoring</li>
                <li><i class="fas fa-sliders-h text-blue-500 mr-2"></i>Set a stock threshold for each supplier (products below this level will be flagged)</li>
                <li><i class="fas fa-expand-alt text-cyan-500 mr-2"></i>Click the expand button to view all products and their current stock levels</li>
                <li><i class="fas fa-clipboard-check text-purple-500 mr-2"></i>Click "Run Stock Check" to see a summary of products needing attention</li>
            </ul>
        </div>

        {{-- Suppliers Table --}}
        <div class="bg-gray-800 rounded-lg overflow-hidden">
            <div class="px-4 py-3 border-b border-gray-700">
                <h3 class="text-lg font-semibold text-gray-100">POS-Linked Suppliers</h3>
                <p class="text-gray-400 text-sm">Only suppliers linked to the POS system can be managed</p>
            </div>

            @if($suppliers->isEmpty())
                <div class="p-8 text-center text-gray-400">
                    <i class="fas fa-link text-4xl mb-3"></i>
                    <p>No POS-linked suppliers found.</p>
                    <p class="text-sm mt-1">Link suppliers to the POS system to use Order Manager.</p>
                </div>
            @else
                <table class="min-w-full divide-y divide-gray-700">
                    <thead class="bg-gray-900">
                        <tr>
                            <th class="px-2 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider w-10"></th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Managed</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Supplier</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">POS ID</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Threshold</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Low Stock</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-700">
                        @foreach($suppliers as $supplier)
                            {{-- Main Supplier Row --}}
                            <tr class="hover:bg-gray-700/50" id="supplier-row-{{ $supplier->id }}">
                                {{-- Expand Button --}}
                                <td class="px-2 py-3">
                                    <button type="button"
                                            onclick="toggleProducts({{ $supplier->id }})"
                                            id="expand-btn-{{ $supplier->id }}"
                                            class="text-gray-400 hover:text-gray-200 focus:outline-none p-1"
                                            title="View products">
                                        <i id="expand-icon-{{ $supplier->id }}" class="fas fa-chevron-right transition-transform duration-200"></i>
                                    </button>
                                </td>

                                {{-- Toggle Switch --}}
                                <td class="px-4 py-3">
                                    <button type="button"
                                            onclick="toggleManaged({{ $supplier->id }})"
                                            id="toggle-btn-{{ $supplier->id }}"
                                            class="relative inline-flex h-6 w-11 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 focus:ring-offset-gray-800 {{ $supplier->is_order_managed ? 'bg-blue-600' : 'bg-gray-600' }}">
                                        <span class="sr-only">Toggle managed</span>
                                        <span id="toggle-dot-{{ $supplier->id }}"
                                              class="pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out {{ $supplier->is_order_managed ? 'translate-x-5' : 'translate-x-0' }}"></span>
                                    </button>
                                </td>

                                {{-- Supplier Info --}}
                                <td class="px-4 py-3">
                                    <div class="text-sm font-medium text-gray-100">{{ $supplier->name }}</div>
                                    <div class="text-xs text-gray-400">{{ $supplier->code }}</div>
                                </td>

                                {{-- POS ID --}}
                                <td class="px-4 py-3">
                                    <span class="text-sm text-purple-400 font-mono">{{ $supplier->external_pos_id }}</span>
                                </td>

                                {{-- Threshold Input --}}
                                <td class="px-4 py-3">
                                    <div class="flex items-center space-x-2">
                                        <input type="number"
                                               id="threshold-input-{{ $supplier->id }}"
                                               value="{{ $supplier->order_manager_threshold }}"
                                               min="0"
                                               max="1000"
                                               class="w-20 bg-gray-700 border-gray-600 text-gray-100 rounded text-sm text-center {{ $supplier->is_order_managed ? '' : 'opacity-50' }}"
                                               {{ $supplier->is_order_managed ? '' : 'disabled' }}
                                               onchange="updateThreshold({{ $supplier->id }})">
                                        <span class="text-xs text-gray-400">units</span>
                                    </div>
                                </td>

                                {{-- Low Stock Count --}}
                                <td class="px-4 py-3">
                                    @if($supplier->is_order_managed && $supplier->low_stock_count !== null)
                                        @if($supplier->low_stock_count > 0)
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-900 text-yellow-300">
                                                {{ $supplier->low_stock_count }} items
                                            </span>
                                        @else
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-900 text-green-300">
                                                All OK
                                            </span>
                                        @endif
                                    @else
                                        <span class="text-gray-500 text-sm">-</span>
                                    @endif
                                </td>
                            </tr>

                            {{-- Expandable Products Row --}}
                            <tr id="products-row-{{ $supplier->id }}" class="hidden">
                                <td colspan="6" class="px-0 py-0">
                                    <div id="products-container-{{ $supplier->id }}" class="bg-gray-900/50 border-t border-b border-gray-600">
                                        {{-- Content loaded via AJAX --}}
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    </div>

    @push('scripts')
    <script>
        // Track which suppliers are expanded
        const expandedSuppliers = new Set();
        const loadedSuppliers = new Set();

        function toggleProducts(supplierId) {
            const row = document.getElementById(`products-row-${supplierId}`);
            const icon = document.getElementById(`expand-icon-${supplierId}`);
            const container = document.getElementById(`products-container-${supplierId}`);

            if (expandedSuppliers.has(supplierId)) {
                // Collapse
                row.classList.add('hidden');
                icon.classList.remove('rotate-90');
                expandedSuppliers.delete(supplierId);
            } else {
                // Expand
                row.classList.remove('hidden');
                icon.classList.add('rotate-90');
                expandedSuppliers.add(supplierId);

                // Load products if not already loaded
                if (!loadedSuppliers.has(supplierId)) {
                    loadProducts(supplierId, container);
                }
            }
        }

        function loadProducts(supplierId, container) {
            container.innerHTML = `
                <div class="p-4 text-center text-gray-400">
                    <i class="fas fa-spinner fa-spin mr-2"></i>Loading products...
                </div>
            `;

            fetch(`/order-manager/${supplierId}/products`, {
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    loadedSuppliers.add(supplierId);
                    renderProducts(container, data);
                } else {
                    container.innerHTML = `
                        <div class="p-4 text-center text-red-400">
                            <i class="fas fa-exclamation-circle mr-2"></i>${data.message || 'Failed to load products'}
                        </div>
                    `;
                }
            })
            .catch(error => {
                console.error('Error:', error);
                container.innerHTML = `
                    <div class="p-4 text-center text-red-400">
                        <i class="fas fa-exclamation-circle mr-2"></i>Error loading products
                    </div>
                `;
            });
        }

        function renderProducts(container, data) {
            if (data.products.length === 0) {
                container.innerHTML = `
                    <div class="p-4 text-center text-gray-400">
                        <i class="fas fa-box-open mr-2"></i>No stocked products found for this supplier
                    </div>
                `;
                return;
            }

            // Summary stats
            let html = `
                <div class="px-4 py-2 bg-gray-800/50 border-b border-gray-700 flex items-center justify-between">
                    <div class="flex items-center space-x-4 text-sm">
                        <span class="text-gray-400">
                            <i class="fas fa-boxes mr-1"></i>
                            <span class="font-medium text-gray-200">${data.total_count}</span> products
                        </span>
                        <span class="text-gray-400">|</span>
                        <span class="text-gray-400">Threshold: <span class="font-medium text-gray-200">${data.threshold}</span> units</span>
                    </div>
                    <div class="flex items-center space-x-3 text-sm">
                        ${data.out_of_stock_count > 0 ? `
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-red-900 text-red-300">
                                ${data.out_of_stock_count} out of stock
                            </span>
                        ` : ''}
                        ${data.low_stock_count > 0 ? `
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-yellow-900 text-yellow-300">
                                ${data.low_stock_count} low stock
                            </span>
                        ` : ''}
                        ${data.out_of_stock_count === 0 && data.low_stock_count === 0 ? `
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-900 text-green-300">
                                <i class="fas fa-check mr-1"></i>All OK
                            </span>
                        ` : ''}
                    </div>
                </div>
            `;

            // Products table
            html += `
                <div class="max-h-96 overflow-y-auto">
                    <table class="min-w-full">
                        <thead class="bg-gray-800 sticky top-0">
                            <tr>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-400 uppercase">Product</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-400 uppercase">Barcode</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-400 uppercase">Supplier Code</th>
                                <th class="px-4 py-2 text-center text-xs font-medium text-gray-400 uppercase">Current Stock</th>
                                <th class="px-4 py-2 text-center text-xs font-medium text-gray-400 uppercase">Case Units</th>
                                <th class="px-4 py-2 text-center text-xs font-medium text-gray-400 uppercase">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-700/50">
            `;

            data.products.forEach(product => {
                const stockClass = product.stock_status === 'out_of_stock' ? 'text-red-400' :
                                   product.stock_status === 'low_stock' ? 'text-yellow-400' : 'text-green-400';
                const statusBadge = product.stock_status === 'out_of_stock' ?
                    '<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-red-900 text-red-300">Out of Stock</span>' :
                    product.stock_status === 'low_stock' ?
                    '<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-yellow-900 text-yellow-300">Low Stock</span>' :
                    '<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-900/50 text-green-400">OK</span>';

                html += `
                    <tr class="hover:bg-gray-700/30">
                        <td class="px-4 py-2 text-sm text-gray-200">${product.product_name}</td>
                        <td class="px-4 py-2 text-sm font-mono text-gray-400">${product.barcode}</td>
                        <td class="px-4 py-2 text-sm text-gray-400">${product.supplier_code || '-'}</td>
                        <td class="px-4 py-2 text-center">
                            <span class="text-sm font-bold ${stockClass}">${parseFloat(product.current_stock).toFixed(1)}</span>
                        </td>
                        <td class="px-4 py-2 text-center text-sm text-gray-400">${product.case_units || '-'}</td>
                        <td class="px-4 py-2 text-center">${statusBadge}</td>
                    </tr>
                `;
            });

            html += `
                        </tbody>
                    </table>
                </div>
            `;

            container.innerHTML = html;
        }

        function toggleManaged(supplierId) {
            const btn = document.getElementById(`toggle-btn-${supplierId}`);
            const dot = document.getElementById(`toggle-dot-${supplierId}`);
            const thresholdInput = document.getElementById(`threshold-input-${supplierId}`);

            fetch(`/order-manager/${supplierId}/toggle`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'Accept': 'application/json'
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Update toggle visual state
                    if (data.is_order_managed) {
                        btn.classList.remove('bg-gray-600');
                        btn.classList.add('bg-blue-600');
                        dot.classList.add('translate-x-5');
                        dot.classList.remove('translate-x-0');
                        thresholdInput.disabled = false;
                        thresholdInput.classList.remove('opacity-50');
                    } else {
                        btn.classList.add('bg-gray-600');
                        btn.classList.remove('bg-blue-600');
                        dot.classList.remove('translate-x-5');
                        dot.classList.add('translate-x-0');
                        thresholdInput.disabled = true;
                        thresholdInput.classList.add('opacity-50');
                    }

                    // Show toast notification
                    showToast(data.message, 'success');

                    // Reload after short delay to update counts
                    setTimeout(() => window.location.reload(), 1000);
                } else {
                    showToast(data.message || 'Failed to toggle supplier', 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('An error occurred', 'error');
            });
        }

        function updateThreshold(supplierId) {
            const input = document.getElementById(`threshold-input-${supplierId}`);
            const threshold = parseInt(input.value);

            if (isNaN(threshold) || threshold < 0 || threshold > 1000) {
                showToast('Threshold must be between 0 and 1000', 'error');
                return;
            }

            fetch(`/order-manager/${supplierId}/threshold`, {
                method: 'PATCH',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'Accept': 'application/json'
                },
                body: JSON.stringify({ threshold: threshold })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast(data.message, 'success');
                    // Clear loaded cache so products reload with new threshold
                    loadedSuppliers.delete(supplierId);
                    // Reload if expanded to show updated status
                    if (expandedSuppliers.has(supplierId)) {
                        const container = document.getElementById(`products-container-${supplierId}`);
                        loadProducts(supplierId, container);
                    }
                } else {
                    showToast(data.message || 'Failed to update threshold', 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('An error occurred', 'error');
            });
        }

        function showToast(message, type = 'info') {
            // Create toast element
            const toast = document.createElement('div');
            toast.className = `fixed bottom-4 right-4 px-4 py-2 rounded-lg text-white text-sm shadow-lg z-50 transition-opacity duration-300 ${
                type === 'success' ? 'bg-green-600' :
                type === 'error' ? 'bg-red-600' : 'bg-blue-600'
            }`;
            toast.textContent = message;
            document.body.appendChild(toast);

            // Remove after 3 seconds
            setTimeout(() => {
                toast.classList.add('opacity-0');
                setTimeout(() => toast.remove(), 300);
            }, 3000);
        }
    </script>
    @endpush
</x-admin-layout>
