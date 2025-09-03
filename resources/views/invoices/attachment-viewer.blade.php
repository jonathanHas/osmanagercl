<x-admin-layout>
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
        {{-- Header --}}
        <div class="flex justify-between items-center mb-6">
            <h2 class="text-2xl font-bold text-gray-100">View Attachment</h2>
            <div class="flex space-x-2">
                <a href="{{ $downloadUrl }}" 
                   class="bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-4 rounded inline-flex items-center">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                    Download
                </a>
                <a href="{{ route('invoices.show', $attachment->invoice_id) }}" 
                   class="bg-gray-600 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded">
                    Back to Invoice
                </a>
            </div>
        </div>

        {{-- File Info --}}
        <div class="bg-gray-800 rounded-lg p-4 mb-6">
            <div class="flex items-center space-x-4">
                <div class="flex-shrink-0">
                    @php
                        $extension = strtolower(pathinfo($attachment->original_filename, PATHINFO_EXTENSION));
                    @endphp
                    
                    @if($extension === 'pdf')
                        <svg class="w-8 h-8 text-red-500" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M14,2H6A2,2 0 0,0 4,4V20A2,2 0 0,0 6,22H18A2,2 0 0,0 20,20V8L14,2M18,20H6V4H13V9H18V20Z" />
                        </svg>
                    @elseif(in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp']))
                        <svg class="w-8 h-8 text-blue-500" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M8.5,13.5L11,16.5L14.5,12L19,18H5M21,19V5C21,3.89 20.1,3 19,3H5A2,2 0 0,0 3,5V19A2,2 0 0,0 5,21H19A2,2 0 0,0 21,19Z" />
                        </svg>
                    @elseif(in_array($extension, ['doc', 'docx']))
                        <svg class="w-8 h-8 text-blue-600" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M14,2H6A2,2 0 0,0 4,4V20A2,2 0 0,0 6,22H18A2,2 0 0,0 20,20V8L14,2M18,20H6V4H13V9H18V20Z" />
                            <path d="M8,13V11H16V13H8M8,15V17H16V15H8Z" />
                        </svg>
                    @elseif(in_array($extension, ['xls', 'xlsx']))
                        <svg class="w-8 h-8 text-green-600" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M14,2H6A2,2 0 0,0 4,4V20A2,2 0 0,0 6,22H18A2,2 0 0,0 20,20V8L14,2M18,20H6V4H13V9H18V20Z" />
                            <path d="M7,11H9V13H7V11M7,15H9V17H7V15M11,11H13V13H11V11M11,15H13V17H11V15M15,11H17V13H15V11M15,15H17V17H15V15Z" />
                        </svg>
                    @else
                        <svg class="w-8 h-8 text-gray-500" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M13,9V3.5L18.5,9M6,2C4.89,2 4,2.89 4,4V20A2,2 0 0,0 6,22H18A2,2 0 0,0 20,20V8L14,2H6Z" />
                        </svg>
                    @endif
                </div>
                <div>
                    <h3 class="text-lg font-semibold text-gray-100">{{ $attachment->original_filename }}</h3>
                    <p class="text-sm text-gray-400">
                        {{ $attachment->attachment_type_label }} • {{ $attachment->formatted_file_size }} • 
                        Uploaded {{ $attachment->uploaded_at->format('d/m/Y H:i') }}
                    </p>
                    @if($attachment->needsConversion())
                        <p class="text-sm text-blue-400 mt-1">
                            <svg class="w-4 h-4 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                            </svg>
                            Viewing converted PDF version of {{ strtoupper($extension) }} document
                        </p>
                    @endif
                    @if($attachment->description)
                        <p class="text-sm text-gray-300 mt-1">{{ $attachment->description }}</p>
                    @endif
                </div>
            </div>
        </div>

        {{-- File Viewer --}}
        <div class="bg-gray-800 rounded-lg overflow-hidden">
            @php
                $extension = strtolower(pathinfo($attachment->original_filename, PATHINFO_EXTENSION));
            @endphp
            
            @if($extension === 'pdf' || $attachment->needsConversion())
                {{-- PDF Viewer (native PDF or converted document) --}}
                <div class="relative" style="height: 80vh;">
                    <embed src="{{ $viewUrl }}" 
                           type="application/pdf" 
                           class="w-full h-full"
                           title="{{ $attachment->original_filename }}">
                    
                    {{-- Fallback if embed doesn't work --}}
                    <div class="absolute inset-0 flex items-center justify-center bg-gray-900 text-center" id="pdf-fallback" style="display: none;">
                        <div>
                            <svg class="w-16 h-16 mx-auto text-gray-500 mb-4" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M14,2H6A2,2 0 0,0 4,4V20A2,2 0 0,0 6,22H18A2,2 0 0,0 20,20V8L14,2M18,20H6V4H13V9H18V20Z" />
                            </svg>
                            <h3 class="text-lg font-semibold text-gray-300 mb-2">
                                @if($attachment->needsConversion())
                                    Converted Document Preview Unavailable
                                @else
                                    PDF Preview Unavailable
                                @endif
                            </h3>
                            <p class="text-gray-400 mb-4">
                                @if($attachment->needsConversion())
                                    Your browser cannot display the converted PDF inline.
                                @else
                                    Your browser cannot display this PDF inline.
                                @endif
                            </p>
                            <a href="{{ $viewUrl }}" target="_blank" 
                               class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded mr-2">
                                Open in New Tab
                            </a>
                            <a href="{{ $downloadUrl }}" 
                               class="bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-4 rounded">
                                Download Original
                            </a>
                        </div>
                    </div>
                </div>
                
                <script>
                    // Check if PDF loaded successfully
                    setTimeout(function() {
                        const embed = document.querySelector('embed');
                        if (!embed || embed.offsetHeight === 0) {
                            document.getElementById('pdf-fallback').style.display = 'flex';
                            embed.style.display = 'none';
                        }
                    }, 3000);
                </script>
                
            @elseif(in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp']))
                {{-- Image Viewer --}}
                <div class="flex justify-center p-4">
                    <img src="{{ $viewUrl }}" 
                         alt="{{ $attachment->original_filename }}"
                         class="max-w-full max-h-screen object-contain rounded">
                </div>
                
            @elseif($extension === 'txt')
                {{-- Text File Viewer --}}
                <div class="p-6">
                    <iframe src="{{ $viewUrl }}" 
                            class="w-full border-0 bg-white rounded"
                            style="height: 60vh;"
                            title="{{ $attachment->original_filename }}">
                    </iframe>
                </div>
                
            @else
                {{-- Unsupported File Type --}}
                <div class="flex items-center justify-center p-12 text-center">
                    <div>
                        <svg class="w-16 h-16 mx-auto text-gray-500 mb-4" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M13,9V3.5L18.5,9M6,2C4.89,2 4,2.89 4,4V20A2,2 0 0,0 6,22H18A2,2 0 0,0 20,20V8L14,2H6Z" />
                        </svg>
                        <h3 class="text-lg font-semibold text-gray-300 mb-2">Preview Not Available</h3>
                        <p class="text-gray-400 mb-4">This file type cannot be previewed in the browser.</p>
                        <a href="{{ $downloadUrl }}" 
                           class="bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-4 rounded">
                            Download File
                        </a>
                    </div>
                </div>
            @endif
        </div>
    </div>
</x-admin-layout>