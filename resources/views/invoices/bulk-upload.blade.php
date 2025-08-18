<x-admin-layout>
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
        {{-- Header --}}
        <div class="flex justify-between items-center mb-6">
            <h2 class="text-2xl font-bold text-gray-100">Bulk Invoice Upload</h2>
            <a href="{{ route('invoices.index') }}" 
               class="bg-gray-600 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded">
                Back to Invoices
            </a>
        </div>

        {{-- Upload Interface --}}
        <div class="bg-gray-800 rounded-lg p-6 mb-6" x-data="bulkUpload()">
            <div class="mb-4">
                <h3 class="text-lg font-semibold text-gray-100 mb-2">Upload Invoice Files</h3>
                <p class="text-gray-400 text-sm">
                    Upload up to {{ $maxFiles }} invoice files at once. Supported formats: 
                    {{ implode(', ', array_map('strtoupper', $allowedExtensions)) }}. 
                    Maximum file size: {{ $maxFileSize }}MB each.
                </p>
            </div>

            {{-- Drag and Drop Zone --}}
            <div class="border-2 border-dashed border-gray-600 rounded-lg p-8 text-center hover:border-gray-500 transition-colors"
                 :class="{ 'border-blue-500 bg-blue-900/20': isDragging }"
                 @dragover.prevent="isDragging = true"
                 @dragleave.prevent="isDragging = false"
                 @drop.prevent="handleDrop($event)">
                
                <div x-show="!hasFiles">
                    <svg class="mx-auto h-12 w-12 text-gray-400 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                              d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/>
                    </svg>
                    <p class="text-gray-300 mb-2">Drag and drop invoice files here, or</p>
                    <label for="file-input" class="inline-flex items-center px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded cursor-pointer">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/>
                        </svg>
                        Browse Files
                    </label>
                    <input id="file-input" type="file" class="hidden" multiple 
                           accept=".pdf,.jpg,.jpeg,.png,.tiff,.tif"
                           @change="handleFileSelect($event)">
                </div>

                {{-- File List --}}
                <div x-show="hasFiles" class="text-left">
                    <div class="flex justify-between items-center mb-4">
                        <h4 class="text-gray-200 font-medium">
                            Selected Files (<span x-text="files.length"></span>/{{ $maxFiles }})
                        </h4>
                        <button @click="clearFiles()" 
                                class="text-sm text-red-400 hover:text-red-300">
                            Clear All
                        </button>
                    </div>

                    <div class="space-y-2 max-h-96 overflow-y-auto">
                        <template x-for="(file, index) in files" :key="index">
                            <div class="flex items-center justify-between bg-gray-700 rounded p-3">
                                <div class="flex items-center space-x-3 flex-1">
                                    {{-- File Icon --}}
                                    <div class="flex-shrink-0">
                                        <svg x-show="file.type.includes('pdf')" class="w-8 h-8 text-red-400" fill="currentColor" viewBox="0 0 20 20">
                                            <path d="M4 18h12a2 2 0 002-2V6.414A2 2 0 0017.414 5L14 1.586A2 2 0 0012.586 1H4a2 2 0 00-2 2v13a2 2 0 002 2z"/>
                                        </svg>
                                        <svg x-show="file.type.includes('image')" class="w-8 h-8 text-blue-400" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M4 3a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V5a2 2 0 00-2-2H4zm12 12H4l4-8 3 6 2-4 3 6z" clip-rule="evenodd"/>
                                        </svg>
                                    </div>
                                    
                                    {{-- File Info --}}
                                    <div class="flex-1 min-w-0">
                                        <p class="text-gray-200 text-sm font-medium truncate" x-text="file.name"></p>
                                        <p class="text-gray-400 text-xs">
                                            <span x-text="formatFileSize(file.size)"></span>
                                            <span x-show="file.error" class="text-red-400 ml-2" x-text="file.error"></span>
                                        </p>
                                    </div>

                                    {{-- Progress Bar (shown during upload) --}}
                                    <div x-show="file.uploading" class="flex-1 max-w-xs">
                                        <div class="bg-gray-600 rounded-full h-2">
                                            <div class="bg-blue-500 h-2 rounded-full transition-all duration-300"
                                                 :style="`width: ${file.progress || 0}%`"></div>
                                        </div>
                                    </div>

                                    {{-- Status --}}
                                    <div x-show="!file.uploading && file.uploaded" class="flex-shrink-0">
                                        <span class="text-green-400 text-sm">✓ Uploaded</span>
                                    </div>

                                    {{-- Remove Button --}}
                                    <button @click="removeFile(index)" 
                                            :disabled="file.uploading"
                                            class="text-gray-400 hover:text-red-400 ml-2">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                        </svg>
                                    </button>
                                </div>
                            </div>
                        </template>
                    </div>

                    {{-- Upload Actions --}}
                    <div class="mt-6 flex justify-between items-center">
                        <div class="text-sm text-gray-400">
                            <span x-show="totalSize > 0">
                                Total size: <span x-text="formatFileSize(totalSize)"></span>
                            </span>
                        </div>
                        <div class="space-x-3">
                            <label for="file-input" class="inline-flex items-center px-4 py-2 bg-gray-600 hover:bg-gray-700 text-white rounded cursor-pointer">
                                Add More Files
                            </label>
                            <button @click="uploadFiles()" 
                                    :disabled="isUploading || files.length === 0"
                                    class="inline-flex items-center px-6 py-2 bg-blue-600 hover:bg-blue-700 disabled:bg-gray-600 disabled:cursor-not-allowed text-white font-medium rounded">
                                <svg x-show="!isUploading" class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/>
                                </svg>
                                <svg x-show="isUploading" class="animate-spin h-4 w-4 mr-2" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                <span x-text="isUploading ? 'Uploading...' : 'Upload Files'"></span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Error Messages --}}
            <div x-show="errors.length > 0" class="mt-4">
                <div class="bg-red-900/50 border border-red-600 rounded-lg p-4">
                    <h4 class="text-red-300 font-medium mb-2">Upload Errors:</h4>
                    <ul class="list-disc list-inside text-red-400 text-sm">
                        <template x-for="error in errors" :key="error">
                            <li x-text="error"></li>
                        </template>
                    </ul>
                </div>
            </div>

            {{-- Success Message --}}
            <div x-show="successMessage" class="mt-4">
                <div class="bg-green-900/50 border border-green-600 rounded-lg p-4">
                    <p class="text-green-300" x-text="successMessage"></p>
                </div>
            </div>
        </div>

        {{-- Recent Uploads --}}
        @if($recentUploads->count() > 0)
        <div class="bg-gray-800 rounded-lg p-6">
            <h3 class="text-lg font-semibold text-gray-100 mb-4">Recent Uploads</h3>
            <div class="space-y-3">
                @foreach($recentUploads as $upload)
                <div class="bg-gray-700 rounded p-4">
                    <div class="flex justify-between items-start">
                        <div>
                            <p class="text-gray-200 font-medium">{{ $upload->batch_id }}</p>
                            <p class="text-gray-400 text-sm">
                                {{ $upload->total_files }} files • 
                                {{ $upload->created_at->diffForHumans() }}
                            </p>
                            <div class="flex space-x-4 mt-1 text-sm">
                                <span class="text-green-400">✓ {{ $upload->successful_files }} completed</span>
                                @if($upload->failed_files > 0)
                                <span class="text-red-400">✗ {{ $upload->failed_files }} failed</span>
                                @endif
                                @if($upload->processed_files < $upload->total_files)
                                <span class="text-yellow-400">⏳ {{ $upload->total_files - $upload->processed_files }} pending</span>
                                @endif
                            </div>
                        </div>
                        <div class="flex space-x-2">
                            <a href="{{ route('invoices.bulk-upload.preview', $upload->batch_id) }}" 
                               class="text-blue-400 hover:text-blue-300 text-sm">
                                View Details
                            </a>
                            @if($upload->status === 'completed')
                            <span class="px-2 py-1 bg-green-900 text-green-300 text-xs rounded">Completed</span>
                            @elseif($upload->status === 'processing')
                            <span class="px-2 py-1 bg-yellow-900 text-yellow-300 text-xs rounded">Processing</span>
                            @else
                            <span class="px-2 py-1 bg-gray-600 text-gray-300 text-xs rounded">{{ ucfirst($upload->status) }}</span>
                            @endif
                        </div>
                    </div>
                </div>
                @endforeach
            </div>
        </div>
        @endif
    </div>

    @push('scripts')
    <script>
        function bulkUpload() {
            return {
                isDragging: false,
                isUploading: false,
                files: [],
                errors: [],
                successMessage: '',
                maxFiles: {{ $maxFiles }},
                maxSizeMB: {{ $maxFileSize }},
                allowedExtensions: @json($allowedExtensions),
                
                get hasFiles() {
                    return this.files.length > 0;
                },
                
                get totalSize() {
                    return this.files.reduce((sum, file) => sum + file.size, 0);
                },
                
                handleDrop(event) {
                    this.isDragging = false;
                    const droppedFiles = Array.from(event.dataTransfer.files);
                    this.addFiles(droppedFiles);
                },
                
                handleFileSelect(event) {
                    const selectedFiles = Array.from(event.target.files);
                    this.addFiles(selectedFiles);
                    event.target.value = ''; // Reset input
                },
                
                addFiles(newFiles) {
                    this.errors = [];
                    this.successMessage = '';
                    
                    // Check total file count
                    if (this.files.length + newFiles.length > this.maxFiles) {
                        this.errors.push(`You can only upload ${this.maxFiles} files at once. You have ${this.files.length} files selected.`);
                        return;
                    }
                    
                    // Validate and add each file
                    newFiles.forEach(file => {
                        // Check file extension
                        const ext = file.name.split('.').pop().toLowerCase();
                        if (!this.allowedExtensions.includes(ext)) {
                            this.errors.push(`${file.name}: Invalid file type. Only ${this.allowedExtensions.join(', ').toUpperCase()} files are allowed.`);
                            return;
                        }
                        
                        // Check file size
                        if (file.size > this.maxSizeMB * 1024 * 1024) {
                            this.errors.push(`${file.name}: File is too large. Maximum size is ${this.maxSizeMB}MB.`);
                            return;
                        }
                        
                        // Check for duplicates
                        if (this.files.some(f => f.name === file.name && f.size === file.size)) {
                            this.errors.push(`${file.name}: File already added.`);
                            return;
                        }
                        
                        // Add file with metadata
                        this.files.push({
                            file: file,
                            name: file.name,
                            size: file.size,
                            type: file.type,
                            progress: 0,
                            uploading: false,
                            uploaded: false,
                            error: null
                        });
                    });
                },
                
                removeFile(index) {
                    this.files.splice(index, 1);
                    if (this.files.length === 0) {
                        this.errors = [];
                        this.successMessage = '';
                    }
                },
                
                clearFiles() {
                    this.files = [];
                    this.errors = [];
                    this.successMessage = '';
                },
                
                formatFileSize(bytes) {
                    const units = ['B', 'KB', 'MB', 'GB'];
                    let size = bytes;
                    let unitIndex = 0;
                    
                    while (size >= 1024 && unitIndex < units.length - 1) {
                        size /= 1024;
                        unitIndex++;
                    }
                    
                    return size.toFixed(2) + ' ' + units[unitIndex];
                },
                
                async uploadFiles() {
                    if (this.files.length === 0 || this.isUploading) return;
                    
                    this.isUploading = true;
                    this.errors = [];
                    this.successMessage = '';
                    
                    // Prepare form data
                    const formData = new FormData();
                    this.files.forEach((fileObj, index) => {
                        formData.append('files[]', fileObj.file);
                        fileObj.uploading = true;
                    });
                    
                    try {
                        // Send files to server
                        const response = await fetch('{{ route("invoices.bulk-upload.upload") }}', {
                            method: 'POST',
                            headers: {
                                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                                'Accept': 'application/json',
                            },
                            body: formData
                        });
                        
                        const data = await response.json();
                        
                        if (data.success) {
                            this.successMessage = data.message;
                            
                            // Mark all files as uploaded
                            this.files.forEach(fileObj => {
                                fileObj.uploading = false;
                                fileObj.uploaded = true;
                                fileObj.progress = 100;
                            });
                            
                            // Redirect to preview page after a short delay
                            if (data.redirect_url) {
                                setTimeout(() => {
                                    window.location.href = data.redirect_url;
                                }, 1500);
                            }
                        } else {
                            throw new Error(data.error || 'Upload failed');
                        }
                    } catch (error) {
                        this.errors.push(error.message);
                        this.files.forEach(fileObj => {
                            fileObj.uploading = false;
                            fileObj.error = 'Upload failed';
                        });
                    } finally {
                        this.isUploading = false;
                    }
                }
            }
        }
    </script>
    @endpush
</x-admin-layout>