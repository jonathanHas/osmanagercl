<x-admin-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('Fruit & Vegetable Labels') }}
            </h2>
            <a href="{{ route('fruit-veg.index') }}" class="text-blue-600 hover:text-blue-800">
                ← Back to Dashboard
            </a>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <!-- Action Buttons -->
            @if($productsNeedingLabels->count() > 0)
            <div class="bg-white rounded-lg shadow mb-6 p-6">
                <div class="flex justify-between items-center">
                    <div>
                        <h3 class="text-lg font-medium text-gray-900">Products Needing Labels</h3>
                        <p class="mt-1 text-sm text-gray-500">
                            {{ $productsNeedingLabels->count() }} products need labels printed
                        </p>
                    </div>
                    <div class="flex gap-3">
                        <a href="{{ route('fruit-veg.labels.preview') }}" 
                           target="_blank"
                           class="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition">
                            <svg class="h-5 w-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                            </svg>
                            Preview All Labels
                        </a>
                        <form action="{{ route('fruit-veg.labels.preview') }}" method="GET" target="_blank" class="inline">
                            <button type="submit" 
                                    class="inline-flex items-center px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition">
                                <svg class="h-5 w-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                                </svg>
                                Print All Labels
                            </button>
                        </form>
                        <form action="{{ route('fruit-veg.labels.clear-all') }}" method="POST" class="inline" onsubmit="return confirm('Are you sure you want to clear all labels from the print queue? This cannot be undone.')">
                            @csrf
                            <button type="submit" 
                                    class="inline-flex items-center px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition">
                                <svg class="h-5 w-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                </svg>
                                Clear All Labels
                            </button>
                        </form>
                    </div>
                </div>
            </div>
            @else
            <div class="bg-green-50 border-l-4 border-green-400 p-4 mb-6">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <svg class="h-5 w-5 text-green-400" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                        </svg>
                    </div>
                    <div class="ml-3">
                        <p class="text-sm text-green-700">
                            All labels are up to date! No products currently need labels.
                        </p>
                    </div>
                </div>
            </div>
            @endif

            <!-- Products List -->
            @if($productsNeedingLabels->count() > 0)
            <div class="bg-white rounded-lg shadow overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Product
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Category
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Price
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Origin
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Class
                                </th>
                                <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Actions
                                </th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @foreach($productsNeedingLabels as $product)
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-gray-900">{{ $product->NAME }}</div>
                                    <div class="text-xs text-gray-500">{{ $product->CODE }}</div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    {{ $product->category->NAME ?? 'N/A' }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-gray-900">
                                        €{{ number_format($product->current_price, 2) }}
                                        @if($product->vegDetails && $product->vegDetails->unit_name)
                                            <span class="text-gray-500">/{{ $product->vegDetails->unit_name }}</span>
                                        @endif
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    @if($product->vegDetails && $product->vegDetails->country)
                                        {{ $product->vegDetails->country->name ?? 'N/A' }}
                                    @else
                                        N/A
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    {{ $product->vegDetails->class_name ?? '-' }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-center">
                                    <div class="flex justify-center space-x-2">
                                        <a href="{{ route('fruit-veg.labels.preview', ['products' => [$product->CODE]]) }}" 
                                           target="_blank"
                                           class="text-indigo-600 hover:text-indigo-900 text-sm font-medium">
                                            Preview
                                        </a>
                                        <button onclick="removeFromQueue('{{ $product->CODE }}')"
                                                class="text-red-600 hover:text-red-900 text-sm font-medium">
                                            Remove
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
            @endif

            <!-- Info Section -->
            <div class="mt-6 bg-gray-50 rounded-lg p-6">
                <h4 class="text-sm font-medium text-gray-900 mb-2">Label Information</h4>
                <ul class="text-sm text-gray-600 space-y-1">
                    <li>• Labels are automatically generated when products are marked as available</li>
                    <li>• Labels are added to the queue when prices are updated</li>
                    <li>• Each label includes: Product name, price per unit, country of origin, and organic certification</li>
                    <li>• Labels are formatted for A4 printing with multiple labels per page</li>
                </ul>
            </div>
        </div>
    </div>

    <script>
        async function removeFromQueue(productCode) {
            if (!confirm('Remove this product from the print queue?')) {
                return;
            }

            try {
                const response = await fetch('{{ route("fruit-veg.labels.remove") }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                    body: JSON.stringify({
                        product_code: productCode
                    })
                });

                const result = await response.json();
                
                if (result.success) {
                    // Reload the page to update the list
                    window.location.reload();
                } else {
                    alert(result.message || 'Failed to remove product from queue');
                }
            } catch (error) {
                console.error('Error:', error);
                alert('An error occurred while removing the product');
            }
        }
    </script>
</x-admin-layout>