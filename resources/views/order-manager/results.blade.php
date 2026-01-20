<x-admin-layout>
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6" x-data="{ expandedSuppliers: {}, allExpanded: true }" x-init="
        {{-- Initialize all suppliers as expanded by default --}}
        @if(!empty($results))
            @foreach(collect($results)->filter(fn($r) => $r['low_stock_count'] > 0) as $index => $result)
                expandedSuppliers['{{ $result['supplier']->id }}'] = true;
            @endforeach
        @endif
    ">
        {{-- Header Section --}}
        <div class="flex justify-between items-center mb-6">
            <div>
                <h2 class="text-2xl font-bold text-gray-100">Stock Check Results</h2>
                <p class="text-gray-400 mt-1">
                    Check run at {{ $checkTime->format('d M Y H:i') }}
                </p>
            </div>
            <div class="flex space-x-2">
                <a href="{{ route('order-manager.check') }}"
                   class="bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-4 rounded text-sm">
                    <i class="fas fa-sync-alt mr-2"></i>Refresh Check
                </a>
                <a href="{{ route('order-manager.index') }}"
                   class="bg-gray-600 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded text-sm">
                    <i class="fas fa-cog mr-2"></i>Manage Suppliers
                </a>
            </div>
        </div>

        {{-- Summary Stats --}}
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
            <div class="bg-gray-800 rounded-lg p-4 text-center">
                <div class="text-3xl font-bold text-blue-400">{{ $stats['managed_suppliers_count'] }}</div>
                <div class="text-sm text-gray-400">Suppliers Checked</div>
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

        {{-- Results by Supplier --}}
        @if(empty($results))
            <div class="bg-gray-800 rounded-lg p-8 text-center">
                <i class="fas fa-inbox text-gray-500 text-4xl mb-3"></i>
                <p class="text-gray-400">No suppliers are being managed.</p>
                <a href="{{ route('order-manager.index') }}" class="text-blue-400 hover:text-blue-300 text-sm mt-2 inline-block">
                    Configure suppliers to monitor
                </a>
            </div>
        @else
            @php
                $suppliersNeedingAttention = collect($results)->filter(fn($r) => $r['low_stock_count'] > 0);
                $suppliersOK = collect($results)->filter(fn($r) => $r['low_stock_count'] === 0);
            @endphp

            {{-- Suppliers Needing Attention --}}
            @if($suppliersNeedingAttention->isNotEmpty())
                <div class="mb-8">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-lg font-semibold text-red-400 flex items-center">
                            <i class="fas fa-exclamation-circle mr-2"></i>
                            Suppliers Needing Attention ({{ $suppliersNeedingAttention->count() }})
                        </h3>
                        <button @click="
                            allExpanded = !allExpanded;
                            @foreach($suppliersNeedingAttention as $result)
                                expandedSuppliers['{{ $result['supplier']->id }}'] = allExpanded;
                            @endforeach
                        " class="text-sm text-blue-400 hover:text-blue-300 flex items-center">
                            <i class="fas mr-1" :class="allExpanded ? 'fa-compress-alt' : 'fa-expand-alt'"></i>
                            <span x-text="allExpanded ? 'Collapse All' : 'Expand All'"></span>
                        </button>
                    </div>

                    @foreach($suppliersNeedingAttention as $result)
                        <div class="bg-gray-800 rounded-lg mb-4 overflow-hidden border-l-4 border-yellow-500">
                            {{-- Supplier Header (Clickable) --}}
                            <button @click="expandedSuppliers['{{ $result['supplier']->id }}'] = !expandedSuppliers['{{ $result['supplier']->id }}']"
                                    class="w-full px-4 py-3 bg-gray-900 flex justify-between items-center cursor-pointer hover:bg-gray-800/50 transition-colors">
                                <div class="flex items-center">
                                    <i class="fas fa-chevron-right mr-3 text-gray-400 transition-transform duration-200"
                                       :class="{ 'rotate-90': expandedSuppliers['{{ $result['supplier']->id }}'] }"></i>
                                    <div class="text-left">
                                        <h4 class="text-lg font-semibold text-gray-100">{{ $result['supplier']->name }}</h4>
                                        <div class="text-sm text-gray-400">
                                            <span class="text-purple-400 font-mono">POS: {{ $result['supplier']->external_pos_id }}</span>
                                            <span class="mx-2">|</span>
                                            Threshold: {{ $result['threshold'] }} units
                                        </div>
                                    </div>
                                </div>
                                <div class="text-right">
                                    <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-yellow-900 text-yellow-300">
                                        {{ $result['low_stock_count'] }} items low
                                    </span>
                                    @if($result['out_of_stock_count'] > 0)
                                        <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-red-900 text-red-300 ml-2">
                                            {{ $result['out_of_stock_count'] }} out of stock
                                        </span>
                                    @endif
                                </div>
                            </button>

                            {{-- Products Table (Collapsible) --}}
                            <div x-show="expandedSuppliers['{{ $result['supplier']->id }}']"
                                 x-collapse
                                 x-cloak>
                            <table class="min-w-full divide-y divide-gray-700">
                                <thead class="bg-gray-800">
                                    <tr>
                                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-400 uppercase">Product</th>
                                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-400 uppercase">Barcode</th>
                                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-400 uppercase">Supplier Code</th>
                                        <th class="px-4 py-2 text-center text-xs font-medium text-gray-400 uppercase">Current Stock</th>
                                        <th class="px-4 py-2 text-center text-xs font-medium text-gray-400 uppercase">Case Units</th>
                                        <th class="px-4 py-2 text-center text-xs font-medium text-gray-400 uppercase">Status</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-700">
                                    @foreach($result['products'] as $product)
                                        <tr class="hover:bg-gray-700/50">
                                            <td class="px-4 py-2">
                                                <div class="text-sm text-gray-100">{{ $product->product_name }}</div>
                                            </td>
                                            <td class="px-4 py-2">
                                                <span class="text-sm font-mono text-gray-400">{{ $product->barcode }}</span>
                                            </td>
                                            <td class="px-4 py-2">
                                                <span class="text-sm text-gray-400">{{ $product->supplier_code ?: '-' }}</span>
                                            </td>
                                            <td class="px-4 py-2 text-center">
                                                <span class="text-sm font-bold {{ $product->current_stock <= 0 ? 'text-red-400' : 'text-yellow-400' }}">
                                                    {{ number_format($product->current_stock, 1) }}
                                                </span>
                                            </td>
                                            <td class="px-4 py-2 text-center">
                                                <span class="text-sm text-gray-400">{{ $product->case_units ?: '-' }}</span>
                                            </td>
                                            <td class="px-4 py-2 text-center">
                                                @if($product->current_stock <= 0)
                                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-red-900 text-red-300">
                                                        Out of Stock
                                                    </span>
                                                @else
                                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-yellow-900 text-yellow-300">
                                                        Low Stock
                                                    </span>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif

            {{-- Suppliers OK --}}
            @if($suppliersOK->isNotEmpty())
                <div>
                    <h3 class="text-lg font-semibold text-green-400 mb-4 flex items-center">
                        <i class="fas fa-check-circle mr-2"></i>
                        Suppliers OK ({{ $suppliersOK->count() }})
                    </h3>

                    <div class="bg-gray-800 rounded-lg overflow-hidden">
                        <table class="min-w-full divide-y divide-gray-700">
                            <thead class="bg-gray-900">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase">Supplier</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase">POS ID</th>
                                    <th class="px-4 py-3 text-center text-xs font-medium text-gray-400 uppercase">Threshold</th>
                                    <th class="px-4 py-3 text-center text-xs font-medium text-gray-400 uppercase">Status</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-700">
                                @foreach($suppliersOK as $result)
                                    <tr class="hover:bg-gray-700/50">
                                        <td class="px-4 py-3">
                                            <div class="text-sm font-medium text-gray-100">{{ $result['supplier']->name }}</div>
                                        </td>
                                        <td class="px-4 py-3">
                                            <span class="text-sm text-purple-400 font-mono">{{ $result['supplier']->external_pos_id }}</span>
                                        </td>
                                        <td class="px-4 py-3 text-center">
                                            <span class="text-sm text-gray-400">{{ $result['threshold'] }} units</span>
                                        </td>
                                        <td class="px-4 py-3 text-center">
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-900 text-green-300">
                                                <i class="fas fa-check mr-1"></i> All stock OK
                                            </span>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif

            {{-- All Clear Message --}}
            @if($stats['low_stock_products_count'] === 0)
                <div class="bg-green-900/30 border border-green-700 rounded-lg p-6 text-center mt-6">
                    <i class="fas fa-thumbs-up text-green-400 text-4xl mb-3"></i>
                    <p class="text-green-300 text-lg font-medium">All managed suppliers have adequate stock!</p>
                    <p class="text-green-400/70 text-sm mt-1">No ordering action needed at this time.</p>
                </div>
            @endif
        @endif
    </div>
</x-admin-layout>
