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
                searchPlaceholder="Search by name, code, or reference..."
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
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                        Code
                                    </th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                        Name
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
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                        Actions
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                @forelse($products as $product)
                                    <tr>
                                        <td class="px-4 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-gray-100">
                                            {{ $product->CODE }}
                                        </td>
                                        <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">
                                            {{ $product->NAME }}
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
                                            @elseif($product->stockCurrent)
                                                <span class="text-green-600 dark:text-green-400 font-semibold">{{ number_format($product->stockCurrent->UNITS, 1) }}</span>
                                                @if($product->stockCurrent->LOCATION && $product->stockCurrent->LOCATION !== '0')
                                                    <br><small class="text-gray-400 dark:text-gray-500">{{ $product->stockCurrent->LOCATION }}</small>
                                                @endif
                                            @else
                                                <span class="text-gray-400 dark:text-gray-500">0.0</span>
                                                <br><small class="text-red-400 dark:text-red-500">No stock record</small>
                                            @endif
                                        </td>
                                        <td class="px-4 py-4 whitespace-nowrap text-sm font-medium">
                                            <x-action-buttons :actions="[
                                                ['type' => 'link', 'route' => 'products.show', 'params' => $product->ID, 'label' => 'View', 'color' => 'primary']
                                            ]" />
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
    </script>
    @endpush
</x-admin-layout>