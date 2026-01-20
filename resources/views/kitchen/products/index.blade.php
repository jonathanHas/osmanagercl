<x-admin-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('Kitchen Products') }}
            </h2>
            <div class="flex items-center space-x-4">
                <a href="{{ route('kitchen.profiles.index') }}" class="text-indigo-600 hover:text-indigo-900">
                    View Ingredient Profiles
                </a>
                <a href="{{ route('kitchen.index') }}" class="text-gray-600 hover:text-gray-900">
                    &larr; Back to Recipes
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            @if (session('success'))
                <div class="mb-4 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded">
                    {{ session('success') }}
                </div>
            @endif

            <!-- Statistics Cards -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <div class="flex items-center">
                            <div class="p-3 rounded-full bg-orange-100 text-orange-800">
                                <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>
                                </svg>
                            </div>
                            <div class="ml-4">
                                <p class="text-gray-500 text-sm">Kitchen Products</p>
                                <p class="text-2xl font-semibold text-gray-900">{{ $totalKitchenProducts }}</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <div class="flex items-center">
                            <div class="p-3 rounded-full bg-green-100 text-green-800">
                                <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                            </div>
                            <div class="ml-4">
                                <p class="text-gray-500 text-sm">With Profiles</p>
                                <p class="text-2xl font-semibold text-green-600">{{ $totalWithProfiles }}</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <div class="flex items-center">
                            <div class="p-3 rounded-full bg-amber-100 text-amber-800">
                                <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                                </svg>
                            </div>
                            <div class="ml-4">
                                <p class="text-gray-500 text-sm">Need Profiles</p>
                                <p class="text-2xl font-semibold text-amber-600">{{ $totalKitchenProducts - $totalWithProfiles }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Info Box -->
            <div class="mb-6 bg-orange-50 border border-orange-200 rounded-lg p-4">
                <h3 class="font-medium text-orange-800 mb-2">What are Kitchen Products?</h3>
                <p class="text-sm text-orange-700">
                    Products flagged here are ones that regularly go to the kitchen. This helps with ordering ingredients
                    and speeds up creating ingredient profiles for accurate recipe costing. Flag products from the
                    <a href="{{ route('orders.index') }}" class="underline font-medium">Orders</a> page using the "Kitchen" button.
                </p>
            </div>

            <!-- Add Product Search -->
            <div class="mb-6 bg-white overflow-visible shadow-sm sm:rounded-lg" x-data="productSearch()">
                <div class="p-6">
                    <h3 class="text-sm font-medium text-gray-700 mb-3">Add Product to Kitchen List</h3>
                    <div class="relative">
                        <div class="flex gap-2">
                            <div class="flex-1 relative">
                                <input type="text"
                                       x-model="query"
                                       @input.debounce.300ms="search()"
                                       @focus="showResults = results.length > 0"
                                       @keydown.escape="showResults = false"
                                       placeholder="Search by product name, barcode, or supplier code..."
                                       class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <div x-show="loading" class="absolute right-3 top-2.5">
                                    <svg class="animate-spin h-5 w-5 text-gray-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                </div>
                            </div>
                        </div>

                        <!-- Search Results Dropdown -->
                        <div x-show="showResults && results.length > 0"
                             x-cloak
                             @click.outside="showResults = false"
                             class="absolute z-20 w-full mt-1 bg-white border border-gray-300 rounded-md shadow-lg max-h-80 overflow-y-auto">
                            <template x-for="product in results" :key="product.id">
                                <div class="px-4 py-3 hover:bg-gray-50 border-b border-gray-100 last:border-0 flex items-center justify-between">
                                    <div class="flex-1 min-w-0">
                                        <div class="font-medium text-gray-900 truncate" x-text="product.name"></div>
                                        <div class="text-xs text-gray-500 flex flex-wrap gap-x-3">
                                            <span x-show="product.code">Barcode: <span x-text="product.code" class="font-mono"></span></span>
                                            <span x-show="product.supplier_code">Supplier Code: <span x-text="product.supplier_code" class="font-mono"></span></span>
                                            <span x-show="product.supplier" class="text-indigo-600" x-text="product.supplier"></span>
                                        </div>
                                    </div>
                                    <button type="button"
                                            @click="addProduct(product)"
                                            :disabled="adding === product.id"
                                            class="ml-3 px-3 py-1.5 text-sm font-medium text-white bg-orange-600 hover:bg-orange-700 rounded-md disabled:opacity-50 disabled:cursor-wait">
                                        <span x-show="adding !== product.id">Add</span>
                                        <span x-show="adding === product.id">Adding...</span>
                                    </button>
                                </div>
                            </template>
                        </div>

                        <!-- No Results Message -->
                        <div x-show="showResults && results.length === 0 && query.length >= 2 && !loading"
                             x-cloak
                             class="absolute z-20 w-full mt-1 bg-white border border-gray-300 rounded-md shadow-lg p-4 text-center text-gray-500">
                            No products found matching "<span x-text="query"></span>"
                        </div>

                        <!-- Success Message -->
                        <div x-show="successMessage"
                             x-transition:enter="transition ease-out duration-200"
                             x-transition:leave="transition ease-in duration-150"
                             class="mt-2 p-2 bg-green-100 border border-green-300 text-green-700 rounded-md text-sm">
                            <span x-text="successMessage"></span>
                        </div>
                    </div>
                </div>
            </div>

            <script>
                function productSearch() {
                    return {
                        query: '',
                        results: [],
                        showResults: false,
                        loading: false,
                        adding: null,
                        successMessage: '',

                        async search() {
                            if (this.query.length < 2) {
                                this.results = [];
                                this.showResults = false;
                                return;
                            }

                            this.loading = true;
                            try {
                                const response = await fetch(`{{ route('kitchen.products.search') }}?q=${encodeURIComponent(this.query)}`);
                                this.results = await response.json();
                                this.showResults = true;
                            } catch (error) {
                                console.error('Search failed:', error);
                            } finally {
                                this.loading = false;
                            }
                        },

                        async addProduct(product) {
                            this.adding = product.id;
                            try {
                                const response = await fetch('{{ route('kitchen.products.toggle') }}', {
                                    method: 'POST',
                                    headers: {
                                        'Content-Type': 'application/json',
                                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                                        'Accept': 'application/json'
                                    },
                                    body: JSON.stringify({ product_id: product.id })
                                });

                                const data = await response.json();

                                if (data.success && data.is_kitchen) {
                                    // Remove from results
                                    this.results = this.results.filter(p => p.id !== product.id);
                                    this.successMessage = `"${product.name}" added to kitchen products`;

                                    // Clear success message after 3 seconds
                                    setTimeout(() => {
                                        this.successMessage = '';
                                    }, 3000);

                                    // Reload page to show the new product
                                    setTimeout(() => {
                                        window.location.reload();
                                    }, 1000);
                                }
                            } catch (error) {
                                console.error('Add failed:', error);
                                alert('Failed to add product. Please try again.');
                            } finally {
                                this.adding = null;
                            }
                        }
                    };
                }
            </script>

            <!-- Filters -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-6">
                <div class="p-6">
                    <form method="GET" action="{{ route('kitchen.products.index') }}" class="flex flex-wrap items-center gap-4">
                        <div class="flex-1 min-w-[200px]">
                            <input type="text" name="search" value="{{ $search }}"
                                   placeholder="Search by product name or code..."
                                   class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        </div>

                        <!-- Supplier dropdown -->
                        <div class="w-48">
                            <select name="supplier" class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <option value="">All Suppliers</option>
                                @foreach($availableSuppliers as $supplier)
                                    <option value="{{ $supplier }}" {{ $selectedSupplier === $supplier ? 'selected' : '' }}>
                                        {{ $supplier }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <!-- Group by Category checkbox -->
                        <label class="inline-flex items-center cursor-pointer">
                            <input type="checkbox" name="group_by_category" value="1"
                                   {{ $groupByCategory ? 'checked' : '' }}
                                   class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                            <span class="ml-2 text-sm text-gray-700">Group by Category</span>
                        </label>

                        <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700">
                            Filter
                        </button>
                        @if($search || $selectedSupplier || $groupByCategory)
                            <a href="{{ route('kitchen.products.index') }}" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-md hover:bg-gray-300">
                                Clear
                            </a>
                        @endif
                    </form>
                </div>
            </div>

            <!-- Products Table -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    @if($kitchenProducts->isEmpty())
                        <div class="text-center py-12">
                            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>
                            </svg>
                            <h3 class="mt-2 text-sm font-medium text-gray-900">No kitchen products</h3>
                            <p class="mt-1 text-sm text-gray-500">
                                Products can be flagged as kitchen products from the Orders page using the "Kitchen" button.
                            </p>
                            <div class="mt-6">
                                <a href="{{ route('orders.index') }}" class="inline-flex items-center px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700">
                                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                                    </svg>
                                    Go to Orders
                                </a>
                            </div>
                        </div>
                    @elseif($groupByCategory)
                        {{-- Grouped by Category View --}}
                        @php
                            $groupedProducts = $kitchenProducts->groupBy(function($kp) use ($categoryInfo) {
                                return $categoryInfo[$kp->product_id]['name'] ?? 'Uncategorized';
                            })->sortKeys();
                        @endphp

                        @foreach($groupedProducts as $categoryName => $products)
                            <div class="mb-8 last:mb-0">
                                <h3 class="text-lg font-semibold text-gray-800 mb-3 pb-2 border-b border-gray-200">
                                    {{ $categoryName }}
                                    <span class="text-sm font-normal text-gray-500">({{ $products->count() }} products)</span>
                                </h3>
                                <table class="min-w-full divide-y divide-gray-200">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Product</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Supplier</th>
                                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Kitchen Sales (avg/wk)</th>
                                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Total (6mo)</th>
                                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Profile</th>
                                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-200">
                                        @foreach($products as $kitchenProduct)
                                            @include('kitchen.products.partials.product-row', [
                                                'kitchenProduct' => $kitchenProduct,
                                                'kitchenSales' => $kitchenSales,
                                                'supplierInfo' => $supplierInfo,
                                                'profiledProductIds' => $profiledProductIds,
                                            ])
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endforeach
                    @else
                        {{-- Standard Table View --}}
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Product</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Supplier</th>
                                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Kitchen Sales (avg/wk)</th>
                                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Total (6mo)</th>
                                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Profile</th>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                @foreach($kitchenProducts as $kitchenProduct)
                                    @include('kitchen.products.partials.product-row', [
                                        'kitchenProduct' => $kitchenProduct,
                                        'kitchenSales' => $kitchenSales,
                                        'supplierInfo' => $supplierInfo,
                                        'profiledProductIds' => $profiledProductIds,
                                    ])
                                @endforeach
                            </tbody>
                        </table>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-admin-layout>
