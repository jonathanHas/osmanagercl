<x-admin-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-lg text-gray-800 leading-tight py-1">
            Destock Review - Restock Suggestions
        </h2>
    </x-slot>

    <div class="py-4">
        <div class="max-w-7xl mx-auto px-2 sm:px-4 lg:px-6">
            <!-- Tab Navigation -->
            <div class="border-b border-gray-200 mb-4">
                <nav class="-mb-px flex space-x-8">
                    <a href="{{ route('destock-review.index') }}"
                       class="border-b-2 border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 py-3 px-1 text-sm font-medium">
                        Audit Log
                    </a>
                    <a href="{{ route('destock-review.suggestions') }}"
                       class="border-b-2 border-indigo-500 text-indigo-600 py-3 px-1 text-sm font-medium">
                        Restock Suggestions
                    </a>
                </nav>
            </div>

            <!-- Filters -->
            <div class="bg-white shadow-sm sm:rounded-lg p-4 mb-4">
                <form method="GET" action="{{ route('destock-review.suggestions') }}">
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-7 gap-4 items-end">
                        <div class="lg:col-span-2">
                            <label for="search" class="block text-xs font-medium text-gray-500 uppercase">Search</label>
                            <input type="text" name="search" id="search" value="{{ request('search') }}" placeholder="Product name or barcode..."
                                   class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                        </div>
                        <div>
                            <label for="days" class="block text-xs font-medium text-gray-500 uppercase">Sales Period</label>
                            <select name="days" id="days"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                                @foreach([7 => 'Last 7 days', 14 => 'Last 14 days', 30 => 'Last 30 days', 60 => 'Last 60 days', 90 => 'Last 90 days', 180 => 'Last 6 months', 365 => 'Last year'] as $value => $label)
                                    <option value="{{ $value }}" {{ $days == $value ? 'selected' : '' }}>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label for="min_units" class="block text-xs font-medium text-gray-500 uppercase">Min Units Sold</label>
                            <input type="number" name="min_units" id="min_units" value="{{ $minUnits }}" min="1" step="1"
                                   class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                        </div>
                        <div>
                            <label for="supplier_id" class="block text-xs font-medium text-gray-500 uppercase">Supplier</label>
                            <select name="supplier_id" id="supplier_id"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <option value="">All Suppliers</option>
                                @foreach($suppliers as $id => $name)
                                    <option value="{{ $id }}" {{ $supplierId == $id ? 'selected' : '' }}>{{ $name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label for="sort" class="block text-xs font-medium text-gray-500 uppercase">Sort By</label>
                            <select name="sort" id="sort"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <option value="units" {{ $sortBy === 'units' ? 'selected' : '' }}>Units Sold</option>
                                <option value="revenue" {{ $sortBy === 'revenue' ? 'selected' : '' }}>Revenue</option>
                                <option value="days" {{ $sortBy === 'days' ? 'selected' : '' }}>Days with Sales</option>
                                <option value="last_sale" {{ $sortBy === 'last_sale' ? 'selected' : '' }}>Last Sale Date</option>
                            </select>
                        </div>
                        <div>
                            <label class="flex items-center gap-2 mt-6">
                                <input type="hidden" name="exclude_fv" value="0">
                                <input type="checkbox" name="exclude_fv" value="1" {{ $excludeFv ? 'checked' : '' }}
                                       class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                                <span class="text-sm text-gray-700">Exclude F&V</span>
                            </label>
                        </div>
                        <div class="flex gap-2">
                            <button type="submit"
                                    class="inline-flex items-center px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-md hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                                Filter
                            </button>
                            <a href="{{ route('destock-review.suggestions') }}"
                               class="inline-flex items-center px-4 py-2 bg-gray-200 text-gray-700 text-sm font-medium rounded-md hover:bg-gray-300">
                                Reset
                            </a>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Info banner -->
            <div class="bg-amber-50 border border-amber-200 rounded-lg p-3 mb-4">
                <p class="text-sm text-amber-800">
                    <span class="font-medium">These products are not currently stocked but have recorded sales in the last {{ $days }} days.</span>
                    Consider restocking products with significant sales activity.
                </p>
            </div>

            <!-- Results -->
            @if($suggestions->isEmpty())
                <div class="bg-white shadow-sm sm:rounded-lg p-8 text-center text-gray-500">
                    <p class="text-lg font-medium">No suggestions found</p>
                    <p class="text-sm mt-1">No destocked products with sales activity matching the selected filters.</p>
                </div>
            @else
                <div class="bg-white shadow-sm sm:rounded-lg overflow-hidden">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase w-12"></th>
                                <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase">Product</th>
                                <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase">Supplier</th>
                                <th class="px-3 py-3 text-right text-xs font-medium text-gray-500 uppercase">Units Sold</th>
                                <th class="px-3 py-3 text-right text-xs font-medium text-gray-500 uppercase">Revenue</th>
                                <th class="px-3 py-3 text-right text-xs font-medium text-gray-500 uppercase">Days</th>
                                <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase">Last Sale</th>
                                <th class="px-3 py-3 text-center text-xs font-medium text-gray-500 uppercase">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            @foreach($suggestions as $suggestion)
                                @php
                                    $productModel = $products[$suggestion->product_code] ?? null;
                                    $supplierName = $productModel?->supplier?->Supplier ?? null;
                                    $supplierWebLink = null;
                                    if ($productModel && $supplierService) {
                                        $supplierWebLink = $supplierService->getSupplierWebsiteLink($productModel);
                                    }
                                @endphp
                                <tr class="hover:bg-gray-50" id="row-{{ $suggestion->product_code }}">
                                    {{-- Image --}}
                                    <td class="px-3 py-2">
                                        @if($productModel)
                                            <x-product-image :product="$productModel" :supplier-service="$supplierService" size="sm" :hover="true" />
                                        @else
                                            <div class="w-8 h-8 bg-gray-100 rounded border border-gray-200 flex items-center justify-center">
                                                <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                                </svg>
                                            </div>
                                        @endif
                                    </td>
                                    {{-- Product name + barcode --}}
                                    <td class="px-3 py-3">
                                        <div class="text-sm font-medium text-gray-900">{{ $suggestion->product_name }}</div>
                                        <div class="text-xs text-gray-500 font-mono">{{ $suggestion->product_code }}</div>
                                    </td>
                                    {{-- Supplier --}}
                                    <td class="px-3 py-3 text-sm text-gray-700">
                                        @if($supplierWebLink)
                                            <a href="{{ $supplierWebLink }}" target="_blank" rel="noopener"
                                               class="text-blue-600 hover:text-blue-800 hover:underline" title="View on supplier website">
                                                {{ $supplierName }}
                                                <svg class="inline w-3 h-3 ml-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                                            </a>
                                        @else
                                            {{ $supplierName ?? '—' }}
                                        @endif
                                    </td>
                                    {{-- Sales data --}}
                                    <td class="px-3 py-3 text-sm text-right font-medium text-gray-900">{{ number_format($suggestion->total_units_sold, 1) }}</td>
                                    <td class="px-3 py-3 text-sm text-right text-gray-700">&euro;{{ number_format($suggestion->total_revenue, 2) }}</td>
                                    <td class="px-3 py-3 text-sm text-right text-gray-500">{{ $suggestion->days_with_sales }}</td>
                                    <td class="px-3 py-3 text-sm text-gray-500">{{ \Carbon\Carbon::parse($suggestion->last_sale)->format('d M Y') }}</td>
                                    {{-- Actions --}}
                                    <td class="px-3 py-3 text-center">
                                        @if($productModel)
                                            <div class="flex items-center justify-center gap-1.5">
                                                <button onclick="showSalesChartModal('{{ $productModel->ID }}', '{{ addslashes($suggestion->product_name) }}')"
                                                        class="inline-flex items-center p-1.5 text-indigo-600 hover:text-indigo-900 hover:bg-indigo-50 rounded transition-colors"
                                                        title="View Sales History">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                                                    </svg>
                                                </button>
                                                <a href="{{ route('products.show', $productModel->ID) }}"
                                                   class="inline-flex items-center p-1.5 text-blue-600 hover:text-blue-900 hover:bg-blue-50 rounded transition-colors"
                                                   title="View Product">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                                    </svg>
                                                </a>
                                                <button
                                                    type="button"
                                                    class="restock-btn inline-flex items-center px-2.5 py-1 bg-green-600 text-white text-xs font-medium rounded-md hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500 transition-colors"
                                                    data-product-id="{{ $productModel->ID }}"
                                                    data-product-name="{{ $suggestion->product_name }}"
                                                    data-barcode="{{ $suggestion->product_code }}"
                                                    onclick="restockProduct(this)"
                                                >
                                                    <svg class="w-3.5 h-3.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                                                    </svg>
                                                    Restock
                                                </button>
                                            </div>
                                        @else
                                            <span class="text-xs text-gray-400">N/A</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="mt-4">
                    {{ $suggestions->withQueryString()->links() }}
                </div>
            @endif
        </div>
    </div>

    {{-- Sales Chart Modal --}}
    <x-sales-chart-modal />

    @push('scripts')
    <script>
        async function restockProduct(button) {
            const productId = button.dataset.productId;
            const productName = button.dataset.productName;
            const barcode = button.dataset.barcode;

            if (!confirm(`Restock "${productName}"?\n\nThis will add it back to stock management.`)) {
                return;
            }

            // Disable button while processing
            button.disabled = true;
            const originalHtml = button.innerHTML;
            button.innerHTML = '<svg class="w-3.5 h-3.5 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>';

            try {
                const response = await fetch(`/products/${productId}/toggle-stocking`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({ include_in_stocking: true, source: 'destock_review' })
                });

                const data = await response.json();

                if (data.success) {
                    // Show restocked state
                    button.classList.remove('bg-green-600', 'hover:bg-green-700', 'focus:ring-green-500');
                    button.classList.add('bg-gray-400', 'cursor-not-allowed');
                    button.innerHTML = '<svg class="w-3.5 h-3.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg> Restocked';
                    button.disabled = true;

                    // Fade the row
                    const row = document.getElementById('row-' + barcode);
                    if (row) {
                        row.classList.add('opacity-50');
                    }
                } else {
                    alert('Failed to restock: ' + (data.error || 'Unknown error'));
                    button.disabled = false;
                    button.innerHTML = originalHtml;
                }
            } catch (error) {
                alert('Error restocking product: ' + error.message);
                button.disabled = false;
                button.innerHTML = originalHtml;
            }
        }
    </script>
    @endpush
</x-admin-layout>
