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
            
            @if ($errors->any())
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6" role="alert">
                    <ul class="list-disc list-inside">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg">
                <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">Delivery Information</h3>
                    <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                        Upload a CSV file from your supplier to create a new delivery for verification.
                    </p>
                </div>
                
                <form method="POST" action="{{ route('deliveries.store') }}" enctype="multipart/form-data" class="p-6">
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
                        <h4 class="text-sm font-medium text-blue-800 dark:text-blue-200 mb-2">Expected CSV Format</h4>
                        <p class="text-sm text-blue-700 dark:text-blue-300 mb-2">
                            The CSV file should contain the following columns:
                        </p>
                        <div class="text-xs font-mono bg-blue-100 dark:bg-blue-900/40 p-2 rounded border">
                            Code,Ordered,Qty,SKU,Content,Description,Price,Sale,Total
                        </div>
                        <p class="text-xs text-blue-600 dark:text-blue-400 mt-2">
                            This matches the standard Udea delivery export format.
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

    @push('scripts')
    <script>
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
    </script>
    @endpush
</x-admin-layout>