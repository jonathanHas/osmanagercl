<x-admin-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <div>
                <h1 class="text-3xl font-bold text-gray-900 dark:text-gray-100">Label Area</h1>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                    Manage and print product labels
                </p>
            </div>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <x-alert type="success" :message="session('success')" />
            <x-alert type="error" :messages="$errors->all()" />

            <!-- Quick Stats -->
            @php
                $sheetsNeeded = $defaultTemplate 
                    ? ceil(count($productsNeedingLabels) / $defaultTemplate->labels_per_a4) 
                    : ceil(count($productsNeedingLabels) / 24);
            @endphp
            
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                <x-stat-card 
                    title="Products Needing Labels" 
                    :value="count($productsNeedingLabels)" 
                    icon="tag" 
                    color="blue" />
                    
                <x-stat-card 
                    title="Labels Printed" 
                    :value="count($recentLabelPrints)" 
                    subtitle="Last 7 days"
                    icon="check-circle" 
                    color="green" />
                    
                <x-stat-card 
                    title="A4 Sheets Needed" 
                    :value="$sheetsNeeded" 
                    icon="document" 
                    color="purple" 
                    id="sheets-needed" />
            </div>

            <!-- Label Template Selector -->
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg mb-6">
                <div class="p-6">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-4">Label Template</h3>
                    <div class="flex flex-wrap gap-4">
                        @foreach($labelTemplates as $template)
                            <label class="flex items-center cursor-pointer">
                                <input type="radio" 
                                       name="selected_template" 
                                       value="{{ $template->id }}" 
                                       class="sr-only template-radio" 
                                       @if($template->is_default) checked @endif
                                       data-labels-per-a4="{{ $template->labels_per_a4 }}"
                                       data-width="{{ $template->width_mm }}"
                                       data-height="{{ $template->height_mm }}">
                                <div class="template-card border-2 rounded-lg p-3 transition-colors hover:bg-gray-50 dark:hover:bg-gray-700" 
                                     data-template="{{ $template->id }}">
                                    <div class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ $template->name }}</div>
                                    <div class="text-xs text-gray-500 dark:text-gray-400">{{ $template->description }}</div>
                                    <div class="text-xs text-gray-600 dark:text-gray-300 mt-1">
                                        {{ $template->labels_per_a4 }} labels per A4
                                    </div>
                                </div>
                            </label>
                        @endforeach
                    </div>
                </div>
            </div>

            <!-- Action Buttons Section (Always Visible) -->
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg mb-6">
                <div class="p-6">
                    <div class="flex justify-between items-center">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Quick Actions</h3>
                        <div class="flex items-center gap-3">
                            <!-- Scan to Label Button -->
                            <button onclick="openScannerModal()" 
                                    class="inline-flex items-center px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white font-semibold text-xs uppercase tracking-widest rounded-md transition">
                                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h2m0 0V6a3 3 0 00-3-3H9a3 3 0 00-3 3v9.5M12 8h0m-6.5 4.5H4"/>
                                </svg>
                                Scan to Label
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Products Needing Labels -->
            @if(count($productsNeedingLabels) > 0)
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg mb-8">
                    <div class="p-6">
                        <div class="flex justify-between items-center mb-4">
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Products Needing Labels</h3>
                            <div class="flex items-center gap-3">
                                <!-- Clear Labels Button -->
                                <button onclick="clearAllLabels()" 
                                        class="inline-flex items-center px-4 py-2 bg-orange-600 hover:bg-orange-700 text-white font-semibold text-xs uppercase tracking-widest rounded-md transition">
                                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                    </svg>
                                    Clear All (<span id="clear-products-count">{{ count($productsNeedingLabels) }}</span>)
                                </button>
                                
                                <!-- Preview Button -->
                                <button onclick="previewAllLabels()" 
                                        class="inline-flex items-center px-4 py-2 bg-gray-600 hover:bg-gray-700 text-white font-semibold text-xs uppercase tracking-widest rounded-md transition">
                                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                    </svg>
                                    Preview All
                                </button>
                                
                                <!-- Print Button -->
                                <form method="POST" action="{{ route('labels.print-a4') }}" target="_blank" id="print-all-form">
                                    @csrf
                                    <!-- Dynamic product inputs will be added by JavaScript -->
                                    <input type="hidden" name="template_id" id="selected-template-input" value="{{ $defaultTemplate?->id }}">
                                    <button type="button" onclick="printAllProducts()" 
                                            class="inline-flex items-center px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white font-semibold text-xs uppercase tracking-widest rounded-md transition">
                                        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
                                        </svg>
                                        Print All (<span id="products-count">{{ count($productsNeedingLabels) }}</span> labels)
                                    </button>
                                </form>
                            </div>
                        </div>
                        
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                <thead class="bg-gray-50 dark:bg-gray-700">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Product</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Barcode</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Price</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Preview</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                    @foreach($productsNeedingLabels as $product)
                                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700 product-row" data-product-id="{{ $product->ID }}">
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ $product->NAME }}</div>
                                                <div class="text-sm text-gray-500 dark:text-gray-400">{{ $product->getCategoryNameAttribute() }}</div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-mono text-gray-900 dark:text-gray-100">
                                                {{ $product->CODE }}
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">
                                                <div class="font-semibold">{{ $product->getFormattedPriceWithVatAttribute() }}</div>
                                                <div class="text-xs text-gray-500 dark:text-gray-400">€{{ number_format($product->PRICESELL, 2) }} net</div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <button onclick="previewLabel('{{ $product->ID }}')" 
                                                        class="inline-flex items-center px-2 py-1 text-xs bg-gray-100 hover:bg-gray-200 dark:bg-gray-700 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-300 rounded transition preview-btn">
                                                    <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                                    </svg>
                                                    Preview
                                                </button>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm">
                                                <div class="flex items-center space-x-2">
                                                    <a href="{{ route('products.print-label', $product->ID) }}" 
                                                       target="_blank"
                                                       class="inline-flex items-center px-2 py-1 text-xs bg-indigo-100 hover:bg-indigo-200 dark:bg-indigo-900 dark:hover:bg-indigo-800 text-indigo-700 dark:text-indigo-300 rounded transition">
                                                        <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
                                                        </svg>
                                                        Single
                                                    </a>
                                                    <a href="{{ route('products.show', $product->ID) }}" 
                                                       class="inline-flex items-center px-2 py-1 text-xs bg-gray-100 hover:bg-gray-200 dark:bg-gray-700 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-300 rounded transition">
                                                        <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                                        </svg>
                                                        View
                                                    </a>
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            @else
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg mb-8">
                    <div class="p-6 text-center">
                        <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z" />
                        </svg>
                        <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-gray-100">All caught up!</h3>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">No products currently need labels.</p>
                    </div>
                </div>
            @endif

            <!-- Recent Label Prints -->
            @if(count($recentLabelPrints) > 0)
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-4">Recent Label Prints</h3>
                        
                        <!-- Grouped Label Print Sessions -->
                        <div class="space-y-4">
                            @foreach($groupedLabelPrints as $groupKey => $group)
                                <div class="border border-gray-200 dark:border-gray-700 rounded-lg">
                                    <!-- Group Header -->
                                    <div class="bg-gray-50 dark:bg-gray-700 px-4 py-3 flex items-center justify-between cursor-pointer" 
                                         onclick="toggleGroup('group-{{ $loop->index }}')">
                                        <div class="flex items-center space-x-3">
                                            <svg class="w-5 h-5 text-gray-400 transform transition-transform group-chevron" id="chevron-{{ $loop->index }}" 
                                                 fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                            </svg>
                                            <div>
                                                <div class="text-sm font-medium text-gray-900 dark:text-gray-100">
                                                    {{ $group['display_time'] }}
                                                </div>
                                                <div class="text-xs text-gray-500 dark:text-gray-400">
                                                    {{ $group['count'] }} {{ Str::plural('product', $group['count']) }} printed
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <!-- Batch Actions -->
                                        <div class="flex items-center space-x-2">
                                            <button onclick="event.stopPropagation(); restoreBatch('{{ $group['display_time'] }}', '{{ $groupKey }}', {{ $loop->index }})"
                                                    class="inline-flex items-center px-3 py-1 text-xs bg-green-100 hover:bg-green-200 dark:bg-green-900 dark:hover:bg-green-800 text-green-700 dark:text-green-300 rounded transition">
                                                <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                                                </svg>
                                                Restore All ({{ $group['count'] }})
                                            </button>
                                        </div>
                                    </div>
                                    
                                    <!-- Group Content (Initially Hidden) -->
                                    <div class="hidden" id="group-{{ $loop->index }}">
                                        <div class="overflow-x-auto">
                                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                                <thead class="bg-gray-50 dark:bg-gray-700">
                                                    <tr>
                                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Product</th>
                                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Barcode</th>
                                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Exact Time</th>
                                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Actions</th>
                                                    </tr>
                                                </thead>
                                                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                                    @foreach($group['products'] as $labelPrint)
                                                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                                            <td class="px-6 py-4 whitespace-nowrap">
                                                                @if($labelPrint->product)
                                                                    <div class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ $labelPrint->product->NAME }}</div>
                                                                @else
                                                                    <div class="text-sm text-gray-500 dark:text-gray-400 italic">Product not found</div>
                                                                @endif
                                                            </td>
                                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-mono text-gray-900 dark:text-gray-100">
                                                                {{ $labelPrint->barcode }}
                                                            </td>
                                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                                                {{ $labelPrint->created_at->format('H:i:s') }}
                                                                <div class="text-xs">{{ $labelPrint->created_at->diffForHumans() }}</div>
                                                            </td>
                                                            <td class="px-6 py-4 whitespace-nowrap text-sm">
                                                                @if($labelPrint->product)
                                                                    <div class="flex items-center space-x-2">
                                                                        <button onclick="requeueProduct('{{ $labelPrint->product->ID }}', this)" 
                                                                                class="inline-flex items-center px-2 py-1 text-xs bg-green-100 hover:bg-green-200 dark:bg-green-900 dark:hover:bg-green-800 text-green-700 dark:text-green-300 rounded transition">
                                                                            <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                                                                            </svg>
                                                                            Add Back
                                                                        </button>
                                                                        <a href="{{ route('products.print-label', $labelPrint->product->ID) }}" 
                                                                           target="_blank"
                                                                           class="inline-flex items-center px-2 py-1 text-xs bg-indigo-100 hover:bg-indigo-200 dark:bg-indigo-900 dark:hover:bg-indigo-800 text-indigo-700 dark:text-indigo-300 rounded transition">
                                                                            <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
                                                                            </svg>
                                                                            Reprint
                                                                        </a>
                                                                    </div>
                                                                @endif
                                                            </td>
                                                        </tr>
                                                    @endforeach
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </div>

    <!-- Scanner Modal -->
    <div id="scanner-modal" class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden" x-data="labelScanner()" x-show="modalOpen" @keydown.escape="closeModal()">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-xl max-w-md w-full max-h-screen overflow-y-auto" @click.away="closeModal()">
                <div class="p-6">
                    <!-- Header -->
                    <div class="flex justify-between items-center mb-6">
                        <div class="flex items-center gap-3">
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Scan to Label</h3>
                            <div class="flex items-center gap-2">
                                <div class="flex items-center justify-center w-8 h-8 bg-blue-100 dark:bg-blue-900/30 text-blue-600 dark:text-blue-400 rounded-full font-bold text-sm">
                                    <span x-text="currentQueueCount"></span>
                                </div>
                                <span class="text-sm text-gray-500 dark:text-gray-400">in queue</span>
                            </div>
                        </div>
                        <button @click="closeModal()" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>

                    <!-- Scanner Interface -->
                    <div class="space-y-4">
                        <!-- Barcode Input -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                Scan Barcode
                            </label>
                            <input type="text" 
                                   x-model="barcode"
                                   x-ref="barcodeInput"
                                   @keydown.enter="processBarcode()"
                                   placeholder="Ready for barcode scan..."
                                   inputmode="none"
                                   autocomplete="off"
                                   class="w-full text-lg py-3 px-4 rounded-lg border-2 border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-blue-500 focus:ring-blue-500">
                        </div>

                        <!-- Product Preview -->
                        <div x-show="scannedProduct" x-transition class="bg-gray-50 dark:bg-gray-700 rounded-lg p-4">
                            <div class="flex items-center justify-between">
                                <div class="flex-1">
                                    <div class="font-medium text-gray-900 dark:text-gray-100" x-text="scannedProduct?.name || 'Unknown Product'"></div>
                                    <div class="text-sm text-gray-600 dark:text-gray-400">
                                        <span x-text="scannedProduct?.code"></span>
                                    </div>
                                </div>
                                <div x-show="scannedProduct?.price" class="text-right">
                                    <div class="font-semibold text-gray-900 dark:text-gray-100" x-text="scannedProduct?.formatted_price"></div>
                                </div>
                            </div>
                        </div>

                        <!-- Feedback Messages -->
                        <div x-show="lastScan" x-transition class="p-3 rounded-lg" 
                             :class="lastScan?.success ? 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400' : 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400'">
                            <div class="flex items-center">
                                <svg x-show="lastScan?.success" class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                </svg>
                                <svg x-show="!lastScan?.success" class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                </svg>
                                <span x-text="lastScan?.message"></span>
                            </div>
                        </div>

                        <!-- Action Buttons -->
                        <div class="flex gap-3 pt-4">
                            <button @click="processBarcode()" 
                                    :disabled="!barcode || processing"
                                    :class="(!barcode || processing) ? 'bg-gray-400 cursor-not-allowed' : 'bg-blue-600 hover:bg-blue-700'"
                                    class="flex-1 py-2 text-white rounded-md transition-colors font-medium">
                                <span x-show="!processing">Add to Labels</span>
                                <span x-show="processing">Processing...</span>
                            </button>
                            <button @click="closeModal()" 
                                    class="px-4 py-2 bg-gray-600 hover:bg-gray-700 text-white rounded-md transition-colors font-medium">
                                Done
                            </button>
                        </div>

                        <!-- Stats -->
                        <div class="text-center border-t pt-4">
                            <div class="flex items-center justify-center gap-2 text-sm text-gray-600 dark:text-gray-400">
                                <div class="flex items-center justify-center w-7 h-7 bg-blue-100 dark:bg-blue-900/30 text-blue-600 dark:text-blue-400 rounded-full font-bold text-xs">
                                    <span x-text="currentQueueCount"></span>
                                </div>
                                <span>in labels queue</span>
                            </div>
                            <div x-show="scansCount > 0" class="mt-2 flex items-center justify-center gap-1 text-sm text-green-600 dark:text-green-400">
                                <div class="flex items-center justify-center w-6 h-6 bg-green-100 dark:bg-green-900/30 text-green-600 dark:text-green-400 rounded-full font-bold text-xs">
                                    +<span x-text="scansCount"></span>
                                </div>
                                <span>added this session</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
    <script>
        let selectedTemplateId = {{ $defaultTemplate?->id ?? 'null' }};
        const totalProducts = {{ count($productsNeedingLabels) }};

        document.addEventListener('DOMContentLoaded', function() {
            // Handle template selection
            const templateRadios = document.querySelectorAll('.template-radio');
            const templateCards = document.querySelectorAll('.template-card');
            const sheetsNeeded = document.getElementById('sheets-needed');
            const selectedTemplateInput = document.getElementById('selected-template-input');

            // Update visual states
            function updateTemplateSelection() {
                templateCards.forEach(card => {
                    const radio = card.parentElement.querySelector('.template-radio');
                    if (radio.checked) {
                        card.classList.add('border-indigo-500', 'bg-indigo-50', 'dark:bg-indigo-900/20');
                        card.classList.remove('border-gray-300', 'dark:border-gray-600');
                        selectedTemplateId = radio.value;
                        
                        // Update selected template input if it exists
                        if (selectedTemplateInput) {
                            selectedTemplateInput.value = selectedTemplateId;
                        }
                        
                        // Update A4 sheets calculation if elements exist
                        if (sheetsNeeded && totalProducts > 0) {
                            const labelsPerA4 = parseInt(radio.dataset.labelsPerA4);
                            sheetsNeeded.textContent = Math.ceil(totalProducts / labelsPerA4);
                        }
                    } else {
                        card.classList.remove('border-indigo-500', 'bg-indigo-50', 'dark:bg-indigo-900/20');
                        card.classList.add('border-gray-300', 'dark:border-gray-600');
                    }
                });
            }

            // Only add event listeners if template elements exist
            if (templateCards.length > 0) {
                // Handle template card clicks
                templateCards.forEach(card => {
                    card.addEventListener('click', function() {
                        const radio = this.parentElement.querySelector('.template-radio');
                        radio.checked = true;
                        updateTemplateSelection();
                    });
                });

                // Handle radio change
                templateRadios.forEach(radio => {
                    radio.addEventListener('change', updateTemplateSelection);
                });

                // Initial state
                updateTemplateSelection();
            }
        });

        function previewLabel(productId) {
            const templateParam = selectedTemplateId ? `?template_id=${selectedTemplateId}` : '';
            window.open(`/labels/preview/${productId}${templateParam}`, '_blank', 'width=400,height=300,scrollbars=yes');
        }


        // Get current product IDs from the visible table
        function getCurrentProductIds() {
            const productRows = document.querySelectorAll('.product-row');
            const productIds = [];
            
            productRows.forEach(row => {
                const productId = row.getAttribute('data-product-id');
                if (productId) {
                    productIds.push(productId);
                }
            });
            
            return productIds;
        }

        // Update products count in button
        function updateProductsCount() {
            const count = getCurrentProductIds().length;
            const countSpan = document.getElementById('products-count');
            if (countSpan) {
                countSpan.textContent = count;
            }
            
            // Also update clear button count
            const clearCountSpan = document.getElementById('clear-products-count');
            if (clearCountSpan) {
                clearCountSpan.textContent = count;
            }
        }

        // Print all products (dynamic)
        function printAllProducts() {
            const form = document.getElementById('print-all-form');
            const productIds = getCurrentProductIds();
            
            if (productIds.length === 0) {
                alert('No products available for printing.');
                return;
            }
            
            // Clear existing product inputs
            const existingInputs = form.querySelectorAll('input[name="products[]"]');
            existingInputs.forEach(input => input.remove());
            
            // Add current product IDs as hidden inputs
            productIds.forEach(productId => {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'products[]';
                input.value = productId;
                form.appendChild(input);
            });
            
            // Submit the form
            form.submit();
        }

        // Preview all products (dynamic)
        function previewAllLabels() {
            const productIds = getCurrentProductIds();
            
            if (productIds.length === 0) {
                alert('No products available for preview.');
                return;
            }
            
            // Build query string for GET request
            const params = new URLSearchParams();
            const templateId = selectedTemplateId || {{ $defaultTemplate?->id ?? 'null' }};
            
            productIds.forEach(productId => {
                params.append('products[]', productId);
            });
            
            if (templateId) {
                params.append('template_id', templateId);
            }
            
            // Open preview in new window
            window.open(`/labels/preview-a4?${params.toString()}`, '_blank', 'width=1200,height=800,scrollbars=yes');
        }

        // Toggle group visibility
        function toggleGroup(groupId) {
            const groupElement = document.getElementById(groupId);
            const chevronElement = document.getElementById('chevron-' + groupId.replace('group-', ''));
            
            if (groupElement.classList.contains('hidden')) {
                groupElement.classList.remove('hidden');
                chevronElement.style.transform = 'rotate(90deg)';
            } else {
                groupElement.classList.add('hidden');
                chevronElement.style.transform = 'rotate(0deg)';
            }
        }

        // Restore batch of products
        function restoreBatch(displayTime, groupKey, groupIndex) {
            // Get all product IDs from the group
            const groupElement = document.getElementById('group-' + groupIndex);
            const productRows = groupElement.querySelectorAll('tbody tr');
            const productIds = [];
            
            productRows.forEach(row => {
                const addBackButton = row.querySelector('button[onclick*="requeueProduct"]');
                if (addBackButton) {
                    const onclickAttr = addBackButton.getAttribute('onclick');
                    const productIdMatch = onclickAttr.match(/requeueProduct\('([^']+)'/);
                    if (productIdMatch) {
                        productIds.push(productIdMatch[1]);
                    }
                }
            });
            
            if (productIds.length === 0) {
                alert('No products found in this group to restore.');
                return;
            }
            
            // Show confirmation dialog
            const confirmed = confirm(`Are you sure you want to restore ${productIds.length} products from ${displayTime} session?\n\nThis will move all products back to "Products Needing Labels".`);
            
            if (!confirmed) {
                return;
            }
            
            // Disable the restore button while processing
            const restoreButton = document.querySelector(`button[onclick*="restoreBatch('${displayTime}'"]`);
            if (restoreButton) {
                restoreButton.disabled = true;
                restoreButton.innerHTML = '<svg class="animate-spin -ml-1 mr-3 h-3 w-3 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>Restoring...';
            }
            
            fetch('/labels/restore-batch', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                },
                body: JSON.stringify({
                    timestamp: displayTime,
                    product_ids: productIds
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Show success message
                    alert(`Successfully restored ${data.restored_count} products from ${displayTime} session!`);
                    // Reload page to show updated tables
                    location.reload();
                } else {
                    alert('Error: ' + (data.error || data.message));
                    // Re-enable button on error
                    if (restoreButton) {
                        restoreButton.disabled = false;
                        restoreButton.innerHTML = '<svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>Restore All (' + productIds.length + ')';
                    }
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error restoring products: ' + error.message);
                // Re-enable button on error
                if (restoreButton) {
                    restoreButton.disabled = false;
                    restoreButton.innerHTML = '<svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>Restore All (' + productIds.length + ')';
                }
            });
        }

        // Clear all labels function
        function clearAllLabels() {
            const productCount = getCurrentProductIds().length;
            
            if (productCount === 0) {
                alert('No products are currently needing labels.');
                return;
            }
            
            // Show confirmation dialog
            const confirmed = confirm(`Are you sure you want to clear ${productCount} products from the labels queue?\n\nThis will move all products from "Products Needing Labels" to "Recent Label Prints".`);
            
            if (!confirmed) {
                return;
            }
            
            // Disable the button while processing
            const clearButton = document.querySelector('button[onclick="clearAllLabels()"]');
            if (clearButton) {
                clearButton.disabled = true;
                clearButton.innerHTML = '<svg class="animate-spin -ml-1 mr-3 h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>Clearing...';
            }
            
            fetch('/labels/clear-all', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Show success message
                    alert(`Successfully cleared ${data.cleared_count} products from labels queue!`);
                    // Reload page to show updated tables
                    location.reload();
                } else {
                    alert('Error: ' + (data.error || data.message));
                    // Re-enable button on error
                    if (clearButton) {
                        clearButton.disabled = false;
                        clearButton.innerHTML = '<svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>Clear All (' + productCount + ')';
                    }
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error clearing labels: ' + error.message);
                // Re-enable button on error
                if (clearButton) {
                    clearButton.disabled = false;
                    clearButton.innerHTML = '<svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>Clear All (' + productCount + ')';
                }
            });
        }

        // Scanner Modal Functions
        function openScannerModal() {
            // Set a global flag that the Alpine component can check
            window.openLabelScanner = true;
            // Remove hidden class to show modal
            const modal = document.getElementById('scanner-modal');
            modal.classList.remove('hidden');
            
            // Trigger Alpine to open and focus
            setTimeout(() => {
                const alpineData = Alpine.$data(modal);
                if (alpineData) {
                    alpineData.openModal();
                }
                
                // Additional focus attempt
                setTimeout(() => {
                    const input = modal.querySelector('input[x-ref="barcodeInput"]');
                    if (input) {
                        input.focus();
                        input.select();
                    }
                }, 200);
            }, 50);
        }

        function labelScanner() {
            return {
                modalOpen: false,
                barcode: '',
                scannedProduct: null,
                lastScan: null,
                processing: false,
                scansCount: 0,
                currentQueueCount: {{ count($productsNeedingLabels) }},

                init() {
                    // Check if we should open immediately
                    if (window.openLabelScanner) {
                        this.modalOpen = true;
                        window.openLabelScanner = false;
                        this.focusInput();
                    }
                    
                    // Initialize when modal opens
                    this.$watch('modalOpen', (value) => {
                        if (value) {
                            this.focusInput();
                        }
                    });
                },

                focusInput() {
                    // Multiple attempts with different timing to ensure focus
                    this.$nextTick(() => {
                        this.$refs.barcodeInput?.focus();
                        this.$refs.barcodeInput?.select();
                        
                        setTimeout(() => {
                            this.$refs.barcodeInput?.focus();
                            this.$refs.barcodeInput?.select();
                        }, 100);
                        
                        setTimeout(() => {
                            this.$refs.barcodeInput?.focus();
                            this.$refs.barcodeInput?.select();
                        }, 300);
                    });
                },

                openModal() {
                    this.modalOpen = true;
                    this.barcode = '';
                    this.scannedProduct = null;
                    this.lastScan = null;
                    // Reset the current queue count to the latest value
                    this.currentQueueCount = {{ count($productsNeedingLabels) }};
                    this.focusInput();
                },

                closeModal() {
                    this.modalOpen = false;
                    this.barcode = '';
                    this.scannedProduct = null;
                    this.lastScan = null;
                    
                    // Hide the modal div
                    document.getElementById('scanner-modal').classList.add('hidden');
                    
                    // Refresh page if products were added to show updated queue
                    if (this.scansCount > 0) {
                        location.reload();
                    }
                },

                async processBarcode() {
                    if (!this.barcode || this.processing) return;
                    
                    this.processing = true;
                    this.lastScan = null;

                    try {
                        // First, lookup product details
                        const lookupResponse = await fetch('/labels/lookup-barcode', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                            },
                            body: JSON.stringify({ barcode: this.barcode })
                        });

                        if (!lookupResponse.ok) {
                            throw new Error('Network error');
                        }

                        const lookupData = await lookupResponse.json();

                        if (lookupData.success && lookupData.product) {
                            this.scannedProduct = lookupData.product;
                            
                            // Add to labels queue
                            const addResponse = await fetch('/labels/scan', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'Accept': 'application/json',
                                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                                },
                                body: JSON.stringify({ barcode: this.barcode })
                            });

                            const addData = await addResponse.json();
                            
                            if (addData.success) {
                                this.lastScan = {
                                    success: true,
                                    message: 'Product added to labels queue!'
                                };
                                this.scansCount++;
                                this.currentQueueCount++;
                            } else {
                                this.lastScan = {
                                    success: false,
                                    message: addData.message || 'Failed to add product to labels'
                                };
                            }
                        } else {
                            this.scannedProduct = null;
                            this.lastScan = {
                                success: false,
                                message: lookupData.message || 'Product not found'
                            };
                        }

                        // Clear barcode and refocus for next scan
                        this.barcode = '';
                        this.focusInput();

                    } catch (error) {
                        this.lastScan = {
                            success: false,
                            message: 'Network error - please try again'
                        };
                        console.error('Scanner error:', error);
                    } finally {
                        this.processing = false;
                    }
                }
            };
        }

        // Requeue product function
        function requeueProduct(productId, buttonElement) {
            const button = buttonElement || event?.target?.closest('button');
            
            fetch('/labels/requeue', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                },
                body: JSON.stringify({ product_id: productId })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Show success message briefly
                    if (button) {
                        const originalText = button.innerHTML;
                        button.innerHTML = '<svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>Added Back!';
                        button.disabled = true;
                        
                        setTimeout(() => {
                            location.reload(); // Refresh to show product in Products Needing Labels
                        }, 1500);
                    } else {
                        // If no button reference, just show success and reload
                        alert('Product added back to Products Needing Labels!');
                        location.reload();
                    }
                } else {
                    alert('Error: ' + (data.message || data.error));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error adding product back to labels list');
            });
        }
    </script>
    @endpush
</x-admin-layout>