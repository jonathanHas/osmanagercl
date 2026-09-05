<x-admin-layout>
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
        {{-- Header --}}
        <div class="flex justify-between items-center mb-6">
            <div class="flex items-center gap-3">
                <h2 class="text-2xl font-bold text-gray-100">Bulk Invoice Upload</h2>
                <x-ai-provider-badge feature="invoice_parsing" label="AI" />
            </div>
            <a href="{{ route('invoices.index') }}"
               class="bg-gray-600 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded">
                Back to Invoices
            </a>
        </div>

        {{-- Tab Switcher --}}
        <div class="mb-6" x-data="{ activeTab: window.innerWidth < 768 ? 'camera' : 'upload' }">
            <div class="flex border-b border-gray-700 mb-0">
                <button @click="activeTab = 'upload'"
                        :class="activeTab === 'upload' ? 'border-blue-500 text-blue-400' : 'border-transparent text-gray-400 hover:text-gray-300'"
                        class="flex items-center px-6 py-3 border-b-2 font-medium text-sm transition-colors">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/>
                    </svg>
                    Upload Files
                </button>
                <button @click="activeTab = 'camera'"
                        :class="activeTab === 'camera' ? 'border-blue-500 text-blue-400' : 'border-transparent text-gray-400 hover:text-gray-300'"
                        class="flex items-center px-6 py-3 border-b-2 font-medium text-sm transition-colors">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"/>
                    </svg>
                    Camera Capture
                </button>
            </div>

            {{-- Camera Capture Tab --}}
            <div x-show="activeTab === 'camera'" x-data="cameraCapture()">
                <div class="bg-gray-800 rounded-b-lg p-6">
                    <div class="mb-4">
                        <h3 class="text-lg font-semibold text-gray-100 mb-2">Camera Invoice Capture</h3>
                        <p class="text-gray-400 text-sm">
                            Take photos of paper invoices with your phone camera. Each photo is automatically sent
                            for AI processing -- keep snapping without waiting for results.
                        </p>
                    </div>

                    {{-- Capture Button --}}
                    <div class="text-center mb-6">
                        <label for="camera-input"
                               class="inline-flex items-center justify-center px-8 py-4 bg-blue-600 hover:bg-blue-700 active:bg-blue-800 text-white text-lg font-medium rounded-xl cursor-pointer transition-colors touch-manipulation">
                            <svg class="w-6 h-6 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"/>
                            </svg>
                            Take Photo of Invoice
                        </label>
                        <input id="camera-input" type="file" accept="image/*" capture="environment" class="hidden"
                               @change="handleCapture($event)">

                        <p class="text-gray-500 text-xs mt-2">or</p>

                        <label for="gallery-input"
                               class="inline-flex items-center mt-2 px-4 py-2 bg-gray-600 hover:bg-gray-700 text-white text-sm rounded cursor-pointer transition-colors">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                            </svg>
                            Choose from Gallery
                        </label>
                        <input id="gallery-input" type="file" accept="image/*" multiple class="hidden"
                               @change="handleGallerySelect($event)">
                    </div>

                    {{-- Captured Photos Grid --}}
                    <div x-show="photos.length > 0">
                        <div class="flex justify-between items-center mb-3">
                            <h4 class="text-gray-200 font-medium">
                                Captured Invoices (<span x-text="photos.length"></span>)
                            </h4>
                            <button @click="clearAll()"
                                    class="text-sm text-red-400 hover:text-red-300"
                                    x-show="!photos.some(p => p.status === 'uploading')">
                                Clear All
                            </button>
                        </div>

                        <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-3">
                            <template x-for="(photo, index) in photos" :key="photo.id">
                                <div class="relative bg-gray-700 rounded-lg overflow-hidden aspect-[3/4]">
                                    {{-- Thumbnail --}}
                                    <img :src="photo.preview" class="w-full h-full object-cover" alt="Invoice photo">

                                    {{-- Status Overlay --}}
                                    <div class="absolute inset-0 flex items-center justify-center"
                                         :class="{
                                             'bg-black/40': photo.status === 'uploading' || photo.status === 'processing',
                                             'bg-black/0': photo.status === 'done',
                                             'bg-red-900/40': photo.status === 'failed'
                                         }">
                                        {{-- Uploading spinner --}}
                                        <div x-show="photo.status === 'uploading'" class="text-center">
                                            <svg class="animate-spin h-8 w-8 text-blue-400 mx-auto" fill="none" viewBox="0 0 24 24">
                                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                            </svg>
                                            <span class="text-white text-xs mt-1 block">Uploading...</span>
                                        </div>

                                        {{-- Processing spinner --}}
                                        <div x-show="photo.status === 'processing'" class="text-center">
                                            <svg class="animate-spin h-8 w-8 text-yellow-400 mx-auto" fill="none" viewBox="0 0 24 24">
                                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                            </svg>
                                            <span class="text-white text-xs mt-1 block">AI Processing...</span>
                                        </div>

                                        {{-- Done checkmark --}}
                                        <div x-show="photo.status === 'done'" class="absolute top-2 right-2">
                                            <span class="bg-green-500 text-white rounded-full w-6 h-6 flex items-center justify-center text-sm font-bold shadow">
                                                &#10003;
                                            </span>
                                        </div>

                                        {{-- Failed --}}
                                        <div x-show="photo.status === 'failed'" class="text-center">
                                            <svg class="h-8 w-8 text-red-400 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4.5c-.77-.833-2.694-.833-3.464 0L3.34 16.5c-.77.833.192 2.5 1.732 2.5z"/>
                                            </svg>
                                            <span class="text-red-300 text-xs mt-1 block">Failed</span>
                                        </div>
                                    </div>

                                    {{-- Remove button --}}
                                    <button @click="removePhoto(index)"
                                            x-show="photo.status !== 'uploading'"
                                            class="absolute top-1 left-1 bg-black/60 hover:bg-black/80 text-white rounded-full w-6 h-6 flex items-center justify-center text-xs">
                                        &#10005;
                                    </button>

                                    {{-- Supplier detected label --}}
                                    <div x-show="photo.supplier" class="absolute bottom-0 left-0 right-0 bg-black/70 px-2 py-1">
                                        <p class="text-green-300 text-xs truncate" x-text="photo.supplier"></p>
                                    </div>
                                </div>
                            </template>
                        </div>

                        {{-- Summary & Actions --}}
                        <div class="mt-4 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3">
                            <div class="text-sm text-gray-400">
                                <span x-show="processingCount > 0" class="text-yellow-400">
                                    <svg class="animate-spin h-4 w-4 inline mr-1" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                    <span x-text="processingCount"></span> processing...
                                </span>
                                <span x-show="doneCount > 0" class="text-green-400 ml-2">
                                    &#10003; <span x-text="doneCount"></span> done
                                </span>
                                <span x-show="failedCount > 0" class="text-red-400 ml-2">
                                    &#10005; <span x-text="failedCount"></span> failed
                                </span>
                            </div>
                            <a x-show="batchId && doneCount > 0"
                               :href="`{{ url('invoices/bulk-upload/preview') }}/${batchId}`"
                               class="inline-flex items-center px-6 py-2 bg-green-600 hover:bg-green-700 text-white font-medium rounded transition-colors">
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                </svg>
                                View Results
                            </a>
                        </div>
                    </div>

                    {{-- Error Messages --}}
                    <div x-show="error" class="mt-4">
                        <div class="bg-red-900/50 border border-red-600 rounded-lg p-4">
                            <p class="text-red-300 text-sm" x-text="error"></p>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Upload Files Tab --}}
            <div x-show="activeTab === 'upload'">
        {{-- Upload Interface --}}
        <div class="bg-gray-800 rounded-b-lg p-6" x-data="bulkUpload()" x-init="initFolder()">
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
                           accept=".pdf,.jpg,.jpeg,.png,.tiff,.tif,.doc,.docx,.xls,.xlsx,.odt,.ods"
                           @change="handleFileSelect($event)">

                    {{-- Folder mode: read the invoices straight out of a folder on this PC so the
                         processed originals can be moved to Processed/ afterwards. --}}
                    {{-- No folder remembered yet: pick one. --}}
                    <template x-if="folderSupported && !inboxHandle">
                        <div class="mt-4">
                            <button type="button" @click="selectFolder()"
                                    class="inline-flex items-center px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded">
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                          d="M3 7a2 2 0 012-2h4l2 2h6a2 2 0 012 2v7a2 2 0 01-2 2H5a2 2 0 01-2-2V7z"/>
                                </svg>
                                Select Folder
                            </button>
                            <p class="text-gray-500 text-xs mt-2 max-w-lg mx-auto">
                                Pick your invoice folder once. This page will remember it, and can move the
                                invoices it creates into a <span class="font-mono text-gray-400">Processed</span>
                                subfolder when you're done. Choose
                                <span class="text-gray-400 font-semibold">Allow on every visit</span> if Chrome offers it.
                            </p>
                        </div>
                    </template>

                    {{-- Remembered, but Chrome dropped the grant: one click to reconnect. --}}
                    <template x-if="folderSupported && inboxHandle && inboxPermission !== 'granted'">
                        <div class="mt-4">
                            <button type="button" @click="loadFromInbox()"
                                    class="inline-flex items-center px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded">
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                          d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                                </svg>
                                Reconnect to "<span x-text="inboxName"></span>"
                            </button>
                            <p class="text-gray-500 text-xs mt-2 max-w-lg mx-auto">
                                Chrome needs you to confirm access again. Pick
                                <span class="text-gray-400 font-semibold">Allow on every visit</span> to stop it asking.
                                <button type="button" @click="selectFolder()" class="underline hover:text-gray-300">Use a different folder</button>
                            </p>
                        </div>
                    </template>

                    {{-- Remembered and permitted, but the folder is empty (files auto-load otherwise). --}}
                    <template x-if="folderSupported && inboxHandle && inboxPermission === 'granted'">
                        <div class="mt-4">
                            <button type="button" @click="loadFromInbox(false)"
                                    class="inline-flex items-center px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded">
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                          d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                                </svg>
                                Recheck "<span x-text="inboxName"></span>"
                            </button>
                            <p class="text-gray-500 text-xs mt-2 max-w-lg mx-auto">
                                No new invoices found in this folder.
                                <button type="button" @click="selectFolder()" class="underline hover:text-gray-300">Use a different folder</button>
                            </p>
                        </div>
                    </template>

                    {{-- Two separate reasons the folder API can be missing; say which one applies. --}}
                    <template x-if="!folderSupported && !secureContext">
                        <p class="text-gray-500 text-xs mt-4 max-w-lg mx-auto">
                            Automatic folder tidy-up needs a secure context, and this page is served over plain HTTP.
                            In Chrome or Edge, open
                            <span class="font-mono text-gray-400">chrome://flags/#unsafely-treat-insecure-origin-as-secure</span>,
                            set the dropdown to <span class="text-gray-400 font-semibold">Enabled</span> (not just filling in the box),
                            add <span class="font-mono text-gray-400">{{ config('app.url') }}</span>, then click
                            <span class="text-gray-400 font-semibold">Relaunch</span>.
                        </p>
                    </template>

                    <template x-if="!folderSupported && secureContext">
                        <p class="text-gray-500 text-xs mt-4 max-w-lg mx-auto">
                            Automatic folder tidy-up isn't available in this browser &mdash; it needs Chrome or Edge on
                            desktop. Firefox and Safari don't implement the folder access API.
                        </p>
                    </template>
                </div>

                {{-- File List --}}
                <div x-show="hasFiles" class="text-left">
                    <div class="flex justify-between items-center mb-4">
                        <h4 class="text-gray-200 font-medium">
                            Selected Files (<span x-text="files.length"></span>/{{ $maxFiles }})
                            <span x-show="folderName" class="block text-xs font-normal text-emerald-400 mt-1">
                                from folder <span class="font-mono" x-text="folderName"></span>
                                <button type="button" @click="selectFolder()"
                                        class="ml-2 underline text-gray-400 hover:text-gray-200">change</button>
                            </span>
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
                                        {{-- Word Document Icon --}}
                                        <svg x-show="file.type.includes('word') || file.name.toLowerCase().endsWith('.doc') || file.name.toLowerCase().endsWith('.docx')" class="w-8 h-8 text-blue-600" fill="currentColor" viewBox="0 0 20 20">
                                            <path d="M4 18h12a2 2 0 002-2V6.414A2 2 0 0017.414 5L14 1.586A2 2 0 0012.586 1H4a2 2 0 00-2 2v13a2 2 0 002 2zm8-13V2l4 4h-3a1 1 0 01-1-1z"/>
                                        </svg>
                                        {{-- Excel Document Icon --}}
                                        <svg x-show="file.type.includes('sheet') || file.name.toLowerCase().endsWith('.xls') || file.name.toLowerCase().endsWith('.xlsx')" class="w-8 h-8 text-green-600" fill="currentColor" viewBox="0 0 20 20">
                                            <path d="M4 18h12a2 2 0 002-2V6.414A2 2 0 0017.414 5L14 1.586A2 2 0 0012.586 1H4a2 2 0 00-2 2v13a2 2 0 002 2zm8-13V2l4 4h-3a1 1 0 01-1-1z"/>
                                        </svg>
                                        {{-- Generic File Icon for other types --}}
                                        <svg x-show="!file.type.includes('pdf') && !file.type.includes('image') && !file.type.includes('word') && !file.name.toLowerCase().match(/\\.(doc|docx|xls|xlsx)$/)" class="w-8 h-8 text-gray-400" fill="currentColor" viewBox="0 0 20 20">
                                            <path d="M9 2a1 1 0 000 2h2a1 1 0 100-2H9z"/>
                                            <path fill-rule="evenodd" d="M4 5a2 2 0 012-2 1 1 0 000 2H4v10h12V5h-2a1 1 0 100-2 2 2 0 012 2v11a2 2 0 01-2 2H6a2 2 0 01-2-2V5z" clip-rule="evenodd"/>
                                        </svg>
                                    </div>
                                    
                                    {{-- File Info --}}
                                    <div class="flex-1 min-w-0">
                                        <p class="text-gray-200 text-sm font-medium truncate" x-text="file.name"></p>
                                        <p class="text-gray-400 text-xs">
                                            <span x-show="file.compressing" class="text-yellow-400">Compressing...</span>
                                            <span x-show="!file.compressing && file.compressed" class="text-green-400" x-text="formatFileSize(file.originalSize) + ' → ' + formatFileSize(file.size)"></span>
                                            <span x-show="!file.compressing && !file.compressed" x-text="formatFileSize(file.size)"></span>
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
                                    :disabled="isUploading || files.length === 0 || files.some(f => f.compressing)"
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
            </div>{{-- /upload tab --}}
        </div>{{-- /tab switcher --}}

        {{-- Upload History --}}
        @if($recentUploads->total() > 0)
        <div class="bg-gray-800 rounded-lg p-6">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-semibold text-gray-100">Upload History</h3>
                <span class="text-gray-400 text-sm">{{ $recentUploads->total() }} {{ \Illuminate\Support\Str::plural('batch', $recentUploads->total()) }}</span>
            </div>
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
            @if($recentUploads->hasPages())
            <div class="mt-4">
                {{ $recentUploads->links() }}
            </div>
            @endif
        </div>
        @endif
    </div>

    @include('invoices.partials.folder-sync-script')

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
                maxImageDimension: 2000,
                imageQuality: 0.7,

                // Folder mode (File System Access API, Chrome/Edge in a secure context only)
                folderSupported: window.InvoiceFolderSync ? window.InvoiceFolderSync.isSupported() : false,
                secureContext: window.isSecureContext === true,
                dirHandle: null,      // where the CURRENT selection came from
                folderName: '',
                inboxHandle: null,    // the remembered default folder, survives Clear All
                inboxName: '',
                inboxPermission: null,
                chunkMaxFiles: {{ $chunkMaxFiles }},
                chunkMaxBytes: {{ $chunkMaxBytes }},

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

                // On page open, reuse the folder the user picked last time. When Chrome still
                // holds permission ("Allow on every visit") we can list the folder with no
                // prompt and no user gesture, so the invoices are simply already there.
                async initFolder() {
                    if (!this.folderSupported) {
                        return;
                    }

                    const saved = await window.InvoiceFolderSync.recallInbox();
                    if (!saved || !saved.dirHandle) {
                        return;
                    }

                    this.inboxHandle = saved.dirHandle;
                    this.inboxName = saved.folderName || saved.dirHandle.name;
                    this.inboxPermission = await window.InvoiceFolderSync.permissionState(saved.dirHandle);

                    if (this.inboxPermission === 'granted' && this.files.length === 0) {
                        await this.loadFromInbox(true);
                    }
                },

                // auto=true is the no-gesture path and must not call requestPermission().
                async loadFromInbox(auto = false) {
                    if (!this.inboxHandle) {
                        return;
                    }

                    this.errors = [];
                    this.successMessage = '';

                    try {
                        if (!auto) {
                            const granted = await window.InvoiceFolderSync.ensurePermission(this.inboxHandle);
                            this.inboxPermission = granted ? 'granted' : 'prompt';
                            if (!granted) {
                                this.errors.push(`Permission to read "${this.inboxName}" was denied.`);
                                return;
                            }
                        }

                        this.dirHandle = this.inboxHandle;
                        this.folderName = this.inboxName;
                        await this.addFilesFromFolder(this.inboxHandle);
                    } catch (error) {
                        if (!auto) {
                            this.errors.push('Could not read that folder: ' + error.message);
                        }
                    }
                },

                // Read the invoices out of a folder on this machine, keeping the directory
                // handle so the preview page can move the processed originals afterwards.
                async selectFolder() {
                    this.errors = [];
                    this.successMessage = '';

                    try {
                        const handle = await window.InvoiceFolderSync.pickFolder();
                        if (!handle) {
                            return; // user cancelled
                        }

                        this.inboxHandle = handle;
                        this.inboxName = handle.name;
                        this.inboxPermission = 'granted';
                        await window.InvoiceFolderSync.rememberInbox(handle);

                        this.dirHandle = handle;
                        this.folderName = handle.name;
                        await this.addFilesFromFolder(handle);
                    } catch (error) {
                        this.errors.push('Could not read that folder: ' + error.message);
                    }
                },

                // Shared by selectFolder() and loadFromInbox().
                async addFilesFromFolder(handle) {
                    const files = await window.InvoiceFolderSync.listFiles(handle, this.allowedExtensions);

                    if (files.length === 0) {
                        this.errors.push(`No ${this.allowedExtensions.join(', ').toUpperCase()} files found in "${handle.name}".`);
                        return;
                    }

                    // A real invoice folder can hold more than one batch. Take what fits
                    // rather than refusing the lot, and say what's left for the next pass.
                    const room = this.maxFiles - this.files.length;
                    const batch = files.slice(0, room);
                    const remaining = files.length - batch.length;

                    if (batch.length === 0) {
                        this.errors.push(`You already have ${this.maxFiles} files selected. Upload those first.`);
                        return;
                    }

                    this.addFiles(batch);

                    if (remaining > 0) {
                        this.successMessage = `Added ${batch.length} of ${files.length} files (${this.maxFiles} per batch). Load the folder again after uploading to do the remaining ${remaining}.`;
                    }
                },

                compressImage(file, entryIndex) {
                    const maxDim = this.maxImageDimension;
                    const quality = this.imageQuality;
                    const maxSizeMB = this.maxSizeMB;
                    const self = this;

                    const img = document.createElement('img');
                    const url = URL.createObjectURL(file);

                    img.onload = function() {
                        URL.revokeObjectURL(url);

                        let w = img.naturalWidth;
                        let h = img.naturalHeight;

                        if (w > maxDim || h > maxDim) {
                            if (w > h) {
                                h = Math.round(h * (maxDim / w));
                                w = maxDim;
                            } else {
                                w = Math.round(w * (maxDim / h));
                                h = maxDim;
                            }
                        }

                        const canvas = document.createElement('canvas');
                        canvas.width = w;
                        canvas.height = h;
                        const ctx = canvas.getContext('2d');
                        ctx.drawImage(img, 0, 0, w, h);

                        canvas.toBlob(function(blob) {
                            if (blob && blob.size < file.size) {
                                const compressed = new File([blob], file.name, {
                                    type: 'image/jpeg',
                                    lastModified: file.lastModified,
                                });
                                self.files[entryIndex].file = compressed;
                                self.files[entryIndex].size = compressed.size;
                                self.files[entryIndex].type = compressed.type;
                                self.files[entryIndex].compressed = true;
                            }
                            self.files[entryIndex].compressing = false;
                        }, 'image/jpeg', quality);
                    };

                    img.onerror = function() {
                        URL.revokeObjectURL(url);
                        self.files[entryIndex].compressing = false;
                    };

                    img.src = url;
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
                    for (const file of newFiles) {
                        // Check file extension
                        const ext = file.name.split('.').pop().toLowerCase();
                        if (!this.allowedExtensions.includes(ext)) {
                            this.errors.push(`${file.name}: Invalid file type. Only ${this.allowedExtensions.join(', ').toUpperCase()} files are allowed.`);
                            continue;
                        }

                        // Check for duplicates
                        if (this.files.some(f => f.name === file.name)) {
                            this.errors.push(`${file.name}: File already added.`);
                            continue;
                        }

                        const isImage = file.type.startsWith('image/');
                        const entry = {
                            file: file,
                            name: file.name,
                            size: file.size,
                            originalSize: isImage ? file.size : null,
                            type: file.type,
                            progress: 0,
                            uploading: false,
                            uploaded: false,
                            compressed: false,
                            compressing: isImage,
                            error: null
                        };
                        this.files.push(entry);

                        // Compress images client-side (pass index so callback mutates via self.files[i])
                        if (isImage) {
                            this.compressImage(file, this.files.length - 1);
                        }

                        // Check file size for non-images
                        if (!isImage && file.size > this.maxSizeMB * 1024 * 1024) {
                            entry.error = `File is too large (${this.formatFileSize(file.size)}). Maximum size is ${this.maxSizeMB}MB.`;
                        }
                    }
                },
                
                removeFile(index) {
                    this.files.splice(index, 1);
                    if (this.files.length === 0) {
                        this.errors = [];
                        this.successMessage = '';
                    }
                },
                
                clearFiles() {
                    this.dirHandle = null;
                    this.folderName = '';
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
                
                // PHP silently discards anything past max_file_uploads and rejects a POST
                // over post_max_size, so a large selection goes up as several requests that
                // all append to one batch. Ceilings come from the server, not guesswork.
                buildChunks() {
                    const chunks = [];
                    let current = [];
                    let currentBytes = 0;

                    for (const fileObj of this.files) {
                        const size = fileObj.file.size;
                        const wouldExceed = current.length >= this.chunkMaxFiles
                            || (current.length > 0 && currentBytes + size > this.chunkMaxBytes);

                        if (wouldExceed) {
                            chunks.push(current);
                            current = [];
                            currentBytes = 0;
                        }

                        current.push(fileObj);
                        currentBytes += size;
                    }

                    if (current.length > 0) {
                        chunks.push(current);
                    }

                    return chunks;
                },

                async uploadChunk(chunk, batchId) {
                    const formData = new FormData();
                    chunk.forEach(fileObj => formData.append('files[]', fileObj.file));
                    if (batchId) {
                        formData.append('batch_id', batchId);
                    }

                    const response = await fetch('{{ route("invoices.bulk-upload.upload") }}', {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': '{{ csrf_token() }}',
                            'Accept': 'application/json',
                        },
                        body: formData
                    });

                    let data;
                    try {
                        data = await response.json();
                    } catch (error) {
                        // A PHP limit breach can return HTML or an empty body rather than JSON.
                        throw new Error(`Server rejected the upload (HTTP ${response.status}). The files may be too large for one request.`);
                    }

                    if (!data.success) {
                        throw new Error(data.error || (data.message ?? 'Upload failed'));
                    }

                    return data;
                },

                async uploadFiles() {
                    if (this.files.length === 0 || this.isUploading) return;

                    this.isUploading = true;
                    this.errors = [];
                    this.successMessage = '';

                    const chunks = this.buildChunks();
                    let batchId = null;
                    let uploadedCount = 0;
                    let lastResponse = null;

                    try {
                        for (const chunk of chunks) {
                            chunk.forEach(fileObj => {
                                fileObj.uploading = true;
                                fileObj.progress = 0;
                            });

                            lastResponse = await this.uploadChunk(chunk, batchId);
                            batchId = lastResponse.batch_id;
                            uploadedCount += chunk.length;

                            chunk.forEach(fileObj => {
                                fileObj.uploading = false;
                                fileObj.uploaded = true;
                                fileObj.progress = 100;
                            });

                            if (chunks.length > 1) {
                                this.successMessage = `Uploaded ${uploadedCount} of ${this.files.length} files...`;
                            }
                        }

                        this.successMessage = `${uploadedCount} file(s) uploaded successfully. Ready for processing.`;

                        // Remember the source folder against this batch so the preview page
                        // can move the created invoices into Processed/ afterwards.
                        if (this.dirHandle && batchId) {
                            try {
                                await window.InvoiceFolderSync.remember(
                                    batchId,
                                    this.dirHandle,
                                    this.files.map(f => f.name)
                                );
                            } catch (error) {
                                console.warn('Could not remember source folder', error);
                            }
                        }

                        if (lastResponse && lastResponse.redirect_url) {
                            setTimeout(() => {
                                window.location.href = lastResponse.redirect_url;
                            }, 1500);
                        }
                    } catch (error) {
                        // Files from earlier chunks are already safely in the batch; say so
                        // rather than implying the whole upload was lost.
                        this.errors.push(error.message);

                        if (uploadedCount > 0) {
                            this.errors.push(`${uploadedCount} of ${this.files.length} file(s) did upload. Open the batch from Upload History to continue with those.`);
                        }

                        this.files.forEach(fileObj => {
                            fileObj.uploading = false;
                            if (!fileObj.uploaded) {
                                fileObj.error = 'Not uploaded';
                            }
                        });
                    } finally {
                        this.isUploading = false;
                    }
                }
            }
        }

        function cameraCapture() {
            return {
                photos: [],
                batchId: null,
                error: null,
                pollTimer: null,
                photoIdCounter: 0,

                get processingCount() {
                    return this.photos.filter(p => p.status === 'uploading' || p.status === 'processing').length;
                },
                get doneCount() {
                    return this.photos.filter(p => p.status === 'done').length;
                },
                get failedCount() {
                    return this.photos.filter(p => p.status === 'failed').length;
                },

                handleCapture(event) {
                    const file = event.target.files[0];
                    event.target.value = '';
                    if (file) this.processAndUpload(file);
                },

                handleGallerySelect(event) {
                    const files = Array.from(event.target.files);
                    event.target.value = '';
                    files.forEach(f => this.processAndUpload(f));
                },

                async processAndUpload(file) {
                    this.error = null;
                    const id = ++this.photoIdCounter;

                    // Create preview
                    const preview = await this.createPreview(file);
                    const photo = {
                        id,
                        preview,
                        status: 'uploading',
                        fileId: null,
                        supplier: null,
                    };
                    this.photos.push(photo);

                    // Resize image client-side before uploading
                    const resized = await this.resizeImage(file);

                    // Upload to server
                    try {
                        const formData = new FormData();
                        formData.append('image', resized);
                        if (this.batchId) {
                            formData.append('batch_id', this.batchId);
                        }

                        const response = await fetch('{{ route("invoices.bulk-upload.camera-upload") }}', {
                            method: 'POST',
                            headers: {
                                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                                'Accept': 'application/json',
                            },
                            body: formData,
                        });

                        const data = await response.json();

                        if (data.success) {
                            photo.status = 'processing';
                            photo.fileId = data.file_id;

                            if (!this.batchId) {
                                this.batchId = data.batch_id;
                            }

                            this.startPolling();
                        } else {
                            photo.status = 'failed';
                            this.error = data.error || 'Upload failed';
                        }
                    } catch (e) {
                        photo.status = 'failed';
                        this.error = 'Upload failed: ' + e.message;
                    }
                },

                createPreview(file) {
                    return new Promise((resolve) => {
                        const reader = new FileReader();
                        reader.onload = (e) => resolve(e.target.result);
                        reader.readAsDataURL(file);
                    });
                },

                resizeImage(file) {
                    return new Promise((resolve) => {
                        const img = document.createElement('img');
                        const url = URL.createObjectURL(file);
                        img.onload = () => {
                            URL.revokeObjectURL(url);
                            let w = img.naturalWidth;
                            let h = img.naturalHeight;
                            const maxDim = 1600;

                            if (w > maxDim || h > maxDim) {
                                if (w > h) {
                                    h = Math.round(h * (maxDim / w));
                                    w = maxDim;
                                } else {
                                    w = Math.round(w * (maxDim / h));
                                    h = maxDim;
                                }
                            }

                            const canvas = document.createElement('canvas');
                            canvas.width = w;
                            canvas.height = h;
                            canvas.getContext('2d').drawImage(img, 0, 0, w, h);

                            canvas.toBlob((blob) => {
                                resolve(new File([blob], file.name || 'invoice.jpg', {
                                    type: 'image/jpeg',
                                    lastModified: Date.now(),
                                }));
                            }, 'image/jpeg', 0.85);
                        };
                        img.onerror = () => {
                            URL.revokeObjectURL(url);
                            resolve(file);
                        };
                        img.src = url;
                    });
                },

                startPolling() {
                    if (this.pollTimer) return;
                    this.pollTimer = setInterval(() => this.pollStatus(), 3000);
                },

                stopPolling() {
                    if (this.pollTimer) {
                        clearInterval(this.pollTimer);
                        this.pollTimer = null;
                    }
                },

                async pollStatus() {
                    if (!this.batchId) return;

                    // Stop polling if nothing is still processing
                    if (this.processingCount === 0) {
                        this.stopPolling();
                        return;
                    }

                    try {
                        const response = await fetch(`{{ url('invoices/bulk-upload/status') }}/${this.batchId}`, {
                            headers: {
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                            },
                        });

                        const data = await response.json();

                        if (data.files) {
                            for (const serverFile of data.files) {
                                const photo = this.photos.find(p => p.fileId == serverFile.id);
                                if (!photo) continue;

                                if (['parsed', 'review', 'completed'].includes(serverFile.status)) {
                                    photo.status = 'done';
                                    photo.supplier = serverFile.supplier_detected || null;
                                } else if (serverFile.status === 'failed') {
                                    photo.status = 'failed';
                                } else if (['uploaded', 'parsing'].includes(serverFile.status)) {
                                    photo.status = 'processing';
                                }
                            }
                        }

                        if (this.processingCount === 0) {
                            this.stopPolling();
                        }
                    } catch (e) {
                        // Silently ignore polling errors
                    }
                },

                removePhoto(index) {
                    this.photos.splice(index, 1);
                    if (this.photos.length === 0) {
                        this.batchId = null;
                        this.stopPolling();
                    }
                },

                clearAll() {
                    this.photos = [];
                    this.batchId = null;
                    this.error = null;
                    this.stopPolling();
                },

                destroy() {
                    this.stopPolling();
                },
            }
        }
    </script>
    @endpush
</x-admin-layout>