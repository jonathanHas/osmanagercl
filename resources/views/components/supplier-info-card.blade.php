@props(['product', 'supplierService'])

@if($product->supplier && $supplierService->hasExternalIntegration($product->supplier->SupplierID))
    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
        <div class="p-6">
            <h3 class="text-lg font-semibold mb-4 flex items-center">
                <svg class="w-5 h-5 mr-2 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>
                </svg>
                Supplier Information
            </h3>
            
            <div class="flex flex-col md:flex-row gap-6">
                <!-- Product Image -->
                <div class="flex-shrink-0">
                    <div class="relative">
                        <img 
                            src="{{ $supplierService->getExternalImageUrl($product) }}" 
                            alt="{{ $product->NAME }}"
                            class="w-32 h-32 object-cover rounded-lg border border-gray-200 dark:border-gray-600 shadow-sm"
                            loading="lazy"
                            onerror="this.onerror=null; this.src='data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMjAwIiBoZWlnaHQ9IjIwMCIgdmlld0JveD0iMCAwIDIwMCAyMDAiIGZpbGw9Im5vbmUiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+CjxyZWN0IHdpZHRoPSIyMDAiIGhlaWdodD0iMjAwIiBmaWxsPSIjRTVFN0VCIi8+CjxwYXRoIGQ9Ik04NSA3MEM4NSA2MS43MTU3IDkxLjcxNTcgNTUgMTAwIDU1QzEwOC4yODQgNTUgMTE1IDYxLjcxNTcgMTE1IDcwQzExNSA3OC4yODQzIDEwOC4yODQgODUgMTAwIDg1Qzk1LjAyOTQgODUgOTAuNzY4NyA4Mi4zNjYxIDg4LjY3NjQgNzguMzUyOEw3NSAxMjBIMTI1TDExMS4zMjQgNzguMzUyOEMxMDkuMjMxIDgyLjM2NjEgMTA0Ljk3MSA4NSAxMDAgODVDOTEuNzE1NyA4NSA4NSA3OC4yODQzIDg1IDcwWiIgZmlsbD0iIzlDQTNCOCIvPgo8cGF0aCBkPSJNNzAgMTMwSDEzMFYxNDBINzBWMTMwWiIgZmlsbD0iIzlDQTNCOCIvPgo8L3N2Zz4=';"
                        >
                    </div>
                </div>
                
                <!-- Supplier Details -->
                <div class="flex-1">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Supplier</dt>
                            <dd class="mt-1 text-base font-semibold text-gray-900 dark:text-gray-100">
                                {{ $product->supplier->Supplier }}
                            </dd>
                        </div>
                        
                        @if($product->supplierLink)
                            <div>
                                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Supplier Code</dt>
                                <dd class="mt-1 text-base text-gray-900 dark:text-gray-100 font-mono">
                                    {{ $product->supplierLink->SupplierCode }}
                                </dd>
                            </div>
                            
                            @if($product->supplierLink->Cost > 0)
                                <div>
                                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Listed Cost</dt>
                                    <dd class="mt-1 text-base text-gray-900 dark:text-gray-100">
                                        €{{ number_format($product->supplierLink->Cost, 2) }}
                                    </dd>
                                </div>
                            @endif
                            
                            @if($product->supplierLink->CaseUnits > 0)
                                <div>
                                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Case Units</dt>
                                    <dd class="mt-1 text-base text-gray-900 dark:text-gray-100">
                                        {{ $product->supplierLink->CaseUnits }} units/case
                                    </dd>
                                </div>
                            @endif
                        @endif
                    </div>
                    
                    <!-- External Link -->
                    @if($link = $supplierService->getSupplierWebsiteLink($product))
                        <div class="mt-4">
                            <a href="{{ $link }}" 
                               target="_blank" 
                               rel="noopener noreferrer" 
                               class="inline-flex items-center px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white font-medium text-sm rounded-md transition-colors duration-200">
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path>
                                </svg>
                                View on {{ $supplierService->getSupplierDisplayName($product->supplier->SupplierID) }}
                            </a>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
@endif