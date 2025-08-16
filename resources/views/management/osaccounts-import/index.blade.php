<x-admin-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-100 leading-tight">
            {{ __('OSAccounts Import Management') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <!-- Status Overview Cards -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <div class="w-8 h-8 bg-blue-100 rounded-full flex items-center justify-center">
                                    @if($status['osaccounts_connection'])
                                        <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                        </svg>
                                    @else
                                        <svg class="w-5 h-5 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                        </svg>
                                    @endif
                                </div>
                            </div>
                            <div class="ml-4">
                                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                                    Database Connection
                                </h3>
                                <p class="text-sm text-gray-500 dark:text-gray-400">
                                    {{ $status['osaccounts_connection'] ? 'Connected' : 'Not Connected' }}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <div class="w-8 h-8 bg-purple-100 rounded-full flex items-center justify-center">
                                    @if($status['supplier_mapping_complete'])
                                        <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                        </svg>
                                    @else
                                        <svg class="w-5 h-5 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z"></path>
                                        </svg>
                                    @endif
                                </div>
                            </div>
                            <div class="ml-4">
                                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                                    Supplier Mapping
                                </h3>
                                <p class="text-sm text-gray-500 dark:text-gray-400">
                                    {{ $status['supplier_mapping_complete'] ? 'Complete' : 'Needs Sync' }}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <div class="w-8 h-8 bg-green-100 rounded-full flex items-center justify-center">
                                    <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                    </svg>
                                </div>
                            </div>
                            <div class="ml-4">
                                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                                    Invoices Imported
                                </h3>
                                <p class="text-xl font-bold text-gray-900 dark:text-white">
                                    {{ number_format($status['invoices_imported']) }}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <div class="w-8 h-8 bg-yellow-100 rounded-full flex items-center justify-center">
                                    <svg class="w-5 h-5 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                    </svg>
                                </div>
                            </div>
                            <div class="ml-4">
                                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                                    Last Import
                                </h3>
                                <p class="text-sm text-gray-500 dark:text-gray-400">
                                    {{ $status['last_import_date'] ? $status['last_import_date']->format('M j, Y') : 'Never' }}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Import Process Steps -->
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg mb-8">
                <div class="p-6">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-6">Import Process</h3>
                    
                    <!-- Step 1: Import Suppliers -->
                    <div class="mb-8" id="suppliers-import-step">
                        <div class="flex items-center mb-4">
                            <span class="flex items-center justify-center w-8 h-8 bg-orange-100 text-orange-800 rounded-full mr-3">1</span>
                            <h4 class="text-lg font-medium text-gray-900 dark:text-white">Import Suppliers</h4>
                        </div>
                        <div class="ml-11 space-y-4">
                            <div class="bg-blue-50 border border-blue-200 rounded-md p-3">
                                <div class="flex">
                                    <div class="flex-shrink-0">
                                        <svg class="h-5 w-5 text-blue-400" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"></path>
                                        </svg>
                                    </div>
                                    <div class="ml-3">
                                        <h3 class="text-sm font-medium text-blue-800">
                                            Important First Step
                                        </h3>
                                        <div class="mt-2 text-sm text-blue-700">
                                            <p><strong>Run this first!</strong> This imports suppliers from OSAccounts EXPENSES table to populate the accounting_suppliers table. Without this, the supplier mapping will show 0 suppliers.</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="flex items-center space-x-2">
                                <input type="checkbox" id="suppliers-dry-run" checked class="rounded">
                                <label for="suppliers-dry-run" class="text-sm text-gray-600 dark:text-gray-300">Dry Run (Preview Only)</label>
                            </div>
                            <div class="flex items-center space-x-2">
                                <input type="checkbox" id="suppliers-force" class="rounded">
                                <label for="suppliers-force" class="text-sm text-gray-600 dark:text-gray-300">Force Import (Update Existing)</label>
                            </div>
                            <button onclick="importSuppliers()" class="bg-orange-500 hover:bg-orange-700 text-white font-bold py-2 px-4 rounded">
                                Import Suppliers from OSAccounts
                            </button>
                            <div id="suppliers-import-output" class="mt-4"></div>
                        </div>
                    </div>

                    <!-- Step 2: Validation -->
                    <div class="mb-8" id="validation-step">
                        <div class="flex items-center mb-4">
                            <span class="flex items-center justify-center w-8 h-8 bg-blue-100 text-blue-800 rounded-full mr-3">2</span>
                            <h4 class="text-lg font-medium text-gray-900 dark:text-white">Pre-Import Validation</h4>
                        </div>
                        <div class="ml-11 space-y-4">
                            <button onclick="validateConnection()" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
                                Test OSAccounts Connection
                            </button>
                            <button onclick="checkSupplierMapping()" class="bg-purple-500 hover:bg-purple-700 text-white font-bold py-2 px-4 rounded ml-2">
                                Check Supplier Mapping
                            </button>
                            <div id="validation-results" class="mt-4"></div>
                        </div>
                    </div>

                    <!-- Step 3: Supplier Sync -->
                    <div class="mb-8" id="supplier-sync-step">
                        <div class="flex items-center mb-4">
                            <span class="flex items-center justify-center w-8 h-8 bg-purple-100 text-purple-800 rounded-full mr-3">3</span>
                            <h4 class="text-lg font-medium text-gray-900 dark:text-white">Supplier Mapping Sync</h4>
                        </div>
                        <div class="ml-11 space-y-4">
                            <div class="flex items-center space-x-2">
                                <input type="checkbox" id="supplier-dry-run" checked class="rounded">
                                <label for="supplier-dry-run" class="text-sm text-gray-600 dark:text-gray-300">Dry Run (Preview Only)</label>
                            </div>
                            <button onclick="syncSuppliers()" class="bg-purple-500 hover:bg-purple-700 text-white font-bold py-2 px-4 rounded">
                                Sync Supplier Mappings
                            </button>
                            <div id="supplier-sync-output" class="mt-4"></div>
                        </div>
                    </div>

                    <!-- Step 4: Invoice Import -->
                    <div class="mb-8" id="invoice-import-step">
                        <div class="flex items-center mb-4">
                            <span class="flex items-center justify-center w-8 h-8 bg-green-100 text-green-800 rounded-full mr-3">4</span>
                            <h4 class="text-lg font-medium text-gray-900 dark:text-white">Invoice Import</h4>
                        </div>
                        <div class="ml-11 space-y-4">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">From Date</label>
                                    <input type="date" id="invoice-date-from" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">To Date</label>
                                    <input type="date" id="invoice-date-to" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                                </div>
                            </div>
                            <div class="space-y-2">
                                <div class="flex items-center space-x-4">
                                    <div class="flex items-center space-x-2">
                                        <input type="checkbox" id="invoice-dry-run" checked class="rounded">
                                        <label for="invoice-dry-run" class="text-sm text-gray-600 dark:text-gray-300">Dry Run</label>
                                    </div>
                                    <div class="flex items-center space-x-2">
                                        <input type="checkbox" id="invoice-force" class="rounded">
                                        <label for="invoice-force" class="text-sm text-gray-600 dark:text-gray-300">Force Update Existing</label>
                                    </div>
                                    <div>
                                        <input type="number" id="invoice-limit" placeholder="Limit (optional)" class="rounded-md border-gray-300 shadow-sm">
                                    </div>
                                </div>
                                <div class="bg-yellow-50 border border-yellow-200 rounded-md p-3">
                                    <div class="flex">
                                        <div class="flex-shrink-0">
                                            <svg class="h-5 w-5 text-yellow-400" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path>
                                            </svg>
                                        </div>
                                        <div class="ml-3">
                                            <h3 class="text-sm font-medium text-yellow-800">
                                                Important Note
                                            </h3>
                                            <div class="mt-2 text-sm text-yellow-700">
                                                <p>The import will automatically skip existing OSAccounts invoices and import only new ones. Check <strong>"Force Update Existing"</strong> to update existing invoices with the latest data from OSAccounts.</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <button onclick="importInvoices()" class="bg-green-500 hover:bg-green-700 text-white font-bold py-2 px-4 rounded">
                                Import Invoices
                            </button>
                            <div id="invoice-import-output" class="mt-4"></div>
                        </div>
                    </div>

                    <!-- Step 5: VAT Lines -->
                    <div class="mb-8" id="vat-lines-step">
                        <div class="flex items-center mb-4">
                            <span class="flex items-center justify-center w-8 h-8 bg-yellow-100 text-yellow-800 rounded-full mr-3">5</span>
                            <h4 class="text-lg font-medium text-gray-900 dark:text-white">VAT Lines Import</h4>
                        </div>
                        <div class="ml-11 space-y-4">
                            <div class="flex items-center space-x-4">
                                <div class="flex items-center space-x-2">
                                    <input type="checkbox" id="vat-dry-run" checked class="rounded">
                                    <label for="vat-dry-run" class="text-sm text-gray-600 dark:text-gray-300">Dry Run</label>
                                </div>
                                <div class="flex items-center space-x-2">
                                    <input type="checkbox" id="vat-force" class="rounded">
                                    <label for="vat-force" class="text-sm text-gray-600 dark:text-gray-300">Force Re-import</label>
                                </div>
                            </div>
                            <button onclick="importVatLines()" class="bg-yellow-500 hover:bg-yellow-700 text-white font-bold py-2 px-4 rounded">
                                Import VAT Lines
                            </button>
                            <div id="vat-lines-output" class="mt-4"></div>
                        </div>
                    </div>

                    <!-- Step 6: Attachments -->
                    <div class="mb-8" id="attachments-step">
                        <div class="flex items-center mb-4">
                            <span class="flex items-center justify-center w-8 h-8 bg-red-100 text-red-800 rounded-full mr-3">6</span>
                            <h4 class="text-lg font-medium text-gray-900 dark:text-white">Attachments Import</h4>
                        </div>
                        <div class="ml-11 space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Base Path to OSManager Files</label>
                                <input type="text" id="attachments-base-path" 
                                       placeholder="{{ env('OSACCOUNTS_FILE_PATH', '/var/www/html/OSManager/invoice_storage') }}" 
                                       value="{{ env('OSACCOUNTS_FILE_PATH', '') }}"
                                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                            </div>
                            <div class="flex items-center space-x-4">
                                <div class="flex items-center space-x-2">
                                    <input type="checkbox" id="attachments-dry-run" checked class="rounded">
                                    <label for="attachments-dry-run" class="text-sm text-gray-600 dark:text-gray-300">Dry Run</label>
                                </div>
                                <div class="flex items-center space-x-2">
                                    <input type="checkbox" id="attachments-force" class="rounded">
                                    <label for="attachments-force" class="text-sm text-gray-600 dark:text-gray-300">Force Re-import</label>
                                </div>
                            </div>
                            <button onclick="importAttachments()" class="bg-red-500 hover:bg-red-700 text-white font-bold py-2 px-4 rounded">
                                Import Attachments
                            </button>
                            <div id="attachments-output" class="mt-4"></div>
                        </div>
                    </div>

                    <!-- Step 7: VAT Returns -->
                    <div class="mb-8" id="vat-returns-step">
                        <div class="flex items-center mb-4">
                            <span class="flex items-center justify-center w-8 h-8 bg-indigo-100 text-indigo-800 rounded-full mr-3">7</span>
                            <h4 class="text-lg font-medium text-gray-900 dark:text-white">VAT Returns Import</h4>
                        </div>
                        <div class="ml-11 space-y-4">
                            <div class="bg-indigo-50 border border-indigo-200 rounded-md p-3">
                                <div class="flex">
                                    <div class="flex-shrink-0">
                                        <svg class="h-5 w-5 text-indigo-400" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"></path>
                                        </svg>
                                    </div>
                                    <div class="ml-3">
                                        <h3 class="text-sm font-medium text-indigo-800">
                                            Historical VAT Returns Recovery
                                        </h3>
                                        <div class="mt-2 text-sm text-indigo-700">
                                            <p><strong>Run this after importing invoices!</strong> This reconstructs historical VAT returns from the OSAccounts 'Assigned' column. It creates VAT return records for each period and calculates totals from the assigned invoices.</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="flex items-center space-x-4">
                                <div class="flex items-center space-x-2">
                                    <input type="checkbox" id="vat-returns-dry-run" checked class="rounded">
                                    <label for="vat-returns-dry-run" class="text-sm text-gray-600 dark:text-gray-300">Dry Run (Preview Only)</label>
                                </div>
                                <div class="flex items-center space-x-2">
                                    <input type="checkbox" id="vat-returns-force" class="rounded">
                                    <label for="vat-returns-force" class="text-sm text-gray-600 dark:text-gray-300">Force Re-import</label>
                                </div>
                            </div>
                            <button onclick="importVatReturns()" class="bg-indigo-500 hover:bg-indigo-700 text-white font-bold py-2 px-4 rounded">
                                Import VAT Returns
                            </button>
                            <div id="vat-returns-output" class="mt-4"></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Import Statistics -->
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Import Statistics</h3>
                        <button onclick="refreshStats()" class="bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded">
                            Refresh Stats
                        </button>
                    </div>
                    <div id="import-stats" class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <!-- Stats will be loaded here -->
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function validateConnection() {
            showSpinner('validation-results');
            fetch('{{ route("management.osaccounts-import.validate-connection") }}')
                .then(response => response.json())
                .then(data => {
                    const resultsDiv = document.getElementById('validation-results');
                    resultsDiv.innerHTML = `
                        <div class="p-4 rounded-md ${data.success ? 'bg-green-50 border border-green-200' : 'bg-red-50 border border-red-200'}">
                            <div class="flex">
                                <div class="flex-shrink-0">
                                    ${data.success ? 
                                        '<svg class="h-5 w-5 text-green-400" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path></svg>' :
                                        '<svg class="h-5 w-5 text-red-400" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"></path></svg>'
                                    }
                                </div>
                                <div class="ml-3">
                                    <h3 class="text-sm font-medium ${data.success ? 'text-green-800' : 'text-red-800'}">
                                        ${data.success ? 'Connection Successful' : 'Connection Failed'}
                                    </h3>
                                    <div class="mt-2 text-sm ${data.success ? 'text-green-700' : 'text-red-700'}">
                                        <p>${data.message}</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    `;
                })
                .catch(error => {
                    showError('validation-results', 'Failed to validate connection: ' + error.message);
                });
        }

        function checkSupplierMapping() {
            showSpinner('validation-results');
            fetch('{{ route("management.osaccounts-import.check-supplier-mapping") }}')
                .then(response => response.json())
                .then(data => {
                    const resultsDiv = document.getElementById('validation-results');
                    if (data.success) {
                        resultsDiv.innerHTML = `
                            <div class="p-4 rounded-md ${data.mapping_complete ? 'bg-green-50 border border-green-200' : 'bg-yellow-50 border border-yellow-200'}">
                                <div class="flex">
                                    <div class="flex-shrink-0">
                                        ${data.mapping_complete ? 
                                            '<svg class="h-5 w-5 text-green-400" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path></svg>' :
                                            '<svg class="h-5 w-5 text-yellow-400" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path></svg>'
                                        }
                                    </div>
                                    <div class="ml-3">
                                        <h3 class="text-sm font-medium ${data.mapping_complete ? 'text-green-800' : 'text-yellow-800'}">
                                            Supplier Mapping Status
                                        </h3>
                                        <div class="mt-2 text-sm ${data.mapping_complete ? 'text-green-700' : 'text-yellow-700'}">
                                            <p>Total Suppliers: ${data.total_suppliers}</p>
                                            <p>Mapped Suppliers: ${data.mapped_suppliers}</p>
                                            <p>Unmapped Suppliers: ${data.unmapped_suppliers}</p>
                                            ${!data.mapping_complete ? '<p><strong>Action Required:</strong> Run supplier sync before importing invoices.</p>' : ''}
                                        </div>
                                    </div>
                                </div>
                            </div>
                        `;
                    } else {
                        showError('validation-results', data.message);
                    }
                })
                .catch(error => {
                    showError('validation-results', 'Failed to check supplier mapping: ' + error.message);
                });
        }

        function importSuppliers() {
            const dryRun = document.getElementById('suppliers-dry-run').checked;
            const force = document.getElementById('suppliers-force').checked;
            startStreamedProcess('{{ route("management.osaccounts-import.import-suppliers") }}', 
                { dry_run: dryRun, force: force }, 'suppliers-import-output');
        }

        function syncSuppliers() {
            const dryRun = document.getElementById('supplier-dry-run').checked;
            startStreamedProcess('{{ route("management.osaccounts-import.sync-suppliers") }}', 
                { dry_run: dryRun }, 'supplier-sync-output');
        }

        function importInvoices() {
            const dateFrom = document.getElementById('invoice-date-from').value;
            const dateTo = document.getElementById('invoice-date-to').value;
            
            if (!dateFrom || !dateTo) {
                alert('Please select both from and to dates');
                return;
            }

            const params = {
                date_from: dateFrom,
                date_to: dateTo,
                dry_run: document.getElementById('invoice-dry-run').checked,
                force: document.getElementById('invoice-force').checked
            };

            const limit = document.getElementById('invoice-limit').value;
            if (limit) {
                params.limit = parseInt(limit);
            }

            startStreamedProcess('{{ route("management.osaccounts-import.import-invoices") }}', params, 'invoice-import-output');
        }

        function importVatLines() {
            const params = {
                dry_run: document.getElementById('vat-dry-run').checked,
                force: document.getElementById('vat-force').checked
            };

            startStreamedProcess('{{ route("management.osaccounts-import.import-vat-lines") }}', params, 'vat-lines-output');
        }

        function importAttachments() {
            const basePath = document.getElementById('attachments-base-path').value;
            
            if (!basePath) {
                alert('Please enter the base path to OSManager files');
                return;
            }

            const params = {
                base_path: basePath,
                dry_run: document.getElementById('attachments-dry-run').checked,
                force: document.getElementById('attachments-force').checked
            };

            startStreamedProcess('{{ route("management.osaccounts-import.import-attachments") }}', params, 'attachments-output');
        }

        function importVatReturns() {
            const params = {
                dry_run: document.getElementById('vat-returns-dry-run').checked,
                force: document.getElementById('vat-returns-force').checked
            };

            startStreamedProcess('{{ route("management.osaccounts-import.import-vat-returns") }}', params, 'vat-returns-output');
        }

        function startStreamedProcess(url, params, outputElementId) {
            console.log('Starting streamed process:', { url, params, outputElementId });
            
            const outputDiv = document.getElementById(outputElementId);
            outputDiv.innerHTML = '<div class="bg-gray-100 p-4 rounded-md"><div class="text-sm font-mono" id="' + outputElementId + '-content">Starting process...</div></div>';
            
            const contentDiv = document.getElementById(outputElementId + '-content');
            
            const formData = new FormData();
            Object.keys(params).forEach(key => {
                if (params[key] !== null && params[key] !== undefined) {
                    console.log('Adding param:', key, '=', params[key]);
                    // Convert booleans to 1/0 for proper Laravel validation
                    if (typeof params[key] === 'boolean') {
                        formData.append(key, params[key] ? '1' : '0');
                    } else {
                        formData.append(key, params[key]);
                    }
                }
            });

            console.log('Making fetch request to:', url);

            fetch(url, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'text/event-stream'
                },
                body: formData
            }).then(response => {
                console.log('Response received:', response.status, response.statusText);
                console.log('Response headers:', Object.fromEntries(response.headers.entries()));
                
                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                }

                const reader = response.body.getReader();
                const decoder = new TextDecoder();
                contentDiv.innerHTML = '<div class="text-blue-600">Connected to stream...</div>';
                let totalBytesReceived = 0;

                function readStream() {
                    return reader.read().then(({ done, value }) => {
                        if (done) {
                            console.log('Stream completed. Total bytes received:', totalBytesReceived);
                            if (contentDiv.innerHTML === '<div class="text-blue-600">Connected to stream...</div>') {
                                contentDiv.innerHTML = '<div class="text-yellow-600">Process completed but no output received.</div>';
                            }
                            return;
                        }

                        totalBytesReceived += value.length;
                        const chunk = decoder.decode(value, { stream: true });
                        console.log('Received chunk (bytes):', value.length, 'Content preview:', chunk.substring(0, 100));
                        
                        // Handle both single lines and multiple lines
                        const events = chunk.split('\n\n');
                        
                        events.forEach(event => {
                            if (event.startsWith('data: ')) {
                                try {
                                    const data = JSON.parse(event.slice(6));
                                    console.log('Parsed SSE data:', data);
                                    
                                    const timestamp = new Date(data.timestamp).toLocaleTimeString();
                                    
                                    let messageClass = 'text-gray-800';
                                    if (data.type === 'error') {
                                        messageClass = 'text-red-600 font-semibold';
                                    } else if (data.type === 'complete') {
                                        messageClass = data.success ? 'text-green-600 font-semibold' : 'text-red-600 font-semibold';
                                    } else if (data.message.includes('❌') || data.message.includes('Error')) {
                                        messageClass = 'text-red-600';
                                    } else if (data.message.includes('✅')) {
                                        messageClass = 'text-green-600';
                                    }

                                    if (contentDiv.innerHTML.includes('Connected to stream...')) {
                                        contentDiv.innerHTML = '';
                                    }
                                    
                                    contentDiv.innerHTML += `<div class="${messageClass}">[${timestamp}] ${data.message}</div>`;
                                    contentDiv.scrollTop = contentDiv.scrollHeight;
                                } catch (e) {
                                    console.error('Failed to parse SSE data:', e, 'Raw event:', event);
                                    // Add raw line if JSON parsing fails
                                    if (event.trim() !== '' && !event.includes('Connected to stream')) {
                                        contentDiv.innerHTML += `<div class="text-gray-600">${event}</div>`;
                                    }
                                }
                            } else if (event.trim() !== '') {
                                console.log('Non-SSE content:', event);
                            }
                        });

                        return readStream();
                    }).catch(streamError => {
                        console.error('Stream reading error:', streamError);
                        contentDiv.innerHTML += `<div class="text-red-600 font-semibold">Stream error: ${streamError.message}</div>`;
                    });
                }

                return readStream();
            }).catch(error => {
                console.error('Fetch error:', error);
                showError(outputElementId, 'Request failed: ' + error.message);
            });
        }

        function refreshStats() {
            fetch('{{ route("management.osaccounts-import.stats") }}')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const statsDiv = document.getElementById('import-stats');
                        const stats = data.stats;
                        statsDiv.innerHTML = `
                            <div class="text-center">
                                <div class="text-2xl font-bold text-gray-900 dark:text-white">${stats.total_invoices.toLocaleString()}</div>
                                <div class="text-sm text-gray-500">Total Invoices</div>
                            </div>
                            <div class="text-center">
                                <div class="text-2xl font-bold text-green-600">${stats.osaccounts_invoices.toLocaleString()}</div>
                                <div class="text-sm text-gray-500">OSAccounts Invoices</div>
                            </div>
                            <div class="text-center">
                                <div class="text-2xl font-bold text-blue-600">${stats.recent_imports.toLocaleString()}</div>
                                <div class="text-sm text-gray-500">Recent Imports (7 days)</div>
                            </div>
                        `;
                    }
                })
                .catch(error => {
                    console.error('Failed to refresh stats:', error);
                });
        }

        function showSpinner(elementId) {
            document.getElementById(elementId).innerHTML = `
                <div class="flex items-center justify-center p-4">
                    <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-gray-900"></div>
                    <span class="ml-2">Loading...</span>
                </div>
            `;
        }

        function showError(elementId, message) {
            document.getElementById(elementId).innerHTML = `
                <div class="p-4 rounded-md bg-red-50 border border-red-200">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <svg class="h-5 w-5 text-red-400" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"></path>
                            </svg>
                        </div>
                        <div class="ml-3">
                            <h3 class="text-sm font-medium text-red-800">Error</h3>
                            <div class="mt-2 text-sm text-red-700">
                                <p>${message}</p>
                            </div>
                        </div>
                    </div>
                </div>
            `;
        }

        // Load stats on page load
        document.addEventListener('DOMContentLoaded', function() {
            refreshStats();
            
            // Set default date range (last month)
            const now = new Date();
            const lastMonth = new Date(now.getFullYear(), now.getMonth() - 1, 1);
            const lastMonthEnd = new Date(now.getFullYear(), now.getMonth(), 0);
            
            document.getElementById('invoice-date-from').value = lastMonth.toISOString().split('T')[0];
            document.getElementById('invoice-date-to').value = lastMonthEnd.toISOString().split('T')[0];
            
            // Set default attachment path from environment
            const envPath = '{{ env('OSACCOUNTS_FILE_PATH', '/var/www/html/OSManager/invoice_storage') }}';
            if (envPath && !document.getElementById('attachments-base-path').value) {
                document.getElementById('attachments-base-path').value = envPath;
            }
        });
    </script>
</x-admin-layout>