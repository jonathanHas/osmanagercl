<div id="kitchen-table-content" class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
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
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Supplier Code</th>
                                <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Shop Stock</th>
                                <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Kitchen Stock</th>
                                <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Profile</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @foreach($products as $kitchenProduct)
                                @include('kitchen.products.partials.product-row', [
                                    'kitchenProduct' => $kitchenProduct,
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
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Supplier Code</th>
                        <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Shop Stock</th>
                        <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Kitchen Stock</th>
                        <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Profile</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @foreach($kitchenProducts as $kitchenProduct)
                        @include('kitchen.products.partials.product-row', [
                            'kitchenProduct' => $kitchenProduct,
                            'supplierInfo' => $supplierInfo,
                            'profiledProductIds' => $profiledProductIds,
                        ])
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
</div>
