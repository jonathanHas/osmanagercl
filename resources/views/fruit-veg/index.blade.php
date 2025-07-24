<x-admin-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('Fruit & Vegetables Management') }}
            </h2>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <!-- Statistics Cards -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-8">
                <!-- Total Fruits -->
                <div class="bg-white rounded-lg shadow p-6">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-green-100 text-green-800">
                            <svg class="h-8 w-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>
                        <div class="ml-5">
                            <p class="text-gray-500 text-sm font-medium">Total Fruits</p>
                            <p class="text-2xl font-semibold text-gray-900">{{ $stats['total_fruits'] }}</p>
                            <p class="text-sm text-green-600">{{ $stats['available_fruits'] }} available</p>
                        </div>
                    </div>
                </div>

                <!-- Total Vegetables -->
                <div class="bg-white rounded-lg shadow p-6">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-yellow-100 text-yellow-800">
                            <svg class="h-8 w-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                            </svg>
                        </div>
                        <div class="ml-5">
                            <p class="text-gray-500 text-sm font-medium">Total Vegetables</p>
                            <p class="text-2xl font-semibold text-gray-900">{{ $stats['total_vegetables'] }}</p>
                            <p class="text-sm text-green-600">{{ $stats['available_vegetables'] }} available</p>
                        </div>
                    </div>
                </div>

                <!-- Labels Needed -->
                <div class="bg-white rounded-lg shadow p-6">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-red-100 text-red-800">
                            <svg class="h-8 w-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z" />
                            </svg>
                        </div>
                        <div class="ml-5">
                            <p class="text-gray-500 text-sm font-medium">Labels Needed</p>
                            <p class="text-2xl font-semibold text-gray-900">{{ $stats['needs_labels'] }}</p>
                            <p class="text-sm text-gray-600">{{ $stats['recent_price_changes'] }} price changes</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="bg-white rounded-lg shadow mb-8">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-medium text-gray-900">Quick Actions</h3>
                </div>
                <div class="p-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                        <a href="{{ route('fruit-veg.availability') }}" 
                           class="flex items-center justify-center px-4 py-3 bg-green-600 text-white rounded-lg hover:bg-green-700 transition">
                            <svg class="h-5 w-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            Manage Availability
                        </a>
                        
                        <a href="{{ route('fruit-veg.prices') }}" 
                           class="flex items-center justify-center px-4 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition">
                            <svg class="h-5 w-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            Update Prices
                        </a>
                        
                        <a href="{{ route('fruit-veg.labels') }}" 
                           class="flex items-center justify-center px-4 py-3 bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition">
                            <svg class="h-5 w-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                            </svg>
                            Print Labels
                        </a>
                        
                        <a href="{{ route('products.index') }}?category=Fruit,Vegetables" 
                           class="flex items-center justify-center px-4 py-3 bg-gray-600 text-white rounded-lg hover:bg-gray-700 transition">
                            <svg class="h-5 w-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                            </svg>
                            View All Products
                        </a>
                    </div>
                </div>
            </div>

            <!-- Featured Available Products -->
            @if($featuredAvailable->count() > 0)
            <div class="bg-white rounded-lg shadow mb-8">
                <div class="px-6 py-4 border-b border-gray-200">
                    <div class="flex justify-between items-center">
                        <h3 class="text-lg font-medium text-gray-900">Available This Week</h3>
                        <a href="{{ route('fruit-veg.availability') }}" 
                           class="text-sm text-green-600 hover:text-green-800 font-medium">
                            Manage All Availability →
                        </a>
                    </div>
                </div>
                <div class="p-6">
                    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-6 gap-4">
                        @foreach($featuredAvailable as $product)
                        <a href="{{ route('fruit-veg.product.edit', $product->CODE) }}" 
                           class="block bg-gray-50 rounded-lg p-3 text-center hover:bg-gray-100 hover:shadow-md transition cursor-pointer">
                            <div class="aspect-square bg-white rounded-lg mb-2 overflow-hidden">
                                <img src="{{ route('fruit-veg.product-image', $product->CODE) }}" 
                                     alt="{{ $product->NAME }}"
                                     class="w-full h-full object-cover"
                                     loading="lazy">
                            </div>
                            <h4 class="text-sm font-medium text-gray-900 truncate" title="{{ strip_tags(html_entity_decode($product->NAME)) }}">
                                @if($product->DISPLAY)
                                    {!! nl2br(html_entity_decode($product->DISPLAY)) !!}
                                @else
                                    {{ strip_tags(html_entity_decode($product->NAME)) }}
                                @endif
                            </h4>
                            <p class="text-xs text-gray-500 truncate">
                                {{ $product->category->NAME ?? 'Unknown' }}
                            </p>
                            <div class="mt-2">
                                <span class="text-sm font-semibold text-green-600">
                                    €{{ number_format($product->current_price, 2) }}
                                </span>
                                @if($product->vegDetails && $product->vegDetails->country)
                                <p class="text-xs text-gray-400 mt-1">
                                    {{ $product->vegDetails->country->country }}
                                </p>
                                @endif
                            </div>
                        </a>
                        @endforeach
                    </div>
                    @if($featuredAvailable->count() >= 12)
                    <div class="mt-4 text-center">
                        <a href="{{ route('fruit-veg.availability') }}" 
                           class="inline-flex items-center px-4 py-2 bg-green-600 text-white text-sm font-medium rounded-lg hover:bg-green-700 transition">
                            <svg class="h-4 w-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            View All Available Products
                        </a>
                    </div>
                    @endif
                </div>
            </div>
            @endif

            <!-- Recent Price Changes -->
            @if($recentPriceChanges->count() > 0)
            <div class="bg-white rounded-lg shadow">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-medium text-gray-900">Recent Price Changes</h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Product</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Old Price</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">New Price</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Change</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @foreach($recentPriceChanges as $change)
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                    {{ $change->product_name }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    €{{ number_format($change->old_price, 2) }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    €{{ number_format($change->new_price, 2) }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm">
                                    @php
                                        $diff = $change->new_price - $change->old_price;
                                        $percent = $change->old_price > 0 ? ($diff / $change->old_price) * 100 : 0;
                                    @endphp
                                    <span class="{{ $diff > 0 ? 'text-red-600' : 'text-green-600' }}">
                                        {{ $diff > 0 ? '+' : '' }}€{{ number_format($diff, 2) }}
                                        ({{ $diff > 0 ? '+' : '' }}{{ number_format($percent, 1) }}%)
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    {{ \Carbon\Carbon::parse($change->changed_at)->format('d/m/Y H:i') }}
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
            @endif
        </div>
    </div>
</x-admin-layout>