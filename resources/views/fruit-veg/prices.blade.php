<x-admin-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('Update Fruit & Vegetable Prices') }}
            </h2>
            <a href="{{ route('fruit-veg.index') }}" class="text-blue-600 hover:text-blue-800">
                ← Back to Dashboard
            </a>
        </div>
    </x-slot>

    <div class="py-6" x-data="priceManager()">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <!-- Info Banner -->
            <div class="bg-blue-50 border-l-4 border-blue-400 p-4 mb-6">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <svg class="h-5 w-5 text-blue-400" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
                        </svg>
                    </div>
                    <div class="ml-3">
                        <p class="text-sm text-blue-700">
                            Products with price changes will be automatically added to the label printing queue.
                            Only available products are shown here.
                        </p>
                    </div>
                </div>
            </div>

            <!-- Products Table -->
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
                                    Origin
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Unit
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Current Price
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    New Price
                                </th>
                                <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Action
                                </th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @foreach($products as $product)
                            <tr x-data="{ 
                                editing: false, 
                                originalPrice: {{ $product->current_price }},
                                newPrice: {{ $product->current_price }},
                                productCode: '{{ $product->CODE }}'
                            }">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-gray-900">{{ $product->NAME }}</div>
                                    <div class="text-xs text-gray-500">{{ $product->CODE }}</div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    {{ $product->category->NAME ?? 'N/A' }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    @if($product->vegDetails && $product->vegDetails->country)
                                        {{ $product->vegDetails->country->country ?? 'N/A' }}
                                    @else
                                        N/A
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    {{ $product->vegDetails->unit_name ?? 'kg' }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="text-sm font-medium text-gray-900">
                                        €<span x-text="originalPrice.toFixed(2)"></span>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div x-show="!editing" class="text-sm font-medium text-gray-900">
                                        €<span x-text="newPrice.toFixed(2)"></span>
                                    </div>
                                    <div x-show="editing" x-cloak class="flex items-center">
                                        <span class="text-sm font-medium text-gray-900 mr-1">€</span>
                                        <input type="number" 
                                               x-model="newPrice" 
                                               step="0.01" 
                                               min="0"
                                               @keyup.enter="$parent.updatePrice(productCode, newPrice, originalPrice); editing = false"
                                               @keyup.escape="newPrice = originalPrice; editing = false"
                                               class="w-24 px-2 py-1 text-sm border-gray-300 rounded-md focus:ring-indigo-500 focus:border-indigo-500">
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-center">
                                    <div x-show="!editing" class="flex justify-center gap-2">
                                        <button @click="editing = true; $nextTick(() => $el.parentElement.parentElement.querySelector('input').focus())"
                                                class="text-indigo-600 hover:text-indigo-900 text-sm font-medium">
                                            Edit
                                        </button>
                                    </div>
                                    <div x-show="editing" x-cloak class="flex justify-center gap-2">
                                        <button @click="$parent.updatePrice(productCode, newPrice, originalPrice); editing = false"
                                                :disabled="newPrice == originalPrice"
                                                :class="newPrice == originalPrice ? 'opacity-50 cursor-not-allowed' : ''"
                                                class="text-green-600 hover:text-green-900 text-sm font-medium disabled:opacity-50">
                                            Save
                                        </button>
                                        <button @click="newPrice = originalPrice; editing = false"
                                                class="text-gray-600 hover:text-gray-900 text-sm font-medium">
                                            Cancel
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
    <script>
        function priceManager() {
            return {
                async updatePrice(productCode, newPrice, oldPrice) {
                    if (newPrice == oldPrice) return;
                    
                    try {
                        const response = await fetch('{{ route('fruit-veg.prices.update') }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': '{{ csrf_token() }}'
                            },
                            body: JSON.stringify({
                                product_code: productCode,
                                new_price: parseFloat(newPrice)
                            })
                        });
                        
                        if (response.ok) {
                            // Show success message
                            this.showNotification('Price updated successfully! Product added to label queue.', 'success');
                            
                            // Update the original price in the UI
                            setTimeout(() => {
                                window.location.reload();
                            }, 1500);
                        } else {
                            const data = await response.json();
                            this.showNotification(data.error || 'Failed to update price', 'error');
                        }
                    } catch (error) {
                        console.error('Error:', error);
                        this.showNotification('An error occurred', 'error');
                    }
                },
                
                showNotification(message, type) {
                    // Create notification element
                    const notification = document.createElement('div');
                    notification.className = `fixed top-4 right-4 px-6 py-3 rounded-lg shadow-lg text-white ${
                        type === 'success' ? 'bg-green-600' : 'bg-red-600'
                    }`;
                    notification.textContent = message;
                    
                    document.body.appendChild(notification);
                    
                    // Remove after 3 seconds
                    setTimeout(() => {
                        notification.remove();
                    }, 3000);
                }
            }
        }
    </script>
    @endpush
</x-admin-layout>