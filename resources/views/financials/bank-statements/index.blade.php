<x-admin-layout>
    <div class="p-6">
        <!-- Header -->
        <div class="mb-6 flex justify-between items-start">
            <div>
                <h1 class="text-3xl font-bold text-gray-900 dark:text-white">Bank Statement Import</h1>
                <p class="text-gray-600 dark:text-gray-400 mt-2">
                    Upload bank statements in CSV format to automatically import transactions for reconciliation.
                </p>
            </div>
            <div class="flex gap-2">
                <a href="{{ route('management.bank-statements.analysis') }}" 
                   class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                    <svg class="w-5 h-5 inline-block mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                    </svg>
                    Analysis & Validation
                </a>
                <a href="{{ route('management.bank-statements.reconciliation') }}" 
                   class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors">
                    <svg class="w-5 h-5 inline-block mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    Reconciliation
                </a>
            </div>
        </div>

        <!-- Success/Error Messages -->
        @if(session('success'))
        <div class="mb-6 p-4 rounded-lg bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200 flex items-center">
            <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
            </svg>
            {{ session('success') }}
        </div>
        @endif

        @if(session('warning'))
        <div class="mb-6 p-4 rounded-lg bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200 flex items-center">
            <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
            </svg>
            {{ session('warning') }}
        </div>
        @endif

        @if(session('error'))
        <div class="mb-6 p-4 rounded-lg bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200 flex items-center">
            <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
            </svg>
            {{ session('error') }}
        </div>
        @endif

        @if($errors->any())
        <div class="mb-6 p-4 rounded-lg bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200">
            <div class="flex items-center mb-2">
                <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                </svg>
                <span class="font-medium">Please fix the following issues:</span>
            </div>
            <ul class="list-disc list-inside space-y-1">
                @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
        @endif

        <!-- Upload Form and History (Alpine.js scope) -->
        <div x-data="bankStatementUpload()"
                 x-init="init()">
            
            <!-- Upload Form -->
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow mb-6">
                <div class="p-6 border-b border-gray-200 dark:border-gray-700">
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Upload New Statement</h2>
                    <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                        Select a CSV or TXT file exported from your bank. File size limit: 10MB.
                    </p>
                </div>
                
                <form @submit.prevent="handleSubmit()" enctype="multipart/form-data"
                @csrf
                <div class="p-6">
                    <!-- File Drop Zone -->
                    <div class="mb-4" 
                         @dragover.prevent="dragover = true"
                         @dragleave.prevent="dragover = false"
                         @drop.prevent="dragover = false; $refs.fileInput.files = $event.dataTransfer.files; $refs.fileName.textContent = $event.dataTransfer.files[0]?.name || 'No file selected'">
                        <label for="statement_csv" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Bank Statement File
                        </label>
                        <div :class="dragover ? 'border-blue-500 bg-blue-50 dark:bg-blue-900/10' : 'border-gray-300 dark:border-gray-600'"
                             class="border-2 border-dashed rounded-lg p-6 text-center transition-colors hover:border-blue-400">
                            <div class="space-y-2">
                                <svg class="mx-auto h-12 w-12 text-gray-400" stroke="currentColor" fill="none" viewBox="0 0 48 48">
                                    <path d="M28 8H12a4 4 0 00-4 4v20m32-12v8m0 0v8a4 4 0 01-4 4H12a4 4 0 01-4-4v-4m32-4l-3.172-3.172a4 4 0 00-5.656 0L28 28M8 32l9.172-9.172a4 4 0 015.656 0L28 28m0 0l4 4m4-24h8m-4-4v8m-12 4h.02" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                                <div class="text-sm text-gray-600 dark:text-gray-400">
                                    <label for="statement_csv" class="cursor-pointer">
                                        <span class="font-medium text-blue-600 hover:text-blue-500">Click to upload</span>
                                        or drag and drop
                                    </label>
                                    <input type="file" 
                                           name="statement_csv" 
                                           id="statement_csv" 
                                           x-ref="fileInput"
                                           @change="$refs.fileName.textContent = $event.target.files[0]?.name || 'No file selected'"
                                           accept=".csv,.txt"
                                           class="sr-only">
                                </div>
                                <p class="text-xs text-gray-500 dark:text-gray-400">CSV, TXT up to 10MB</p>
                            </div>
                        </div>
                        <p class="text-sm text-gray-600 dark:text-gray-400 mt-2" x-ref="fileName">No file selected</p>
                    </div>

                    <!-- Processing Status -->
                    <div x-show="processing" class="mb-4 p-4 rounded-lg bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300">
                        <div class="flex items-center">
                            <svg class="animate-spin -ml-1 mr-3 h-4 w-4 text-blue-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            <span x-text="processingMessage || 'Processing your file...'"></span>
                        </div>
                    </div>

                    <!-- Upload Button -->
                    <div class="flex justify-between items-center">
                        <button type="submit" 
                                :disabled="uploading || processing"
                                :class="(uploading || processing) ? 'bg-gray-400 cursor-not-allowed' : 'bg-blue-600 hover:bg-blue-700'"
                                class="px-6 py-2 text-white rounded-md font-medium transition-colors">
                            <span x-show="!uploading && !processing">Upload and Process</span>
                            <span x-show="uploading" class="flex items-center">
                                <svg class="animate-spin -ml-1 mr-3 h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                Uploading...
                            </span>
                            <span x-show="processing" class="flex items-center">
                                <svg class="animate-spin -ml-1 mr-3 h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                Processing...
                            </span>
                        </button>
                        <a href="{{ route('management.bank-statements.reconciliation') }}" 
                           class="text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300 font-medium">
                            View Reconciliation →
                        </a>
                    </div>
                </div>
            </form>
            </div>

            <!-- Statistics Cards -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Total Transactions</p>
                        <p class="text-2xl font-bold text-gray-900 dark:text-white" data-stat="total-transactions">
                            {{ number_format($recentTransactions->count() > 0 ? \App\Models\BankTransaction::count() : 0) }}
                        </p>
                    </div>
                    <div class="p-3 rounded-full bg-blue-100 dark:bg-blue-900/30">
                        <svg class="w-6 h-6 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                        </svg>
                    </div>
                </div>
            </div>

            <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Upload Files</p>
                        <p class="text-2xl font-bold text-gray-900 dark:text-white" data-stat="upload-files">{{ $uploadHistory->count() }}</p>
                    </div>
                    <div class="p-3 rounded-full bg-green-100 dark:bg-green-900/30">
                        <svg class="w-6 h-6 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/>
                        </svg>
                    </div>
                </div>
            </div>

            <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Latest Upload</p>
                        <p class="text-lg font-semibold text-gray-900 dark:text-white" data-stat="latest-upload">
                            @if($uploadHistory->first())
                                {{ \Carbon\Carbon::parse($uploadHistory->first()->uploaded_at)->diffForHumans() }}
                            @else
                                No uploads yet
                            @endif
                        </p>
                    </div>
                    <div class="p-3 rounded-full bg-purple-100 dark:bg-purple-900/30">
                        <svg class="w-6 h-6 text-purple-600 dark:text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    </div>
                </div>
            </div>
        </div>

        <!-- Upload History -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow">
            <div class="p-6 border-b border-gray-200 dark:border-gray-700">
                <div class="flex justify-between items-center">
                    <div>
                        <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Upload History</h2>
                        <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">Recent bank statement uploads and their processing results.</p>
                    </div>
                    <div class="flex gap-2 items-center">
                        <!-- Refreshing indicator -->
                        <div x-show="refreshingHistory" class="flex items-center text-sm text-blue-600 dark:text-blue-400">
                            <svg class="animate-spin -ml-1 mr-2 h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            Refreshing...
                        </div>
                        
                        <button @click="refreshUploadHistory()" 
                                :disabled="refreshingHistory"
                                :class="refreshingHistory ? 'bg-gray-400 cursor-not-allowed' : 'bg-blue-600 hover:bg-blue-700'"
                                class="px-3 py-2 text-white text-sm rounded-md transition-colors">
                            🔄 Refresh
                        </button>
                        
                        <form action="{{ route('management.bank-statements.cleanup-duplicates') }}" method="POST" 
                              onsubmit="return confirm('This will remove all duplicate transactions. Are you sure?')">
                            @csrf
                            <button type="submit" class="px-3 py-2 bg-yellow-600 text-white text-sm rounded-md hover:bg-yellow-700 transition-colors">
                                🧹 Clean Duplicates
                            </button>
                        </form>
                    </div>
                </div>
            </div>
            
            @if($uploadHistory->count() > 0)
            <div class="overflow-x-auto" data-upload-history-container>
                <table class="w-full">
                    <thead class="bg-gray-50 dark:bg-gray-700">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">File Name</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Transactions</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Date Range</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Uploaded</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700" data-upload-history-tbody>
                        @foreach($uploadHistory as $upload)
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="flex items-center">
                                    <div class="p-2 rounded bg-blue-100 dark:bg-blue-900/30 mr-3">
                                        <svg class="w-4 h-4 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                        </svg>
                                    </div>
                                    <div>
                                        <div class="text-sm font-medium text-gray-900 dark:text-white">
                                            {{ $upload->source_filename }}
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300">
                                    {{ number_format($upload->transaction_count) }} transactions
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                @if($upload->date_from && $upload->date_to)
                                    {{ \Carbon\Carbon::parse($upload->date_from)->format('M j') }} - {{ \Carbon\Carbon::parse($upload->date_to)->format('M j, Y') }}
                                @else
                                    No date info
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                {{ \Carbon\Carbon::parse($upload->uploaded_at)->format('M j, Y g:i A') }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                <div class="flex items-center gap-2">
                                    <a href="{{ route('management.bank-statements.reconciliation') }}" 
                                       class="text-blue-600 hover:text-blue-900 dark:text-blue-400 dark:hover:text-blue-300">
                                        View →
                                    </a>
                                    <form action="{{ route('management.bank-statements.delete') }}" method="POST" 
                                          onsubmit="return confirm('This will permanently delete all {{ number_format($upload->transaction_count) }} transactions from {{ $upload->source_filename }}. Are you sure?')"
                                          class="inline">
                                        @csrf
                                        @method('DELETE')
                                        <input type="hidden" name="filename" value="{{ $upload->source_filename }}">
                                        <button type="submit" class="text-red-600 hover:text-red-900 dark:text-red-400 dark:hover:text-red-300 ml-2">
                                            🗑️ Delete
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
            @else
            <div class="p-8 text-center" data-empty-state>
                <svg class="mx-auto h-12 w-12 text-gray-400 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
                <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-2">No uploads yet</h3>
                <p class="text-gray-500 dark:text-gray-400">Upload your first bank statement CSV file to get started.</p>
            </div>
            @endif
        </div>
        </div> <!-- Close Alpine.js scope -->
    </div>

    <script>
        function bankStatementUpload() {
            return {
                uploading: false,
                dragover: false,
                processing: false,
                processingMessage: '',
                uploadedFilename: '',
                statusCheckAttempts: 0,
                maxStatusCheckAttempts: 30,
                refreshingHistory: false,
                reconciliationRoute: '{{ route("management.bank-statements.reconciliation") }}',
                deleteRoute: '{{ route("management.bank-statements.delete") }}',
                csrfToken: '{{ csrf_token() }}',
                
                init() {
                    // Any initialization logic
                },
                
                handleSubmit() {
                    const fileInput = document.querySelector('#statement_csv');
                    const file = fileInput.files[0];
                    
                    if (!file) {
                        alert('Please select a file to upload.');
                        return;
                    }
                    
                    this.uploading = true;
                    this.processingMessage = 'Uploading file...';
                    this.uploadedFilename = file.name;
                    
                    const formData = new FormData();
                    formData.append('statement_csv', file);
                    formData.append('_token', this.csrfToken);
                    
                    fetch('{{ route("management.bank-statements.store") }}', {
                        method: 'POST',
                        body: formData,
                        headers: {
                            'Accept': 'application/json',
                        },
                        credentials: 'same-origin'
                    })
                    .then(async response => {
                        const data = await response.json();
                        this.uploading = false;
                        
                        if (response.ok && data.success) {
                            this.processing = true;
                            this.processingMessage = data.message || 'Processing file...';
                            this.statusCheckAttempts = 0;
                            // Clear the form
                            fileInput.value = '';
                            document.querySelector('[x-ref="fileName"]').textContent = 'No file selected';
                            // Start checking processing status with initial delay
                            console.log('Upload successful, starting status checks in 5 seconds...');
                            setTimeout(() => this.checkProcessingStatus(), 5000);
                        } else {
                            alert(data.error || 'Upload failed. Please try again.');
                            this.processing = false;
                            this.processingMessage = '';
                        }
                    })
                    .catch(error => {
                        console.error('Upload error:', error);
                        this.uploading = false;
                        this.processing = false;
                        alert('Upload failed. Please try again.');
                    });
                },
                
                checkProcessingStatus() {
                    if (!this.uploadedFilename) return;
                    
                    this.statusCheckAttempts++;
                    const timestamp = new Date().toLocaleTimeString();
                    console.log(`[${timestamp}] Status check #${this.statusCheckAttempts} for:`, this.uploadedFilename);
                    
                    // Stop checking after max attempts and fall back to refresh
                    if (this.statusCheckAttempts > this.maxStatusCheckAttempts) {
                        console.log('Max status check attempts reached, falling back to history refresh');
                        this.processing = false;
                        this.processingMessage = '';
                        this.refreshUploadHistory();
                        return;
                    }
                    
                    fetch('{{ route('management.bank-statements.status') }}?filename=' + encodeURIComponent(this.uploadedFilename), {
                        credentials: 'same-origin'
                    })
                        .then(response => {
                            console.log(`[${timestamp}] Status response:`, response.status, response.statusText);
                            if (!response.ok) {
                                throw new Error('Status check failed: ' + response.status);
                            }
                            return response.json();
                        })
                        .then(data => {
                            console.log(`[${timestamp}] Status data:`, data);
                            
                            if (data.status === 'processing') {
                                this.processing = true;
                                this.processingMessage = data.message || 'Processing file...';
                                // Use exponential backoff but cap at 10 seconds
                                const delay = Math.min(2000 + (this.statusCheckAttempts * 500), 10000);
                                console.log(`Processing continues, next check in ${delay}ms`);
                                setTimeout(() => this.checkProcessingStatus(), delay);
                            } else if (data.status === 'completed') {
                                console.log('✅ Processing completed! Refreshing history...');
                                this.processing = false;
                                this.processingMessage = '';
                                
                                // Show completion message with duplicate info if available
                                if (data.message) {
                                    this.showSuccessMessage(data.message);
                                }
                                
                                this.refreshUploadHistory();
                            } else if (data.status === 'failed') {
                                console.log('❌ Processing failed:', data.error);
                                this.processing = false;
                                this.processingMessage = '';
                                alert('Processing failed: ' + (data.error || 'Unknown error'));
                            } else if (data.status === 'not_found') {
                                // Job might still be queued, keep checking with longer delay
                                console.log('Status not found, job may still be queued. Checking again...');
                                const delay = Math.min(3000 + (this.statusCheckAttempts * 1000), 15000);
                                setTimeout(() => this.checkProcessingStatus(), delay);
                            } else {
                                console.log('Unknown status:', data.status, 'trying again...');
                                setTimeout(() => this.checkProcessingStatus(), 5000);
                            }
                        })
                        .catch(error => {
                            console.error(`[${timestamp}] Status check error:`, error);
                            // For authentication or network errors, try a few more times with increasing delay
                            const delay = Math.min(5000 + (this.statusCheckAttempts * 2000), 20000);
                            console.log(`Will retry in ${delay}ms (attempt ${this.statusCheckAttempts}/${this.maxStatusCheckAttempts})`);
                            setTimeout(() => this.checkProcessingStatus(), delay);
                        });
                },
                
                refreshUploadHistory() {
                    if (this.refreshingHistory) return;
                    
                    this.refreshingHistory = true;
                    console.log('🔄 Refreshing upload history...');
                    
                    fetch('{{ route('management.bank-statements.history') }}', {
                        credentials: 'same-origin'
                    })
                        .then(response => {
                            if (!response.ok) {
                                throw new Error('History fetch failed: ' + response.status);
                            }
                            return response.json();
                        })
                        .then(data => {
                            console.log('✅ Upload history refreshed successfully');
                            this.updateStatistics(data);
                            this.updateUploadHistoryTable(data.uploadHistory);
                            this.refreshingHistory = false;
                        })
                        .catch(error => {
                            console.error('❌ Error refreshing upload history:', error);
                            this.refreshingHistory = false;
                            // Show error message but don't reload automatically
                            console.log('History refresh failed, you may need to refresh the page');
                        });
                },
                
                updateStatistics(data) {
                    const totalElement = document.querySelector('[data-stat=total-transactions]');
                    if (totalElement) {
                        totalElement.textContent = new Intl.NumberFormat().format(data.totalTransactions);
                    }
                    
                    const filesElement = document.querySelector('[data-stat=upload-files]');
                    if (filesElement) {
                        filesElement.textContent = data.uploadHistory.length;
                    }
                    
                    const latestElement = document.querySelector('[data-stat=latest-upload]');
                    if (latestElement && data.uploadHistory.length > 0) {
                        const latestUpload = new Date(data.uploadHistory[0].uploaded_at);
                        latestElement.textContent = this.timeAgo(latestUpload);
                    }
                },
                
                updateUploadHistoryTable(uploadHistory) {
                    const tableContainer = document.querySelector('[data-upload-history-container]');
                    const emptyState = document.querySelector('[data-empty-state]');
                    
                    if (uploadHistory.length > 0) {
                        if (emptyState) emptyState.style.display = 'none';
                        if (tableContainer) tableContainer.style.display = 'block';
                        
                        const tbody = document.querySelector('[data-upload-history-tbody]');
                        if (tbody) {
                            tbody.innerHTML = this.generateTableRows(uploadHistory);
                        }
                    } else {
                        if (tableContainer) tableContainer.style.display = 'none';
                        if (emptyState) emptyState.style.display = 'block';
                    }
                },
                
                generateTableRows(uploadHistory) {
                    return uploadHistory.map(upload => {
                        const dateFrom = upload.date_from ? new Date(upload.date_from).toLocaleDateString('en-US', { month: 'short', day: 'numeric' }) : '';
                        const dateTo = upload.date_to ? new Date(upload.date_to).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' }) : '';
                        const uploadedAt = new Date(upload.uploaded_at).toLocaleDateString('en-US', { 
                            month: 'short', day: 'numeric', year: 'numeric', hour: 'numeric', minute: '2-digit'
                        });
                        
                        const dateRange = dateFrom && dateTo ? dateFrom + ' - ' + dateTo : 'No date info';
                        const transactionCount = new Intl.NumberFormat().format(upload.transaction_count);
                        const confirmMessage = 'This will permanently delete all ' + transactionCount + ' transactions from ' + upload.source_filename + '. Are you sure?';
                        
                        return '<tr class="hover:bg-gray-50 dark:hover:bg-gray-700">' +
                            '<td class="px-6 py-4 whitespace-nowrap">' +
                                '<div class="flex items-center">' +
                                    '<div class="p-2 rounded bg-blue-100 dark:bg-blue-900/30 mr-3">' +
                                        '<svg class="w-4 h-4 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">' +
                                            '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>' +
                                        '</svg>' +
                                    '</div>' +
                                    '<div class="text-sm font-medium text-gray-900 dark:text-white">' +
                                        upload.source_filename +
                                    '</div>' +
                                '</div>' +
                            '</td>' +
                            '<td class="px-6 py-4 whitespace-nowrap">' +
                                '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300">' +
                                    transactionCount + ' transactions' +
                                '</span>' +
                            '</td>' +
                            '<td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">' +
                                dateRange +
                            '</td>' +
                            '<td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">' +
                                uploadedAt +
                            '</td>' +
                            '<td class="px-6 py-4 whitespace-nowrap text-sm font-medium">' +
                                '<div class="flex items-center gap-2">' +
                                    '<a href="' + this.reconciliationRoute + '" class="text-blue-600 hover:text-blue-900 dark:text-blue-400 dark:hover:text-blue-300">' +
                                        'View →' +
                                    '</a>' +
                                    '<form action="' + this.deleteRoute + '" method="POST" onsubmit="return confirm(\'' + confirmMessage + '\')" class="inline">' +
                                        '<input type="hidden" name="_token" value="' + this.csrfToken + '">' +
                                        '<input type="hidden" name="_method" value="DELETE">' +
                                        '<input type="hidden" name="filename" value="' + upload.source_filename + '">' +
                                        '<button type="submit" class="text-red-600 hover:text-red-900 dark:text-red-400 dark:hover:text-red-300 ml-2">' +
                                            '🗑️ Delete' +
                                        '</button>' +
                                    '</form>' +
                                '</div>' +
                            '</td>' +
                        '</tr>';
                    }).join('');
                },
                
                timeAgo(date) {
                    const now = new Date();
                    const diffInSeconds = Math.floor((now - date) / 1000);
                    
                    if (diffInSeconds < 60) return 'Just now';
                    if (diffInSeconds < 3600) return Math.floor(diffInSeconds / 60) + ' minutes ago';
                    if (diffInSeconds < 86400) return Math.floor(diffInSeconds / 3600) + ' hours ago';
                    return Math.floor(diffInSeconds / 86400) + ' days ago';
                },
                
                showSuccessMessage(message) {
                    // Remove any existing dynamic success messages
                    const existingMessage = document.querySelector('[data-dynamic-success]');
                    if (existingMessage) {
                        existingMessage.remove();
                    }
                    
                    // Create new success message
                    const successDiv = document.createElement('div');
                    successDiv.setAttribute('data-dynamic-success', 'true');
                    successDiv.className = 'mb-6 p-4 rounded-lg bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200 flex items-center';
                    successDiv.innerHTML = `
                        <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                        </svg>
                        ${message}
                        <button onclick="this.parentElement.remove()" class="ml-auto text-green-600 hover:text-green-800 dark:text-green-400 dark:hover:text-green-200">
                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/>
                            </svg>
                        </button>
                    `;
                    
                    // Insert after header but before upload form
                    const header = document.querySelector('.mb-6 h1').closest('.mb-6');
                    header.insertAdjacentElement('afterend', successDiv);
                    
                    // Auto-hide after 10 seconds
                    setTimeout(() => {
                        if (successDiv.parentElement) {
                            successDiv.remove();
                        }
                    }, 10000);
                }
            }
        }
    </script>
</x-admin-layout>