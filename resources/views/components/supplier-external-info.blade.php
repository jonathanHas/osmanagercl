@props(['product', 'supplierService', 'udeaPricing' => null])

@if($product->supplier && $supplierService->hasExternalIntegration($product->supplier->SupplierID))
    <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-6">
        <h3 class="text-lg font-semibold mb-4">Supplier Information</h3>
        
        <div class="flex flex-col md:flex-row gap-6">
            <!-- Product Image -->
            <div class="flex-shrink-0">
                <div class="relative">
                    <img 
                        src="{{ $supplierService->getExternalImageUrl($product) }}" 
                        alt="{{ $product->NAME }}"
                        class="w-48 h-48 object-cover rounded-lg border border-gray-200 dark:border-gray-600 shadow-sm"
                        loading="lazy"
                        onerror="this.onerror=null; this.src='data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMjAwIiBoZWlnaHQ9IjIwMCIgdmlld0JveD0iMCAwIDIwMCAyMDAiIGZpbGw9Im5vbmUiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+CjxyZWN0IHdpZHRoPSIyMDAiIGhlaWdodD0iMjAwIiBmaWxsPSIjRTVFN0VCIi8+CjxwYXRoIGQ9Ik04NSA3MEM4NSA2MS43MTU3IDkxLjcxNTcgNTUgMTAwIDU1QzEwOC4yODQgNTUgMTE1IDYxLjcxNTcgMTE1IDcwQzExNSA3OC4yODQzIDEwOC4yODQgODUgMTAwIDg1Qzk1LjAyOTQgODUgOTAuNzY4NyA4Mi4zNjYxIDg4LjY3NjQgNzguMzUyOEw3NSAxMjBIMTI1TDExMS4zMjQgNzguMzUyOEMxMDkuMjMxIDgyLjM2NjEgMTA0Ljk3MSA4NSAxMDAgODVDOTEuNzE1NyA4NSA4NSA3OC4yODQzIDg1IDcwWiIgZmlsbD0iIzlDQTNCOCIvPgo8cGF0aCBkPSJNNzAgMTMwSDEzMFYxNDBINzBWMTMwWiIgZmlsbD0iIzlDQTNCOCIvPgo8L3N2Zz4=';"
                    >
                    <div class="absolute bottom-0 right-0 bg-white dark:bg-gray-800 rounded-tl-lg px-2 py-1 text-xs text-gray-600 dark:text-gray-400">
                        External image
                    </div>
                </div>
            </div>
            
            <!-- Supplier Details -->
            <div class="flex-1 space-y-4">
                <div>
                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Supplier</dt>
                    <dd class="mt-1 text-lg font-semibold text-gray-900 dark:text-gray-100">
                        {{ $product->supplier->Supplier }}
                    </dd>
                </div>
                
                @if($product->supplierLink)
                    <div>
                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Supplier Code</dt>
                        <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100 font-mono">
                            {{ $product->supplierLink->SupplierCode }}
                        </dd>
                    </div>
                    
                    @if($product->supplierLink->Cost > 0)
                        <div>
                            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Supplier Cost</dt>
                            <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100">
                                €{{ number_format($product->supplierLink->Cost, 2) }}
                            </dd>
                        </div>
                    @endif
                    
                    @if($product->supplierLink->CaseUnits > 0)
                        <div>
                            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Case Units</dt>
                            <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100">
                                {{ $product->supplierLink->CaseUnits }} units per case
                            </dd>
                        </div>
                    @endif
                @endif
                
                <!-- External Link -->
                @if($link = $supplierService->getSupplierWebsiteLink($product))
                    <div class="pt-4">
                        <a href="{{ $link }}" 
                           target="_blank" 
                           rel="noopener noreferrer" 
                           class="inline-flex items-center px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white font-medium text-sm rounded-md transition-colors duration-200">
                            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path>
                            </svg>
                            View on {{ $supplierService->getSupplierDisplayName($product->supplier->SupplierID) }} website
                        </a>
                    </div>
                @endif
            </div>
        </div>
        
        <!-- Live Price Comparison Section -->
        @if($udeaPricing && $product->supplierLink)
            <div class="mt-6 border-t border-gray-200 dark:border-gray-600 pt-6">
                <div class="flex items-center justify-between mb-4">
                    <h4 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Live Price Comparison</h4>
                    <button onclick="refreshUdeaPricing()" 
                            id="refreshPricingBtn"
                            class="inline-flex items-center px-3 py-1 text-xs font-medium text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300 transition-colors duration-200">
                        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                        </svg>
                        Refresh Prices
                    </button>
                </div>

                <div id="priceComparisonContainer" class="grid grid-cols-1 lg:grid-cols-3 gap-4">
                    <!-- Your Current Price -->
                    <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-600 rounded-lg p-4">
                        <div class="text-sm font-medium text-gray-500 dark:text-gray-400 mb-1">Your Current Price</div>
                        <div class="text-2xl font-bold text-gray-900 dark:text-gray-100">
                            €{{ number_format($product->PRICESELL * (1 + $product->getVatRate()), 2) }}
                            <span class="text-sm font-normal text-gray-500 dark:text-gray-400">(incl. VAT per unit)</span>
                        </div>
                        <div class="text-xs text-gray-500 dark:text-gray-400 mt-1 space-y-1">
                            <div>Net selling: €{{ number_format($product->PRICESELL, 2) }}</div>
                            <div>Your cost: €{{ number_format($product->PRICEBUY, 2) }}</div>
                            <div class="font-medium {{ ($product->PRICESELL - $product->PRICEBUY) > 0 ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400' }}">
                                Margin: €{{ number_format($product->PRICESELL - $product->PRICEBUY, 2) }} 
                                @if($product->PRICEBUY > 0)
                                    ({{ number_format((($product->PRICESELL - $product->PRICEBUY) / $product->PRICEBUY) * 100, 1) }}%)
                                @endif
                            </div>
                        </div>
                    </div>

                    <!-- Udea Price -->
                    <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-700 rounded-lg p-4">
                        <div class="flex items-center justify-between mb-1">
                            <div class="text-sm font-medium text-gray-500 dark:text-gray-400">Udea Pricing</div>
                            @if($udeaPricing['is_discounted'] ?? false)
                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200">
                                    🏷️ DISCOUNTED
                                </span>
                            @endif
                        </div>
                        
                        @php
                            $casePrice = (float)str_replace(',', '.', $udeaPricing['case_price'] ?? '0');
                            $scrapedUnitPrice = (float)str_replace(',', '.', $udeaPricing['unit_price'] ?? '0');
                            $unitsPerCase = $udeaPricing['units_per_case'] ?? null;
                            
                            // Use scraped unit price if available, otherwise calculate from case price
                            if ($scrapedUnitPrice > 0) {
                                $unitPrice = $scrapedUnitPrice;
                                // Calculate units per case from scraped prices if not provided
                                if (!$unitsPerCase && $casePrice > 0) {
                                    $unitsPerCase = round($casePrice / $scrapedUnitPrice);
                                }
                            } else {
                                // Fallback to calculation if no scraped unit price
                                $unitsPerCase = $unitsPerCase ?? 1;
                                $unitPrice = $unitsPerCase > 0 ? $casePrice / $unitsPerCase : 0;
                            }
                            
                            $transportCost = $casePrice * 0.15; // 15% transport cost per case
                            $transportCostPerUnit = ($unitsPerCase ?? 1) > 0 ? $transportCost / ($unitsPerCase ?? 1) : 0;
                        @endphp
                        
                        <div class="text-2xl font-bold text-blue-900 dark:text-blue-100">
                            €{{ number_format($unitPrice, 2) }}
                            <span class="text-sm font-normal text-gray-500 dark:text-gray-400">(per unit)</span>
                        </div>
                        
                        <div class="text-xs text-gray-500 dark:text-gray-400 mt-1 space-y-1">
                            <div>Case price: €{{ $udeaPricing['case_price'] ?? 'N/A' }} ({{ $unitsPerCase ?? 'unknown' }} units)</div>
                            @if($udeaPricing['is_discounted'] ?? false)
                                <div class="line-through">Original: €{{ $udeaPricing['original_price'] }}</div>
                                <div class="text-green-600 dark:text-green-400 font-medium">
                                    Discount: €{{ number_format((float)str_replace(',', '.', $udeaPricing['original_price']) - (float)str_replace(',', '.', $udeaPricing['discount_price']), 2) }}
                                    ({{ round(((float)str_replace(',', '.', $udeaPricing['original_price']) - (float)str_replace(',', '.', $udeaPricing['discount_price'])) / (float)str_replace(',', '.', $udeaPricing['original_price']) * 100, 1) }}% off)
                                </div>
                            @endif
                            <div class="font-medium text-orange-600 dark:text-orange-400">
                                + Transport (15%): €{{ number_format($transportCostPerUnit, 2) }} per unit
                            </div>
                        </div>
                    </div>

                    <!-- Cost Analysis -->
                    <div class="bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-700 rounded-lg p-4">
                        <div class="text-sm font-medium text-gray-500 dark:text-gray-400 mb-1">Cost Analysis</div>
                        <div class="text-2xl font-bold text-green-900 dark:text-green-100">
                            €{{ number_format($unitPrice, 2) }}
                            <span class="text-sm font-normal text-gray-500 dark:text-gray-400">(Udea unit cost)</span>
                        </div>
                        <div class="text-xs text-gray-500 dark:text-gray-400 mt-1 space-y-1">
                            <div>Your current cost: €{{ number_format($product->PRICEBUY, 2) }}</div>
                            <div class="font-medium {{ $unitPrice < $product->PRICEBUY ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400' }}">
                                Udea saving: €{{ number_format($product->PRICEBUY - $unitPrice, 2) }}
                                @if($product->PRICEBUY > 0)
                                    ({{ number_format((($product->PRICEBUY - $unitPrice) / $product->PRICEBUY) * 100, 1) }}%)
                                @endif
                            </div>
                            <div class="text-orange-600 dark:text-orange-400 font-medium text-xs">
                                + Transport: €{{ number_format($transportCostPerUnit, 2) }} per unit
                            </div>
                            @if($unitPrice > 0)
                                <div class="text-blue-600 dark:text-blue-400 font-medium">
                                    Margin vs Udea cost: €{{ number_format($product->PRICESELL - $unitPrice, 2) }}
                                    ({{ number_format((($product->PRICESELL - $unitPrice) / $unitPrice) * 100, 1) }}%)
                                </div>
                            @endif
                        </div>
                    </div>
                </div>

                <!-- Unit Price Comparison Analysis (including transport costs) -->
                @php
                    $yourUnitPrice = $product->PRICESELL * (1 + $product->getVatRate());
                    $udeaUnitPriceWithTransport = $unitPrice + $transportCostPerUnit;
                    $priceDifference = $yourUnitPrice - $udeaUnitPriceWithTransport;
                    $pricePercentageDiff = $udeaUnitPriceWithTransport > 0 ? (($priceDifference / $udeaUnitPriceWithTransport) * 100) : 0;
                @endphp

                @if($udeaUnitPriceWithTransport > 0)
                    <div class="mt-4 p-4 rounded-lg {{ $priceDifference > 0 ? 'bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-700' : ($priceDifference < 0 ? 'bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-700' : 'bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-600') }}">
                        <div class="flex items-center justify-between mb-2">
                            <div class="text-sm font-medium {{ $priceDifference > 0 ? 'text-green-800 dark:text-green-200' : ($priceDifference < 0 ? 'text-red-800 dark:text-red-200' : 'text-gray-800 dark:text-gray-200') }}">
                                Unit Price Comparison (incl. transport)
                            </div>
                            <div class="text-sm font-bold {{ $priceDifference > 0 ? 'text-green-800 dark:text-green-200' : ($priceDifference < 0 ? 'text-red-800 dark:text-red-200' : 'text-gray-800 dark:text-gray-200') }}">
                                @if($priceDifference > 0)
                                    +€{{ number_format($priceDifference, 2) }} ({{ number_format($pricePercentageDiff, 1) }}% higher)
                                @elseif($priceDifference < 0)
                                    -€{{ number_format(abs($priceDifference), 2) }} ({{ number_format(abs($pricePercentageDiff), 1) }}% lower)
                                @else
                                    Same price
                                @endif
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-4 text-xs">
                            <div>
                                <div class="text-gray-500 dark:text-gray-400">Your unit price (incl. VAT)</div>
                                <div class="font-medium text-gray-900 dark:text-gray-100">€{{ number_format($yourUnitPrice, 2) }}</div>
                            </div>
                            <div>
                                <div class="text-gray-500 dark:text-gray-400">Udea unit price (incl. transport)</div>
                                <div class="font-medium text-gray-900 dark:text-gray-100">€{{ number_format($udeaUnitPriceWithTransport, 2) }}</div>
                            </div>
                        </div>
                        <div class="text-xs {{ $priceDifference > 0 ? 'text-green-600 dark:text-green-400' : ($priceDifference < 0 ? 'text-red-600 dark:text-red-400' : 'text-gray-600 dark:text-gray-400') }} mt-2">
                            @if($priceDifference > 0)
                                ✓ Your pricing is competitive
                            @elseif($priceDifference < -0.10)
                                ⚠️ Udea is significantly cheaper - consider price review
                            @elseif($priceDifference < 0)
                                ⚠️ Udea is slightly cheaper
                            @else
                                ✓ Prices are well aligned
                            @endif
                        </div>
                    </div>
                @endif

                <!-- Discount Alert (if applicable) -->
                @if($udeaPricing['is_discounted'] ?? false)
                    <div class="mt-4 p-3 bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-700 rounded-lg">
                        <div class="flex">
                            <div class="flex-shrink-0">
                                <svg class="h-5 w-5 text-yellow-400" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                                </svg>
                            </div>
                            <div class="ml-3">
                                <p class="text-sm text-yellow-700 dark:text-yellow-200">
                                    <strong>Discount Alert:</strong> Udea has a temporary discount. Monitor this price as it may revert to €{{ $udeaPricing['original_price'] }} when the promotion ends.
                                </p>
                            </div>
                        </div>
                    </div>
                @endif

                <!-- Quick Actions -->
                <div class="mt-6 border-t border-gray-200 dark:border-gray-600 pt-4">
                    <div class="flex items-center justify-between mb-3">
                        <h5 class="text-sm font-medium text-gray-900 dark:text-gray-100">Quick Price Actions</h5>
                    </div>
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-2">
                        @php
                            // Use the already calculated values from above
                            $actualUdeaCost = $unitPrice; // This is the actual unit cost from Udea
                        @endphp
                        
                        <!-- Update Cost to Udea Cost -->
                        @if($actualUdeaCost > 0)
                            <button onclick="updateProductCost({{ $actualUdeaCost }})" 
                                    class="px-3 py-2 text-xs font-medium text-white bg-orange-600 hover:bg-orange-700 rounded-md transition-colors duration-200">
                                Update Cost<br>€{{ number_format($actualUdeaCost, 2) }}
                            </button>
                        @endif
                        
                        <!-- Match Udea with Margin -->
                        @if($unitPrice > 0)
                            @php $matchUdeaPrice = $unitPrice * 1.25; @endphp
                            <button onclick="updateProductPrice({{ $matchUdeaPrice }})" 
                                    class="px-3 py-2 text-xs font-medium text-white bg-blue-600 hover:bg-blue-700 rounded-md transition-colors duration-200">
                                Match Udea +25%<br>€{{ number_format($matchUdeaPrice, 2) }}
                            </button>
                        @endif
                        
                        <!-- Competitive Price (Udea + 10%) -->
                        @if($unitPrice > 0)
                            @php $competitivePrice = $unitPrice * 1.1; @endphp
                            <button onclick="updateProductPrice({{ $competitivePrice }})" 
                                    class="px-3 py-2 text-xs font-medium text-white bg-green-600 hover:bg-green-700 rounded-md transition-colors duration-200">
                                Udea +10%<br>€{{ number_format($competitivePrice, 2) }}
                            </button>
                        @endif
                        
                        <!-- Smart Optimal Price (Cost + Transport + Margin) -->
                        @if($actualUdeaCost > 0)
                            @php 
                                $totalCostWithTransport = $actualUdeaCost + $transportCostPerUnit;
                                $optimalPrice = $totalCostWithTransport * 1.35; // 35% margin on total cost
                            @endphp
                            <button onclick="updateProductPrice({{ $optimalPrice }})" 
                                    class="px-3 py-2 text-xs font-medium text-white bg-purple-600 hover:bg-purple-700 rounded-md transition-colors duration-200">
                                Optimal Price<br>€{{ number_format($optimalPrice, 2) }}
                            </button>
                        @endif
                    </div>
                    
                    <!-- Manual Edit Row -->
                    <div class="mt-3 flex gap-2">
                        <div class="flex-1">
                            <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Edit Cost</label>
                            <div class="flex">
                                <input type="number" step="0.01" min="0" 
                                       id="manualCost" value="{{ $product->PRICEBUY }}"
                                       class="flex-1 text-xs rounded-l-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-blue-500 dark:focus:border-blue-600 focus:ring-blue-500 dark:focus:ring-blue-600">
                                <button onclick="updateProductCost(document.getElementById('manualCost').value)" 
                                        class="px-2 py-1 text-xs font-medium text-white bg-orange-600 hover:bg-orange-700 rounded-r-md transition-colors duration-200">
                                    Update
                                </button>
                            </div>
                        </div>
                        <div class="flex-1">
                            <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Edit Selling Price</label>
                            <div class="flex">
                                <input type="number" step="0.01" min="0" 
                                       id="manualPrice" value="{{ $product->PRICESELL }}"
                                       class="flex-1 text-xs rounded-l-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-blue-500 dark:focus:border-blue-600 focus:ring-blue-500 dark:focus:ring-blue-600">
                                <button onclick="updateProductPrice(document.getElementById('manualPrice').value)" 
                                        class="px-2 py-1 text-xs font-medium text-white bg-blue-600 hover:bg-blue-700 rounded-r-md transition-colors duration-200">
                                    Update
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mt-4 text-xs text-gray-500 dark:text-gray-400">
                    <p>Last updated: {{ \Carbon\Carbon::parse($udeaPricing['scraped_at'])->format('d/m/Y H:i') }}</p>
                </div>
            </div>
        @elseif($product->supplierLink?->SupplierCode)
            <!-- Show loading or fetch button if no pricing data yet -->
            <div class="mt-6 border-t border-gray-200 dark:border-gray-600 pt-6">
                <div class="flex items-center justify-between mb-4">
                    <h4 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Live Price Comparison</h4>
                    <button onclick="refreshUdeaPricing()" 
                            id="refreshPricingBtn"
                            class="inline-flex items-center px-3 py-2 bg-blue-600 hover:bg-blue-700 text-white font-medium text-sm rounded-md transition-colors duration-200">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                        </svg>
                        Fetch Udea Prices
                    </button>
                </div>
                <div class="text-center py-8 text-gray-500 dark:text-gray-400">
                    <p>Click "Fetch Udea Prices" to load current pricing from Udea for supplier code: <strong>{{ $product->supplierLink->SupplierCode }}</strong></p>
                </div>
            </div>
        @endif

        <div class="mt-4 text-xs text-gray-500 dark:text-gray-400">
            <p>Product images and information provided by {{ $supplierService->getSupplierDisplayName($product->supplier->SupplierID) }}.</p>
        </div>
    </div>
@endif