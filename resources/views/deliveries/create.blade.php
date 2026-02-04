<x-admin-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="text-2xl font-bold text-gray-900 dark:text-gray-100">Import New Delivery</h2>
            <a href="{{ route('deliveries.index') }}"
               class="inline-flex items-center px-4 py-2 bg-gray-600 hover:bg-gray-700 text-white font-medium rounded-md transition-colors duration-200">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                </svg>
                Back to Deliveries
            </a>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">

            <x-alert type="error" :messages="$errors->all()" />

            <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg">
                <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">Delivery Information</h3>
                    <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                        Upload a file from your supplier to create a new delivery for verification.
                    </p>
                </div>

                <!-- Tab Navigation -->
                <div class="border-b border-gray-200 dark:border-gray-700" x-data="{ activeTab: 'pdf' }">
                    <nav class="flex -mb-px" aria-label="Tabs">
                        <button @click="activeTab = 'pdf'"
                                :class="activeTab === 'pdf' ? 'border-indigo-500 text-indigo-600 dark:text-indigo-400' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:text-gray-400 dark:hover:text-gray-300'"
                                class="w-1/2 py-4 px-1 text-center border-b-2 font-medium text-sm transition-colors duration-200">
                            <svg class="w-5 h-5 inline-block mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                            </svg>
                            PDF Invoice (Recommended)
                        </button>
                        <button @click="activeTab = 'csv'"
                                :class="activeTab === 'csv' ? 'border-indigo-500 text-indigo-600 dark:text-indigo-400' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:text-gray-400 dark:hover:text-gray-300'"
                                class="w-1/2 py-4 px-1 text-center border-b-2 font-medium text-sm transition-colors duration-200">
                            <svg class="w-5 h-5 inline-block mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                            </svg>
                            CSV File (Legacy)
                        </button>
                    </nav>

                    <!-- PDF Upload Tab -->
                    <div x-show="activeTab === 'pdf'" class="p-6">
                        <form method="POST" action="{{ route('deliveries.store-pdf') }}" enctype="multipart/form-data" id="pdf-form">
                            @csrf

                            <!-- Supplier Selection -->
                            <div class="mb-6">
                                <label for="pdf_supplier_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    Supplier
                                </label>
                                <select name="supplier_id" id="pdf_supplier_id" required
                                        class="w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 focus:ring-indigo-500">
                                    <option value="">Select a supplier...</option>
                                    @foreach($suppliers as $supplier)
                                        <option value="{{ $supplier->SupplierID }}" {{ old('supplier_id') == $supplier->SupplierID ? 'selected' : '' }}>
                                            {{ $supplier->Supplier }}
                                        </option>
                                    @endforeach
                                </select>
                                <!-- Auto-detection status -->
                                <div id="supplier-detection-status" class="mt-2 hidden">
                                    <span id="supplier-detection-message" class="text-sm"></span>
                                </div>
                                @error('supplier_id')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- Delivery Date -->
                            <div class="mb-6">
                                <label for="pdf_delivery_date" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    Delivery Date
                                </label>
                                <input type="date" name="delivery_date" id="pdf_delivery_date"
                                       value="{{ old('delivery_date', date('Y-m-d')) }}" required
                                       class="w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 focus:ring-indigo-500">
                                @error('delivery_date')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- PDF File Upload -->
                            <div class="mb-6">
                                <label for="pdf_file" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    Delivery Invoice PDF(s)
                                </label>
                                <div class="mt-1 flex justify-center px-6 pt-5 pb-6 border-2 border-gray-300 border-dashed rounded-md hover:border-gray-400 transition-colors duration-200"
                                     id="pdf-dropzone"
                                     ondrop="handlePdfDrop(event)" ondragover="handleDragOver(event)" ondragleave="handleDragLeave(event)">
                                    <div class="space-y-1 text-center">
                                        <svg class="mx-auto h-12 w-12 text-gray-400" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                        </svg>
                                        <div class="flex text-sm text-gray-600 dark:text-gray-400">
                                            <label for="pdf_file" class="relative cursor-pointer bg-white dark:bg-gray-800 rounded-md font-medium text-indigo-600 hover:text-indigo-500 focus-within:outline-none focus-within:ring-2 focus-within:ring-offset-2 focus-within:ring-indigo-500">
                                                <span>Upload PDF(s)</span>
                                                <input id="pdf_file" name="pdf_file[]" type="file" accept=".pdf" required multiple class="sr-only" onchange="handlePdfSelect(event)">
                                            </label>
                                            <p class="pl-1">or drag and drop</p>
                                        </div>
                                        <p class="text-xs text-gray-500 dark:text-gray-400">PDF files only, up to 20MB each. Select multiple to combine into one delivery.</p>
                                        <div id="pdf-file-names" class="text-sm font-medium text-gray-900 dark:text-gray-100 hidden mt-2"></div>
                                    </div>
                                </div>
                                @error('pdf_file')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- PDF Format Information -->
                            <div class="mb-6 bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-700 rounded-lg p-4">
                                <h4 class="text-sm font-medium text-green-800 dark:text-green-200 mb-2">PDF Upload Benefits</h4>
                                <ul class="text-sm text-green-700 dark:text-green-300 list-disc list-inside space-y-1">
                                    <li>Automatic product parsing - no CSV conversion needed</li>
                                    <li>Accurate case/unit quantity interpretation</li>
                                    <li>Correct unit cost calculation</li>
                                    <li>Currently supports: <strong>Independent Irish Health Foods</strong>, <strong>UDEA</strong></li>
                                </ul>
                            </div>

                            <!-- Preview Section (hidden by default) -->
                            <div id="pdf-preview" class="mb-6 hidden">
                                <div class="bg-gray-50 dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-lg p-4">
                                    <div class="flex justify-between items-center mb-3">
                                        <h4 class="text-sm font-medium text-gray-800 dark:text-gray-200">
                                            Parsed Items Preview
                                        </h4>
                                        <span id="pdf-confidence" class="text-xs px-2 py-1 rounded-full"></span>
                                    </div>

                                    <!-- Files Processed (for multi-file uploads) -->
                                    <div id="pdf-files-processed" class="mb-3 hidden">
                                        <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-700 rounded p-3">
                                            <h5 class="text-xs font-medium text-blue-800 dark:text-blue-200 mb-2">Files Processed</h5>
                                            <ul id="pdf-files-list" class="text-xs text-blue-700 dark:text-blue-300 space-y-1"></ul>
                                        </div>
                                    </div>

                                    <!-- Warnings -->
                                    <div id="pdf-warnings" class="mb-3 hidden">
                                        <div class="bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-700 rounded p-3">
                                            <h5 class="text-xs font-medium text-yellow-800 dark:text-yellow-200 mb-1">Warnings</h5>
                                            <ul id="pdf-warnings-list" class="text-xs text-yellow-700 dark:text-yellow-300 list-disc list-inside"></ul>
                                        </div>
                                    </div>

                                    <!-- Summary -->
                                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-3 text-sm">
                                        <div class="bg-white dark:bg-gray-800 rounded p-2">
                                            <span class="text-gray-500 dark:text-gray-400">Items:</span>
                                            <span id="pdf-item-count" class="font-medium text-gray-900 dark:text-gray-100 ml-1">0</span>
                                        </div>
                                        <div class="bg-white dark:bg-gray-800 rounded p-2">
                                            <span class="text-gray-500 dark:text-gray-400">Products:</span>
                                            <span id="pdf-products-total" class="font-medium text-gray-900 dark:text-gray-100 ml-1">€0.00</span>
                                        </div>
                                        <div id="pdf-barrels-summary" class="bg-amber-50 dark:bg-amber-900/30 rounded p-2 hidden">
                                            <span class="text-amber-700 dark:text-amber-400">Barrels:</span>
                                            <span id="pdf-barrels-total" class="font-medium text-amber-800 dark:text-amber-300 ml-1">€0.00</span>
                                        </div>
                                        <div class="bg-white dark:bg-gray-800 rounded p-2">
                                            <span class="text-gray-500 dark:text-gray-400">Grand Total:</span>
                                            <span id="pdf-total-value" class="font-bold text-gray-900 dark:text-gray-100 ml-1">€0.00</span>
                                        </div>
                                    </div>

                                    <!-- Totals Verification Section -->
                                    <div id="pdf-totals-verification" class="mb-3 hidden">
                                        <!-- Content populated by JavaScript -->
                                    </div>

                                    <!-- Barrels Section (collapsible) -->
                                    <div id="pdf-barrels-section" class="hidden mb-3">
                                        <details class="bg-amber-50 dark:bg-amber-900/30 border border-amber-200 dark:border-amber-800 rounded">
                                            <summary class="px-3 py-2 cursor-pointer text-sm font-medium text-amber-800 dark:text-amber-200">
                                                Barrel Deposits (<span id="pdf-barrels-count">0</span> items)
                                            </summary>
                                            <div class="px-3 pb-3">
                                                <table class="min-w-full text-xs mt-2">
                                                    <thead>
                                                        <tr class="text-left text-amber-700 dark:text-amber-400">
                                                            <th class="py-1">Qty</th>
                                                            <th class="py-1">Description</th>
                                                            <th class="py-1 text-right">Total</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody id="pdf-barrels-body" class="text-amber-900 dark:text-amber-100">
                                                    </tbody>
                                                </table>
                                            </div>
                                        </details>
                                    </div>

                                    <!-- Items Table -->
                                    <div class="overflow-x-auto max-h-64 overflow-y-auto">
                                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-xs">
                                            <thead class="bg-gray-100 dark:bg-gray-800 sticky top-0">
                                                <tr>
                                                    <th class="px-2 py-1 text-left font-medium text-gray-500 dark:text-gray-400">Code</th>
                                                    <th class="px-2 py-1 text-left font-medium text-gray-500 dark:text-gray-400">Product</th>
                                                    <th class="px-2 py-1 text-right font-medium text-gray-500 dark:text-gray-400">Units</th>
                                                    <th class="px-2 py-1 text-right font-medium text-gray-500 dark:text-gray-400">Unit Cost</th>
                                                    <th class="px-2 py-1 text-right font-medium text-gray-500 dark:text-gray-400">Total</th>
                                                </tr>
                                            </thead>
                                            <tbody id="pdf-items-body" class="divide-y divide-gray-200 dark:divide-gray-700">
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>

                            <!-- Submit Button -->
                            <div class="flex justify-end space-x-3">
                                <button type="button" id="parse-pdf-btn" onclick="parsePdf()"
                                        class="inline-flex items-center px-4 py-2 bg-gray-600 hover:bg-gray-700 text-white font-medium rounded-md transition-colors duration-200">
                                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                    </svg>
                                    Preview
                                </button>
                                <button type="submit" id="submit-pdf-btn"
                                        class="inline-flex items-center px-6 py-2 bg-indigo-600 hover:bg-indigo-700 text-white font-medium rounded-md transition-colors duration-200">
                                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/>
                                    </svg>
                                    Import Delivery
                                </button>
                            </div>
                        </form>
                    </div>

                    <!-- CSV Upload Tab -->
                    <div x-show="activeTab === 'csv'" class="p-6">
                        <form method="POST" action="{{ route('deliveries.store') }}" enctype="multipart/form-data">
                            @csrf

                            <!-- Supplier Selection -->
                            <div class="mb-6">
                                <label for="supplier_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    Supplier
                                </label>
                                <select name="supplier_id" id="supplier_id" required
                                        class="w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 focus:ring-indigo-500">
                                    <option value="">Select a supplier...</option>
                                    @foreach($suppliers as $supplier)
                                        <option value="{{ $supplier->SupplierID }}" {{ old('supplier_id') == $supplier->SupplierID ? 'selected' : '' }}>
                                            {{ $supplier->Supplier }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('supplier_id')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- Delivery Date -->
                            <div class="mb-6">
                                <label for="delivery_date" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    Delivery Date
                                </label>
                                <input type="date" name="delivery_date" id="delivery_date"
                                       value="{{ old('delivery_date', date('Y-m-d')) }}" required
                                       class="w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 focus:ring-indigo-500">
                                @error('delivery_date')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- CSV File Upload -->
                            <div class="mb-6">
                                <label for="csv_file" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    Delivery CSV File
                                </label>
                                <div class="mt-1 flex justify-center px-6 pt-5 pb-6 border-2 border-gray-300 border-dashed rounded-md hover:border-gray-400 transition-colors duration-200"
                                     ondrop="handleDrop(event)" ondragover="handleDragOver(event)" ondragleave="handleDragLeave(event)">
                                    <div class="space-y-1 text-center">
                                        <svg class="mx-auto h-12 w-12 text-gray-400" stroke="currentColor" fill="none" viewBox="0 0 48 48">
                                            <path d="M28 8H12a4 4 0 00-4 4v20m32-12v8m0 0v8a4 4 0 01-4 4H12a4 4 0 01-4-4v-4m32-4l-3.172-3.172a4 4 0 00-5.656 0L28 28M8 32l9.172-9.172a4 4 0 015.656 0L28 28m0 0l4 4m4-24h8m-4-4v8m-12 4h.02" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                        </svg>
                                        <div class="flex text-sm text-gray-600 dark:text-gray-400">
                                            <label for="csv_file" class="relative cursor-pointer bg-white dark:bg-gray-800 rounded-md font-medium text-indigo-600 hover:text-indigo-500 focus-within:outline-none focus-within:ring-2 focus-within:ring-offset-2 focus-within:ring-indigo-500">
                                                <span>Upload a file</span>
                                                <input id="csv_file" name="csv_file" type="file" accept=".csv,.txt" required class="sr-only" onchange="handleFileSelect(event)">
                                            </label>
                                            <p class="pl-1">or drag and drop</p>
                                        </div>
                                        <p class="text-xs text-gray-500 dark:text-gray-400">CSV files only, up to 10MB</p>
                                        <p id="file-name" class="text-sm font-medium text-gray-900 dark:text-gray-100 hidden"></p>
                                    </div>
                                </div>
                                @error('csv_file')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- CSV Format Information -->
                            <div class="mb-6 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-700 rounded-lg p-4">
                                <h4 class="text-sm font-medium text-blue-800 dark:text-blue-200 mb-2">Supported CSV Formats</h4>
                                <p class="text-sm text-blue-700 dark:text-blue-300 mb-3">
                                    The system automatically detects and supports multiple supplier formats:
                                </p>

                                <!-- Udea Format -->
                                <div class="mb-3">
                                    <p class="text-xs font-medium text-blue-800 dark:text-blue-200 mb-1">Udea Format:</p>
                                    <div class="text-xs font-mono bg-blue-100 dark:bg-blue-900/40 p-2 rounded border">
                                        Code,Ordered,Qty,SKU,Content,Description,Price,Sale,Total
                                    </div>
                                </div>

                                <!-- Independent Format -->
                                <div class="mb-3">
                                    <p class="text-xs font-medium text-blue-800 dark:text-blue-200 mb-1">Independent Health Foods Format:</p>
                                    <div class="text-xs font-mono bg-blue-100 dark:bg-blue-900/40 p-2 rounded border">
                                        Code,Product,Ordered,Qty,RSP,Price,Tax,Value
                                    </div>
                                    <p class="text-xs text-blue-600 dark:text-blue-400 mt-1">
                                        Supports "x/y" quantity notation (ordered/received) and separate tax column
                                    </p>
                                </div>

                                <p class="text-xs text-blue-600 dark:text-blue-400">
                                    The format will be automatically detected based on the CSV headers and selected supplier.
                                </p>
                            </div>

                            <!-- Submit Button -->
                            <div class="flex justify-end">
                                <button type="submit"
                                        class="inline-flex items-center px-6 py-3 bg-indigo-600 hover:bg-indigo-700 text-white font-medium rounded-md transition-colors duration-200">
                                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/>
                                    </svg>
                                    Import Delivery
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
    <script>
        // CSV file handling
        function handleFileSelect(event) {
            const file = event.target.files[0];
            if (file) {
                document.getElementById('file-name').textContent = file.name;
                document.getElementById('file-name').classList.remove('hidden');
            }
        }

        function handleDragOver(event) {
            event.preventDefault();
            event.currentTarget.classList.add('border-indigo-500');
        }

        function handleDragLeave(event) {
            event.currentTarget.classList.remove('border-indigo-500');
        }

        function handleDrop(event) {
            event.preventDefault();
            event.currentTarget.classList.remove('border-indigo-500');

            const files = event.dataTransfer.files;
            if (files.length > 0) {
                const file = files[0];
                if (file.type === 'text/csv' || file.name.endsWith('.csv')) {
                    document.getElementById('csv_file').files = files;
                    document.getElementById('file-name').textContent = file.name;
                    document.getElementById('file-name').classList.remove('hidden');
                } else {
                    alert('Please select a CSV file.');
                }
            }
        }

        // PDF file handling - supports multiple files
        function handlePdfSelect(event) {
            const files = event.target.files;
            const fileNamesDiv = document.getElementById('pdf-file-names');

            if (files.length > 0) {
                if (files.length === 1) {
                    fileNamesDiv.innerHTML = `<span class="inline-flex items-center"><svg class="w-4 h-4 mr-1 text-green-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path></svg>${files[0].name}</span>`;
                } else {
                    const fileList = Array.from(files).map(f =>
                        `<span class="inline-flex items-center"><svg class="w-3 h-3 mr-1 text-green-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path></svg>${f.name}</span>`
                    ).join('<br>');
                    fileNamesDiv.innerHTML = `<div class="text-left"><strong>${files.length} files selected:</strong><br>${fileList}</div>`;
                }
                fileNamesDiv.classList.remove('hidden');
                // Hide preview when new files selected
                document.getElementById('pdf-preview').classList.add('hidden');

                // Auto-detect supplier if not already selected
                const supplierSelect = document.getElementById('pdf_supplier_id');
                if (!supplierSelect.value) {
                    detectSupplier(files[0]);
                }
            }
        }

        // Auto-detect supplier from PDF
        async function detectSupplier(file) {
            const statusDiv = document.getElementById('supplier-detection-status');
            const messageSpan = document.getElementById('supplier-detection-message');
            const supplierSelect = document.getElementById('pdf_supplier_id');

            // Show detecting status
            statusDiv.classList.remove('hidden');
            messageSpan.innerHTML = '<span class="text-gray-500"><svg class="animate-spin inline w-4 h-4 mr-1" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg> Detecting supplier...</span>';

            try {
                const formData = new FormData();
                formData.append('pdf_file', file);

                const response = await fetch('{{ route("deliveries.detect-supplier") }}', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json'
                    },
                    body: formData
                });

                const data = await response.json();

                if (data.success && data.supplier_id) {
                    // Auto-select the detected supplier
                    supplierSelect.value = data.supplier_id;
                    messageSpan.innerHTML = `<span class="text-green-600"><svg class="inline w-4 h-4 mr-1" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path></svg> Detected: <strong>${data.detected_name}</strong></span>`;
                } else if (data.success && data.detected_name) {
                    // Detected but couldn't map to a supplier
                    messageSpan.innerHTML = `<span class="text-yellow-600"><svg class="inline w-4 h-4 mr-1" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path></svg> Detected: ${data.detected_name} (please select manually)</span>`;
                } else {
                    messageSpan.innerHTML = '<span class="text-gray-500">Could not detect supplier. Please select manually.</span>';
                }
            } catch (error) {
                console.error('Supplier detection error:', error);
                messageSpan.innerHTML = '<span class="text-gray-500">Could not detect supplier. Please select manually.</span>';
            }
        }

        function handlePdfDrop(event) {
            event.preventDefault();
            event.currentTarget.classList.remove('border-indigo-500');

            const files = event.dataTransfer.files;
            if (files.length > 0) {
                // Validate all files are PDFs
                const invalidFiles = Array.from(files).filter(f =>
                    f.type !== 'application/pdf' && !f.name.toLowerCase().endsWith('.pdf')
                );

                if (invalidFiles.length > 0) {
                    alert('Please select only PDF files. Invalid: ' + invalidFiles.map(f => f.name).join(', '));
                    return;
                }

                // Set files to input
                document.getElementById('pdf_file').files = files;

                // Update display
                const fileNamesDiv = document.getElementById('pdf-file-names');
                if (files.length === 1) {
                    fileNamesDiv.innerHTML = `<span class="inline-flex items-center"><svg class="w-4 h-4 mr-1 text-green-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path></svg>${files[0].name}</span>`;
                } else {
                    const fileList = Array.from(files).map(f =>
                        `<span class="inline-flex items-center"><svg class="w-3 h-3 mr-1 text-green-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path></svg>${f.name}</span>`
                    ).join('<br>');
                    fileNamesDiv.innerHTML = `<div class="text-left"><strong>${files.length} files selected:</strong><br>${fileList}</div>`;
                }
                fileNamesDiv.classList.remove('hidden');
                // Hide preview when new files selected
                document.getElementById('pdf-preview').classList.add('hidden');

                // Auto-detect supplier if not already selected
                const supplierSelect = document.getElementById('pdf_supplier_id');
                if (!supplierSelect.value) {
                    detectSupplier(files[0]);
                }
            }
        }

        // Parse PDF(s) and show preview
        async function parsePdf() {
            const form = document.getElementById('pdf-form');
            const fileInput = document.getElementById('pdf_file');
            const supplierSelect = document.getElementById('pdf_supplier_id');

            if (!fileInput.files || fileInput.files.length === 0) {
                alert('Please select at least one PDF file first.');
                return;
            }

            if (!supplierSelect.value) {
                alert('Please select a supplier first.');
                return;
            }

            const parseBtn = document.getElementById('parse-pdf-btn');
            const originalText = parseBtn.innerHTML;
            const fileCount = fileInput.files.length;
            parseBtn.innerHTML = `<svg class="animate-spin w-4 h-4 mr-2" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg> Parsing ${fileCount} file${fileCount > 1 ? 's' : ''}...`;
            parseBtn.disabled = true;

            try {
                const formData = new FormData();
                // Append all files with array notation
                for (let i = 0; i < fileInput.files.length; i++) {
                    formData.append('pdf_file[]', fileInput.files[i]);
                }
                formData.append('supplier_id', supplierSelect.value);

                const response = await fetch('{{ route("deliveries.parse-pdf") }}', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json'
                    },
                    body: formData
                });

                const data = await response.json();

                if (data.success) {
                    displayPreview(data);
                } else {
                    alert('Failed to parse PDF: ' + data.message);
                }
            } catch (error) {
                console.error('Error parsing PDF:', error);
                alert('Error parsing PDF. Please try again.');
            } finally {
                parseBtn.innerHTML = originalText;
                parseBtn.disabled = false;
            }
        }

        function displayPreview(data) {
            const preview = document.getElementById('pdf-preview');
            const itemCount = document.getElementById('pdf-item-count');
            const productsTotal = document.getElementById('pdf-products-total');
            const barrelsTotal = document.getElementById('pdf-barrels-total');
            const barrelsSummary = document.getElementById('pdf-barrels-summary');
            const totalValue = document.getElementById('pdf-total-value');
            const confidenceBadge = document.getElementById('pdf-confidence');
            const filesProcessedDiv = document.getElementById('pdf-files-processed');
            const filesList = document.getElementById('pdf-files-list');
            const warningsDiv = document.getElementById('pdf-warnings');
            const warningsList = document.getElementById('pdf-warnings-list');
            const itemsBody = document.getElementById('pdf-items-body');
            const barrelsSection = document.getElementById('pdf-barrels-section');
            const barrelsCount = document.getElementById('pdf-barrels-count');
            const barrelsBody = document.getElementById('pdf-barrels-body');

            // Update summary with products/barrels breakdown
            itemCount.textContent = data.totals.line_count;
            const productsValue = data.totals.products_total || data.totals.total_value;
            const barrelsValue = data.totals.barrels_total || 0;
            const grandTotal = data.totals.total_value;

            productsTotal.textContent = '\u20AC' + productsValue.toFixed(2);
            totalValue.textContent = '\u20AC' + grandTotal.toFixed(2);

            // Show barrels summary if there are barrels
            if (barrelsValue > 0) {
                barrelsTotal.textContent = '\u20AC' + barrelsValue.toFixed(2);
                barrelsSummary.classList.remove('hidden');
            } else {
                barrelsSummary.classList.add('hidden');
            }

            // Display barrels section if there are barrel items
            if (data.barrels && data.barrels.items && data.barrels.items.length > 0) {
                barrelsCount.textContent = data.barrels.items.length;
                barrelsBody.innerHTML = data.barrels.items.map(item => `
                    <tr>
                        <td class="py-1">${item.qty}</td>
                        <td class="py-1">${item.description}</td>
                        <td class="py-1 text-right">\u20AC${item.total.toFixed(2)}</td>
                    </tr>
                `).join('');
                barrelsSection.classList.remove('hidden');
            } else {
                barrelsSection.classList.add('hidden');
            }

            // Update confidence badge
            const confidence = data.confidence;
            confidenceBadge.textContent = confidence.toFixed(0) + '% confidence';
            if (confidence >= 90) {
                confidenceBadge.className = 'text-xs px-2 py-1 rounded-full bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200';
            } else if (confidence >= 70) {
                confidenceBadge.className = 'text-xs px-2 py-1 rounded-full bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200';
            } else {
                confidenceBadge.className = 'text-xs px-2 py-1 rounded-full bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200';
            }

            // Update totals verification section
            const totalsVerification = document.getElementById('pdf-totals-verification');
            const totals = data.totals;

            if (totals.grand_stated !== null && totals.grand_stated !== undefined) {
                const match = totals.totals_match;
                const bgClass = match
                    ? 'bg-green-50 dark:bg-green-900/20 border-green-200 dark:border-green-700'
                    : 'bg-red-50 dark:bg-red-900/20 border-red-200 dark:border-red-700';
                const headerClass = match
                    ? 'text-green-800 dark:text-green-200'
                    : 'text-red-800 dark:text-red-200';
                const icon = match
                    ? '<svg class="w-4 h-4 inline mr-1" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path></svg>'
                    : '<svg class="w-4 h-4 inline mr-1" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path></svg>';

                const checkMark = (val1, val2) => {
                    if (val1 === null || val2 === null) return '';
                    return Math.abs(val1 - val2) <= 0.50
                        ? '<span class="text-green-600 dark:text-green-400">&#10003;</span>'
                        : '<span class="text-red-600 dark:text-red-400">&#10007;</span>';
                };

                const formatVal = (val) => val !== null && val !== undefined ? '\u20AC' + val.toFixed(2) : 'N/A';

                let html = `
                    <div class="${bgClass} border rounded-lg p-3">
                        <h5 class="text-xs font-semibold ${headerClass} mb-2">
                            ${icon} ${match ? 'Totals Verified' : 'Totals Mismatch Detected'}
                        </h5>
                        <table class="text-xs w-full">
                            <thead>
                                <tr class="text-gray-500 dark:text-gray-400">
                                    <th class="text-left py-1"></th>
                                    <th class="text-right py-1">PDF States</th>
                                    <th class="text-right py-1">Parsed</th>
                                    <th class="text-center py-1 w-8"></th>
                                </tr>
                            </thead>
                            <tbody class="text-gray-700 dark:text-gray-300">`;

                // Products row
                if (totals.products_stated !== null) {
                    html += `
                                <tr>
                                    <td class="py-1">Products:</td>
                                    <td class="text-right py-1">${formatVal(totals.products_stated)}</td>
                                    <td class="text-right py-1">${formatVal(totals.products_calculated)}</td>
                                    <td class="text-center py-1">${checkMark(totals.products_stated, totals.products_calculated)}</td>
                                </tr>`;
                }

                // Barrels row (if applicable)
                if (totals.barrels_stated !== null && totals.barrels_stated > 0) {
                    html += `
                                <tr>
                                    <td class="py-1">Barrels:</td>
                                    <td class="text-right py-1">${formatVal(totals.barrels_stated)}</td>
                                    <td class="text-right py-1">${formatVal(totals.barrels_calculated)}</td>
                                    <td class="text-center py-1">${checkMark(totals.barrels_stated, totals.barrels_calculated)}</td>
                                </tr>`;
                }

                // Grand total row
                html += `
                                <tr class="font-semibold border-t border-gray-200 dark:border-gray-600">
                                    <td class="py-1">Grand Total:</td>
                                    <td class="text-right py-1">${formatVal(totals.grand_stated)}</td>
                                    <td class="text-right py-1">${formatVal(totals.grand_calculated)}</td>
                                    <td class="text-center py-1">${checkMark(totals.grand_stated, totals.grand_calculated)}</td>
                                </tr>
                            </tbody>
                        </table>`;

                // Warning message if mismatch
                if (!match && totals.discrepancy !== null && totals.discrepancy > 0.50) {
                    html += `
                        <p class="mt-2 text-xs text-red-700 dark:text-red-300 font-semibold">
                            Warning: \u20AC${totals.discrepancy.toFixed(2)} discrepancy - some items may not have been parsed!
                        </p>`;
                }

                html += `</div>`;

                totalsVerification.innerHTML = html;
                totalsVerification.classList.remove('hidden');
            } else {
                totalsVerification.classList.add('hidden');
            }

            // Update files processed (for multi-file uploads)
            if (data.file_results && data.file_results.length > 1) {
                filesList.innerHTML = data.file_results.map(f => {
                    const icon = f.success
                        ? '<svg class="w-4 h-4 text-green-500 inline mr-1" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path></svg>'
                        : '<svg class="w-4 h-4 text-red-500 inline mr-1" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"></path></svg>';
                    const valueStr = f.total_value ? ` - \u20AC${f.total_value.toFixed(2)}` : '';
                    return `<li>${icon}${f.filename} - ${f.item_count} items${valueStr}</li>`;
                }).join('');
                filesProcessedDiv.classList.remove('hidden');
            } else {
                filesProcessedDiv.classList.add('hidden');
            }

            // Update warnings
            if (data.warnings && data.warnings.length > 0) {
                warningsList.innerHTML = data.warnings.map(w => '<li>' + w + '</li>').join('');
                warningsDiv.classList.remove('hidden');

                // Show unparsed line details if available
                if (data.unmatched_lines && data.unmatched_lines.length > 0) {
                    let unmatchedHtml = '<li class="mt-2"><strong>Unparsed lines for review:</strong>';
                    unmatchedHtml += '<ul class="ml-4 mt-1 text-xs opacity-75 font-mono max-h-32 overflow-y-auto">';
                    data.unmatched_lines.forEach(line => {
                        const prefix = line.filename ? '[' + line.filename + '] ' : '';
                        unmatchedHtml += '<li class="truncate" title="' + line.content + '">' + prefix + 'L' + line.line_num + ': ' + line.content + '</li>';
                    });
                    unmatchedHtml += '</ul></li>';
                    warningsList.innerHTML += unmatchedHtml;
                }
            } else {
                warningsDiv.classList.add('hidden');
            }

            // Update items table
            itemsBody.innerHTML = data.items.map(item => `
                <tr class="hover:bg-gray-50 dark:hover:bg-gray-800">
                    <td class="px-2 py-1 text-gray-900 dark:text-gray-100">${item.Code}</td>
                    <td class="px-2 py-1 text-gray-600 dark:text-gray-400 max-w-[200px] truncate" title="${item.Product}">${item.Product}</td>
                    <td class="px-2 py-1 text-right text-gray-900 dark:text-gray-100">${item.Total_Delivered_Units}</td>
                    <td class="px-2 py-1 text-right text-gray-900 dark:text-gray-100">\u20AC${parseFloat(item.Unit_Cost).toFixed(2)}</td>
                    <td class="px-2 py-1 text-right text-gray-900 dark:text-gray-100">\u20AC${parseFloat(item.Value).toFixed(2)}</td>
                </tr>
            `).join('');

            // Show preview
            preview.classList.remove('hidden');
        }
    </script>
    @endpush
</x-admin-layout>
