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


            <!-- Products Needing Labels -->
            @if(count($productsNeedingLabels) > 0)
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg mb-8">
                    <div class="p-6">
                        <div class="flex justify-between items-center mb-4">
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Products Needing Labels</h3>
                            <div class="flex items-center gap-3">
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
                        
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                <thead class="bg-gray-50 dark:bg-gray-700">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Product</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Barcode</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Printed At</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                    @foreach($recentLabelPrints as $labelPrint)
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
                                                {{ $labelPrint->created_at->format('M j, Y H:i') }}
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
                                                            Add Back to Products Needing Labels
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
            @endif
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