<x-admin-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                Edit Product: {{ $product->NAME }}
            </h2>
            @php
                $headerActions = [];

                // Requeue for Labels button
                $headerActions[] = [
                    'type' => 'button',
                    'onclick' => "requeueProduct('{$product->ID}', this)",
                    'label' => 'Requeue Label',
                    'color' => 'green',
                    'class' => 'inline-flex items-center px-4 py-2 bg-green-600 hover:bg-green-700 text-white font-medium rounded-md transition-colors duration-200',
                    'icon' => 'M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15'
                ];

                // Print Label link
                $headerActions[] = [
                    'type' => 'link',
                    'route' => 'products.print-label',
                    'params' => $product->ID,
                    'label' => 'Print Label',
                    'color' => 'indigo',
                    'class' => 'inline-flex items-center px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white font-medium rounded-md transition-colors duration-200',
                    'icon' => 'M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z',
                    'target' => '_blank'
                ];

                // Back navigation
                $backAction = [
                    'type' => 'link',
                    'color' => 'secondary',
                    'class' => 'inline-flex items-center px-4 py-2 bg-gray-600 hover:bg-gray-700 text-white font-medium rounded-md transition-colors duration-200',
                    'icon' => 'M10 19l-7-7m0 0l7-7m-7 7h18'
                ];

                if ($fromDelivery) {
                    $backAction['route'] = 'deliveries.show';
                    $backAction['params'] = ['delivery' => $fromDelivery];
                    $backAction['label'] = 'Back to Delivery';
                } elseif ($fromContext === 'coffee') {
                    $backAction['route'] = 'coffee.products';
                    $backAction['label'] = 'Back to Coffee Products';
                } else {
                    $backAction['route'] = 'products.index';
                    $backAction['label'] = 'Back to Products';
                }
                $headerActions[] = $backAction;
            @endphp
            <x-action-buttons :actions="$headerActions" spacing="tight" size="lg" />
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <x-alert type="error" :messages="$errors->all()" />
            <x-alert type="success" :message="session('success')" />

            <!-- Quick Stats Bar -->
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg mb-6">
                <div class="p-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                        <!-- Stock Status -->
                        <div>
                            <div class="flex items-center gap-2">
                                <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400 mb-1">Stock Status</h3>
                                @if(!$product->isService())
                                    <button type="button"
                                            onclick="toggleStockEdit()"
                                            class="text-indigo-600 hover:text-indigo-700 dark:text-indigo-400 dark:hover:text-indigo-300 transition-colors"
                                            title="Edit Stock">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/>
                                        </svg>
                                    </button>
                                @endif
                            </div>
                            @if($product->isService())
                                <p class="text-lg font-semibold text-gray-500">Service Item</p>
                            @else
                                <div id="stockDisplay">
                                    <p class="text-2xl font-bold {{ $product->getCurrentStock() > 0 ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400' }}">
                                        <span id="currentStockValue">{{ number_format($product->getCurrentStock(), 2) }}</span>
                                    </p>
                                    @if($product->getCurrentStock() > 0 && $product->getCurrentStock() < 10)
                                        <p class="text-xs text-yellow-600 dark:text-yellow-400">Low Stock</p>
                                    @endif
                                </div>

                                <!-- Stock Edit Form (hidden by default) -->
                                <form id="stockEditForm" class="hidden mt-2" onsubmit="updateStock(event)">
                                    @csrf
                                    <div class="flex items-center gap-2">
                                        <input type="number"
                                               name="stock_units"
                                               id="stockUnitsInput"
                                               value="{{ $product->getCurrentStock() }}"
                                               step="0.01"
                                               min="0"
                                               max="9999.99"
                                               class="w-20 text-sm bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded px-2 py-1 text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                                               required>
                                        <button type="submit"
                                                class="inline-flex items-center px-2 py-1 bg-green-600 hover:bg-green-700 text-white text-xs font-medium rounded transition-colors">
                                            Save
                                        </button>
                                        <button type="button"
                                                onclick="toggleStockEdit()"
                                                class="inline-flex items-center px-2 py-1 bg-gray-500 hover:bg-gray-600 text-white text-xs font-medium rounded transition-colors">
                                            Cancel
                                        </button>
                                    </div>
                                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Update current stock level</p>
                                </form>
                            @endif
                        </div>

                        <!-- VAT Rate -->
                        <div>
                            <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400 mb-1">VAT Rate</h3>
                            <span class="inline-flex items-center px-3 py-1 text-lg font-semibold rounded-full {{ $product->tax_category_badge_class }}">
                                {{ $product->formatted_vat_rate }}
                            </span>
                        </div>

                        <!-- Stocking Status -->
                        <div>
                            <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400 mb-1">Stock Management</h3>
                            <div class="flex items-center space-x-3">
                                @if($product->stocking)
                                    <span class="inline-flex items-center px-3 py-1 text-lg font-semibold rounded-full bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">
                                        <svg class="w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                        </svg>
                                        Stocked
                                    </span>
                                @else
                                    <span class="inline-flex items-center px-3 py-1 text-lg font-semibold rounded-full bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200">
                                        <svg class="w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                                        </svg>
                                        Not Stocked
                                    </span>
                                @endif
                                <button type="button"
                                        onclick="toggleStocking('{{ $product->ID }}', {{ $product->stocking ? 'false' : 'true' }})"
                                        class="inline-flex items-center px-2 py-1 text-xs font-medium rounded border {{ $product->stocking ? 'border-red-300 text-red-700 hover:bg-red-50 dark:border-red-600 dark:text-red-400 dark:hover:bg-red-900/20' : 'border-green-300 text-green-700 hover:bg-green-50 dark:border-green-600 dark:text-green-400 dark:hover:bg-green-900/20' }} transition-colors duration-200">
                                    {{ $product->stocking ? 'Remove' : 'Add' }}
                                </button>
                            </div>
                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                {{ $product->stocking ? 'Included in ordering operations' : 'Excluded from automated ordering' }}
                            </p>
                        </div>

                        <!-- Minimum Stock Override (Admin/Manager Only) -->
                        @if(auth()->user()->hasAnyRole(['admin', 'manager']))
                            <div x-data="{
                                editing: false,
                                value: '{{ $orderSettings?->min_stock_override ?? '' }}',
                                originalValue: '{{ $orderSettings?->min_stock_override ?? '' }}',
                                saving: false,
                                error: null
                            }">
                                <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400 mb-1">Minimum Stock Override</h3>
                                <div class="flex items-center space-x-3">
                                    <!-- Display Mode -->
                                    <div x-show="!editing" class="flex items-center space-x-2">
                                        @if($orderSettings?->min_stock_override)
                                            <span class="inline-flex items-center px-3 py-1 text-lg font-semibold rounded-full bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200">
                                                <svg class="w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-11a1 1 0 10-2 0v3.586L7.707 9.293a1 1 0 00-1.414 1.414l3 3a1 1 0 001.414 0l3-3a1 1 0 00-1.414-1.414L11 10.586V7z" clip-rule="evenodd"/>
                                                </svg>
                                                {{ number_format($orderSettings->min_stock_override, 0) }} units
                                            </span>
                                        @else
                                            <span class="text-lg text-gray-500 dark:text-gray-400">Not set</span>
                                        @endif
                                        <button type="button"
                                                @click="editing = true"
                                                class="inline-flex items-center px-2 py-1 text-xs font-medium rounded border border-blue-300 text-blue-700 hover:bg-blue-50 dark:border-blue-600 dark:text-blue-400 dark:hover:bg-blue-900/20 transition-colors duration-200">
                                            {{ $orderSettings?->min_stock_override ? 'Edit' : 'Set' }}
                                        </button>
                                    </div>

                                    <!-- Edit Mode -->
                                    <div x-show="editing" class="flex items-center space-x-2">
                                        <input type="number"
                                               x-model="value"
                                               step="1"
                                               min="0"
                                               placeholder="Enter minimum stock"
                                               class="w-32 px-2 py-1 text-sm border border-gray-300 dark:border-gray-600 rounded dark:bg-gray-700 dark:text-gray-100"
                                               @keydown.enter="
                                                   saving = true;
                                                   error = null;
                                                   fetch('{{ route('products.update-min-stock-override', $product->ID) }}', {
                                                       method: 'PATCH',
                                                       headers: {
                                                           'Content-Type': 'application/json',
                                                           'X-CSRF-TOKEN': '{{ csrf_token() }}',
                                                           'Accept': 'application/json'
                                                       },
                                                       body: JSON.stringify({ min_stock_override: value !== '' ? value : null })
                                                   })
                                                   .then(response => response.json())
                                                   .then(data => {
                                                       if (data.message) {
                                                           originalValue = value;
                                                           editing = false;
                                                           window.location.reload();
                                                       } else {
                                                           error = 'Failed to update';
                                                       }
                                                   })
                                                   .catch(err => {
                                                       error = 'Network error';
                                                   })
                                                   .finally(() => {
                                                       saving = false;
                                                   })
                                               "
                                               @keydown.escape="editing = false; value = originalValue">
                                        <button type="button"
                                                @click="
                                                    saving = true;
                                                    error = null;
                                                    fetch('{{ route('products.update-min-stock-override', $product->ID) }}', {
                                                        method: 'PATCH',
                                                        headers: {
                                                            'Content-Type': 'application/json',
                                                            'X-CSRF-TOKEN': '{{ csrf_token() }}',
                                                            'Accept': 'application/json'
                                                        },
                                                        body: JSON.stringify({ min_stock_override: value !== '' ? value : null })
                                                    })
                                                    .then(response => response.json())
                                                    .then(data => {
                                                        if (data.message) {
                                                            originalValue = value;
                                                            editing = false;
                                                            window.location.reload();
                                                        } else {
                                                            error = 'Failed to update';
                                                        }
                                                    })
                                                    .catch(err => {
                                                        error = 'Network error';
                                                    })
                                                    .finally(() => {
                                                        saving = false;
                                                    })
                                                "
                                                :disabled="saving"
                                                class="inline-flex items-center px-2 py-1 text-xs font-medium rounded bg-green-600 text-white hover:bg-green-700 disabled:opacity-50">
                                            <span x-show="!saving">Save</span>
                                            <span x-show="saving">Saving...</span>
                                        </button>
                                        <button type="button"
                                                @click="editing = false; value = originalValue"
                                                :disabled="saving"
                                                class="inline-flex items-center px-2 py-1 text-xs font-medium rounded bg-gray-300 text-gray-700 hover:bg-gray-400 disabled:opacity-50 dark:bg-gray-600 dark:text-gray-200 dark:hover:bg-gray-500">
                                            Cancel
                                        </button>
                                        @if($orderSettings?->min_stock_override)
                                            <button type="button"
                                                    @click="
                                                        value = '';
                                                        saving = true;
                                                        error = null;
                                                        fetch('{{ route('products.update-min-stock-override', $product->ID) }}', {
                                                            method: 'PATCH',
                                                            headers: {
                                                                'Content-Type': 'application/json',
                                                                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                                                                'Accept': 'application/json'
                                                            },
                                                            body: JSON.stringify({ min_stock_override: null })
                                                        })
                                                        .then(response => response.json())
                                                        .then(data => {
                                                            if (data.message) {
                                                                originalValue = '';
                                                                editing = false;
                                                                window.location.reload();
                                                            } else {
                                                                error = 'Failed to remove';
                                                            }
                                                        })
                                                        .catch(err => {
                                                            error = 'Network error';
                                                        })
                                                        .finally(() => {
                                                            saving = false;
                                                        })
                                                    "
                                                    :disabled="saving"
                                                    class="inline-flex items-center px-2 py-1 text-xs font-medium rounded bg-red-600 text-white hover:bg-red-700 disabled:opacity-50">
                                                Remove
                                            </button>
                                        @endif
                                    </div>
                                </div>
                                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                    @if($orderSettings?->min_stock_override)
                                        System will maintain at least this stock level when ordering
                                    @else
                                        Optional: Override calculated minimum stock level
                                    @endif
                                </p>
                                <p x-show="error" x-text="error" class="text-xs text-red-600 dark:text-red-400 mt-1"></p>
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            <form action="{{ route('products.update', $product->ID) }}" method="POST">
                @csrf
                @method('PUT')
                
                <!-- Hidden product code field - required for validation -->
                <input type="hidden" name="code" value="{{ $prefillData['code'] }}">

                <!-- Preserve stocking status (managed via AJAX toggle in stats bar) -->
                <input type="hidden" name="include_in_stocking" value="{{ $includeInStocking ? '1' : '0' }}">
                
                @if($fromDelivery)
                    <input type="hidden" name="from_delivery" value="{{ $fromDelivery }}">
                @elseif($fromContext)
                    <input type="hidden" name="from" value="{{ $fromContext }}">
                @endif

                <!-- Hidden field for override confirmation -->
                <input type="hidden" id="force_override" name="force_override" value="0">

                <!-- Main Content Grid -->
                <div class="grid grid-cols-1 xl:grid-cols-2 gap-6">
                    <!-- Left Column -->
                    <div class="space-y-6">
                        <!-- Basic Information Section -->
                        <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6">
                            <div class="border-b border-gray-200 dark:border-gray-700 pb-3 mb-4">
                                <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Basic Information</h3>
                            </div>

                            <div class="space-y-4">
                                <!-- Product Name -->
                                <x-form-group 
                                    name="name" 
                                    label="Product Name *" 
                                    type="text" 
                                    :value="old('name', $prefillData['name'])" 
                                    required 
                                    placeholder="Enter product name" 
                                    containerClass="" />

                                <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                                    <!-- Product Code/Barcode (Read Only) -->
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                            Product Code/Barcode
                                        </label>
                                        <input type="text" 
                                               value="{{ $prefillData['code'] }}" 
                                               disabled
                                               class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 bg-gray-100 dark:bg-gray-600 text-gray-500 dark:text-gray-400">
                                        <p class="text-xs text-gray-500 mt-1">
                                            Barcode cannot be changed from this form. Use the barcode editor on the product detail page if needed.
                                        </p>
                                    </div>

                                    <!-- Reference Code -->  
                                    <x-form-group 
                                        name="reference" 
                                        label="Reference Code" 
                                        type="text" 
                                        :value="old('reference', $prefillData['reference'])" 
                                        placeholder="Optional reference" />
                                </div>

                                <!-- Product Category -->
                                <div>
                                    <label for="category" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Product Category *
                                        <span class="ml-1 text-red-500">●</span>
                                    </label>
                                    <select id="category" 
                                            name="category"
                                            required
                                            class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 focus:border-indigo-500 focus:ring-indigo-500">
                                        <option value="">Please select a category</option>
                                        @foreach($categories as $category)
                                            <option value="{{ $category->ID }}" {{ old('category', $product->CATEGORY) == $category->ID ? 'selected' : '' }}>
                                                {{ $category->NAME }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                        </div>

                        <!-- Supplier Information -->
                        <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6">
                            <div class="border-b border-gray-200 dark:border-gray-700 pb-3 mb-4">
                                <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Supplier Information</h3>
                            </div>

                            <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                                <div>
                                    <label for="supplier_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Supplier
                                    </label>
                                    <select id="supplier_id" 
                                            name="supplier_id"
                                            class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 focus:border-indigo-500 focus:ring-indigo-500">
                                        <option value="">No supplier</option>
                                        @foreach($suppliers as $supplier)
                                            <option value="{{ $supplier->SupplierID }}" 
                                                    {{ old('supplier_id', $prefillData['supplier_id']) == $supplier->SupplierID ? 'selected' : '' }}>
                                                {{ $supplier->Supplier }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>

                                <div>
                                    <label for="supplier_code" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Supplier Code
                                    </label>
                                    <input type="text"
                                           id="supplier_code"
                                           name="supplier_code"
                                           value="{{ old('supplier_code', $prefillData['supplier_code']) }}"
                                           class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 focus:border-indigo-500 focus:ring-indigo-500"
                                           placeholder="Supplier's product code">

                                    <!-- Duplicate Warning (initially hidden) -->
                                    <div id="duplicate-warning" class="mt-2 hidden">
                                        <div class="flex items-start p-3 bg-yellow-50 dark:bg-yellow-900 border border-yellow-300 dark:border-yellow-700 rounded-md">
                                            <svg class="w-5 h-5 text-yellow-600 dark:text-yellow-400 mr-2 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path>
                                            </svg>
                                            <div class="flex-1">
                                                <p class="text-sm font-medium text-yellow-800 dark:text-yellow-300">
                                                    This supplier code is already linked to:
                                                </p>
                                                <p id="conflict-product-info" class="text-sm text-yellow-700 dark:text-yellow-400 mt-1"></p>
                                                <a id="conflict-product-link" href="#" target="_blank" class="text-sm text-blue-600 dark:text-blue-400 hover:underline mt-1 inline-block">
                                                    View conflicting product →
                                                </a>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Checking Status -->
                                    <div id="duplicate-checking" class="mt-2 hidden">
                                        <p class="text-sm text-gray-500 dark:text-gray-400">
                                            <span class="inline-block animate-spin mr-1">⏳</span>
                                            Checking for duplicates...
                                        </p>
                                    </div>
                                </div>

                                <div>
                                    <label for="units_per_case" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Units per Case
                                        <span class="text-xs text-gray-500 block">Retail packages per case</span>
                                    </label>
                                    <input type="number"
                                           id="units_per_case"
                                           name="units_per_case"
                                           value="{{ old('units_per_case', $prefillData['units_per_case']) }}"
                                           min="1"
                                           class="w-24 rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 focus:border-indigo-500 focus:ring-indigo-500">
                                </div>

                                <!-- Outer Barcode -->
                                <div x-data="outerBarcodeScanner()" x-init="$watch('scanner.cameraVisible', v => { if (!v) stopScannerCamera() })">
                                    <label for="outer_code" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Outer/Case Barcode</label>
                                    <div class="flex gap-2">
                                        <input type="text"
                                               id="outer_code"
                                               name="outer_code"
                                               x-ref="outerInput"
                                               value="{{ old('outer_code', $prefillData['outer_code']) }}"
                                               placeholder="No outer barcode"
                                               class="flex-1 min-w-0 rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 focus:border-indigo-500 focus:ring-indigo-500">
                                        <button type="button"
                                                @click="toggleScannerCamera()"
                                                class="px-3 py-2 rounded-md border-2 touch-manipulation"
                                                :class="scanner.cameraActive ? 'bg-red-100 border-red-500 text-red-700' : 'bg-green-100 border-green-500 text-green-700'"
                                                :title="scanner.cameraActive ? 'Stop camera' : 'Scan outer barcode with camera'">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"/>
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"/>
                                            </svg>
                                        </button>
                                    </div>
                                    <div x-show="scanner.cameraVisible" x-transition class="mt-3">
                                        <div id="outer-barcode-scanner" class="rounded-md overflow-hidden bg-gray-900" style="min-height: 220px;"></div>
                                        <p class="text-gray-500 dark:text-gray-400 text-xs text-center mt-2" x-text="scanner.cameraStatus"></p>
                                    </div>
                                </div>

                            </div>

                        </div>

                        <!-- Product Image Section -->
                        <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6">
                            <div class="border-b border-gray-200 dark:border-gray-700 pb-3 mb-4">
                                <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Product Image</h3>
                            </div>

                            @if($product->supplier && $supplierService->hasExternalIntegration($product->supplier->SupplierID))
                                <!-- Supplier Image -->
                                <div class="mb-4">
                                    <p class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        {{ $product->supplier->Supplier ?? 'Supplier' }} Image
                                    </p>
                                    <x-product-image :product="$product" :supplier-service="$supplierService" size="xl" :hover="true" />
                                </div>
                            @endif

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                                <!-- Current Image Display -->
                                <div>
                                    <p class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Current Image</p>
                                    <div class="aspect-square bg-gray-100 dark:bg-gray-700 rounded-lg overflow-hidden w-32 h-32">
                                        <img id="current-product-image"
                                             src="{{ route('products.image', $product->ID) }}?t={{ time() }}"
                                             alt="{{ $product->NAME }}"
                                             class="w-full h-full object-cover"
                                             onerror="this.src='data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 64 64%22><rect fill=%22%23e5e7eb%22 width=%2264%22 height=%2264%22/><text x=%2232%22 y=%2236%22 text-anchor=%22middle%22 fill=%22%239ca3af%22 font-size=%2210%22>No Image</text></svg>'">
                                    </div>
                                </div>

                                <!-- Image Upload (no form - handled via JS) -->
                                <div>
                                    <div class="mb-4">
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                            Upload New Image
                                        </label>
                                        <input type="file"
                                               id="image-input"
                                               accept="image/*"
                                               class="block w-full text-sm text-gray-500 dark:text-gray-400 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100 dark:file:bg-indigo-900 dark:file:text-indigo-300">
                                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Max 2MB. JPEG, PNG, or GIF.</p>
                                    </div>
                                    <button type="button"
                                            id="upload-btn"
                                            class="w-full px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition disabled:opacity-50 disabled:cursor-not-allowed">
                                        Upload Image
                                    </button>

                                    <!-- Image Preview -->
                                    <div id="image-preview" class="mt-4 hidden">
                                        <p class="text-sm text-gray-600 dark:text-gray-400 mb-2">Preview:</p>
                                        <div class="aspect-square bg-gray-100 dark:bg-gray-700 rounded-lg overflow-hidden w-24 h-24">
                                            <img id="preview-img" class="w-full h-full object-cover">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Right Column -->
                    <div class="space-y-6">
                        <!-- Pricing & Tax Section -->
                        <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6 border-2 border-green-200 dark:border-green-700">
                            <div class="border-b border-gray-200 dark:border-gray-700 pb-3 mb-4">
                                <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 flex items-center">
                                    <svg class="w-5 h-5 mr-2 text-green-600" fill="currentColor" viewBox="0 0 20 20">
                                        <path d="M8.433 7.418c.155-.103.346-.196.567-.267v1.698a2.305 2.305 0 01-.567-.267C8.07 8.34 8 8.114 8 8c0-.114.07-.34.433-.582zM11 12.849v-1.698c.22.071.412.164.567.267.364.243.433.468.433.582 0 .114-.07.34-.433.582a2.305 2.305 0 01-.567.267z"/>
                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-13a1 1 0 10-2 0v.092a4.535 4.535 0 00-1.676.662C6.602 6.234 6 7.009 6 8c0 .99.602 1.765 1.324 2.246.48.32 1.054.545 1.676.662v1.941c-.391-.127-.68-.317-.843-.504a1 1 0 10-1.51 1.31c.562.649 1.413 1.076 2.353 1.253V15a1 1 0 102 0v-.092a4.535 4.535 0 001.676-.662C13.398 13.766 14 12.991 14 12c0-.99-.602-1.765-1.324-2.246A4.535 4.535 0 0011 9.092V7.151c.391.127.68.317.843.504a1 1 0 101.511-1.31c-.563-.649-1.413-1.076-2.354-1.253V5z" clip-rule="evenodd"/>
                                    </svg>
                                    Pricing & Tax
                                </h3>
                            </div>

                            <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
                                <!-- Cost Price -->
                                <x-form-group 
                                    name="price_buy" 
                                    label="Cost Price (€) *" 
                                    type="number" 
                                    :value="old('price_buy', $prefillData['price_buy'])" 
                                    required 
                                    step="0.01" 
                                    min="0" />

                                <!-- Selling Price -->
                                <div>
                                    <label for="price_sell" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Selling Price (€) *
                                    </label>
                                    <input type="number" 
                                           id="price_sell" 
                                           name="price_sell" 
                                           value="{{ old('price_sell', $prefillData['price_sell']) }}" 
                                           step="0.0001" 
                                           min="0" 
                                           required
                                           class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 focus:border-indigo-500 focus:ring-indigo-500">
                                </div>

                                <!-- Tax Category -->
                                <div>
                                    <label for="tax_category" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Tax Category *
                                    </label>
                                    <select id="tax_category" 
                                            name="tax_category" 
                                            required
                                            class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 focus:border-indigo-500 focus:ring-indigo-500">
                                        <option value="">Please select tax category</option>
                                        @foreach($taxCategories as $taxCategory)
                                            <option value="{{ $taxCategory->ID }}" 
                                                {{ old('tax_category', $product->TAXCAT) == $taxCategory->ID ? 'selected' : '' }}>
                                                {{ $taxCategory->NAME }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>

                            <!-- Delivery Cost Option -->
                            <div class="mt-4">
                                <div class="flex items-center">
                                    <input type="checkbox"
                                           id="has_delivery_cost"
                                           name="has_delivery_cost"
                                           value="1"
                                           class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                                    <label for="has_delivery_cost" class="ml-2 block text-sm text-gray-700 dark:text-gray-300">
                                        Include delivery cost (15%)
                                        <span class="block text-xs text-gray-500">For suppliers with delivery charges</span>
                                    </label>
                                </div>
                            </div>

                            <!-- Pricing Breakdown -->
                            <div class="mt-6 p-4 bg-gray-50 dark:bg-gray-700 rounded-lg">
                                <h4 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-3">Pricing Breakdown</h4>

                                <div class="space-y-2 text-sm">
                                    <div class="flex justify-between">
                                        <span class="text-gray-600 dark:text-gray-400">Cost Price:</span>
                                        <span id="breakdown-cost" class="font-medium">€0.00</span>
                                    </div>
                                    <div class="flex justify-between" id="delivery-cost-row" style="display: none;">
                                        <span class="text-gray-600 dark:text-gray-400">Delivery Cost (15%):</span>
                                        <span id="breakdown-delivery" class="font-medium">€0.00</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-gray-600 dark:text-gray-400">Total Cost:</span>
                                        <span id="breakdown-total-cost" class="font-medium">€0.00</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-gray-600 dark:text-gray-400">Selling Price (ex VAT):</span>
                                        <span id="breakdown-selling-ex-vat" class="font-medium">€0.00</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-gray-600 dark:text-gray-400">VAT Amount:</span>
                                        <span id="breakdown-vat" class="font-medium">€0.00 (<span id="vat-rate">0%</span>)</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-gray-600 dark:text-gray-400">Selling Price (inc VAT):</span>
                                        <span id="breakdown-selling-inc-vat" class="font-medium">€0.00</span>
                                    </div>
                                    <hr class="border-gray-300 dark:border-gray-600">
                                    <div class="flex justify-between">
                                        <span class="text-gray-700 dark:text-gray-300 font-medium">Profit Margin:</span>
                                        <div class="text-right">
                                            <div id="margin-amount" class="font-semibold text-green-600 dark:text-green-400">€0.00</div>
                                            <div id="margin-percentage" class="text-sm text-green-600 dark:text-green-400">0%</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Till Display Settings -->
                        <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6">
                            <div class="border-b border-gray-200 dark:border-gray-700 pb-3 mb-4">
                                <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Till Display Settings</h3>
                                <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                                    Optional custom display name for products that need till buttons (products without barcodes)
                                </p>
                            </div>

                            <div class="space-y-6">
                                <!-- Display Name Input -->
                                <div>
                                    <label for="display_name" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Display Name
                                    </label>
                                    <input type="text" 
                                           id="display_name" 
                                           name="display_name"
                                           value="{{ old('display_name', $prefillData['display_name']) }}" 
                                           class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 focus:border-indigo-500 focus:ring-indigo-500"
                                           placeholder="Custom display name (leave empty to use product name)">
                                    <p class="text-xs text-gray-500 mt-1">
                                        Used on till buttons and displays. Supports line breaks with &lt;br&gt; tags.
                                    </p>
                                </div>

                                <!-- Till Visibility Toggle -->
                                <div class="flex items-start">
                                    <input type="checkbox" 
                                           id="show_on_till" 
                                           name="show_on_till" 
                                           value="1" 
                                           {{ old('show_on_till', $showOnTill) ? 'checked' : '' }}
                                           class="mt-1 rounded border-gray-300 text-green-600 focus:ring-green-500">
                                    <label for="show_on_till" class="ml-3 block text-sm text-gray-700 dark:text-gray-300">
                                        <span class="font-medium">Show on Till</span>
                                        <span class="block text-xs text-gray-500 mt-1">
                                            Make this product visible on the POS till. Uncheck to hide from till buttons.
                                        </span>
                                    </label>
                                </div>

                                <!-- Display Name Preview -->
                                <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-4">
                                    <div class="flex items-center justify-between mb-2">
                                        <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300">Till Button Preview</h4>
                                        <span class="text-xs text-gray-500 dark:text-gray-400">How it will appear on POS</span>
                                    </div>
                                    
                                    <!-- Mock Till Button -->
                                    <div id="till-button-preview" class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-600 rounded-lg p-4 text-center shadow-sm max-w-xs mx-auto">
                                        <div id="display-preview" class="text-sm font-medium text-gray-900 dark:text-gray-100 min-h-[2.5rem] flex items-center justify-center">
                                            <span class="text-gray-400 dark:text-gray-500 italic" id="preview-placeholder">Enter display name above</span>
                                        </div>
                                        <div class="mt-2 flex items-center justify-center gap-2">
                                            <div id="visibility-indicator" class="w-2 h-2 rounded-full bg-green-500"></div>
                                            <span class="text-xs text-gray-500 dark:text-gray-400" id="visibility-text">Visible on Till</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Short-Dated Product Settings (Admin/Manager Only) -->
                        @if(auth()->user()->hasAnyRole(['admin', 'manager']))
                            <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6">
                                <div class="border-b border-gray-200 dark:border-gray-700 pb-3 mb-4">
                                    <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Short-Dated Product</h3>
                                    <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                                        Flag products with short shelf life for special attention during ordering
                                    </p>
                                </div>

                                <div class="space-y-4">
                                    <!-- Short-Dated Checkbox -->
                                    <div class="flex items-start">
                                        <input type="checkbox"
                                               id="is_short_dated"
                                               name="is_short_dated"
                                               value="1"
                                               {{ old('is_short_dated', $orderSettings?->is_short_dated) ? 'checked' : '' }}
                                               class="mt-1 rounded border-gray-300 text-amber-600 focus:ring-amber-500">
                                        <label for="is_short_dated" class="ml-3 block text-sm text-gray-700 dark:text-gray-300">
                                            <span class="font-medium">Flag as short-dated</span>
                                            <span class="block text-xs text-gray-500 mt-1">
                                                Products flagged as short-dated will be highlighted on the orders page and automatically set to "review" priority
                                            </span>
                                        </label>
                                    </div>

                                    <!-- Shelf Life Days -->
                                    <div>
                                        <label for="shelf_life_days" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                            Shelf Life (Days)
                                            <span class="text-gray-500 font-normal">- Optional</span>
                                        </label>
                                        <input type="number"
                                               id="shelf_life_days"
                                               name="shelf_life_days"
                                               value="{{ old('shelf_life_days', $orderSettings?->shelf_life_days) }}"
                                               min="0"
                                               max="999"
                                               step="1"
                                               class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 focus:border-amber-500 focus:ring-amber-500"
                                               placeholder="e.g., 7">
                                        <p class="text-xs text-gray-500 mt-1">
                                            Specify how many days this product typically remains fresh (optional but recommended for short-dated items)
                                        </p>
                                    </div>

                                    <!-- Info box -->
                                    <div class="bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 rounded-lg p-3">
                                        <div class="flex items-start">
                                            <svg class="w-5 h-5 text-amber-600 dark:text-amber-400 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                                            </svg>
                                            <p class="ml-3 text-xs text-amber-800 dark:text-amber-200">
                                                Short-dated products will display with an amber border and warning icon on the orders page, helping you adjust quantities carefully to avoid waste.
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endif

                        <!-- Kitchen Product Toggle -->
                        <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6">
                            <div class="flex items-center justify-between">
                                <div>
                                    <h3 class="text-sm font-medium text-gray-700 dark:text-gray-300">Kitchen Product</h3>
                                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">Mark this product as used by the kitchen</p>
                                </div>
                                <button type="button"
                                        class="kitchen-toggle-btn text-[11px] font-medium border rounded px-2 py-0.5 transition-colors {{ $isKitchenProduct ? 'text-orange-600 border-orange-300 bg-orange-50 hover:bg-orange-100' : 'text-gray-500 border-gray-300 hover:border-orange-300 hover:text-orange-600' }}"
                                        data-product-id="{{ $product->ID }}"
                                        data-product-name="{{ e($product->NAME) }}"
                                        data-is-kitchen="{{ $isKitchenProduct ? 'true' : 'false' }}"
                                        title="{{ $isKitchenProduct ? 'Remove from kitchen products' : 'Add to kitchen products' }}">
                                    Kitchen
                                </button>
                            </div>
                        </div>

                        <!-- Action Buttons -->
                        <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6">
                            <div class="flex items-center justify-end space-x-4">
                                @if($fromDelivery)
                                    <a href="{{ route('deliveries.show', ['delivery' => $fromDelivery]) }}" 
                                       class="px-4 py-2 bg-gray-300 hover:bg-gray-400 text-gray-800 rounded-md transition-colors duration-200">
                                        Cancel
                                    </a>
                                @elseif($fromContext === 'coffee')
                                    <a href="{{ route('coffee.products') }}" 
                                       class="px-4 py-2 bg-gray-300 hover:bg-gray-400 text-gray-800 rounded-md transition-colors duration-200">
                                        Cancel
                                    </a>
                                @else
                                    <a href="{{ route('products.index') }}"
                                       class="px-4 py-2 bg-gray-300 hover:bg-gray-400 text-gray-800 rounded-md transition-colors duration-200">
                                        Cancel
                                    </a>
                                @endif
                                <button type="submit" 
                                        class="px-6 py-2 bg-green-600 hover:bg-green-700 text-white rounded-md transition-colors duration-200">
                                    Update Product
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </form>

            <!-- Sales History Section (Collapsible) -->
            <div x-data="{ salesOpen: false }" class="mt-6 bg-white dark:bg-gray-800 shadow rounded-lg overflow-hidden">
                <button type="button"
                        @click="salesOpen = !salesOpen; if(salesOpen && !window.salesChartLoaded) { loadInitialSalesData(); window.salesChartLoaded = true; }"
                        class="w-full px-6 py-4 flex items-center justify-between text-left hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors duration-200">
                    <div class="flex items-center text-sm font-medium text-gray-900 dark:text-gray-100">
                        <svg class="w-5 h-5 mr-2 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                        </svg>
                        Sales History
                    </div>
                    <svg class="w-5 h-5 text-gray-400 transition-transform duration-200" :class="{ 'rotate-180': salesOpen }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                    </svg>
                </button>

                <div x-show="salesOpen" x-collapse>
                    <div class="p-6 border-t border-gray-200 dark:border-gray-700">
                        <!-- Time Period Selection -->
                        <div class="mb-6 flex flex-wrap items-center gap-3">
                            <button type="button" onclick="loadSalesData(4)" class="period-btn active px-3 py-1.5 text-sm font-medium rounded-lg bg-indigo-600 text-white transition-colors">Last 4 Months</button>
                            <button type="button" onclick="loadSalesData(6)" class="period-btn px-3 py-1.5 text-sm font-medium rounded-lg bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300 border border-gray-300 dark:border-gray-600 transition-colors">Last 6 Months</button>
                            <button type="button" onclick="loadSalesData(12)" class="period-btn px-3 py-1.5 text-sm font-medium rounded-lg bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300 border border-gray-300 dark:border-gray-600 transition-colors">Last 12 Months</button>
                            <button type="button" onclick="loadSalesData('ytd')" class="period-btn px-3 py-1.5 text-sm font-medium rounded-lg bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300 border border-gray-300 dark:border-gray-600 transition-colors">Year to Date</button>
                            <button type="button" onclick="showSalesChartModal('{{ $product->ID }}', '{{ addslashes($product->NAME) }}')"
                                    class="inline-flex items-center px-3 py-1.5 text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 rounded-lg transition-colors">
                                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                                </svg>
                                Detailed Sales
                            </button>
                        </div>

                        <!-- Chart Container -->
                        <div class="bg-gray-50 dark:bg-gray-900 p-4 rounded-lg mb-6">
                            <div class="relative" style="height: 300px;">
                                <canvas id="salesChart"></canvas>
                                <div id="chartLoading" class="hidden absolute inset-0 bg-white dark:bg-gray-800 bg-opacity-75 flex items-center justify-center rounded-lg">
                                    <div class="text-center">
                                        <svg class="animate-spin h-8 w-8 text-indigo-600 mx-auto" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                        </svg>
                                        <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">Loading sales data...</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Sales Stats & Table (lazy loaded via Alpine) -->
                        <div x-data="{
                            loading: true,
                            error: null,
                            salesHistory: [],
                            salesStats: {},
                            salesUrl: '{{ route('products.sales-data', $product->ID) }}'
                        }" x-init="
                            fetch(salesUrl)
                                .then(r => r.ok ? r.json() : Promise.reject('HTTP ' + r.status))
                                .then(data => { salesHistory = data.salesHistory || []; salesStats = data.salesStats || {}; })
                                .catch(e => { console.error(e); error = String(e); })
                                .finally(() => { loading = false; })
                        ">
                            <!-- Loading skeleton -->
                            <template x-if="loading">
                                <div class="animate-pulse">
                                    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
                                        <div class="bg-gray-200 dark:bg-gray-700 h-20 rounded-lg"></div>
                                        <div class="bg-gray-200 dark:bg-gray-700 h-20 rounded-lg"></div>
                                        <div class="bg-gray-200 dark:bg-gray-700 h-20 rounded-lg"></div>
                                        <div class="bg-gray-200 dark:bg-gray-700 h-20 rounded-lg"></div>
                                    </div>
                                </div>
                            </template>

                            <!-- Loaded content -->
                            <template x-if="!loading && !error && salesHistory.length > 0">
                                <div>
                                    <!-- Sales Statistics Cards -->
                                    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
                                        <div class="bg-gray-50 dark:bg-gray-700 p-4 rounded-lg">
                                            <div class="text-sm font-medium text-gray-500 dark:text-gray-400">Total Sales (12m)</div>
                                            <div class="mt-1 text-2xl font-semibold text-gray-900 dark:text-gray-100" data-stat="total_sales_12m" x-text="Math.round(salesStats.total_sales_12m || 0).toLocaleString()"></div>
                                        </div>
                                        <div class="bg-gray-50 dark:bg-gray-700 p-4 rounded-lg">
                                            <div class="text-sm font-medium text-gray-500 dark:text-gray-400">Avg Monthly</div>
                                            <div class="mt-1 text-2xl font-semibold text-gray-900 dark:text-gray-100" data-stat="avg_monthly_sales" x-text="(salesStats.avg_monthly_sales || 0).toFixed(1)"></div>
                                        </div>
                                        <div class="bg-gray-50 dark:bg-gray-700 p-4 rounded-lg">
                                            <div class="text-sm font-medium text-gray-500 dark:text-gray-400">This Month</div>
                                            <div class="mt-1 text-2xl font-semibold text-gray-900 dark:text-gray-100" data-stat="this_month_sales" x-text="Math.round(salesStats.this_month_sales || 0).toLocaleString()"></div>
                                        </div>
                                        <div class="bg-gray-50 dark:bg-gray-700 p-4 rounded-lg">
                                            <div class="text-sm font-medium text-gray-500 dark:text-gray-400">Trend</div>
                                            <div class="mt-1 text-2xl font-semibold" data-stat="trend">
                                                <span x-show="salesStats.trend === 'up'" class="text-green-600 dark:text-green-400">↑ Up</span>
                                                <span x-show="salesStats.trend === 'down'" class="text-red-600 dark:text-red-400">↓ Down</span>
                                                <span x-show="salesStats.trend === 'stable'" class="text-gray-600 dark:text-gray-400">→ Stable</span>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Sales by Month Table -->
                                    <h3 class="text-lg font-semibold mb-4">Sales by Month</h3>
                                    <div class="overflow-x-auto">
                                        <table id="salesTable" class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                            <thead class="bg-gray-50 dark:bg-gray-700">
                                                <tr>
                                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Month</th>
                                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Units Sold</th>
                                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Trend</th>
                                                </tr>
                                            </thead>
                                            <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                                <template x-for="(monthData, index) in salesHistory" :key="index">
                                                    <tr>
                                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-gray-100" x-text="monthData.month"></td>
                                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100" x-text="parseFloat(monthData.units || 0).toFixed(1)"></td>
                                                        <td class="px-6 py-4 whitespace-nowrap text-sm">
                                                            <template x-if="index > 0">
                                                                <span>
                                                                    <template x-if="monthData.units > salesHistory[index-1].units">
                                                                        <span class="text-green-600 dark:text-green-400">↑</span>
                                                                    </template>
                                                                    <template x-if="monthData.units < salesHistory[index-1].units">
                                                                        <span class="text-red-600 dark:text-red-400">↓</span>
                                                                    </template>
                                                                    <template x-if="monthData.units === salesHistory[index-1].units">
                                                                        <span class="text-gray-400">→</span>
                                                                    </template>
                                                                </span>
                                                            </template>
                                                        </td>
                                                    </tr>
                                                </template>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </template>

                            <!-- Error state -->
                            <template x-if="!loading && error">
                                <div class="text-center py-8">
                                    <p class="text-sm text-red-600 dark:text-red-400" x-text="'Failed to load sales data: ' + error"></p>
                                </div>
                            </template>

                            <!-- No data state -->
                            <template x-if="!loading && !error && salesHistory.length === 0">
                                <div class="text-center py-8">
                                    <p class="text-sm text-gray-500 dark:text-gray-400">No sales data available for this product.</p>
                                </div>
                            </template>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Sales Chart Modal -->
            <x-sales-chart-modal />

            <!-- Alternate Barcode Section -->
            <div x-data="alternateBarcodeScanner()"
                 x-init="$watch('open', value => { if (!value) stopScannerCamera() })"
                 class="mt-6 bg-white dark:bg-gray-800 shadow rounded-lg overflow-hidden">
                <button type="button"
                        @click="open = !open"
                        class="w-full px-6 py-4 flex items-center justify-between text-left hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors duration-200">
                    <div class="flex items-center text-sm text-gray-600 dark:text-gray-400">
                        <svg class="w-4 h-4 mr-2 transition-transform duration-200" :class="{ 'rotate-90': open }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                        </svg>
                        <span class="font-medium">Add alternate barcode for this product</span>
                    </div>
                    <span class="text-xs text-gray-400 dark:text-gray-500">Product changed packaging?</span>
                </button>

                <div x-show="open"
                     x-collapse
                     class="px-6 pb-6 border-t border-gray-200 dark:border-gray-700">
                    <form action="{{ route('products.create-alternate', $product->ID) }}" method="POST" class="pt-4">
                        @csrf
                        <div class="max-w-md">
                            <label for="new_barcode" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                New Barcode
                            </label>
                            <div class="flex gap-2">
                                <input type="text"
                                       id="new_barcode"
                                       name="new_barcode"
                                       x-model="barcode"
                                       x-ref="barcodeInput"
                                       required
                                       class="flex-1 rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 focus:border-indigo-500 focus:ring-indigo-500"
                                       placeholder="Scan or enter new barcode">
                                <button type="button"
                                        @click="toggleScannerCamera()"
                                        class="px-3 py-2 rounded-md border-2 touch-manipulation"
                                        :class="scanner.cameraActive ? 'bg-red-100 border-red-500 text-red-700' : 'bg-green-100 border-green-500 text-green-700'"
                                        :title="scanner.cameraActive ? 'Stop camera' : 'Scan with camera'">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"/>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"/>
                                    </svg>
                                </button>
                            </div>
                            @error('new_barcode')
                                <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                            @enderror
                            <div x-show="scanner.cameraVisible" x-transition class="mt-3">
                                <div id="product-edit-scanner" class="rounded-md overflow-hidden bg-gray-900" style="min-height: 220px;"></div>
                                <p class="text-gray-500 dark:text-gray-400 text-xs text-center mt-2" x-text="scanner.cameraStatus"></p>
                            </div>
                            <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                                This creates a copy of this product with the new barcode, preserving all pricing, supplier, and category details.
                            </p>
                        </div>
                        <div class="mt-4">
                            <button type="submit"
                                    class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-md transition-colors duration-200">
                                Create Linked Product
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <script>
                function outerBarcodeScanner() {
                    return {
                        scanner: {
                            cameraActive: false,
                            cameraVisible: false,
                            cameraStatus: '',
                            lastScannedBarcode: '',
                            lastScanTime: 0,
                        },
                        toggleScannerCamera() {
                            if (this.scanner.cameraActive) {
                                this.stopScannerCamera();
                                return;
                            }
                            if (!window.BarcodeScanner) {
                                this.scanner.cameraStatus = 'Scanner module not loaded. Ensure HTTPS is enabled.';
                                this.scanner.cameraVisible = true;
                                return;
                            }
                            this.scanner.cameraVisible = true;
                            this.scanner.cameraStatus = 'Starting camera...';
                            this.$nextTick(() => {
                                window.BarcodeScanner.startScanner(
                                    'outer-barcode-scanner',
                                    (decodedText) => this.onScannerDetected(decodedText),
                                    (error) => {}
                                ).then(() => {
                                    this.scanner.cameraActive = true;
                                    this.scanner.cameraStatus = 'Point camera at outer/case barcode';
                                }).catch((err) => {
                                    this.scanner.cameraStatus = 'Camera error: ' + (err.message || err);
                                    this.scanner.cameraActive = false;
                                });
                            });
                        },
                        stopScannerCamera() {
                            if (window.BarcodeScanner && window.BarcodeScanner.isRunning()) {
                                window.BarcodeScanner.stopScanner().catch(() => {});
                            }
                            this.scanner.cameraActive = false;
                            this.scanner.cameraVisible = false;
                            this.scanner.cameraStatus = '';
                        },
                        parseBarcode(raw) {
                            let text = raw.trim();
                            if (text.startsWith(']C1')) text = text.substring(3);
                            if (text.startsWith(']d2')) text = text.substring(3);
                            if (text.startsWith(']e0')) text = text.substring(3);
                            text = text.replace(/[\x1D]/g, '|');
                            const gtin14Match = text.match(/(?:^|\|)01(\d{14})/);
                            if (gtin14Match) {
                                return { barcode: gtin14Match[1], isGS1: true, raw };
                            }
                            return { barcode: text, isGS1: false, raw };
                        },
                        onScannerDetected(text) {
                            try {
                                const ctx = new (window.AudioContext || window.webkitAudioContext)();
                                const osc = ctx.createOscillator();
                                osc.type = 'square';
                                osc.frequency.value = 1000;
                                osc.connect(ctx.destination);
                                osc.start();
                                osc.stop(ctx.currentTime + 0.1);
                            } catch(e) {}
                            if (navigator.vibrate) navigator.vibrate(100);

                            const now = Date.now();
                            if (text === this.scanner.lastScannedBarcode && now - this.scanner.lastScanTime < 2000) {
                                return;
                            }
                            this.scanner.lastScannedBarcode = text;
                            this.scanner.lastScanTime = now;

                            const parsed = this.parseBarcode(text);
                            this.stopScannerCamera();
                            this.$refs.outerInput.value = parsed.barcode;
                            this.$refs.outerInput.dispatchEvent(new Event('input', { bubbles: true }));
                            this.$refs.outerInput.dispatchEvent(new Event('change', { bubbles: true }));
                            this.$nextTick(() => this.$refs.outerInput.focus());
                        },
                    };
                }

                function alternateBarcodeScanner() {
                    return {
                        open: false,
                        barcode: '',
                        scanner: {
                            cameraActive: false,
                            cameraVisible: false,
                            cameraStatus: '',
                            lastScannedBarcode: '',
                            lastScanTime: 0,
                        },
                        toggleScannerCamera() {
                            if (this.scanner.cameraActive) {
                                this.stopScannerCamera();
                                return;
                            }
                            if (!window.BarcodeScanner) {
                                this.scanner.cameraStatus = 'Scanner module not loaded. Ensure HTTPS is enabled.';
                                this.scanner.cameraVisible = true;
                                return;
                            }
                            this.scanner.cameraVisible = true;
                            this.scanner.cameraStatus = 'Starting camera...';
                            this.$nextTick(() => {
                                window.BarcodeScanner.startScanner(
                                    'product-edit-scanner',
                                    (decodedText) => this.onScannerDetected(decodedText),
                                    (error) => {}
                                ).then(() => {
                                    this.scanner.cameraActive = true;
                                    this.scanner.cameraStatus = 'Point camera at barcode';
                                }).catch((err) => {
                                    this.scanner.cameraStatus = 'Camera error: ' + (err.message || err);
                                    this.scanner.cameraActive = false;
                                });
                            });
                        },
                        stopScannerCamera() {
                            if (window.BarcodeScanner && window.BarcodeScanner.isRunning()) {
                                window.BarcodeScanner.stopScanner().catch(() => {});
                            }
                            this.scanner.cameraActive = false;
                            this.scanner.cameraVisible = false;
                            this.scanner.cameraStatus = '';
                        },
                        parseBarcode(raw) {
                            let text = raw.trim();
                            if (text.startsWith(']C1')) text = text.substring(3);
                            if (text.startsWith(']d2')) text = text.substring(3);
                            if (text.startsWith(']e0')) text = text.substring(3);
                            text = text.replace(/[\x1D\u001D]/g, '|');
                            const gtin14Match = text.match(/(?:^|\|)01(\d{14})/);
                            if (gtin14Match) {
                                return { barcode: gtin14Match[1], isGS1: true, raw };
                            }
                            return { barcode: text, isGS1: false, raw };
                        },
                        onScannerDetected(text) {
                            try {
                                const ctx = new (window.AudioContext || window.webkitAudioContext)();
                                const osc = ctx.createOscillator();
                                osc.type = 'square';
                                osc.frequency.value = 1000;
                                osc.connect(ctx.destination);
                                osc.start();
                                osc.stop(ctx.currentTime + 0.1);
                            } catch(e) {}
                            if (navigator.vibrate) navigator.vibrate(100);

                            const now = Date.now();
                            if (text === this.scanner.lastScannedBarcode && now - this.scanner.lastScanTime < 2000) {
                                return;
                            }
                            this.scanner.lastScannedBarcode = text;
                            this.scanner.lastScanTime = now;

                            const parsed = this.parseBarcode(text);
                            this.stopScannerCamera();
                            this.barcode = parsed.barcode;
                            this.$nextTick(() => this.$refs.barcodeInput.focus());
                        },
                    };
                }
            </script>
        </div>
    </div>

    <!-- Confirmation Modal for Supplier Link Override -->
    <div id="override-modal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
        <div class="relative top-20 mx-auto p-5 border w-11/12 md:w-3/4 lg:w-1/2 shadow-lg rounded-md bg-white dark:bg-gray-800">
            <div class="mt-3">
                <!-- Modal Header -->
                <div class="flex items-center justify-between pb-3 border-b border-gray-200 dark:border-gray-700">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 flex items-center">
                        <svg class="w-6 h-6 text-yellow-600 mr-2" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path>
                        </svg>
                        Supplier Link Conflict
                    </h3>
                </div>

                <!-- Modal Body -->
                <div class="mt-4 space-y-4">
                    <p class="text-sm text-gray-700 dark:text-gray-300">
                        This supplier code is already linked to another product. If you proceed, the supplier code will be reassigned to this product.
                    </p>

                    <div class="bg-gray-100 dark:bg-gray-700 rounded-lg p-4">
                        <h4 class="text-sm font-semibold text-gray-900 dark:text-gray-100 mb-2">Conflicting Product Details:</h4>
                        <dl class="space-y-2 text-sm">
                            <div class="flex">
                                <dt class="font-medium text-gray-700 dark:text-gray-300 w-32">Product Name:</dt>
                                <dd id="modal-product-name" class="text-gray-900 dark:text-gray-100"></dd>
                            </div>
                            <div class="flex">
                                <dt class="font-medium text-gray-700 dark:text-gray-300 w-32">Barcode:</dt>
                                <dd id="modal-product-barcode" class="text-gray-900 dark:text-gray-100 font-mono"></dd>
                            </div>
                            <div class="flex">
                                <dt class="font-medium text-gray-700 dark:text-gray-300 w-32">Supplier:</dt>
                                <dd id="modal-supplier-name" class="text-gray-900 dark:text-gray-100"></dd>
                            </div>
                        </dl>
                        <a id="modal-product-link" href="#" target="_blank" class="mt-3 inline-block text-sm text-blue-600 dark:text-blue-400 hover:underline">
                            View product details →
                        </a>
                    </div>

                    <div class="bg-yellow-50 dark:bg-yellow-900 border border-yellow-300 dark:border-yellow-700 rounded-md p-3">
                        <p class="text-sm text-yellow-800 dark:text-yellow-300">
                            <strong>⚠️ Warning:</strong> The supplier code will be removed from the product listed above and assigned to the current product.
                        </p>
                    </div>
                </div>

                <!-- Modal Footer -->
                <div class="mt-6 flex items-center justify-end space-x-3 pt-4 border-t border-gray-200 dark:border-gray-700">
                    <button type="button"
                            onclick="closeOverrideModal()"
                            class="px-4 py-2 bg-gray-300 hover:bg-gray-400 text-gray-800 rounded-md transition-colors duration-200">
                        Cancel
                    </button>
                    <button type="button"
                            onclick="confirmOverride()"
                            class="px-4 py-2 bg-red-600 hover:bg-red-700 text-white rounded-md transition-colors duration-200">
                        Proceed & Reassign
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Tax rates mapping from PHP (actual database values)
        const taxRates = @json($taxRates);
        
        console.log('Edit page script loaded, tax rates:', taxRates);
        
        // Track if user has manually entered selling price
        let userHasSetSellingPrice = true; // Default to true for edit form since price is already set
        
        // Pricing breakdown calculation
        function updatePricingBreakdown() {
            console.log('updatePricingBreakdown called');

            const costPriceEl = document.getElementById('price_buy');
            const sellPriceEl = document.getElementById('price_sell');
            const deliveryCostEl = document.getElementById('has_delivery_cost');
            const taxCategoryEl = document.getElementById('tax_category');

            if (!costPriceEl || !sellPriceEl || !deliveryCostEl || !taxCategoryEl) {
                console.error('Missing form elements:', {costPriceEl, sellPriceEl, deliveryCostEl, taxCategoryEl});
                return;
            }

            const costPrice = parseFloat(costPriceEl.value) || 0;
            const sellPrice = parseFloat(sellPriceEl.value) || 0;
            const hasDeliveryCost = deliveryCostEl.checked;
            const taxCategoryId = taxCategoryEl.value;

            console.log('Selected tax category ID:', taxCategoryId);
            // Get tax rate from the PHP-provided rates, convert string to float
            const taxRate = taxCategoryId && taxRates[taxCategoryId] !== undefined ? parseFloat(taxRates[taxCategoryId]) : 0.00;
            console.log('Applied tax rate:', taxRate);

            // Calculate delivery cost
            const deliveryCost = hasDeliveryCost ? costPrice * 0.15 : 0;
            const totalCost = costPrice + deliveryCost;

            // Calculate VAT (selling price is VAT inclusive)
            // Special handling for 0% VAT
            const sellPriceExVat = taxRate === 0 ? sellPrice : sellPrice / (1 + taxRate);
            const vatAmount = taxRate === 0 ? 0 : sellPrice - sellPriceExVat;

            console.log('VAT calculation:', {sellPrice, taxRate, sellPriceExVat, vatAmount});

            // Calculate profit margin (excluding VAT) - based on selling price, not cost
            const marginAmount = sellPriceExVat - totalCost;
            const marginPercentage = sellPriceExVat > 0 ? (marginAmount / sellPriceExVat) * 100 : 0;

            // Debug logging
            console.log('Pricing breakdown:', {
                costPrice, sellPrice, hasDeliveryCost, taxCategoryId, taxRate,
                deliveryCost, totalCost, sellPriceExVat, vatAmount,
                marginAmount, marginPercentage
            });

            // Update breakdown display - check if elements exist
            const elements = {
                'breakdown-cost': costPrice.toFixed(2),
                'breakdown-delivery': deliveryCost.toFixed(2),
                'breakdown-total-cost': totalCost.toFixed(2),
                'breakdown-selling-ex-vat': sellPriceExVat.toFixed(2),
                'breakdown-vat': vatAmount.toFixed(2),
                'breakdown-selling-inc-vat': sellPrice.toFixed(2),
                'vat-rate': (taxRate * 100).toFixed(1) + '%'
            };
            
            for (const [id, value] of Object.entries(elements)) {
                const el = document.getElementById(id);
                if (el) {
                    el.textContent = (id === 'vat-rate') ? value : '€' + value;
                } else {
                    console.error('Element not found:', id);
                }
            }
            
            // Ensure margin elements exist before updating
            const marginAmountEl = document.getElementById('margin-amount');
            const marginPercentageEl = document.getElementById('margin-percentage');
            
            if (marginAmountEl && marginPercentageEl) {
                marginAmountEl.textContent = '€' + marginAmount.toFixed(2);
                marginPercentageEl.textContent = marginPercentage.toFixed(1) + '%';
                
                // Color coding for margin
                if (marginPercentage < 10) {
                    marginAmountEl.className = 'font-semibold text-red-600 dark:text-red-400';
                    marginPercentageEl.className = 'text-sm text-red-600 dark:text-red-400';
                } else if (marginPercentage < 20) {
                    marginAmountEl.className = 'font-semibold text-yellow-600 dark:text-yellow-400';
                    marginPercentageEl.className = 'text-sm text-yellow-600 dark:text-yellow-400';
                } else {
                    marginAmountEl.className = 'font-semibold text-green-600 dark:text-green-400';
                    marginPercentageEl.className = 'text-sm text-green-600 dark:text-green-400';
                }
            }

            // Show/hide delivery cost row
            const deliveryRow = document.getElementById('delivery-cost-row');
            if (deliveryRow) {
                deliveryRow.style.display = hasDeliveryCost ? 'flex' : 'none';
            }
        }

        // Recalculate summary when cost price changes
        const priceBuyEl = document.getElementById('price_buy');
        if (priceBuyEl) {
            priceBuyEl.addEventListener('input', function() {
                updatePricingBreakdown();
            });
        }

        // Update pricing when selling price changes
        const priceSellEl = document.getElementById('price_sell');
        if (priceSellEl) {
            priceSellEl.addEventListener('input', function() {
                updatePricingBreakdown();
            });
        }
        
        // Update pricing when tax category changes
        const taxCategoryEl = document.getElementById('tax_category');
        if (taxCategoryEl) {
            taxCategoryEl.addEventListener('change', function() {
                updatePricingBreakdown();
            });
        }

        // Update pricing when delivery cost checkbox changes
        const hasDeliveryCostEl = document.getElementById('has_delivery_cost');
        if (hasDeliveryCostEl) {
            hasDeliveryCostEl.addEventListener('change', updatePricingBreakdown);
        }

        // Initialize display name preview (with error handling)
        function initializeDisplayNamePreview() {
            const displayNameField = document.getElementById('display_name');
            const productNameField = document.getElementById('name');
            
            if (!displayNameField) return;
            
            // Live preview update on display name input
            displayNameField.addEventListener('input', function() {
                updateDisplayPreview();
            });
            
            // Also update when product name changes (for fallback)
            if (productNameField) {
                productNameField.addEventListener('input', function() {
                    updateDisplayPreview();
                });
            }
            
            // Initial update
            updateDisplayPreview();
        }

        function updateDisplayPreview() {
            const displayNameField = document.getElementById('display_name');
            const productNameField = document.getElementById('name');
            const preview = document.getElementById('display-preview');
            const placeholder = document.getElementById('preview-placeholder');
            
            if (!displayNameField || !preview) return;
            
            const displayName = displayNameField.value.trim();
            const productName = productNameField ? productNameField.value.trim() : '';
            
            if (displayName) {
                // Hide placeholder and show formatted display name
                if (placeholder) {
                    placeholder.style.display = 'none';
                }
                
                // Convert HTML entities and handle <br> tags
                let formattedName = displayName
                    .replace(/&lt;/g, '<')
                    .replace(/&gt;/g, '>')
                    .replace(/&amp;/g, '&')
                    .replace(/&quot;/g, '"')
                    .replace(/&#39;/g, "'");
                
                // Convert <br> tags and newlines to line breaks
                formattedName = formattedName
                    .replace(/\n/g, '<br>')
                    .replace(/<br\s*\/?>/gi, '<br>');
                
                preview.innerHTML = formattedName;
                preview.className = 'text-sm font-medium text-gray-900 dark:text-gray-100 min-h-[2.5rem] flex items-center justify-center';
            } else if (productName) {
                // Show product name as fallback
                if (placeholder) {
                    placeholder.style.display = 'none';
                }
                preview.innerHTML = productName;
                preview.className = 'text-sm font-medium text-gray-500 dark:text-gray-400 min-h-[2.5rem] flex items-center justify-center italic';
            } else {
                // Show placeholder
                if (placeholder) {
                    placeholder.style.display = 'inline';
                }
                preview.innerHTML = '<span class="text-gray-400 dark:text-gray-500 italic" id="preview-placeholder">Enter display name above</span>';
                preview.className = 'text-sm font-medium text-gray-900 dark:text-gray-100 min-h-[2.5rem] flex items-center justify-center';
            }
        }

        // Till Visibility Preview Functionality
        function initializeTillVisibilityPreview() {
            const showOnTillCheckbox = document.getElementById('show_on_till');
            
            if (!showOnTillCheckbox) return;
            
            // Update preview when checkbox changes
            showOnTillCheckbox.addEventListener('change', function() {
                updateTillVisibilityPreview();
            });
            
            // Initial update
            updateTillVisibilityPreview();
        }

        function updateTillVisibilityPreview() {
            const showOnTillCheckbox = document.getElementById('show_on_till');
            const tillButtonPreview = document.getElementById('till-button-preview');
            const visibilityIndicator = document.getElementById('visibility-indicator');
            const visibilityText = document.getElementById('visibility-text');
            
            if (!showOnTillCheckbox || !tillButtonPreview || !visibilityIndicator || !visibilityText) return;
            
            const isVisible = showOnTillCheckbox.checked;
            
            if (isVisible) {
                // Show as visible
                tillButtonPreview.className = 'bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-600 rounded-lg p-4 text-center shadow-sm max-w-xs mx-auto';
                visibilityIndicator.className = 'w-2 h-2 rounded-full bg-green-500';
                visibilityText.textContent = 'Visible on Till';
                visibilityText.className = 'text-xs text-gray-500 dark:text-gray-400';
            } else {
                // Show as hidden
                tillButtonPreview.className = 'bg-gray-100 dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg p-4 text-center shadow-sm max-w-xs mx-auto opacity-60';
                visibilityIndicator.className = 'w-2 h-2 rounded-full bg-red-500';
                visibilityText.textContent = 'Hidden from Till';
                visibilityText.className = 'text-xs text-red-600 dark:text-red-400';
            }
        }
        
        // Initialize everything when DOM is ready
        document.addEventListener('DOMContentLoaded', function() {
            console.log('Edit page DOM loaded, initializing...');
            
            // Test if we can find the margin elements
            const marginAmount = document.getElementById('margin-amount');
            const marginPercentage = document.getElementById('margin-percentage');
            console.log('Margin elements found:', {marginAmount, marginPercentage});
            
            // Call pricing breakdown update after a small delay to ensure all fields are initialized
            setTimeout(() => {
                updatePricingBreakdown();
            }, 100);

            // Initialize display name preview (with error handling)
            try {
                initializeDisplayNamePreview();
            } catch (e) {
                console.log('Display name preview initialization skipped:', e.message);
            }
            
            // Initialize till visibility preview (with error handling)
            try {
                initializeTillVisibilityPreview();
            } catch (e) {
                console.log('Till visibility preview initialization skipped:', e.message);
            }

            // Initialize supplier link duplicate checking
            try {
                initializeSupplierLinkDuplicateCheck();
            } catch (e) {
                console.log('Supplier link duplicate check initialization skipped:', e.message);
            }

            // Initialize image upload functionality
            try {
                initializeImageUpload();
            } catch (e) {
                console.log('Image upload initialization skipped:', e.message);
            }
        });

        // Image Upload Functionality
        function initializeImageUpload() {
            const imageInput = document.getElementById('image-input');
            const uploadBtn = document.getElementById('upload-btn');

            if (!imageInput || !uploadBtn) return;

            // Image Preview on file select
            imageInput.addEventListener('change', function(event) {
                const file = event.target.files[0];
                if (file) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        document.getElementById('preview-img').src = e.target.result;
                        document.getElementById('image-preview').classList.remove('hidden');
                    };
                    reader.readAsDataURL(file);
                } else {
                    document.getElementById('image-preview').classList.add('hidden');
                }
            });

            // Image Upload Button Click
            uploadBtn.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();

                const file = imageInput.files[0];
                if (!file) {
                    showImageUploadAlert('Please select an image first.', 'error');
                    return;
                }

                const formData = new FormData();
                formData.append('image', file);
                formData.append('_token', document.querySelector('meta[name="csrf-token"]').getAttribute('content'));

                uploadBtn.disabled = true;
                uploadBtn.textContent = 'Uploading...';

                fetch('{{ route("products.update-image", $product->ID) }}', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showImageUploadAlert('Image updated successfully!', 'success');
                        // Refresh current image with server timestamp for cache busting
                        const timestamp = data.timestamp || new Date().getTime();
                        document.getElementById('current-product-image').src = '{{ route("products.image", $product->ID) }}?t=' + timestamp;
                        // Hide preview
                        document.getElementById('image-preview').classList.add('hidden');
                        // Clear file input
                        imageInput.value = '';
                    } else {
                        showImageUploadAlert(data.error || 'Failed to update image.', 'error');
                    }
                })
                .catch(error => {
                    console.error('Error uploading image:', error);
                    showImageUploadAlert('Error uploading image.', 'error');
                })
                .finally(() => {
                    uploadBtn.disabled = false;
                    uploadBtn.textContent = 'Upload Image';
                });
            });
        }

        function showImageUploadAlert(message, type) {
            // Create alert element
            const alertDiv = document.createElement('div');
            alertDiv.className = `fixed top-4 right-4 z-50 p-4 rounded-lg shadow-lg ${
                type === 'success'
                    ? 'bg-green-100 text-green-800 border border-green-300'
                    : 'bg-red-100 text-red-800 border border-red-300'
            }`;
            alertDiv.innerHTML = `
                <div class="flex items-center">
                    <span>${message}</span>
                    <button onclick="this.parentElement.parentElement.remove()" class="ml-4 text-lg font-bold">&times;</button>
                </div>
            `;
            document.body.appendChild(alertDiv);

            // Auto-remove after 3 seconds
            setTimeout(() => alertDiv.remove(), 3000);
        }

        // Supplier Link Duplicate Checking
        let duplicateCheckTimeout = null;
        let currentConflict = null;
        let isCheckingDuplicate = false;

        function initializeSupplierLinkDuplicateCheck() {
            const supplierCodeField = document.getElementById('supplier_code');
            const supplierIdField = document.getElementById('supplier_id');
            const form = supplierCodeField?.closest('form');

            if (!supplierCodeField || !supplierIdField || !form) return;

            // Add change listeners for real-time checking
            supplierCodeField.addEventListener('input', debounceCheckDuplicate);
            supplierIdField.addEventListener('change', checkDuplicate);

            // Intercept form submission
            form.addEventListener('submit', handleFormSubmit);

            // Check on page load if fields have values
            if (supplierCodeField.value && supplierIdField.value) {
                checkDuplicate();
            }
        }

        function debounceCheckDuplicate() {
            clearTimeout(duplicateCheckTimeout);
            duplicateCheckTimeout = setTimeout(checkDuplicate, 500);
        }

        async function checkDuplicate() {
            const supplierCodeField = document.getElementById('supplier_code');
            const supplierIdField = document.getElementById('supplier_id');
            const warningDiv = document.getElementById('duplicate-warning');
            const checkingDiv = document.getElementById('duplicate-checking');

            if (!supplierCodeField || !supplierIdField) return;

            const supplierCode = supplierCodeField.value.trim();
            const supplierId = supplierIdField.value;

            // Hide warning and checking if fields are empty
            if (!supplierCode || !supplierId) {
                warningDiv.classList.add('hidden');
                checkingDiv.classList.add('hidden');
                currentConflict = null;
                return;
            }

            // Show checking status
            warningDiv.classList.add('hidden');
            checkingDiv.classList.remove('hidden');
            isCheckingDuplicate = true;

            try {
                const response = await fetch('/api/products/check-supplier-link-duplicate', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({
                        supplier_code: supplierCode,
                        supplier_id: parseInt(supplierId),
                        current_product_id: '{{ $product->ID }}',
                    }),
                });

                const data = await response.json();
                checkingDiv.classList.add('hidden');
                isCheckingDuplicate = false;

                if (data.duplicate && data.conflict) {
                    // Show warning with conflict details
                    currentConflict = data.conflict;
                    displayDuplicateWarning(data.conflict);
                } else {
                    // No duplicate found
                    warningDiv.classList.add('hidden');
                    currentConflict = null;
                }
            } catch (error) {
                console.error('Error checking for duplicates:', error);
                checkingDiv.classList.add('hidden');
                isCheckingDuplicate = false;
            }
        }

        function displayDuplicateWarning(conflict) {
            const warningDiv = document.getElementById('duplicate-warning');
            const productInfo = document.getElementById('conflict-product-info');
            const productLink = document.getElementById('conflict-product-link');

            productInfo.textContent = `${conflict.product_name} (${conflict.product_barcode}) - Supplier: ${conflict.supplier_name}`;
            productLink.href = conflict.edit_url || '#';

            warningDiv.classList.remove('hidden');
        }

        function handleFormSubmit(e) {
            const forceOverrideField = document.getElementById('force_override');

            // If there's a conflict and override is not set, show modal
            if (currentConflict && forceOverrideField.value === '0') {
                e.preventDefault();
                showOverrideModal(currentConflict);
                return false;
            }

            // Allow form to submit normally
            return true;
        }

        function showOverrideModal(conflict) {
            const modal = document.getElementById('override-modal');
            const modalProductName = document.getElementById('modal-product-name');
            const modalProductBarcode = document.getElementById('modal-product-barcode');
            const modalSupplierName = document.getElementById('modal-supplier-name');
            const modalProductLink = document.getElementById('modal-product-link');

            if (!modal) return;

            // Populate modal with conflict details
            modalProductName.textContent = conflict.product_name || 'Unknown';
            modalProductBarcode.textContent = conflict.product_barcode || 'N/A';
            modalSupplierName.textContent = conflict.supplier_name || 'Unknown';
            modalProductLink.href = conflict.edit_url || '#';

            // Show modal
            modal.classList.remove('hidden');
        }

        function closeOverrideModal() {
            const modal = document.getElementById('override-modal');
            if (modal) {
                modal.classList.add('hidden');
            }
        }

        function confirmOverride() {
            const forceOverrideField = document.getElementById('force_override');
            const form = forceOverrideField.closest('form');

            // Set override flag
            forceOverrideField.value = '1';

            // Close modal
            closeOverrideModal();

            // Submit form
            if (form) {
                form.submit();
            }
        }

        // Close modal when clicking outside
        document.getElementById('override-modal')?.addEventListener('click', function(e) {
            if (e.target === this) {
                closeOverrideModal();
            }
        });

        // Kitchen toggle button handler
        document.querySelectorAll('.kitchen-toggle-btn').forEach(button => {
            button.addEventListener('click', async function() {
                const productId = this.dataset.productId;
                const isKitchen = this.dataset.isKitchen === 'true';

                this.disabled = true;
                const originalText = this.textContent;
                this.textContent = isKitchen ? 'Removing...' : 'Adding...';

                try {
                    const response = await fetch('/kitchen/products/toggle', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                            'Accept': 'application/json'
                        },
                        body: JSON.stringify({ product_id: productId })
                    });

                    const data = await response.json();

                    if (response.ok && data.success) {
                        this.dataset.isKitchen = data.is_kitchen.toString();
                        if (data.is_kitchen) {
                            this.classList.remove('text-gray-500', 'border-gray-300');
                            this.classList.add('text-orange-600', 'border-orange-300', 'bg-orange-50');
                            this.title = 'Remove from kitchen products';
                        } else {
                            this.classList.remove('text-orange-600', 'border-orange-300', 'bg-orange-50');
                            this.classList.add('text-gray-500', 'border-gray-300');
                            this.title = 'Add to kitchen products';
                        }
                        this.textContent = 'Kitchen';
                        this.disabled = false;
                    } else {
                        alert('Failed to update kitchen status: ' + (data.error || 'Unknown error'));
                        this.disabled = false;
                        this.textContent = originalText;
                    }
                } catch (error) {
                    console.error('Kitchen toggle error:', error);
                    alert('Failed to update kitchen status. Please try again.');
                    this.disabled = false;
                    this.textContent = originalText;
                }
            });
        });
        // ========================================
        // Stock Edit Functions
        // ========================================
        function toggleStockEdit() {
            const display = document.getElementById('stockDisplay');
            const form = document.getElementById('stockEditForm');
            const input = document.getElementById('stockUnitsInput');

            if (form.classList.contains('hidden')) {
                display.classList.add('hidden');
                form.classList.remove('hidden');
                input.focus();
                input.select();
            } else {
                form.classList.add('hidden');
                display.classList.remove('hidden');
            }
        }

        // Handle arrow keys to increment/decrement by 1
        const stockInput = document.getElementById('stockUnitsInput');
        if (stockInput) {
            stockInput.addEventListener('keydown', function(e) {
                if (e.key === 'ArrowUp') {
                    e.preventDefault();
                    this.value = parseFloat((parseFloat(this.value || 0) + 1).toFixed(2));
                } else if (e.key === 'ArrowDown') {
                    e.preventDefault();
                    this.value = Math.max(0, parseFloat((parseFloat(this.value || 0) - 1).toFixed(2)));
                }
            });
        }

        function updateStock(event) {
            event.preventDefault();

            const form = event.target;
            const input = form.querySelector('input[name="stock_units"]');
            const stockValue = parseFloat(input.value);
            const prodId = '{{ $product->ID }}';

            const submitButton = form.querySelector('button[type="submit"]');
            const originalText = submitButton.textContent;
            submitButton.disabled = true;
            submitButton.textContent = 'Saving...';

            fetch(`/products/${prodId}/update-stock`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                },
                body: JSON.stringify({ stock_units: stockValue })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const currentStockValue = document.getElementById('currentStockValue');
                    currentStockValue.textContent = Number(stockValue).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});

                    const stockDisplay = currentStockValue.closest('p');
                    stockDisplay.className = stockValue > 0 ?
                        'text-2xl font-bold text-green-600 dark:text-green-400' :
                        'text-2xl font-bold text-red-600 dark:text-red-400';

                    toggleStockEdit();
                    showMessage(data.message || 'Stock updated successfully', 'success');
                } else {
                    showMessage(data.message || 'Failed to update stock', 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showMessage('Network error while updating stock. Please try again.', 'error');
            })
            .finally(() => {
                submitButton.disabled = false;
                submitButton.textContent = originalText;
            });
        }

        // ========================================
        // Stocking Toggle
        // ========================================
        function toggleStocking(productId, includeInStocking) {
            const button = event.target.closest('button');
            const originalContent = button.innerHTML;

            button.disabled = true;
            button.innerHTML = '<svg class="w-4 h-4 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>';

            fetch(`/products/${productId}/toggle-stocking`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                },
                body: JSON.stringify({ include_in_stocking: includeInStocking, source: 'product_edit' })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    window.location.reload();
                } else {
                    alert('Error: ' + (data.error || 'Failed to update stocking status'));
                    button.disabled = false;
                    button.innerHTML = originalContent;
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Network error while updating stocking status. Please try again.');
                button.disabled = false;
                button.innerHTML = originalContent;
            });
        }

        // ========================================
        // Requeue for Labels
        // ========================================
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
                    if (button) {
                        const originalText = button.innerHTML;
                        button.innerHTML = '<svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>Added!';
                        button.disabled = true;
                        setTimeout(() => {
                            button.innerHTML = originalText;
                            button.disabled = false;
                        }, 2000);
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

        // ========================================
        // Sales Chart Functions
        // ========================================
        let salesChart = null;
        const productId = '{{ $product->ID }}';
        const minStockOverride = {{ $orderSettings?->min_stock_override ?? 'null' }};
        window.salesChartLoaded = false;

        function loadInitialSalesData() {
            const chartLoading = document.getElementById('chartLoading');
            if (chartLoading) chartLoading.classList.remove('hidden');

            fetch(`/products/${productId}/sales-data?period=4`, {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.salesHistory && data.salesHistory.length > 0) {
                    createChart(data.salesHistory);
                }
                if (chartLoading) chartLoading.classList.add('hidden');
            })
            .catch(error => {
                console.error('Error loading initial sales data:', error);
                if (chartLoading) chartLoading.classList.add('hidden');
            });
        }

        function createChart(salesData) {
            const canvas = document.getElementById('salesChart');
            if (!canvas) return;
            const ctx = canvas.getContext('2d');

            if (salesChart) {
                salesChart.destroy();
            }

            const labels = salesData.map(item => item.month_short + ' ' + item.year);
            const data = salesData.map(item => item.units);

            const gradient = ctx.createLinearGradient(0, 0, 0, 250);
            gradient.addColorStop(0, 'rgba(99, 102, 241, 0.8)');
            gradient.addColorStop(1, 'rgba(99, 102, 241, 0.1)');

            const datasets = [{
                label: 'Units Sold',
                data: data,
                backgroundColor: gradient,
                borderColor: 'rgb(99, 102, 241)',
                borderWidth: 2,
                borderRadius: 8,
                borderSkipped: false,
                order: 2
            }];

            if (minStockOverride !== null && minStockOverride > 0) {
                datasets.push({
                    type: 'line',
                    label: 'Min Stock Override',
                    data: Array(labels.length).fill(minStockOverride),
                    borderColor: 'rgb(249, 115, 22)',
                    borderWidth: 2,
                    borderDash: [8, 4],
                    pointRadius: 0,
                    pointHoverRadius: 0,
                    fill: false,
                    tension: 0,
                    order: 1
                });
            }

            salesChart = new Chart(ctx, {
                type: 'bar',
                data: { labels: labels, datasets: datasets },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: minStockOverride !== null && minStockOverride > 0,
                            position: 'top',
                            labels: { color: 'rgb(107, 114, 128)', usePointStyle: true, padding: 15 }
                        },
                        tooltip: {
                            backgroundColor: 'rgba(0, 0, 0, 0.8)',
                            padding: 12,
                            titleColor: 'white',
                            bodyColor: 'white',
                            borderColor: 'rgb(99, 102, 241)',
                            borderWidth: 1,
                            displayColors: true,
                            callbacks: {
                                label: function(context) {
                                    if (context.dataset.label === 'Min Stock Override') {
                                        return 'Min Stock: ' + context.parsed.y.toFixed(0) + ' units';
                                    }
                                    return 'Units Sold: ' + context.parsed.y.toFixed(1);
                                }
                            }
                        }
                    },
                    scales: {
                        y: { beginAtZero: true, grid: { color: 'rgba(156, 163, 175, 0.1)' }, ticks: { color: 'rgb(107, 114, 128)' } },
                        x: { grid: { display: false }, ticks: { color: 'rgb(107, 114, 128)' } }
                    },
                    animation: { duration: 750, easing: 'easeInOutQuart' }
                }
            });
        }

        function loadSalesData(period) {
            document.querySelectorAll('.period-btn').forEach(btn => {
                btn.classList.remove('active', 'bg-indigo-600', 'text-white');
                btn.classList.add('bg-white', 'dark:bg-gray-800', 'text-gray-700', 'dark:text-gray-300', 'border', 'border-gray-300', 'dark:border-gray-600');
            });
            event.target.classList.remove('bg-white', 'dark:bg-gray-800', 'text-gray-700', 'dark:text-gray-300', 'border', 'border-gray-300', 'dark:border-gray-600');
            event.target.classList.add('active', 'bg-indigo-600', 'text-white');

            const chartLoading = document.getElementById('chartLoading');
            if (chartLoading) chartLoading.classList.remove('hidden');

            fetch(`/products/${productId}/sales-data?period=${period}`, {
                headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
            })
            .then(response => response.json())
            .then(data => {
                createChart(data.salesHistory);
                updateStatistics(data.salesStats);
                updateTable(data.salesHistory);
                if (chartLoading) chartLoading.classList.add('hidden');
            })
            .catch(error => {
                console.error('Error loading sales data:', error);
                if (chartLoading) chartLoading.classList.add('hidden');
                alert('Failed to load sales data. Please try again.');
            });
        }

        function updateStatistics(stats) {
            const els = {
                'total_sales_12m': document.querySelector('[data-stat="total_sales_12m"]'),
                'avg_monthly_sales': document.querySelector('[data-stat="avg_monthly_sales"]'),
                'this_month_sales': document.querySelector('[data-stat="this_month_sales"]'),
                'trend': document.querySelector('[data-stat="trend"]')
            };
            if (els.total_sales_12m) els.total_sales_12m.textContent = Math.round(stats.total_sales_12m).toLocaleString();
            if (els.avg_monthly_sales) els.avg_monthly_sales.textContent = stats.avg_monthly_sales.toFixed(1);
            if (els.this_month_sales) els.this_month_sales.textContent = Math.round(stats.this_month_sales).toLocaleString();
            if (els.trend) {
                els.trend.innerHTML = stats.trend === 'up'
                    ? '<span class="text-green-600 dark:text-green-400">↑ Up</span>'
                    : stats.trend === 'down'
                    ? '<span class="text-red-600 dark:text-red-400">↓ Down</span>'
                    : '<span class="text-gray-600 dark:text-gray-400">→ Stable</span>';
            }
        }

        function updateTable(salesData) {
            const tbody = document.querySelector('#salesTable tbody');
            if (!tbody) return;

            tbody.innerHTML = '';
            let previousUnits = null;

            salesData.forEach(monthData => {
                const row = document.createElement('tr');

                const monthCell = document.createElement('td');
                monthCell.className = 'px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-gray-100';
                monthCell.textContent = monthData.month;
                row.appendChild(monthCell);

                const unitsCell = document.createElement('td');
                unitsCell.className = 'px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100';
                unitsCell.textContent = monthData.units.toFixed(1);
                row.appendChild(unitsCell);

                const trendCell = document.createElement('td');
                trendCell.className = 'px-6 py-4 whitespace-nowrap text-sm';
                if (previousUnits !== null) {
                    if (monthData.units > previousUnits) {
                        const percent = ((monthData.units - previousUnits) / Math.max(previousUnits, 1)) * 100;
                        trendCell.innerHTML = `<span class="text-green-600 dark:text-green-400">↑ ${percent.toFixed(1)}%</span>`;
                    } else if (monthData.units < previousUnits) {
                        const percent = ((previousUnits - monthData.units) / Math.max(previousUnits, 1)) * 100;
                        trendCell.innerHTML = `<span class="text-red-600 dark:text-red-400">↓ ${percent.toFixed(1)}%</span>`;
                    } else {
                        trendCell.innerHTML = '<span class="text-gray-600 dark:text-gray-400">→ 0%</span>';
                    }
                } else {
                    trendCell.innerHTML = '<span class="text-gray-400 dark:text-gray-500">-</span>';
                }
                row.appendChild(trendCell);
                tbody.appendChild(row);
                previousUnits = monthData.units;
            });
        }

        // ========================================
        // Flash Message Helper
        // ========================================
        function showMessage(message, type = 'info') {
            let messageEl = document.getElementById('flash-message');
            if (!messageEl) {
                messageEl = document.createElement('div');
                messageEl.id = 'flash-message';
                messageEl.style.cssText = 'position: fixed; top: 20px; right: 20px; z-index: 9999; padding: 12px 20px; border-radius: 6px; font-weight: 500; transition: opacity 0.3s;';
                document.body.appendChild(messageEl);
            }

            const colors = {
                success: 'background: #10b981; color: white;',
                error: 'background: #ef4444; color: white;',
                info: 'background: #3b82f6; color: white;',
                warning: 'background: #f59e0b; color: white;'
            };

            messageEl.style.cssText += colors[type] || colors.info;
            messageEl.textContent = message;
            messageEl.style.opacity = '1';

            setTimeout(() => {
                messageEl.style.opacity = '0';
                setTimeout(() => {
                    if (messageEl.parentNode) messageEl.parentNode.removeChild(messageEl);
                }, 300);
            }, 3000);
        }
    </script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    @push('scripts')
        @vite(['resources/js/barcode-scanner.js'])
    @endpush
</x-admin-layout>
