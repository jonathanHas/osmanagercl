@props(['product', 'supplierService', 'udeaPricing' => null])

@php
    $hasSupplierIntegration = $product->supplier && $supplierService->hasExternalIntegration($product->supplier->SupplierID);
    $hasUdeaPricing = $hasSupplierIntegration && $udeaPricing;
    
    // Calculate key metrics
    $sellPriceWithVat = $product->PRICESELL * (1 + $product->getVatRate());
    $costPrice = $product->PRICEBUY;
    $margin = $product->PRICESELL - $costPrice;
    $marginPercent = $costPrice > 0 ? ($margin / $costPrice) * 100 : 0;
    
    // Udea pricing calculations
    if ($hasUdeaPricing) {
        $udeaUnitPrice = (float)str_replace(',', '.', $udeaPricing['unit_price'] ?? '0');
        $udeaCasePrice = (float)str_replace(',', '.', $udeaPricing['case_price'] ?? '0');
        $unitsPerCase = $udeaPricing['units_per_case'] ?? 1;
        
        // Use scraped unit price if available, otherwise calculate
        if ($udeaUnitPrice <= 0 && $udeaCasePrice > 0 && $unitsPerCase > 0) {
            $udeaUnitPrice = $udeaCasePrice / $unitsPerCase;
        }
        
        $transportCostPerUnit = $udeaUnitPrice * 0.15;
        $udeaTotalCost = $udeaUnitPrice + $transportCostPerUnit;
        $customerPrice = isset($udeaPricing['customer_price']) ? (float)str_replace(',', '.', $udeaPricing['customer_price']) : null;
        
        // Price comparison
        $priceDifference = $costPrice - $udeaUnitPrice;
        $priceCompetitive = $sellPriceWithVat <= ($udeaTotalCost * 1.35); // 35% margin on total cost
    }
@endphp

<div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg mb-6">
    <div class="p-6">
        <!-- Section Header with Status Indicators -->
        <div class="flex items-center justify-between mb-6">
            <h2 class="text-xl font-bold text-gray-900 dark:text-gray-100">Pricing & Margins</h2>
            <div class="flex items-center gap-3">
                @if($hasUdeaPricing)
                    @if($priceCompetitive)
                        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900/20 dark:text-green-400">
                            <svg class="w-4 h-4 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                            </svg>
                            Price Competitive
                        </span>
                    @else
                        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-orange-100 text-orange-800 dark:bg-orange-900/20 dark:text-orange-400">
                            <svg class="w-4 h-4 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                            </svg>
                            Review Price
                        </span>
                    @endif
                @endif
                
                <button onclick="togglePriceEdit()" class="text-indigo-600 hover:text-indigo-900 dark:text-indigo-400 dark:hover:text-indigo-300">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                    </svg>
                </button>
            </div>
        </div>

        <!-- Main Pricing Grid -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Your Pricing Column -->
            <div class="bg-gray-50 dark:bg-gray-700/50 rounded-lg p-5">
                <h3 class="text-sm font-medium text-gray-600 dark:text-gray-400 mb-4 flex items-center">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                    </svg>
                    Your Pricing
                </h3>
                
                <div class="space-y-3">
                    <div>
                        <div class="text-xs text-gray-500 dark:text-gray-400">Selling Price (incl. VAT)</div>
                        <div class="text-2xl font-bold text-gray-900 dark:text-gray-100">€{{ number_format($sellPriceWithVat, 2) }}</div>
                    </div>
                    
                    <div class="pt-2 space-y-2 border-t border-gray-200 dark:border-gray-600">
                        <div class="flex justify-between text-sm">
                            <span class="text-gray-600 dark:text-gray-400">Net Price:</span>
                            <span class="font-medium text-gray-900 dark:text-gray-100">€{{ number_format($product->PRICESELL, 2) }}</span>
                        </div>
                        <div class="flex justify-between text-sm">
                            <span class="text-gray-600 dark:text-gray-400">Cost Price:</span>
                            <span class="font-medium text-gray-900 dark:text-gray-100">€{{ number_format($costPrice, 2) }}</span>
                        </div>
                        <div class="flex justify-between text-sm">
                            <span class="text-gray-600 dark:text-gray-400">VAT ({{ $product->formatted_vat_rate }}):</span>
                            <span class="font-medium text-gray-900 dark:text-gray-100">€{{ number_format($product->getVatAmount(), 2) }}</span>
                        </div>
                    </div>
                    
                    <div class="pt-2 border-t border-gray-200 dark:border-gray-600">
                        <div class="flex justify-between items-center">
                            <span class="text-sm text-gray-600 dark:text-gray-400">Margin:</span>
                            <span class="text-lg font-bold {{ $margin > 0 ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400' }}">
                                €{{ number_format($margin, 2) }}
                                @if($costPrice > 0)
                                    <span class="text-sm font-normal">({{ number_format($marginPercent, 1) }}%)</span>
                                @endif
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Supplier Pricing Column -->
            <div class="bg-blue-50 dark:bg-blue-900/20 rounded-lg p-5">
                <h3 class="text-sm font-medium text-gray-600 dark:text-gray-400 mb-4 flex items-center">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                    </svg>
                    Supplier Pricing
                </h3>
                
                @if($hasUdeaPricing)
                    <div class="space-y-3">
                        <div>
                            <div class="text-xs text-gray-500 dark:text-gray-400">Udea Unit Cost</div>
                            <div class="text-2xl font-bold text-blue-900 dark:text-blue-100">€{{ number_format($udeaUnitPrice, 2) }}</div>
                            @if($udeaPricing['is_discounted'] ?? false)
                                <div class="text-xs text-red-600 dark:text-red-400 font-medium">
                                    🏷️ Discounted from €{{ $udeaPricing['original_price'] }}
                                </div>
                            @endif
                        </div>
                        
                        <div class="pt-2 space-y-2 border-t border-blue-200 dark:border-blue-700">
                            <div class="flex justify-between text-sm">
                                <span class="text-gray-600 dark:text-gray-400">Case Price:</span>
                                <span class="font-medium text-gray-900 dark:text-gray-100">€{{ $udeaPricing['case_price'] ?? 'N/A' }}</span>
                            </div>
                            <div class="flex justify-between text-sm">
                                <span class="text-gray-600 dark:text-gray-400">Units/Case:</span>
                                <span class="font-medium text-gray-900 dark:text-gray-100">{{ $unitsPerCase }}</span>
                            </div>
                            <div class="flex justify-between text-sm">
                                <span class="text-gray-600 dark:text-gray-400">Transport (15%):</span>
                                <span class="font-medium text-orange-600 dark:text-orange-400">+€{{ number_format($transportCostPerUnit, 2) }}</span>
                            </div>
                            @if($customerPrice)
                                <div class="flex justify-between text-sm">
                                    <span class="text-gray-600 dark:text-gray-400">Customer Price:</span>
                                    <span class="font-medium text-purple-600 dark:text-purple-400">€{{ number_format($customerPrice, 2) }}</span>
                                </div>
                            @endif
                        </div>
                        
                        <div class="pt-2 border-t border-blue-200 dark:border-blue-700">
                            <div class="flex justify-between items-center">
                                <span class="text-sm text-gray-600 dark:text-gray-400">Total Cost:</span>
                                <span class="text-lg font-bold text-blue-900 dark:text-blue-100">
                                    €{{ number_format($udeaTotalCost, 2) }}
                                </span>
                            </div>
                        </div>
                        
                        @if($hasSupplierIntegration)
                            <div class="pt-2">
                                <button onclick="refreshUdeaPricing()" 
                                        id="refreshPricingBtn"
                                        class="w-full inline-flex items-center justify-center px-3 py-1.5 text-xs font-medium text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300 border border-blue-300 dark:border-blue-600 rounded-md hover:bg-blue-50 dark:hover:bg-blue-900/30 transition-colors duration-200">
                                    <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                                    </svg>
                                    Refresh Prices
                                </button>
                            </div>
                        @endif
                    </div>
                @elseif($hasSupplierIntegration && $product->supplierLink?->SupplierCode)
                    <div class="text-center py-8">
                        <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">
                            Supplier code: <strong>{{ $product->supplierLink->SupplierCode }}</strong>
                        </p>
                        <button onclick="refreshUdeaPricing()" 
                                id="refreshPricingBtn"
                                class="inline-flex items-center px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white font-medium text-sm rounded-md transition-colors duration-200">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                            </svg>
                            Fetch Udea Prices
                        </button>
                    </div>
                @else
                    <div class="text-center py-8 text-gray-500 dark:text-gray-400">
                        <svg class="w-12 h-12 mx-auto mb-3 text-gray-300 dark:text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                        </svg>
                        <p class="text-sm">No supplier pricing available</p>
                    </div>
                @endif
            </div>

            <!-- Analysis & Actions Column -->
            <div class="bg-green-50 dark:bg-green-900/20 rounded-lg p-5">
                <h3 class="text-sm font-medium text-gray-600 dark:text-gray-400 mb-4 flex items-center">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                    </svg>
                    Quick Actions
                </h3>
                
                @if($hasUdeaPricing)
                    <div class="space-y-3">
                        <!-- Comparison Summary -->
                        <div class="p-3 rounded-lg {{ $priceDifference > 0 ? 'bg-green-100 dark:bg-green-900/30' : 'bg-red-100 dark:bg-red-900/30' }}">
                            <div class="text-xs {{ $priceDifference > 0 ? 'text-green-700 dark:text-green-300' : 'text-red-700 dark:text-red-300' }} font-medium mb-1">
                                Cost Comparison
                            </div>
                            <div class="text-lg font-bold {{ $priceDifference > 0 ? 'text-green-800 dark:text-green-200' : 'text-red-800 dark:text-red-200' }}">
                                @if($priceDifference > 0)
                                    Udea €{{ number_format(abs($priceDifference), 2) }} cheaper
                                @elseif($priceDifference < 0)
                                    Current €{{ number_format(abs($priceDifference), 2) }} cheaper
                                @else
                                    Same cost
                                @endif
                            </div>
                        </div>
                        
                        <!-- Primary Actions -->
                        <div class="space-y-2">
                            @if($udeaUnitPrice > 0 && abs($costPrice - $udeaUnitPrice) > 0.01)
                                <button onclick="updateProductCost({{ $udeaUnitPrice }})" 
                                        class="w-full px-3 py-2 text-sm font-medium text-white bg-orange-600 hover:bg-orange-700 rounded-md transition-colors duration-200">
                                    Update Cost to €{{ number_format($udeaUnitPrice, 2) }}
                                </button>
                            @endif
                            
                            @if($customerPrice && abs($product->PRICESELL - $customerPrice) > 0.01)
                                <button onclick="updateProductPrice({{ $customerPrice }})" 
                                        class="w-full px-3 py-2 text-sm font-medium text-white bg-purple-600 hover:bg-purple-700 rounded-md transition-colors duration-200">
                                    Match Customer Price €{{ number_format($customerPrice, 2) }}
                                </button>
                            @endif
                        </div>
                        
                        <!-- More Actions (Collapsible) -->
                        <div x-data="{ showMore: false }" class="pt-2 border-t border-gray-200 dark:border-gray-600">
                            <button @click="showMore = !showMore" class="w-full text-sm text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-gray-200 flex items-center justify-center">
                                <span x-text="showMore ? 'Hide' : 'Show'">Show</span> more pricing options
                                <svg class="w-4 h-4 ml-1 transform transition-transform" :class="showMore ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                                </svg>
                            </button>
                            
                            <div x-show="showMore" x-transition class="mt-3 space-y-2">
                                @php
                                    $optimalPrice = $udeaTotalCost * 1.35; // 35% margin on total cost
                                    $competitivePrice = $udeaUnitPrice * 1.1; // 10% above Udea
                                @endphp
                                
                                <button onclick="updateProductPrice({{ $optimalPrice }})" 
                                        class="w-full px-3 py-2 text-xs font-medium text-white bg-indigo-600 hover:bg-indigo-700 rounded-md">
                                    Optimal (35% margin): €{{ number_format($optimalPrice, 2) }}
                                </button>
                                
                                <button onclick="updateProductPrice({{ $competitivePrice }})" 
                                        class="w-full px-3 py-2 text-xs font-medium text-white bg-green-600 hover:bg-green-700 rounded-md">
                                    Competitive (+10%): €{{ number_format($competitivePrice, 2) }}
                                </button>
                            </div>
                        </div>
                    </div>
                @else
                    <div class="space-y-3">
                        <!-- Manual Price Edit -->
                        <div>
                            <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Update Cost</label>
                            <div class="flex">
                                <input type="number" step="0.01" min="0" 
                                       id="quickCostUpdate" value="{{ $product->PRICEBUY }}"
                                       class="flex-1 text-sm rounded-l-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300">
                                <button onclick="updateProductCost(document.getElementById('quickCostUpdate').value)" 
                                        class="px-3 py-1 text-xs font-medium text-white bg-orange-600 hover:bg-orange-700 rounded-r-md">
                                    Update
                                </button>
                            </div>
                        </div>
                        
                        <div>
                            <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Update Selling Price (incl. VAT)</label>
                            <div class="flex">
                                <input type="number" step="0.01" min="0" 
                                       id="quickPriceUpdateVat" 
                                       value="{{ number_format($product->PRICESELL * (1 + $product->getVatRate()), 2) }}"
                                       oninput="calculateNetFromGross()"
                                       class="flex-1 text-sm rounded-l-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300">
                                <button onclick="updateProductPriceFromVat()" 
                                        class="px-3 py-1 text-xs font-medium text-white bg-blue-600 hover:bg-blue-700 rounded-r-md">
                                    Update
                                </button>
                            </div>
                            <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                Net price: €<span id="calculatedNetPrice">{{ number_format($product->PRICESELL, 2) }}</span>
                            </div>
                        </div>
                    </div>
                @endif
                
                <!-- Last Updated -->
                @if($hasUdeaPricing && isset($udeaPricing['scraped_at']))
                    <div class="mt-4 pt-3 border-t border-gray-200 dark:border-gray-600">
                        <p class="text-xs text-gray-500 dark:text-gray-400 text-center">
                            Prices updated: {{ \Carbon\Carbon::parse($udeaPricing['scraped_at'])->format('d/m H:i') }}
                        </p>
                    </div>
                @endif
            </div>
        </div>
        
        <!-- Expandable Advanced Analysis -->
        @if($hasUdeaPricing)
            <div x-data="{ showAnalysis: false }" class="mt-6">
                <button @click="showAnalysis = !showAnalysis" 
                        class="w-full flex items-center justify-between px-4 py-3 bg-gray-50 dark:bg-gray-700/50 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors duration-200">
                    <span class="text-sm font-medium text-gray-700 dark:text-gray-300">
                        Advanced Pricing Analysis
                    </span>
                    <svg class="w-5 h-5 text-gray-500 transform transition-transform" :class="showAnalysis ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                    </svg>
                </button>
                
                <div x-show="showAnalysis" x-transition class="mt-4 p-4 bg-gray-50 dark:bg-gray-700/50 rounded-lg">
                    <!-- Detailed margin calculations, price history, etc. -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-3">Margin Analysis</h4>
                            <div class="space-y-2 text-sm">
                                <div class="flex justify-between">
                                    <span class="text-gray-600 dark:text-gray-400">Current Margin vs Cost:</span>
                                    <span class="font-medium">{{ number_format($marginPercent, 1) }}%</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-600 dark:text-gray-400">Margin vs Udea Cost:</span>
                                    <span class="font-medium">{{ $udeaUnitPrice > 0 ? number_format((($product->PRICESELL - $udeaUnitPrice) / $udeaUnitPrice) * 100, 1) : 'N/A' }}%</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-600 dark:text-gray-400">Margin vs Udea + Transport:</span>
                                    <span class="font-medium">{{ number_format((($product->PRICESELL - $udeaTotalCost) / $udeaTotalCost) * 100, 1) }}%</span>
                                </div>
                            </div>
                        </div>
                        
                        <div>
                            <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-3">Price Recommendations</h4>
                            <div class="space-y-2 text-sm">
                                <div class="flex justify-between">
                                    <span class="text-gray-600 dark:text-gray-400">Break-even price:</span>
                                    <span class="font-medium">€{{ number_format($costPrice * (1 + $product->getVatRate()), 2) }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-600 dark:text-gray-400">20% margin price:</span>
                                    <span class="font-medium">€{{ number_format($costPrice * 1.2, 2) }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-600 dark:text-gray-400">Industry avg (30%):</span>
                                    <span class="font-medium">€{{ number_format($costPrice * 1.3, 2) }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    @if($hasSupplierIntegration && $product->supplier)
                        <div class="mt-4 pt-4 border-t border-gray-200 dark:border-gray-600">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-sm text-gray-600 dark:text-gray-400">
                                        Supplier: <strong>{{ $product->supplier->Supplier }}</strong>
                                        @if($product->supplierLink)
                                            • Code: <strong>{{ $product->supplierLink->SupplierCode }}</strong>
                                        @endif
                                    </p>
                                </div>
                                @if($link = $supplierService->getSupplierWebsiteLink($product))
                                    <a href="{{ $link }}" 
                                       target="_blank" 
                                       rel="noopener noreferrer" 
                                       class="inline-flex items-center text-sm text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300">
                                        View on supplier site
                                        <svg class="w-4 h-4 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
                                        </svg>
                                    </a>
                                @endif
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        @endif
    </div>
</div>