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

            {{-- Optional supplier / category filters (rarely used; a change reloads with the new query string) --}}
            <details class="mb-4 text-sm" {{ ($supplierId || $categoryId) ? 'open' : '' }}>
                <summary class="cursor-pointer select-none text-gray-600 dark:text-gray-300 hover:text-gray-900 dark:hover:text-gray-100">
                    More filters
                    @if($supplierId || $categoryId)
                        <span class="ml-1 inline-flex items-center px-2 py-0.5 rounded-full text-xs bg-indigo-100 text-indigo-800 dark:bg-indigo-900 dark:text-indigo-200">active</span>
                    @endif
                </summary>
                <div class="mt-3 flex flex-wrap items-end gap-4 bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg p-4">
                    <div>
                        <label for="supplier_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Supplier</label>
                        <select name="supplier_id" id="supplier_id"
                                onchange="productsFilterChanged('supplier_id', this.value)"
                                class="rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600">
                            <option value="">All Suppliers</option>
                            @foreach($suppliers as $supplier)
                                <option value="{{ $supplier->SupplierID }}" {{ $supplierId == $supplier->SupplierID ? 'selected' : '' }}>
                                    {{ $supplier->Supplier }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="category_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Category</label>
                        <select name="category_id" id="category_id"
                                onchange="productsFilterChanged('category_id', this.value)"
                                class="rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600">
                            <option value="">All Categories</option>
                            @foreach($categories as $category)
                                <option value="{{ $category->ID }}" {{ $categoryId == $category->ID ? 'selected' : '' }}>
                                    {{ $category->NAME }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    @if($supplierId || $categoryId)
                        <a href="{{ route('products.index', array_filter(['q' => $q, 'stocked' => $stocked ? null : 0, 'show_stats' => $showStats ? 1 : null])) }}"
                           class="inline-flex items-center px-4 py-2 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-500 rounded-md font-semibold text-xs text-gray-700 dark:text-gray-300 uppercase tracking-widest shadow-sm hover:bg-gray-50 dark:hover:bg-gray-700">
                            Clear Filters
                        </a>
                    @endif
                </div>
            </details>

            {{-- Search bar + results (server-rendered first page via :initial) --}}
            <x-product-search mode="list" :initial="$initial" :camera="true" :supplier-id="$supplierId" :category-id="$categoryId">
                <x-slot:row-actions>
                    <div class="flex items-center gap-3">
                        {{-- Inline stock editor (same /products/{id}/update-stock endpoint as before) --}}
                        <div x-show="!product.is_service"
                             x-data="{
                                editing: false,
                                stockUnits: product.stock_units,
                                originalStock: product.stock_units,
                                save() {
                                    return updateStock(product.id, this.stockUnits).then((success) => {
                                        if (success) {
                                            product.stock_units = parseFloat(this.stockUnits);
                                            product.has_stock_record = true;
                                            this.originalStock = this.stockUnits;
                                            this.editing = false;
                                        }
                                    });
                                }
                             }"
                             class="min-w-[5rem]">
                            <button type="button" x-show="!editing"
                                    x-on:click="editing = true; $nextTick(() => $refs.stockInput.select())"
                                    class="inline-flex items-center gap-1 px-2 py-1 rounded text-xs text-gray-600 dark:text-gray-300 hover:bg-blue-50 dark:hover:bg-gray-700 transition-colors"
                                    title="Edit stock">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                                <span x-text="Number(product.stock_units).toFixed(2)"></span>
                            </button>
                            <div x-show="editing" x-cloak class="flex items-center gap-1">
                                <input type="number"
                                       x-ref="stockInput"
                                       x-model="stockUnits"
                                       step="0.01" min="0" max="9999.99"
                                       x-on:keydown.up.prevent="stockUnits = parseFloat((parseFloat(stockUnits) + 1).toFixed(2))"
                                       x-on:keydown.down.prevent="stockUnits = Math.max(0, parseFloat((parseFloat(stockUnits) - 1).toFixed(2)))"
                                       x-on:keyup.enter="save()"
                                       x-on:keyup.escape="editing = false; stockUnits = originalStock"
                                       x-on:blur="save()"
                                       class="w-20 text-sm border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded focus:ring-indigo-500 focus:border-indigo-500">
                            </div>
                        </div>

                        <button type="button" x-on:click="showSalesChartModal(product.id, product.name)"
                                class="text-indigo-600 hover:text-indigo-900 dark:text-indigo-400 dark:hover:text-indigo-300"
                                title="View Sales History">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                            </svg>
                        </button>
                        <a :href="product.edit_url"
                           class="text-amber-600 hover:text-amber-900 dark:text-amber-400 dark:hover:text-amber-300"
                           title="Edit Product">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                            </svg>
                        </a>
                    </div>
                </x-slot:row-actions>
            </x-product-search>
        </div>
    </div>

    @push('scripts')
    <script>
        // Supplier / category selects reload the page, keeping whatever q / stocked the search bar has synced into the URL.
        function productsFilterChanged(param, value) {
            const url = new URL(window.location.href);
            value ? url.searchParams.set(param, value) : url.searchParams.delete(param);
            url.searchParams.delete('page');
            window.location.href = url.toString();
        }

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
