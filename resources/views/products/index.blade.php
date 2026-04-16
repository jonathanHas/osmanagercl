<x-admin-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                {{ __('Products') }}
            </h2>
            <x-action-buttons :actions="[
                [
                    'type' => 'link',
                    'route' => 'products.create',
                    'label' => 'Create New Product',
                    'color' => 'success',
                    'class' => 'inline-flex items-center px-4 py-2 bg-green-600 hover:bg-green-700 text-white font-medium rounded-md transition-colors duration-200',
                    'icon' => 'M12 6v6m0 0v6m0-6h6m-6 0H6'
                ]
            ]" size="lg" />
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <!-- Statistics Toggle -->
            <div class="mb-6">
                @if($showStats && $statistics)
                    <!-- Statistics Dashboard -->
                    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg mb-4">
                        <div class="p-6 text-gray-900 dark:text-gray-100">
                            <div class="flex justify-between items-center mb-4">
                                <h3 class="text-lg font-semibold">Product Statistics</h3>
                                <a href="{{ request()->fullUrlWithQuery(['show_stats' => null]) }}" 
                                   class="inline-flex items-center px-3 py-1 bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 text-sm rounded-md hover:bg-gray-200 dark:hover:bg-gray-600 transition">
                                    Hide Statistics ▲
                                </a>
                            </div>
                            <div class="grid grid-cols-2 md:grid-cols-6 gap-4">
                                <div class="bg-gray-50 dark:bg-gray-700 p-4 rounded">
                                    <div class="text-2xl font-bold">{{ $statistics['total_products'] }}</div>
                                    <div class="text-sm text-gray-600 dark:text-gray-400">Total Products</div>
                                </div>
                                <div class="bg-gray-50 dark:bg-gray-700 p-4 rounded">
                                    <div class="text-2xl font-bold">{{ $statistics['active_products'] }}</div>
                                    <div class="text-sm text-gray-600 dark:text-gray-400">Active (Non-Service)</div>
                                </div>
                                <div class="bg-gray-50 dark:bg-gray-700 p-4 rounded">
                                    <div class="text-2xl font-bold">{{ $statistics['stocked_products'] }}</div>
                                    <div class="text-sm text-gray-600 dark:text-gray-400">Stocked Products</div>
                                </div>
                                <div class="bg-green-50 dark:bg-green-900 p-4 rounded">
                                    <div class="text-2xl font-bold text-green-700 dark:text-green-300">{{ $statistics['in_stock'] }}</div>
                                    <div class="text-sm text-green-600 dark:text-green-400">In Stock</div>
                                </div>
                                <div class="bg-red-50 dark:bg-red-900 p-4 rounded">
                                    <div class="text-2xl font-bold text-red-700 dark:text-red-300">{{ $statistics['out_of_stock'] }}</div>
                                    <div class="text-sm text-red-600 dark:text-red-400">Out of Stock</div>
                                </div>
                                <div class="bg-gray-50 dark:bg-gray-700 p-4 rounded">
                                    <div class="text-2xl font-bold">{{ $statistics['service_products'] }}</div>
                                    <div class="text-sm text-gray-600 dark:text-gray-400">Service Products</div>
                                </div>
                            </div>
                        </div>
                    </div>
                @else
                    <!-- Show Statistics Button -->
                    <div class="text-center">
                        <a href="{{ request()->fullUrlWithQuery(['show_stats' => '1']) }}" 
                           class="inline-flex items-center px-4 py-2 bg-blue-100 dark:bg-blue-900 text-blue-700 dark:text-blue-300 text-sm font-medium rounded-md hover:bg-blue-200 dark:hover:bg-blue-800 transition">
                            📊 Show Product Statistics ▼
                        </a>
                    </div>
                @endif
            </div>

            <!-- Search and Filter -->
            <x-filter-form 
                :action="route('products.index')"
                searchName="search"
                :searchValue="$search"
                searchPlaceholder="Search by name, code, reference, or supplier code..."
                :showSubmit="true"
                submitLabel="Search & Filter"
                :filters="[
                    [
                        'name' => 'active_only',
                        'label' => 'Active only (non-service)',
                        'type' => 'checkbox',
                        'checked' => $activeOnly
                    ],
                    [
                        'name' => 'stocked_only',
                        'label' => 'Stocked products',
                        'type' => 'checkbox',
                        'checked' => $stockedOnly
                    ],
                    [
                        'name' => 'in_stock_only',
                        'label' => 'In stock only',
                        'type' => 'checkbox',
                        'checked' => $inStockOnly
                    ],
                    [
                        'name' => 'show_suppliers',
                        'label' => 'Show suppliers',
                        'type' => 'checkbox',
                        'checked' => $showSuppliers
                    ]
                ]">
                
                <div id="supplier-dropdown" class="mt-4 {{ $showSuppliers ? '' : 'hidden' }}">
                    <label for="supplier_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Filter by Supplier</label>
                    <select name="supplier_id" id="supplier_id" class="rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600">
                        <option value="">All Suppliers</option>
                        @if($suppliers->count() > 0)
                            @foreach($suppliers as $supplier)
                                <option value="{{ $supplier->SupplierID }}" {{ $supplierId == $supplier->SupplierID ? 'selected' : '' }}>
                                    {{ $supplier->Supplier }}
                                </option>
                            @endforeach
                        @else
                            <option value="" disabled>No suppliers available</option>
                        @endif
                    </select>
                </div>

                @if($categories->count() > 0)
                    <div class="mt-4">
                        <label for="category_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Filter by Category</label>
                        <select name="category_id" id="category_id" class="rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600">
                            <option value="">All Categories</option>
                            @foreach($categories as $category)
                                <option value="{{ $category->ID }}" {{ $categoryId == $category->ID ? 'selected' : '' }}>
                                    {{ $category->NAME }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                @endif
                
                @if($search || $activeOnly || $stockedOnly || $inStockOnly || $supplierId || $categoryId)
                    <div class="flex justify-end mt-4">
                        <a href="{{ route('products.index') }}{{ $showSuppliers ? '?show_suppliers=1' : '' }}" class="inline-flex items-center px-4 py-2 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-500 rounded-md font-semibold text-xs text-gray-700 dark:text-gray-300 uppercase tracking-widest shadow-sm hover:bg-gray-50 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 disabled:opacity-25 transition ease-in-out duration-150">
                            Clear Filters
                        </a>
                    </div>
                @endif
                
            </x-filter-form>

            <!-- Products Table -->
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900 dark:text-gray-100">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead class="bg-gray-50 dark:bg-gray-700">
                                <tr>
                                    <th class="px-2 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider w-12">

                                    </th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                        Product
                                    </th>
                                    <th class="hidden lg:table-cell px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                        Category
                                    </th>
                                    @if($showSuppliers)
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                            Supplier
                                        </th>
                                    @endif
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                        Price (incl. VAT)
                                    </th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                        VAT Rate
                                    </th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                        Stock
                                    </th>
                                    <th class="sticky right-0 bg-gray-50 dark:bg-gray-700 shadow-[-4px_0_6px_-4px_rgba(0,0,0,0.1)] px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                        Actions
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                @forelse($products as $product)
                                    <tr>
                                        <td class="px-2 py-2 whitespace-nowrap">
                                            @php
                                                $imageUrl = null;
                                                // Priority 1: Database image
                                                if ($product->has_image) {
                                                    $imageUrl = route('products.image', $product->ID);
                                                }
                                                // Priority 2: Supplier image (if suppliers shown and has external integration)
                                                elseif ($showSuppliers && $product->supplier && $supplierService->hasExternalIntegration($product->supplier->SupplierID)) {
                                                    $imageUrl = $supplierService->getExternalImageUrl($product);
                                                }
                                            @endphp

                                            @if($imageUrl)
                                                <img src="{{ $imageUrl }}"
                                                     alt="{{ $product->NAME }}"
                                                     class="w-10 h-10 object-cover rounded border border-gray-200 dark:border-gray-700"
                                                     loading="lazy"
                                                     onerror="this.style.display='none'">
                                            @endif
                                        </td>
                                        <td class="px-4 py-4 text-sm text-gray-900 dark:text-gray-100">
                                            <div>{{ $product->NAME }}</div>
                                            <div class="text-xs text-gray-500 dark:text-gray-400">{{ $product->CODE }}</div>
                                        </td>
                                        <td class="hidden lg:table-cell px-4 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                            @if($product->category)
                                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200">
                                                    {{ $product->category->NAME }}
                                                </span>
                                            @else
                                                <span class="text-gray-400 dark:text-gray-500 text-xs">Uncategorized</span>
                                            @endif
                                        </td>
                                        @if($showSuppliers)
                                            <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">
                                                @if($product->supplier)
                                                    <div class="flex items-start space-x-3">
                                                        @if($supplierService->hasExternalIntegration($product->supplier->SupplierID))
                                                            <div class="relative w-10 h-10">
                                                                <img 
                                                                    src="{{ $supplierService->getExternalImageUrl($product) }}" 
                                                                    alt="{{ $product->NAME }}"
                                                                    class="w-10 h-10 object-cover rounded border border-gray-200 dark:border-gray-700 animate-pulse"
                                                                    loading="lazy"
                                                                    onload="this.classList.remove('animate-pulse')"
                                                                    onerror="this.style.display='none'; this.parentElement.style.display='none'"
                                                                >
                                                            </div>
                                                        @endif
                                                        <div class="flex flex-col">
                                                            <span class="font-medium">{{ $product->supplier->Supplier }}</span>
                                                            @if($product->supplierLink && $product->supplierLink->SupplierCode)
                                                                <span class="text-xs text-gray-500 dark:text-gray-400">{{ $product->supplierLink->SupplierCode }}</span>
                                                            @endif
                                                            @if($link = $supplierService->getSupplierWebsiteLink($product))
                                                                <a href="{{ $link }}" 
                                                                   target="_blank" 
                                                                   rel="noopener noreferrer" 
                                                                   class="text-xs text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300 mt-1">
                                                                    View on {{ $supplierService->getSupplierDisplayName($product->supplier->SupplierID) }} site →
                                                                </a>
                                                            @endif
                                                        </div>
                                                    </div>
                                                @else
                                                    <span class="text-gray-400 dark:text-gray-500 text-xs">No supplier</span>
                                                @endif
                                            </td>
                                        @endif
                                        <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">
                                            {{ $product->formatted_price_with_vat }}
                                        </td>
                                        <td class="px-4 py-4 whitespace-nowrap text-sm">
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full {{ $product->tax_category_badge_class }}">
                                                {{ $product->formatted_vat_rate }}
                                            </span>
                                        </td>
                                        <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">
                                            @if($product->isService())
                                                <span class="text-gray-500 dark:text-gray-400">N/A</span>
                                            @else
                                                <div x-data="{
                                                        editing: false,
                                                        stockUnits: {{ $product->stockCurrent ? $product->stockCurrent->UNITS : 0 }},
                                                        originalStock: {{ $product->stockCurrent ? $product->stockCurrent->UNITS : 0 }},
                                                        hasStockRecord: {{ $product->stockCurrent ? 'true' : 'false' }}
                                                     }">
                                                    <div x-show="!editing"
                                                         @@click="editing = true; $nextTick(() => $refs.stockInput.select())"
                                                         class="cursor-pointer hover:bg-blue-50 dark:hover:bg-gray-700 px-2 py-1 rounded transition-colors">
                                                        <span :class="stockUnits > 0 ? 'text-green-600 dark:text-green-400 font-semibold' : 'text-gray-400 dark:text-gray-500'"
                                                              x-text="parseFloat(stockUnits).toFixed(2)"></span>
                                                        
                                                        @if($product->stockCurrent && $product->stockCurrent->LOCATION && $product->stockCurrent->LOCATION !== '0')
                                                            <small class="text-gray-400 dark:text-gray-500 block">{{ $product->stockCurrent->LOCATION }}</small>
                                                        @endif
                                                        <span x-show="!hasStockRecord" class="text-xs text-red-400 dark:text-red-500 block">No stock record</span>
                                                    </div>
                                                    <div x-show="editing" x-cloak class="flex items-center gap-1">
                                                        <input type="number"
                                                               x-ref="stockInput"
                                                               x-model="stockUnits"
                                                               step="0.01"
                                                               min="0"
                                                               max="9999.99"
                                                               @@keydown.up.prevent="stockUnits = parseFloat((parseFloat(stockUnits) + 1).toFixed(2))"
                                                               @@keydown.down.prevent="stockUnits = Math.max(0, parseFloat((parseFloat(stockUnits) - 1).toFixed(2)))"
                                                               @@keyup.enter="updateStock('{{ $product->ID }}', stockUnits).then((success) => { if(success) { hasStockRecord = true; editing = false; originalStock = stockUnits; } })"
                                                               @@keyup.escape="editing = false; stockUnits = originalStock"
                                                               @@blur="updateStock('{{ $product->ID }}', stockUnits).then((success) => { if(success) { hasStockRecord = true; editing = false; originalStock = stockUnits; } })"
                                                               class="w-20 text-sm border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded focus:ring-indigo-500 focus:border-indigo-500">
                                                    </div>
                                                </div>
                                            @endif
                                        </td>
                                        <td class="sticky right-0 bg-white dark:bg-gray-800 shadow-[-4px_0_6px_-4px_rgba(0,0,0,0.1)] px-4 py-4 whitespace-nowrap text-sm font-medium">
                                            <div class="flex items-center gap-2">
                                                <button onclick="showSalesChartModal('{{ $product->ID }}', '{{ addslashes($product->NAME) }}')"
                                                        class="text-indigo-600 hover:text-indigo-900 dark:text-indigo-400 dark:hover:text-indigo-300"
                                                        title="View Sales History">
                                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                                                    </svg>
                                                </button>
                                                <a href="{{ route('products.edit', $product->ID) }}"
                                                   class="text-blue-600 hover:text-blue-900 dark:text-blue-400 dark:hover:text-blue-300"
                                                   title="View Product">
                                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                                    </svg>
                                                </a>
                                                <a href="{{ route('products.edit', $product->ID) }}"
                                                   class="text-amber-600 hover:text-amber-900 dark:text-amber-400 dark:hover:text-amber-300"
                                                   title="Edit Product">
                                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                                    </svg>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="{{ $showSuppliers ? '7' : '6' }}" class="px-6 py-4 text-center text-gray-500 dark:text-gray-400">
                                            No products found.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <div class="mt-4">
                        {{ $products->appends([
                            'search' => $search, 
                            'active_only' => $activeOnly,
                            'stocked_only' => $stockedOnly,
                            'in_stock_only' => $inStockOnly,
                            'show_stats' => $showStats,
                            'show_suppliers' => $showSuppliers,
                            'supplier_id' => $supplierId
                        ])->links() }}
                    </div>
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Get the show suppliers checkbox and supplier dropdown
            const showSuppliersCheckbox = document.querySelector('input[name="show_suppliers"]');
            const supplierDropdown = document.getElementById('supplier-dropdown');

            if (showSuppliersCheckbox && supplierDropdown) {
                // Add event listener to toggle supplier dropdown
                showSuppliersCheckbox.addEventListener('change', function() {
                    if (this.checked) {
                        supplierDropdown.classList.remove('hidden');
                    } else {
                        supplierDropdown.classList.add('hidden');
                        // Reset supplier selection when hiding
                        const supplierSelect = document.getElementById('supplier_id');
                        if (supplierSelect) {
                            supplierSelect.value = '';
                        }
                    }
                });
            }
        });

        // Function to update stock via AJAX
        async function updateStock(productId, stockUnits) {
            try {
                const response = await fetch('/products/' + productId + '/update-stock', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                    body: JSON.stringify({ stock_units: stockUnits })
                });

                const data = await response.json();

                if (response.ok && data.success) {
                    showToast('Stock updated successfully', 'success');
                    return true;
                } else {
                    showToast(data.error || data.message || 'Failed to update stock', 'error');
                    return false;
                }
            } catch (error) {
                console.error('Error updating stock:', error);
                showToast('Failed to update stock: ' + error.message, 'error');
                return false;
            }
        }

        // Function to show toast notifications
        function showToast(message, type = 'info') {
            const toast = document.createElement('div');
            toast.className = `fixed top-4 right-4 z-50 px-4 py-3 rounded-lg shadow-lg text-white transform transition-all duration-300 ${
                type === 'success' ? 'bg-green-500' :
                type === 'error' ? 'bg-red-500' :
                'bg-blue-500'
            }`;
            toast.textContent = message;
            document.body.appendChild(toast);

            // Animate in
            setTimeout(() => toast.classList.add('opacity-100'), 10);

            // Remove after 3 seconds
            setTimeout(() => {
                toast.classList.add('opacity-0');
                setTimeout(() => toast.remove(), 300);
            }, 3000);
        }
    </script>
    @endpush

    {{-- Sales Chart Modal --}}
    <x-sales-chart-modal />
</x-admin-layout>